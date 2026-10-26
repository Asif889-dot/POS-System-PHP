<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../../config/conn.php";
require_once __DIR__ . "/../../config/settings_helper.php";
/** @var mysqli $conn */

// Get and sanitize the invoice number
$invoiceNo = trim($_GET['invoice_no'] ?? '');

if (empty($invoiceNo)) { 
    die("<div style='text-align:center;margin-top:50px;font-family:sans-serif;'>
            <h2>Error: No Invoice Number Provided</h2>
            <p>Please go back to Sales History and click Print again.</p>
         </div>"); 
}

// Fetch Sale
$stmt = $conn->prepare("
    SELECT s.*, c.customer_name 
    FROM sales s 
    LEFT JOIN customers c ON c.customer_id = s.customer_id 
    WHERE s.invoice_no = ?
");
$stmt->bind_param("s", $invoiceNo);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) { 
    die("<div style='text-align:center;margin-top:50px;font-family:sans-serif;'>
            <h2>Error: Invoice Not Found</h2>
            <p>No sale exists with the invoice number: " . htmlspecialchars($invoiceNo) . "</p>
         </div>"); 
}

// Fetch Items
$stmt = $conn->prepare("
    SELECT si.*, p.name AS product_name 
    FROM sale_items si 
    LEFT JOIN products p ON p.id = si.product_id 
    WHERE si.sale_id = ?
");
$stmt->bind_param("i", $sale['id']);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load the client's saved settings
$S = load_settings($conn);

// Extract display variables
$theme      = $S['bill_theme'] ?? 'classic';
$paper      = $S['bill_paper'] ?? 'thermal-80';
$showLogo   = ($S['bill_show_logo'] ?? '1') === '1';
$showHeader = ($S['bill_show_header'] ?? '1') === '1';
$showQR     = ($S['bill_show_qr'] ?? '1') === '1';
$showPrices = ($S['bill_show_prices'] ?? '1') === '1';
$showCashier= ($S['bill_show_cashier'] ?? '0') === '1';

$orgName  = $S['org_name'] ?? 'My Store';
$orgAddr  = $S['org_address'] ?? '';
$orgPhone = $S['org_phone'] ?? '';
$orgLogo  = $S['org_logo'] ?? '';
$CUR      = $S['currency_symbol'] ?? 'Rs.';
$thankYou = $S['bill_thank_you'] ?? 'Thank you for shopping with us!';
$footNote = $S['bill_footer_note'] ?? '';
$cashier  = $S['cashier_name'] ?? 'Admin';

// Helper for money
function money($v, $cur) { return $cur . number_format((float)$v, 2); }

// Determine Paper Size for CSS
$pageCSS = '';
if ($paper === 'thermal-80') {
    $pageCSS = "@page { size: 80mm auto; margin: 4mm; } body { width: 72mm; }";
} elseif ($paper === 'thermal-58') {
    $pageCSS = "@page { size: 58mm auto; margin: 3mm; } body { width: 52mm; }";
} else {
    $pageCSS = "@page { size: A4; margin: 12mm; } body { max-width: 210mm; margin: 0 auto; }";
}

// Theme Colors
$themes = [
    'classic' => ['font' => 'Georgia, serif', 'bg' => '#f8f9fa', 'accent' => '#000', 'text' => '#000'],
    'modern'  => ['font' => 'system-ui, sans-serif', 'bg' => '#0d6efd', 'accent' => '#0d6efd', 'text' => '#fff'],
    'minimal' => ['font' => 'system-ui, sans-serif', 'bg' => '#fff', 'accent' => '#495057', 'text' => '#000'],
    'bold'    => ['font' => 'system-ui, sans-serif', 'bg' => '#dc3545', 'accent' => '#dc3545', 'text' => '#fff', 'weight' => 600],
    'elegant' => ['font' => 'Georgia, serif', 'bg' => '#fdfcf8', 'accent' => '#b89440', 'text' => '#5a4a1f'],
    'wholesale'=> ['font' => 'Arial, sans-serif', 'bg' => '#fff', 'accent' => '#333', 'text' => '#000']
];
$t = $themes[$theme] ?? $themes['classic'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt - <?= htmlspecialchars($invoiceNo) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <style>
    body { font-family: <?= $t['font'] ?>; background: #f5f6f8; padding: 20px; color: #333; margin: 0; }
    .receipt-box { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.08); margin: 0 auto; font-size: 12px; overflow: hidden; }
    
    /* Print Styles */
    <?= $pageCSS ?>
    @media print {
      body { background: #fff; padding: 0; }
      .no-print { display: none !important; }
      .receipt-box { box-shadow: none !important; padding: 0; border: none; border-radius: 0; }
      /* Ensure background colors print */
      * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }

    /* Wholesale Theme Overrides */
    <?php if ($theme === 'wholesale'): ?>
      .receipt-box { width: 100%; max-width: 800px; padding: 40px; font-size: 13px; border: 1px solid #ddd; }
      .table-wholesale th, .table-wholesale td { border: 1px solid #ccc; padding: 8px; }
      .header-title { color: #f97316; font-size: 28px; font-weight: bold; text-transform: uppercase; }
    <?php endif; ?>
  </style>
</head>
<body>

<div class="no-print text-center mb-4">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Now</button>
    <button class="btn btn-light border" onclick="window.close()">Close Tab</button>
</div>

<div class="receipt-box">
  
  <!-- ========================================== -->
  <!-- WHOLESALE THEME LAYOUT                     -->
  <!-- ========================================== -->
  <?php if ($theme === 'wholesale'): ?>
    
    <?php if ($showHeader): ?>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px;">
        <div style="width: 60%;">
            <?php if ($showLogo && $orgLogo): ?>
              <img src="../../<?= htmlspecialchars($orgLogo) ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;"><br>
            <?php endif; ?>
            <div style="font-size: 22px; font-weight: bold;"><?= htmlspecialchars($orgName) ?></div>
            <div style="font-size: 11px; color: #666; margin-top: 4px;"><?= htmlspecialchars($orgAddr) ?><br><?= htmlspecialchars($orgPhone) ?></div>
        </div>
        <div style="text-align: right;">
            <div class="header-title">Invoice</div>
        </div>
    </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <div style="width: 50%; border: 1px solid #ccc; padding: 10px;">
            <div style="font-weight: bold; font-size: 14px; margin-bottom: 4px;"><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer') ?></div>
            <?php if ($sale['customer_id']): ?>
                <div style="color: #666; font-weight: bold;">Customer Code : <?= $sale['customer_id'] ?></div>
            <?php endif; ?>
        </div>
        <div style="width: 45%;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="border: 1px solid #ccc; padding: 6px; font-weight: bold; background: #f9f9f9; width: 40%;">Date</td>
                    <td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><?= date('d M Y', strtotime($sale['created_at'])) ?></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ccc; padding: 6px; font-weight: bold; background: #f9f9f9;">Invoice #</td>
                    <td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><?= htmlspecialchars($sale['invoice_no']) ?></td>
                </tr>
                <?php if ($showCashier): ?>
                <tr>
                    <td style="border: 1px solid #ccc; padding: 6px; font-weight: bold; background: #f9f9f9;">Sale Men</td>
                    <td style="border: 1px solid #ccc; padding: 6px; text-align: right;"><?= htmlspecialchars($cashier) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr style="background: #e5e7eb;">
                <th style="border: 1px solid #ccc; padding: 8px; text-align: left; width: 10%;">Qty</th>
                <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Description</th>
                <?php if ($showPrices): ?>
                <th style="border: 1px solid #ccc; padding: 8px; text-align: right; width: 20%;">Unit Price</th>
                <th style="border: 1px solid #ccc; padding: 8px; text-align: right; width: 20%;">Amount</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td style="border: 1px solid #ccc; padding: 8px;"><?= $it['quantity'] ?></td>
                    <td style="border: 1px solid #ccc; padding: 8px;"><?= htmlspecialchars($it['product_name'] ?: 'Deleted Product') ?></td>
                    <?php if ($showPrices): ?>
                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right;"><?= money($it['unit_price'], $CUR) ?></td>
                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right;"><?= money($it['subtotal'], $CUR) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="display: flex; justify-content: space-between;">
        <div style="width: 55%; font-size: 10px; color: #555; border: 1px solid #ccc; padding: 10px;">
            <?= htmlspecialchars($thankYou) ?><br><?= htmlspecialchars($footNote) ?>
        </div>
        <div style="width: 40%;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <tr>
                    <td style="padding: 6px; font-weight: bold; text-align: right;">Total:</td>
                    <td style="padding: 6px; text-align: right; font-weight: bold;"><?= money($sale['total_amount'], $CUR) ?></td>
                </tr>
                <tr>
                    <td style="padding: 6px; text-align: right;">Paid:</td>
                    <td style="padding: 6px; text-align: right;"><?= money($sale['paid_amount'], $CUR) ?></td>
                </tr>
                <tr>
                    <td style="padding: 6px; text-align: right; font-weight: bold; color: #f97316; font-size: 16px;">Balance due:</td>
                    <td style="padding: 6px; text-align: right; font-weight: bold; color: #f97316; font-size: 16px;"><?= money($sale['due_amount'], $CUR) ?></td>
                </tr>
            </table>
        </div>
    </div>

  <!-- ========================================== -->
  <!-- STANDARD THERMAL / A4 LAYOUT               -->
  <!-- ========================================== -->
  <?php else: ?>

    <?php if ($showHeader): ?>
    <div style="background: <?= $t['bg'] ?>; color: <?= $t['text'] ?>; padding: 15px; text-align: center; margin: -20px -20px 20px -20px; <?= $theme !== 'classic' && $theme !== 'minimal' ? 'border-radius: 6px 6px 0 0;' : '' ?>">
        <?php if ($showLogo && $orgLogo): ?>
          <img src="../../<?= htmlspecialchars($orgLogo) ?>" style="max-height: 50px; margin-bottom: 10px;"><br>
        <?php endif; ?>
        <div style="font-size: 18px; font-weight: bold; letter-spacing: 0.5px;"><?= htmlspecialchars($orgName) ?></div>
        <div style="font-size: 10px; opacity: 0.8;"><?= htmlspecialchars($orgAddr) ?><br><?= htmlspecialchars($orgPhone) ?></div>
    </div>
    <?php endif; ?>

    <div class="text-center mb-3">
        <div style="font-size: 11px; color: #666;">Invoice: <strong><?= htmlspecialchars($sale['invoice_no']) ?></strong></div>
        <?php if ($sale['customer_id']): ?>
            <div style="font-size: 10px;"><strong>Billed To:</strong> <?= htmlspecialchars($sale['customer_name']) ?> (Code: <?= $sale['customer_id'] ?>)</div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between mb-3" style="font-size: 10px; color: #666;">
        <span><?= date('d M Y', strtotime($sale['created_at'])) ?></span>
        <span><?= date('h:i A', strtotime($sale['created_at'])) ?></span>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 15px;">
        <thead>
            <tr style="border-bottom: 1px solid <?= $t['accent'] ?>; border-top: 1px solid <?= $t['accent'] ?>; font-size: 10px; text-transform: uppercase;">
                <th style="text-align: left; padding: 5px 0;">Item</th>
                <th style="text-align: center; padding: 5px 0;">Qty</th>
                <?php if ($showPrices): ?>
                <th style="text-align: right; padding: 5px 0;">Price</th>
                <th style="text-align: right; padding: 5px 0;">Subtotal</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td style="padding: 4px 0;"><?= htmlspecialchars($it['product_name'] ?: 'Deleted Product') ?></td>
                    <td style="text-align: center; padding: 4px 0;"><?= $it['quantity'] ?></td>
                    <?php if ($showPrices): ?>
                    <td style="text-align: right; padding: 4px 0;"><?= money($it['unit_price'], $CUR) ?></td>
                    <td style="text-align: right; padding: 4px 0;"><?= money($it['subtotal'], $CUR) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="border-top: 1px dashed <?= $t['accent'] ?>;">
                <td colspan="2" style="padding: 6px 0; font-weight: 700;">TOTAL</td>
                <?php if ($showPrices): ?>
                <td colspan="2" style="padding: 6px 0; text-align: right; font-weight: 700; color: <?= $t['accent'] ?>;"><?= money($sale['total_amount'], $CUR) ?></td>
                <?php endif; ?>
            </tr>
            <tr>
                <td colspan="2" style="padding: 2px 0; font-size: 10px;">Paid</td>
                <?php if ($showPrices): ?>
                <td colspan="2" style="padding: 2px 0; text-align: right; font-size: 10px;"><?= money($sale['paid_amount'], $CUR) ?></td>
                <?php endif; ?>
            </tr>
            <?php if ($sale['change_amount'] > 0): ?>
            <tr>
                <td colspan="2" style="padding: 2px 0; font-size: 10px;">Change</td>
                <?php if ($showPrices): ?>
                <td colspan="2" style="padding: 2px 0; text-align: right; font-size: 10px;"><?= money($sale['change_amount'], $CUR) ?></td>
                <?php endif; ?>
            </tr>
            <?php endif; ?>
            <?php if ($sale['due_amount'] > 0): ?>
            <tr>
                <td colspan="2" style="padding: 2px 0; font-size: 10px; color: #b91c1c;">Due</td>
                <?php if ($showPrices): ?>
                <td colspan="2" style="padding: 2px 0; text-align: right; font-size: 10px; color: #b91c1c; font-weight: 600;"><?= money($sale['due_amount'], $CUR) ?></td>
                <?php endif; ?>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <?php if ($showQR): ?>
      <div class="text-center mt-3 pt-3" style="border-top: 1px dashed <?= $t['accent'] ?>;">
        <div id="qrcode" style="display: inline-block; padding: 8px; background: #fff; border: 1px solid #ccc;"></div>
        <div style="font-size: 8px; color: #666; margin-top: 4px; letter-spacing: 1px;">SCAN TO VERIFY</div>
      </div>
    <?php endif; ?>

    <div class="text-center mt-4 pt-3" style="border-top: 1px dashed <?= $t['accent'] ?>; font-size: 10px;">
        <div class="fw-bold" style="color: <?= $t['accent'] ?>;"><?= htmlspecialchars($thankYou) ?></div>
        <?php if ($footNote): ?><div class="text-muted"><?= htmlspecialchars($footNote) ?></div><?php endif; ?>
    </div>

  <?php endif; ?>

</div>

<script>
  // Generate QR Code
  <?php if ($showQR): ?>
  document.addEventListener('DOMContentLoaded', () => {
      new QRCode(document.getElementById("qrcode"), {
          text: "<?= htmlspecialchars($sale['invoice_no']) ?>",
          width: 64,
          height: 64,
          colorDark : "#000000",
          colorLight : "#ffffff",
          correctLevel : QRCode.CorrectLevel.M
      });
  });
  <?php endif; ?>

  // Auto-print after loading images and QR
  window.onload = () => {
      setTimeout(() => { window.print(); }, 600);
  };
</script>
</body>
</html>