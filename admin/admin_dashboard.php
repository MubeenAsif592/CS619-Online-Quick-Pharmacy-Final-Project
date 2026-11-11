<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['admin']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

// System Metrics
$total_customers = 0;
$total_pharmacies = 0;
$total_orders = 0;
$total_revenue = 0.00;

$r1 = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role = 'customer'");
if ($r1) { $total_customers = $r1->fetch_assoc()['count']; }

$r2 = $conn->query("SELECT COUNT(*) AS count FROM pharmacies");
if ($r2) { $total_pharmacies = $r2->fetch_assoc()['count']; }

$r3 = $conn->query("SELECT COUNT(*) AS count, SUM(total_amount) AS revenue FROM orders");
if ($r3) {
    $row3 = $r3->fetch_assoc();
    $total_orders = $row3['count'] ?? 0;
    $total_revenue = (float)($row3['revenue'] ?? 0);
}
?>

<div class="container my-5">
    <!-- Header Banner -->
    <div class="card bg-danger text-white p-4 shadow-sm mb-4 border-0">
        <h2 class="fw-bold mb-1">Admin Control Panel</h2>
        <p class="mb-0">System-wide platform statistics, pharmacy approvals, and promotions management (FR9).</p>
    </div>

    <!-- Analytics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white p-3 shadow-sm h-100 border-0">
                <h6 class="text-uppercase fw-bold mb-1" style="opacity: 0.85;">Total Customers</h6>
                <h1 class="display-5 fw-bold mb-0"><?= $total_customers; ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-dark p-3 shadow-sm h-100 border-0">
                <h6 class="text-uppercase fw-bold mb-1" style="opacity: 0.85;">Registered Pharmacies</h6>
                <h1 class="display-5 fw-bold mb-0"><?= $total_pharmacies; ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark p-3 shadow-sm h-100 border-0">
                <h6 class="text-uppercase fw-bold mb-1" style="opacity: 0.85;">Total Orders Placed</h6>
                <h1 class="display-5 fw-bold mb-0"><?= $total_orders; ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white p-3 shadow-sm h-100 border-0">
                <h6 class="text-uppercase fw-bold mb-1" style="opacity: 0.85;">System Revenue</h6>
                <h1 class="display-6 fw-bold mb-0">Rs. <?= number_format($total_revenue, 2); ?></h1>
            </div>
        </div>
    </div>

    <!-- Core Action Modules -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card bg-dark text-white p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">Manage Pharmacies</h5>
                        <p class="mb-0 text-white-50">View all registered pharmacy stores, monitor listed stock, and manage account access.</p>
                    </div>
                    <a href="manage_pharmacies.php" class="btn btn-outline-light btn-sm mt-4 fw-bold">Manage Pharmacies</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-secondary text-white p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">Marketing & Promotions (FR9)</h5>
                        <p class="mb-0 text-white-50">Issue promotional discount credits directly into customer wallets for system campaigns.</p>
                    </div>
                    <a href="promotions.php" class="btn btn-dark btn-sm mt-4 fw-bold">Manage Promotions</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-success text-white p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">System Reports (FR14)</h5>
                        <p class="mb-0 text-white-50">View sales analytics, low-stock inventory alerts, wallet transaction logs, and ratings.</p>
                    </div>
                    <a href="reports.php" class="btn btn-dark btn-sm mt-4 fw-bold">📊 Open Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>