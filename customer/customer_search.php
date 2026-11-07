<?php
ob_start();

// Session status check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includes based on project structure
require_once '../includes/db.php';

// Handle Add to Cart action via POST with selected quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart_id'])) {
    $med_id = intval($_POST['add_to_cart_id']);
    $qty_input = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    if ($qty_input < 1) { $qty_input = 1; }

    $stmt = $conn->prepare("SELECT m.*, p.pharmacy_name FROM medicines m JOIN pharmacies p ON m.pharmacy_id = p.id WHERE m.id = ?");
    $stmt->bind_param("i", $med_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $med = $res->fetch_assoc();
        $available_stock = (int)$med['stock_quantity'];

        if ($qty_input > $available_stock) {
            $_SESSION['error'] = "Cannot add more than available stock (" . $available_stock . " units).";
        } else {
            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            if (isset($_SESSION['cart'][$med_id])) {
                $new_qty = $_SESSION['cart'][$med_id]['quantity'] + $qty_input;
                if ($new_qty > $available_stock) {
                    $new_qty = $available_stock;
                }
                $_SESSION['cart'][$med_id]['quantity'] = $new_qty;
            } else {
                $_SESSION['cart'][$med_id] = [
                    'id' => $med['id'],
                    'pharmacy_id' => $med['pharmacy_id'],
                    'pharmacy_name' => $med['pharmacy_name'] ?? 'Pharmacy',
                    'name' => $med['medicine_name'],
                    'price' => (float)$med['price'],
                    'quantity' => $qty_input,
                    'stock' => $available_stock
                ];
            }
            $_SESSION['success'] = "Added " . $qty_input . " x '" . htmlspecialchars($med['medicine_name']) . "' to your cart!";
        }
    }
    $stmt->close();

    $find_param = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';
    $redirect_url = !empty($find_param) ? "customer_search.php?search=" . urlencode($find_param) : "customer_search.php";
    header("Location: " . $redirect_url);
    exit();
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Capture Search Query
$find = isset($_GET['search']) ? trim($_GET['search']) : '';
$medicines = [];

// Base Query: JOIN medicines with pharmacies & users table to get live pharmacy name
if (!empty($find)) {
    $q = "SELECT m.*, COALESCE(p.pharmacy_name, u.full_name) AS pharmacy_name 
          FROM medicines m 
          JOIN pharmacies p ON m.pharmacy_id = p.id 
          JOIN users u ON p.user_id = u.id 
          WHERE m.medicine_name LIKE ? 
             OR m.category LIKE ? 
             OR m.description LIKE ? 
          ORDER BY m.price ASC";
          
    $stmt = $conn->prepare($q);
    $like_val = "%" . $find . "%";
    $stmt->bind_param("sss", $like_val, $like_val, $like_val);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $q = "SELECT m.*, COALESCE(p.pharmacy_name, u.full_name) AS pharmacy_name 
          FROM medicines m 
          JOIN pharmacies p ON m.pharmacy_id = p.id 
          JOIN users u ON p.user_id = u.id 
          ORDER BY m.id DESC LIMIT 20";
    $res = $conn->query($q);
}

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $medicines[] = $row;
    }
}

$cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>

<style>
/* Custom Quantity Selector Styling */
.quantity-input-group {
    display: inline-flex;
    align-items: center;
    border: 1px solid #ced4da;
    border-radius: 4px;
    overflow: hidden;
}
.qty-btn {
    background-color: #f8f9fa;
    border: none;
    padding: 2px 10px;
    font-weight: bold;
    cursor: pointer;
    user-select: none;
}
.qty-btn:hover {
    background-color: #e2e6ea;
}
.qty-input {
    width: 45px;
    text-align: center;
    border: none;
    font-weight: bold;
}
.qty-input::-webkit-inner-spin-button, 
.qty-input::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}
</style>

<div class="container my-4">
    <!-- Feedback Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Header Title & Cart/Dashboard Link -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold">Search & Compare Medicines</h2>
        <div>
            <a href="cart.php" class="btn btn-success fw-bold me-2">🛒 View Cart (<?= $cart_count; ?>)</a>
            <a href="customer_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
        </div>
    </div>

    <!-- Search Form Bar -->
    <div class="card p-3 mb-4 bg-light shadow-sm">
        <form action="customer_search.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-9">
                <input type="text" name="search" class="form-control" placeholder="Search by name, category, or symptom (e.g. Panadol, Pain Relief)..." value="<?= htmlspecialchars($find); ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-success w-100 fw-bold">Search</button>
                <?php if (!empty($find)): ?>
                    <a href="customer_search.php" class="btn btn-outline-dark fw-bold">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Pharmacy Name</th>
                            <th>Price (PKR)</th>
                            <th>Stock Status</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($medicines) > 0): ?>
                            <?php 
                            $sr_no = 1;
                            foreach ($medicines as $med): 
                                $qty = (int)($med['stock_quantity'] ?? 0);
                            ?>
                            <tr>
                                <td><?= $sr_no++; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($med['medicine_name']); ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($med['category']); ?></span></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($med['pharmacy_name'] ?? 'Pharmacy'); ?></td>
                                <td class="text-success fw-bold">Rs. <?= number_format((float)$med['price'], 2); ?></td>
                                <td>
                                    <?php if ($qty <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($qty <= 10): ?>
                                        <span class="badge bg-warning text-dark">Low Stock (<?= $qty; ?> units)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock (<?= $qty; ?> units)</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Form wrapper for row item action -->
                                <?php if ($qty > 0): ?>
                                    <form action="customer_search.php" method="POST" class="m-0 p-0">
                                        <input type="hidden" name="add_to_cart_id" value="<?= $med['id']; ?>">
                                        <input type="hidden" name="search_query" value="<?= htmlspecialchars($find); ?>">
                                        
                                        <td class="text-center">
                                            <div class="quantity-input-group">
                                                <button type="button" class="qty-btn" onclick="decrementQty(this)">-</button>
                                                <input type="number" name="quantity" class="qty-input" value="1" min="1" max="<?= $qty; ?>" readonly>
                                                <button type="button" class="qty-btn" onclick="incrementQty(this, <?= $qty; ?>)">+</button>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button type="submit" class="btn btn-sm btn-outline-success fw-bold">+ Add to Cart</button>
                                        </td>
                                    </form>
                                <?php else: ?>
                                    <td class="text-center text-muted">-</td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-secondary fw-bold" disabled>Unavailable</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No medicines found matching your search. 
                                    <br>
                                    <a href="ai_chat.php" class="btn btn-sm btn-link text-success mt-2">Ask AI Assistant for Alternate Medicine Suggestions (FR8)</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Controls for Quantity +/- -->
<script>
function decrementQty(btn) {
    let input = btn.parentNode.querySelector('.qty-input');
    let val = parseInt(input.value) || 1;
    if (val > 1) {
        input.value = val - 1;
    }
}

function incrementQty(btn, maxStock) {
    let input = btn.parentNode.querySelector('.qty-input');
    let val = parseInt(input.value) || 1;
    if (val < maxStock) {
        input.value = val + 1;
    }
}
</script>

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
include_once '../includes/footer.php';
ob_end_flush();
?>