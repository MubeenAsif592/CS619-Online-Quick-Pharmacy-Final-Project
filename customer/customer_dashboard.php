<?php
// Session start agar initialize nahi hai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database aur Auth Middleware include kar rahe hain
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']); // Sirf 'customer' role allow hai

// Global Header aur Navbar include
include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Logged-in customer ka naam capture kar rahe hain
$customer_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Customer');
?>

<div class="container my-5">
    <!-- Header Title Banner -->
    <div class="card bg-success text-white p-4 shadow-sm mb-4">
        <h2 class="fw-bold mb-1">Customer Panel - <?= htmlspecialchars($customer_name); ?></h2>
        <p class="mb-0">Welcome to Online Quick Pharmacy Market.</p>
    </div>

    <!-- Simple Main Action Modules -->
    <div class="row g-4">
        <!-- Card 1: Search & Compare Medicines (FR3 & FR4) -->
        <div class="col-md-4">
            <div class="card bg-primary text-white p-3 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">Search Medicines</h5>
                        <p class="mb-0 text-white-50">Search by name, category, or symptoms and compare prices across pharmacies.</p>
                    </div>
                    <a href="customer_search.php" class="btn btn-dark btn-sm mt-4 fw-bold">Search & Compare</a>
                </div>
            </div>
        </div>

        <!-- Card 2: AI Medical Assistant (FR8) -->
        <div class="col-md-4">
            <div class="card bg-info text-dark p-3 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">AI Medical Assistant</h5>
                        <p class="mb-0 text-dark-50">Get instant AI guidance for medicine details and medical queries.</p>
                    </div>
                    <a href="ai_chat.php" class="btn btn-dark btn-sm mt-4 fw-bold text-white">Ask AI Assistant</a>
                </div>
            </div>
        </div>

        <!-- Card 3: Customer Wallet (FR10) -->
        <div class="col-md-4">
            <div class="card bg-warning text-dark p-3 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">My Wallet</h5>
                        <p class="mb-0 text-dark-50">View your wallet balance and promotional discount credits.</p>
                    </div>
                    <a href="wallet.php" class="btn btn-dark btn-sm mt-4 fw-bold text-white">Manage Wallet</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Layout Footer Inclusion
include_once '../includes/footer.php';
?>