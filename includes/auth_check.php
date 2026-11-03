<!-- //eh middleware check karega ke user logged in hai ya nahi aur unauthorized access ko block karke login.php par bhej dega. -->

<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}
function check_login(){
    if(!isset($_SESSION["user_id"])){
        $_SESSION['error'] = "Please login to access this page.";
        header("Location: login.php");
        exit();
    }
}

function check_role($allowed_roles = []){
    check_login();
    if(!in_array($_SESSION['user_role'], $allowed_roles)){
        $_SESSION['error'] = "Unauthorized access!";
        header("Location: login.php");
        exit();
    }
}
?>