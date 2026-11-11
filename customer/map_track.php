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

$stmt = $conn->prepare("SELECT o.*, p.pharmacy_name, p.address AS pharmacy_address, 
                               r.full_name AS rider_name, r.phone AS rider_phone 
                        FROM orders o 
                        JOIN pharmacies p ON o.pharmacy_id = p.id 
                        LEFT JOIN users r ON o.rider_id = r.id 
                        WHERE o.id = ? AND o.customer_id = ?");
$stmt->bind_param("ii", $order_id, $customer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<div class='container my-5'><div class='alert alert-danger'>Order not found or unauthorized.</div></div>";
    include_once '../includes/footer.php';
    exit();
}

$status = strtolower(trim((string)$order['order_status']));
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container my-4" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold mb-1">🗺️ Live Map Tracking</h2>
            <p class="text-muted mb-0">Tracking Order #ORD-<?= $order['id']; ?></p>
        </div>
        <a href="customer_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <!-- Status Bar -->
    <div class="card shadow-sm p-4 mb-4 border-0">
        <h5 class="fw-bold mb-3">Delivery Progress</h5>
        <div class="progress" style="height: 25px;">
            <?php 
            $pct = 33;
            if ($status === 'dispatched' || $status === 'out for delivery') $pct = 66;
            if ($status === 'delivered') $pct = 100;
            ?>
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success fw-bold" style="width: <?= $pct; ?>%;">
                <?= strtoupper($status === '' ? 'PENDING' : $status); ?> (<?= $pct; ?>%)
            </div>
        </div>
        <div class="d-flex justify-content-between mt-3 text-center small fw-bold">
            <span class="<?= $pct >= 33 ? 'text-success' : 'text-muted'; ?>">1. Order Placed</span>
            <span class="<?= $pct >= 66 ? 'text-success' : 'text-muted'; ?>">2. Out for Delivery</span>
            <span class="<?= $pct == 100 ? 'text-success' : 'text-muted'; ?>">3. Delivered</span>
        </div>
    </div>

    <!-- Map Container -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center fw-bold">
            <span>📍 Real-Time GPS Delivery Tracking</span>
            <span id="liveStatus" class="badge bg-success">Live Polling Active</span>
        </div>
        <div class="card-body p-0">
            <div id="trackingMap" style="height: 420px; width: 100%;"></div>
        </div>
    </div>

    <!-- Order Summary Details -->
    <div class="card shadow-sm p-3 border-0">
        <div class="row">
            <div class="col-md-4">
                <strong class="text-muted d-block">Pickup Pharmacy:</strong>
                <span><?= htmlspecialchars($order['pharmacy_name']); ?></span>
            </div>
            <div class="col-md-4">
                <strong class="text-muted d-block">Assigned Rider:</strong>
                <span id="riderNameSpan"><?= htmlspecialchars($order['rider_name'] ?? 'Awaiting rider pickup...'); ?></span>
                <?php if (!empty($order['rider_phone'])): ?>
                    <small class="d-block text-muted">📞 <?= htmlspecialchars($order['rider_phone']); ?></small>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <strong class="text-muted d-block">Delivery Address:</strong>
                <span><?= htmlspecialchars($order['delivery_address']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const rawAddress = <?= json_encode($order['delivery_address']); ?>;

// Default Map initialization
var map = L.map('trackingMap').setView([31.5204, 74.3587], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© Quick Pharmacy Real-Time Tracking'
}).addTo(map);

// Geocode Customer Address dynamically
function locateAddress(address) {
    let query = encodeURIComponent(address);
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}`)
        .then(res => res.json())
        .then(data => {
            if (data && data.length > 0) {
                const cLat = parseFloat(data[0].lat);
                const cLng = parseFloat(data[0].lon);
                
                L.marker([cLat, cLng]).addTo(map)
                    .bindPopup(`<b>📍 Delivery Destination:</b><br>${address}`).openPopup();
                map.setView([cLat, cLng], 14);
            } else {
                // Browser Geolocation position fallback for exact user location
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(pos => {
                        const uLat = pos.coords.latitude;
                        const uLng = pos.coords.longitude;
                        L.marker([uLat, uLng]).addTo(map)
                            .bindPopup(`<b>📍 My Location (Delivery Destination):</b><br>${address}`).openPopup();
                        map.setView([uLat, uLng], 14);
                    }, err => {
                        L.marker([31.5204, 74.3587]).addTo(map).bindPopup(`<b>📍 Delivery Address:</b><br>${address}`).openPopup();
                    });
                }
            }
        });
}

locateAddress(rawAddress);

var riderMarker = null;

// Poll Live Rider Position
function fetchRiderLocation() {
    fetch('get_live_location.php?order_id=<?= $order['id']; ?>')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.lat && data.lng) {
                const rLat = parseFloat(data.lat);
                const rLng = parseFloat(data.lng);

                if (!riderMarker) {
                    riderMarker = L.marker([rLat, rLng]).addTo(map)
                        .bindPopup(`<b>🚴 Live Rider:</b> ${data.rider_name || 'In Transit'}`).openPopup();
                } else {
                    riderMarker.setLatLng([rLat, rLng]);
                }
            }
        })
        .catch(err => console.error("Error polling location:", err));
}

fetchRiderLocation();
setInterval(fetchRiderLocation, 4000);
</script>

<?php
$conn->close();
include_once '../includes/footer.php';
?>