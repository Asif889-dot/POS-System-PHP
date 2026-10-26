<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

$search = trim($_GET['q'] ?? '');
$show   = $_GET['show'] ?? 'due'; // due | all | history

$where = [];
$types = '';
$params = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(c.customer_name LIKE ? OR c.customer_business_name LIKE ? OR s.invoice_no LIKE ?)';
    $types  .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($show === 'due') $where[] = 's.due_amount > 0';

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// List credit sales
$sql = "SELECT s.id, s.invoice_no, s.total_amount, s.paid_amount, s.due_amount,
               s.due_date, s.status, s.created_at,
               c.customer_id, c.customer_name, c.customer_business_name, c.customer_phone
        FROM sales s
        JOIN customers c ON c.customer_id = s.customer_id
        $whereSql
        ORDER BY (s.due_amount > 0) DESC, s.due_date ASC, s.created_at DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Totals
$totalDue = 0;
$totalPending = 0;
foreach ($rows as $r) {
    $totalDue += (float)$r['due_amount'];
    if ($r['due_amount'] > 0) $totalPending++;
}

function e($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function money($v)
{
    return number_format((float)$v, 2);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Payments | Udhar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/customer_payments.css">
</head>

<body class="bg-light">

    <div class="d-flex">
        <?php include_once "../../config/sidebar.php"; ?>

        <main class="flex-grow-1 p-3 p-md-4 main-content">
            <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Customer Payments</h2>
                    <p class="text-muted mb-0">Track credit (udhar) sales and record customer payments.</p>
                </div>
                <form class="d-flex search-bar" method="GET" action="customer_payments.php">
                    <i class="bi bi-search search-icon"></i>
                    <input class="search-input" type="search" name="q"
                        placeholder="Search customer, business, invoice…" value="<?= e($search) ?>">
                    <input type="hidden" name="show" value="<?= e($show) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="customer_payments.php?show=<?= e($show) ?>" class="search-clear"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </header>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i><?= e($_SESSION['success']) ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($_SESSION['error']) ?>
                    <button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Summary chips -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="?show=due" class="summary-chip <?= $show === 'due' ? 'active' : '' ?>">
                    <i class="bi bi-exclamation-circle text-warning me-1"></i>Outstanding
                    <span class="badge bg-warning text-dark ms-1"><?= $totalPending ?></span>
                </a>
                <a href="?show=all" class="summary-chip <?= $show === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history text-primary me-1"></i>All Credit Sales
                </a>
                <div class="summary-chip" style="cursor:default;">
                    <i class="bi bi-cash-coin text-danger me-1"></i>Total Due:
                    <strong class="ms-1">Rs.<?= money($totalDue) ?></strong>
                </div>
            </div>

            <!-- Table -->
            <div class="card border-0 shadow-sm rounded-3 list-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Customer</th>
                                <th>Invoice</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="bi bi-emoji-smile fs-2 d-block mb-2"></i>
                                        <?= $show === 'due' ? 'No outstanding balances. Everything is settled!' : 'No credit sales yet.' ?>
                                    </td>
                                </tr>
                                <?php else: foreach ($rows as $r):
                                    $overdue = $r['due_amount'] > 0 && $r['due_date'] && strtotime($r['due_date']) < time();
                                ?>
                                    <tr class="<?= $overdue ? 'row-overdue' : '' ?>">
                                        <td class="ps-3">
                                            <div class="fw-semibold"><?= e($r['customer_name']) ?></div>
                                            <?php if ($r['customer_business_name']): ?>
                                                <small class="text-muted"><?= e($r['customer_business_name']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($r['customer_phone']): ?>
                                                <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= e($r['customer_phone']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><code class="small"><?= e($r['invoice_no']) ?></code>
                                            <small class="text-muted d-block"><?= date('d M Y', strtotime($r['created_at'])) ?></small>
                                        </td>
                                        <td>Rs.<?= money($r['total_amount']) ?></td>
                                        <td>Rs.<?= money($r['paid_amount']) ?></td>
                                        <td>
                                            <strong class="<?= $r['due_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                Rs.<?= money($r['due_amount']) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($r['due_date']): ?>
                                                <?= date('d M Y', strtotime($r['due_date'])) ?>
                                                <?php if ($overdue): ?>
                                                    <span class="badge bg-danger-subtle text-danger ms-1">Overdue</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?=
                                                                    $r['status'] === 'completed' ? 'success-subtle text-success' : ($r['status'] === 'pending' ? 'warning-subtle text-warning' : ($r['status'] === 'partial' ? 'info-subtle text-info' : 'secondary-subtle text-secondary'))
                                                                    ?>"><?= ucfirst($r['status']) ?></span>
                                        </td>
                                        <td class="text-end pe-3 text-nowrap">
                                            <?php if ($r['due_amount'] > 0): ?>
                                                <button type="button" class="btn btn-sm btn-success"
                                                    onclick='openPaymentModal(<?= json_encode([
                                                                                    "sale_id" => (int)$r["id"],
                                                                                    "invoice_no" => $r["invoice_no"],
                                                                                    "customer_name" => $r["customer_name"],
                                                                                    "due_amount" => (float)$r["due_amount"],
                                                                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                    <i class="bi bi-cash-coin me-1"></i>Record Payment
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-secondary"
                                                onclick='viewHistory(<?= (int)$r['customer_id'] ?>, <?= json_encode($r['customer_name']) ?>)'>
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                        </td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Payment modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin text-success me-2"></i>Record Payment</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="paymentForm" action="../../backend/customer/record_payment.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="sale_id" id="paySaleId">
                        <div class="mb-3">
                            <div class="text-muted small">Customer</div>
                            <div class="fw-semibold" id="payCustomerName">—</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Invoice</div>
                            <code id="payInvoiceNo">—</code>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Outstanding</div>
                            <div class="fw-bold text-danger fs-5" id="payDueAmount">Rs.0.00</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Amount Received <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="payAmount" step="0.01" min="0.01"
                                class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="digital">Digital Wallet</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" class="form-control" maxlength="255"
                                placeholder="Optional">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Save Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- History modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i><span id="historyTitle">Customer History</span></h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="historyBody">
                    <div class="text-center text-muted py-4">Loading…</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let paymentModal, historyModal;
        document.addEventListener('DOMContentLoaded', () => {
            paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
        });

        function openPaymentModal(data) {
            document.getElementById('paySaleId').value = data.sale_id;
            document.getElementById('payInvoiceNo').textContent = data.invoice_no;
            document.getElementById('payCustomerName').textContent = data.customer_name;
            document.getElementById('payDueAmount').textContent = 'Rs.' + data.due_amount.toFixed(2);
            const amt = document.getElementById('payAmount');
            amt.max = data.due_amount;
            amt.value = data.due_amount;
            paymentModal.show();
        }

        async function viewHistory(customerId, name) {
            document.getElementById('historyTitle').textContent = name + ' — History';
            document.getElementById('historyBody').innerHTML =
                '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
            historyModal.show();
            try {
                const res = await fetch(`../../backend/customer/payment_history.php?customer_id=${customerId}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.message);

                const {
                    sales,
                    payments,
                    summary
                } = json.data;
                let html = `<div class="row g-2 mb-3">
            <div class="col-4"><div class="border rounded p-2 text-center">
              <div class="text-muted small">Total Sales</div>
              <div class="fw-bold">Rs.${summary.total_sales.toFixed(2)}</div></div></div>
            <div class="col-4"><div class="border rounded p-2 text-center">
              <div class="text-muted small">Paid</div>
              <div class="fw-bold text-success">Rs.${summary.total_paid.toFixed(2)}</div></div></div>
            <div class="col-4"><div class="border rounded p-2 text-center">
              <div class="text-muted small">Outstanding</div>
              <div class="fw-bold text-danger">Rs.${summary.total_due.toFixed(2)}</div></div></div>
        </div>
        <h6 class="fw-bold mt-3">Credit Sales</h6>
        <table class="table table-sm">
          <thead><tr><th>Invoice</th><th>Total</th><th>Paid</th><th>Due</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>${sales.map(s => `
            <tr><td><code>${s.invoice_no}</code></td>
                <td>Rs.${Number(s.total_amount).toFixed(2)}</td>
                <td>Rs.${Number(s.paid_amount).toFixed(2)}</td>
                <td class="${s.due_amount > 0 ? 'text-danger' : 'text-success'}">Rs.${Number(s.due_amount).toFixed(2)}</td>
                <td>${s.created_at}</td>
                <td>${s.status}</td></tr>`).join('')}</tbody>
        </table>
        <h6 class="fw-bold mt-3">Payments Received</h6>
        <table class="table table-sm">
          <thead><tr><th>Date</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Notes</th></tr></thead>
          <tbody>${payments.length ? payments.map(p => `
            <tr><td>${p.paid_at}</td>
                <td><code>${p.invoice_no}</code></td>
                <td class="text-success fw-semibold">Rs.${Number(p.amount).toFixed(2)}</td>
                <td>${p.payment_method}</td>
                <td class="text-muted small">${p.notes || '—'}</td></tr>`).join('')
            : '<tr><td colspan="5" class="text-center text-muted">No payments recorded.</td></tr>'}</tbody>
        </table>`;
                document.getElementById('historyBody').innerHTML = html;
            } catch (err) {
                document.getElementById('historyBody').innerHTML =
                    `<div class="alert alert-danger">Failed to load history: ${err.message}</div>`;
            }
        }
    </script>
</body>

</html>