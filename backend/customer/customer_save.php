<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../frontend/admin/customer.php");
    exit();
}

require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

$action = $_POST['action'] ?? 'save';

try {
    // ---------- DELETE ----------
    if ($action === 'delete') {
        $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
        if ($customerId <= 0) {
            $_SESSION['error'] = "Invalid customer ID.";
            header("Location: ../../frontend/admin/customer.php");
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Customer deleted successfully.";
        header("Location: ../../frontend/admin/customer.php");
        exit();
    }

    // ---------- CREATE / UPDATE ----------
    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $name       = trim($_POST['customer_name'] ?? '');
    $business   = trim($_POST['customer_business_name'] ?? '');
    $email      = trim($_POST['customer_email'] ?? '');
    $phone      = trim($_POST['customer_phone'] ?? '');
    $address    = trim($_POST['customer_address'] ?? '');
    $status     = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $status     = ($status === 0) ? 0 : 1;

    // Normalize empty optional values to NULL so UNIQUE constraints behave
    $business = $business !== '' ? $business : null;
    $email    = $email    !== '' ? $email    : null;
    $phone    = $phone    !== '' ? $phone    : null;
    $address  = $address  !== '' ? $address  : null;

    // Validation
    if ($name === '' || mb_strlen($name) > 100) {
        $_SESSION['error'] = "Customer Name is required (max 100 characters).";
        header("Location: ../../frontend/admin/customer.php");
        exit();
    }
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address.";
        header("Location: ../../frontend/admin/customer.php");
        exit();
    }
    if ($phone !== null && mb_strlen($phone) > 20) {
        $_SESSION['error'] = "Phone number is too long (max 20 characters).";
        header("Location: ../../frontend/admin/customer.php");
        exit();
    }

    if ($customerId) {
        $stmt = $conn->prepare(
            "UPDATE customers
                SET customer_name = ?, customer_business_name = ?, customer_email = ?,
                    customer_phone = ?, customer_address = ?, status = ?
              WHERE customer_id = ?"
        );
        $stmt->bind_param("sssssii",
            $name, $business, $email, $phone, $address, $status, $customerId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Customer updated successfully.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO customers
                (customer_name, customer_business_name, customer_email,
                 customer_phone, customer_address, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssi",
            $name, $business, $email, $phone, $address, $status);
        try {
            $stmt->execute();
            $_SESSION['success'] = "Customer created successfully.";
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $_SESSION['error'] = "A customer with that email or phone already exists.";
            } else {
                throw $e;
            }
        }
        $stmt->close();
    }

} catch (Exception $e) {
    error_log("Customer Save Error: " . $e->getMessage());
    $_SESSION['error'] = "An unexpected error occurred while saving the customer.";
}

header("Location: ../../frontend/admin/customer.php");
exit();