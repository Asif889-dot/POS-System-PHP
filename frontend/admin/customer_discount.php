<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

// ---- Filters ----
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';  // all | active | inactive | expiring

// ---- Load customers (include inactive so we can display current one when editing) ----
$customerList = [];
$cr = $conn->query("SELECT customer_id, customer_name, customer_business_name, status
                    FROM customers
                    ORDER BY status DESC, customer_name ASC");
if ($cr) {
    while ($row = $cr->fetch_assoc()) $customerList[] = $row;
}

// ---- Build WHERE for list ----
$conds  = [];
$types  = '';
$params = [];

if ($search !== '') {
    $like    = '%' . $search . '%';
    $conds[] = '(c.customer_name LIKE ? OR c.customer_business_name LIKE ?)';
    $types  .= 'ss';
    array_push($params, $like, $like);
}

// FIXED: Filter now checks BOTH discount status AND customer status
if ($filter === 'active') {
    $conds[] = 'd.status = 1 
                AND c.status = 1 
                AND (d.valid_until IS NULL OR d.valid_until >= CURDATE())';
} elseif ($filter === 'inactive') {
    $conds[] = '(
                    d.status = 0 
                    OR c.status = 0 
                    OR (d.valid_until IS NOT NULL AND d.valid_until < CURDATE())
                )';
} elseif ($filter === 'expiring') {
    $conds[] = 'd.status = 1 
                AND c.status = 1 
                AND d.valid_until IS NOT NULL
                AND d.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

// ---- Load discounts ----
$discounts = [];
$sql = "SELECT d.*, c.customer_name, c.customer_business_name, c.status AS customer_status
        FROM customer_discounts d
        INNER JOIN customers c ON c.customer_id = d.customer_id
        $where
        ORDER BY d.status DESC, d.discount_id DESC";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) $discounts[] = $row;
}
$stmt->close();

// ---- Preload edit (?edit=ID) ----
$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM customer_discounts WHERE discount_id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ---- Is the currently attached customer inactive? ----
$currentCustomerInactive = false;
if ($edit) {
    foreach ($customerList as $cu) {
        if ((int)$cu['customer_id'] === (int)$edit['customer_id'] && (int)$cu['status'] !== 1) {
            $currentCustomerInactive = true;
            break;
        }
    }
}

// ---- Summary counts (FIXED: customer status-aware) ----
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'expiring' => 0];
$sr = $conn->query("
    SELECT
      COUNT(*) AS total,

      SUM(d.status = 1 AND c.status = 1
          AND (d.valid_until IS NULL OR d.valid_until >= CURDATE())) AS active,

      SUM(d.status = 0
          OR c.status = 0
          OR (d.valid_until IS NOT NULL AND d.valid_until < CURDATE())) AS inactive,

      SUM(d.status = 1 AND c.status = 1
          AND d.valid_until IS NOT NULL
          AND d.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS expiring

    FROM customer_discounts d
    INNER JOIN customers c ON c.customer_id = d.customer_id
");
if ($sr && $row = $sr->fetch_assoc()) {
    $stats['total']    = (int)$row['total'];
    $stats['active']   = (int)$row['active'];
    $stats['inactive'] = (int)$row['inactive'];
    $stats['expiring'] = (int)$row['expiring'];
}

// ---- Helpers ----
function e($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function money($v)
{
    return number_format((float)$v, 2);
}
function discount_label($d)
{
    if ($d['discount_type'] === 'percentage') {
        return rtrim(rtrim(number_format((float)$d['discount_value'], 2, '.', ''), '0'), '.') . '%';
    }
    return 'Rs. ' . number_format((float)$d['discount_value'], 2);
}
function validity_label($d)
{
    $from  = $d['valid_from']  ?? null;
    $until = $d['valid_until'] ?? null;
    if (!$from && !$until) return 'Always';
    if ($from && $until)   return date('d M Y', strtotime($from)) . ' → ' . date('d M Y', strtotime($until));
    if ($from)             return 'From ' . date('d M Y', strtotime($from));
    return 'Until ' . date('d M Y', strtotime($until));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System | Customer Discounts</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/customer_discount.css">
</head>

<body class="bg-light">

    <div class="d-flex">
        <?php include_once "../../config/sidebar.php"; ?>

        <main class="flex-grow-1 p-3 p-md-4 main-content">

            <!-- ============ HEADER ============ -->
            <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Customer Discounts</h2>
                    <p class="text-muted mb-0">Assign percentage or fixed-amount discounts to individual customers.</p>
                </div>
                <form class="d-flex search-bar" method="GET" action="customer_discount.php" role="search">
                    <i class="bi bi-search search-icon"></i>
                    <input class="search-input" type="search" name="q"
                        placeholder="Search customer name…"
                        value="<?= e($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="customer_discount.php" class="search-clear" title="Clear">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="visually-hidden">Search</button>
                </form>
            </header>

            <!-- ============ FLASH ============ -->
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= e($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- ============ SUMMARY CHIPS ============ -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="customer_discount.php?filter=all&<?= $search !== '' ? 'q=' . urlencode($search) : '' ?>"
                    class="summary-chip <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-ticket-perforated me-1"></i>All
                    <span class="badge bg-secondary ms-1"><?= $stats['total'] ?></span>
                </a>
                <a href="customer_discount.php?filter=active&<?= $search !== '' ? 'q=' . urlencode($search) : '' ?>"
                    class="summary-chip <?= $filter === 'active' ? 'active' : '' ?>">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>Active
                    <span class="badge bg-success ms-1"><?= $stats['active'] ?></span>
                </a>
                <a href="customer_discount.php?filter=expiring&<?= $search !== '' ? 'q=' . urlencode($search) : '' ?>"
                    class="summary-chip <?= $filter === 'expiring' ? 'active' : '' ?>">
                    <i class="bi bi-hourglass-split text-warning me-1"></i>Expiring ≤7d
                    <span class="badge bg-warning text-dark ms-1"><?= $stats['expiring'] ?></span>
                </a>
                <a href="customer_discount.php?filter=inactive&<?= $search !== '' ? 'q=' . urlencode($search) : '' ?>"
                    class="summary-chip <?= $filter === 'inactive' ? 'active' : '' ?>">
                    <i class="bi bi-dash-circle-fill text-secondary me-1"></i>Inactive
                    <span class="badge bg-secondary ms-1"><?= $stats['inactive'] ?></span>
                </a>
            </div>

            <div class="row g-4">

                <!-- ==================== FORM CARD (LEFT) ==================== -->
                <div class="col-12 col-lg-5 col-xl-4">
                    <div class="card form-card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
                            <h5 class="fw-bold text-dark mb-0" id="formTitle">
                                <i class="bi bi-percent text-primary me-2"></i>
                                <?= $edit ? 'Edit Discount #' . (int)$edit['discount_id'] : 'Assign New Discount' ?>
                            </h5>
                        </div>
                        <div class="card-body p-3 p-md-4">

                            <?php if ($currentCustomerInactive): ?>
                                <div class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3 mb-3 small">
                                    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                                    <div>
                                        The attached customer is <strong>inactive</strong>.
                                        You can <strong>deactivate</strong> this discount (set status = Inactive) as cleanup,
                                        but any other change will be blocked on save.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <form id="discountForm"
                                action="../../backend/customer/discount_save.php"
                                method="POST"
                                class="needs-validation"
                                novalidate>

                                <?php if ($edit): ?>
                                    <input type="hidden" name="discount_id" value="<?= (int)$edit['discount_id']; ?>">
                                <?php endif; ?>

                                <!-- Customer -->
                                <div class="mb-3">
                                    <label for="customerId" class="form-label fw-semibold">
                                        Customer <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="customerId" name="customer_id" required
                                        data-editing-customer-id="<?= $edit ? (int)$edit['customer_id'] : '' ?>">
                                        <option value="" disabled <?= !$edit ? 'selected' : ''; ?>>Select customer…</option>
                                        <?php foreach ($customerList as $cu):
                                            $isActive  = (int)$cu['status'] === 1;
                                            $isCurrent = $edit && (int)$edit['customer_id'] === (int)$cu['customer_id'];
                                            // Inactive customers are only shown if they're the one currently attached to this discount
                                            if (!$isActive && !$isCurrent) continue;
                                        ?>
                                            <option value="<?= (int)$cu['customer_id']; ?>"
                                                data-active="<?= $isActive ? '1' : '0' ?>"
                                                <?= $isCurrent ? 'selected' : ''; ?>>
                                                <?= e($cu['customer_name']); ?>
                                                <?= $cu['customer_business_name'] ? ' — ' . e($cu['customer_business_name']) : ''; ?>
                                                <?= !$isActive ? ' (Inactive — cannot change)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select an active customer.</div>
                                    <div class="form-text text-warning d-none" id="inactiveCustomerHint">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        This customer is <strong>inactive</strong>. You cannot assign a new discount to them.
                                        Reactivate the customer first, or choose a different customer.
                                    </div>
                                </div>

                                <!-- Type & Value -->
                                <div class="row g-2 mb-3">
                                    <div class="col-7">
                                        <label for="discountType" class="form-label fw-semibold">
                                            Discount Type <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="discountType" name="discount_type" required>
                                            <option value="percentage" <?= ($edit['discount_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                            <option value="fixed" <?= ($edit['discount_type'] ?? '') === 'fixed'      ? 'selected' : ''; ?>>Fixed Amount (Rs.)</option>
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        <label for="discountValue" class="form-label fw-semibold">
                                            Value <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="discountValue"
                                            name="discount_value" required
                                            value="<?= e($edit['discount_value'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Min purchase & Max discount -->
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label for="minPurchase" class="form-label fw-semibold">Min. Purchase (Rs.)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="minPurchase"
                                            name="min_purchase"
                                            value="<?= e($edit['min_purchase'] ?? '0.00') ?>"
                                            placeholder="0.00">
                                    </div>
                                    <div class="col-6">
                                        <label for="maxDiscount" class="form-label fw-semibold">Max Discount (Rs.)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="maxDiscount"
                                            name="max_discount"
                                            value="<?= e($edit['max_discount'] ?? '') ?>"
                                            placeholder="Optional">
                                        <small class="text-muted" style="font-size: .72rem;">Cap for % discounts</small>
                                    </div>
                                </div>

                                <!-- Validity -->
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label for="validFrom" class="form-label fw-semibold">Valid From</label>
                                        <input type="date" class="form-control" id="validFrom"
                                            name="valid_from"
                                            value="<?= e($edit['valid_from'] ?? '') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label for="validUntil" class="form-label fw-semibold">Valid Until</label>
                                        <input type="date" class="form-control" id="validUntil"
                                            name="valid_until"
                                            value="<?= e($edit['valid_until'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Status & Notes -->
                                <div class="mb-3">
                                    <label for="status" class="form-label fw-semibold">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="1" <?= ($edit['status'] ?? 1) == 1 ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?= ($edit['status'] ?? 1) == 0 ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="notes" class="form-label fw-semibold">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="255"
                                        placeholder="Optional internal note…"><?= e($edit['notes'] ?? '') ?></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="customer_discount.php" class="btn btn-light w-100 w-md-auto me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary w-100 w-md-auto" id="saveBtn">
                                        <i class="bi bi-save me-1"></i>
                                        <?= $edit ? 'Update Discount' : 'Save Discount' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ==================== DISCOUNT TABLE (RIGHT) ==================== -->
                <div class="col-12 col-lg-7 col-xl-8">
                    <div class="card list-card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-0 py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-dark mb-0">
                                <i class="bi bi-tags me-2"></i>Discount Rules
                                <span class="badge bg-secondary ms-1"><?= count($discounts) ?></span>
                            </h5>
                            <?php if ($search !== '' || $filter !== 'all'): ?>
                                <small class="text-muted">
                                    <?= $search !== '' ? 'Search: “' . e($search) . '”' : '' ?>
                                    <?= $search !== '' && $filter !== 'all' ? ' · ' : '' ?>
                                    <?= $filter !== 'all' ? 'Filter: ' . e(ucfirst($filter)) : '' ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 ps-md-4">Customer</th>
                                        <th>Discount</th>
                                        <th>Conditions</th>
                                        <th>Validity</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3 pe-md-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($discounts)): ?>
                                        <?php foreach ($discounts as $d):
                                            $expired       = $d['valid_until'] && strtotime($d['valid_until']) < strtotime(date('Y-m-d'));
                                            $expiringSoon  = $d['valid_until'] && !$expired
                                                && strtotime($d['valid_until']) <= strtotime('+7 days');
                                            $customerInactive = (int)$d['customer_status'] !== 1;
                                            $effectiveActive  = ((int)$d['status'] === 1) && !$expired && !$customerInactive;
                                        ?>
                                            <tr class="<?= !$effectiveActive ? 'row-inactive' : ''; ?>">
                                                <!-- Customer -->
                                                <td class="ps-3 ps-md-4">
                                                    <div class="fw-semibold text-dark">
                                                        <?= e($d['customer_name']); ?>
                                                        <?php if ($customerInactive): ?>
                                                            <span class="badge bg-secondary-subtle text-secondary ms-1">Customer Inactive</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($d['customer_business_name']): ?>
                                                        <small class="text-muted"><?= e($d['customer_business_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Discount -->
                                                <td>
                                                    <span class="discount-badge <?= $d['discount_type'] === 'percentage' ? 'pct' : 'fixed'; ?>">
                                                        <?= e(discount_label($d)); ?>
                                                    </span>
                                                    <small class="text-muted d-block mt-1">
                                                        <?= $d['discount_type'] === 'percentage' ? 'Percentage' : 'Fixed amount' ?>
                                                    </small>
                                                </td>

                                                <!-- Conditions -->
                                                <td>
                                                    <small class="d-block text-muted">
                                                        <i class="bi bi-bag-check me-1"></i>Min: Rs. <?= money($d['min_purchase']) ?>
                                                    </small>
                                                    <?php if ($d['max_discount'] !== null && (float)$d['max_discount'] > 0): ?>
                                                        <small class="text-muted">
                                                            <i class="bi bi-shield-check me-1"></i>Cap: Rs. <?= money($d['max_discount']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Validity -->
                                                <td>
                                                    <small class="text-muted"><?= e(validity_label($d)); ?></small>
                                                    <?php if ($expired): ?>
                                                        <span class="badge bg-danger-subtle text-danger ms-1">Expired</span>
                                                    <?php elseif ($expiringSoon): ?>
                                                        <span class="badge bg-warning-subtle text-warning ms-1">Soon</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Status -->
                                                <td>
                                                    <?php if ($effectiveActive): ?>
                                                        <span class="badge bg-success-subtle text-success">
                                                            <i class="bi bi-check-circle me-1"></i>Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary">
                                                            <i class="bi bi-dash-circle me-1"></i>Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Actions -->
                                                <td class="text-end pe-3 pe-md-4 text-nowrap">
                                                    <a href="customer_discount.php?edit=<?= (int)$d['discount_id']; ?>"
                                                        class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>

                                                    <form action="../../backend/customer/discount_save.php"
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Delete this discount rule for &quot;<?= e($d['customer_name']); ?>&quot;?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="discount_id" value="<?= (int)$d['discount_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-ticket-perforated fs-2 d-block mb-2"></i>
                                                <?= ($search !== '' || $filter !== 'all')
                                                    ? 'No discount rules match your filters.'
                                                    : 'No discount rules yet. Assign one on the left.'; ?>
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
        // ---- Type-aware label for discount value ----
        function syncDiscountType() {
            const type = document.getElementById('discountType');
            const label = document.querySelector('label[for="discountValue"]');
            const valueInput = document.getElementById('discountValue');
            const maxField = document.getElementById('maxDiscount');

            if (type.value === 'percentage') {
                label.innerHTML = 'Value (%) <span class="text-danger">*</span>';
                valueInput.max = 100;
                maxField.disabled = false;
            } else {
                label.innerHTML = 'Value (Rs.) <span class="text-danger">*</span>';
                valueInput.removeAttribute('max');
                maxField.disabled = true;
                maxField.value = '';
            }
        }

        // ---- Enforce active-customer rule on save ----
        function syncCustomerState() {
            const sel = document.getElementById('customerId');
            const hint = document.getElementById('inactiveCustomerHint');
            const saveBtn = document.getElementById('saveBtn');
            const opt = sel.options[sel.selectedIndex];
            const isActive = opt && opt.dataset.active === '1';

            if (!opt || sel.value === '') {
                hint.classList.add('d-none');
                saveBtn.disabled = false;
                saveBtn.title = '';
                return;
            }

            if (isActive) {
                hint.classList.add('d-none');
                saveBtn.disabled = false;
                saveBtn.title = '';
            } else {
                hint.classList.remove('d-none');
                saveBtn.disabled = true;
                saveBtn.title = 'Cannot save: selected customer is inactive.';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('discountType').addEventListener('change', syncDiscountType);
            document.getElementById('customerId').addEventListener('change', syncCustomerState);

            syncDiscountType();
            syncCustomerState();
        });

        // ---- Client-side submit validation ----
        document.getElementById('discountForm').addEventListener('submit', function(e) {
            const sel = document.getElementById('customerId');
            const opt = sel.options[sel.selectedIndex];

            if (opt && opt.dataset.active !== '1') {
                e.preventDefault();
                e.stopPropagation();
                alert('You cannot save a discount for an inactive customer.\nReactivate the customer first, or choose a different customer.');
                return;
            }

            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        }, false);
    </script>
</body>

</html>