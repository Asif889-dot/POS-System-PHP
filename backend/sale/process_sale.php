<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

function reply(bool $success, string $message, ?array $data = null): void {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

register_shutdown_function(function() use (&$conn) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (isset($conn) && $conn instanceof mysqli) {
            try { $conn->rollback(); } catch (Throwable $e) {}
        }
        echo json_encode(['success' => false, 'message' => 'PHP Fatal Error: ' . $error['message']]);
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(false, 'Invalid request method.');
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || empty($body['items']) || !is_array($body['items'])) {
    reply(false, 'No items in cart.');
}

$items         = $body['items'];
$totalAmount   = (float)($body['total_amount'] ?? 0);
$paidAmount    = (float)($body['paid_amount']  ?? 0);
$paymentMethod = $body['payment_method'] ?? 'cash';
$customerId    = !empty($body['customer_id']) ? (int)$body['customer_id'] : null;
$dueDate       = !empty($body['due_date'])    ? $body['due_date']         : null;

if (!in_array($paymentMethod, ['cash','card','digital'], true)) {
    $paymentMethod = 'cash';
}
if ($totalAmount <= 0) reply(false, 'Invalid total amount.');
if ($paidAmount < 0) reply(false, 'Paid amount cannot be negative.');

$dueAmount    = round(max(0, $totalAmount - $paidAmount), 2);
$paymentType  = $dueAmount <= 0 ? 'paid' : ($paidAmount <= 0 ? 'udhar' : 'partial');
$saleStatus   = $dueAmount <= 0 ? 'completed' : ($paidAmount <= 0 ? 'pending' : 'partial');
$changeAmount = round(max(0, $paidAmount - $totalAmount), 2);

if ($dueAmount > 0 && !$customerId) reply(false, 'Credit (udhar) sales require a customer.');

$customerName = 'Walk-in Customer';
if ($customerId !== null) {
    $stmt = $conn->prepare("SELECT customer_name FROM customers WHERE customer_id = ? AND status = 1");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $custRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$custRow) reply(false, 'Selected customer is invalid or inactive.');
    $customerName = $custRow['customer_name'];
}

try {
    $conn->begin_transaction();

    // 1. INSERT SALE
    $invoiceNo = 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $stmt = $conn->prepare(
        "INSERT INTO sales
           (invoice_no, customer_id, total_amount, paid_amount, change_amount,
            due_amount, due_date, payment_method, payment_type, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("siddddssss", $invoiceNo, $customerId, $totalAmount, $paidAmount, $changeAmount, $dueAmount, $dueDate, $paymentMethod, $paymentType, $saleStatus);
    $stmt->execute();
    $saleId = $conn->insert_id;
    $stmt->close();

    // 2. LINE ITEMS + STOCK (Capturing Cost Price for Profit/Loss)
    // We also select cost_price to save the exact cost at the time of sale
    $checkStmt = $conn->prepare("SELECT stock, name, cost_price FROM products WHERE id = ? AND status = 1 FOR UPDATE");
    $itemStmt  = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cost_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    
    $receiptItems = [];

    foreach ($items as $it) {
        $productId = (int)($it['product_id'] ?? 0);
        $qty       = (int)($it['quantity']   ?? 0);
        $unitPrice = (float)($it['unit_price'] ?? 0);

        if ($productId <= 0 || $qty <= 0 || $unitPrice <= 0) throw new Exception('Invalid line item.');

        $checkStmt->bind_param("i", $productId);
        $checkStmt->execute();
        $row = $checkStmt->get_result()->fetch_assoc();

        if (!$row) throw new Exception("Product #{$productId} not found or inactive.");
        if ((int)$row['stock'] < $qty) throw new Exception("Insufficient stock for \"{$row['name']}\".");

        $costPrice = (float)($row['cost_price'] ?? 0); // Capture the cost
        $subtotal  = round($unitPrice * $qty, 2);

        // Insert with cost_price
        $itemStmt->bind_param("iiiddd", $saleId, $productId, $qty, $unitPrice, $costPrice, $subtotal);
        $itemStmt->execute();

        $stockStmt->bind_param("ii", $qty, $productId);
        $stockStmt->execute();

        $receiptItems[] = [
            'name'     => $row['name'],
            'qty'      => $qty,
            'price'    => $unitPrice,
            'subtotal' => $subtotal,
        ];
    }
    $checkStmt->close(); $itemStmt->close(); $stockStmt->close();

    // 3. INITIAL PAYMENT RECORD
    if ($paidAmount > 0 && $customerId !== null) {
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $note   = "Initial payment on invoice {$invoiceNo}";
        $stmt = $conn->prepare("INSERT INTO customer_payments (sale_id, customer_id, amount, payment_method, notes, received_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidssi", $saleId, $customerId, $paidAmount, $paymentMethod, $note, $userId);
        $stmt->execute();
        $stmt->close();
    }

    // 4. RECEIPT SETTINGS
    $receiptSettings = [
        'org_name' => 'My Store', 'org_address' => '', 'org_phone' => '', 'org_email' => '',
        'org_logo' => '', 'currency_symbol' => 'Rs.', 'currency_position' => 'before', 'receipt_footer' => 'Thank you!',
        'cashier_name' => '',
        'bill_theme' => 'classic', 'bill_paper' => 'a4', 
        'bill_show_logo' => '1', 'bill_show_header' => '1', 'bill_show_qr' => '1',
        'bill_show_datetime' => '1', 'bill_show_cashier' => '0', 'bill_show_prices' => '1',
        'bill_thank_you' => 'Thank you for shopping with us!', 'bill_footer_note' => ''
    ];

    $checkTable = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $res = $conn->query("SELECT setting_key, setting_value FROM settings");
        if ($res) {
            while ($r = $res->fetch_assoc()) $receiptSettings[$r['setting_key']] = $r['setting_value'];
        }
    }

    if (!empty($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            if ($u) $receiptSettings['cashier_name'] = $u['username'];
            $stmt->close();
        }
    }

    $conn->commit();

    reply(true, 'Sale saved.', [
        'sale_id' => $saleId, 'invoice_no' => $invoiceNo, 'customer_id' => $customerId,
        'customer_name' => $customerName, 'total_amount' => $totalAmount,
        'paid_amount' => $paidAmount, 'change_amount' => $changeAmount,
        'due_amount' => $dueAmount, 'due_date' => $dueDate,
        'payment_method' => $paymentMethod, 'payment_type' => $paymentType,
        'status' => $saleStatus, 'items' => $receiptItems, 'settings' => $receiptSettings,
    ]);

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $e) {}
    reply(false, 'Database error: ' . $e->getMessage());
}