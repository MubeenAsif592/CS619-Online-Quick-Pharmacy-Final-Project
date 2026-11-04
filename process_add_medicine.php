<?php 
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "includes/auth_check.php";
check_role(["pharmacy"]);
require_once "includes/db.php";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $pharmacy_id = $_SESSION['user_id'];
    $medicine_name = trim($_POST['medicine_name']);
    $category = trim($_POST['category']);
    $price = trim($_POST['price']);
    $stock_quantity = trim($_POST['stock_quantity']);
    $description = trim($_POST['description']);

    if(empty($medicine_name) || empty($category) || empty($price) || empty($stock_quantity)){
    $_SESSION['error'] = 'Please fill in all required fields!';
    header("Location: add_medicine.php");
    exit();
}
//'$conn->prepare()' = SQL query ko compile karta hai security maintain rakhne ke liye
$stmt = $conn->prepare("INSERT INTO medicines (pharmacy_id, medicine_name, category, price, stock_quantity, description ) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("issdis", $pharmacy_id, $medicine_name, $category, $price, $stock_quantity, $description);

//Execute query check
if($stmt->execute()){
    $_SESSION['success'] = "Medicine added to inventory successfully!";
    header("Location: manage_inventory.php");
    exit();
}else{
    $_SESSION['error'] = "Database error: Could not add medicine.";
    header("Location: add_medicine.php");
    exit();
}
$stmt->close();
$conn->close();

} else{
    //Direct URL access prevention
    header("Location: add_medicine.php");
    exit();
}

?>