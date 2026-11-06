<?php
    if(session_status()==PHP_SESSION_NONE){
    session_start();
    }
    require_once("includes/db.php");
    if($_SERVER["REQUEST_METHOD"] === "POST"){

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if(empty($email) || empty($password)){
            $_SESSION['error'] = "Please fill in all fields.";
            header("Location: login.php");
            exit();
        }
    

    // fetching user record via prepared statment

    $stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $user = $result->fetch_assoc();
        
        // Password hash verfication 
        if(password_verify($password, $user['password'])){
            // Password Correct -> Set Userr session date
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            //Role based redirection routing

            if($user['role'] === 'pharmacy'){
                header("Location: pharmacy/pharmacy_dashboard.php");
                exit();
            }elseif($user['role'] === 'rider'){
                header("Location: rider/rider_dashboard.php");
                exit();
            }elseif($user['role'] === "admin"){
                header("Location: admin/admin_dashboard.php");
                exit();
            }elseif($user['role'] === "customer"){
                header("Location: customer/customer_dashboard.php");
                exit();
            }
            else{
                header("Location: index.php");
                exit();
            }
        }
        else{
            //password mismatch error
            $_SESSION['error'] = "Invalid password!";
            header("Location: login.php");
            exit();
        }
       
    } else{
            //email not found error
            $_SESSION['error'] = "No account found with this email";
            header("Location: login.php");
            exit();
        }
        $stmt->close();
        $conn->close();
       } else{
                header("Location: login.php");
                exit();
        }
?>