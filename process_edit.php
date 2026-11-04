<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/auth_check.php';
check_role(['pharmacy']);

$pharmacy_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $medicine_name = trim($_POST['medicine_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? 0);
    $stock_quantity = trim($_POST['stock_quantity'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($id <= 0 || empty($medicine_name) || empty($category)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: edit_medicine.php?id=" . $id);
        exit();
    }

    // Update query restricted to current pharmacy owner
    $stmt = $conn->prepare("UPDATE medicines SET medicine_name = ?, category = ?, price = ?, stock_quantity = ?, description = ? WHERE id = ? AND pharmacy_id = ?");
    $stmt->bind_param("ssdssii", $medicine_name, $category, $price, $stock_quantity, $description, $id, $pharmacy_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Medicine updated successfully!";
        header("Location: manage_inventory.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update medicine. Try again.";
        header("Location: edit_medicine.php?id=" . $id);
        exit();
    }
    $stmt->close();
} else {
    header("Location: manage_inventory.php");
    exit();
}
$conn->close();
?>