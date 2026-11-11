<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$customer_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT o.order_status, o.rider_lat, o.rider_lng, u.full_name AS rider_name, u.phone AS rider_phone 
                        FROM orders o 
                        LEFT JOIN users u ON o.rider_id = u.id 
                        WHERE o.id = ? AND o.customer_id = ?");
$stmt->bind_param("ii", $order_id, $customer_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'status' => 'success',
        'order_status' => $row['order_status'],
        'rider_name' => $row['rider_name'] ?? 'Assigned Rider',
        'rider_phone' => $row['rider_phone'] ?? 'N/A',
        'lat' => $row['rider_lat'] ? floatval($row['rider_lat']) : null,
        'lng' => $row['rider_lng'] ? floatval($row['rider_lng']) : null
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
}
$stmt->close();
$conn->close();