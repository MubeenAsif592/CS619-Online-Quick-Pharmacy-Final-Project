<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['admin']);

// 1. Handle Create Promo Coupon Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_coupon'])) {
    $code = strtoupper(trim($_POST['promo_code']));
    $discount = floatval($_POST['discount_percentage']);

    if (!empty($code) && $discount > 0) {
        $cp_stmt = $conn->prepare("INSERT INTO campaigns (promo_code, discount_percentage, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE discount_percentage = ?, is_active = 1");
        $cp_stmt->bind_param("sdd", $code, $discount, $discount);
        $cp_stmt->execute();
        $cp_stmt->close();
        $_SESSION['success'] = "Promo Coupon Code '" . htmlspecialchars($code) . "' (" . $discount . "% OFF) created successfully!";
    }
    header("Location: promotions.php");
    exit();
}

// 2. Handle Sending Wallet Promo Credits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promo_amount'], $_POST['customer_target'])) {
    $amount = floatval($_POST['promo_amount']);
    $target = $_POST['customer_target'];
    $promo_title = trim($_POST['promo_title'] ?? 'Promotional Credit Bonus');

    if ($amount > 0) {
        $target_ids = [];
        if ($target === 'all') {
            $c_res = $conn->query("SELECT id FROM users WHERE role = 'customer'");
            while ($c_row = $c_res->fetch_assoc()) {
                $target_ids[] = $c_row['id'];
            }
        } else {
            $target_ids[] = intval($target);
        }

        foreach ($target_ids as $c_id) {
            $upd = $conn->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?");
            $upd->bind_param("idd", $c_id, $amount, $amount);
            $upd->execute();
            $upd->close();

            $t_stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
            $desc = "Promo Reward: " . $promo_title;
            $t_stmt->bind_param("ids", $c_id, $amount, $desc);
            $t_stmt->execute();
            $t_stmt->close();
        }

        $_SESSION['success'] = "Successfully distributed Rs. " . number_format($amount, 2) . " promo balance to " . count($target_ids) . " customer(s)!";
    }
    header("Location: promotions.php");
    exit();
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';

$customers = $conn->query("SELECT id, full_name, email FROM users WHERE role = 'customer' ORDER BY full_name ASC");
$campaigns = $conn->query("SELECT * FROM campaigns ORDER BY id DESC");
?>

<div class="container my-4" style="max-width: 950px;">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">🎁 Marketing & Promotions</h2>
            <p class="text-muted mb-0">Create promo codes or issue credit bonuses directly into customer digital wallets.</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="row g-4">
        <!-- Coupon Code Creation Form -->
        <div class="col-md-5">
            <div class="card shadow-sm border-0 p-4 h-100">
                <h5 class="fw-bold border-bottom pb-2 mb-3">🏷️ Create Promo Code</h5>
                <form action="promotions.php" method="POST">
                    <input type="hidden" name="create_coupon" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Promo Coupon Code</label>
                        <input type="text" name="promo_code" class="form-control text-uppercase" placeholder="e.g. QUICK10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Discount Percentage (%)</label>
                        <input type="number" name="discount_percentage" class="form-control" placeholder="e.g. 10" min="1" max="50" step="0.5" required>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold w-100">Save Coupon Code</button>
                </form>
            </div>
        </div>

        <!-- Issue Wallet Bonus Form -->
        <div class="col-md-7">
            <div class="card shadow-sm border-0 p-4 h-100">
                <h5 class="fw-bold border-bottom pb-2 mb-3">💳 Issue Wallet Credit Bonus</h5>
                <form action="promotions.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Recipient(s)</label>
                        <select name="customer_target" class="form-select" required>
                            <option value="">-- Choose Target --</option>
                            <option value="all" class="fw-bold text-success">🌟 ALL REGISTERED CUSTOMERS (BULK)</option>
                            <option disabled>------------------------------------</option>
                            <?php if ($customers && $customers->num_rows > 0): ?>
                                <?php while ($c = $customers->fetch_assoc()): ?>
                                    <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['full_name']); ?> (<?= htmlspecialchars($c['email']); ?>)</option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Campaign Description</label>
                            <input type="text" name="promo_title" class="form-control" placeholder="e.g. Weekend Discount" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Amount (PKR)</label>
                            <input type="number" name="promo_amount" class="form-control" placeholder="250" min="10" step="10" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success fw-bold w-100">Distribute Credit Bonus</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Active Promo Codes Table -->
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-dark text-white fw-bold py-3">Active Promotional Coupons</div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Coupon Code</th>
                        <th>Discount (%)</th>
                        <th>Status</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($campaigns && $campaigns->num_rows > 0): ?>
                        <?php $c_sr = 1; while ($cp = $campaigns->fetch_assoc()): ?>
                        <tr>
                            <td><?= $c_sr++; ?></td>
                            <td><strong class="badge bg-primary fs-6"><?= htmlspecialchars($cp['promo_code']); ?></strong></td>
                            <td class="fw-bold text-success"><?= number_format($cp['discount_percentage'], 1); ?>% OFF</td>
                            <td><span class="badge bg-success">Active</span></td>
                            <td class="small text-muted"><?= date('d-m-Y', strtotime($cp['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No active coupons created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>