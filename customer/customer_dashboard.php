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
$customer_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Customer');
$cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$show_delivered = isset($_GET['show_delivered']) && $_GET['show_delivered'] === '1';

// Query filters delivered orders unless unhidden
if ($show_delivered) {
    $o_stmt = $conn->prepare("SELECT o.*, p.pharmacy_name 
                             FROM orders o 
                             JOIN pharmacies p ON o.pharmacy_id = p.id 
                             WHERE o.customer_id = ? 
                             ORDER BY o.id DESC");
    $o_stmt->bind_param("i", $customer_id);
} else {
    $o_stmt = $conn->prepare("SELECT o.*, p.pharmacy_name 
                             FROM orders o 
                             JOIN pharmacies p ON o.pharmacy_id = p.id 
                             WHERE o.customer_id = ? AND LOWER(o.order_status) != 'delivered' 
                             ORDER BY o.id DESC");
    $o_stmt->bind_param("i", $customer_id);
}

$o_stmt->execute();
$orders_result = $o_stmt->get_result();
?>

<div class="container my-5">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card bg-success text-white p-4 shadow-sm mb-4 border-0">
        <h2 class="fw-bold mb-1">Customer Panel - <?= htmlspecialchars($customer_name); ?></h2>
        <p class="mb-0">Welcome to Online Quick Pharmacy Market.</p>
    </div>

    <!-- Main Action Modules -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card bg-primary text-white p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">Search Medicines</h5>
                        <p class="mb-0 text-white-50">Search by name, category, or symptoms and compare prices across pharmacies.</p>
                    </div>
                    <a href="customer_search.php" class="btn btn-dark btn-sm mt-4 fw-bold">Search & Compare</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-dark text-white p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-2">Shopping Cart</h5>
                            <span class="badge bg-success fs-6"><?= $cart_count; ?> Items</span>
                        </div>
                        <p class="mb-0 text-white-50">Review selected items, update quantities, and proceed to checkout.</p>
                    </div>
                    <a href="cart.php" class="btn btn-success btn-sm mt-4 fw-bold">View Cart & Checkout</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-info text-dark p-3 shadow-sm h-100 border-0">
                <div class="card-body d-flex flex-column justify-content-between p-0">
                    <div>
                        <h5 class="fw-bold mb-2">AI Assistant</h5>
                        <p class="mb-0 text-dark-50">Get instant AI guidance for medicine details and medical queries.</p>
                    </div>
                    <a href="ai_chat.php" class="btn btn-dark btn-sm mt-4 fw-bold text-white">Ask AI Assistant</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-dark p-3 shadow-sm h-100 border-0">
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

    <!-- Active Orders & Live Map Tracking Section -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">📦 My Active Orders & Live Tracking</h5>
            <div>
                <?php if ($show_delivered): ?>
                    <a href="customer_dashboard.php" class="btn btn-sm btn-outline-light fw-bold">Hide Delivered Orders</a>
                <?php else: ?>
                    <a href="customer_dashboard.php?show_delivered=1" class="btn btn-sm btn-outline-light fw-bold">Show Delivered Orders</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Pharmacy Store</th>
                            <th>Total Amount</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while ($ord = $orders_result->fetch_assoc()): ?>
                                <?php $st = strtolower($ord['order_status']); ?>
                                <tr>
                                    <td class="fw-bold">#ORD-<?= $ord['id']; ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($ord['pharmacy_name']); ?></td>
                                    <td class="text-success fw-bold">Rs. <?= number_format((float) $ord['total_amount'], 2); ?></td>
                                    <td class="small"><?= date('g:i A, d-m-Y', strtotime($ord['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        if ($st === 'pending')
                                            echo '<span class="badge bg-warning text-dark">Pending</span>';
                                        elseif ($st === 'dispatched' || $st === 'out for delivery')
                                            echo '<span class="badge bg-info text-dark">Out for Delivery</span>';
                                        elseif ($st === 'delivered')
                                            echo '<span class="badge bg-success">Delivered</span>';
                                        else
                                            echo '<span class="badge bg-secondary">' . htmlspecialchars($ord['order_status']) . '</span>';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($st === 'delivered'): ?>
                                            <!-- FR15: Rate Order Button -->
                                            <a href="feedback.php?order_id=<?= $ord['id']; ?>" class="btn btn-sm btn-outline-warning fw-bold me-1">
                                                ⭐ Rate Order
                                            </a>
                                        <?php endif; ?>
                                        <a href="map_track.php?id=<?= $ord['id']; ?>" class="btn btn-sm btn-success fw-bold px-3">
                                            📍 Track Order
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <?= $show_delivered ? 'No order history found.' : 'No active orders in progress. (Click "Show Delivered Orders" to view completed history)'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$o_stmt->close();
$conn->close();
include_once '../includes/footer.php';
?>