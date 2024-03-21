<?php
// connect to database 
include "connect-db.php";
connectDB(); 

if (!isset($_SESSION)) session_start();

// callback message from other pages
if (isset($_GET['message'])) {
    $message = $_GET['message'];

    // display corresponding message
    if ($message == "registered") {
        echo "<div id='message-div'><i class='fa-regular fa-circle-check'></i><br>";
        echo "<h3 style='margin-top:20px'>You have successfully registered!</h3></div>";
    } else if ($message == "logout") {
        echo "<div id='message-div'><i class='fa-regular fa-circle-check'></i><br>";
        echo "<h3 style='margin-top:20px'>You have successfully log out.</h3></div>";
    } else if ($message == "notLoggedIn") {
        echo "<div id='message-div'><i class='fa-solid fa-right-to-bracket'></i><br>";
        echo "<h3 style='margin-top:20px'>Please log in to access Trips page.</h3></div>";
    }
}

// check if form is submitted 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // perform form validation
    $errors = 0; // error count

    // function to sanitize input
    function sanitize($handler, $input) {
        $input = trim($input);
        $input = htmlentities($input);
        return mysqli_real_escape_string($handler, $input);
    }

    // validate email and password 
    // retrieve existing emails from database 
    $query = "SELECT email FROM users";
    $result = mysqli_query($handler, $query);
    $emails = array();
    while ($row = mysqli_fetch_array($result)) {
        array_push($emails, $row[0]);
    }
    if (!in_array($_POST['email'], $emails)) { // check if email exists
        $emailError = "Email does not exist, please enter a registered email";
        $errors += 1;
    } else { // validate password
        $email = sanitize($handler, $_POST['email']);
        $password = sanitize($handler, $_POST['password']);

        // retrieve password of provided email from database 
        $query = "SELECT password FROM users WHERE email='$email'";
        $result = mysqli_query($handler, $query);
        while ($row = mysqli_fetch_array($result)) {
            $hash = $row[0];
        }

        // check if password is correct 
        if (!password_verify("$password", "$hash")) {
            $passwordError = "Incorrect password, please try again";
            $errors += 1;
        }
    }

    // if no errors found 
    if ($errors == 0) {
        // start session
        if (!isset($_SESSION)) session_start();
        $_SESSION['email'] = $email;

        // retrieve name from database 
        $query = "SELECT name FROM users WHERE email='$email'";
        $result = mysqli_query($handler, $query);
        while ($row = mysqli_fetch_array($result)) {
            $name = $row[0];
        }
        $_SESSION['name'] = $name;

        // redirect user 
        if (isset($_SESSION['addExplore']) && $_SESSION['addExplore'] == true) {
            header("Location: explore.php");
        } else if (isset($_SESSION['addLocationDetails']) && $_SESSION['addLocationDetails'] == true) {
            $placeID = $_SESSION['placeID'];
            $category = $_SESSION['category'];
            header("Location: location-details.php?placeID=$placeID&category=$category");
        } else {
            header("Location: trips.php");
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html> 
    <head>
        <title>Log In</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
    </head>

    <body> 
        <?php include "header.php"; ?>

        <div class="wrapper" style="height: 515px">
            <div class="title-container">Log In</div>
            <div class="login-div">
                <a href="index.php"><i class="fa-solid fa-xmark"></i></a>
                <form class="signup-form" action="login.php" method="post" name="login" style="text-align: left">
                    <h2>Welcome back!</h2>

                    <!--input fields for login form-->
                    <div class="form-control">
                        <label class="form-label">Email</label>
                        <input class="login-info" type="email" id="email" name="email" value="<?php if (isset($_POST['email'])) echo $_POST['email'] ?>" required>
                        <?php
                            if (isset($emailError)) {
                                echo '<div class="form-error">';
                                echo $emailError;
                                echo "</div>";
                                unset($emailError);
                            }
                        ?>
                    </div>
                    <br> 

                    <div class="form-control">
                        <label class="form-label">Password</label>
                        <input class="login-info" type="password" id="password" name="password" value="<?php if (isset($_POST['password'])) echo $_POST['password'] ?>" required>
                        <?php
                            if (isset($passwordError)) {
                                echo '<div class="form-error">';
                                echo $passwordError;
                                echo "</div>";
                                unset($passwordError);
                            }
                        ?>
                    </div>
                    <br>

                    <button type="submit" class="login-btn" value="submit">Log In</button><br><br>
                    <div class="signup-to-login">
                        Don't have an account?&nbsp;&nbsp;<a href="sign-up.php">Sign up now!</a>
                    </div>
                </form>
                <div class="login-img"></div>
            </div>
        </div>
        <div style="height:70px; width:100%"></div>

        <?php include "footer.php";?>
        
        <script> 
            // set fading effect for message div 
            let div = document.getElementById("message-div");
            setTimeout(() => {
                div.classList.add("fadeout-effect");
            }, 2000); 
            setTimeout(() => {
                div.style.display = "none";
            }, 3000);
        </script>
    </body>
</html>