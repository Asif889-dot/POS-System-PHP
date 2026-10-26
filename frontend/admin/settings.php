<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
require_once dirname(__DIR__, 2) . "/config/settings_helper.php";
/** @var mysqli $conn */

// Load current settings
$S = load_settings($conn);
$userId = $_SESSION['user_id'] ?? null;

// Flash helpers
$flashSuccess = $_SESSION['success'] ?? null;
$flashError   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System | Settings</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/settings.css">
</head>

<body class="bg-light">

    <div class="d-flex">
        <?php include_once "../../config/sidebar.php"; ?>

        <main class="flex-grow-1 p-3 p-md-4 main-content">

            <!-- ============ HEADER ============ -->
            <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-3 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Settings</h2>
                    <p class="text-muted mb-0">Manage organization profile, receipt options, taxes, and display preferences.</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
            </header>

            <!-- ============ FLASH ============ -->
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($flashSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ============ TABS ============ -->
            <ul class="nav nav-pills settings-tabs mb-3" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-general" data-bs-toggle="pill"
                        data-bs-target="#pane-general" type="button" role="tab">
                        <i class="bi bi-shop me-1"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-receipt" data-bs-toggle="pill"
                        data-bs-target="#pane-receipt" type="button" role="tab">
                        <i class="bi bi-receipt me-1"></i> Receipt & Logo
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-taxes" data-bs-toggle="pill"
                        data-bs-target="#pane-taxes" type="button" role="tab">
                        <i class="bi bi-percent me-1"></i> Taxes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-display" data-bs-toggle="pill"
                        data-bs-target="#pane-display" type="button" role="tab">
                        <i class="bi bi-display me-1"></i> Display
                    </button>
                </li>
            </ul>

            <!-- Added enctype="multipart/form-data" for the file upload -->
            <form action="../../backend/settings/save_settings.php" method="POST" id="settingsForm" enctype="multipart/form-data">

                <div class="tab-content">

                    <!-- ============ GENERAL ============ -->
                    <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white border-0">
                                <h5 class="fw-bold mb-0"><i class="bi bi-shop text-primary me-2"></i>Organization Profile</h5>
                                <small class="text-muted">Shown on receipts, invoices, and the sidebar header.</small>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Organization Name <span class="text-danger">*</span></label>
                                        <input type="text" name="org_name" class="form-control" required maxlength="120"
                                            value="<?= htmlspecialchars($S['org_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Website</label>
                                        <input type="url" name="org_website" class="form-control" maxlength="255"
                                            placeholder="https://…" value="<?= htmlspecialchars($S['org_website'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Phone</label>
                                        <input type="text" name="org_phone" class="form-control" maxlength="40"
                                            value="<?= htmlspecialchars($S['org_phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email</label>
                                        <input type="email" name="org_email" class="form-control" maxlength="120"
                                            value="<?= htmlspecialchars($S['org_email'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Business Address</label>
                                        <textarea name="org_address" class="form-control" rows="2" maxlength="255"><?= htmlspecialchars($S['org_address'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ RECEIPT & LOGO ============ -->
                    <div class="tab-pane fade" id="pane-receipt" role="tabpanel">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white border-0">
                                <h5 class="fw-bold mb-0"><i class="bi bi-receipt text-primary me-2"></i>Receipt &amp; Currency</h5>
                                <small class="text-muted">Applies to printed receipts and sales screens.</small>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    
                                    <!-- Logo Upload Section -->
                                    <div class="col-12 mb-2">
                                        <label class="form-label fw-semibold">Business Logo</label>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($S['org_logo'])): ?>
                                                <img src="../../<?= htmlspecialchars($S['org_logo']) ?>" alt="Logo" class="rounded border p-1 bg-white" style="height: 48px; object-fit: contain;">
                                            <?php else: ?>
                                                <div class="bg-light rounded border d-flex align-items-center justify-content-center text-muted" style="width: 48px; height: 48px;">
                                                    <i class="bi bi-image"></i>
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="org_logo" class="form-control form-control-sm" accept="image/png, image/jpeg, image/webp" style="max-width: 300px;">
                                        </div>
                                        <small class="text-muted">Recommended: PNG with transparent background, max 1MB.</small>
                                    </div>
                                    <hr class="my-2">

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Currency Symbol</label>
                                        <input type="text" name="currency_symbol" class="form-control" maxlength="8"
                                            value="<?= htmlspecialchars($S['currency_symbol'] ?? 'Rs.') ?>">
                                        <small class="text-muted">e.g. Rs., $, €, £</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Currency Position</label>
                                        <select name="currency_position" class="form-select">
                                            <option value="before" <?= ($S['currency_position'] ?? 'before') === 'before' ? 'selected' : '' ?>>
                                                Before (Rs. 100)
                                            </option>
                                            <option value="after" <?= ($S['currency_position'] ?? '') === 'after' ? 'selected' : '' ?>>
                                                After (100 Rs.)
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Show logo on receipt</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="receipt_show_logo" name="receipt_show_logo" value="1"
                                                <?= !empty($S['receipt_show_logo']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="receipt_show_logo">Enabled</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Default Receipt Footer</label>
                                        <textarea name="receipt_footer" class="form-control" rows="2" maxlength="255"
                                            placeholder="Thank you for shopping with us!"><?= htmlspecialchars($S['receipt_footer'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ TAXES ============ -->
                    <div class="tab-pane fade" id="pane-taxes" role="tabpanel">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white border-0">
                                <h5 class="fw-bold mb-0"><i class="bi bi-percent text-primary me-2"></i>Tax Configuration</h5>
                                <small class="text-muted">Optional — enable only if you need tax applied at checkout.</small>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Enable Tax</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="tax_enabled" name="tax_enabled" value="1"
                                                <?= !empty($S['tax_enabled']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="tax_enabled">Tax enabled</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Tax Rate (%)</label>
                                        <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" max="100"
                                            value="<?= htmlspecialchars($S['tax_rate'] ?? '0.00') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Tax Included in Price</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="tax_included" name="tax_included" value="1"
                                                <?= !empty($S['tax_included']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="tax_included">Prices include tax</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ DISPLAY ============ -->
                    <div class="tab-pane fade" id="pane-display" role="tabpanel">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white border-0">
                                <h5 class="fw-bold mb-0"><i class="bi bi-display text-primary me-2"></i>Display Preferences</h5>
                                <small class="text-muted">Controls dates, time, and where the app lands after login.</small>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Low Stock Threshold</label>
                                        <input type="number" name="low_stock_threshold" class="form-control" min="0" max="10000"
                                            value="<?= (int)($S['low_stock_threshold'] ?? 5) ?>">
                                        <small class="text-muted">Products at or below this count show as low-stock</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Default Landing Page</label>
                                        <select name="default_landing" class="form-select">
                                            <?php
                                            $landingOptions = [
                                                'dashboard.php'          => 'Dashboard',
                                                'sale.php'               => 'Terminal Sale',
                                                'sales_history.php'      => 'Sale History',
                                                'products.php'           => 'Products',
                                                'all_customers.php'      => 'All Customers',
                                            ];
                                            $currentLanding = $S['default_landing'] ?? 'dashboard.php';
                                            foreach ($landingOptions as $val => $label):
                                            ?>
                                                <option value="<?= $val ?>" <?= $currentLanding === $val ? 'selected' : '' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Date Format</label>
                                        <select name="date_format" class="form-select">
                                            <?php
                                            $dateOptions = [
                                                'd M Y'   => date('d M Y'),
                                                'd/m/Y'   => date('d/m/Y'),
                                                'm/d/Y'   => date('m/d/Y'),
                                                'Y-m-d'   => date('Y-m-d'),
                                                'D, d M Y' => date('D, d M Y'),
                                            ];
                                            $currentDate = $S['date_format'] ?? 'd M Y';
                                            foreach ($dateOptions as $val => $label):
                                            ?>
                                                <option value="<?= htmlspecialchars($val) ?>" <?= $currentDate === $val ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?> (<?= htmlspecialchars($val) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Time Format</label>
                                        <select name="time_format" class="form-select">
                                            <?php
                                            $timeOptions = [
                                                'H:i'      => date('H:i')      . ' (24-hour)',
                                                'H:i:s'    => date('H:i:s')    . ' (24-hour, seconds)',
                                                'h:i A'    => date('h:i A')    . ' (12-hour AM/PM)',
                                                'h:i:s A'  => date('h:i:s A')  . ' (12-hour AM/PM, seconds)',
                                            ];
                                            $currentTime = $S['time_format'] ?? 'H:i';
                                            foreach ($timeOptions as $val => $label):
                                            ?>
                                                <option value="<?= htmlspecialchars($val) ?>" <?= $currentTime === $val ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============ STICKY SAVE BAR ============ -->
                <div class="settings-savebar mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Changes take effect immediately after save.
                        </small>
                        <div class="d-flex gap-2">
                            <a href="settings.php" class="btn btn-light">Reset Form</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Save All Settings
                            </button>
                        </div>
                    </div>
                </div>

            </form>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Remember active tab across reloads
        document.addEventListener('DOMContentLoaded', () => {
            const saved = sessionStorage.getItem('settings_active_tab');
            if (saved) {
                const btn = document.querySelector(`[data-bs-target="${saved}"]`);
                if (btn) new bootstrap.Tab(btn).show();
            }

            document.querySelectorAll('[data-bs-toggle="pill"]').forEach(btn => {
                btn.addEventListener('shown.bs.tab', e => {
                    sessionStorage.setItem('settings_active_tab', e.target.dataset.bsTarget);
                });
            });
        });
    </script>
</body>

</html>