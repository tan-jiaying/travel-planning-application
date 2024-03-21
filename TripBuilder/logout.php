<?php
session_start(); 

if (isset($_SESSION['email'])) {
    // unset session variables
    unset($_SESSION['email']);

    // destroy session
    $_SESSION = array();
    session_unset();
    session_destroy();

    // redirect user to login page 
    header("Location: login.php?message=logout");
    exit();
}
?>