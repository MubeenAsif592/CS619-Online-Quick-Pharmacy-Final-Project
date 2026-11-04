<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once "includes/auth_check.php";
check_role(['pharmacy']);

$pharmacy_id = $_SESSION['user_id'];

// Check if ID is received via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $medicine_id = intval($_POST['id']);

    // Secure Delete: Ensure the medicine belongs to the logged-in pharmacy owner
    $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ? AND pharmacy_id = ?");
    $stmt->bind_param("ii", $medicine_id, $pharmacy_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Medicine deleted from inventory successfully!";
        } else {
            $_SESSION['error'] = "Unauthorized action or medicine not found.";
        }
    } else {
        $_SESSION['error'] = "Database error: Could not delete medicine.";
    }

    $stmt->close();
    $conn->close();

    header("Location: manage_inventory.php");
    exit();
} else {
    header("Location: manage_inventory.php");
    exit();
}
?>