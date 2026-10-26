<?php
session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../frontend/admin/settings.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? null;
$back   = "../../frontend/admin/settings.php";

// 1. Handle Logo Upload
if (isset($_FILES['org_logo']) && $_FILES['org_logo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = dirname(__DIR__, 2) . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = strtolower(pathinfo($_FILES['org_logo']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $newName = 'logo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['org_logo']['tmp_name'], $uploadDir . $newName)) {
            save_setting($conn, 'org_logo', 'uploads/' . $newName, 'string', $userId);
        }
    }
}

// 2. Field map
$fields = [
    'org_name'             => ['string', true,  fn($v) => mb_strlen($v) <= 120],
    'org_website'          => ['string', false, fn($v) => $v === '' || filter_var($v, FILTER_VALIDATE_URL)],
    'org_phone'            => ['string', false, fn($v) => mb_strlen($v) <= 40],
    'org_email'            => ['string', false, fn($v) => $v === '' || filter_var($v, FILTER_VALIDATE_EMAIL)],
    'org_address'          => ['string', false, fn($v) => mb_strlen($v) <= 255],
    'currency_symbol'      => ['string', true,  fn($v) => $v !== '' && mb_strlen($v) <= 8],
    'currency_position'    => ['string', true,  fn($v) => in_array($v, ['before', 'after'], true)],
    'receipt_footer'       => ['string', false, fn($v) => mb_strlen($v) <= 255],
    'receipt_show_logo'    => ['bool',   false, null],
    'tax_enabled'          => ['bool',   false, null],
    'tax_rate'             => ['float',  false, fn($v) => is_numeric($v) && $v >= 0 && $v <= 100],
    'tax_included'         => ['bool',   false, null],
    'low_stock_threshold'  => ['int',    false, fn($v) => ctype_digit((string)$v) && (int)$v >= 0],
    'default_landing'      => ['string', false, fn($v) => preg_match('/^[a-z0-9_\-]+\.php$/i', $v)],
    'date_format'          => ['string', false, fn($v) => mb_strlen($v) <= 30],
    'time_format'          => ['string', false, fn($v) => mb_strlen($v) <= 30],
];

$errors = [];
$saved  = 0;

try {
    $conn->begin_transaction();

    foreach ($fields as $key => [$type, $required, $validator]) {
        if ($type === 'bool') {
            $value = isset($_POST[$key]) && $_POST[$key] !== '' ? '1' : '0';
        } else {
            $value = trim((string)($_POST[$key] ?? ''));
        }

        if ($required && $value === '') {
            $errors[] = "Field '{$key}' is required.";
            continue;
        }

        if ($validator !== null && $value !== '' && !$validator($value)) {
            $errors[] = "Invalid value for '{$key}'.";
            continue;
        }

        $dbType = match ($type) {
            'int'   => 'int',
            'float' => 'float',
            'bool'  => 'bool',
            default => 'string',
        };

        if (!save_setting($conn, $key, $value, $dbType, $userId)) {
            $errors[] = "Failed to save '{$key}'.";
            continue;
        }
        $saved++;
    }

    if ($errors) {
        $conn->rollback();
        $_SESSION['error'] = "Some settings could not be saved: " . implode(' ', array_slice($errors, 0, 3));
    } else {
        $conn->commit();
        $_SESSION['success'] = "Settings saved successfully ({$saved} fields updated).";
    }
} catch (Throwable $e) {
    $conn->rollback();
    error_log("Settings Save Error: " . $e->getMessage());
    $_SESSION['error'] = "An unexpected error occurred while saving settings.";
}

header("Location: $back");
exit();