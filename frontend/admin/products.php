<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../../config/conn.php";

/** @var mysqli $conn */

// Fetch active categories for the dropdown
$categoriesResult = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");

// Fetch products for listing table
$productsQuery = "SELECT p.*, c.name AS category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  ORDER BY p.id DESC";
$productsResult = $conn->query($productsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Manage Products</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body class="bg-light">

<div class="d-flex">
  <?php include_once "../../config/sidebar.php"; ?>
  
  <main class="flex-grow-1 p-3 p-md-4 main-content">
    
    <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
      <div>
        <h2 class="fw-bold text-dark mb-1">Product Management</h2>
        <p class="text-muted mb-0">Add, track, and modify store inventory items.</p>
      </div>
    </header>

    <!-- Session Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row g-4">
      
      <!-- Product Entry Form -->
      <div class="col-12 col-lg-5 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3">
          <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
            <h5 class="fw-bold text-dark mb-0" id="formTitle">
              <i class="bi bi-box-seam text-primary me-2"></i>Add New Product
            </h5>
          </div>
          <div class="card-body p-3 p-md-4">
            
            <form id="productForm" action="../../backend/product/add_product.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
              
              <input type="hidden" id="productId" name="product_id" value="">

              <!-- Product Name -->
              <div class="mb-3">
                <label for="productName" class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="productName" name="product_name" placeholder="e.g. Wireless Mouse" required>
              </div>

              <!-- Category Select -->
              <div class="mb-3">
                <label for="categoryId" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                <select class="form-select" id="categoryIdSelect" name="category_id" required>
                  <option value="" disabled selected>Select Category</option>
                  <?php if ($categoriesResult && $categoriesResult->num_rows > 0): ?>
                    <?php while ($cat = $categoriesResult->fetch_assoc()): ?>
                      <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?></option>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </select>
              </div>

              <!-- SKU & Price Row -->
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label for="sku" class="form-label fw-semibold">SKU / Code <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="sku" name="sku" placeholder="PRD-001" required>
                </div>
                <div class="col-6">
                  <label for="price" class="form-label fw-semibold">Price (Rs.) <span class="text-danger">*</span></label>
                  <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" placeholder="0.00" required>
                </div>
              </div>

              <!-- Stock & Status Row -->
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label for="stock" class="form-label fw-semibold">Stock Level</label>
                  <input type="number" min="0" class="form-control" id="stock" name="stock" value="0">
                </div>
                <div class="col-6">
                  <label for="status" class="form-label fw-semibold">Status</label>
                  <select class="form-select" id="status" name="status">
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
              </div>

              <!-- Description -->
              <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2" placeholder="Item specs or detail..."></textarea>
              </div>

              <!-- Image Upload -->
              <div class="mb-4">
                <label for="productImage" class="form-label fw-semibold">Product Image</label>
                <input type="file" class="form-control" id="productImage" name="product_image" accept="image/*">
                <div class="mt-2 text-center" id="imagePreviewContainer" style="display: none;">
                  <img id="imagePreview" src="#" alt="Preview" class="img-thumbnail rounded-3" style="max-height: 100px;">
                </div>
              </div>

              <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="button" class="btn btn-light w-100 w-md-auto me-md-2" id="resetBtn" onclick="resetProductForm()">Cancel</button>
                <button type="submit" class="btn btn-primary w-100 w-md-auto" id="saveBtn">Save Product</button>
              </div>

            </form>
          </div>
        </div>
      </div>

      <!-- Products Data Table -->
      <div class="col-12 col-lg-7 col-xl-8">
        <div class="card border-0 shadow-sm rounded-3">
          <div class="card-header bg-white border-0 py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Products List</h5>
          </div>

          <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col" class="ps-3 ps-md-4">Item</th>
                  <th scope="col">Category</th>
                  
                  <th scope="col">Price</th>
                  <th scope="col">Stock</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="text-end pe-3 pe-md-4">Action</th>
                </tr>
              </thead>
              <tbody id="productTableBody">
                <?php if ($productsResult && $productsResult->num_rows > 0): ?>
                  <?php while ($prod = $productsResult->fetch_assoc()): ?>
                    <tr>
                      <td class="ps-3 ps-md-4">
                        <div class="d-flex align-items-center">
                          <?php if (!empty($prod['image'])): ?>
                            <img src="../../<?= htmlspecialchars($prod['image']); ?>" alt="Product" class="rounded-2 me-2 object-fit-cover" width="38" height="38">
                          <?php else: ?>
                            <div class="bg-light text-secondary rounded-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                              <i class="bi bi-box"></i>
                            </div>
                          <?php endif; ?>
                          <div>
                            <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 140px;"><?= htmlspecialchars($prod['name']); ?></h6>
                            <small class="text-muted"><?= htmlspecialchars($prod['sku']); ?></small>
                          </div>
                        </div>
                      </td>
                      <td><small><?= htmlspecialchars($prod['category_name'] ?? 'Uncategorized'); ?></small></td>
                      <td class="fw-semibold">Rs <?= number_format($prod['price'], 2); ?></td>
                      <td>
                        <span class="badge <?= $prod['stock'] > 5 ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning'; ?>">
                          <?= $prod['stock']; ?> units
                        </span>
                      </td>
                      <td>
                        <?= $prod['status'] == 1 
                          ? '<span class="badge bg-success-subtle text-success">Active</span>' 
                          : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>'; ?>
                      </td>
                      <td class="text-end pe-3 pe-md-4">
                        <button class="btn btn-sm btn-outline-primary" onclick='editProduct(<?= json_encode($prod, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                          <i class="bi bi-pencil"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No products recorded.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

    </div>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/product.js"></script>
</body>
</html>