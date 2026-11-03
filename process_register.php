<?php

session_start();

require_once('includes/db.php');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']);
    $password = trim($_POST['password']);

    // checking for form validation

    if (empty($full_name) || empty($email) || empty($phone) || empty($role) || empty($password)) {
        $_SESSION['error'] = 'All fields are required!';
        header("Location: register.php");
        exit();
    }

    // Email Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["error"] = "Invalid email formate!";
        header("Location: register.php");
        exit();
    }

    //checking the duplication email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION["error"] = "Email address is already registerd!";
        header("Location: register.php");
        exit();
    }
    $stmt->close();
    //secure password 
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    //Insert new user
    $insert_stmt = $conn->prepare("INSERT INTO users(full_name, email, phone, password, role) VALUES(?,?,?,?,?)");
    $insert_stmt->bind_param("sssss", $full_name, $email, $phone, $hashed_password, $role);
    if ($insert_stmt->execute()) {
        $new_user_id = $conn->insert_id;

        //Role based logic execution
        if ($role == "customer") {
            $wallet_stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?,0.00)");
            $wallet_stmt->bind_param("i", $new_user_id);
            $wallet_stmt->execute();
            $wallet_stmt->close();
        }

        if ($role === 'pharmacy') {
            $pharmacy_stmt = $conn->prepare("INSERT INTO pharmacies (user_id, pharmacy_name, address) VALUES (?,?,?)");
            $pharmacy_name = $full_name . " Pharmacy";
            $default_address = "Address pending update";
            $pharmacy_stmt->bind_param("iss", $new_user_id, $pharmacy_name, $default_address);
            $pharmacy_stmt->execute();
            $pharmacy_stmt->close();
        }

        $_SESSION['success'] = "Account registered successfully! Please login.";
        header("Location: login.php");
        exit();
    } else{
        $_SESSION['error'] = "Registration failed! Please try again.";
        header("Loction: register.php");
        exit();
    }

    $insert_stmt->close();
    $conn->close();
}else{
    header("Location: register.php");
    exit();
}
?>