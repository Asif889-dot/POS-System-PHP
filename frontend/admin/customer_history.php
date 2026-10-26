<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

// ---- Filters ----
$search   = trim($_GET['q'] ?? '');
$filter   = $_GET['filter'] ?? 'all';   // all | dues | overdue | paid
$sort     = $_GET['sort']   ?? 'name';  // name | sales | due | recent
$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date']   ?? '';

// ---- Build the customer summary query ----
$where  = [];
$types  = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(c.customer_name LIKE ? OR c.customer_business_name LIKE ?
                 OR c.customer_email LIKE ? OR c.customer_phone LIKE ?)';
    $types  .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

// Date filter for the JOIN clause
$joinCondition = "s.customer_id = c.customer_id";
if ($fromDate) {
    $joinCondition .= " AND DATE(s.created_at) >= ?";
    $types .= 's';
    $params[] = $fromDate;
}
if ($toDate) {
    $joinCondition .= " AND DATE(s.created_at) <= ?";
    $types .= 's';
    $params[] = $toDate;
}

$having = [];
if ($filter === 'dues') {
    $having[] = 'total_due > 0';
} elseif ($filter === 'overdue') {
    $having[] = 'overdue_count > 0';
} elseif ($filter === 'paid') {
    $having[] = 'total_due = 0 AND total_sales > 0';
}

$orderBy = match ($sort) {
    'sales'  => 'total_sales_amount DESC',
    'due'    => 'total_due DESC',
    'recent' => 'last_activity DESC',
    default  => 'c.customer_name ASC',
};

$whereSql  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$havingSql = $having ? 'HAVING ' . implode(' AND ', $having) : '';

$sql = "
    SELECT
        c.customer_id,
        c.customer_name,
        c.customer_business_name,
        c.customer_email,
        c.customer_phone,
        c.customer_address,
        COUNT(s.id)                                          AS total_sales,
        COALESCE(SUM(s.total_amount), 0)                     AS total_sales_amount,
        COALESCE(SUM(s.paid_amount),  0)                     AS total_paid,
        COALESCE(SUM(s.due_amount),   0)                     AS total_due,
        SUM(CASE WHEN s.due_amount > 0
                  AND s.due_date IS NOT NULL
                  AND s.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
        MAX(s.created_at)                                    AS last_activity
    FROM customers c
    LEFT JOIN sales s ON $joinCondition
    $whereSql
    GROUP BY c.customer_id
    $havingSql
    ORDER BY $orderBy
";

$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---- Global totals ----
$grand = ['billed' => 0, 'paid' => 0, 'due' => 0, 'with_due' => 0];
foreach ($customers as $c) {
    $grand['billed'] += (float)$c['total_sales_amount'];
    $grand['paid']   += (float)$c['total_paid'];
    $grand['due']    += (float)$c['total_due'];
    if ((float)$c['total_due'] > 0) $grand['with_due']++;
}

// ---- Helpers ----
function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function money($v) { return number_format((float)$v, 2); }
function initial_of($name) {
    $name = trim((string)($name ?? ''));
    if ($name === '') return '?';
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
  <title>POS System | Customer History</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/customer_history.css">
</head>
<body class="bg-light">

<div class="d-flex">
  <?php include_once "../../config/sidebar.php"; ?>

  <main class="flex-grow-1 p-3 p-md-4 main-content">

    <!-- ============ HEADER ============ -->
    <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
      <div>
        <h2 class="fw-bold text-dark mb-1">Customer History</h2>
        <p class="text-muted mb-0">Full ledger: sales, payments, and outstanding balances per customer.</p>
      </div>
      
      <form class="d-flex flex-wrap align-items-center gap-2" method="GET" action="customer_history.php">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <input type="hidden" name="sort"   value="<?= e($sort) ?>">
        
        <!-- Date Filters -->
        <div class="input-group input-group-sm" style="width: auto;">
          <span class="input-group-text bg-white">From</span>
          <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>">
        </div>
        <div class="input-group input-group-sm" style="width: auto;">
          <span class="input-group-text bg-white">To</span>
          <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>">
        </div>
        
        <!-- Search -->
        <div class="search-bar">
          <i class="bi bi-search search-icon"></i>
          <input class="search-input" type="search" name="q"
                 placeholder="Search customer..."
                 value="<?= e($search) ?>">
          <?php if ($search !== '' || $fromDate || $toDate): ?>
            <a href="customer_history.php?filter=<?= e($filter) ?>&sort=<?= e($sort) ?>" class="search-clear" title="Clear all filters">
              <i class="bi bi-x-lg"></i>
            </a>
          <?php endif; ?>
          <button type="submit" class="visually-hidden">Search</button>
        </div>
        
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
          <i class="bi bi-printer"></i> Print List
        </button>
      </form>
    </header>

    <!-- ============ SUMMARY CARDS ============ -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="stat-label">Customers</div>
            <div class="stat-value"><?= count($customers) ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-circle-fill"></i></div>
          <div>
            <div class="stat-label">With Dues</div>
            <div class="stat-value"><?= $grand['with_due'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></div>
          <div>
            <div class="stat-label">Collected</div>
            <div class="stat-value text-success">Rs.<?= money($grand['paid']) ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-cash-stack"></i></div>
          <div>
            <div class="stat-label">Outstanding</div>
            <div class="stat-value text-danger">Rs.<?= money($grand['due']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ FILTER CHIPS ============ -->
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php
      $chips = [
          'all'     => ['All Customers', 'bi-people',              'secondary'],
          'dues'    => ['With Dues',     'bi-exclamation-circle',  'warning'],
          'overdue' => ['Overdue',       'bi-clock-history',       'danger'],
          'paid'    => ['Fully Paid',    'bi-check-circle',        'success'],
      ];
      foreach ($chips as $key => [$label, $icon, $color]):
          $active = $filter === $key ? 'active' : '';
      ?>
        <a href="customer_history.php?filter=<?= $key ?>&sort=<?= e($sort) ?>&q=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>"
           class="summary-chip <?= $active ?>">
          <i class="bi <?= $icon ?> text-<?= $color ?> me-1"></i><?= $label ?>
        </a>
      <?php endforeach; ?>

      <div class="ms-auto">
        <form method="GET" action="customer_history.php" class="d-flex align-items-center gap-2">
          <input type="hidden" name="filter" value="<?= e($filter) ?>">
          <input type="hidden" name="q" value="<?= e($search) ?>">
          <input type="hidden" name="from_date" value="<?= e($fromDate) ?>">
          <input type="hidden" name="to_date" value="<?= e($toDate) ?>">
          <label class="small text-muted mb-0">Sort:</label>
          <select name="sort" class="form-select form-select-sm" style="max-width:170px;" onchange="this.form.submit()">
            <option value="name"   <?= $sort === 'name'   ? 'selected' : '' ?>>Name (A→Z)</option>
            <option value="sales"  <?= $sort === 'sales'  ? 'selected' : '' ?>>Highest Sales</option>
            <option value="due"    <?= $sort === 'due'    ? 'selected' : '' ?>>Highest Due</option>
            <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most Recent</option>
          </select>
        </form>
      </div>
    </div>

    <!-- ============ CUSTOMER TABLE ============ -->
    <div class="card border-0 shadow-sm rounded-3 list-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
          <tr>
            <th class="ps-3">Customer</th>
            <th class="text-end">Sales</th>
            <th class="text-end">Billed</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Due</th>
            <th>Last Activity</th>
            <th class="text-end pe-3">Action</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($customers)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                <?= $search !== '' || $fromDate || $toDate ? 'No customers match your filters.' : 'No customer activity yet.' ?>
              </td>
            </tr>
          <?php else: foreach ($customers as $c):
              $due = (float)$c['total_due'];
              $hasOverdue = (int)$c['overdue_count'] > 0;
          ?>
            <tr class="<?= $hasOverdue ? 'row-overdue' : '' ?>">
              <td class="ps-3">
                <div class="d-flex align-items-center">
                  <div class="avatar-circle me-2"><?= e(initial_of($c['customer_name'])) ?></div>
                  <div>
                    <div class="fw-semibold text-dark text-truncate" style="max-width: 200px;">
                      <?= e($c['customer_name']) ?>
                      <?php if ($hasOverdue): ?>
                        <span class="badge bg-danger-subtle text-danger ms-1">Overdue</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($c['customer_business_name']): ?>
                      <small class="text-muted d-block"><?= e($c['customer_business_name']) ?></small>
                    <?php endif; ?>
                    <?php if ($c['customer_phone']): ?>
                      <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($c['customer_phone']) ?></small>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="text-end">
                <span class="badge bg-secondary-subtle text-secondary"><?= (int)$c['total_sales'] ?></span>
              </td>
              <td class="text-end">Rs.<?= money($c['total_sales_amount']) ?></td>
              <td class="text-end text-success">Rs.<?= money($c['total_paid']) ?></td>
              <td class="text-end">
                <?php if ($due > 0): ?>
                  <strong class="text-danger">Rs.<?= money($due) ?></strong>
                <?php else: ?>
                  <span class="text-muted">Rs.0.00</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($c['last_activity']): ?>
                  <small class="text-muted"><?= date('d M Y', strtotime($c['last_activity'])) ?></small>
                <?php else: ?>
                  <small class="text-muted">—</small>
                <?php endif; ?>
              </td>
              <td class="text-end pe-3">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        onclick='openLedger(<?= (int)$c['customer_id'] ?>, <?= json_encode($c['customer_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($fromDate) ?>, <?= json_encode($toDate) ?>)'>
                  <i class="bi bi-journal-text me-1"></i>View Ledger
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- ============ LEDGER MODAL ============ -->
<div class="modal fade" id="ledgerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      
      <!-- NEW: Modal Header with Date Filters -->
      <div class="modal-header d-flex flex-column flex-md-row align-items-md-center gap-3">
        <h5 class="modal-title fw-bold mb-0">
          <i class="bi bi-journal-text text-primary me-2"></i>
          <span id="ledgerTitle">Customer Ledger</span>
        </h5>
        
        <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
          <div class="input-group input-group-sm" style="width: auto;">
            <span class="input-group-text bg-white">From</span>
            <input type="date" id="modalFromDate" class="form-control form-control-sm" onchange="reloadLedger()">
          </div>
          <div class="input-group input-group-sm" style="width: auto;">
            <span class="input-group-text bg-white">To</span>
            <input type="date" id="modalToDate" class="form-control form-control-sm" onchange="reloadLedger()">
          </div>
        </div>
        
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="ledgerBody">
        <div class="text-center text-muted py-5">
          <span class="spinner-border spinner-border-sm me-2"></span>Loading…
        </div>
      </div>

      <div class="modal-footer border-0 pt-0 flex-wrap gap-2">
        <a href="#" class="btn btn-outline-success" id="ledgerExportBtn" target="_blank">
          <i class="bi bi-download me-1"></i> Export CSV
        </a>
        <button type="button" class="btn btn-outline-primary" id="ledgerPrintBtn">
          <i class="bi bi-printer me-1"></i> Print Ledger
        </button>
        <button type="button" class="btn btn-light ms-auto" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let ledgerModalInstance = null;
let currentCustomerId   = null;
let currentCustomerName = null;

document.addEventListener('DOMContentLoaded', () => {
    ledgerModalInstance = new bootstrap.Modal(document.getElementById('ledgerModal'));
});

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function money(v) { return Number(v || 0).toFixed(2); }

async function openLedger(customerId, customerName, fromDate, toDate) {
    currentCustomerId   = customerId;
    currentCustomerName = customerName;

    document.getElementById('ledgerTitle').textContent = customerName + ' — Ledger';
    
    // Pre-fill modal date filters with the page's current filters
    document.getElementById('modalFromDate').value = fromDate || '';
    document.getElementById('modalToDate').value   = toDate || '';

    ledgerModalInstance.show();
    fetchLedgerData(); // Fetch immediately
}

// Triggered when date inputs inside the modal change
function reloadLedger() {
    fetchLedgerData();
}

async function fetchLedgerData() {
    if (!currentCustomerId) return;

    const fromDate = document.getElementById('modalFromDate').value;
    const toDate   = document.getElementById('modalToDate').value;

    // Build URLs
    let url = `../../backend/customer/payment_history.php?customer_id=${currentCustomerId}`;
    if (fromDate) url += `&from_date=${fromDate}`;
    if (toDate) url += `&to_date=${toDate}`;
    
    let exportUrl = `../../backend/customer/customer_ledger_export.php?customer_id=${currentCustomerId}`;
    if (fromDate) exportUrl += `&from_date=${fromDate}`;
    if (toDate) exportUrl += `&to_date=${toDate}`;
    
    document.getElementById('ledgerExportBtn').href = exportUrl;
    document.getElementById('ledgerBody').innerHTML =
        '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';

    try {
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        document.getElementById('ledgerBody').innerHTML = renderLedger(json.data);
    } catch (err) {
        document.getElementById('ledgerBody').innerHTML =
            `<div class="alert alert-danger m-3">Failed to load: ${escapeHtml(err.message)}</div>`;
    }
}

function renderLedger(data) {
    const { sales, payments, summary } = data;

    const summaryHtml = `
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
          <div class="ledger-stat">
            <div class="ledger-stat-label">Total Billed</div>
            <div class="ledger-stat-value">Rs.${money(summary.total_sales)}</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="ledger-stat">
            <div class="ledger-stat-label">Total Paid</div>
            <div class="ledger-stat-value text-success">Rs.${money(summary.total_paid)}</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="ledger-stat">
            <div class="ledger-stat-label">Outstanding</div>
            <div class="ledger-stat-value text-danger">Rs.${money(summary.total_due)}</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="ledger-stat">
            <div class="ledger-stat-label">Transactions</div>
            <div class="ledger-stat-value">${sales.length}</div>
          </div>
        </div>
      </div>`;

    const salesRows = sales.length
        ? sales.map(s => {
            const overdue = s.due_amount > 0 && s.due_date && new Date(s.due_date) < new Date();
            const statusBadge = {
                'completed': '<span class="badge bg-success-subtle text-success">Paid</span>',
                'pending':   '<span class="badge bg-warning-subtle text-warning">Udhar</span>',
                'partial':   '<span class="badge bg-info-subtle text-info">Partial</span>',
                'paid':      '<span class="badge bg-success-subtle text-success">Paid</span>',
                'cancelled': '<span class="badge bg-secondary-subtle text-secondary">Cancelled</span>',
            }[s.status] || `<span class="badge bg-secondary-subtle text-secondary">${escapeHtml(s.status)}</span>`;

            return `<tr>
              <td><code>${escapeHtml(s.invoice_no)}</code>
                  <small class="text-muted d-block">${new Date(s.created_at).toLocaleDateString()}</small></td>
              <td class="text-end">Rs.${money(s.total_amount)}</td>
              <td class="text-end">Rs.${money(s.paid_amount)}</td>
              <td class="text-end ${s.due_amount > 0 ? 'text-danger fw-semibold' : 'text-muted'}">
                Rs.${money(s.due_amount)}</td>
              <td>${s.due_date ? new Date(s.due_date).toLocaleDateString() : '—'}
                  ${overdue ? '<span class="badge bg-danger-subtle text-danger ms-1">Overdue</span>' : ''}</td>
              <td>${statusBadge}</td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="6" class="text-center text-muted">No sales in this period.</td></tr>';

    const paymentRows = payments.length
        ? payments.map(p => `<tr>
            <td><small class="text-muted">${new Date(p.paid_at).toLocaleString()}</small></td>
            <td><code>${escapeHtml(p.invoice_no)}</code></td>
            <td class="text-end text-success fw-semibold">Rs.${money(p.amount)}</td>
            <td><span class="badge bg-secondary-subtle text-secondary">${escapeHtml(p.payment_method)}</span></td>
            <td class="small text-muted">${escapeHtml(p.notes || '—')}</td>
          </tr>`).join('')
        : '<tr><td colspan="5" class="text-center text-muted">No payments in this period.</td></tr>';

    return `
      ${summaryHtml}

      <h6 class="fw-bold mt-4 mb-2">
        <i class="bi bi-receipt me-1 text-primary"></i>Sales History
        <span class="badge bg-secondary ms-1">${sales.length}</span>
      </h6>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Invoice</th>
              <th class="text-end">Total</th>
              <th class="text-end">Paid</th>
              <th class="text-end">Due</th>
              <th>Due Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>${salesRows}</tbody>
        </table>
      </div>

      <h6 class="fw-bold mt-4 mb-2">
        <i class="bi bi-cash-coin me-1 text-success"></i>Payments Received
        <span class="badge bg-secondary ms-1">${payments.length}</span>
      </h6>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Date & Time</th>
              <th>Invoice</th>
              <th class="text-end">Amount</th>
              <th>Method</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>${paymentRows}</tbody>
        </table>
      </div>
    `;
}

// ---------- Print Ledger ----------
document.getElementById('ledgerPrintBtn')?.addEventListener('click', async () => {
    if (!currentCustomerId) return;

    const btn      = document.getElementById('ledgerPrintBtn');
    const original = btn.innerHTML;
    btn.disabled   = true;
    btn.innerHTML  = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing…';

    try {
        const fromDate = document.getElementById('modalFromDate').value;
        const toDate   = document.getElementById('modalToDate').value;
        
        let url = `../../backend/customer/payment_history.php?customer_id=${currentCustomerId}`;
        if (fromDate) url += `&from_date=${fromDate}`;
        if (toDate) url += `&to_date=${toDate}`;
        
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        printLedger(currentCustomerName, json.data, fromDate, toDate);
    } catch (err) {
        alert('Failed to prepare ledger print: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});

function printLedger(customerName, data, fromDate, toDate) {
    const { sales, payments, summary } = data;
    
    const dateRangeText = (fromDate || toDate) 
        ? `Period: ${fromDate || 'Beginning'} to ${toDate || 'Present'}` 
        : 'All Time';

    const salesRows = sales.length
        ? sales.map(s => {
            const overdue = s.due_amount > 0 && s.due_date && new Date(s.due_date) < new Date();
            return `
                <tr>
                    <td>${escapeHtml(s.invoice_no)}</td>
                    <td>${new Date(s.created_at).toLocaleDateString()}</td>
                    <td class="num">${money(s.total_amount)}</td>
                    <td class="num">${money(s.paid_amount)}</td>
                    <td class="num ${s.due_amount > 0 ? 'due' : ''}">${money(s.due_amount)}</td>
                    <td>${s.due_date ? new Date(s.due_date).toLocaleDateString() : '—'}
                        ${overdue ? ' <strong>(Overdue)</strong>' : ''}</td>
                    <td>${escapeHtml(s.status)}</td>
                </tr>`;
        }).join('')
        : '<tr><td colspan="7" class="empty">No sales recorded in this period.</td></tr>';

    const paymentRows = payments.length
        ? payments.map(p => `
            <tr>
                <td>${new Date(p.paid_at).toLocaleString()}</td>
                <td>${escapeHtml(p.invoice_no)}</td>
                <td class="num">${money(p.amount)}</td>
                <td>${escapeHtml(p.payment_method)}</td>
                <td>${escapeHtml(p.notes || '—')}</td>
            </tr>`).join('')
        : '<tr><td colspan="5" class="empty">No payments recorded in this period.</td></tr>';

    const printedAt = new Date().toLocaleString();

    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Ledger — ${escapeHtml(customerName)}</title>
            <style>
                * { box-sizing: border-box; }
                body {
                    font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
                    font-size: 11px;
                    color: #222;
                    margin: 0;
                    padding: 20px 24px;
                }
                .header {
                    border-bottom: 2px solid #222;
                    padding-bottom: 8px;
                    margin-bottom: 16px;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                }
                .header h1 { font-size: 18px; margin: 0; }
                .header .meta { text-align: right; font-size: 10px; color: #555; }
                h2 { font-size: 13px; margin: 18px 0 6px; }
                .summary { display: flex; gap: 8px; margin-bottom: 16px; }
                .summary .box {
                    flex: 1;
                    border: 1px solid #ccc;
                    padding: 8px 10px;
                    border-radius: 4px;
                }
                .summary .label {
                    font-size: 9px;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: .05em;
                    margin-bottom: 2px;
                }
                .summary .value { font-size: 12px; font-weight: 700; }
                .summary .value.due { color: #b91c1c; }
                .summary .value.paid { color: #15803d; }

                table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
                th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; font-size: 10px; }
                th { background: #f0f2f5; font-weight: 600; }
                td.num, th.num { text-align: right; }
                td.due { color: #b91c1c; font-weight: 600; }
                td.empty { text-align: center; color: #888; padding: 10px; font-style: italic; }

                .footer {
                    margin-top: 24px;
                    border-top: 1px solid #ccc;
                    padding-top: 6px;
                    font-size: 9px;
                    color: #666;
                    display: flex;
                    justify-content: space-between;
                }
                .signature {
                    margin-top: 32px;
                    display: flex;
                    justify-content: space-between;
                    font-size: 10px;
                    color: #333;
                }
                .signature div {
                    border-top: 1px solid #333;
                    padding-top: 3px;
                    min-width: 200px;
                }

                @page { size: A4; margin: 12mm; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            <div class="header">
                <div>
                    <h1>Customer Ledger</h1>
                    <div><strong>${escapeHtml(customerName)}</strong></div>
                    <div style="font-size:10px; color:#555; margin-top:4px;">${dateRangeText}</div>
                </div>
                <div class="meta">Printed: ${printedAt}</div>
            </div>

            <div class="summary">
                <div class="box">
                    <div class="label">Total Billed</div>
                    <div class="value">Rs.${money(summary.total_sales)}</div>
                </div>
                <div class="box">
                    <div class="label">Total Paid</div>
                    <div class="value paid">Rs.${money(summary.total_paid)}</div>
                </div>
                <div class="box">
                    <div class="label">Outstanding</div>
                    <div class="value due">Rs.${money(summary.total_due)}</div>
                </div>
                <div class="box">
                    <div class="label">Transactions</div>
                    <div class="value">${sales.length}</div>
                </div>
            </div>

            <h2>Sales History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th class="num">Total</th>
                        <th class="num">Paid</th>
                        <th class="num">Due</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>${salesRows}</tbody>
            </table>

            <h2>Payments Received</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Invoice</th>
                        <th class="num">Amount</th>
                        <th>Method</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>${paymentRows}</tbody>
            </table>

            <div class="signature">
                <div>Customer Signature</div>
                <div>Authorized Signature</div>
            </div>

            <div class="footer">
                <span>Generated from POS System</span>
                <span>${printedAt}</span>
            </div>

            <script>
                window.onload = () => { window.print(); };
            <\/script>
        </body>
        </html>
    `;

    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return alert('Pop-up blocked. Please allow pop-ups for this site.');
    w.document.open();
    w.document.write(html);
    w.document.close();
}
</script>
</body>
</html>