<?php
session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

// ==========================================
// HANDLE DELETE ACTION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    
    if ($categoryId > 0) {
        try {
            // Optional: Check if products exist in this category before deleting
            $checkStmt = $conn->prepare("SELECT id FROM products WHERE category_id = ? LIMIT 1");
            $checkStmt->bind_param("i", $categoryId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $_SESSION['error'] = "Cannot delete category because it contains products. Reassign or delete the products first.";
            } else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $categoryId);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Category deleted successfully.";
                } else {
                    $_SESSION['error'] = "Failed to delete category.";
                }
                $stmt->close();
            }
            $checkStmt->close();
        } catch (Throwable $e) {
            error_log("Category Delete Error: " . $e->getMessage());
            $_SESSION['error'] = "Database error: Could not delete category.";
        }
    } else {
        $_SESSION['error'] = "Invalid category ID.";
    }
    
    header("Location: ../../frontend/admin/category.php");
    exit();
}

// ==========================================
// HANDLE ADD / EDIT ACTION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form Field Inputs
    $categoryName = trim($_POST['category_name'] ?? '');
    $description  = trim($_POST['category_description'] ?? $_POST['description'] ?? '');
    $status       = isset($_POST['category_status']) ? (int)$_POST['category_status'] : (int)($_POST['status'] ?? 1);
    $categoryId   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    // Validation
    if (empty($categoryName)) {
        $_SESSION['error'] = "Category name is required.";
        header("Location: ../../frontend/admin/category.php");
        exit();
    }

    // Handle Image Upload
    $imagePath = null;
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['category_image']['tmp_name'];
        $fileName      = $_FILES['category_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadDir = dirname(__DIR__, 2) . '/uploads/categories/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid('cat_', true) . '.' . $fileExtension;
            $destPath    = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imagePath = 'uploads/categories/' . $newFileName;
            } else {
                $_SESSION['error'] = "Failed to save uploaded image.";
                header("Location: ../../frontend/admin/category.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP, GIF.";
            header("Location: ../../frontend/admin/category.php");
            exit();
        }
    }

    // Database Operations
    try {
        if ($categoryId) {
            // Update Category
            if ($imagePath) {
                $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, image = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssii", $categoryName, $description, $imagePath, $status, $categoryId);
            } else {
                $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssii", $categoryName, $description, $status, $categoryId);
            }
        } else {
            // Insert New Category
            $stmt = $conn->prepare("INSERT INTO categories (name, description, image, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $categoryName, $description, $imagePath, $status);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "Category saved successfully.";
        } else {
            $_SESSION['error'] = "Error saving category: " . $stmt->error;
        }
        $stmt->close();

    } catch (Exception $e) {
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An unexpected database error occurred.";
    }

    header("Location: ../../frontend/admin/category.php");
    exit();
} else {
    header("Location: ../../frontend/admin/category.php");
    exit();
}