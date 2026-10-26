<?php 
session_start();

// Load database connection using reliable absolute path
require_once dirname(__DIR__, 2) . "/config/conn.php";

/** @var mysqli $conn */ // Tells VS Code that $conn exists and is a mysqli object

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: ../../index.php");
        exit();
    }

    // Fixed: Selected 'username' instead of non-existent column 'name'
    $stmt = $conn->prepare("SELECT id, email, username, password FROM users WHERE email = ?");

    // Check if prepare() failed to prevent calling bind_param on bool
    if (!$stmt) {
        die("Database preparation failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['username']; // Updated to match table schema
        
            $stmt->close();
            header("Location: ../../frontend/admin/dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid email or password.";
        }
    } else {
        $_SESSION['error'] = "Invalid email or password.";
    }

    $stmt->close();

    header("Location: ../../index.php");
    exit();
}