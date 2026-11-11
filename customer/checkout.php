<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

$cart_items = $_SESSION['cart'] ?? [];
if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$wallet_balance = 0.00;

// Safe Dynamic Balance Calculation
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

// Fallback to user_wallets table if calculated balance is 0
if ($wallet_balance <= 0.00) {
    try {
        $w_stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
        if ($w_stmt) {
            $w_stmt->bind_param("i", $customer_id);
            $w_stmt->execute();
            $w_res = $w_stmt->get_result();
            if ($w_res && $w_res->num_rows > 0) {
                $wallet_balance = floatval($w_res->fetch_assoc()['balance']);
            }
            $w_stmt->close();
        }
    } catch (Throwable $e) {
        $wallet_balance = 0.00;
    }
}

$grand_total = 0;
foreach ($cart_items as $item) {
    $grand_total += ($item['price'] * $item['quantity']);
}
?>

<div class="container my-4" style="max-width: 800px;">
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

    <div class="card shadow-sm p-4 border-0">
        <form action="process_checkout.php" method="POST">
            <!-- 1. Delivery Address -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">1. Delivery Information</h5>
            <div class="mb-3">
                <label class="form-label fw-bold">Delivery Address <span class="text-danger">*</span></label>
                <textarea name="delivery_address" class="form-control" rows="3" placeholder="Enter complete home/office address..." required></textarea>
            </div>

            <!-- 2. Payment Method -->
            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4">2. Select Payment Method</h5>
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

            <!-- 3. Summary -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">3. Order Summary</h5>
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