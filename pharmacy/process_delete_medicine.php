<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/auth_check.php";
check_role(['pharmacy']);

$user_id = $_SESSION['user_id'];

// Check if ID is received via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $medicine_id = intval($_POST['id']);

    // Get pharmacy_id
    $p_stmt = $conn->prepare("SELECT id FROM pharmacies WHERE user_id = ?");
    $p_stmt->bind_param("i", $user_id);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result();
    $pharmacy_id = ($p_res->num_rows > 0) ? $p_res->fetch_assoc()['id'] : 0;
    $p_stmt->close();

    // Secure Delete
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