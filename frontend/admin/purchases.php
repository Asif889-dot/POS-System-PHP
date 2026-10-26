<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

$S = load_settings($conn);
$CUR = $S['currency_symbol'] ?? 'Rs.';

// Fetch purchases
$purchases = $conn->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.purchase_id) as item_count
    FROM purchases p 
    ORDER BY p.purchased_at DESC, p.purchase_id DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch products for the modal dropdown
$products = $conn->query("SELECT id, name, sku, price FROM products WHERE status = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Purchases</title>
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
        <h2 class="fw-bold text-dark mb-1">Purchase History</h2>
        <p class="text-muted mb-0">Record stock entering your inventory and track supplier costs.</p>
      </div>
      <button class="btn btn-primary" onclick="openPurchaseModal()">
        <i class="bi bi-plus-lg me-1"></i> Record Purchase
      </button>
    </header>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Date</th>
              <th>Supplier</th>
              <th>Invoice #</th>
              <th>Items</th>
              <th class="text-end">Total Cost</th>
              <th class="text-end pe-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($purchases)): ?>
              <tr><td colspan="6" class="text-center text-muted py-5">No purchases recorded yet.</td></tr>
            <?php else: foreach ($purchases as $p): ?>
              <tr>
                <td class="ps-3"><?= date('d M Y', strtotime($p['purchased_at'])) ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($p['supplier_name']) ?></td>
                <td><code><?= htmlspecialchars($p['invoice_no'] ?: '—') ?></code></td>
                <td><span class="badge bg-secondary"><?= $p['item_count'] ?> items</span></td>
                <td class="text-end fw-bold text-danger">
                  <?= htmlspecialchars($CUR) ?><?= number_format($p['total_amount'], 2) ?>
                </td>
                <td class="text-end pe-3">
                  <button class="btn btn-sm btn-outline-primary" onclick="viewPurchase(<?= $p['purchase_id'] ?>)">
                    <i class="bi bi-eye"></i> View
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

<!-- Add Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down text-primary me-2"></i>Record Purchase</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="purchaseForm">
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Supplier Name <span class="text-danger">*</span></label>
              <input type="text" name="supplier_name" class="form-control" required placeholder="e.g. Tech Distributors">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Invoice / Bill #</label>
              <input type="text" name="invoice_no" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Purchase Date</label>
              <input type="date" name="purchased_at" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Items Purchased</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPurchaseRow()">
              <i class="bi bi-plus-lg"></i> Add Item
            </button>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered align-middle" id="purchaseTable">
              <thead class="table-light">
                <tr>
                  <th style="width: 40%;">Product</th>
                  <th style="width: 15%;">Qty</th>
                  <th style="width: 20%;">Cost Price (<?= htmlspecialchars($CUR) ?>)</th>
                  <th style="width: 20%;">Subtotal</th>
                  <th style="width: 5%;"></th>
                </tr>
              </thead>
              <tbody id="purchaseRows">
                <!-- Rows injected by JS -->
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                  <td class="fw-bold text-danger" id="purchaseTotal"><?= htmlspecialchars($CUR) ?>0.00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-3">
            <label class="form-label fw-semibold">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Purchase</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Purchase Modal -->
<div class="modal fade" id="viewPurchaseModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Purchase Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewPurchaseBody">
        <!-- Loaded by JS -->
      </div>
    </div>
  </div>
</div>

<!-- Hidden Product Data for JS -->
<script>
  const PRODUCTS = <?= json_encode($products) ?>;
  const CURRENCY = "<?= htmlspecialchars($CUR) ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  let purchaseModal, viewModal;
  document.addEventListener('DOMContentLoaded', () => {
    purchaseModal = new bootstrap.Modal(document.getElementById('purchaseModal'));
    viewModal = new bootstrap.Modal(document.getElementById('viewPurchaseModal'));
    addPurchaseRow(); // Add first row by default
  });

  function openPurchaseModal() {
    document.getElementById('purchaseForm').reset();
    document.getElementById('purchaseRows').innerHTML = '';
    addPurchaseRow();
    purchaseModal.show();
  }

  function addPurchaseRow() {
    const tbody = document.getElementById('purchaseRows');
    const tr = document.createElement('tr');
    
    let options = '<option value="">Select product...</option>';
    PRODUCTS.forEach(p => {
      options += `<option value="${p.id}" data-price="${p.price}">${p.name} (${p.sku})</option>`;
    });

    tr.innerHTML = `
      <td>
        <select class="form-select form-select-sm product-select" onchange="updateRow(this)" required>
          ${options}
        </select>
      </td>
      <td><input type="number" class="form-control form-control-sm qty-input" min="1" value="1" oninput="updateRow(this)" required></td>
      <td><input type="number" step="0.01" class="form-control form-control-sm cost-input" value="0.00" oninput="updateRow(this)" required></td>
      <td class="text-end fw-semibold row-subtotal">${CURRENCY}0.00</td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calculateTotal();">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  }

  function updateRow(el) {
    const tr = el.closest('tr');
    const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
    const cost = parseFloat(tr.querySelector('.cost-input').value) || 0;
    
    // Auto-fill cost if product selected and cost is 0
    if (el.classList.contains('product-select') && cost === 0) {
      const selected = el.options[el.selectedIndex];
      if (selected.dataset.price) {
        tr.querySelector('.cost-input').value = selected.dataset.price;
      }
    }

    const newCost = parseFloat(tr.querySelector('.cost-input').value) || 0;
    const subtotal = qty * newCost;
    tr.querySelector('.row-subtotal').textContent = CURRENCY + subtotal.toFixed(2);
    
    calculateTotal();
  }

  function calculateTotal() {
    let total = 0;
    document.querySelectorAll('#purchaseRows tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
      const cost = parseFloat(tr.querySelector('.cost-input').value) || 0;
      total += (qty * cost);
    });
    document.getElementById('purchaseTotal').textContent = CURRENCY + total.toFixed(2);
  }

  document.getElementById('purchaseForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Collect items
    const items = [];
    document.querySelectorAll('#purchaseRows tr').forEach(tr => {
      const pid = tr.querySelector('.product-select').value;
      const qty = tr.querySelector('.qty-input').value;
      const cost = tr.querySelector('.cost-input').value;
      if (pid && qty > 0) {
        items.push({ product_id: pid, quantity: qty, cost_price: cost });
      }
    });

    if (items.length === 0) return alert('Please add at least one product.');

    const formData = new FormData(e.target);
    const data = {
      supplier_name: formData.get('supplier_name'),
      invoice_no: formData.get('invoice_no'),
      purchased_at: formData.get('purchased_at'),
      notes: formData.get('notes'),
      items: items
    };

    const btn = e.target.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

    try {
      const res = await fetch('../../backend/sale/add_purchase.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const result = await res.json();
      
      if (result.success) {
        location.reload();
      } else {
        alert('Error: ' + result.message);
        btn.disabled = false;
        btn.innerHTML = orig;
      }
    } catch (err) {
      alert('Server error.');
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });

  // View Purchase Details
  async function viewPurchase(id) {
    document.getElementById('viewPurchaseBody').innerHTML = '<div class="text-center py-4"><span class="spinner-border"></span></div>';
    viewModal.show();
    
    try {
      const res = await fetch(`../../backend/sale/get_purchase.php?id=${id}`);
      const json = await res.json();
      
      if (!json.success) throw new Error(json.message);
      
      const p = json.data;
      let rows = p.items.map(i => `
        <tr>
          <td>${i.product_name}</td>
          <td class="text-center">${i.quantity}</td>
          <td class="text-end">${CURRENCY}${parseFloat(i.cost_price).toFixed(2)}</td>
          <td class="text-end fw-semibold">${CURRENCY}${parseFloat(i.subtotal).toFixed(2)}</td>
        </tr>
      `).join('');

      document.getElementById('viewPurchaseBody').innerHTML = `
        <div class="row mb-3">
          <div class="col-sm-6">
            <div class="text-muted small">Supplier</div>
            <div class="fw-bold fs-5">${p.supplier_name}</div>
          </div>
          <div class="col-sm-6 text-sm-end">
            <div class="text-muted small">Date</div>
            <div class="fw-bold">${new Date(p.purchased_at).toLocaleDateString()}</div>
            ${p.invoice_no ? `<div class="mt-1"><span class="badge bg-secondary">Inv: ${p.invoice_no}</span></div>` : ''}
          </div>
        </div>
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th class="text-center">Qty</th>
              <th class="text-end">Cost</th>
              <th class="text-end">Subtotal</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="text-end fw-bold">Total:</td>
              <td class="text-end fw-bold text-danger">${CURRENCY}${parseFloat(p.total_amount).toFixed(2)}</td>
            </tr>
          </tfoot>
        </table>
        ${p.notes ? `<div class="mt-3 p-2 bg-light rounded"><small class="text-muted">Notes: ${p.notes}</small></div>` : ''}
      `;
    } catch (err) {
      document.getElementById('viewPurchaseBody').innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
    }
  }
</script>
</body>
</html>