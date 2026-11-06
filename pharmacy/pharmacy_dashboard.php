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
$pharmacy_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Pharmacy Owner');

// Fetch pharmacy_id
$p_stmt = $conn->prepare("SELECT id FROM pharmacies WHERE user_id = ?");
$p_stmt->bind_param("i", $user_id);
$p_stmt->execute();
$p_res = $p_stmt->get_result();
$pharmacy_id = ($p_res->num_rows > 0) ? $p_res->fetch_assoc()['id'] : 0;
$p_stmt->close();

// Dynamic Analytics Queries
$total_stmt = $conn->prepare("SELECT COUNT(*) AS total_count FROM medicines WHERE pharmacy_id = ?");
$total_stmt->bind_param("i", $pharmacy_id);
$total_stmt->execute();
$total_count = $total_stmt->get_result()->fetch_assoc()['total_count'] ?? 0;
$total_stmt->close();

$low_stock_stmt = $conn->prepare("SELECT COUNT(*) AS low_count FROM medicines WHERE pharmacy_id = ? AND stock_quantity <= 10");
$low_stock_stmt->bind_param("i", $pharmacy_id);
$low_stock_stmt->execute();
$low_stock_count = $low_stock_stmt->get_result()->fetch_assoc()['low_count'] ?? 0;
$low_stock_stmt->close();

$val_stmt = $conn->prepare("SELECT SUM(price * stock_quantity) AS total_val FROM medicines WHERE pharmacy_id = ?");
$val_stmt->bind_param("i", $pharmacy_id);
$val_stmt->execute();
$total_val = $val_stmt->get_result()->fetch_assoc()['total_val'] ?? 0.00;
$val_stmt->close();
?>

<div class="container my-5">
    <!-- Header -->
    <div class="mb-4">
        <h2 class="fw-bold mb-1">Pharmacy Panel - <?= htmlspecialchars($pharmacy_name); ?></h2>
        <p class="text-muted mb-0">Overview of inventory analytics, incoming orders, and marketing wallet.</p>
    </div>

    <!-- Live Analytics Counter Cards -->
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="card bg-primary text-white shadow-sm h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-uppercase fw-semibold" style="opacity: 0.85;">Total Medicines</h6>
                    <h2 class="display-6 fw-bold mb-0"><?= number_format($total_count); ?></h2>
                    <small class="text-light">Active items in stock</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark shadow-sm h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-uppercase fw-semibold" style="opacity: 0.85;">Low Stock Alert</h6>
                    <h2 class="display-6 fw-bold mb-0"><?= number_format($low_stock_count); ?></h2>
                    <small class="text-dark">Items with &le; 10 units</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white shadow-sm h-100 border-0">
                <div class="card-body">
                    <h6 class="card-title text-uppercase fw-semibold" style="opacity: 0.85;">Total Inventory Value</h6>
                    <h2 class="display-6 fw-bold mb-0">Rs. <?= number_format($total_val, 2); ?></h2>
                    <small class="text-light">Estimated worth</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Action Cards -->
    <div class="row g-4">
        <!-- 1. Manage Inventory -->
        <div class="col-md-4">
            <div class="card bg-primary text-white p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">Manage Inventory</h5>
                        <p class="mb-0 text-white-50">Add & update medicines stock.</p>
                    </div>
                    <a href="manage_inventory.php" class="btn btn-dark btn-sm mt-4 fw-bold">Open Hub</a>
                </div>
            </div>
        </div>

        <!-- 2. Orders Received -->
        <div class="col-md-4">
            <div class="card bg-success text-white p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">Orders Received</h5>
                        <p class="mb-0 text-white-50">View & process incoming customer orders (FR6).</p>
                    </div>
                    <a href="#" class="btn btn-dark btn-sm mt-4 fw-bold">View Orders</a>
                </div>
            </div>
        </div>

        <!-- 3. Marketing Wallet -->
        <div class="col-md-4">
            <div class="card bg-warning text-dark p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">Marketing Wallet</h5>
                        <p class="mb-0 text-dark-50">Manage promotional budget & ad credits.</p>
                    </div>
                    <a href="#" class="btn btn-dark btn-sm mt-4 fw-bold">Manage Wallet</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once '../includes/footer.php';
?>