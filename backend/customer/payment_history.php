<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

function reply($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

$customerId = (int)($_GET['customer_id'] ?? 0);
$fromDate   = $_GET['from_date'] ?? '';
$toDate     = $_GET['to_date']   ?? '';

if ($customerId <= 0) reply(false, 'Invalid customer ID.');

try {
    // ==========================================
    // 1. Fetch Credit Sales (with Date Filters)
    // ==========================================
    $dateSql = "";
    $types = "i";
    $params = [$customerId];

    if ($fromDate) {
        $dateSql .= " AND DATE(created_at) >= ?";
        $types .= "s";
        $params[] = $fromDate;
    }
    if ($toDate) {
        $dateSql .= " AND DATE(created_at) <= ?";
        $types .= "s";
        $params[] = $toDate;
    }

    $stmt = $conn->prepare(
        "SELECT id, invoice_no, total_amount, paid_amount, due_amount,
                payment_type, status, created_at, due_date
         FROM sales
         WHERE customer_id = ? $dateSql
         ORDER BY created_at DESC"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ==========================================
    // 2. Fetch Payments (with Date Filters)
    // ==========================================
    // We filter payments based on when the payment was made (paid_at)
    $dateSqlPay = "";
    $typesPay = "i";
    $paramsPay = [$customerId];

    if ($fromDate) {
        $dateSqlPay .= " AND DATE(p.paid_at) >= ?";
        $typesPay .= "s";
        $paramsPay[] = $fromDate;
    }
    if ($toDate) {
        $dateSqlPay .= " AND DATE(p.paid_at) <= ?";
        $typesPay .= "s";
        $paramsPay[] = $toDate;
    }

    $stmt = $conn->prepare(
        "SELECT p.payment_id, p.amount, p.payment_method, p.notes, p.paid_at,
                s.invoice_no
         FROM customer_payments p
         JOIN sales s ON s.id = p.sale_id
         WHERE p.customer_id = ? $dateSqlPay
         ORDER BY p.paid_at DESC"
    );
    $stmt->bind_param($typesPay, ...$paramsPay);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ==========================================
    // 3. Calculate Summary
    // ==========================================
    $totalSales = array_sum(array_column($sales, 'total_amount'));
    $totalPaid  = array_sum(array_column($sales, 'paid_amount'));
    $totalDue   = array_sum(array_column($sales, 'due_amount'));

    reply(true, 'OK', [
        'sales'    => $sales,
        'payments' => $payments,
        'summary'  => [
            'total_sales' => round($totalSales, 2),
            'total_paid'  => round($totalPaid, 2),
            'total_due'   => round($totalDue, 2),
        ],
    ]);
} catch (Throwable $e) {
    error_log("History Error: " . $e->getMessage());
    reply(false, $e->getMessage());
}