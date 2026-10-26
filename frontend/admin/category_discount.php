<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM category_discounts WHERE discount_id = $id");
        $_SESSION['success'] = "Discount deleted.";
        header("Location: category_discount.php"); exit;
    } else {
        $id         = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $catId      = (int)$_POST['category_id'];
        $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $type       = $_POST['discount_type'];
        $value      = (float)$_POST['discount_value'];
        $status     = isset($_POST['status']) ? 1 : 0;

        if ($id) {
            $stmt = $conn->prepare("UPDATE category_discounts SET category_id=?, customer_id=?, discount_type=?, discount_value=?, status=? WHERE discount_id=?");
            $stmt->bind_param("iisdis", $catId, $customerId, $type, $value, $status, $id);
        } else {
            // Remove existing rule for this combination to avoid duplicates
            if ($customerId) {
                $conn->query("UPDATE category_discounts SET status = 0 WHERE category_id = $catId AND customer_id = $customerId");
            } else {
                $conn->query("UPDATE category_discounts SET status = 0 WHERE category_id = $catId AND customer_id IS NULL");
            }
            
            $stmt = $conn->prepare("INSERT INTO category_discounts (category_id, customer_id, discount_type, discount_value, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdi", $catId, $customerId, $type, $value, $status);
        }
        $stmt->execute();
        $_SESSION['success'] = "Discount saved successfully.";
        header("Location: category_discount.php"); exit;
    }
}

// Load Data
$categories = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");
$customers = $conn->query("SELECT customer_id, customer_name FROM customers WHERE status = 1 ORDER BY customer_name ASC");

$discounts = $conn->query("
    SELECT d.*, c.name as category_name, cu.customer_name 
    FROM category_discounts d 
    JOIN categories c ON c.id = d.category_id 
    LEFT JOIN customers cu ON cu.customer_id = d.customer_id
    WHERE d.status = 1
    ORDER BY c.name ASC, cu.customer_name ASC
")->fetch_all(MYSQLI_ASSOC);

// Edit Mode
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM category_discounts WHERE discount_id = $id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Category Discounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/customer_discount.css">
</head>
<body class="bg-light">
    <div class="d-flex">
        <?php include_once "../../config/sidebar.php"; ?>
        <main class="flex-grow-1 p-3 p-md-4 main-content">
            <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Customer Category Discounts</h2>
                    <p class="text-muted mb-0">Apply specific category discounts to individual customers.</p>
                </div>
            </header>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Form -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-0 pt-3">
                            <h5 class="fw-bold mb-0"><?= $edit ? 'Edit Discount' : 'Add Discount' ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['discount_id'] ?>"><?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Customer</label>
                                    <select name="customer_id" class="form-select">
                                        <option value="">All Customers (Global)</option>
                                        <?php foreach($customers as $cu): ?>
                                            <option value="<?= $cu['customer_id'] ?>" <?= ($edit && $edit['customer_id'] == $cu['customer_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cu['customer_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Leave blank to apply to everyone.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Category</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php foreach($categories as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($edit && $edit['category_id'] == $c['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Type</label>
                                        <select name="discount_type" class="form-select">
                                            <option value="percentage" <?= ($edit && $edit['discount_type']=='percentage')?'selected':'' ?>>Percentage (%)</option>
                                            <option value="fixed" <?= ($edit && $edit['discount_type']=='fixed')?'selected':'' ?>>Fixed Amount</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Value</label>
                                        <input type="number" name="discount_value" step="0.01" min="0" class="form-control" required value="<?= $edit['discount_value'] ?? '' ?>">
                                    </div>
                                </div>

                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="status" id="status" value="1" <?= (!$edit || $edit['status']==1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="status">Active</label>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary w-100">Save</button>
                                    <?php if ($edit): ?><a href="category_discount.php" class="btn btn-light w-100">Cancel</a><?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- List -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Category</th>
                                        <th>Discount</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($discounts)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No category discounts found.</td></tr>
                                    <?php else: foreach ($discounts as $d): ?>
                                        <tr>
                                            <td>
                                                <?php if ($d['customer_id']): ?>
                                                    <span class="badge bg-primary-subtle text-primary"><i class="bi bi-person me-1"></i><?= htmlspecialchars($d['customer_name']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary">All Customers</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold"><?= htmlspecialchars($d['category_name']) ?></td>
                                            <td>
                                                <span class="badge <?= $d['discount_type']=='percentage' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                                                    <?= $d['discount_type']=='percentage' ? $d['discount_value'].'%' : 'Rs.'.$d['discount_value'] ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="?edit=<?= $d['discount_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this discount?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $d['discount_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>