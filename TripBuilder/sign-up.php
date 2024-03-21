<?php 
// connect to database 
include "connect-db.php";
connectDB(); 

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

    // validate name 
    $pattern = "/^[A-Za-z\s\.\'-]*$/";
    if (!preg_match($pattern, $_POST['name'])) {
        $nameError = "Can only contain alphabets, white spaces, and special characters -.'";
        $errors += 1;
    } else {
        $name = sanitize($handler, $_POST['name']);
    }

    // validate email 
    // retrieve existing emails from database 
    $query = "SELECT email FROM users";
    $result = mysqli_query($handler, $query);
    $emails = array();
    while($row = mysqli_fetch_array($result)) {
        array_push($emails, $row[0]);
    } 
    $pattern = "/^[^0-9][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[@][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[.][a-zA-Z]{2,4}$/";
    if (!preg_match($pattern, $_POST['email'])) {
        $emailError = "Invalid email format";
        $errors += 1;
    } else if (in_array($_POST['email'], $emails)) { // check if email already taken
        $emailError = "Email is already taken, please use another email";
        $errors += 1;
    } else {
        $email = sanitize($handler, $_POST['email']);
    }

    // validate password 
    $pattern = "/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-~]).{8,}$/";
    if (!preg_match($pattern, $_POST['password'])) {
        $passwordError = "Must contain a minumum of 8 characters, at least one uppercase letter, one lowercase letter, one number, and one special character";
        $errors += 1;
    } else if ($_POST['password'] != $_POST['confirm_password']) {
        $passwordConfirmError = "Passwords do not match";
        $errors += 1;
    } else {
        $password = sanitize($handler, $_POST['password']);
        $password = password_hash($password, PASSWORD_DEFAULT); // hash password
    }

    // if no errors found 
    if ($errors == 0) {
        // insert inputs into users table
        $query = "INSERT INTO users(name, email, password)
                    VALUES('$name', '$email', '$password')";
        mysqli_query($handler, $query);

        // redirect user to login page
        header("Location: login.php?message=registered");
        exit();
    }
}
?>

<!DOCTYPE html>
<html> 
    <head>
        <title>Sign Up</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
    </head>

    <body> 
        <?php include "header.php"; ?>

        <div class="wrapper" style="height: 515px;">
            <div class="title-container">Sign Up</div>
            <div class="signup-div">
                <a href="index.php"><i class="fa-solid fa-xmark"></i></a>
                <form class="signup-form" action="sign-up.php" method="post" name="signup" style="text-align: left">
                    <h2>Let's get you started!</h2>

                    <!--input fields for signup form-->
                    <div class="form-control"> 
                        <label class="form-label">Name</label><br>
                        <input class="signup-info" type="text" id="name" name="name" value="<?php if (isset($_POST['name'])) echo $_POST['name'] ?>" required>
                        <?php
                        if (isset($nameError)) {
                            echo '<div class="form-error">';
                            echo $nameError;
                            echo "</div>";
                            unset($nameError);
                        }
                        ?>
                    </div>
                    <br>

                    <div class="form-control">
                        <label class="form-label">Email</label><br>
                        <input class="signup-info" type="email" id="email" name="email" value="<?php if (isset($_POST['email'])) echo $_POST['email'] ?>" required>
                        <?php
                            echo isset($emailError);
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
                        <input class="signup-info" type="password" id="password" name="password" value="<?php if (isset($_POST['password'])) echo $_POST['password'] ?>" required>
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

                    <div class="form-control">
                        <label class="form-label">Confirm Password</label>
                        <input class="signup-info" type="password" id="confirm_password" name="confirm_password" value="<?php if (isset($_POST['confirm_password'])) echo $_POST['confirm_password'] ?>" required>
                        <?php
                            if (isset($passwordConfirmError)) {
                                echo '<div class="form-error">';
                                echo $passwordConfirmError;
                                echo "</div>";
                                unset($passwordConfirmError);
                            }
                        ?>
                    </div>
                    <br>
                    
                    <button type="submit" class="signup-btn" value="submit">Sign Up</button><br><br>
                    <div class="signup-to-login">
                        Already have an account?&nbsp;&nbsp;<a href="login.php">Log in now!</a>
                    </div>
                </form>
                <div class="signup-img"></div>
            </div>
        </div>
        <div style="height:70px; width:100%"></div>

        <?php include "footer.php";?>
    </body>
</html>