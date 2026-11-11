<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['pharmacy']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

$user_id = $_SESSION['user_id'];

// Get Pharmacy ID
$p_stmt = $conn->prepare("SELECT id FROM pharmacies WHERE user_id = ?");
$p_stmt->bind_param("i", $user_id);
$p_stmt->execute();
$pharmacy_res = $p_stmt->get_result()->fetch_assoc();
$pharmacy_id = $pharmacy_res['id'] ?? 0;
$p_stmt->close();

// Handle Dispatch Action by Pharmacy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $order_id = intval($_POST['order_id']);
    
    if ($_POST['action'] === 'dispatch') {
        $u_stmt = $conn->prepare("UPDATE orders SET order_status = 'dispatched' WHERE id = ? AND pharmacy_id = ?");
        $u_stmt->bind_param("ii", $order_id, $pharmacy_id);
        if ($u_stmt->execute()) {
            $_SESSION['success'] = "Order #ORD-" . $order_id . " marked as Dispatched!";
        }
        $u_stmt->close();
    }
    header("Location: orders.php");
    exit();
}

// Fetch Incoming Orders with Assigned Rider Info
$orders_query = "SELECT o.*, u.full_name AS customer_name, r.full_name AS rider_name, r.phone AS rider_phone 
                FROM orders o 
                JOIN users u ON o.customer_id = u.id 
                LEFT JOIN users r ON o.rider_id = r.id 
                WHERE o.pharmacy_id = ? 
                ORDER BY o.id DESC";

$o_stmt = $conn->prepare($orders_query);
$o_stmt->bind_param("i", $pharmacy_id);
$o_stmt->execute();
$orders_result = $o_stmt->get_result();
?>

<div class="container my-5">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Customer Incoming Orders</h2>
            <p class="text-muted mb-0">Prepare incoming orders and dispatch them for rider pickup.</p>
        </div>
        <a href="pharmacy_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Customer Name</th>
                            <th>Total Amount</th>
                            <th>Delivery Address</th>
                            <th>Order Date</th>
                            <th>Current Status</th>
                            <th>Assigned Rider</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while ($ord = $orders_result->fetch_assoc()): ?>
                            <?php $st = strtolower(trim((string)$ord['order_status'])); ?>
                            <tr>
                                <td class="fw-bold">#ORD-<?= $ord['id']; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($ord['customer_name']); ?></td>
                                <td class="fw-bold text-success">Rs. <?= number_format((float)$ord['total_amount'], 2); ?></td>
                                <td class="small"><?= htmlspecialchars($ord['delivery_address']); ?></td>
                                <td class="small text-muted"><?= date('g:i A, d-m-Y', strtotime($ord['created_at'])); ?></td>
                                <td>
                                    <?php 
                                    if ($st === 'pending') {
                                        echo '<span class="badge bg-warning text-dark">Pending Packing</span>';
                                    } elseif ($st === 'dispatched') {
                                        echo '<span class="badge bg-info text-dark">Dispatched (Awaiting Rider)</span>';
                                    } elseif ($st === 'out for delivery') {
                                        echo '<span class="badge bg-primary">🛵 Out for Delivery</span>';
                                    } elseif ($st === 'delivered') {
                                        echo '<span class="badge bg-success">✅ Delivered</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($ord['order_status'])) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($ord['rider_name'])): ?>
                                        <span class="fw-bold text-dark d-block">🚴 <?= htmlspecialchars($ord['rider_name']); ?></span>
                                        <small class="text-muted">📞 <?= htmlspecialchars($ord['rider_phone'] ?? 'N/A'); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small">Awaiting Rider</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($st === 'pending'): ?>
                                        <form action="orders.php" method="POST" class="m-0">
                                            <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                            <input type="hidden" name="action" value="dispatch">
                                            <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                                                📦 Mark Dispatched
                                            </button>
                                        </form>
                                    <?php elseif ($st === 'dispatched' || $st === 'out for delivery'): ?>
                                        <span class="badge bg-light text-primary border border-primary px-2 py-1 small">In Rider Pipeline</span>
                                    <?php elseif ($st === 'delivered'): ?>
                                        <span class="badge bg-light text-success border border-success px-2 py-1 small">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border px-2 py-1 small">In Progress</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No incoming customer orders found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$o_stmt->close();
$conn->close();
include_once '../includes/footer.php';
?>