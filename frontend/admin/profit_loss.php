<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

$S = load_settings($conn);
$CUR = $S['currency_symbol'] ?? 'Rs.';

$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate   = $_GET['to_date']   ?? date('Y-m-d');

// 1. Total Sales Revenue
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS revenue,
        COALESCE(SUM(paid_amount), 0) AS collected,
        COALESCE(SUM(due_amount), 0) AS pending
    FROM sales
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $fromDate, $toDate);
$stmt->execute();
$salesData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Cost of Goods Sold (COGS)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(si.cost_price * si.quantity), 0) AS cogs
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $fromDate, $toDate);
$stmt->execute();
$cogsData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Expenses
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
");
$stmt->bind_param("ss", $fromDate, $toDate);
$stmt->execute();
$expenseData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$revenue  = (float)$salesData['revenue'];
$cogs     = (float)$cogsData['cogs'];
$expenses = (float)$expenseData['total_expenses'];

$grossProfit = $revenue - $cogs;
$netProfit   = $grossProfit - $expenses;
$margin      = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Profit & Loss</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <style>
    .main-content { padding: 1.5rem 1.75rem; background: #f5f6f8; }
    .stat-card { background: #fff; border-radius: .65rem; padding: 1.25rem; border: 1px solid #eef0f4; box-shadow: 0 2px 4px rgba(0,0,0,.02); }
    .stat-label { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; font-weight: 600; }
    .stat-value { font-size: 1.6rem; font-weight: 700; margin-top: .25rem; }
  </style>
</head>
<body class="bg-light">
<div class="d-flex">
  <?php include_once "../../config/sidebar.php"; ?>
  
  <main class="flex-grow-1 main-content">
    <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
      <div>
        <h2 class="fw-bold text-dark mb-1">Profit & Loss Account</h2>
        <p class="text-muted mb-0">Calculate revenue, COGS, expenses, and net profit.</p>
      </div>
      
      <form class="d-flex align-items-center gap-2" method="GET">
        <div class="input-group input-group-sm" style="width: auto;">
          <span class="input-group-text bg-white">From</span>
          <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
        </div>
        <div class="input-group input-group-sm" style="width: auto;">
          <span class="input-group-text bg-white">To</span>
          <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
      </form>
    </header>

    <div class="row g-3 mb-4">
      <!-- Revenue Card -->
      <div class="col-md-3">
        <div class="stat-card border-top border-primary border-4">
          <div class="stat-label text-primary">Revenue (Sales)</div>
          <div class="stat-value"><?= htmlspecialchars($CUR) ?><?= number_format($revenue, 2) ?></div>
        </div>
      </div>

      <!-- COGS Card -->
      <div class="col-md-3">
        <div class="stat-card border-top border-warning border-4">
          <div class="stat-label text-warning">Cost of Goods Sold</div>
          <div class="stat-value"><?= htmlspecialchars($CUR) ?><?= number_format($cogs, 2) ?></div>
        </div>
      </div>

      <!-- Expenses Card -->
      <div class="col-md-3">
        <div class="stat-card border-top border-danger border-4">
          <div class="stat-label text-danger">Total Expenses</div>
          <div class="stat-value"><?= htmlspecialchars($CUR) ?><?= number_format($expenses, 2) ?></div>
        </div>
      </div>

      <!-- Net Profit Card -->
      <div class="col-md-3">
        <div class="stat-card border-top <?= $netProfit >= 0 ? 'border-success' : 'border-danger' ?> border-4">
          <div class="stat-label <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">Net Profit</div>
          <div class="stat-value <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= htmlspecialchars($CUR) ?><?= number_format($netProfit, 2) ?>
          </div>
          <div class="small mt-2 fw-semibold <?= $margin >= 0 ? 'text-success' : 'text-danger' ?>">
            Margin: <?= number_format($margin, 1) ?>%
          </div>
        </div>
      </div>
    </div>

    <!-- Calculation Breakdown -->
    <div class="card border-0 shadow-sm rounded-3">
      <div class="card-header bg-white border-0 py-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-calculator text-primary me-2"></i>Calculation Breakdown</h5>
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <tbody>
            <tr>
              <td class="ps-4">Gross Sales Revenue</td>
              <td class="text-end pe-4 fw-bold text-success"><?= htmlspecialchars($CUR) ?> <?= number_format($revenue, 2) ?></td>
            </tr>
            <tr>
              <td class="ps-4 text-danger">Less: Cost of Goods Sold (COGS)</td>
              <td class="text-end pe-4 text-danger">- <?= htmlspecialchars($CUR) ?> <?= number_format($cogs, 2) ?></td>
            </tr>
            <tr class="table-light border-bottom border-dark">
              <td class="ps-4 fw-bold">Gross Profit</td>
              <td class="text-end pe-4 fw-bold"><?= htmlspecialchars($CUR) ?> <?= number_format($grossProfit, 2) ?></td>
            </tr>
            <tr>
              <td class="ps-4 text-danger">Less: Operating Expenses</td>
              <td class="text-end pe-4 text-danger">- <?= htmlspecialchars($CUR) ?> <?= number_format($expenses, 2) ?></td>
            </tr>
            <tr class="table-light border-top border-2 border-dark">
              <td class="ps-4 fw-bold fs-5">Net Profit</td>
              <td class="text-end pe-4 fw-bold fs-5 <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= htmlspecialchars($CUR) ?> <?= number_format($netProfit, 2) ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>