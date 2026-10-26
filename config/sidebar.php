<?php
// Ensure session is started to access user ID
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/** @var mysqli $conn */
require_once __DIR__ . "/conn.php";

// Load settings helper (if present) to pull org_name from DB
$settingsHelper = __DIR__ . "/settings_helper.php";
if (is_file($settingsHelper)) {
  require_once $settingsHelper;
}

// Determine current script name for active states
$currentPage = basename($_SERVER['PHP_SELF']);

// Fallbacks for session variables
$username     = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Guest';
$organization = 'POS System';

if (isset($_SESSION['user_id'])) {
  $stmt = $conn->prepare("SELECT username, organization FROM users WHERE id = ?");
  if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
      $username     = $user['username'];
      $organization = $user['organization'] ?? 'POS System';
    }
    $stmt->close();
  }
}

// Prefer org_name from settings if available
if (function_exists('load_settings')) {
  $__settings = load_settings($conn);
  if (!empty($__settings['org_name'])) {
    $organization = $__settings['org_name'];
  }
}

// Active state helper
function isActive($pageName, $currentPage)
{
  return $currentPage === $pageName ? 'active bg-primary text-white' : 'text-white-50';
}

// ---- Page groups (for collapse-open state) ----
$posPages       = ['sale.php', 'sales_history.php'];
$inventoryPages = ['category.php', 'products.php', 'category_discount.php', 'purchases.php'];
$customerPages  = ['all_customers.php', 'customer.php', 'customer_history.php', 'customer_discount.php', 'customer_payments.php'];
$financePages   = ['expenses.php', 'profit_loss.php'];
$ecomPages      = ['orders.php', 'ecom_products.php'];

$isPosActive       = in_array($currentPage, $posPages, true);
$isInventoryActive = in_array($currentPage, $inventoryPages, true);
$isCustomerActive  = in_array($currentPage, $customerPages, true);
$isFinanceActive   = in_array($currentPage, $financePages, true);
$isEcomActive      = in_array($currentPage, $ecomPages, true);
?>
<aside class="sidebar-wrapper bg-dark text-white d-flex flex-column flex-shrink-0 p-3 min-vh-100" style="width: 260px;">

  <!-- Brand / Logo -->
  <a href="dashboard.php" class="d-flex align-items-center mb-3 text-white text-decoration-none px-2">
    <i class="bi bi-box-seam-fill fs-4 me-2 text-primary"></i>
    <span class="fs-5 fw-bold text-truncate"><?php echo htmlspecialchars($organization); ?></span>
  </a>

  <hr class="text-secondary mt-0">

  <!-- Navigation Links -->
  <ul class="nav nav-pills flex-column mb-auto" id="sidebar-menu">

    <!-- Dashboard -->
    <li class="nav-item">
      <a href="dashboard.php" class="nav-link d-flex align-items-center mb-1 <?php echo $currentPage === 'dashboard.php' ? 'active bg-primary text-white' : 'text-white'; ?>">
        <i class="bi bi-grid-1x2-fill me-2"></i>
        <span>Dashboard</span>
      </a>
    </li>

    <!-- Point of Sale -->
    <li class="nav-item">
      <a href="#posCollapse"
        class="nav-link text-white d-flex align-items-center justify-content-between mb-1 <?php echo $isPosActive ? 'fw-bold' : ''; ?>"
        data-bs-toggle="collapse"
        role="button"
        aria-expanded="<?php echo $isPosActive ? 'true' : 'false'; ?>"
        aria-controls="posCollapse">
        <span>
          <i class="bi bi-cart-check me-2 text-info"></i>
          Point of Sale
        </span>
        <i class="bi bi-chevron-down arrow-icon ms-auto small"></i>
      </a>
      <div class="collapse <?php echo $isPosActive ? 'show' : ''; ?>" id="posCollapse" data-bs-parent="#sidebar-menu">
        <ul class="nav flex-column ps-3 mb-2 sub-menu">
          <li class="nav-item">
            <a href="sale.php" class="nav-link py-1 px-2 rounded <?php echo isActive('sale.php', $currentPage); ?>">
              <i class="bi bi-cart me-2"></i>Terminal Sale
            </a>
          </li>
          <li class="nav-item">
            <a href="sales_history.php" class="nav-link py-1 px-2 rounded <?php echo isActive('sales_history.php', $currentPage); ?>">
              <i class="bi bi-clock-history me-2"></i>Sale History
            </a>
          </li>
        </ul>
      </div>
    </li>

    <!-- Inventory -->
    <li class="nav-item">
      <a href="#inventoryCollapse"
        class="nav-link text-white d-flex align-items-center justify-content-between mb-1 <?php echo $isInventoryActive ? 'fw-bold' : ''; ?>"
        data-bs-toggle="collapse"
        role="button"
        aria-expanded="<?php echo $isInventoryActive ? 'true' : 'false'; ?>"
        aria-controls="inventoryCollapse">
        <span>
          <i class="bi bi-boxes me-2 text-primary"></i>
          Inventory
        </span>
        <i class="bi bi-chevron-down arrow-icon ms-auto small"></i>
      </a>
      <div class="collapse <?php echo $isInventoryActive ? 'show' : ''; ?>" id="inventoryCollapse" data-bs-parent="#sidebar-menu">
        <ul class="nav flex-column ps-3 mb-2 sub-menu">
          <li class="nav-item">
            <a href="products.php" class="nav-link py-1 px-2 rounded <?php echo isActive('products.php', $currentPage); ?>">
              <i class="bi bi-box me-2"></i>Products
            </a>
          </li>
          <li class="nav-item">
            <a href="category.php" class="nav-link py-1 px-2 rounded <?php echo isActive('category.php', $currentPage); ?>">
              <i class="bi bi-tags me-2"></i>Categories
            </a>
          </li>
          <li class="nav-item">
            <a href="purchases.php" class="nav-link py-1 px-2 rounded <?php echo isActive('purchases.php', $currentPage); ?>">
              <i class="bi bi-box-arrow-in-down me-2"></i>Purchases (Stock In)
            </a>
          </li>
          <li class="nav-item">
            <a href="category_discount.php" class="nav-link py-1 px-2 rounded <?php echo isActive('category_discount.php', $currentPage); ?>">
              <i class="bi bi-tags-fill me-2"></i>Category Discounts
            </a>
          </li>
        </ul>
      </div>
    </li>

    <!-- Customers -->
    <li class="nav-item">
      <a href="#customerCollapse"
        class="nav-link text-white d-flex align-items-center justify-content-between mb-1 <?php echo $isCustomerActive ? 'fw-bold' : ''; ?>"
        data-bs-toggle="collapse"
        role="button"
        aria-expanded="<?php echo $isCustomerActive ? 'true' : 'false'; ?>"
        aria-controls="customerCollapse">
        <span>
          <i class="bi bi-people me-2 text-success"></i>
          Customers
        </span>
        <i class="bi bi-chevron-down arrow-icon ms-auto small"></i>
      </a>
      <div class="collapse <?php echo $isCustomerActive ? 'show' : ''; ?>" id="customerCollapse" data-bs-parent="#sidebar-menu">
        <ul class="nav flex-column ps-3 mb-2 sub-menu">
          <li class="nav-item">
            <a href="all_customers.php" class="nav-link py-1 px-2 rounded <?php echo isActive('all_customers.php', $currentPage); ?>">
              <i class="bi bi-person-vcard me-2"></i>All Customers
            </a>
          </li>
          <li class="nav-item">
            <a href="customer.php" class="nav-link py-1 px-2 rounded <?php echo isActive('customer.php', $currentPage); ?>">
              <i class="bi bi-pencil-square me-2"></i>Add / Edit
            </a>
          </li>
          <li class="nav-item">
            <a href="customer_history.php" class="nav-link py-1 px-2 rounded <?php echo isActive('customer_history.php', $currentPage); ?>">
              <i class="bi bi-clock-history me-2"></i>Ledger / History
            </a>
          </li>
          <li class="nav-item">
            <a href="customer_payments.php" class="nav-link py-1 px-2 rounded <?php echo isActive('customer_payments.php', $currentPage); ?>">
              <i class="bi bi-cash-coin me-2"></i>Udhar / Payments
            </a>
          </li>
          <li class="nav-item">
            <a href="customer_discount.php" class="nav-link py-1 px-2 rounded <?php echo isActive('customer_discount.php', $currentPage); ?>">
              <i class="bi bi-percent me-2"></i>Customer Discounts
            </a>
          </li>
        </ul>
      </div>
    </li>

    <!-- Finance -->
    <li class="nav-item">
      <a href="#financeCollapse"
        class="nav-link text-white d-flex align-items-center justify-content-between mb-1 <?php echo $isFinanceActive ? 'fw-bold' : ''; ?>"
        data-bs-toggle="collapse"
        role="button"
        aria-expanded="<?php echo $isFinanceActive ? 'true' : 'false'; ?>"
        aria-controls="financeCollapse">
        <span>
          <i class="bi bi-wallet2 me-2 text-danger"></i>
          Finance
        </span>
        <i class="bi bi-chevron-down arrow-icon ms-auto small"></i>
      </a>
      <div class="collapse <?php echo $isFinanceActive ? 'show' : ''; ?>" id="financeCollapse" data-bs-parent="#sidebar-menu">
        <ul class="nav flex-column ps-3 mb-2 sub-menu">
          <li class="nav-item">
            <a href="expenses.php" class="nav-link py-1 px-2 rounded <?php echo isActive('expenses.php', $currentPage); ?>">
              <i class="bi bi-receipt me-2"></i>Expenses
            </a>
          </li>
          <li class="nav-item">
            <a href="profit_loss.php" class="nav-link py-1 px-2 rounded <?php echo isActive('profit_loss.php', $currentPage); ?>">
              <i class="bi bi-graph-up-arrow me-2"></i>Profit &amp; Loss
            </a>
          </li>
        </ul>
      </div>
    </li>

    <!-- E-Commerce -->
    <li class="nav-item">
      <a href="#ecommerceCollapse"
        class="nav-link text-white d-flex align-items-center justify-content-between mb-1 <?php echo $isEcomActive ? 'fw-bold' : ''; ?>"
        data-bs-toggle="collapse"
        role="button"
        aria-expanded="<?php echo $isEcomActive ? 'true' : 'false'; ?>"
        aria-controls="ecommerceCollapse">
        <span>
          <i class="bi bi-bag-check me-2 text-warning"></i>
          E-Commerce
        </span>
        <i class="bi bi-chevron-down arrow-icon ms-auto small"></i>
      </a>
      <div class="collapse <?php echo $isEcomActive ? 'show' : ''; ?>" id="ecommerceCollapse" data-bs-parent="#sidebar-menu">
        <ul class="nav flex-column ps-3 mb-2 sub-menu">
          <li class="nav-item">
            <a href="orders.php" class="nav-link py-1 px-2 rounded <?php echo isActive('orders.php', $currentPage); ?>">
              <i class="bi bi-truck me-2"></i>Online Orders
            </a>
          </li>
          <li class="nav-item">
            <a href="ecom_products.php" class="nav-link py-1 px-2 rounded <?php echo isActive('ecom_products.php', $currentPage); ?>">
              <i class="bi bi-shop me-2"></i>E-Com Products
            </a>
          </li>
        </ul>
      </div>
    </li>

    <!-- System -->
    <li class="nav-item mt-3">
      <hr class="text-secondary">
    </li>
    
    <li class="nav-item">
      <a href="employees.php" class="nav-link d-flex align-items-center mb-1 <?php echo isActive('employees.php', $currentPage); ?>">
        <i class="bi bi-shield-lock me-2 text-secondary"></i>
        <span>Manage Users</span>
      </a>
    </li>

    <li class="nav-item">
      <a href="settings.php" class="nav-link d-flex align-items-center mb-1 <?php echo isActive('settings.php', $currentPage); ?>">
        <i class="bi bi-gear me-2 text-secondary"></i>
        <span>Settings</span>
      </a>
    </li>

  </ul>

  <hr class="text-secondary">

  <!-- Footer User Dropdown -->
  <div class="dropdown">
    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle p-1 rounded" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="bi bi-person-circle fs-4 me-2 text-primary"></i>
      <div class="d-flex flex-column text-truncate">
        <strong class="lh-1"><?php echo htmlspecialchars($username); ?></strong>
        <small class="text-white-50" style="font-size: 0.75rem;">Connected</small>
      </div>
    </a>
    <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser">
      <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
      <li><a class="dropdown-item" href="settings.php"><i class="bi bi-sliders me-2"></i>Account Settings</a></li>
      <li>
        <hr class="dropdown-divider">
      </li>
      <li><a class="dropdown-item text-danger" href="../../backend/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
    </ul>
  </div>

</aside>