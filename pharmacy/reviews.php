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

// Get Pharmacy ID
$p_stmt = $conn->prepare("SELECT id, pharmacy_name FROM pharmacies WHERE user_id = ?");
$p_stmt->bind_param("i", $user_id);
$p_stmt->execute();
$pharmacy = $p_stmt->get_result()->fetch_assoc();
$pharmacy_id = $pharmacy['id'] ?? 0;
$p_stmt->close();

// Fetch Reviews for this Pharmacy
$reviews = $conn->query("SELECT r.*, u.full_name AS customer_name 
                        FROM reviews r 
                        JOIN users u ON r.customer_id = u.id 
                        WHERE r.pharmacy_id = $pharmacy_id 
                        ORDER BY r.id DESC");
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">⭐ Customer Ratings & Reviews</h2>
            <p class="text-muted mb-0">Feedback submitted by customers for <?= htmlspecialchars($pharmacy['pharmacy_name'] ?? 'your pharmacy'); ?>.</p>
        </div>
        <a href="pharmacy_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer Name</th>
                            <th>Rating</th>
                            <th>Feedback / Review</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reviews && $reviews->num_rows > 0): ?>
                            <?php while ($rev = $reviews->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold">#ORD-<?= $rev['order_id']; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($rev['customer_name']); ?></td>
                                <td>
                                    <span class="text-warning fw-bold">
                                        <?= str_repeat('⭐', $rev['rating']); ?> (<?= $rev['rating']; ?>/5)
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($rev['feedback']); ?></td>
                                <td class="small text-muted"><?= date('d-m-Y g:i A', strtotime($rev['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No customer ratings received yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>