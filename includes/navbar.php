<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = "/Final%20Project%20Online%20Pharmacy/";

// Exact file mapping according to your folder structure
$dashboard_link = $base_url . "index.php";
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'pharmacy') {
        $dashboard_link = $base_url . "pharmacy/pharmacy_dashboard.php";
    } elseif ($_SESSION['user_role'] === 'rider') {
        $dashboard_link = $base_url . "rider/rider_dashboard.php";
    } elseif ($_SESSION['user_role'] === 'admin') {
        $dashboard_link = $base_url . "admin/admin_dashboard.php";
    } else {
        $dashboard_link = $base_url . "customer/customer_dashboard.php";
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
<div class="container">
    <a class="navbar-brand text-white fw-bold" href="<?= $base_url; ?>index.php">Quick Pharmacy</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link text-white" href="<?= $base_url; ?>index.php">Home</a>
            </li>
            <?php if (isset($_SESSION['user_id'])): ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="<?= $dashboard_link; ?>">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="<?= $base_url; ?>logout.php">Logout</a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="<?= $base_url; ?>login.php">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="<?= $base_url; ?>register.php">Register</a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>
</nav>