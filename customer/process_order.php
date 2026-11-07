<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Cart Session Handler: Add Item to Cart
if (isset($_GET['add_id'])) {
    $med_id = intval($_GET['add_id']);
    
    // Fetch medicine details
    $stmt =$conn->prepare("SELECT m.*, p.pharmacy_name FROM medicines m JOIN pharmacies p ON m.pharmacy_id = p.id WHERE m.id = ?");
    $stmt->bind_param("i", $med_id);$stmt->execute();
    $res =$stmt->get_result();
    
    if ($res->num_rows > 0) {
        $med =$res->fetch_assoc();
        
        // Cart Structure Array
        if (!isset($_SESSION['cart'])) {$_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$med_id])) {
            $_SESSION['cart'][$med_id]['quantity'] += 1;
        } else {
            $_SESSION['cart'][$med_id] = [
                'id' => $med['id'],
                'pharmacy_id' => $med['pharmacy_id'],
                'pharmacy_name' => $med['pharmacy_name'],
                'name' => $med['medicine_name'],
                'price' => $med['price'],
                'quantity' => 1,
                'stock' => $med['stock_quantity']
            ];
        }
        $_SESSION['success'] = "Item added to cart!";
    }
    $stmt->close();
    header("Location: cart.php");
    exit();
}

// Remove item from cart
if (isset($_GET['remove_id'])) {
    $rem_id = intval($_GET['remove_id']);
    unset($_SESSION['cart'][$rem_id]);$_SESSION['success'] = "Item removed from cart.";
    header("Location: cart.php");
    exit();
}

$cart_items = $_SESSION['cart'] ?? [];$grand_total = 0;
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold">🛒 Shopping Cart</h2>
        <div>
            <a href="customer_search.php" class="btn btn-outline-secondary me-2 fw-bold">Continue Shopping</a>
            <a href="customer_dashboard.php" class="btn btn-outline-dark fw-bold">Dashboard</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Medicine</th>
                            <th>Pharmacy</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($cart_items) > 0): ?>
                            <?php 
                            $sr = 1;
                            foreach ($cart_items as $item):$subtotal = $item['price'] *$item['quantity'];
                                $grand_total +=$subtotal;
                            ?>
                            <tr>
                                <td><?= $sr++; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($item['name']); ?></td>
                                <td class="text-primary fw-semibold"><?= htmlspecialchars($item['pharmacy_name']); ?></td>
                                <td>Rs. <?= number_format($item['price'], 2); ?></td>
                                <td><?= $item['quantity']; ?></td>
                                <td class="fw-bold text-success">Rs. <?= number_format($subtotal, 2); ?></td>
                                <td class="text-center">
                                    <a href="cart.php?remove_id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-danger fw-bold">Remove</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Your cart is currently empty.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (count($cart_items) > 0): ?>
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Grand Total: <span class="text-success">Rs. <?= number_format($grand_total, 2); ?></span></h5>
                <a href="checkout.php" class="btn btn-success btn-lg fw-bold">Proceed to Checkout &rarr;</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>