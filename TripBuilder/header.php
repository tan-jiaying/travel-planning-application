<?php 
ob_start();
date_default_timezone_set('Asia/Singapore');

if (!isset($_SESSION)) session_start();
?>

<!DOCTYPE html>
<html> 
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" type="text/css" href="css/header.css">
        <link rel="stylesheet" type="text/css" href="css/main-content.css">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
    </head>

    <body>
        <!-- navigation bar -->
        <div id="home" class="nav-bar" style="overflow: visible;">
            <nav class="nav-box">
                <a class="logo-text" href="index.php">
                    <i class="fa-solid fa-person-walking-luggage"></i>
                    <b>TripBuilder</b>
                </a>
                <?php
                // display name if logged in  
                if (isset($_SESSION['email'])) {
                    echo "<button class='account-btn' onclick='showAccountBox()'>";
                    echo "<i class='fa-solid fa-user'></i>&emsp;";
                    echo $_SESSION['name'];
                    echo "</button>";
                } else {
                    echo "<a class='nav-login' href='login.php'><button id='login-btn' class='nav-login'>Log In</button></a>";
                    echo "<a class='nav-login' href='sign-up.php'><span class='signup-text'>Sign Up</span></a>";
                }
                ?>

                <a class="nav-list" href="index.php" style="margin-left: 260px;">Home<div id="nav-home"></div></a>
                <a class="nav-list" href="explore.php">Explore<div id="nav-explore"></div></a>
                <a class="nav-list" href="trips.php">Trips<div id="nav-trips"></div></a>

                <!--account box-->
                <div id="account-box" style="display: none;">
                    <div class="up-arrow"></div>
                    <a href="profile.php">Profile</a>
                    <a href="logout.php" style="margin-top: -2px;">Log Out</a>
                </div>
            </nav>
        </div> 

        <script>
            // display account box
            function showAccountBox() {
                let accountBox = document.getElementById("account-box");
                if (accountBox.style.display === "none") {
                    accountBox.style.display = "block";
                } else {
                    accountBox.style.display = "none";
                }
            }
        </script>
    </body>
</html>