<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['rider']);

header('Content-Type: application/json');

$rider_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order_id'], $data['lat'], $data['lng'])) {
    $order_id = intval($data['order_id']);
    $lat = floatval($data['lat']);
    $lng = floatval($data['lng']);

    $stmt = $conn->prepare("UPDATE orders SET rider_lat = ?, rider_lng = ? WHERE id = ? AND rider_id = ?");
    $stmt->bind_param("ddii", $lat, $lng, $order_id, $rider_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Location updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
}
$conn->close();