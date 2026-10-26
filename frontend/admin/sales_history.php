<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../../config/conn.php";
require_once __DIR__ . "/../../config/settings_helper.php";
/** @var mysqli $conn */

$S = load_settings($conn);
$CUR = $S['currency_symbol'] ?? 'Rs.';

// Filter Parameters
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$payment   = $_GET['payment_method'] ?? '';
$search    = trim($_GET['search'] ?? '');

// Build Query
$query = "
    SELECT s.*, c.customer_name 
    FROM sales s
    LEFT JOIN customers c ON c.customer_id = s.customer_id
    WHERE 1=1
";
$params = [];
$types  = "";

if (!empty($startDate)) {
    $query .= " AND DATE(s.created_at) >= ?";
    $params[] = $startDate;
    $types .= "s";
}
if (!empty($endDate)) {
    $query .= " AND DATE(s.created_at) <= ?";
    $params[] = $endDate;
    $types .= "s";
}
if (!empty($payment)) {
    $query .= " AND s.payment_method = ?";
    $params[] = $payment;
    $types .= "s";
}
if (!empty($search)) {
    $query .= " AND (s.invoice_no LIKE ? OR c.customer_name LIKE ?)";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $types .= "ss";
}

$query .= " ORDER BY s.id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$salesResult = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Sales History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body class="bg-light">

<div class="d-flex">
  <?php include_once "../../config/sidebar.php"; ?>

  <main class="flex-grow-1 p-3 p-md-4 main-content">
    
    <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
      <div>
        <h2 class="fw-bold text-dark mb-1">Sales History</h2>
        <p class="text-muted mb-0">Browse past transactions, inspect details, and reprint receipts.</p>
      </div>
    </header>

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-body p-3">
        <form method="GET" class="row g-3">
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice or Customer..." value="<?= htmlspecialchars($search); ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">Start Date</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate); ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">End Date</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate); ?>">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold">Payment Method</label>
            <select name="payment_method" class="form-select form-select-sm">
              <option value="">All Methods</option>
              <option value="cash" <?= $payment === 'cash' ? 'selected' : ''; ?>>Cash</option>
              <option value="card" <?= $payment === 'card' ? 'selected' : ''; ?>>Card</option>
              <option value="digital" <?= $payment === 'digital' ? 'selected' : ''; ?>>Digital Wallet</option>
            </select>
          </div>
          <div class="col-6 col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
            <a href="sales_history.php" class="btn btn-sm btn-light border"><i class="bi bi-arrow-counterclockwise"></i></a>
          </div>
        </form>
      </div>
    </div>

    <!-- Sales Table -->
    <div class="card border-0 shadow-sm rounded-3">
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Invoice</th>
              <th>Customer</th>
              <th>Date & Time</th>
              <th>Total</th>
              <th>Paid</th>
              <th>Status</th>
              <th class="text-end pe-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($salesResult && $salesResult->num_rows > 0): ?>
              <?php while ($sale = $salesResult->fetch_assoc()): 
                $statusMap = [
                    'completed' => ['bg-success-subtle text-success', 'Paid'],
                    'pending'   => ['bg-warning-subtle text-warning', 'Udhar'],
                    'partial'   => ['bg-info-subtle text-info', 'Partial'],
                    'paid'      => ['bg-success-subtle text-success', 'Paid'],
                ];
                [$badgeClass, $badgeLabel] = $statusMap[$sale['status']] ?? ['bg-secondary-subtle text-secondary', ucfirst($sale['status'])];
              ?>
                <tr>
                  <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($sale['invoice_no']); ?></td>
                  <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer'); ?></td>
                  <td><small><?= date('d M Y, h:i A', strtotime($sale['created_at'])); ?></small></td>
                  <td class="fw-bold"><?= htmlspecialchars($CUR) ?><?= number_format($sale['total_amount'], 2); ?></td>
                  <td><?= htmlspecialchars($CUR) ?><?= number_format($sale['paid_amount'], 2); ?></td>
                  <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                  <td class="text-end pe-3">
                    <button class="btn btn-sm btn-outline-info me-1" onclick="viewReceiptModal('<?= $sale['invoice_no']; ?>')">
                      <i class="bi bi-eye"></i> View
                    </button>
                    <a href="receipt.php?invoice_no=<?= urlencode($sale['invoice_no']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-printer"></i> Print
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">No sales records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0 bg-light rounded-top">
        <h6 class="modal-title fw-bold">Receipt Details</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="modalReceiptContent">
        <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
      </div>
      <div class="modal-footer border-0 pt-0 bg-light rounded-bottom justify-content-between">
        <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnPrintModal"><i class="bi bi-printer me-1"></i> Print Receipt</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Define the currency variable from PHP to JS
const CURRENCY = "<?= htmlspecialchars($CUR) ?>";

async function viewReceiptModal(invoiceNo) {
  const modalElement = document.getElementById('receiptModal');
  const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
  const modalContent = document.getElementById('modalReceiptContent');
  const printBtn = document.getElementById('btnPrintModal');
  
  const encodedInvoice = encodeURIComponent(invoiceNo);
  printBtn.onclick = () => window.open(`receipt.php?invoice_no=${encodedInvoice}`, '_blank');
  
  modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
  modal.show();

  try {
    const res = await fetch(`../../backend/sale/get_sale_details.php?invoice_no=${encodedInvoice}`);
    const data = await res.json();

    if (!data.success) {
      modalContent.innerHTML = `<div class="alert alert-danger mb-0">${data.message}</div>`;
      return;
    }

    const sale = data.sale;
    let itemsHtml = '';
    
    if (data.items && data.items.length > 0) {
      data.items.forEach(item => {
        itemsHtml += `
          <tr>
            <td>${item.product_name || 'Deleted Product'}</td>
            <td class="text-center">${item.quantity}</td>
            <td class="text-end">${CURRENCY}${parseFloat(item.subtotal).toFixed(2)}</td>
          </tr>`;
      });
    } else {
      itemsHtml = '<tr><td colspan="3" class="text-center text-muted">No items found</td></tr>';
    }

    const totalAmount = parseFloat(sale.total_amount).toFixed(2);
    const paidAmount = parseFloat(sale.paid_amount).toFixed(2);
    const changeAmount = parseFloat(sale.change_amount).toFixed(2);
    const dueAmount = parseFloat(sale.due_amount).toFixed(2);

    modalContent.innerHTML = `
      <div class="text-center mb-3">
        <h4 class="fw-bold mb-0">${data.settings.org_name || 'My Store'}</h4>
        <small class="text-muted">${data.settings.org_address || ''}</small>
      </div>
      
      <div class="small mb-3 border-bottom pb-2">
        <div class="d-flex justify-content-between"><span>Invoice:</span><strong>${sale.invoice_no}</strong></div>
        <div class="d-flex justify-content-between"><span>Date:</span><span>${sale.created_at}</span></div>
        <div class="d-flex justify-content-between"><span>Customer:</span><span>${sale.customer_name || 'Walk-in'}</span></div>
      </div>
      
      <table class="table table-sm border-bottom small mb-3">
        <thead>
          <tr>
            <th>Item</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Total</th>
          </tr>
        </thead>
        <tbody>${itemsHtml}</tbody>
      </table>
      
      <div class="small">
        <div class="d-flex justify-content-between"><span>Total:</span><strong>${CURRENCY}${totalAmount}</strong></div>
        <div class="d-flex justify-content-between"><span>Paid:</span><span>${CURRENCY}${paidAmount}</span></div>
        ${parseFloat(changeAmount) > 0 ? `<div class="d-flex justify-content-between"><span>Change:</span><span>${CURRENCY}${changeAmount}</span></div>` : ''}
        ${parseFloat(dueAmount) > 0 ? `<div class="d-flex justify-content-between text-danger fw-bold"><span>Due:</span><span>${CURRENCY}${dueAmount}</span></div>` : ''}
      </div>
    `;
  } catch (err) {
    console.error("Modal Render Error:", err);
    modalContent.innerHTML = '<div class="alert alert-danger mb-0">Failed to load details. Please ensure <code>get_sale_details.php</code> is working properly.</div>';
  }
}
</script>
</body>
</html>