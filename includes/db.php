<?php
//database configuration parameters
$host = "localhost";
$username = "root";
$password = "";
$dbname = "quick_pharmacy_db";

//Establish database connection
$conn = new mysqli($host,$username,$password,$dbname);

//connection verfication check
if($conn->connect_error){
    die("Database Connection Failed:" . $conn->connect_error);
}

//Set UFT-8 ENCODINGN
$conn->set_charset("utf8mb4");

?>