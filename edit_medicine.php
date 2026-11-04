<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/auth_check.php';
check_role(['pharmacy']);

$pharmacy_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_inventory.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM medicines WHERE id = ? AND pharmacy_id = ?");
$stmt->bind_param("ii", $id, $pharmacy_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: manage_inventory.php");
    exit();
}

$medicine = $result->fetch_assoc();
$stmt->close();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- Using the exact same CSS stylesheet as Add Medicine -->
<link rel="stylesheet" href="Style/add_medicine.css">

<div class="container my-5">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert-error">
            <span><?= htmlspecialchars($_SESSION['error']); ?></span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-header">
            <h3>💊 Edit Medicine Record</h3>
            <a href="manage_inventory.php" class="btn-back">Back to Hub</a>
        </div>
        
        <form action="process_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= $medicine['id']; ?>">

            <div class="form-group">
                <label for="medicine_name">Medicine Name <span class="required">*</span></label>
                <input type="text" name="medicine_name" id="medicine_name" value="<?= htmlspecialchars($medicine['medicine_name']); ?>" required>
            </div>

            <div class="form-row">
                <!-- dropdown / input category matching add medicine -->
                <div class="flex-2">
                    <label for="category">Category <span class="required">*</span></label>
                    <input type="text" name="category" id="category" value="<?= htmlspecialchars($medicine['category']); ?>" required>
                </div>

                <div class="flex-1">
                    <label for="price">Price (PKR) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="price" id="price" min="0.01" value="<?= htmlspecialchars($medicine['price']); ?>" required>
                </div>

                <div class="flex-1">
                    <label for="stock_quantity">Stock <span class="required">*</span></label>
                    <input type="number" name="stock_quantity" id="stock_quantity" min="0" value="<?= htmlspecialchars($medicine['stock_quantity']); ?>" required>
                </div>
            </div>

            <!-- Description textarea -->
            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <textarea name="description" id="description" rows="3" maxlength="500"><?= htmlspecialchars($medicine['description']); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">Update Medicine</button>
        </form>
    </div>
</div>

<?php 
$conn->close();
require_once 'includes/footer.php'; 
?>