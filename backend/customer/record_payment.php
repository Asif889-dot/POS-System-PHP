<?php
session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../frontend/admin/customer_payments.php");
    exit();
}

$back = "../../frontend/admin/customer_payments.php";
$saleId = (int)($_POST['sale_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$method = $_POST['payment_method'] ?? 'cash';
$notes  = trim($_POST['notes'] ?? '');
$notes  = $notes !== '' ? $notes : null;
$userId = $_SESSION['user_id'] ?? null;

if ($saleId <= 0 || $amount <= 0) {
    $_SESSION['error'] = "Invalid payment amount or sale.";
    header("Location: $back"); exit();
}
if (!in_array($method, ['cash','card','digital','bank_transfer','other'], true)) {
    $method = 'cash';
}

try {
    $conn->begin_transaction();

    // Lock the sale row
    $stmt = $conn->prepare(
        "SELECT id, customer_id, total_amount, paid_amount, due_amount, status, invoice_no
         FROM sales WHERE id = ? FOR UPDATE"
    );
    $stmt->bind_param("i", $saleId);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sale) throw new Exception("Sale not found.");
    if ((float)$sale['due_amount'] <= 0) throw new Exception("This invoice is already fully paid.");
    if ($amount > (float)$sale['due_amount']) {
        throw new Exception("Amount exceeds outstanding due of Rs." . number_format($sale['due_amount'], 2) . ".");
    }
    if (!$sale['customer_id']) throw new Exception("Cannot record payment: sale has no customer.");

    // Insert payment record
    $stmt = $conn->prepare(
        "INSERT INTO customer_payments
           (sale_id, customer_id, amount, payment_method, notes, received_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iidssi",
        $saleId, $sale['customer_id'], $amount, $method, $notes, $userId);
    $stmt->execute();
    $stmt->close();

    // Update sale totals
    $newPaid = round((float)$sale['paid_amount'] + $amount, 2);
    $newDue  = round((float)$sale['total_amount'] - $newPaid, 2);
    if ($newDue < 0) $newDue = 0;

    $newStatus = $newDue <= 0 ? 'completed' : 'partial';

    $stmt = $conn->prepare(
        "UPDATE sales SET paid_amount = ?, due_amount = ?, status = ? WHERE id = ?"
    );
    $stmt->bind_param("ddsi", $newPaid, $newDue, $newStatus, $saleId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    $_SESSION['success'] = "Payment of Rs." . number_format($amount, 2) .
                           " recorded for invoice {$sale['invoice_no']}. " .
                           ($newDue > 0 ? "Remaining due: Rs." . number_format($newDue, 2) : "Invoice fully settled.");
    header("Location: $back"); exit();

} catch (Throwable $e) {
    $conn->rollback();
    error_log("Payment Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header("Location: $back"); exit();
}