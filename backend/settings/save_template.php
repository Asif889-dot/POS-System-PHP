<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'] ?? null;

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received.']);
    exit();
}

$saved = 0;
foreach ($data as $key => $value) {
    // Basic validation
    if (!in_array($key, [
        'bill_theme', 'bill_paper', 'bill_show_logo', 'bill_show_header', 
        'bill_show_qr', 'bill_show_datetime', 'bill_show_cashier', 
        'bill_show_prices', 'bill_thank_you', 'bill_footer_note'
    ])) continue;

    $type = is_bool($value) ? 'bool' : 'string';
    $val  = is_bool($value) ? ($value ? '1' : '0') : (string)$value;

    if (save_setting($conn, $key, $val, $type, $userId)) {
        $saved++;
    }
}

echo json_encode(['success' => true, 'message' => "Saved {$saved} settings."]);