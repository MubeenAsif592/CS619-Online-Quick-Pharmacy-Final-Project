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

// Handle Order Actions (Accept / Complete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $order_id = intval($_POST['order_id']);
    $action = trim($_POST['action']);

    if ($action === 'accept') {
        $u = $conn->prepare("UPDATE orders SET order_status = 'out for delivery', rider_id = ? WHERE id = ?");
        $u->bind_param("ii", $rider_id, $order_id);
        if ($u->execute()) {
            $_SESSION['active_rider_order_id'] = $order_id;
            $_SESSION['success'] = "Order #ORD-" . $order_id . " accepted! Live GPS navigation active.";
        }
        $u->close();
    } elseif ($action === 'delivered') {
        $u = $conn->prepare("UPDATE orders SET order_status = 'delivered' WHERE id = ?");
        $u->bind_param("i", $order_id);
        if ($u->execute()) {
            unset($_SESSION['active_rider_order_id']);
            $_SESSION['success'] = "Order #ORD-" . $order_id . " successfully marked as Delivered!";
        }
        $u->close();
    }
    header("Location: rider_dashboard.php");
    exit();
}

// 1. Fetch Active Order for this Rider
$active_order = null;
$active_query = "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone, p.pharmacy_name, p.address AS pharmacy_address 
                FROM orders o 
                JOIN users u ON o.customer_id = u.id 
                JOIN pharmacies p ON o.pharmacy_id = p.id 
                WHERE o.rider_id = $rider_id AND LOWER(o.order_status) = 'out for delivery' 
                ORDER BY o.id DESC LIMIT 1";

$active_res = $conn->query($active_query);

if ($active_res && $active_res->num_rows > 0) {
    $active_order = $active_res->fetch_assoc();
} elseif (isset($_SESSION['active_rider_order_id'])) {
    $sess_ord_id = intval($_SESSION['active_rider_order_id']);
    $fallback_res = $conn->query("SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone, p.pharmacy_name, p.address AS pharmacy_address 
                                  FROM orders o 
                                  JOIN users u ON o.customer_id = u.id 
                                  JOIN pharmacies p ON o.pharmacy_id = p.id 
                                  WHERE o.id = $sess_ord_id AND LOWER(o.order_status) != 'delivered'");
    if ($fallback_res && $fallback_res->num_rows > 0) {
        $active_order = $fallback_res->fetch_assoc();
    }
}

// 2. Fetch Available Dispatched Orders (Either specifically assigned to this rider OR unassigned)
$pending_query = "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone, p.pharmacy_name, p.address AS pharmacy_address 
                  FROM orders o 
                  JOIN users u ON o.customer_id = u.id 
                  JOIN pharmacies p ON o.pharmacy_id = p.id 
                  WHERE (LOWER(o.order_status) = 'dispatched' OR LOWER(o.order_status) = 'pending') 
                    AND (o.rider_id = $rider_id OR o.rider_id IS NULL)
                  ORDER BY o.id DESC";
$pending_orders = $conn->query($pending_query);
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
        <h2 class="fw-bold mb-1">🚴 Rider Console - Welcome <?= htmlspecialchars($rider_name); ?></h2>
        <p class="mb-0 text-white-50">Manage active deliveries and turn-by-turn route directions.</p>
    </div>

    <!-- Active Delivery Box -->
    <?php if ($active_order): ?>
        <div class="card border-primary shadow-lg mb-5" style="border-width: 2px;">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">🚨 ACTIVE DELIVERY IN PROGRESS (#ORD-<?= $active_order['id']; ?>)</h5>
                <span class="badge bg-warning text-dark fs-6">📡 Live GPS Tracking Active</span>
            </div>
            <div class="card-body p-4 bg-light">
                <div class="row g-4 align-items-center">
                    
                    <div class="col-md-6 border-end">
                        <div class="bg-white p-3 rounded shadow-sm border mb-3">
                            <h6 class="text-uppercase text-danger fw-bold mb-2">📍 Customer Delivery Destination</h6>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($active_order['customer_name']); ?></h4>
                            <p class="mb-2 text-primary fw-bold">
                                📞 Phone: <a href="tel:<?= htmlspecialchars($active_order['customer_phone']); ?>" class="text-decoration-none"><?= htmlspecialchars($active_order['customer_phone'] ?? 'N/A'); ?></a>
                            </p>
                            <p class="mb-0 text-dark"><strong>Exact Address:</strong> <?= htmlspecialchars($active_order['delivery_address']); ?></p>
                        </div>
                        
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($active_order['delivery_address']); ?>" target="_blank" class="btn btn-danger btn-lg fw-bold w-100 shadow">
                            🗺️ Open Route in Google Maps
                        </a>
                    </div>

                    <div class="col-md-6">
                        <div class="bg-white p-3 rounded shadow-sm border mb-3">
                            <h6 class="text-uppercase text-muted fw-bold mb-2">🏪 Pickup Pharmacy Store</h6>
                            <h5 class="fw-bold text-primary mb-1"><?= htmlspecialchars($active_order['pharmacy_name']); ?></h5>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($active_order['pharmacy_address'] ?? 'Store Location'); ?></p>
                            <h4 class="text-success fw-bold mb-0">Collect Amount: Rs. <?= number_format((float)$active_order['total_amount'], 2); ?></h4>
                        </div>

                        <form action="rider_dashboard.php" method="POST" class="m-0">
                            <input type="hidden" name="order_id" value="<?= $active_order['id']; ?>">
                            <button type="submit" name="action" value="delivered" class="btn btn-success btn-lg fw-bold w-100 shadow py-3">
                                ✅ Complete & Mark Delivered
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Available Orders Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-secondary text-white py-3">
            <h5 class="mb-0 fw-bold">📋 Available Delivery Orders</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Pickup Store</th>
                            <th>Customer Address</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_orders && $pending_orders->num_rows > 0): ?>
                            <?php while ($ord = $pending_orders->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold">#ORD-<?= $ord['id']; ?></td>
                                <td>
                                    <strong class="text-primary d-block"><?= htmlspecialchars($ord['pharmacy_name']); ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($ord['pharmacy_address'] ?? 'Pharmacy Store'); ?></small>
                                </td>
                                <td>
                                    <strong class="d-block"><?= htmlspecialchars($ord['customer_name']); ?></strong>
                                    <small class="text-muted">📍 <?= htmlspecialchars($ord['delivery_address']); ?></small>
                                </td>
                                <td class="fw-bold text-success">Rs. <?= number_format((float)$ord['total_amount'], 2); ?></td>
                                <td><span class="badge bg-warning text-dark">Ready for Pickup</span></td>
                                <td class="text-center">
                                    <form action="rider_dashboard.php" method="POST" class="m-0">
                                        <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                        <button type="submit" name="action" value="accept" class="btn btn-sm btn-success fw-bold px-3">
                                            Accept & Navigate
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No pending orders waiting for pickup right now.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($active_order): ?>
<script>
const activeOrderId = <?= $active_order['id']; ?>;

if ("geolocation" in navigator) {
    function updateGPS() {
        navigator.geolocation.getCurrentPosition(pos => {
            fetch('update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: activeOrderId, lat: pos.coords.latitude, lng: pos.coords.longitude })
            });
        }, err => {
            fetch('update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: activeOrderId, lat: 31.5204, lng: 74.3587 })
            });
        });
    }
    updateGPS();
    setInterval(updateGPS, 5000);
}
</script>
<?php endif; ?>

<?php
$conn->close();
include_once '../includes/footer.php';
?>