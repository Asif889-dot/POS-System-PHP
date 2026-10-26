<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

// ---- Read filters ----
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';        // all | 1 | 0
$sort   = $_GET['sort']   ?? 'newest';     // newest | oldest | name

// ---- CSV export ----
if (isset($_GET['export']) && $_GET['export'] === '1') {
    // Re-run the same filters below to get a matching export
    [$whereSql, $whereTypes, $whereParams] = buildWhere($search, $status);

    $orderSql = match ($sort) {
        'oldest' => 'customer_id ASC',
        'name'   => 'customer_name ASC',
        default  => 'customer_id DESC',
    };

    $sql  = "SELECT * FROM customers $whereSql ORDER BY $orderSql";
    $stmt = $conn->prepare($sql);
    if ($whereTypes !== '') $stmt->bind_param($whereTypes, ...$whereParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Business', 'Email', 'Phone', 'Address', 'Status', 'Created', 'Updated']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['customer_id'],
            $r['customer_name'],
            $r['customer_business_name'],
            $r['customer_email'],
            $r['customer_phone'],
            $r['customer_address'],
            ((int)$r['status'] === 1 ? 'Active' : 'Inactive'),
            $r['created_at'],
            $r['updated_at'],
        ]);
    }
    fclose($out);
    exit();
}

// ---- Build WHERE clause ----
function buildWhere($search, $status)
{
    $conds  = [];
    $types  = '';
    $params = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $conds[]  = '(customer_name LIKE ? OR customer_business_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)';
        $types   .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }
    if ($status === '1' || $status === '0') {
        $conds[] = 'status = ?';
        $types  .= 'i';
        $params[] = (int)$status;
    }

    $whereSql = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
    return [$whereSql, $types, $params];
}

[$whereSql, $whereTypes, $whereParams] = buildWhere($search, $status);

$orderSql = match ($sort) {
    'oldest' => 'customer_id ASC',
    'name'   => 'customer_name ASC',
    default  => 'customer_id DESC',
};

$sql  = "SELECT * FROM customers $whereSql ORDER BY $orderSql";
$stmt = $conn->prepare($sql);
if ($whereTypes !== '') $stmt->bind_param($whereTypes, ...$whereParams);
$stmt->execute();
$res = $stmt->get_result();
$customers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// ---- Totals for the summary chips ----
$totals = ['all' => 0, 'active' => 0, 'inactive' => 0];
$tRes = $conn->query("SELECT status, COUNT(*) AS cnt FROM customers GROUP BY status");
if ($tRes) {
    while ($row = $tRes->fetch_assoc()) {
        $totals['all'] += (int)$row['cnt'];
        if ((int)$row['status'] === 1) $totals['active']   += (int)$row['cnt'];
        else                           $totals['inactive'] += (int)$row['cnt'];
    }
}

// ---- Helpers ----
function e($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function initial_of($name)
{
    if (!$name) return '?';
    return function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
}
function buildQuery($overrides = [])
{
    $q = array_merge([
        'q'      => $_GET['q']      ?? '',
        'status' => $_GET['status'] ?? 'all',
        'sort'   => $_GET['sort']   ?? 'newest',
    ], $overrides);
    return http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System | All Customers</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/all_customers.css">
</head>

<body class="bg-light">

    <div class="d-flex">
        <?php include_once "../../config/sidebar.php"; ?>

        <main class="flex-grow-1 p-3 p-md-4 main-content">

            <!-- ============ HEADER ============ -->
            <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark mb-1">All Customers</h2>
                    <p class="text-muted mb-0">Browse, search, and inspect every customer record.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="customer.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i> Add New
                    </a>
                    <a href="all_customer.php?<?= buildQuery(['export' => '1']) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </a>
                </div>
            </header>

            <!-- ============ FILTER BAR ============ -->
            <form method="GET" action="all_customers.php"
                class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body d-flex flex-wrap gap-2 align-items-center">

                    <!-- Search -->
                    <div class="search-bar flex-grow-1">
                        <i class="bi bi-search search-icon"></i>
                        <input class="search-input" type="search" name="q"
                            placeholder="Search name, business, email, phone…"
                            value="<?= e($search) ?>">
                        <?php if ($search !== ''): ?>
                            <a href="all_customers.php?<?= buildQuery(['q' => '', 'export' => null]) ?>"
                                class="search-clear" title="Clear search">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Status filter -->
                    <select name="status" class="form-select" style="max-width: 170px;"
                        onchange="this.form.submit()">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="1" <?= $status === '1'   ? 'selected' : '' ?>>Active only</option>
                        <option value="0" <?= $status === '0'   ? 'selected' : '' ?>>Inactive only</option>
                    </select>

                    <!-- Sort -->
                    <select name="sort" class="form-select" style="max-width: 180px;"
                        onchange="this.form.submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                        <option value="name" <?= $sort === 'name'   ? 'selected' : '' ?>>Name (A→Z)</option>
                    </select>

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="all_customers.php" class="btn btn-light">Reset</a>
                </div>
            </form>

            <!-- ============ SUMMARY CHIPS ============ -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="all_customers.php?<?= buildQuery(['status' => 'all']) ?>"
                    class="summary-chip <?= $status === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill me-1"></i>
                    All <span class="badge bg-secondary ms-1"><?= $totals['all'] ?></span>
                </a>
                <a href="all_customers.php?<?= buildQuery(['status' => '1']) ?>"
                    class="summary-chip <?= $status === '1' ? 'active' : '' ?>">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    Active <span class="badge bg-success ms-1"><?= $totals['active'] ?></span>
                </a>
                <a href="all_customers.php?<?= buildQuery(['status' => '0']) ?>"
                    class="summary-chip <?= $status === '0' ? 'active' : '' ?>">
                    <i class="bi bi-dash-circle-fill text-secondary me-1"></i>
                    Inactive <span class="badge bg-secondary ms-1"><?= $totals['inactive'] ?></span>
                </a>
            </div>

            <!-- ============ TABLE CARD ============ -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-0 py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-people me-2"></i>Customers
                        <span class="badge bg-secondary ms-1"><?= count($customers) ?></span>
                    </h5>
                    <?php if ($search !== '' || $status !== 'all'): ?>
                        <small class="text-muted">
                            <?= $search !== '' ? 'Search: “' . e($search) . '”' : '' ?>
                            <?= $search !== '' && $status !== 'all' ? ' · ' : '' ?>
                            <?= $status !== 'all' ? 'Status: ' . ($status === '1' ? 'Active' : 'Inactive') : '' ?>
                        </small>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 ps-md-4">Customer</th>
                                <th>Business</th>
                                <th>Contact Info</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end pe-3 pe-md-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($customers)): ?>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td class="ps-3 ps-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2">
                                                    <?= e(initial_of($c['customer_name'])); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 180px;">
                                                        <?= e($c['customer_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">#<?= (int)$c['customer_id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?= !empty($c['customer_business_name'])
                                                ? '<small class="fw-semibold text-secondary">' . e($c['customer_business_name']) . '</small>'
                                                : '<small class="text-muted">—</small>'; ?>
                                        </td>
                                        <td>
                                            <small class="d-block text-truncate" style="max-width: 200px;">
                                                <i class="bi bi-envelope text-muted me-1"></i>
                                                <?= !empty($c['customer_email']) ? e($c['customer_email']) : '<span class="text-muted">N/A</span>'; ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?= !empty($c['customer_phone']) ? e($c['customer_phone']) : 'N/A'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                <?= !empty($c['customer_address']) ? e($c['customer_address']) : 'N/A'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?= (int)$c['status'] === 1
                                                ? '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Active</span>'
                                                : '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-dash-circle me-1"></i>Inactive</span>'; ?>
                                        </td>
                                        <td><small class="text-muted"><?= e($c['created_at']); ?></small></td>
                                        <td class="text-end pe-3 pe-md-4 text-nowrap">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-secondary me-1"
                                                title="View details"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewCustomerModal"
                                                onclick='viewCustomer(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>)'>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <!-- <a href="customer.php?edit=<?= (int)$c['customer_id']; ?>"
                                                class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a> -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        <?= ($search !== '' || $status !== 'all')
                                            ? 'No customers match your filters.'
                                            : 'No customers recorded yet.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- ============ VIEW CUSTOMER MODAL ============ -->
    <div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-vcard text-primary me-2"></i>Customer Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <div class="d-flex align-items-center mb-4">
                        <div class="avatar-circle avatar-lg me-3" id="viewAvatar">?</div>
                        <div>
                            <h4 class="mb-0 fw-bold" id="viewName">—</h4>
                            <small class="text-muted">Customer <span id="viewId">#—</span></small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Business Name</label>
                            <div class="fw-semibold" id="viewBusiness">—</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Status</label>
                            <div id="viewStatus">—</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Email</label>
                            <div class="fw-semibold" id="viewEmail">—</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Phone</label>
                            <div class="fw-semibold" id="viewPhone">—</div>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small d-block">Address</label>
                            <div class="fw-semibold" id="viewAddress">—</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Created</label>
                            <div class="small text-muted" id="viewCreated">—</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Last Updated</label>
                            <div class="small text-muted" id="viewUpdated">—</div>
                        </div>
                    </div>

                </div>
                <!-- <div class="modal-footer border-0 pt-0">
                    <a href="#" class="btn btn-outline-primary" id="viewEditBtn">
                        <i class="bi bi-pencil me-1"></i> Edit this customer
                    </a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div> -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewCustomer(c) {
            const initial = (c.customer_name || '?').trim().charAt(0).toUpperCase();
            document.getElementById('viewAvatar').textContent = initial || '?';
            document.getElementById('viewName').textContent = c.customer_name || '—';
            document.getElementById('viewId').textContent = '#' + c.customer_id;
            document.getElementById('viewBusiness').textContent = c.customer_business_name || '—';
            document.getElementById('viewEmail').textContent = c.customer_email || 'N/A';
            document.getElementById('viewPhone').textContent = c.customer_phone || 'N/A';
            document.getElementById('viewAddress').textContent = c.customer_address || 'N/A';
            document.getElementById('viewCreated').textContent = c.created_at || '—';
            document.getElementById('viewUpdated').textContent = c.updated_at || '—';

            document.getElementById('viewStatus').innerHTML = (Number(c.status) === 1) ?
                '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Active</span>' :
                '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-dash-circle me-1"></i>Inactive</span>';

            document.getElementById('viewEditBtn').href =
                'customer.php?edit=' + encodeURIComponent(c.customer_id);
        }
    </script>
</body>

</html>