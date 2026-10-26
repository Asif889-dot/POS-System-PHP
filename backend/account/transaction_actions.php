<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        // --- SAVE NEW TRANSACTION ---
        case 'save':
            $type            = $_POST['type'] ?? 'expense';
            $category        = trim($_POST['category'] ?? '');
            $amount          = (float)($_POST['amount'] ?? 0);
            $paymentMethod   = $_POST['payment_method'] ?? 'cash';
            $description     = trim($_POST['description'] ?? '');
            $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');
            $userId          = $_SESSION['user_id'] ?? null;

            if (empty($category) || $amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Valid category and positive amount are required.']);
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, category, amount, payment_method, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdsss", $userId, $type, $category, $amount, $paymentMethod, $description, $transactionDate);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Transaction logged successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        // --- DELETE TRANSACTION ---
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete transaction.']);
            }
            $stmt->close();
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}