<?php
// Start session if not started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect them away from index/login to their specific dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    
    if ($role === 'pharmacy') {
        header("Location: pharmacy/pharmacy_dashboard.php");
        exit();
    } elseif ($role === 'rider') {
        header("Location: rider/rider_dashboard.php");
        exit();
    } elseif ($role === 'admin') {
        header("Location: admin/admin_dashboard.php");
        exit();
    } else {
        header("Location: customer/customer_dashboard.php");
        exit();
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<div class="container mt-5" >
    <div class="text-center py-5">
        <h1 class="display-4 fw=bold text-success">Online Quick Pharmacy</h1>

        <p class="lead text-secondary">Find medicines, compare prices accross pharmacies, and consult AI for medical guidance.</p>

        <div class="mt-4">
            <a href="register.php" class="btn btn-success btn-lg me-2">Get Started (Register)</a>
            <a href="login.php" class="btn btn-outline-success btn-lg">Login</a>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>