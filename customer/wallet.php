<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Customer');

// 1. Dynamic Live Wallet Balance Calculation (Credits - Debits)
$bal_stmt = $conn->prepare("SELECT 
    (IFNULL(SUM(CASE WHEN LOWER(type) = 'credit' THEN amount ELSE 0 END), 0) - 
     IFNULL(SUM(CASE WHEN LOWER(type) = 'debit' THEN amount ELSE 0 END), 0)) AS calculated_balance 
    FROM wallet_transactions WHERE user_id = ?");
$bal_stmt->bind_param("i", $customer_id);
$bal_stmt->execute();
$bal_res = $bal_stmt->get_result()->fetch_assoc();
$wallet_balance = floatval($bal_res['calculated_balance'] ?? 0.00);
$bal_stmt->close();

// Sync Calculated Balance to user_wallets table
$sync_stmt = $conn->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = ?");
$sync_stmt->bind_param("idd", $customer_id, $wallet_balance, $wallet_balance);
$sync_stmt->execute();
$sync_stmt->close();

// Handle Top-Up Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_amount'])) {
    $amount = floatval($_POST['topup_amount']);
    if ($amount > 0) {
        $t_stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'credit', 'Wallet Top-Up (Online Deposit)', NOW())");
        $t_stmt->bind_param("id", $customer_id, $amount);
        $t_stmt->execute();
        $t_stmt->close();

        $u_bal = $conn->prepare("UPDATE user_wallets SET balance = balance + ? WHERE user_id = ?");
        $u_bal->bind_param("di", $amount, $customer_id);
        $u_bal->execute();
        $u_bal->close();

        $_SESSION['success'] = "Rs. " . number_format($amount, 2) . " added to your wallet successfully!";
    }
    header("Location: wallet.php");
    exit();
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';

// 2. Fetch ALL Transactions (We will hide older entries via JS/CSS frontend logic)
$tx_stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC");
$tx_stmt->bind_param("i", $customer_id);
$tx_stmt->execute();
$all_transactions = $tx_stmt->get_result();
$total_count = $all_transactions ? $all_transactions->num_rows : 0;
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
            <p class="text-muted mb-0">Manage credits, add funds, and view order payments.</p>
        </div>
        <a href="customer_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card bg-success text-white shadow-sm p-3 h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h6 class="text-uppercase fw-semibold mb-2" style="opacity: 0.85;">AVAILABLE BALANCE</h6>
                        <h1 class="display-5 fw-bold mb-0">Rs. <?= number_format($wallet_balance, 2); ?></h1>
                        <small class="text-white-50 d-block mt-2">Instant Dynamic Balance Synchronized</small>
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
                    <p class="text-muted small">Top-up funds instantly using your card/digital banking simulation and pay seamlessly at checkout without needing cash on delivery.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="card shadow-sm mt-4 border-0">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Recent Wallet Transactions</h5>
            <span class="badge bg-secondary">Total Entries: <?= $total_count; ?></span>
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
                        <?php if ($total_count > 0): ?>
                            <?php $sr = 1; while ($t = $all_transactions->fetch_assoc()): ?>
                            <?php 
                            $t_type = strtolower(trim($t['type']));
                            // Hide rows beyond entry #10 by default
                            $row_class = ($sr > 10) ? 'older-transaction d-none' : '';
                            ?>
                            <tr class="<?= $row_class; ?>">
                                <td><?= $sr++; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($t['description']); ?></td>
                                <td>
                                    <?php if ($t_type === 'credit'): ?>
                                        <span class="badge bg-success">Credit</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Debit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('g:i A, d-m-Y', strtotime($t['created_at'])); ?></td>
                                <td class="text-end fw-bold <?= $t_type === 'credit' ? 'text-success' : 'text-danger'; ?>">
                                    <?= $t_type === 'credit' ? '+ Rs. ' : '- Rs. '; ?>
                                    <?= number_format((float)$t['amount'], 2); ?>
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

            <!-- Toggle Button for Older Entries -->
            <?php if ($total_count > 10): ?>
                <div class="p-3 text-center bg-light border-top">
                    <button type="button" id="toggleTransBtn" class="btn btn-outline-primary btn-sm fw-bold">
                        🔍 View Older Transactions (<?= $total_count - 10; ?> More)
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Top Up -->
<div class="modal fade" id="topupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form action="wallet.php" method="POST">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title fw-bold">💳 Top-Up Wallet Funds</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Enter Amount (PKR)</label>
            <input type="number" name="topup_amount" class="form-control form-control-lg" placeholder="e.g. 500" min="10" step="10" required>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success fw-bold px-4">Add Funds Now</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// JavaScript Toggle logic to expand/hide older records
document.addEventListener("DOMContentLoaded", function() {
    const toggleBtn = document.getElementById('toggleTransBtn');
    if (toggleBtn) {
        let isExpanded = false;
        toggleBtn.addEventListener('click', function() {
            const olderRows = document.querySelectorAll('.older-transaction');
            olderRows.forEach(row => {
                row.classList.toggle('d-none');
            });
            isExpanded = !isExpanded;
            toggleBtn.textContent = isExpanded ? '⬆️ Hide Older Transactions' : '🔍 View Older Transactions (<?= $total_count - 10; ?> More)';
        });
    }
});
</script>

<?php
$tx_stmt->close();
$conn->close();
include_once '../includes/footer.php';
?>