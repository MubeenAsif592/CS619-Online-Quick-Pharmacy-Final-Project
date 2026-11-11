<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['admin']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Sales Report Metrics
$sales_res = $conn->query("SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_amount),0) AS gross_sales FROM orders WHERE LOWER(order_status) = 'delivered'");
$sales = $sales_res ? $sales_res->fetch_assoc() : ['total_orders' => 0, 'gross_sales' => 0];

// Stock Report Metrics (Fixed column names: medicine_name & stock_quantity)
$stock_res = $conn->query("SELECT m.medicine_name, m.stock_quantity, p.pharmacy_name 
                            FROM medicines m 
                            JOIN pharmacies p ON m.pharmacy_id = p.id 
                            WHERE m.stock_quantity <= 5 
                            ORDER BY m.stock_quantity ASC");

// Wallet Transactions Summary
$wallet_res = $conn->query("SELECT wt.*, u.full_name, u.email 
                            FROM wallet_transactions wt 
                            JOIN users u ON wt.user_id = u.id 
                            ORDER BY wt.id DESC LIMIT 10");
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">📊 System Reports & Analytics</h2>
            <p class="text-muted mb-0">Sales performance, inventory stock levels, wallet logs, and delivery metrics.</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <!-- Summary Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card bg-success text-white p-3 shadow-sm border-0">
                <h6 class="text-uppercase fw-bold opacity-75">Gross Revenue (Delivered Orders)</h6>
                <h2 class="display-6 fw-bold mb-0">Rs. <?= number_format((float)$sales['gross_sales'], 2); ?></h2>
                <small><?= $sales['total_orders']; ?> Completed Orders</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-warning text-dark p-3 shadow-sm border-0">
                <h6 class="text-uppercase fw-bold opacity-75">Low Stock Alert Count</h6>
                <h2 class="display-6 fw-bold mb-0"><?= $stock_res ? $stock_res->num_rows : 0; ?> Items</h2>
                <small>Medicines with &le; 5 units in stock</small>
            </div>
        </div>
    </div>

    <!-- Low Stock Inventory Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white fw-bold">⚠️ Low Stock Inventory Report</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Medicine Name</th>
                            <th>Pharmacy Store</th>
                            <th class="text-center">Stock Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stock_res && $stock_res->num_rows > 0): ?>
                            <?php while ($s = $stock_res->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($s['medicine_name']); ?></td>
                                <td><?= htmlspecialchars($s['pharmacy_name']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= $s['stock_quantity']; ?> Units Left</span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted">All inventory stock levels are healthy.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Wallet Transaction Audit -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-secondary text-white fw-bold">💳 Recent Wallet Transactions (FR10/FR14)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Transaction Type</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($wallet_res && $wallet_res->num_rows > 0): ?>
                            <?php while ($w = $wallet_res->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($w['full_name']); ?></td>
                                <td class="fw-bold <?= strtolower($w['type']) === 'credit' ? 'text-success' : 'text-danger'; ?>">
                                    <?= strtolower($w['type']) === 'credit' ? '+' : '-'; ?> Rs. <?= number_format((float)$w['amount'], 2); ?>
                                </td>
                                <td><span class="badge bg-<?= strtolower($w['type']) === 'credit' ? 'success' : 'primary'; ?>"><?= strtoupper($w['type']); ?></span></td>
                                <td class="small"><?= htmlspecialchars($w['description']); ?></td>
                                <td class="small"><?= date('d-m-Y g:i A', strtotime($w['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">No wallet transactions logged.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Overall Customer Ratings & Feedback (FR15) -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white fw-bold">⭐ Overall Platform Ratings & Feedback (FR15)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th>Pharmacy Store</th>
                            <th>Rating</th>
                            <th>Feedback / Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $all_revs = $conn->query("SELECT r.*, u.full_name, p.pharmacy_name 
                                                 FROM reviews r 
                                                 JOIN users u ON r.customer_id = u.id 
                                                 JOIN pharmacies p ON r.pharmacy_id = p.id 
                                                 ORDER BY r.id DESC LIMIT 10");
                        if ($all_revs && $all_revs->num_rows > 0): 
                            while ($r = $all_revs->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($r['full_name']); ?></td>
                            <td><?= htmlspecialchars($r['pharmacy_name']); ?></td>
                            <td class="text-warning fw-bold"><?= str_repeat('⭐', $r['rating']); ?> (<?= $r['rating']; ?>/5)</td>
                            <td class="small"><?= htmlspecialchars($r['feedback']); ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="text-center py-3 text-muted">No customer ratings or feedback records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>