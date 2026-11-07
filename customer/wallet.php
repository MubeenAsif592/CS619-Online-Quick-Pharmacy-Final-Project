<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Customer');

// Handle Add Funds (Top-Up)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_amount'])) {
    $amount = floatval($_POST['topup_amount']);
    if ($amount > 0) {
        // Ensure wallet row exists
        $chk = $conn->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?");
        $chk->bind_param("idd", $customer_id, $amount, $amount);
        $chk->execute();
        $chk->close();

        // Log transaction
        $t_stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', 'Wallet Top-Up (Online Deposit)')");
        $t_stmt->bind_param("id", $customer_id, $amount);
        $t_stmt->execute();
        $t_stmt->close();

        $_SESSION['success'] = "Rs. " . number_format($amount, 2) . " added to your wallet successfully!";
    }
    header("Location: wallet.php");
    exit();
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Fetch Live Wallet Balance
$w_stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
$w_stmt->bind_param("i", $customer_id);
$w_stmt->execute();
$w_res = $w_stmt->get_result();

if ($w_res && $w_res->num_rows > 0) {
    $wallet_balance = floatval($w_res->fetch_assoc()['balance']);
} else {
    // Default initial balance insertion
    $ins = $conn->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, 1000.00)");
    $ins->bind_param("i", $customer_id);
    $ins->execute();
    $ins->close();
    $wallet_balance = 1000.00;
}
$w_stmt->close();

// Fetch Transactions
$tx_stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC");
$tx_stmt->bind_param("i", $customer_id);
$tx_stmt->execute();
$transactions = $tx_stmt->get_result();
?>

<div class="container my-4" style="max-width: 900px;">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">💳 My Digital Wallet</h2>
            <p class="text-muted mb-0">Manage credits, add funds, and view payments (FR10).</p>
        </div>
        <a href="customer_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card bg-success text-white shadow-sm p-3 h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h6 class="text-uppercase fw-semibold mb-2" style="opacity: 0.85;">Available Balance</h6>
                        <h1 class="display-5 fw-bold mb-0">Rs. <?= number_format($wallet_balance, 2); ?></h1>
                        <small class="text-white-50 d-block mt-2">Live Dynamic Balance</small>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-light btn-sm fw-bold text-success w-100" data-bs-toggle="modal" data-bs-target="#topupModal">+ Add Funds (Top-Up)</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card bg-light shadow-sm p-3 h-100 border">
                <div class="card-body">
                    <h5 class="fw-bold text-dark mb-2">🎁 Dynamic Payment System</h5>
                    <p class="text-muted small">Top-up funds instantly using your card/digital banking simulation and pay seamlessly at checkout.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="mb-0 fw-bold">Recent Wallet Transactions</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Transaction Detail</th>
                            <th>Type</th>
                            <th>Date & Time</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transactions && $transactions->num_rows > 0): ?>
                            <?php $sr = 1; while ($t = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?= $sr++; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($t['description']); ?></td>
                                <td>
                                    <?php if ($t['type'] === 'credit'): ?>
                                        <span class="badge bg-success">Credit</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Debit</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('g:i A, d-m-Y', strtotime($t['created_at'])); ?></td>
                                <td class="text-end fw-bold <?= $t['type'] === 'credit' ? 'text-success' : 'text-danger'; ?>">
                                    <?= $t['type'] === 'credit' ? '+' : '-'; ?> Rs. <?= number_format($t['amount'], 2); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No transactions recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Top Up -->
<div class="modal fade" id="topupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="wallet.php" method="POST">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title fw-bold">💳 Top-Up Wallet Funds</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Enter Amount (PKR)</label>
            <input type="number" name="topup_amount" class="form-control" placeholder="e.g. 500" min="10" step="10" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success fw-bold">Add Funds Now</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>