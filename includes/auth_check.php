<!-- yeh middleware check karega ke user logged in hai ya nahi aur unauthorized access ko block karke login.php par bhej dega. -->

<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

//Dynamic login patch detector for root or subfolders
function get_login_path(){
    $current_dir = dirname($_SERVER['PHP_SELF']);
    if(preg_match('/\\/(customer|pharmacy|rider|admin)(\\/|$)/',$current_dir)){
        return '../login.php';
    }
    return 'login.php';
}

function check_login(){
    if(!isset($_SESSION["user_id"])){
        $_SESSION['error'] = "Please login to access this page.";
        $login_path = get_login_path();
        header("Location: $login_path");
        exit();
    }
}

function check_role($allowed_roles = []){
    check_login();
    if(!in_array($_SESSION['user_role'], $allowed_roles)){
        $_SESSION['error'] = "Unauthorized access!";
        $login_path = get_login_path();
        header("Location: $login_path");
        exit();
    }
}
?>