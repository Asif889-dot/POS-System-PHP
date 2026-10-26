<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        // --- CREATE OR UPDATE USER ---
        case 'save':
            $userId       = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            $username     = trim($_POST['username'] ?? '');
            $organization = trim($_POST['organization'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $address      = trim($_POST['address'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');
            $role         = $_POST['role'] ?? 'user';
            $status       = isset($_POST['status']) ? (int)$_POST['status'] : 1;
            $password     = $_POST['password'] ?? '';

            if (empty($username) || empty($organization) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Username, Organization, and Email are required.']);
                exit();
            }

            if ($userId) {
                // UPDATE USER
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("UPDATE users SET username=?, organization=?, email=?, address=?, phone=?, password=?, role=?, status=? WHERE id=?");
                    $stmt->bind_param("sssssssii", $username, $organization, $email, $address, $phone, $hashed, $role, $status, $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username=?, organization=?, email=?, address=?, phone=?, role=?, status=? WHERE id=?");
                    $stmt->bind_param("ssssssii", $username, $organization, $email, $address, $phone, $role, $status, $userId);
                }
            } else {
                // CREATE NEW USER
                if (empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'Password is required for new accounts.']);
                    exit();
                }
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (username, organization, email, address, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssi", $username, $organization, $email, $address, $phone, $hashed, $role, $status);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => $userId ? 'User updated successfully!' : 'User created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        // --- FETCH SINGLE USER DATA FOR EDITING ---
        case 'get':
            $userId = (int)($_GET['id'] ?? 0);
            $stmt = $conn->prepare("SELECT id, username, organization, email, address, phone, role, status FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user) {
                echo json_encode(['success' => true, 'data' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
            }
            break;

        // --- DELETE USER ---
        case 'delete':
            $userId = (int)($_POST['id'] ?? 0);
            
            // Protect current logged-in user from self-deletion
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                echo json_encode(['success' => false, 'message' => 'You cannot delete your own logged-in account!']);
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User account deleted.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
            }
            $stmt->close();
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action endpoint.']);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}