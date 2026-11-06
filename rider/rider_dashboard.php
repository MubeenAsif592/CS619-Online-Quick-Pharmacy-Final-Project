<?php 
require_once'includes/auth_check.php';
check_role(['rider']);
require_once'includes/header.php';
require_once'includes/navbar.php';
?>

<div class="container my-5">
     <div class="d-flex justify-content-between align-items-center mb-4">
     <h2>Rider Panel -<?= htmlspecialchars($_SESSION['user_name']); ?></h2>
     <a href="logout.php" class="btn btn-outline-danger">Logout</a>
     </div>

     <div class="alert alert-info shadow-sm">
        <h5>Available Deliveries</h5>
        <p class="mb-0">Accept nearby medicine order requests & view Google Maps navigation.</p>
     </div>
</div>
<?php require_once'includes/footer.php';