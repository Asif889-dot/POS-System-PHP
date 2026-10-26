<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

$S      = load_settings($conn);
$CUR    = $S['currency_symbol']   ?? 'Rs.';
$CURPOS = $S['currency_position'] ?? 'before';

// Active categories
$categories = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");

// Active products in stock
$products = $conn->query(
  "SELECT p.id, p.name, p.sku, p.price, p.stock, p.image, p.category_id,
          c.name AS category_name
       FROM products p
       LEFT JOIN categories c ON p.category_id = c.id
      WHERE p.status = 1 AND p.stock > 0
      ORDER BY p.name ASC"
);

// Active customers
$customers = $conn->query(
  "SELECT customer_id, customer_name, customer_business_name
       FROM customers WHERE status = 1
      ORDER BY customer_name ASC"
);

// Fetch all active category discounts to pass to JavaScript
$catDiscounts = [];
$cdResult = $conn->query("SELECT customer_id, category_id, discount_type, discount_value FROM category_discounts WHERE status = 1");
if ($cdResult) {
    while ($row = $cdResult->fetch_assoc()) {
        // Group by customer_id (or 'global' if null)
        $key = $row['customer_id'] ? $row['customer_id'] : 'global';
        $catDiscounts[$key][$row['category_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POS System | Terminal Sale</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/sale.css">
</head>

<body class="bg-light">

  <div class="d-flex">
    <?php include_once "../../config/sidebar.php"; ?>

    <main class="flex-grow-1 p-3 main-content">
      <div class="row g-3">

        <!-- ============ PRODUCTS ============ -->
        <div class="col-12 col-lg-7 col-xl-8">
          <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
            <div class="row g-2">
              <div class="col-md-7">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                  <input type="text" id="searchInput" class="form-control border-start-0"
                    placeholder="Search product or scan SKU…">
                </div>
              </div>
              <div class="col-md-5">
                <select id="categoryFilter" class="form-select">
                  <option value="">All Categories</option>
                  <?php if ($categories && $categories->num_rows): ?>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                      <option value="<?= (int)$cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?></option>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="row g-3" id="productGrid">
            <?php if ($products && $products->num_rows): ?>
              <?php while ($prod = $products->fetch_assoc()): ?>
                <div class="col-6 col-md-4 col-xl-3 product-item"
                  data-category="<?= (int)$prod['category_id']; ?>"
                  data-name="<?= htmlspecialchars(strtolower($prod['name'])); ?>"
                  data-sku="<?= htmlspecialchars(strtolower($prod['sku'])); ?>">
                  <div class="card h-100 border-0 shadow-sm rounded-3 product-card"
                    onclick='addToCart(<?= json_encode($prod, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                    <div class="card-body p-2 text-center">
                      <?php if (!empty($prod['image'])): ?>
                        <img src="../../<?= htmlspecialchars($prod['image']); ?>"
                          class="img-fluid rounded mb-2 object-fit-cover product-thumb"
                          alt="<?= htmlspecialchars($prod['name']); ?>">
                      <?php else: ?>
                        <div class="bg-light rounded mb-2 d-flex align-items-center justify-content-center product-thumb">
                          <i class="bi bi-box fs-1 text-muted"></i>
                        </div>
                      <?php endif; ?>
                      <h6 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($prod['name']); ?></h6>
                      <span class="text-primary fw-bold product-price">
                        <?= htmlspecialchars($CUR) ?><?= number_format($prod['price'], 2); ?>
                      </span>
                      <div class="text-muted small product-stock"><?= (int)$prod['stock']; ?> in stock</div>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="col-12">
                <div class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                  No products available in stock.
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ============ CART ============ -->
        <div class="col-12 col-lg-5 col-xl-4">
          <div class="card border-0 shadow-sm rounded-3 h-100 d-flex flex-column cart-panel">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0"><i class="bi bi-cart3 me-2 text-primary"></i>Current Order</h5>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearCart()" title="Clear cart">
                <i class="bi bi-trash"></i>
              </button>
            </div>

            <div class="card-body p-2 flex-grow-1 cart-body">
              <table class="table align-middle table-borderless mb-0">
                <tbody id="cartTableBody">
                  <tr id="emptyCartRow">
                    <td colspan="4" class="text-center text-muted py-4">Cart is empty</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="card-footer bg-white border-top p-3">

              <!-- Customer -->
              <div class="mb-2">
                <label class="form-label small fw-semibold">
                  Customer <span class="text-muted fw-normal">(required for Udhar / Partial)</span>
                </label>
                <select id="customerSelect" class="form-select form-select-sm">
                  <option value="">— Walk-in —</option>
                  <?php if ($customers && $customers->num_rows): ?>
                    <?php while ($cu = $customers->fetch_assoc()): ?>
                      <option value="<?= (int)$cu['customer_id']; ?>">
                        <?= htmlspecialchars($cu['customer_name']); ?>
                        <?= $cu['customer_business_name'] ? ' — ' . htmlspecialchars($cu['customer_business_name']) : ''; ?>
                      </option>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </select>
              </div>

              <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold">Total:</span>
                <span class="fw-bold fs-4 text-primary" id="cartTotal"><?= htmlspecialchars($CUR) ?>0.00</span>
              </div>

              <!-- Payment type -->
              <div class="mb-2">
                <label class="form-label small fw-semibold d-block">Payment Type</label>
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="paymentType" id="pt-paid" value="paid" checked>
                  <label class="btn btn-outline-primary btn-sm" for="pt-paid">
                    <i class="bi bi-check-circle me-1"></i>Full Paid
                  </label>

                  <input type="radio" class="btn-check" name="paymentType" id="pt-partial" value="partial">
                  <label class="btn btn-outline-primary btn-sm" for="pt-partial">
                    <i class="bi bi-circle-half me-1"></i>Partial
                  </label>

                  <input type="radio" class="btn-check" name="paymentType" id="pt-udhar" value="udhar">
                  <label class="btn btn-outline-warning btn-sm" for="pt-udhar">
                    <i class="bi bi-clock-history me-1"></i>Udhar
                  </label>
                </div>
              </div>

              <div class="row g-2 mb-2">
                <div class="col-7">
                  <label class="form-label small fw-semibold">Payment Method</label>
                  <select id="paymentMethod" class="form-select form-select-sm">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="digital">Digital Wallet</option>
                  </select>
                </div>
                <div class="col-5">
                  <label class="form-label small fw-semibold">Amount Paid</label>
                  <input type="number" id="paidAmount" step="0.01" min="0"
                    class="form-control form-control-sm" placeholder="0.00">
                </div>
              </div>

              <div class="mb-2" id="dueDateWrap" style="display:none;">
                <label class="form-label small fw-semibold">Due Date</label>
                <input type="date" id="dueDate" class="form-control form-control-sm">
              </div>

              <div class="mb-3">
                <button class="btn btn-success w-100 py-2 fw-bold" id="checkoutBtn"
                  onclick="processCheckout()" disabled>
                  <i class="bi bi-check2-circle me-1"></i> Complete Sale
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- ============ BILL CUSTOMIZATION MODAL ============ -->
  <div class="modal fade" id="billCustomizeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow">

        <div class="modal-header bg-white border-bottom">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-printer text-primary me-2"></i>Customize Your Bill
          </h5>
          <small class="text-muted ms-auto me-3">The customer can personalize this receipt before printing.</small>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-0">
          <div class="row g-0">

            <!-- LEFT: Options -->
            <div class="col-md-4 border-end bg-white p-3" style="max-height: 70vh; overflow-y: auto;">
              
              <!-- Edit Details Toggle -->
              <div class="form-check form-switch mb-3 p-2 bg-light rounded border">
                <input class="form-check-input ms-0 me-2" type="checkbox" id="billEditMode">
                <label class="form-check-label small fw-bold text-primary" for="billEditMode">
                  <i class="bi bi-pencil-square me-1"></i>Edit Bill Details
                </label>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold">Theme</label>
                <div class="row g-2">
                  <?php
                  $themes = [
                    ['classic',  'bi-journal-bookmark',  '#0d6efd'],
                    ['modern',   'bi-palette',           '#6f42c1'],
                    ['minimal',  'bi-dash-square',       '#495057'],
                    ['bold',     'bi-lightning-charge',  '#dc3545'],
                    ['elegant',  'bi-gem',               '#d4af37'],
                    ['wholesale', 'bi-file-earmark-spreadsheet', '#f97316'],
                  ];
                  foreach ($themes as [$key, $icon, $color]): ?>
                    <div class="col-6">
                      <input type="radio" class="btn-check" name="billTheme"
                        id="theme-<?= $key ?>" value="<?= $key ?>"
                        <?= $key === 'classic' ? 'checked' : '' ?>>
                      <label class="btn btn-outline-secondary w-100 py-2 text-start"
                        for="theme-<?= $key ?>" style="font-size: .82rem;">
                        <i class="bi <?= $icon ?>" style="color: <?= $color ?>;"></i>
                        <span class="ms-1 text-capitalize"><?= $key ?></span>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <hr class="my-3">

              <div class="mb-3">
                <label class="form-label small fw-semibold">Paper Size</label>
                <select class="form-select form-select-sm" id="billPaperSize">
                  <option value="a4">A4</option>
                  <option value="thermal-80">80mm Thermal</option>
                  <option value="thermal-58">58mm Thermal</option>
                </select>
              </div>

              <hr class="my-3">

              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="billShowLogo" checked>
                <label class="form-check-label small" for="billShowLogo">Show logo</label>
              </div>

              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="billShowHeader" checked>
                <label class="form-check-label small" for="billShowHeader">Show business info</label>
              </div>

              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="billShowQR" checked>
                <label class="form-check-label small" for="billShowQR">Show QR code</label>
              </div>

              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="billShowDateTime" checked>
                <label class="form-check-label small" for="billShowDateTime">Show date &amp; time</label>
              </div>

              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="billShowCashier">
                <label class="form-check-label small" for="billShowCashier">Show cashier name</label>
              </div>

              <hr class="my-3">

              <div class="mb-2">
                <label class="form-label small fw-semibold">Custom Thank-You Message</label>
                <textarea class="form-control form-control-sm" id="billThankYou" rows="2"
                  placeholder="Thank you for shopping!">Thank you for shopping with us!</textarea>
              </div>

              <div class="mb-2">
                <label class="form-label small fw-semibold">Footer Note</label>
                <input type="text" class="form-control form-control-sm" id="billFooterNote"
                  placeholder="e.g. Returns accepted within 7 days">
              </div>

              <div class="form-check mt-3 mb-1">
                <input class="form-check-input" type="checkbox" id="billShowPrices" checked>
                <label class="form-check-label small" for="billShowPrices">Show item prices</label>
              </div>

            </div>

            <!-- RIGHT: Live preview -->
            <div class="col-md-8 bg-secondary-subtle p-3" style="max-height: 70vh; overflow-y: auto;">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted">
                  <i class="bi bi-eye me-1"></i>Live preview — updates as you change options
                </small>
              </div>
              <div id="billPreviewContainer" class="mx-auto">
                <!-- Rendered by JS -->
              </div>
            </div>

          </div>
        </div>

        <!-- MODAL FOOTER -->
        <div class="modal-footer bg-white border-top">
          <button type="button" class="btn btn-outline-success me-auto" id="billSaveTemplateBtn">
            <i class="bi bi-bookmark-check me-1"></i> Save as Default
          </button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            Skip &amp; Close
          </button>
          <button type="button" class="btn btn-outline-primary" id="billPreviewPdfBtn">
            <i class="bi bi-file-earmark-pdf me-1"></i> Save as PDF
          </button>
          <button type="button" class="btn btn-primary" id="billPrintBtn">
            <i class="bi bi-printer me-1"></i> Print Bill
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- QR Code Library -->
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  
  <!-- Inject Category Discounts to JS -->
  <script>
      const CATEGORY_DISCOUNTS = <?= json_encode($catDiscounts) ?>;
  </script>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../assets/js/sale.js"></script>
</body>

</html>