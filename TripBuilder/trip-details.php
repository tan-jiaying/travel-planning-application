<?php 
ob_start();
date_default_timezone_set('Asia/Singapore');

if (!isset($_SESSION)) session_start();
$email = $_SESSION['email'];

// connect to database 
include "connect-db.php";
connectDB(); 

// retrieve user details from database
$query = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($handler, $query);
$details = mysqli_fetch_assoc($result);
$startTime = $details['startTime'];
$endTime = $details['endTime'];

// initalize variables
$tripID = 0;
$tripUpdated = false; 
$fromExplore = false;
$enableLocations = false; 
$enableItinerary = false;

// retrieve GET variable
if (isset($_GET['message'])) {
    $tripUpdated = true; 
} else {
    $tripUpdated = false; 
}

if (isset($_GET['fromExplore'])) {
    $fromExplore = true;
} else {
    $fromExplore = false;
}

if (isset($_GET['location'])) {
    $_SESSION['location'] = $_GET['location'];
    $_SESSION['placeID'] = $_GET['placeID'];
    $_SESSION['category'] = $_GET['category'];
} 
if (isset($_SESSION['location'])) {
    $locationName = $_SESSION['location'];
}

// retrieve trip details from database 
if (isset($_GET['tripID']) && ($_GET['tripID'] == "0")) {
    $tripID = 0;
}
if (isset($_GET['tripID']) && ($_GET['tripID'] !== "0")) { // passed from trips page
    $tripID = $_GET['tripID'];
    $_SESSION['tripID'] = $tripID;
    $query = "SELECT * FROM trips WHERE tripID = $tripID";
    $result = mysqli_query($handler, $query);
    $trip = mysqli_fetch_assoc($result);

    $name = $trip['name'];
    $destination = $trip['destination'];
    $startDate = $trip['startDate'];
    $endDate = $trip['endDate'];
    $accomodation = $trip['accomodation'];
    $imageFile = $trip['imageFile'];
    $imageDirectory = $trip['imageDirectory'];

    $_SESSION['currentStartDate'] = $startDate;
    $_SESSION['currentEndDate'] = $endDate;
    $_SESSION['currentAccomodation'] = $accomodation;

    // enable Locations tab if waypoints are selected
    if (!is_null($trip['waypoints'])) {
        $waypoints = explode('_ ', $trip['waypoints']);
        if (count($waypoints) > 0) {
            $enableLocations = true;
        } 
    }

    // enable Itinerary tab if itinerary has been created
    if (!is_null($trip['itinerary'])) {
        $enableItinerary = true;
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

    // validate name 
    $pattern = "/^[A-Za-z0-9\s\-_,\.;:()\'\&@]+$/";
    if (!preg_match($pattern, $_POST['name'])) {
        $nameError = "Can only contain alphabets, numbers, white spaces, and special characters -_,.:;()'&@";
        $errors += 1;
    } else {
        $name = sanitize($handler, $_POST['name']);
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

    // validate image 
    if (!empty($_FILES['upload-file']['name'])) {
        // check file extension
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        $filename = $_FILES['upload-file']['name'];
        $extension = pathInfo($filename, PATHINFO_EXTENSION);
        
        if (!in_array($extension, $allowed)) {
            $imageError = "Only extensions .jpg, .jpeg, .png, .gif are allowed. Please upload another file";
            $errors += 1;
        } else {
            // create directory path for uploaded image file 
            $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
            $targetFileName = $_FILES['upload-file']['name'];
            $targetFileDirectory = $uploadsDir . DIRECTORY_SEPARATOR . $_FILES['upload-file']['name'];

            if (file_exists($targetFileDirectory)) { // check if file exists in directory
                $imageError = "File already exists. Please upload another file";
                $errors += 1;
            } else {
                if ($tripID !== 0) {
                    if (!is_null($imageDirectory)) {
                        // delete old file directory
                        unlink($imageDirectory);
                    }
                }

                // store image file in the directory created
                if (array_key_exists('upload-file', $_FILES)) {
                    $uploadInfo = $_FILES['upload-file'];

                    switch ($uploadInfo['error']) {
                        case UPLOAD_ERR_OK: // file uploaded
                            mime_content_type($uploadInfo['tmp_name']);
                            move_uploaded_file($uploadInfo['tmp_name'], $targetFileDirectory);
                            break;
                        case UPLOAD_ERR_NO_FILE: // no file uploaded
                            echo 'No file was uploaded.';
                            break;
                    }
                }

                $targetFileName = mysqli_real_escape_string($handler, $targetFileName);
                $targetFileDirectory = mysqli_real_escape_string($handler, $targetFileDirectory);
                $_SESSION['fileName'] = $targetFileName;
                $_SESSION['fileDirectory'] = $targetFileDirectory;
            }
        }
    } 

    // retrieve coordinates of accomodation
    $accomodationLat = "";
    if ($_POST['accomodation-lat']) {
        $_SESSION['accomodation-lat'] = $_POST['accomodation-lat'];
    }
    if (isset($_SESSION['accomodation-lat'])) {
        $accomodationLat = $_SESSION['accomodation-lat'];
    }

    $accomodationLong = "";
    if ($_POST['accomodation-long']) {
        $_SESSION['accomodation-long'] = $_POST['accomodation-long'];
    }
    if (isset($_SESSION['accomodation-long'])) {
        $accomodationLong = $_SESSION['accomodation-long'];
    }
    $accomodation = sanitize($handler, $_POST['accomodation']);
    $destination = sanitize($handler, $_POST['destination']);

    // if no errors found
    if ($errors == 0) {
        if ($tripID == 0) { // add trip details
            if (isset($_SESSION['location'])) {
                // retrieve location details from database
                $placeID = $_SESSION['placeID'];
                $category = $_SESSION['category'];
                $query = "SELECT * FROM " . ($category == "attraction" ? "attractions" : "eateries") . " WHERE placeID = '$placeID'";
                $result = mysqli_query($handler, $query);
                $details = mysqli_fetch_assoc($result);

                // fetch operating hours from API 
                $key = 'AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM';
                $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$placeID&key=$key";
                $response = file_get_contents($url);
        
                // check if request was successful 
                if ($response !== false) {
                    $data = json_decode($response, true);
        
                    // handle API reponse and extract details
                    if ($data['status'] === 'OK') {
                        $placeDetails = $data['result'];
                        $operatingHours = isset($placeDetails['opening_hours']['weekday_text']) ? implode("_ ", $placeDetails['opening_hours']['weekday_text']) : "No operating hours available";
        
                        // update operating hours in database
                        if ($category == "attraction") {
                            $query = "UPDATE attractions SET
                                    operatingHours = '" . $operatingHours . "'
                                WHERE placeID='$placeID'";
                        } else {
                            $query = "UPDATE eateries SET
                                    operatingHours = '" . $operatingHours . "'
                                WHERE placeID='$placeID'";
                        }
                        mysqli_query($handler, $query);
                    }
                }
           
                $sanitized = sanitize($handler, $locationName);
                if (!isset($_SESSION['fileName'])) { // no image uploaded
                    $query = "INSERT INTO trips (name, userEmail, destination, startDate, endDate, accomodation, accomodationLat, accomodationLong, waypoints)
                        VALUES ('$name', '$email', '$destination', '$startDate', '$endDate', '$accomodation', '$accomodationLat', '$accomodationLong', '$sanitized')";
                    mysqli_query($handler, $query);
                } else {
                    $targetFileName = $_SESSION['fileName'];
                    $targetFileDirectory = $_SESSION['fileDirectory'];
                    $query = "INSERT INTO trips (name, userEmail, destination, startDate, endDate, accomodation, accomodationLat, accomodationLong, waypoints, imageFile, imageDirectory)
                        VALUES ('$name', '$email', '$destination', '$startDate', '$endDate', '$accomodation', '$accomodationLat', '$accomodationLong', '$sanitized', '$targetFileName', '$targetFileDirectory')";
                    mysqli_query($handler, $query);
                    unset($_SESSION['fileName']);
                    unset($_SESSION['fileDirectory']);
                }
    
                // direct user to trips page
                $encodedName = urlencode($locationName);
                header("Location: trips.php?add=$name&location=$encodedName");
                exit();
            } else {
                if (!isset($_SESSION['fileName'])) { // no image uploaded
                    $query = "INSERT INTO trips (name, userEmail, destination, startDate, endDate, accomodation, accomodationLat, accomodationLong)
                        VALUES ('$name', '$email', '$destination', '$startDate', '$endDate', '$accomodation', '$accomodationLat', '$accomodationLong')";
                    mysqli_query($handler, $query);
                } else {
                    $targetFileName = $_SESSION['fileName'];
                    $targetFileDirectory = $_SESSION['fileDirectory'];
                    $query = "INSERT INTO trips (name, userEmail, destination, startDate, endDate, accomodation, accomodationLat, accomodationLong, imageFile, imageDirectory)
                        VALUES ('$name', '$email', '$destination', '$startDate', '$endDate', '$accomodation', '$accomodationLat', '$accomodationLong', '$targetFileName', '$targetFileDirectory')";
                    mysqli_query($handler, $query);
                    unset($_SESSION['fileName']);
                    unset($_SESSION['fileDirectory']);
                }
    
                // refresh page 
                header("Location: trips.php?add=$name");
                exit();
            }
        } else { // update trip details
            // check if start date, end date or accomodation has changed 
            if ($startDate !== $_SESSION['currentStartDate'] || $endDate !== $_SESSION['currentEndDate'] || $accomodation !== $_SESSION['currentAccomodation']) {
                $_SESSION['currentStartDate'] = $startDate;
                $_SESSION['currentEndDate'] = $endDate;
                $_SESSION['currentAccomodation'] = $accomodation;

                $query = "UPDATE trips SET
                    name = '$name', 
                    destination = '$destination', 
                    startDate = '$startDate',
                    endDate = '$endDate', 
                    accomodation = '$accomodation', 
                    accomodationLat = '$accomodationLat', 
                    accomodationLong = '$accomodationLong',
                    distanceMatrix = NULL,
                    itinerary = NULL
                WHERE tripID =" . $tripID;
                mysqli_query($handler, $query);
            } else {
                $query = "UPDATE trips SET
                    name = '$name', 
                    destination = '$destination', 
                    startDate = '$startDate',
                    endDate = '$endDate', 
                    accomodation = '$accomodation', 
                    accomodationLat = '$accomodationLat', 
                    accomodationLong = '$accomodationLong'
                WHERE tripID =" . $tripID;
                mysqli_query($handler, $query);
            }

            if (isset($_SESSION['fileName'])) {
                $targetFileName = $_SESSION['fileName'];
                $targetFileDirectory = $_SESSION['fileDirectory'];
                $query = "UPDATE trips SET
                            imageFile = '$targetFileName', 
                            imageDirectory = '$targetFileDirectory'
                        WHERE tripID =" . $tripID;
                mysqli_query($handler, $query);
                unset($_SESSION['fileName']);
                unset($_SESSION['fileDirectory']);
            }
            
            // refresh page 
            header("Location: trip-details.php?message=updated&tripID=$tripID");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Trip Details</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
        <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&key=AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM"></script>
    </head>

    <body>
        <?php include "header.php"; ?>

        <div class="wrapper" style="height: 75%">
            <div class="trip-block">
                <?php 
                    if ($tripID == 0) {
                        echo '<span class="trip-name">New Trip</span><br>';
                        echo '<span class="trip-date"></span>';
                    } else {
                        echo '<span class="trip-name">'. $name .'</span><br>';
                        echo '<span class="trip-date">'. date("m/d/Y", strtotime($startDate)) .' - '. date("m/d/Y", strtotime($endDate)) .'</span>';
                    }
                ?>
            </div>

            <!--tabs-->
            <div class="page-tabs">
                <a href="trip-details.php"><button class="tab active" id="details-tab" onclick="openTab(event, 'past')" style="margin-right: 13px;" disabled><b>1&nbsp;&nbsp;&nbsp;Details</b></button></a>
                <a href="locations.php"><button class="tab" id="locations-tab" onclick="openTab(event, 'ongoing')" style="margin-right: 13px;"><b>2&nbsp;&nbsp;&nbsp;Locations</b></button></a>
                <a href="itinerary.php"><button class="tab" id="itinerary-tab" onclick="openTab(event, 'upcoming')"><b>3&nbsp;&nbsp;&nbsp;Itinerary</b></button></a>
            </div>
            <div class="empty-div"></div>

            <!--trip details-->
            <div class="trip-details-div">
                <div style="transform: translate(120px, -28px);">
                    <a href="trips.php" id="back-explore">< Back to Trips Page</a>
                </div>
                 <!--display trip updated message-->
                 <div id="updated-div" style="display: none;">
                    <i class="fa-regular fa-circle-check"></i><br>
                    <h3 style="margin-top: 20px;">Trip details have been updated.</h3>
                </div>
                
                <!--form for user to update trip details-->
                <form action="trip-details.php?tripID=<?php echo $tripID;?>" id="details-form" method="post" style="margin-bottom: 18px;" enctype="multipart/form-data">
                    <table class="details-table"> 
                        <tr>
                            <td>
                                <div class="form-control"> 
                                    <label class="label">What would you like to name your trip?</label><br>
                                    <input class="update-info" type="text" id="name" name="name" value="<?php 
                                            if (isset($_POST['name'])) {
                                                echo $_POST['name'];
                                            } else if ($tripID !== 0) {
                                                echo $name;
                                            }
                                        ?>" required>
                                    <?php
                                    if (isset($nameError)) {
                                        echo '<div class="form-error" style="width: 370px;">';
                                        echo $nameError;
                                        echo "</div>";
                                        unset($nameError);
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="form-control"> 
                                    <label class="label">Where would you like to visit?</label><br>
                                    <input class="update-info" type="text" name="destination" id="destination1" placeholder="Enter a city or region" value="<?php 
                                            if (isset($_POST['destination'])) {
                                                echo $_POST['destination'];
                                            } else if ($tripID !== 0) {
                                                echo $destination;
                                            } else if ($fromExplore) {
                                                if (isset($_SESSION['searchInput'])) {
                                                    echo $_SESSION['searchInput'];
                                                } else if (isset($_SESSION['destination'])) {
                                                    echo $_SESSION['destination'];
                                                }
                                            }
                                        ?>" required>
                                    <?php
                                    if (isset($destinationError)) {
                                        echo '<div class="form-error">';
                                        echo $destinationError;
                                        echo "</div>";
                                        unset($destinationError);
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="date-div"> 
                                    <label class="label">When is your trip?</label><br>
                                    <div class="form-control" style="float: left;">
                                        <input class="update-info" type="date" name="start-date" style="width: 110px; padding-right: 10px;" 
                                        <?php 
                                            if ($tripID == 0) {
                                                echo "min='" . date('Y-m-d') ."'";
                                            } 
                                        ?>
                                        value="<?php 
                                            if (isset($_POST['start-date'])) {
                                                echo $_POST['start-date'];
                                            } else if ($tripID !== 0) {
                                                echo $startDate;
                                            } else if ($fromExplore) {
                                                if (isset($_SESSION['startDate'])) {
                                                    echo $_SESSION['startDate'];
                                                }
                                            }
                                        ?>" required>
                                        <label class="sub-label">&nbsp;&ndash;</label>
                                    </div>
                                    <div class="form-control" style="float: right; transform: translate(-174px, 0px);">
                                        <input class="update-info" type="date" name="end-date" style="width: 110px; padding-right: 10px;" 
                                        <?php 
                                            if ($tripID == 0) {
                                                echo "min='" . date('Y-m-d') ."'";
                                            } 
                                        ?>
                                        value="<?php 
                                            if (isset($_POST['end-date'])) {
                                                echo $_POST['end-date'];
                                            } else if ($tripID !== 0) {
                                                echo $endDate;
                                            } else if ($fromExplore) {
                                                if (isset($_SESSION['endDate'])) {
                                                    echo $_SESSION['endDate'];
                                                }
                                            }
                                        ?>" required>
                                        <?php
                                            if (isset($endDateError) && $endDateError == "End date must be after start date") {
                                                echo '<div class="form-error" style="width: 190px; transform: translate(0px, -1.5px);">';
                                                echo $endDateError;
                                                echo "</div>";
                                                unset($endDateError);
                                            } else if (isset($endDateError) && $endDateError == "Only a maximum number of 6 days is allowed") {
                                                echo '<div class="form-error" style="width: 263px; transform: translate(0px, -1.5px);">';
                                                echo $endDateError;
                                                echo "</div>";
                                                unset($endDateError);
                                            }
                                        ?>
                                    </div>
                                    <div style="clear: both;"></div>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <label for="upload-file" class="label">Trip Cover Image:</label>
                                <span style="font-size: 15px; margin-left: 4px;">
                                    <?php 
                                        if (isset($_POST['update'])) {
                                            if (!empty($_FILES['upload-file']['name'])) {
                                                echo $_FILES['upload-file']['name'];
                                            } else if ($tripID !== 0) {
                                                if (!is_null($imageFile)) {
                                                    echo $imageFile;
                                                } else {
                                                    echo "no image uploaded";
                                                }
                                            } else {
                                                echo "no image uploaded";
                                            }
                                        } else {
                                            if ($tripID !== 0) {
                                                if (!is_null($imageFile)) {
                                                    echo $imageFile;
                                                } else {
                                                    echo "no image uploaded";
                                                }
                                            } else {
                                                echo "no image uploaded";
                                            }
                                        }
                                    ?>
                                </span>
                                <button id="upload-btn" type="button" onclick="showUploadDiv()">Upload Image</button><br>
                                    
                                <?php
                                    if (isset($_POST['update'])) {
                                        if (empty($_FILES['upload-file']['name']) || !isset($imageError)) { // no new file uploaded or no error
                                            echo '<div class="form-control" id="upload-div" style="display: none">
                                                            <input type="file" name="upload-file" id="upload-file">
                                                        </div>';
                                        } else {
                                            echo '<div class="form-control" id="upload-div">
                                                            <input type="file" name="upload-file" id="upload-file" style="font-size: 14px;">';
                                            echo '<br><div class="form-error" style="transform: translate(190px, -37px);">';
                                            echo $imageError;
                                            echo "</div>";
                                            unset($imageError);                                      
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<div class="form-control" id="upload-div" style="display: none">
                                                        <input type="file" name="upload-file" id="upload-file">
                                                    </div>';
                                    }
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="form-control"> 
                                    <label class="label">Where will you be staying?</label><br>
                                    <input class="update-info" type="text" id="accomodation" name="accomodation" value="<?php 
                                            if (isset($_POST['accomodation'])) {
                                                echo $_POST['accomodation'];
                                            } else if ($tripID !== 0) {
                                                echo $accomodation;
                                            }
                                        ?>" required>
                                    <?php
                                    if (isset($accomodationError)) {
                                        echo '<div class="form-error" style="width: 199px;">';
                                        echo $accomodationError;
                                        echo "</div>";
                                        unset($accomodationError);
                                    }
                                    ?>
                                </div>
                                <input type="hidden" id="accomodation-lat" name="accomodation-lat">
                                <input type="hidden" id="accomodation-long" name="accomodation-long">
                            </td>
                        </tr>

                    </table>
                    <tr>
                        <td><input class="update-btn" type="submit" name="update" value="Save Changes" style="transform: translate(-10px, 0px);"></td>
                    </tr>
                </form>
            </div>
        </div>

        <?php include "footer.php";?>

        <script>
            window.onload = underlineTrips();
            function underlineTrips() {
                let div = document.getElementById("nav-trips");
                div.style.display = "block";
            }

            // disable Locations and Itinerary tabs 
            let enableLocations = "<?php echo $enableLocations?>";
            let enableItinerary = "<?php echo $enableItinerary?>";
            if (!enableLocations) {
                document.getElementById("locations-tab").disabled = true;
            }
            if (!enableItinerary) {
                document.getElementById("itinerary-tab").disabled = true;
            }

            // display trip updated message
            let updatedDiv = document.getElementById("updated-div");
            let tripUpdated = "<?php echo $tripUpdated?>";
            if (tripUpdated) {
                updatedDiv.style.display = "block";
            }

            // set fading effect
            if (updatedDiv.style.display === "block") {
                setTimeout(() => {
                    updatedDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    updatedDiv.style.display = "none";
                }, 3000);
            }

            // show or hide Choose File input when Upload Image button is clicked
            function showUploadDiv() {
                let uploadDiv = document.getElementById("upload-div");
                if (uploadDiv.style.display !== "none") {
                    uploadDiv.style.display = "none";
                } else {
                    uploadDiv.style.display = "block";
                }
            }

            // location autocomplete using google places API
            $(document).ready(function() {
                let destination_autocomplete = new google.maps.places.Autocomplete((document.getElementById("destination1")), {
                    types: ['(regions)']
                });
                google.maps.event.addListener(destination_autocomplete, 'place_changed', function() {
                    let place = destination_autocomplete.getPlace();
                });

                let accomodation_autocomplete = new google.maps.places.Autocomplete((document.getElementById("accomodation")), {
                    types: ['lodging']
                });
                google.maps.event.addListener(accomodation_autocomplete, 'place_changed', function() {
                    let place = accomodation_autocomplete.getPlace();
                    document.getElementById("accomodation-lat").value = place.geometry.location.lat();
                    document.getElementById("accomodation-long").value = place.geometry.location.lng();
                });
            });
        </script>
    </body>
</html>


