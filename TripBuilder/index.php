<?php 
ob_start();
date_default_timezone_set('Asia/Singapore');

// connect to database 
include "connect-db.php";
connectDB(); 

if (!isset($_SESSION)) session_start();
if (isset($_SESSION['searchInput'])) {
    unset($_SESSION['searchInput']);
}
if (isset($_SESSION['destination'])) {
    unset($_SESSION['destination']);
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

    // validate destination 
    $pattern = "/^[A-Za-z0-9\s\-_,\.;:()\'\&@]+$/";
    if (!preg_match($pattern, $_POST['destination'])) {
        $destinationError = "Can only contain alphabets, numbers, white spaces, and special characters -_,.:;()'&@";
        $errors += 1;
    } else {
        $destination = sanitize($handler, $_POST['destination']);
    }

    // validate start and end date
    if (!empty($_POST['start-date']) && !empty($_POST['end-date'])) {
        // calculate number of days from start to end date 
        $startDateObj = new DateTime($_POST['start-date']);
        $endDateObj = new DateTime($_POST['end-date']); 
        $interval = $startDateObj->diff($endDateObj);
        $numOfDays = $interval-> days + 1;

        // check if number of days exceeds maximum of 6
        if ($numOfDays > 6) {
            $endDateError = "Only a maximum number of 6 days is allowed";
            $errors += 1;
        } else if ($_POST['end-date'] <= $_POST['start-date']) {
            $endDateError = "End date must be after start date";
            $errors += 1;
        } else {
            $startDate = $_POST['start-date'];
            $endDate = $_POST['end-date'];
        }   
    }

    // if no errors found
    if ($errors == 0) {
        // redirect user to explore page
        header("Location: explore.php?destination=$destination&startDate=$startDate&endDate=$endDate");
        exit();
    }
}    
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Home</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
        <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&key=AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM"></script>
    </head>

    <body>
        <?php include "header.php"; ?>

        <div class="wrapper" style="height: 450px;">
            <span id="discover-text">Discover. Plan. Wander.</span><br>
            <span id="passport-text">Your passport to seamleass adventures.</span>
            <p id="crafting-text">Crafting tailored itineraries for every wanderer's dream.</p>

            <!--form for travel destination and date-->
            <form id="home-form" action="index.php" method="post"> 
                <div class="form-control">
                    <input class="form-input destination" type="text" id="destination" name="destination" value="<?php if (isset($_POST['destination'])) echo $_POST['destination']; ?>" placeholder="Where would you like to go to?" required>
                    <i class="fa-solid fa-magnifying-glass" style="color: #90CAF9; font-size: 16px; transform: translate(-38px, -1px);"></i>
                    <?php
                        if (isset($destinationError)) {
                            echo '<div class="form-error" style="transform: translate(-20px, 38px); width: 370px; text-align: left;">';
                            echo $destinationError;
                            echo "</div>";
                            unset($destinationError);
                        }
                    ?>
                </div>
          
                <div id="date-div">
                    <div class="form-control" style="float: left;">
                        <input class="form-input" type="text" id="start-date" name="start-date" placeholder="Start Date" onfocus="(this.type='date')" min="<?php echo date('Y-m-d');?>" value="<?php if (isset($_POST['start-date'])) echo $_POST['start-date']; ?>" required>
                    </div>
                    <i class="fa-solid fa-arrow-right-long"></i>
                    <div class="form-control" style="float: right;">
                        <input class="form-input" type="text" id="end-date" name="end-date" placeholder="End Date" onfocus="(this.type='date')" min="<?php echo date('Y-m-d');?>" value="<?php if (isset($_POST['end-date'])) echo $_POST['end-date']; ?>" required>
                        <?php
                            if (isset($endDateError)) {
                                echo '<div class="form-error" style="transform: translate(-75px, 20px);">';
                                echo $endDateError;
                                echo "</div>";
                                unset($endDateError);
                            }
                        ?>
                    </div>
                    <div style="clear: both;"></div>
                </div>

                <a><button id="plan-button" type="submit">Start Planning</button></a>
            </form>
        </div>

        <?php include "footer.php";?>

        <script>
            window.onload = underlineHome();
            function underlineHome() {
                let div = document.getElementById("nav-home");
                div.style.display = "block";
            }

            // change placeholder text on click/focus
            let searchBar = document.getElementById("destination");
            function changeText() {
                searchBar.setAttribute ("placeholder", "Enter a city or region");
            }
            searchBar.addEventListener("click", changeText);
            searchBar.addEventListener("focus", changeText);
            searchBar.addEventListener("blur", function () {
                searchBar.setAttribute ("placeholder", "Where would you like to go to?");
            });

            // location autocomplete using google places API
            $(document).ready(function() {
                let autocomplete = new google.maps.places.Autocomplete((document.getElementById("destination")), {
                    types: ['(regions)']
                });
                google.maps.event.addListener(autocomplete, 'place_changed', function() {
                    let place = autocomplete.getPlace();
                });
            });
        </script>
    </body>
</html>