<?php 
ob_start();
date_default_timezone_set('Asia/Singapore');

if (!isset($_SESSION)) session_start();
$email = $_SESSION['email'];
$name = $_SESSION['name'];

// connect to database 
include "connect-db.php";
connectDB(); 

// retrieve user details from database
$query = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($handler, $query);
$details = mysqli_fetch_assoc($result);
$startTime = $details['startTime'];
$endTime = $details['endTime'];
$attractionStay = $details['attractionStay'];
$eateryStay = $details['eateryStay'];
$breakfastTime = $details['breakfastTime'];
$lunchTime = $details['lunchTime'];
$dinnerTime = $details['dinnerTime'];
$passwordChanged = false;
$detailsUpdated = false;

$_SESSION['currentStartTime'] = $startTime;
$_SESSION['currentEndTime'] = $endTime;
$_SESSION['currentAttractionStay'] = $attractionStay;
$_SESSION['currentEateryStay'] = $eateryStay;
$_SESSION['currentBreakfastTime'] = $breakfastTime;
$_SESSION['currentLunchTime'] = $lunchTime;
$_SESSION['currentDinnerTime'] = $dinnerTime;

// display successful update message
if (isset($_GET['message'])) {
    $detailsUpdated = true;
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

    // preferred duration of stay already validated in form itself
    $attractionStay = $_POST['attraction'];
    $eateryStay = $_POST['eatery'];

    // validate name 
    $pattern = "/^[A-Za-z\s\.\'-]*$/";
    if (!preg_match($pattern, $_POST['name'])) {
        $nameError = "Can only contain alphabets, white spaces, and special characters -.'";
        $errors += 1;
    } else {
        $name = sanitize($handler, $_POST['name']);
    }

    // validate new password 
    if (!empty($_POST['password'])) {
        $passwordChanged = true;
        $pattern = "/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-~]).{8,}$/";
        if (!preg_match($pattern, $_POST['password'])) {
            $passwordError = "Must contain a minumum of 8 characters, at least one uppercase letter, one lowercase letter, one number, and one special character";
            $errors += 1;
        } else if (empty($_POST['confirm_password'])) {
            $passwordConfirmError = "Please confirm password";
            $errors += 1;
        } else if ($_POST['password'] != $_POST['confirm_password']) {
            $passwordConfirmError = "Passwords do not match";
            $errors += 1;
        } else {
            $password = sanitize($handler, $_POST['password']);
            $password = password_hash($password, PASSWORD_DEFAULT); // hash password
        }
    }

    // validate end time
    if ($_POST['end-time'] <= $_POST['start-time'])  {
        $endTimeError = "End time must be after start time";
        $errors += 1;
    } else {
        $startTime = $_POST['start-time'];
        $endTime = $_POST['end-time'];
    }

    // validate breakfast time
    $breakfast_test = (string)$_POST['breakfast'];
    $breakfast_test = str_replace(":", "", $breakfast_test); // remove all :
    if (strlen($breakfast_test) > 4) {
        $breakfast_test = substr($breakfast_test, 0, -2); // remove seconds
    }
    $breakfast_test = (int)$breakfast_test;

    if ($breakfast_test < 600 || $breakfast_test > 1100) {
        $breakfastError = "Breakfast time must be within 6:00 AM to 10:00 AM";
        $errors += 1;
    } else if ($_POST['breakfast'] < $_POST['start-time']) { // check if breakfast time is before start time 
        $breakfastError = "Breakfast time cannot be before start time";
        $errors += 1;
    } else {
        $breakfastTime = $_POST['breakfast'];
    }

    // validate lunch time
    $lunch_test = (string)$_POST['lunch'];
    $lunch_test = str_replace(":", "", $lunch_test); // remove all :
    if (strlen($lunch_test) > 4) {
        $lunch_test = substr($lunch_test, 0, -2); // remove seconds
    }
    $lunch_test = (int)$lunch_test;

    if ($lunch_test < 1100 || $lunch_test > 1500) {
        $lunchError = "Lunch time must be within 11:00 AM to 3:00 PM";
        $errors += 1;
    } else {
        $lunchTime = $_POST['lunch'];
    }

    // validate dinner time
    $dinner_test = (string)$_POST['dinner'];
    $dinner_test = str_replace(":", "", $dinner_test); // remove all :
    if (strlen($dinner_test) > 4) {
        $dinner_test = substr($dinner_test, 0, -2); // remove seconds
    }
    $dinner_test = (int)$dinner_test;

    $latestTime = new DateTime($_POST['end-time']);
    $latestTime->modify("-$eateryStay hours");
    $latestTime = $latestTime->format('H:i:s');
    
    if ($dinner_test < 1700 || $dinner_test > 2100) {
        $dinnerError = "Dinner time must be within 5:00 PM to 9:00 PM";
        $errors += 1;
    } else if ($_POST['dinner'] > $latestTime) { // check if dinner time is (end time - eatery stay)
        $dinnerError = "Dinner time must be at least ". $eateryStay ." hour(s) before end time";
        $errors += 1;
    } else {
        $dinnerTime = $_POST['dinner'];
    }

    // if no errors found
    if ($errors == 0) {
        // update user details 
        $query = "UPDATE users SET
                    name = '" . $name . "', 
                    startTime = '" . $startTime . "', 
                    endTime = '" . $endTime . "',
                    attractionStay = '" . $attractionStay . "', 
                    eateryStay = '" . $eateryStay . "', 
                    breakfastTime = '" . $breakfastTime . "', 
                    lunchTime = '" . $lunchTime . "', 
                    dinnerTime = '" . $dinnerTime . "'
                WHERE email='$email'";
        mysqli_query($handler, $query);

        // change session name if name is edited 
        if (isset($_POST['name'])) {
            $_SESSION['name'] = $name;
        }

        // update password
        if (!empty($_POST['password'])) {
            $query = "UPDATE users SET password='$password' WHERE email='$email'";
            mysqli_query($handler, $query);
        }

        // reset distance matrix and itinerary if any preferences are updated 
        if ($startTime !== $_SESSION['currentStartTime'] || $endTime !== $_SESSION['currentEndTime'] || $attractionStay !== $_SESSION['currentAttractionStay'] || $eateryStay !== $_SESSION['currentEateryStay'] 
            || $breakfastTime !== $_SESSION['currentBreakfastTime'] || $lunchTime !== $_SESSION['currentLunchTime'] || $dinnerTime !== $_SESSION['currentDinnerTime']) {
            $query = "UPDATE trips SET
                distanceMatrix = NULL,
                itinerary = NULL
            WHERE userEmail='$email'";
            mysqli_query($handler, $query);
        }

        // refresh page
        header("Location: profile.php?message=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Profile</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
    </head>

    <body>
        <?php include "header.php"; ?>

        <div class="wrapper" style="height: 75%;">
            <div class="title-container">Profile</div>
            <div class="profile-div">
                <a href="index.php"><i class="fa-solid fa-xmark"></i></a>
                 <!--display successful message-->
                 <div id="updated-div" style="display: none;">
                    <i class="fa-regular fa-circle-check"></i><br>
                    <h3 style="margin-top: 20px;">Profile details have been updated.</h3>
                </div>
                
                <!--form for user to update profile-->
                <form action="profile.php" id="profile-form" method="post">
                    <table class="profile-table"> 
                        <tr>
                            <td style="color: #4178A4; vertical-align: top; text-align: right; transform: translate(-50px, 0px);"><b>Personal Information</b></td>
                            <td>
                                <div class="form-control"> 
                                    <label class="label">Name</label><br>
                                    <input class="update-info" type="text" id="name" name="name" value="<?php if (isset($_POST['name'])) echo $_POST['name']; else echo $name; ?>" required>
                                    <?php
                                    if (isset($nameError)) {
                                        echo '<div class="form-error">';
                                        echo $nameError;
                                        echo "</div>";
                                        unset($nameError);
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>
                            
                        <tr>
                            <td></td>
                            <td>
                                <div class="form-control"> 
                                    <label class="label">Email</label><br>
                                    <input class="update-info" type="text" id="email" name="email" value="<?php echo $email;?>" disabled>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td></td>
                            <td>
                                <input onclick="showPasswordDiv()" id="password-btn" type="button" value="Change Password">
                                <div id="password-div">
                                    <div class="form-control" style="margin-top: -20px;"> 
                                        <label class="label">New Password</label>
                                        <input class="update-info" type="password" id="password" name="password" value="<?php if (isset($_POST['password'])) echo $_POST['password'] ?>">
                                        <?php
                                        if (isset($passwordError)) {
                                            echo '<div class="form-error" style="width: 388px;">';
                                            echo $passwordError;
                                            echo "</div>";
                                            unset($passwordError);
                                        }
                                        ?>
                                    </div>

                                    <div class="form-control" style="margin-top: 18px; margin-bottom: 5px;"> 
                                        <label class="label">Confirm New Password</label>
                                        <input class="update-info" type="password" id="confirm_password" name="confirm_password" value="<?php if (isset($_POST['confirm_password'])) echo $_POST['confirm_password'] ?>">
                                        <?php
                                        if (isset($passwordConfirmError)) {
                                            echo '<div class="form-error">';
                                            echo $passwordConfirmError;
                                            echo "</div>";
                                            unset($passwordConfirmError);
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td style="color: #4178A4; vertical-align: top; text-align: right; transform: translate(-50px, 0px);"><b>Preferences</b></td>
                            <td>
                                <div class="time-div"> 
                                    <label class="label">What time would you like to start and end your trip each day?</label><br>
                                    <div class="form-control" style="float: left;">
                                        <input class="update-info" type="time" id="start-time" name="start-time" style="width: 110px; padding-right: 10px;" value="<?php if (isset($_POST['start-time'])) echo $_POST['start-time']; else echo $startTime; ?>" required>
                                        <label class="sub-label">&nbsp;&ndash;</label>
                                    </div>
                                    <div class="form-control" style="float: right; transform: translate(-172px, 0px);">
                                        <input class="update-info" type="time" id="end-time" name="end-time" style="width: 110px; padding-right: 10px;" value="<?php if (isset($_POST['end-time'])) echo $_POST['end-time']; else echo $endTime; ?>" required>
                                        <?php
                                        if (isset($endTimeError)) {
                                            echo '<div class="form-error" style="width: 189px;">';
                                            echo $endTimeError;
                                            echo "</div>";
                                            unset($endTimeError);
                                        }
                                        ?>
                                    </div>
                                    <div style="clear: both;"></div>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td></td>
                            <td>
                                <div class="form-control"> 
                                    <label class="label">How long would you like to stay at each location?</label><br>
                                    <label class="sub-label">Attraction:</label>
                                    <input class="update-info" type="number" id="attraction" name="attraction" min="1" max="6" style="width: 36px; margin-left: 25px; border-radius: 10px;" value="<?php if (isset($_POST['attraction'])) echo $_POST['attraction']; else echo $attractionStay; ?>" required>&nbsp;&nbsp;hour(s)<br>
                                    <label class="sub-label">Eatery:</label>
                                    <input class="update-info" type="number" id="eatery" name="eatery" min="1" max="3" style="width: 36px; margin-left: 46.5px; border-radius: 10px;" value="<?php if (isset($_POST['eatery'])) echo $_POST['eatery']; else echo $eateryStay; ?>" required>&nbsp;&nbsp;hour(s)<br>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td></td>
                            <td>
                                <div class="form-control"> 
                                    <label class="label">What are your preferred mealtimes?</label><br>
                                    <label class="sub-label">Breakfast:</label>
                                    <input class="update-info" type="time" id="breakfast" name="breakfast" style="width: 110px; padding-right: 10px; margin-left: 19px;" value="<?php if (isset($_POST['breakfast'])) echo $_POST['breakfast']; else echo $breakfastTime; ?>" required>
                                    <?php
                                        if (isset($breakfastError)) {
                                            echo '<div class="form-error">';
                                            echo $breakfastError;
                                            echo "</div>";
                                            unset($breakfastError);
                                        }
                                    ?>

                                    <br>
                                    <label class="sub-label">Lunch:</label>
                                    <input class="update-info" type="time" id="lunch" name="lunch" style="width: 110px; padding-right: 10px; margin-left: 43px;" value="<?php if (isset($_POST['lunch'])) echo $_POST['lunch']; else echo $lunchTime; ?>" required>
                                    <?php
                                        if (isset($lunchError)) {
                                            echo '<div class="form-error">';
                                            echo $lunchError;
                                            echo "</div>";
                                            unset($lunchError);
                                        }
                                    ?>

                                    <br>
                                    <label class="sub-label">Dinner:</label>
                                    <input class="update-info" type="time" id="dinner" name="dinner" style="width: 110px; padding-right: 10px; margin-left: 39px;" value="<?php if (isset($_POST['dinner'])) echo $_POST['dinner']; else echo $dinnerTime; ?>" required>
                                    <?php
                                        if (isset($dinnerError)) {
                                            echo '<div class="form-error">';
                                            echo $dinnerError;
                                            echo "</div>";
                                            unset($dinnerError);
                                        }
                                    ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td></td>
                            <td><input class="update-btn" type="submit" value="Save Changes"></td>
                        </tr>
                    </table>
                </form>
            </div>
        </div>

        <?php include "footer.php";?>

        <script>
            // display password div 
            function showPasswordDiv() {
                let div = document.getElementById("password-div");
                if (div.style.display === "block") {
                    div.style.display = "none";
                } else {
                    div.style.display = "block";
                }
            }

            let passwordChanged = "<?php echo $passwordChanged?>";
            if (passwordChanged) {
                document.getElementById("password-div").style.display = "block";
            }

            // display successful message
            let message = document.getElementById("updated-div");
            let detailsUpdated = "<?php echo $detailsUpdated?>";
            if (detailsUpdated) {
                message.style.display = "block";
            }

            // set fading effect for message div 
            if (message.style.display === "block") {
                setTimeout(() => {
                    message.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    message.style.display = "none";
                }, 3000);
            }
        </script>
    </body>
</html>