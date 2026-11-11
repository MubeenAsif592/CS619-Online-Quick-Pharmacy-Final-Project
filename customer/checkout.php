<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

$cart_items = $_SESSION['cart'] ?? [];
if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$wallet_balance = 0.00;

// Dynamic Safe Wallet Balance Calculation
try {
    $bal_stmt = $conn->prepare("SELECT 
        (IFNULL(SUM(CASE WHEN LOWER(type) = 'credit' THEN amount ELSE 0 END), 0) - 
         IFNULL(SUM(CASE WHEN LOWER(type) = 'debit' THEN amount ELSE 0 END), 0)) AS calculated_balance 
        FROM wallet_transactions WHERE user_id = ?");
    if ($bal_stmt) {
        $bal_stmt->bind_param("i", $customer_id);
        $bal_stmt->execute();
        $bal_res = $bal_stmt->get_result()->fetch_assoc();
        $wallet_balance = floatval($bal_res['calculated_balance'] ?? 0.00);
        $bal_stmt->close();
    }
} catch (Throwable $e) {
    $wallet_balance = 0.00;
}

// Calculate Grand Total
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}

// Handle Promo Code Apply Action
$applied_discount = 0.00;
$discount_percent = 0;
$promo_code_input = '';

if (isset($_POST['apply_promo']) && !empty($_POST['promo_code'])) {
    $promo_code_input = strtoupper(trim($_POST['promo_code']));
    $cp_chk = $conn->prepare("SELECT discount_percentage FROM campaigns WHERE promo_code = ? AND is_active = 1");
    if ($cp_chk) {
        $cp_chk->bind_param("s", $promo_code_input);
        $cp_chk->execute();
        $cp_res = $cp_chk->get_result();
        if ($cp_res && $cp_res->num_rows > 0) {
            $discount_percent = floatval($cp_res->fetch_assoc()['discount_percentage']);
            $applied_discount = ($subtotal * $discount_percent) / 100;
            $_SESSION['applied_promo'] = [
                'code' => $promo_code_input,
                'discount' => $applied_discount,
                'percent' => $discount_percent
            ];
            $_SESSION['success'] = "Promo Code '" . $promo_code_input . "' applied! You saved " . $discount_percent . "% (Rs. " . number_format($applied_discount, 2) . ").";
        } else {
            unset($_SESSION['applied_promo']);
            $_SESSION['error'] = "Invalid or expired promo code.";
        }
        $cp_chk->close();
    }
}

if (isset($_SESSION['applied_promo'])) {
    $promo_code_input = $_SESSION['applied_promo']['code'];
    $applied_discount = $_SESSION['applied_promo']['discount'];
    $discount_percent = $_SESSION['applied_promo']['percent'];
}

$grand_total = max(0, $subtotal - $applied_discount);

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="container my-4" style="max-width: 800px;">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold">📦 Order Checkout</h2>
        <a href="cart.php" class="btn btn-outline-secondary fw-bold">Back to Cart</a>
    </div>

    <div class="card shadow-sm p-4 border-0 mb-4">
        <!-- 1. Promo Code Section -->
        <h5 class="fw-bold mb-3 border-bottom pb-2">1. Have a Promo Code? (FR9)</h5>
        <form action="checkout.php" method="POST" class="row g-2 mb-4">
            <input type="hidden" name="apply_promo" value="1">
            <div class="col-8 col-md-9">
                <input type="text" name="promo_code" class="form-control text-uppercase" placeholder="Enter Promo Code (e.g. QUICK10)" value="<?= htmlspecialchars($promo_code_input); ?>" required>
            </div>
            <div class="col-4 col-md-3">
                <button type="submit" class="btn btn-outline-primary fw-bold w-100">Apply</button>
            </div>
        </form>

        <form action="process_checkout.php" method="POST">
            <!-- 2. Delivery Address -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">2. Delivery Information</h5>
            <div class="mb-3">
                <label class="form-label fw-bold">Delivery Address <span class="text-danger">*</span></label>
                <textarea name="delivery_address" class="form-control" rows="3" placeholder="Enter complete home/office address..." required></textarea>
            </div>

            <!-- 3. Payment Method -->
            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4">3. Select Payment Method</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="form-check card p-3 h-100 border shadow-sm">
                        <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" checked>
                        <label class="form-check-label fw-bold d-block" for="cod">
                            💵 Cash on Delivery (COD)
                            <small class="text-muted d-block fw-normal">Pay cash when rider arrives.</small>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check card p-3 h-100 border shadow-sm">
                        <input class="form-check-input" type="radio" name="payment_method" id="wallet" value="wallet" <?= $wallet_balance < $grand_total ? 'disabled' : ''; ?>>
                        <label class="form-check-label fw-bold d-block" for="wallet">
                            💳 Pay via Digital Wallet
                            <small class="<?= $wallet_balance >= $grand_total ? 'text-success' : 'text-danger'; ?> d-block fw-bold">
                                Available Balance: Rs. <?= number_format($wallet_balance, 2); ?>
                                <?php if ($wallet_balance < $grand_total): ?>
                                    <span class="d-block text-danger font-normal small">(Insufficient Funds)</span>
                                <?php endif; ?>
                            </small>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 4. Summary -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">4. Order Summary</h5>
            <ul class="list-group mb-3">
                <?php foreach ($cart_items as $item): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="d-block"><?= htmlspecialchars($item['name']); ?></strong>
                            <small class="text-muted">Qty: <?= $item['quantity']; ?> | Pharmacy: <?= htmlspecialchars($item['pharmacy_name'] ?? 'Pharmacy Store'); ?></small>
                        </div>
                        <span class="fw-bold text-success">Rs. <?= number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </li>
                <?php endforeach; ?>
                
                <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                    <span>Subtotal</span>
                    <span>Rs. <?= number_format($subtotal, 2); ?></span>
                </li>

                <?php if ($applied_discount > 0): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-light text-success fw-bold">
                        <span>Promo Discount (<?= $discount_percent; ?>%)</span>
                        <span>- Rs. <?= number_format($applied_discount, 2); ?></span>
                    </li>
                <?php endif; ?>

                <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                    <span class="fw-bold fs-5">Total Amount Payable</span>
                    <strong class="text-success fs-5">Rs. <?= number_format($grand_total, 2); ?></strong>
                </li>
            </ul>

            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold mt-3">Confirm & Place Order</button>
        </form>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>