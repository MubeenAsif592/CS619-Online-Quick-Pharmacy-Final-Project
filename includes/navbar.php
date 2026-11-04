<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
<div class="container">
    <a class="navbar-brand text-white fw-bold" href="pharmacy_dashboard.php">Quick Pharmacy</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link text-white" href="pharmacy_dashboard.php">Home</a>
            </li>
            <?php if (isset($_SESSION['user_id'])): ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="pharmacy_dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="logout.php">Logout</a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="login.php">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="register.php">Register</a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>
</nav>