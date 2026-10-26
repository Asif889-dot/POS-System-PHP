<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__, 2) . "/config/conn.php";
    /** @var mysqli $conn */

    // Retrieve and sanitize inputs
    $productId   = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $productName = trim($_POST['product_name'] ?? '');
    $categoryId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $sku         = trim($_POST['sku'] ?? '');
    $price       = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
    $stock       = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $description = trim($_POST['description'] ?? '');
    $status      = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    // Basic Validation
    if (empty($productName) || empty($sku) || !$categoryId) {
        $_SESSION['error'] = "Product Name, Category, and SKU are required fields.";
        header("Location: ../../frontend/admin/product.php");
        exit();
    }

    // Handle Image Upload
    $imagePath = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['product_image']['tmp_name'];
        $fileName      = $_FILES['product_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadDir = dirname(__DIR__, 2) . '/uploads/products/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid('prod_', true) . '.' . $fileExtension;
            $destPath    = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imagePath = 'uploads/products/' . $newFileName;
            } else {
                $_SESSION['error'] = "Failed to upload image.";
                header("Location: ../../frontend/admin/product.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid image file format.";
            header("Location: ../../frontend/admin/product.php");
            exit();
        }
    }

    try {
        if ($productId) {
            // Update Existing Product
            if ($imagePath) {
                $stmt = $conn->prepare("UPDATE products SET category_id = ?, name = ?, sku = ?, price = ?, stock = ?, description = ?, image = ?, status = ? WHERE id = ?");
                $stmt->bind_param("issdissii", $categoryId, $productName, $sku, $price, $stock, $description, $imagePath, $status, $productId);
            } else {
                $stmt = $conn->prepare("UPDATE products SET category_id = ?, name = ?, sku = ?, price = ?, stock = ?, description = ?, status = ? WHERE id = ?");
                $stmt->bind_param("issdisii", $categoryId, $productName, $sku, $price, $stock, $description, $status, $productId);
            }
        } else {
            // Create New Product
            $stmt = $conn->prepare("INSERT INTO products (category_id, name, sku, price, stock, description, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdissi", $categoryId, $productName, $sku, $price, $stock, $description, $imagePath, $status);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "Product saved successfully.";
        } else {
            $_SESSION['error'] = "Database write error: " . $stmt->error;
        }
        $stmt->close();

    } catch (Exception $e) {
        error_log("Product Save Error: " . $e->getMessage());
        $_SESSION['error'] = "An unexpected error occurred while saving the product.";
    }

    header("Location: ../../frontend/admin/product.php");
    exit();
} else {
    header("Location: ../../frontend/admin/product.php");
    exit();
}