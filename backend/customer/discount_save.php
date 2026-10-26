<?php
session_start();
require_once dirname(__DIR__, 2) . "/config/conn.php";
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../frontend/admin/customer_discount.php");
    exit();
}

$back   = "../../frontend/admin/customer_discount.php";
$action = $_POST['action'] ?? 'save';

try {

    /* =========================================================
       DELETE
       ========================================================= */
    if ($action === 'delete') {
        $id = isset($_POST['discount_id']) ? (int)$_POST['discount_id'] : 0;
        if ($id <= 0) {
            $_SESSION['error'] = "Invalid discount ID.";
            header("Location: $back"); exit();
        }

        $stmt = $conn->prepare("DELETE FROM customer_discounts WHERE discount_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Discount rule deleted.";
        header("Location: $back"); exit();
    }

    /* =========================================================
       SAVE (create or update)
       ========================================================= */
    $discountId = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;
    $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $type       = $_POST['discount_type'] ?? 'percentage';
    $value      = isset($_POST['discount_value']) ? (float)$_POST['discount_value'] : 0;
    $minPurch   = isset($_POST['min_purchase']) && $_POST['min_purchase'] !== ''
                    ? (float)$_POST['min_purchase'] : 0.00;
    $maxDisc    = isset($_POST['max_discount']) && $_POST['max_discount'] !== ''
                    ? (float)$_POST['max_discount'] : null;
    $validFrom  = !empty($_POST['valid_from'])  ? $_POST['valid_from']  : null;
    $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $status     = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $status     = $status === 0 ? 0 : 1;
    $notes      = trim($_POST['notes'] ?? '');
    $notes      = $notes !== '' ? $notes : null;
    $createdBy  = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    /* -------- Validation -------- */
    if ($customerId <= 0) {
        $_SESSION['error'] = "Please select a customer.";
        header("Location: $back"); exit();
    }
    if (!in_array($type, ['percentage', 'fixed'], true)) {
        $_SESSION['error'] = "Invalid discount type.";
        header("Location: $back"); exit();
    }
    if ($value <= 0) {
        $_SESSION['error'] = "Discount value must be greater than 0.";
        header("Location: $back"); exit();
    }
    if ($type === 'percentage' && $value > 100) {
        $_SESSION['error'] = "Percentage discount cannot exceed 100%.";
        header("Location: $back"); exit();
    }
    if ($type === 'fixed' && $value > 1000000) {
        $_SESSION['error'] = "Fixed discount value is unreasonably large.";
        header("Location: $back"); exit();
    }
    if ($minPurch < 0) {
        $_SESSION['error'] = "Minimum purchase cannot be negative.";
        header("Location: $back"); exit();
    }
    if ($maxDisc !== null && $maxDisc < 0) {
        $_SESSION['error'] = "Max discount cannot be negative.";
        header("Location: $back"); exit();
    }
    if ($validFrom && $validUntil && strtotime($validFrom) > strtotime($validUntil)) {
        $_SESSION['error'] = "Valid From cannot be after Valid Until.";
        header("Location: $back"); exit();
    }
    if ($validFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) {
        $_SESSION['error'] = "Invalid 'Valid From' date format.";
        header("Location: $back"); exit();
    }
    if ($validUntil && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) {
        $_SESSION['error'] = "Invalid 'Valid Until' date format.";
        header("Location: $back"); exit();
    }

    /* -------- Verify customer exists -------- */
    $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $customerExists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$customerExists) {
        $_SESSION['error'] = "Selected customer does not exist.";
        header("Location: $back"); exit();
    }

    /* -------- Enforce one ACTIVE discount per customer -------- */
    // Only check if the rule we're saving is going to be active.
    if ($status === 1) {
        $checkSql = "SELECT discount_id FROM customer_discounts
                     WHERE customer_id = ? AND status = 1";
        if ($discountId) {
            $checkSql .= " AND discount_id <> ?";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param("ii", $customerId, $discountId);
        } else {
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param("i", $customerId);
        }
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $_SESSION['error'] = "This customer already has an active discount. Deactivate it first or edit the existing rule.";
            header("Location: $back"); exit();
        }
    }

    /* -------- UPDATE -------- */
    if ($discountId) {
        $sql = "UPDATE customer_discounts SET
                    customer_id    = ?,
                    discount_type  = ?,
                    discount_value = ?,
                    min_purchase   = ?,
                    max_discount   = ?,
                    valid_from     = ?,
                    valid_until    = ?,
                    status         = ?,
                    notes          = ?
                WHERE discount_id = ?";
        $stmt = $conn->prepare($sql);

        // 10 params: i s d d d s s i s i
        $stmt->bind_param(
            "isddsissii",
            $customerId, $type, $value, $minPurch, $maxDisc,
            $validFrom, $validUntil, $status, $notes, $discountId
        );

        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $stmt->close();
                $_SESSION['error'] = "A duplicate discount already exists for this customer.";
                header("Location: $back"); exit();
            }
            throw $e;
        }
        $stmt->close();

        $_SESSION['success'] = "Discount rule updated.";
        header("Location: $back"); exit();
    }

    /* -------- INSERT -------- */
    $sql = "INSERT INTO customer_discounts
                (customer_id, discount_type, discount_value,
                 min_purchase, max_discount, valid_from, valid_until,
                 status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // 10 params: i s d d d s s i s i
    $stmt->bind_param(
        "isddsissii",
        $customerId, $type, $value, $minPurch, $maxDisc,
        $validFrom, $validUntil, $status, $notes, $createdBy
    );

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            $stmt->close();
            $_SESSION['error'] = "A duplicate discount already exists for this customer.";
            header("Location: $back"); exit();
        }
        throw $e;
    }
    $stmt->close();

    $_SESSION['success'] = "Discount rule created.";
    header("Location: $back"); exit();

} catch (Throwable $e) {
    error_log("Discount Save Error: " . $e->getMessage());
    $_SESSION['error'] = "An unexpected error occurred while saving the discount.";
    header("Location: $back");
    exit();
}