<?php
session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId <= 0) {
    http_response_code(400);
    exit('Invalid customer ID.');
}

// Fetch customer
$stmt = $conn->prepare("SELECT customer_name FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) { http_response_code(404); exit('Customer not found.'); }

// Fetch sales
$stmt = $conn->prepare(
    "SELECT invoice_no, total_amount, paid_amount, due_amount,
            due_date, status, created_at
     FROM sales WHERE customer_id = ? ORDER BY created_at"
);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch payments
$stmt = $conn->prepare(
    "SELECT p.paid_at, s.invoice_no, p.amount, p.payment_method, p.notes
     FROM customer_payments p
     JOIN sales s ON s.id = p.sale_id
     WHERE p.customer_id = ?
     ORDER BY p.paid_at"
);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---- Output CSV ----
$filename = 'ledger_' . preg_replace('/[^a-z0-9]+/i', '_', $customer['customer_name'])
          . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Customer Ledger']);
fputcsv($out, ['Customer', $customer['customer_name']]);
fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
fputcsv($out, []);
fputcsv($out, ['SALES']);
fputcsv($out, ['Invoice', 'Date', 'Total', 'Paid', 'Due', 'Due Date', 'Status']);
foreach ($sales as $s) {
    fputcsv($out, [
        $s['invoice_no'],
        $s['created_at'],
        number_format((float)$s['total_amount'], 2),
        number_format((float)$s['paid_amount'], 2),
        number_format((float)$s['due_amount'], 2),
        $s['due_date'] ?? '',
        $s['status'],
    ]);
}
fputcsv($out, []);
fputcsv($out, ['PAYMENTS']);
fputcsv($out, ['Date', 'Invoice', 'Amount', 'Method', 'Notes']);
foreach ($payments as $p) {
    fputcsv($out, [
        $p['paid_at'],
        $p['invoice_no'],
        number_format((float)$p['amount'], 2),
        $p['payment_method'],
        $p['notes'] ?? '',
    ]);
}
fclose($out);
exit();