<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['admin']);

// Handle Sending Promo Credits
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
?>

<div class="container my-4" style="max-width: 900px;">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">🎁 Marketing & Promotions (FR9)</h2>
            <p class="text-muted mb-0">Issue promotional discount credits directly into customer digital wallets.</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="card shadow-sm border-0 p-4 mb-4">
        <h5 class="fw-bold border-bottom pb-2 mb-3">Issue Promotional Credit Bonus</h5>
        <form action="promotions.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
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

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Campaign Title / Description</label>
                        <input type="text" name="promo_title" class="form-control" placeholder="e.g. Festival Special Discount" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Promo Credit Amount (PKR)</label>
                        <input type="number" name="promo_amount" class="form-control" placeholder="e.g. 250" min="10" step="10" required>
                    </div>
                </div>

                <div class="col-md-6 d-flex align-items-end mb-3">
                    <button type="submit" class="btn btn-success fw-bold w-100">Distribute Credit Bonus</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>