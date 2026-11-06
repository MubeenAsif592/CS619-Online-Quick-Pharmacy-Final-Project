<?php
// Session status check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includes based on project structure
require_once '../includes/db.php';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Capture Search Query
$find = isset($_GET['search']) ? trim($_GET['search']) : '';
$medicines = [];

// Base Query: JOIN medicines with pharmacies table to fetch pharmacy_name accurately
if (!empty($find)) {
    // Search by Medicine Name, Category, Active Ingredient, or Description (FR3)
    $q = "SELECT m.*, p.pharmacy_name 
          FROM medicines m 
          JOIN pharmacies p ON m.pharmacy_id = p.id 
          WHERE m.medicine_name LIKE ? 
             OR m.category LIKE ? 
             OR m.active_ingredient LIKE ? 
             OR m.description LIKE ? 
          ORDER BY m.price ASC";
          
    $stmt = $conn->prepare($q);
    $like_val = "%" . $find . "%";
    $stmt->bind_param("ssss", $like_val, $like_val, $like_val, $like_val);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    // Default view: Display available medicines sorted by newest
    $q = "SELECT m.*, p.pharmacy_name 
          FROM medicines m 
          JOIN pharmacies p ON m.pharmacy_id = p.id 
          ORDER BY m.id DESC LIMIT 20";
    $res = $conn->query($q);
}

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $medicines[] = $row;
    }
}
?>

<div class="container my-4">
    <!-- Header Title & Dashboard Link -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Search & Compare Medicines</h2>
        <a href="customer_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <!-- Search Form Bar -->
    <div class="card p-3 mb-4 bg-light shadow-sm">
        <form action="customer_search.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-9">
                <input type="text" name="search" class="form-control" placeholder="Search by name, category, ingredient, or symptom (e.g. Panadol, Paracetamol)..." value="<?= htmlspecialchars($find); ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-success w-100 fw-bold">Search</button>
                <?php if (!empty($find)): ?>
                    <a href="customer_search.php" class="btn btn-outline-dark fw-bold">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Table (FR4: Multi-Pharmacy Price Comparison) -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Active Ingredient</th>
                            <th>Pharmacy Name</th>
                            <th>Price (PKR)</th>
                            <th>Stock Status</th>
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
                                <td class="small text-muted"><?= htmlspecialchars($med['active_ingredient'] ?? 'N/A'); ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($med['pharmacy_name'] ?? 'Registered Pharmacy'); ?></td>
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
                                <td class="text-center">
                                    <?php if ($qty > 0): ?>
                                        <a href="#" class="btn btn-sm btn-outline-success fw-bold">Order Now</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary fw-bold" disabled>Unavailable</button>
                                    <?php endif; ?>
                                </td>
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

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
include_once '../includes/footer.php';
?>