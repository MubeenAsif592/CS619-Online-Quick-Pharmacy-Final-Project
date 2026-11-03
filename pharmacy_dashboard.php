<?php
// 'require_once' = Security check function
require_once 'includes/auth_check.php';
check_role(['pharmacy']);
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Pharmacy Panel -<?=htmlspecialchars($_SESSION['user_name']); ?></h2>
        <!-- <a href="logout.php" class="btn btn-outline-danger">Logout</a> -->
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white p-3 shadow-sm">
                <h5>Manage Inventory</h5>
                <p class="mb-0">Add & update medicines stock.</p>
                <a href="manage_inventory.php" class="btn btn-dark btn-sm mt-3 fw-bold">Open Hub</a>
            </div>
        </div>
            <div class="col-md-4">
                <div class="card bg-success text-white p-3 shadow-sm">
                <h5>Orders Received</h5>
                <p class="mb-0">View & process incoming customer orders (FR6).</p>
                </div>
            </div>
        

        <div class="col-md-4">
            <div class="card bg-warning text-white p-3  shadow-sm">
                <h5>Marketing Wallet</h5>
                <p class="mb-0">Manage promotional budget & ad credits.</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once'includes/footer.php';
?>
