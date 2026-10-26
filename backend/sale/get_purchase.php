<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

try {
    // Get Purchase Info
    $stmt = $conn->prepare("SELECT * FROM purchases WHERE purchase_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$purchase) { echo json_encode(['success' => false, 'message' => 'Purchase not found']); exit; }

    // Get Items
    $stmt = $conn->prepare("
        SELECT pi.*, p.name as product_name 
        FROM purchase_items pi 
        JOIN products p ON p.id = pi.product_id 
        WHERE pi.purchase_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $purchase['items'] = $items;

    echo json_encode(['success' => true, 'data' => $purchase]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}