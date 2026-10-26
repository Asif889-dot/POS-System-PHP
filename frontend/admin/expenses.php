<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

$S = load_settings($conn);
$CUR = $S['currency_symbol'] ?? 'Rs.';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['expense_id'];
    $conn->query("DELETE FROM expenses WHERE expense_id = $id");
    $_SESSION['success'] = "Expense deleted successfully.";
    header("Location: expenses.php"); exit;
}

// Fetch Expenses
$expenses = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, expense_id DESC")->fetch_all(MYSQLI_ASSOC);

// Calculate Total
$totalExpenses = array_sum(array_column($expenses, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Expenses</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <style>
    .main-content { padding: 1.5rem 1.75rem; background: #f5f6f8; }
  </style>
</head>
<body class="bg-light">

<div class="d-flex">
  <?php include_once "../../config/sidebar.php"; ?>
  
  <main class="flex-grow-1 main-content">
    <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
      <div>
        <h2 class="fw-bold text-dark mb-1">Expense Tracker</h2>
        <p class="text-muted mb-0">Record utility bills, fuel, rent, and other business expenses.</p>
      </div>
      <button class="btn btn-primary" onclick="openExpenseModal()">
        <i class="bi bi-plus-lg me-1"></i> Add Expense
      </button>
    </header>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Summary Card -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 bg-danger-subtle text-danger">
          <div class="card-body">
            <h6 class="fw-bold text-uppercase mb-1" style="font-size: 0.8rem; letter-spacing: 0.5px;">Total Expenses</h6>
            <h3 class="fw-bold mb-0"><?= htmlspecialchars($CUR) ?><?= number_format($totalExpenses, 2) ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Expenses Table -->
    <div class="card border-0 shadow-sm rounded-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Date</th>
              <th>Category</th>
              <th>Notes</th>
              <th class="text-end">Amount</th>
              <th class="text-end pe-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($expenses)): ?>
              <tr><td colspan="5" class="text-center text-muted py-5">No expenses recorded yet.</td></tr>
            <?php else: foreach ($expenses as $e): ?>
              <tr>
                <td class="ps-3"><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($e['category']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($e['notes'] ?: '—') ?></td>
                <td class="text-end fw-bold text-danger"><?= htmlspecialchars($CUR) ?><?= number_format($e['amount'], 2) ?></td>
                <td class="text-end pe-3">
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete this expense?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="expense_id" value="<?= $e['expense_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack text-danger me-2"></i>Add Expense</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="expenseForm" action="../../backend/admin/save_expense.php" method="POST">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Expense Category <span class="text-danger">*</span></label>
            <select name="category" class="form-select" required>
              <option value="">Select category...</option>
              <option value="Fuel">Fuel</option>
              <option value="Utility Bills">Utility Bills (Electricity, Water, Gas)</option>
              <option value="Rent">Rent</option>
              <option value="Salary">Salary / Wages</option>
              <option value="Maintenance">Maintenance / Repairs</option>
              <option value="Office Supplies">Office Supplies</option>
              <option value="Marketing">Marketing / Ads</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount (<?= htmlspecialchars($CUR) ?>) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="amount" class="form-control" required placeholder="0.00">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Notes / Description</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Fuel for delivery van"></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Save Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  let expenseModal;
  document.addEventListener('DOMContentLoaded', () => {
    expenseModal = new bootstrap.Modal(document.getElementById('expenseModal'));
  });
  function openExpenseModal() {
    document.getElementById('expenseForm').reset();
    expenseModal.show();
  }
</script>
</body>
</html>