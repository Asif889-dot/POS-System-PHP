<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../../config/conn.php";
/** @var mysqli $conn */

// Fetch total POS sales income
$salesQuery = $conn->query("SELECT SUM(total_amount) AS total_sales FROM sales");
$totalSales = (float)($salesQuery->fetch_assoc()['total_sales'] ?? 0);

// Fetch manual extra income
$incomeQuery = $conn->query("SELECT SUM(amount) AS total_income FROM transactions WHERE type='income'");
$extraIncome = (float)($incomeQuery->fetch_assoc()['total_income'] ?? 0);

// Fetch total expenses
$expenseQuery = $conn->query("SELECT SUM(amount) AS total_expense FROM transactions WHERE type='expense'");
$totalExpense = (float)($expenseQuery->fetch_assoc()['total_expense'] ?? 0);

$grossRevenue = $totalSales + $extraIncome;
$netProfit = $grossRevenue - $totalExpense;

// Fetch all ledger transactions
$transactions = $conn->query("
    SELECT t.*, u.username 
    FROM transactions t 
    LEFT JOIN users u ON t.user_id = u.id 
    ORDER BY t.transaction_date DESC, t.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account & Financial Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body class="bg-light">

<div class="d-flex">
  <?php include_once "../../config/sidebar.php"; ?>

  <main class="flex-grow-1 p-3 p-md-4 main-content">
    
    <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
      <div>
        <h2 class="fw-bold text-dark mb-1">Accounts & Cash Flow</h2>
        <p class="text-muted mb-0">Track system revenue, general expenses, and net profit performance.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transactionModal">
        <i class="bi bi-plus-circle me-1"></i> Log Transaction
      </button>
    </header>

    <!-- Financial Overview Metrics -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 border-start border-primary border-4 rounded-3">
          <div class="text-muted small fw-semibold">Gross Revenue (Sales + Manual)</div>
          <div class="fs-4 fw-bold text-dark mt-1">$<?= number_format($grossRevenue, 2); ?></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 border-start border-danger border-4 rounded-3">
          <div class="text-muted small fw-semibold">Total Expenses</div>
          <div class="fs-4 fw-bold text-danger mt-1">$<?= number_format($totalExpense, 2); ?></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 border-start border-success border-4 rounded-3">
          <div class="text-muted small fw-semibold">Net Profit</div>
          <div class="fs-4 fw-bold <?= $netProfit >= 0 ? 'text-success' : 'text-danger'; ?> mt-1">
            $<?= number_format($netProfit, 2); ?>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 border-start border-info border-4 rounded-3">
          <div class="text-muted small fw-semibold">POS Terminal Revenue</div>
          <div class="fs-4 fw-bold text-info mt-1">$<?= number_format($totalSales, 2); ?></div>
        </div>
      </div>
    </div>

    <!-- Ledger Table -->
    <div class="card border-0 shadow-sm rounded-3">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="fw-bold mb-0">General Ledger</h5>
      </div>
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Date</th>
              <th>Type</th>
              <th>Category</th>
              <th>Description</th>
              <th>Payment Method</th>
              <th>Amount</th>
              <th>Logged By</th>
              <th class="text-end pe-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($transactions && $transactions->num_rows > 0): ?>
              <?php while ($row = $transactions->fetch_assoc()): ?>
                <tr>
                  <td class="ps-3"><?= date('Y-m-d', strtotime($row['transaction_date'])); ?></td>
                  <td>
                    <span class="badge <?= $row['type'] === 'income' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                      <?= ucfirst($row['type']); ?>
                    </span>
                  </td>
                  <td class="fw-semibold text-dark"><?= htmlspecialchars($row['category']); ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($row['description'] ?? '-'); ?></td>
                  <td><span class="badge bg-light text-dark border"><?= strtoupper($row['payment_method']); ?></span></td>
                  <td class="fw-bold <?= $row['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                    <?= $row['type'] === 'income' ? '+' : '-'; ?>$<?= number_format($row['amount'], 2); ?>
                  </td>
                  <td><small class="text-muted"><?= htmlspecialchars($row['username'] ?? 'System'); ?></small></td>
                  <td class="text-end pe-3">
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTransaction(<?= $row['id']; ?>)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">No manual transactions logged.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- Transaction Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Log Financial Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="transactionForm">
        <div class="modal-body">
          
          <div class="mb-3">
            <label class="form-label small fw-semibold">Entry Type *</label>
            <select class="form-select" name="type" required>
              <option value="expense" selected>Expense (Money Out)</option>
              <option value="income">Extra Income (Money In)</option>
            </select>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Category *</label>
              <input type="text" class="form-control" name="category" placeholder="e.g., Rent, Utilities" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Amount ($) *</label>
              <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Payment Method</label>
              <select class="form-select" name="payment_method">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Date</label>
              <input type="date" class="form-control" name="transaction_date" value="<?= date('Y-m-d'); ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Description / Notes</label>
            <textarea class="form-control" name="description" rows="2" placeholder="Optional details..."></textarea>
          </div>

        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Entry</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('transactionForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);

  try {
    const res = await fetch('../../backend/accounts/transaction_actions.php?action=save', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();

    if (result.success) {
      alert(result.message);
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (err) {
    alert('Failed to save transaction.');
  }
});

async function deleteTransaction(id) {
  if (!confirm('Are you sure you want to delete this ledger entry?')) return;

  const formData = new FormData();
  formData.append('id', id);

  try {
    const res = await fetch('../../backend/accounts/transaction_actions.php?action=delete', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();

    if (result.success) {
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (err) {
    alert('Deletion failed.');
  }
}
</script>
</body>
</html>