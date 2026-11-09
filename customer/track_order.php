<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

$customer_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Order Details
$stmt = $conn->prepare("SELECT o.*, p.pharmacy_name, p.address AS pharmacy_address 
                        FROM orders o 
                        JOIN pharmacies p ON o.pharmacy_id = p.id 
                        WHERE o.id = ? AND o.customer_id = ?");
$stmt->bind_param("ii", $order_id, $customer_id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<div class='container my-5'><div class='alert alert-danger'>Order not found or access denied.</div></div>";
    include_once '../includes/footer.php';
    exit();
}

$status = strtolower($order['order_status']);
?>

<!-- Leaflet CSS for Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container my-4" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold mb-1">📍 Live Order Tracking</h2>
            <p class="text-muted mb-0">Tracking Order #ORD-<?= $order['id']; ?></p>
        </div>
        <a href="customer_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <!-- Status Progress Bar -->
    <div class="card shadow-sm p-4 mb-4">
        <h5 class="fw-bold mb-3">Delivery Progress</h5>
        <div class="progress" style="height: 25px;">
            <?php 
            $pct = 33;
            if ($status === 'dispatched' || $status === 'out for delivery') $pct = 66;
            if ($status === 'delivered') $pct = 100;
            ?>
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success fw-bold" role="progressbar" style="width: <?= $pct; ?>%;">
                <?= strtoupper($status); ?> (<?= $pct; ?>%)
            </div>
        </div>
        
        <div class="d-flex justify-content-between mt-3 text-center small fw-bold">
            <span class="<?= $pct >= 33 ? 'text-success' : 'text-muted'; ?>">1. Order Placed</span>
            <span class="<?= $pct >= 66 ? 'text-success' : 'text-muted'; ?>">2. Out for Delivery</span>
            <span class="<?= $pct == 100 ? 'text-success' : 'text-muted'; ?>">3. Delivered</span>
        </div>
    </div>

    <!-- Map View -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">
            🗺️ Live Delivery Route Simulation
        </div>
        <div class="card-body p-0">
            <div id="map" style="height: 380px; width: 100%;"></div>
        </div>
    </div>

    <!-- Order Summary Details -->
    <div class="card shadow-sm p-3">
        <div class="row">
            <div class="col-md-6">
                <strong class="d-block text-muted">Pickup Pharmacy:</strong>
                <span><?= htmlspecialchars($order['pharmacy_name']); ?></span>
            </div>
            <div class="col-md-6">
                <strong class="d-block text-muted">Delivery Address:</strong>
                <span><?= htmlspecialchars($order['delivery_address']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS for Interactive Map -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Default Map Coordinates (Lahore Center Simulation)
var map = L.map('map').setView([31.5204, 74.3587], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© Quick Pharmacy Tracking System'
}).addTo(map);

// Add Markers
var pharmacyMarker = L.marker([31.5204, 74.3587]).addTo(map)
    .bindPopup('<b>Pharmacy Location</b><br><?= htmlspecialchars($order['pharmacy_name']); ?>').openPopup();

var customerMarker = L.marker([31.5497, 74.3436]).addTo(map)
    .bindPopup('<b>Delivery Address</b><br><?= htmlspecialchars($order['delivery_address']); ?>');
</script>

<?php
$conn->close();
include_once '../includes/footer.php';
?>