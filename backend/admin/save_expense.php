<?php
session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../frontend/admin/expenses.php");
    exit();
}

$category = trim($_POST['category'] ?? '');
$amount   = (float)($_POST['amount'] ?? 0);
$date     = $_POST['expense_date'] ?? date('Y-m-d');
$notes    = trim($_POST['notes'] ?? '');
$userId   = $_SESSION['user_id'] ?? null;

if ($category === '' || $amount <= 0) {
    $_SESSION['error'] = "Category and a valid amount are required.";
    header("Location: ../../frontend/admin/expenses.php");
    exit();
}

try {
    $stmt = $conn->prepare("INSERT INTO expenses (category, amount, expense_date, notes, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdssi", $category, $amount, $date, $notes, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Expense recorded successfully.";
    } else {
        $_SESSION['error'] = "Failed to save expense.";
    }
    $stmt->close();
} catch (Throwable $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

header("Location: ../../frontend/admin/expenses.php");
exit();