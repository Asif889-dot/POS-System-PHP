<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

function reply($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

$invoiceNo = trim($_GET['invoice_no'] ?? '');
if (empty($invoiceNo)) {
    reply(false, 'Invoice number is required.');
}

try {
    // 1. Fetch Sale & Customer
    $stmt = $conn->prepare("
        SELECT s.*, c.customer_name 
        FROM sales s 
        LEFT JOIN customers c ON c.customer_id = s.customer_id 
        WHERE s.invoice_no = ?
    ");
    $stmt->bind_param("s", $invoiceNo);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sale) {
        reply(false, 'Sale not found.');
    }

    // 2. Fetch Items
    $stmt = $conn->prepare("
        SELECT si.*, p.name AS product_name 
        FROM sale_items si 
        LEFT JOIN products p ON p.id = si.product_id 
        WHERE si.sale_id = ?
    ");
    $stmt->bind_param("i", $sale['id']);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 3. Fetch Settings
    $S = load_settings($conn);

    // 4. Return Data
    echo json_encode([
        'success'  => true,
        'sale'     => $sale,
        'items'    => $items,
        'settings' => $S
    ]);

} catch (Throwable $e) {
    reply(false, 'Database error: ' . $e->getMessage());
}