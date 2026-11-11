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

// Handle Order Accept / Delivered Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $order_id = intval($_POST['order_id']);
    $action = trim($_POST['action']);

    if ($action === 'accept') {
        $u = $conn->prepare("UPDATE orders SET order_status = 'out for delivery', rider_id = ? WHERE id = ?");
        $u->bind_param("ii", $rider_id, $order_id);
        if ($u->execute()) {
            $_SESSION['success'] = "Order #ORD-" . $order_id . " accepted! Live GPS tracking activated.";
        }
        $u->close();
    } elseif ($action === 'delivered') {
        $u = $conn->prepare("UPDATE orders SET order_status = 'delivered' WHERE id = ? AND rider_id = ?");
        $u->bind_param("ii", $order_id, $rider_id);
        if ($u->execute()) {
            $_SESSION['success'] = "Order #ORD-" . $order_id . " marked as Delivered!";
        }
        $u->close();
    }
    header("Location: rider_dashboard.php");
    exit();
}

// Flexible Query: Show orders pending pickup OR assigned out-for-delivery orders
$q = "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone, p.pharmacy_name, p.address AS pharmacy_address 
      FROM orders o 
      JOIN users u ON o.customer_id = u.id 
      JOIN pharmacies p ON o.pharmacy_id = p.id 
      WHERE (LOWER(o.order_status) IN ('pending', 'dispatched') AND (o.rider_id IS NULL OR o.rider_id = $rider_id))
         OR (o.rider_id = $rider_id AND LOWER(o.order_status) = 'out for delivery')
      ORDER BY o.id DESC";
$orders = $conn->query($q);

$active_order_id = 0;
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
        <p class="mb-0 text-white-50">Logged in as: <?= htmlspecialchars($rider_name); ?> | Accept orders and broadcast live GPS locations.</p>
    </div>

    <!-- GPS Status Alert Banner -->
    <div id="gpsAlert" class="alert alert-warning shadow-sm border-0 d-none" role="alert">
        📡 <strong>GPS Tracking Active:</strong> Transmitting live location to customer map...
    </div>

    <!-- Delivery Jobs Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-secondary text-white py-3">
            <h5 class="mb-0 fw-bold">🛵 Available & Active Deliveries</h5>
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
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders && $orders->num_rows > 0): ?>
                            <?php while ($ord = $orders->fetch_assoc()): ?>
                            <?php 
                            $st = strtolower($ord['order_status']);
                            if ($st === 'out for delivery' && $ord['rider_id'] == $rider_id) {
                                $active_order_id = $ord['id'];
                            }
                            ?>
                            <tr>
                                <td class="fw-bold">#ORD-<?= $ord['id']; ?></td>
                                <td>
                                    <strong class="text-primary d-block"><?= htmlspecialchars($ord['pharmacy_name']); ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($ord['pharmacy_address']); ?></small>
                                </td>
                                <td>
                                    <strong class="d-block"><?= htmlspecialchars($ord['customer_name']); ?> (<?= htmlspecialchars($ord['customer_phone']); ?>)</strong>
                                    <small class="text-muted">📍 <?= htmlspecialchars($ord['delivery_address']); ?></small>
                                </td>
                                <td class="fw-bold text-success">Rs. <?= number_format((float)$ord['total_amount'], 2); ?></td>
                                <td>
                                    <?php 
                                    if ($st === 'pending' || $st === 'dispatched') echo '<span class="badge bg-warning text-dark">Ready for Pickup</span>';
                                    elseif ($st === 'out for delivery') echo '<span class="badge bg-info text-dark">In Transit (Live GPS Active)</span>';
                                    elseif ($st === 'delivered') echo '<span class="badge bg-success">Delivered</span>';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <form action="rider_dashboard.php" method="POST" class="d-inline-flex gap-1 m-0">
                                        <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                        <?php if ($st === 'pending' || $st === 'dispatched'): ?>
                                            <button type="submit" name="action" value="accept" class="btn btn-sm btn-success fw-bold">Accept Order</button>
                                        <?php elseif ($st === 'out for delivery' && $ord['rider_id'] == $rider_id): ?>
                                            <button type="submit" name="action" value="delivered" class="btn btn-sm btn-primary fw-bold">Mark Delivered</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No pending delivery jobs available right now.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($active_order_id > 0): ?>
<script>
// Live GPS Broadcast Script
const activeOrderId = <?= $active_order_id; ?>;
const gpsAlert = document.getElementById('gpsAlert');

if ("geolocation" in navigator) {
    gpsAlert.classList.remove('d-none');
    
    function sendLocation() {
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            fetch('update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: activeOrderId, lat: lat, lng: lng })
            })
            .then(res => res.json())
            .then(data => console.log('GPS Updated:', data))
            .catch(err => console.error('GPS Error:', err));
        }, error => {
            console.error("Geolocation failed: ", error);
            // Simulated coordinates fallback (Lahore location)
            fetch('update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: activeOrderId, lat: 31.5204, lng: 74.3587 })
            });
        }, { enableHighAccuracy: true });
    }

    sendLocation();
    setInterval(sendLocation, 5000); // 5 sec interval for live updates
}
</script>
<?php endif; ?>

<?php
$conn->close();
include_once '../includes/footer.php';
?>