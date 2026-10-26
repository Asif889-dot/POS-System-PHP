<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . "/../../config/conn.php";

/** @var mysqli $conn */

// Fetch all categories for the table
$categoriesResult = $conn->query("SELECT * FROM categories ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Manage Categories</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/category.css">
</head>

<body class="bg-light">

  <div class="d-flex">
    <?php include_once "../../config/sidebar.php"; ?>

    <main class="flex-grow-1 p-3 p-md-4 main-content">

      <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
        <div>
          <h2 class="fw-bold text-dark mb-1">Category Management</h2>
          <p class="text-muted mb-0">Create, view, and manage product categories for your inventory.</p>
        </div>
      </header>

      <!-- Session Alerts -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <div class="row g-4">

        <!-- Category Entry Form Card -->
        <div class="col-12 col-lg-5 col-xl-4">
          <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
              <h5 class="fw-bold text-dark mb-0" id="formTitle">
                <i class="bi bi-plus-circle text-primary me-2"></i>Add New Category
              </h5>
            </div>
            <div class="card-body p-3 p-md-4">

              <form id="categoryForm" action="../../backend/category/add_category.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>

                <input type="hidden" id="categoryId" name="category_id" value="">

                <!-- Category Name -->
                <div class="mb-3">
                  <label for="categoryName" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-tag"></i></span>
                    <input type="text" class="form-control" id="categoryName" name="category_name" placeholder="e.g. Beverages, Electronics" required>
                    <div class="invalid-feedback">Please enter a valid category name.</div>
                  </div>
                </div>

                <!-- Description -->
                <div class="mb-3">
                  <label for="categoryDescription" class="form-label fw-semibold">Description</label>
                  <textarea class="form-control" id="categoryDescription" name="category_description" rows="3" placeholder="Brief details about items in this category..."></textarea>
                </div>

                <!-- Category Image Upload -->
                <div class="mb-3">
                  <label for="categoryImage" class="form-label fw-semibold">Category Image</label>
                  <input type="file" class="form-control" id="categoryImage" name="category_image" accept="image/*">
                  <div class="mt-2 text-center" id="imagePreviewContainer" style="display: none;">
                    <img id="imagePreview" src="#" alt="Preview" class="img-thumbnail rounded-3" style="max-height: 120px;">
                  </div>
                </div>

                <!-- Status Select -->
                <div class="mb-4">
                  <label for="categoryStatus" class="form-label fw-semibold">Status</label>
                  <select class="form-select" id="categoryStatus" name="category_status">
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                  <button type="button" class="btn btn-light w-100 w-md-auto me-md-2" id="resetBtn">Cancel</button>
                  <button type="submit" class="btn btn-primary w-100 w-md-auto" id="saveBtn">
                    <i class="bi bi-check-lg me-1"></i> Save Category
                  </button>
                </div>

              </form>
            </div>
          </div>
        </div>

        <!-- Category Data Table -->
        <div class="col-12 col-lg-7 col-xl-8">
          <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 py-3 px-3 px-md-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
              <h5 class="fw-bold text-dark mb-0">Existing Categories</h5>
              <div class="input-group style-search w-auto">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0 bg-light" id="searchTable" placeholder="Search categories...">
              </div>
            </div>

            <div class="table-responsive">
              <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th scope="col" class="ps-3 ps-md-4">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end pe-3 pe-md-4">Action</th>
                  </tr>
                </thead>
                <tbody id="categoryTableBody">
                  <?php if ($categoriesResult && $categoriesResult->num_rows > 0): ?>
                    <?php while ($cat = $categoriesResult->fetch_assoc()): ?>
                      <tr>
                        <td class="ps-3 ps-md-4">
                          <div class="d-flex align-items-center">
                            <?php if (!empty($cat['image'])): ?>
                              <img src="../../<?= htmlspecialchars($cat['image']); ?>" alt="Category" class="rounded-2 me-3 object-fit-cover" width="40" height="40">
                            <?php else: ?>
                              <div class="category-icon-box bg-primary-subtle text-primary rounded-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-folder fs-5"></i>
                              </div>
                            <?php endif; ?>
                            <div>
                              <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($cat['name']); ?></h6>
                            </div>
                          </div>
                        </td>
                        <td>
                          <small class="text-muted"><?= htmlspecialchars($cat['description'] ?? '—'); ?></small>
                        </td>
                        <td>
                          <?php if ($cat['status'] == 1): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                          <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end pe-3 pe-md-4">
                          <!-- Edit Button -->
                          <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editCategory(<?= $cat['id']; ?>, '<?= addslashes(htmlspecialchars($cat['name'])); ?>', '<?= addslashes(htmlspecialchars($cat['description'] ?? '')); ?>', '<?= $cat['status']; ?>')">
                            <i class="bi bi-pencil"></i>
                          </button>

                          <!-- Delete Form (Replaced broken onclick with a secure Form POST) -->
                          <form action="../../backend/category/add_category.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?= $cat['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="4" class="text-center py-4 text-muted">No categories found in database.</td>
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
  <script src="../../assets/js/category.js"></script>
</body>

</html>