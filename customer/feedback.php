<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

$customer_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['rating'])) {
    $ord_id = intval($_POST['order_id']);
    $rating = intval($_POST['rating']);
    $feedback = trim($_POST['feedback'] ?? '');

    // Get Pharmacy ID for order
    $o_stmt = $conn->prepare("SELECT pharmacy_id FROM orders WHERE id = ? AND customer_id = ?");
    $o_stmt->bind_param("ii", $ord_id, $customer_id);
    $o_stmt->execute();
    $ord_data = $o_stmt->get_result()->fetch_assoc();
    $o_stmt->close();

    if ($ord_data) {
        $pharmacy_id = $ord_data['pharmacy_id'];
        $ins = $conn->prepare("INSERT INTO reviews (customer_id, pharmacy_id, order_id, rating, feedback) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("iiiis", $customer_id, $pharmacy_id, $ord_id, $rating, $feedback);
        $ins->execute();
        $ins->close();
        $_SESSION['success'] = "Thank you! Your feedback and rating have been recorded.";
    }
    header("Location: customer_dashboard.php");
    exit();
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="container my-5" style="max-width: 600px;">
    <div class="card shadow-sm border-0 p-4">
        <h4 class="fw-bold mb-3 text-center">⭐ Rate & Review Pharmacy Service</h4>
        <form action="feedback.php" method="POST">
            <input type="hidden" name="order_id" value="<?= $order_id; ?>">
            
            <div class="mb-3">
                <label class="form-label fw-bold">Select Rating (1 to 5 Stars)</label>
                <select name="rating" class="form-select" required>
                    <option value="5">⭐⭐⭐⭐⭐ 5 - Excellent Service</option>
                    <option value="4">⭐⭐⭐⭐ 4 - Very Good</option>
                    <option value="3">⭐⭐⭐ 3 - Average</option>
                    <option value="2">⭐⭐ 2 - Poor</option>
                    <option value="1">⭐ 1 - Very Poor</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Your Feedback / Comments</label>
                <textarea name="feedback" class="form-control" rows="4" placeholder="Share details about medicine condition, packaging, or rider speed..." required></textarea>
            </div>

            <button type="submit" class="btn btn-success fw-bold w-100">Submit Review</button>
        </form>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>