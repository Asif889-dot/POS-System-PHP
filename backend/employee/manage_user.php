<?php
session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId       = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $username     = trim($_POST['username'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = $_POST['role'] ?? 'user';

    if (empty($username) || empty($organization) || empty($email)) {
        $_SESSION['error'] = "Username, Organization, and Email are required fields.";
        header("Location: ../../frontend/admin/employees.php");
        exit();
    }

    try {
        if ($userId) {
            // Update Existing User
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, organization = ?, email = ?, address = ?, phone = ?, password = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssssssi", $username, $organization, $email, $address, $phone, $hashedPassword, $role, $userId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, organization = ?, email = ?, address = ?, phone = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssssi", $username, $organization, $email, $address, $phone, $role, $userId);
            }
        } else {
            // Create New User
            if (empty($password)) {
                $_SESSION['error'] = "Password is required for new accounts.";
                header("Location: ../../frontend/admin/employees.php");
                exit();
            }
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, organization, email, address, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $organization, $email, $address, $phone, $hashedPassword, $role);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "User account saved successfully.";
        } else {
            $_SESSION['error'] = "Database error: " . $stmt->error;
        }
        $stmt->close();

    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: ../../frontend/admin/employees.php");
    exit();
}