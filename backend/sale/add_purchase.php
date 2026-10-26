<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items provided.']); exit;
}

$supplier = trim($data['supplier_name'] ?? '');
$invoice  = trim($data['invoice_no'] ?? '');
$date     = $data['purchased_at'] ?? date('Y-m-d');
$notes    = trim($data['notes'] ?? '');
$userId   = $_SESSION['user_id'] ?? null;
$items    = $data['items'];

if ($supplier === '') {
    echo json_encode(['success' => false, 'message' => 'Supplier name is required.']); exit;
}

try {
    $conn->begin_transaction();

    // Calculate total
    $totalAmount = 0;
    foreach ($items as $it) {
        $totalAmount += ($it['quantity'] * $it['cost_price']);
    }

    // 1. Insert Purchase Record
    $stmt = $conn->prepare("INSERT INTO purchases (supplier_name, invoice_no, total_amount, notes, purchased_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdssi", $supplier, $invoice, $totalAmount, $notes, $date, $userId);
    $stmt->execute();
    $purchaseId = $conn->insert_id;
    $stmt->close();

    // 2. Insert Purchase Items & Update Stock
    $itemStmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, cost_price, subtotal) VALUES (?, ?, ?, ?, ?)");
    
    // UPDATED: Now updates 'cost_price' instead of 'price' to properly track profit/loss
    $stockStmt = $conn->prepare("UPDATE products SET stock = stock + ?, cost_price = ? WHERE id = ?");

    foreach ($items as $it) {
        $pid = (int)$it['product_id'];
        $qty = (int)$it['quantity'];
        $cost = (float)$it['cost_price'];
        $subtotal = $qty * $cost;

        // Insert item
        $itemStmt->bind_param("iiidd", $purchaseId, $pid, $qty, $cost, $subtotal);
        $itemStmt->execute();

        // Update stock and the latest cost price
        $stockStmt->bind_param("idi", $qty, $cost, $pid);
        $stockStmt->execute();
    }
    $itemStmt->close();
    $stockStmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Purchase recorded and stock updated.']);

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}