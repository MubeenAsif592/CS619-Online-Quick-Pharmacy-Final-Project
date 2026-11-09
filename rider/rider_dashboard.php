<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['rider']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

$rider_id = $_SESSION['user_id'];
$rider_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Delivery Rider');

// Update Order Status by Rider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['new_status']);

    $u_stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
    $u_stmt->bind_param("si", $new_status, $order_id);
    if ($u_stmt->execute()) {
        $_SESSION['success'] = "Order #" . $order_id . " status updated to " . ucfirst($new_status) . "!";
    }
    $u_stmt->close();
    header("Location: rider_dashboard.php");
    exit();
}

// Fetch All System Orders for Delivery Management
$q = "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone, p.pharmacy_name, p.address AS pharmacy_address 
      FROM orders o 
      JOIN users u ON o.customer_id = u.id 
      JOIN pharmacies p ON o.pharmacy_id = p.id 
      ORDER BY o.id DESC";
$orders = $conn->query($q);
?>

<div class="container my-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Banner -->
    <div class="card bg-dark text-white p-4 shadow-sm mb-4 border-0">
        <h2 class="fw-bold mb-1">🚴 Rider Delivery Console</h2>
        <p class="mb-0 text-white-50">Logged in as: <?= htmlspecialchars($rider_name); ?> | Manage pickup and live customer deliveries.</p>
    </div>

    <!-- Incoming Delivery Jobs Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-secondary text-white py-3">
            <h5 class="mb-0 fw-bold">🛵 Delivery Assignments</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Pickup Pharmacy</th>
                            <th>Customer & Address</th>
                            <th>Total Amount</th>
                            <th>Current Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders && $orders->num_rows > 0): ?>
                            <?php while ($ord = $orders->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold">#ORD-<?= $ord['id']; ?></td>
                                <td>
                                    <strong class="text-primary d-block"><?= htmlspecialchars($ord['pharmacy_name']); ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($ord['pharmacy_address']); ?></small>
                                </td>
                                <td>
                                    <strong class="d-block"><?= htmlspecialchars($ord['customer_name']); ?></strong>
                                    <small class="text-muted">📍 <?= htmlspecialchars($ord['delivery_address']); ?></small>
                                </td>
                                <td class="fw-bold text-success">Rs. <?= number_format((float)$ord['total_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    $st = strtolower($ord['order_status']);
                                    if ($st === 'pending') echo '<span class="badge bg-warning text-dark">Pending Pickup</span>';
                                    elseif ($st === 'dispatched' || $st === 'out for delivery') echo '<span class="badge bg-info text-dark">Out for Delivery</span>';
                                    elseif ($st === 'delivered') echo '<span class="badge bg-success">Delivered</span>';
                                    else echo '<span class="badge bg-secondary">' . htmlspecialchars($ord['order_status']) . '</span>';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <form action="rider_dashboard.php" method="POST" class="d-inline-flex gap-1 m-0">
                                        <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                        <?php if ($st === 'pending'): ?>
                                            <button type="submit" name="new_status" value="dispatched" class="btn btn-sm btn-info fw-bold">Pickup Order</button>
                                        <?php elseif ($st === 'dispatched' || $st === 'out for delivery'): ?>
                                            <button type="submit" name="new_status" value="delivered" class="btn btn-sm btn-success fw-bold">Mark Delivered</button>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">Completed</span>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No delivery jobs available right now.</td>
                            </tr>
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