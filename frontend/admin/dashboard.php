<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: ../index.php");
  exit();
}

require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

$S      = load_settings($conn);
$CUR    = $S['currency_symbol']   ?? 'Rs.';
$CURPOS = $S['currency_position'] ?? 'before';

// Helper
function money($v, $cur = 'Rs.', $pos = 'before')
{
  $n = number_format((float)$v, 2);
  return $pos === 'before' ? $cur . $n : $n . ' ' . $cur;
}

/* =========================================================
   TODAY's METRICS
   ========================================================= */
$today = date('Y-m-d');

// Today's revenue
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) AS rev, COUNT(*) AS cnt
                        FROM sales WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$todayStats = $stmt->get_result()->fetch_assoc();
$stmt->close();
$todayRevenue = (float)$todayStats['rev'];
$todayOrders  = (int)$todayStats['cnt'];

// Yesterday's revenue (for comparison)
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) AS rev FROM sales WHERE DATE(created_at) = ?");
$stmt->bind_param("s", $yesterday);
$stmt->execute();
$yesterdayRevenue = (float)$stmt->get_result()->fetch_assoc()['rev'];
$stmt->close();

$revChange = $yesterdayRevenue > 0
  ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
  : 0;

// Low stock items
$lowStockThreshold = (int)($S['low_stock_threshold'] ?? 5);
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE status = 1 AND stock <= ?");
$stmt->bind_param("i", $lowStockThreshold);
$stmt->execute();
$lowStockCount = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Customers served today
$stmt = $conn->prepare("SELECT COUNT(DISTINCT customer_id) AS c
                        FROM sales WHERE DATE(created_at) = ? AND customer_id IS NOT NULL");
$stmt->bind_param("s", $today);
$stmt->execute();
$customersToday = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

/* =========================================================
   HOURLY SALES (for chart)
   ========================================================= */
$chartRange = $_GET['chart'] ?? 'today';

switch ($chartRange) {
  case 'yesterday':
    $chartDate = date('Y-m-d', strtotime('-1 day'));
    break;
  case 'week':
    $chartDate = null; // handled separately
    break;
  default:
    $chartDate = $today;
}

if ($chartRange === 'week') {
  // Last 7 days
  $hourlyLabels = [];
  $hourlyData   = [];
  for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) AS rev FROM sales WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $d);
    $stmt->execute();
    $hourlyData[]   = round((float)$stmt->get_result()->fetch_assoc()['rev'], 2);
    $hourlyLabels[] = date('D', strtotime($d));
    $stmt->close();
  }
} else {
  // Hourly for the selected day
  $stmt = $conn->prepare("SELECT HOUR(created_at) AS hr, SUM(total_amount) AS rev
                            FROM sales WHERE DATE(created_at) = ?
                            GROUP BY HOUR(created_at) ORDER BY hr");
  $stmt->bind_param("s", $chartDate);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $byHour = [];
  foreach ($rows as $r) $byHour[(int)$r['hr']] = (float)$r['rev'];

  $hourlyLabels = [];
  $hourlyData   = [];
  for ($h = 0; $h < 24; $h++) {
    $hourlyLabels[] = sprintf('%02d:00', $h);
    $hourlyData[]   = $byHour[$h] ?? 0;
  }
}

/* =========================================================
   TOP SELLERS (today)
   ========================================================= */
$stmt = $conn->prepare("
    SELECT p.name, SUM(si.quantity) AS qty, SUM(si.subtotal) AS total
    FROM sale_items si
    JOIN sales s   ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    WHERE DATE(s.created_at) = ?
    GROUP BY p.id, p.name
    ORDER BY qty DESC
    LIMIT 5
");
$stmt->bind_param("s", $today);
$stmt->execute();
$topSellers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =========================================================
   RECENT TRANSACTIONS (last 10)
   ========================================================= */
$recentSales = $conn->query("
    SELECT s.id, s.invoice_no, s.total_amount, s.payment_method, s.status, s.created_at,
           COALESCE(c.customer_name, 'Walk-in Customer') AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.customer_id = s.customer_id
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>

<body class="bg-light">

  <div class="d-flex">
    <?php include '../../config/sidebar.php'; ?>

    <main class="flex-grow-1 p-4 main-content">

      <!-- ============ HEADER ============ -->
      <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-2">
        <div>
          <h2 class="fw-bold text-dark mb-1">POS Dashboard</h2>
          <p class="text-muted mb-0">Live Overview &amp; Active Sales Counter</p>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span class="badge bg-success-subtle text-success px-3 py-2 fs-6 rounded-pill border border-success-subtle">
            <i class="bi bi-circle-fill me-1 align-middle" style="font-size: .5rem;"></i> Register #01 Active
          </span>
          <a href="sale.php" class="btn btn-primary fw-medium px-3">
            <i class="bi bi-plus-lg me-1"></i> New Terminal Sale
          </a>
        </div>
      </header>

      <!-- ============ METRICS ============ -->
      <section class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
              <div class="metric-icon bg-primary-subtle text-primary rounded-3 p-3 me-3">
                <i class="bi bi-currency-dollar fs-3"></i>
              </div>
              <div>
                <span class="text-muted fs-7 text-uppercase fw-semibold">Today's Revenue</span>
                <h3 class="fw-bold text-dark mb-0"><?= money($todayRevenue, $CUR, $CURPOS) ?></h3>
                <small class="<?= $revChange >= 0 ? 'text-success' : 'text-danger' ?> fw-medium">
                  <i class="bi bi-arrow-<?= $revChange >= 0 ? 'up' : 'down' ?>-short"></i>
                  <?= ($revChange >= 0 ? '+' : '') . $revChange ?>% from yesterday
                </small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
              <div class="metric-icon bg-success-subtle text-success rounded-3 p-3 me-3">
                <i class="bi bi-receipt fs-3"></i>
              </div>
              <div>
                <span class="text-muted fs-7 text-uppercase fw-semibold">Today's Orders</span>
                <h3 class="fw-bold text-dark mb-0"><?= $todayOrders ?></h3>
                <small class="text-muted fw-medium"><?= $todayOrders > 0 ? 'Sales recorded today' : 'No sales yet' ?></small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
              <div class="metric-icon bg-warning-subtle text-warning rounded-3 p-3 me-3">
                <i class="bi bi-exclamation-triangle fs-3"></i>
              </div>
              <div>
                <span class="text-muted fs-7 text-uppercase fw-semibold">Low Stock Items</span>
                <h3 class="fw-bold text-dark mb-0"><?= $lowStockCount ?></h3>
                <small class="<?= $lowStockCount > 0 ? 'text-danger' : 'text-success' ?> fw-medium">
                  <?= $lowStockCount > 0
                    ? '<i class="bi bi-arrow-down-short"></i> Action required'
                    : '<i class="bi bi-check-circle"></i> All good' ?>
                </small>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
              <div class="metric-icon bg-info-subtle text-info rounded-3 p-3 me-3">
                <i class="bi bi-people fs-3"></i>
              </div>
              <div>
                <span class="text-muted fs-7 text-uppercase fw-semibold">Customers Served</span>
                <h3 class="fw-bold text-dark mb-0"><?= $customersToday ?></h3>
                <small class="text-muted fw-medium">Unique customers today</small>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ CHART + TOP SELLERS ============ -->
      <section class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
              <h5 class="fw-bold text-dark mb-0">Sales Activity</h5>
              <form method="GET" class="d-flex">
                <select class="form-select form-select-sm w-auto" name="chart"
                  onchange="this.form.submit()">
                  <option value="today" <?= $chartRange === 'today'     ? 'selected' : '' ?>>Today (Hourly)</option>
                  <option value="yesterday" <?= $chartRange === 'yesterday' ? 'selected' : '' ?>>Yesterday (Hourly)</option>
                  <option value="week" <?= $chartRange === 'week'      ? 'selected' : '' ?>>Last 7 Days</option>
                </select>
              </form>
            </div>
            <div class="card-body">
              <canvas id="salesChart" height="260"
                data-labels='<?= json_encode($hourlyLabels, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                data-values='<?= json_encode($hourlyData,   JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                data-currency='<?= htmlspecialchars($CUR, ENT_QUOTES) ?>'></canvas>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="fw-bold text-dark mb-0">Top Sellers Today</h5>
            </div>
            <div class="card-body p-0">
              <?php if (empty($topSellers)): ?>
                <div class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                  No sales today yet.
                </div>
              <?php else: ?>
                <ul class="list-group list-group-flush border-top-0">
                  <?php foreach ($topSellers as $i => $p): ?>
                    <li class="list-group-item d-flex align-items-center py-3 px-3">
                      <span class="badge <?= $i === 0 ? 'bg-primary' : 'bg-secondary' ?> rounded-circle me-3">
                        <?= $i + 1 ?>
                      </span>
                      <div class="flex-grow-1">
                        <h6 class="mb-0 fw-semibold text-truncate" style="max-width: 200px;">
                          <?= htmlspecialchars($p['name']) ?>
                        </h6>
                        <small class="text-muted"><?= (int)$p['qty'] ?> units sold</small>
                      </div>
                      <span class="fw-bold text-dark text-nowrap">
                        <?= money($p['total'], $CUR, $CURPOS) ?>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ RECENT TRANSACTIONS ============ -->
      <section class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
          <h5 class="fw-bold text-dark mb-0">Recent Transactions</h5>
          <a href="sales_history.php" class="text-primary text-decoration-none fw-medium small">
            View All <i class="bi bi-chevron-right"></i>
          </a>
        </div>
        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Invoice</th>
                <th>Customer</th>
                <th>Payment</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="text-end pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentSales)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    No transactions yet.
                  </td>
                </tr>
                <?php else: foreach ($recentSales as $s):
                  $statusMap = [
                    'completed' => ['bg-success-subtle text-success border border-success-subtle', 'Completed'],
                    'pending'   => ['bg-warning-subtle text-warning border border-warning-subtle', 'Udhar'],
                    'partial'   => ['bg-info-subtle text-info border border-info-subtle',       'Partial'],
                    'paid'      => ['bg-success-subtle text-success border border-success-subtle', 'Paid'],
                    'cancelled' => ['bg-secondary-subtle text-secondary border border-secondary-subtle', 'Cancelled'],
                  ];
                  [$badgeClass, $badgeLabel] = $statusMap[$s['status']] ?? ['bg-secondary-subtle text-secondary', $s['status']];

                  $methodIcon = [
                    'cash'    => 'bi-cash',
                    'card'    => 'bi-credit-card',
                    'digital' => 'bi-qr-code',
                    'bank_transfer' => 'bi-bank',
                    'other'   => 'bi-three-dots',
                  ][$s['payment_method']] ?? 'bi-credit-card';
                ?>
                  <tr>
                    <td class="ps-3 fw-semibold">
                      <?= htmlspecialchars($s['invoice_no']) ?>
                      <small class="text-muted d-block fw-normal">
                        <?= date('d M Y, H:i', strtotime($s['created_at'])) ?>
                      </small>
                    </td>
                    <td><?= htmlspecialchars($s['customer_name']) ?></td>
                    <td>
                      <i class="bi <?= $methodIcon ?> me-1 text-secondary"></i>
                      <?= ucfirst(str_replace('_', ' ', $s['payment_method'])) ?>
                    </td>
                    <td class="fw-bold"><?= money($s['total_amount'], $CUR, $CURPOS) ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                    <td class="text-end pe-3">
                      <a href="sales_history.php?invoice=<?= urlencode($s['invoice_no']) ?>"
                        class="btn btn-sm btn-outline-secondary" title="View invoice">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../../assets/js/sidebar.js"></script>
  <script src="../../assets/js/dashboard.js"></script>
</body>

</html>