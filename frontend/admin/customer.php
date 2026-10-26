<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

// ---- Search ----
$search = trim($_GET['q'] ?? '');

// ---- Load customer list ----
$customers = [];
if ($search !== '') {
    $stmt = $conn->prepare(
        "SELECT * FROM customers
         WHERE customer_name LIKE ? OR customer_business_name LIKE ?
            OR customer_email LIKE ? OR customer_phone LIKE ?
         ORDER BY customer_id DESC"
    );
    $like = '%' . $search . '%';
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
} else {
    $res = $conn->query("SELECT * FROM customers ORDER BY customer_id DESC");
}
if ($res) {
    while ($row = $res->fetch_assoc()) $customers[] = $row;
}

// Helper: escape
function e($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
// Helper: avatar initial (multi-byte safe)
function initial_of($name)
{
    if (!$name) return '?';
    return function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System | Manage Customers</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/customer.css">
</head>

<body class="bg-light">

    <div class="d-flex">
        <?php include_once "../../config/sidebar.php"; ?>

        <main class="flex-grow-1 p-3 p-md-4 main-content">

            <!-- ============ HEADER ============ -->
            <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Customer Management</h2>
                    <p class="text-muted mb-0">Add, track, and manage your customer records.</p>
                </div>
                <form class="d-flex search-bar" method="GET" action="customers.php" role="search">
                    <i class="bi bi-search search-icon"></i>
                    <input class="search-input" type="search" name="q"
                        placeholder="Search name, business, email, phone…"
                        value="<?= e($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="customers.php" class="search-clear" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </header>

            <!-- Session Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= e($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="row g-4">

                <!-- ==================== FORM CARD (LEFT) ==================== -->
                <div class="col-12 col-lg-5 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
                            <h5 class="fw-bold text-dark mb-0" id="formTitle">
                                <i class="bi bi-person-plus text-primary me-2"></i>Add New Customer
                            </h5>
                        </div>
                        <div class="card-body p-3 p-md-4">

                            <form id="customerForm"
                                action="../../backend/customer/customer_save.php"
                                method="POST"
                                class="needs-validation"
                                novalidate>

                                <input type="hidden" id="customerId" name="customer_id" value="">

                                <!-- Name -->
                                <div class="mb-3">
                                    <label for="customerName" class="form-label fw-semibold">
                                        Customer Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="customerName"
                                        name="customer_name" maxlength="100"
                                        placeholder="e.g. Ali Khan" required>
                                </div>

                                <!-- Business -->
                                <div class="mb-3">
                                    <label for="businessName" class="form-label fw-semibold">Business Name</label>
                                    <input type="text" class="form-control" id="businessName"
                                        name="customer_business_name" maxlength="100"
                                        placeholder="e.g. Ali Traders">
                                </div>

                                <!-- Email & Phone Row -->
                                <div class="row g-2 mb-3">
                                    <div class="col-12 col-sm-6">
                                        <label for="email" class="form-label fw-semibold">Email</label>
                                        <input type="email" class="form-control" id="email"
                                            name="customer_email" maxlength="100"
                                            placeholder="name@example.com">
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label for="phone" class="form-label fw-semibold">Phone</label>
                                        <input type="text" class="form-control" id="phone"
                                            name="customer_phone" maxlength="20"
                                            placeholder="0300-1234567">
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="mb-3">
                                    <label for="address" class="form-label fw-semibold">Address</label>
                                    <input type="text" class="form-control" id="address"
                                        name="customer_address" maxlength="255"
                                        placeholder="Street, city…">
                                </div>

                                <!-- Status -->
                                <div class="mb-4">
                                    <label for="status" class="form-label fw-semibold">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="1" selected>Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="button" class="btn btn-light w-100 w-md-auto me-md-2"
                                        onclick="resetCustomerForm()">Cancel</button>
                                    <button type="submit" class="btn btn-primary w-100 w-md-auto" id="saveBtn">
                                        Save Customer
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

                <!-- ==================== CUSTOMER TABLE (RIGHT) ==================== -->
                <div class="col-12 col-lg-7 col-xl-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-0 py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-dark mb-0">Customers List</h5>
                            <?php if ($search !== ''): ?>
                                <small class="text-muted">Filtered by “<?= e($search) ?>”</small>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 ps-md-4">Customer</th>
                                        <th>Business</th>
                                        <th>Contact Info</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3 pe-md-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($customers)): ?>
                                        <?php foreach ($customers as $c): ?>
                                            <tr>
                                                <!-- Customer -->
                                                <td class="ps-3 ps-md-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle me-2">
                                                            <?= e(initial_of($c['customer_name'])); ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 160px;">
                                                                <?= e($c['customer_name']); ?>
                                                            </h6>
                                                            <small class="text-muted">#<?= (int)$c['customer_id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Business -->
                                                <td>
                                                    <?php if (!empty($c['customer_business_name'])): ?>
                                                        <small class="fw-semibold text-secondary"><?= e($c['customer_business_name']); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">—</small>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Contact Info -->
                                                <td>
                                                    <small class="d-block text-truncate" style="max-width: 180px;">
                                                        <i class="bi bi-envelope text-muted me-1"></i>
                                                        <?= !empty($c['customer_email']) ? e($c['customer_email']) : '<span class="text-muted">N/A</span>'; ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="bi bi-telephone me-1"></i>
                                                        <?= !empty($c['customer_phone']) ? e($c['customer_phone']) : 'N/A'; ?>
                                                    </small>
                                                </td>

                                                <!-- Status -->
                                                <td>
                                                    <?= (int)$c['status'] === 1
                                                        ? '<span class="badge bg-success-subtle text-success">Active</span>'
                                                        : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>'; ?>
                                                </td>

                                                <!-- Actions -->
                                                <td class="text-end pe-3 pe-md-4 text-nowrap">
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-primary me-1"
                                                        title="Edit"
                                                        onclick='editCustomer(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>)'>
                                                        <i class="bi bi-pencil"></i>
                                                    </button>

                                                    <form action="../../backend/customer/customer_save.php"
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Delete customer #<?= (int)$c['customer_id']; ?>?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="customer_id" value="<?= (int)$c['customer_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <?= $search !== ''
                                                    ? 'No customers match “' . e($search) . '”.'
                                                    : 'No customers recorded.'; ?>
                                            </td>
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
    <script>
        // ---------- Form helpers ----------
        function resetCustomerForm() {
            const form = document.getElementById('customerForm');
            form.reset();
            document.getElementById('customerId').value = '';
            document.getElementById('formTitle').innerHTML =
                '<i class="bi bi-person-plus text-primary me-2"></i>Add New Customer';
            document.getElementById('saveBtn').textContent = 'Save Customer';
        }

        // ---------- Edit: prefill the form from the row ----------
        function editCustomer(c) {
            document.getElementById('customerId').value = c.customer_id;
            document.getElementById('customerName').value = c.customer_name || '';
            document.getElementById('businessName').value = c.customer_business_name || '';
            document.getElementById('email').value = c.customer_email || '';
            document.getElementById('phone').value = c.customer_phone || '';
            document.getElementById('address').value = c.customer_address || '';
            document.getElementById('status').value = (c.status ?? 1).toString();

            document.getElementById('formTitle').innerHTML =
                '<i class="bi bi-pencil-square text-primary me-2"></i>Edit Customer #' + c.customer_id;
            document.getElementById('saveBtn').textContent = 'Update Customer';

            // Scroll form into view on mobile
            document.querySelector('.card').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // ---------- Bootstrap client-side validation ----------
        document.getElementById('customerForm').addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
    </script>
</body>

</html>