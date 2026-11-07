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

// Fetch Actual Wallet Balance from Database
$w_stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
if ($w_stmt) {
    $w_stmt->bind_param("i", $customer_id);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result();
    if ($w_res && $w_res->num_rows > 0) {
        $wallet_balance = floatval($w_res->fetch_assoc()['balance']);
    } else {
        // Auto-initialize with 1000.00 if user has no wallet row
        $ins = $conn->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, 1000.00)");
        $ins->bind_param("i", $customer_id);
        $ins->execute();
        $ins->close();
        $wallet_balance = 1000.00;
    }
    $w_stmt->close();
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

    <div class="card shadow-sm p-4">
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
                    <div class="form-check card p-3 h-100 border">
                        <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" checked>
                        <label class="form-check-label fw-bold d-block" for="cod">
                            💵 Cash on Delivery (COD)
                            <small class="text-muted d-block fw-normal">Pay cash when rider arrives.</small>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check card p-3 h-100 border">
                        <input class="form-check-input" type="radio" name="payment_method" id="wallet" value="wallet">
                        <label class="form-check-label fw-bold d-block" for="wallet">
                            💳 Pay via Digital Wallet
                            <small class="text-success d-block fw-bold">Available Balance: Rs. <?= number_format($wallet_balance, 2); ?></small>
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
                            <small class="text-muted">Qty: <?= $item['quantity']; ?> | Pharmacy: <?= htmlspecialchars($item['pharmacy_name']); ?></small>
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