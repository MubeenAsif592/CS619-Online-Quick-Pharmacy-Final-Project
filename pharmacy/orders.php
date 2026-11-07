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

// Get exact pharmacy_id from pharmacies table
$p_stmt = $conn->prepare("SELECT id FROM pharmacies WHERE user_id = ?");
$p_stmt->bind_param("i", $user_id);
$p_stmt->execute();
$p_res = $p_stmt->get_result();
$pharmacy_id = ($p_res->num_rows > 0) ? $p_res->fetch_assoc()['id'] : 0;
$p_stmt->close();

// Update Order Status Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['status']);

    $u_stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ? AND pharmacy_id = ?");
    $u_stmt->bind_param("sii", $new_status, $order_id, $pharmacy_id);
    if ($u_stmt->execute()) {
        $_SESSION['success'] = "Order #" . $order_id . " status updated to " . ucfirst($new_status) . ".";
    }
    $u_stmt->close();
    header("Location: orders.php");
    exit();
}

// Fetch Orders received for this pharmacy
$q = "SELECT o.*, u.full_name AS customer_name 
      FROM orders o 
      JOIN users u ON o.customer_id = u.id 
      WHERE o.pharmacy_id = ? 
      ORDER BY o.id DESC";
$stmt = $conn->prepare($q);
$stmt->bind_param("i", $pharmacy_id);
$stmt->execute();
$orders_result = $stmt->get_result();
?>

<div class="container my-4">
    <!-- Success Alert -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold">📦 Customer Incoming Orders</h2>
        <a href="pharmacy_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="card shadow-sm">
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
                            <th class="text-center">Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while ($ord = $orders_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold">#ORD-<?= $ord['id']; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($ord['customer_name']); ?></td>
                                <td class="text-success fw-bold">Rs. <?= number_format((float)$ord['total_amount'], 2); ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($ord['delivery_address']); ?></td>
                                <td class="small"><?= date('g:i A, d-m-Y', strtotime($ord['created_at'])); ?></td>
                                <td>
                                    <?php 
                                    $st = strtolower($ord['order_status']);
                                    if ($st === 'pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
                                    elseif ($st === 'dispatched') echo '<span class="badge bg-info text-dark">Dispatched</span>';
                                    elseif ($st === 'delivered') echo '<span class="badge bg-success">Delivered</span>';
                                    else echo '<span class="badge bg-secondary">' . htmlspecialchars($ord['order_status']) . '</span>';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <form action="orders.php" method="POST" class="d-flex align-items-center justify-content-center gap-1 m-0">
                                        <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: 130px;">
                                            <option value="pending" <?= $st === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="dispatched" <?= $st === 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                                            <option value="delivered" <?= $st === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary fw-bold">Save</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No customer orders received yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
include_once '../includes/footer.php';
?>