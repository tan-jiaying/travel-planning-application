<?php 
ob_start();
date_default_timezone_set('Asia/Singapore');
if (!isset($_SESSION)) session_start();
$email = $_SESSION['email'];

// connect to database 
include "connect-db.php";
connectDB();

// initalize variables
$enableItinerary = false;
$waypointRemoved = false;
$sufficientAttractions = true; 
$sufficientEateries = true; 
$preferencesFilled = true;

// retrieve user details from database
$query = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($handler, $query);
$details = mysqli_fetch_assoc($result);
if (is_null($details['startTime'])) {
    $preferencesFilled = false;
} else {
    $preferencesFilled = true;
}

// retrieve trip details from database
$tripID = $_SESSION['tripID'];
$query = "SELECT * FROM trips WHERE tripID = $tripID";
$result = mysqli_query($handler, $query);
$trip = mysqli_fetch_assoc($result);
$name = $trip['name'];
$startDate = $trip['startDate'];
$endDate = $trip['endDate'];
$accomodation = $trip['accomodation'];
$waypoints = $trip['waypoints']; 
$waypoints = explode('_ ', $trip['waypoints']); // array of waypoints

// retrieve only attractions 
$attractions = array(); 
$attractionCount = 0;
foreach ($waypoints as $waypoint) {
    $query = "SELECT * FROM attractions WHERE name = '$waypoint'";
    $result = mysqli_query($handler, $query);
    $attraction = mysqli_fetch_all($result, MYSQLI_ASSOC);

    if (!empty($attraction)) {
        $attractionCount += 1;
        array_push($attractions, $waypoint);
    }
}

// retrieve only eateries 
$eateries = array();
$eateryCount = 0;
foreach ($waypoints as $waypoint) {
    $query = "SELECT * FROM eateries WHERE name = '$waypoint'";
    $result = mysqli_query($handler, $query);
    $eatery = mysqli_fetch_all($result, MYSQLI_ASSOC);

    if (!empty($eatery)) {
        $eateryCount += 1;
        array_push($eateries, $waypoint);
    }
}

// calculate number of days from start to end date 
$startDateObj = new DateTime($startDate);
$endDateObj = new DateTime($endDate); 

$interval = $startDateObj->diff($endDateObj);
$numOfDays = $interval-> days + 1; 

// enable Itinerary tab if itinerary has been created
if (!is_null($trip['itinerary'])) {
    $enableItinerary = true;
} 

// check if at least (4 * number of days) attractions are added
if ($attractionCount < (4 * $numOfDays)) {
    $sufficientAttractions = false;
} else {
    $sufficientAttractions = true;
}

// check if at least (3 * number of days) eateries are added
if ($eateryCount < (3 * $numOfDays)) {
    $sufficientEateries = false;
} else {
    $sufficientEateries = true;
}

// function to sanitize input
function sanitize($handler, $input) {
    $input = trim($input);
    $input = htmlentities($input);
    return mysqli_real_escape_string($handler, $input);
}

// check if delete button is clicked 
if (isset($_GET['name'])) {
    $name = $_GET['name'];

    // remove waypoint from trip 
    $waypoints = explode('_ ', $trip['waypoints']); // array of waypoints
    $index = array_search($name, $waypoints);
    array_splice($waypoints, $index, 1);
    $waypoints = implode("_ ", $waypoints); // string 
    if ($waypoints == "") {
        unset($waypoints); // assign null
    }

   // reset distance matrix and itinerary to null
   $query = "UPDATE trips SET
        waypoints = '$waypoints',
        distanceMatrix = NULL, 
        itinerary = NULL
        WHERE tripID =" . $tripID;
    mysqli_query($handler, $query);
    $name1 = urlencode($name);
    header("Location: locations.php?remove=$name1");
    exit();
} 

// display waypoint removed message 
if (isset($_GET['remove'])) {
    $waypointRemoved = true;
} else {
    $waypointRemoved = false;
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Locations</title>
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
                <span class="trip-name"><?php echo $name;?></span><br>
                <span class="trip-date"><?php echo date("m/d/Y", strtotime($startDate));?> - <?php echo date("m/d/Y", strtotime($endDate));?></span>
            </div>

            <!--tabs-->
            <div class="page-tabs">
                <a href="trip-details.php?tripID=<?php echo $_SESSION['tripID'];?>"><button class="tab" id="details-tab" onclick="openTab(event, 'past')" style="margin-right: 13px;"><b>1&nbsp;&nbsp;&nbsp;Details</b></button></a>
                <a href="locations.php"><button class="tab active" id="locations-tab" onclick="openTab(event, 'ongoing')" style="margin-right: 13px;" disabled><b>2&nbsp;&nbsp;&nbsp;Locations</b></button></a>
                <a href="itinerary.php"><button class="tab" id="itinerary-tab" onclick="openTab(event, 'upcoming')"><b>3&nbsp;&nbsp;&nbsp;Itinerary</b></button></a>
            </div>
            <div class="empty-div2"></div>

            <!--trip details-->
            <div class="locations-div">
                <!--start and end locations-->
                <div class="start-end-div" style="transform: translate(-20px, -37px); margin-bottom: -30px;">
                    <form action="locations.php" id="start-form" method="post">
                        <div class="form-control" style="transform: translate(0px, -10px); text-align: left;"> 
                            <label class="label" style="color:#4178A4;">Start</label>&nbsp;&nbsp;&nbsp;
                            <div class="waypoint" style="transform: translate(0px, 12px);">
                                <span>
                                    <?php 
                                        $accomodation1 = explode(",", $accomodation);
                                        $accomodation1 = trim($accomodation1[0]);
                                        echo $accomodation1;
                                    ?>
                                </span>
                            </div>
                        </div>
                    </form>

                    <form action="locations.php" id="end-form" method="post">
                        <div class="form-control" style="transform: translate(6px, -18px); text-align: left;"> 
                            <label class="label" style="color:#4178A4;">End</label>&nbsp;&nbsp;&nbsp;
                            <div class="waypoint" style="transform: translate(0px, 12px);">
                                <span>
                                    <?php 
                                        $accomodation1 = explode(",", $accomodation);
                                        $accomodation1 = trim($accomodation1[0]);
                                        echo $accomodation1;
                                    ?>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>

                <!--waypoints--> 
                <div class="waypoints-title">
                    <label class="label" style="color:#4178A4;">Waypoints</label>
                </div>
                
                <div class="waypoints-div">
                    <table>
                        <tr>
                            <td>
                                <!--attractions!-->
                                <div class="attractions-div">
                                    <div class="attractions-title">
                                        <label class="label" style="color:black; float:left;">Attractions
                                        <a href="explore.php"><button class="add-location-btn" style="float:right;"><i class="fa-solid fa-plus"></i></button></a>
                                        <div style="clear: both;"></div>
                                    </div>
                                    <br>

                                    <div class="attractions" id="attraction-waypoints">
                                        <div class="attractions-box">
                                            <?php 
                                                foreach ($attractions as $attraction) {   
                                            ?>
                                            <form id="delete-form" method="post" action="locations.php?name=<?php echo urlencode($attraction);?>" style="margin-bottom: -24px;">
                                                <div class="waypoint">
                                                    <span><?php echo $attraction;?></span>
                                                </div>
                                                <button type="submit" name="delete" class="remove-btn"><i class="fa-solid fa-minus"></i></button>
                                            </form>
                                            <br>
                                            <?php
                                          
                                                }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <!--eateries-->
                                <div class="restaurants-div">
                                    <div class="attractions-title">
                                        <label class="label" style="color:black; float:left;">Eateries
                                        <a href="explore.php"><button class="add-location-btn" style="float:right;"><i class="fa-solid fa-plus"></i></button></a>
                                        <div style="clear: both;"></div>
                                    </div>
                                    <br>

                                    <div class="attractions" id="restaurant-waypoints">
                                        <div class="attractions-box">
                                            <?php 
                                                foreach ($eateries as $eatery) {
                                            ?>
                                            <form id="delete-form" method="post" action="locations.php?name=<?php echo urlencode($eatery);?>" style="margin-bottom: -24px;">
                                                <div class="waypoint">
                                                    <span><?php echo $eatery;?></span>
                                                </div>
                                                <button type="submit" name="delete" class="remove-btn"><i class="fa-solid fa-minus"></i></button>
                                            </form>
                                            <br>
                                            <?php
                                                }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                <button class="plan-btn" id="plan-btn">Plan Itinerary&nbsp;&nbsp;<i class="fa-solid fa-angles-right" style="color: white;"></i></button>
                
                <!--display waypoint removed message-->
                <div id="removed-div" style="display: none;">
                    <i class="fa-regular fa-circle-check"></i><br>
                    <h3 id="remove-text" style="margin: auto; margin-top: 20px;"></h3>
                </div>

                <!--display insufficient number of locations message-->
                <div id="insufficient-div" style="display: none;">
                    <i class="fa-regular fa-circle-xmark"></i><br>
                    <h3 id="insufficient-text" style="margin: auto; margin-top: 20px; width: 450px;"></h3>
                </div>

                <!--display preferences not filled message-->
                <div id="preferences-div" style="display: none;">
                    <i class="fa-regular fa-circle-xmark"></i><br>
                    <h3 id="preferences-text" style="margin: auto; margin-top: 20px; width: 450px;">Please fill in your preferences in the Profile page.</h3>
                </div>

                <!--display planning itinerary message-->
                <div id="planning-div" style="display: none;">
                    <img id="loading-img" src="img/loading.gif" alt="Planning Itinerary..." /><br>
                    <h3 id="preferences-text" style="margin: auto; margin-top: 35px; width: 450px;">Planning Itinerary...</h3>
                </div>
            </div>
        </div>

        <?php include "footer.php";?>

        <script>
            window.onload = underlineTrips();
            function underlineTrips() {
                let div = document.getElementById("nav-trips");
                div.style.display = "block";
            }

            // hide scrollbar if less than 8 locations
            let attractionCount = "<?php echo $attractionCount?>";
            if (attractionCount < 8)
            {
                document.getElementById("attraction-waypoints").style.overflow = "hidden";
            }

            let eateryCount = "<?php echo $eateryCount?>";
            if (eateryCount < 8)
            {
                document.getElementById("restaurant-waypoints").style.overflow = "hidden";
            }

            // disable Itinerary tab
            let enableItinerary = "<?php echo $enableItinerary?>";
            if (!enableItinerary) {
                document.getElementById("itinerary-tab").disabled = true;
            }

            // display waypoint removed message
            let removedDiv = document.getElementById("removed-div");
            let waypointRemoved = "<?php echo $waypointRemoved?>";
            <?php 
            if (isset($_GET['remove'])) {
            ?>
            if (waypointRemoved) {
                removedDiv.style.display = "block";
                document.getElementById("remove-text").innerHTML = "<?php echo $_GET['remove']; ?>" + " has been removed.";
            }
            <?php 
                }
            ?>
            
            // set fading effect
            if (removedDiv.style.display === "block") {
                setTimeout(() => {
                    removedDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    removedDiv.style.display = "none";
                }, 3000);
            }

            document.getElementById("plan-btn").addEventListener('click', () => {
                // display error message if requirements are not met
                let preferencesDiv = document.getElementById("preferences-div");
                let preferencesFilled = "<?php echo $preferencesFilled?>";
                let insufficientDiv = document.getElementById("insufficient-div");
                let sufficientAttractions = "<?php echo $sufficientAttractions?>";
                let sufficientEateries = "<?php echo $sufficientEateries?>";
                let numOfDays = "<?php echo $numOfDays?>";
                let minimumAttractions = 4 * numOfDays;
                let minimumEateries = 3 * numOfDays;

                if (!preferencesFilled) { 
                    preferencesDiv.style.display = "block";
                } else if (!sufficientAttractions && !sufficientEateries) {  
                    insufficientDiv.style.display = "block";
                    document.getElementById("insufficient-text").innerHTML = "Please add at least " + minimumAttractions + " attractions and " + minimumEateries + " eateries.";
                } else if (!sufficientAttractions) {
                    insufficientDiv.style.display = "block";
                    document.getElementById("insufficient-text").innerHTML = "Please add at least " + minimumAttractions + " attractions.";
                } else if (!sufficientEateries) {
                    insufficientDiv.style.display = "block";
                    document.getElementById("insufficient-text").innerHTML = "Please add at least " + minimumEateries + " eateries.";
                } 

                // set fading effect
                if (preferencesDiv.style.display === "block") {
                    setTimeout(() => {
                        preferencesDiv.classList.add("fadeout-effect");
                    }, 2000); 
                    setTimeout(() => {
                        preferencesDiv.style.display = "none";
                    }, 3000);
                }

                if (insufficientDiv.style.display === "block") {
                    setTimeout(() => {
                        insufficientDiv.classList.add("fadeout-effect");
                    }, 2000); 
                    setTimeout(() => {
                        insufficientDiv.style.display = "none";
                    }, 3000);
                }

                if (preferencesFilled && sufficientAttractions && sufficientEateries) {
                    // direct user to itinerary page
                    window.location.href = "itinerary.php";

                    // show planning itinerary popup while loading itinerary.php 
                    document.getElementById("planning-div").style.display = 'block';

                    // use fetch API to load "itinerary.php"
                    fetch('itinerary.php')
                        .then(response => response.text())
                        .then(data => {
                            // hide planning itinerary popup
                            document.getElementById("planning-div").style.display = 'none';

                            // replace current page with loaded "itinerary.php" content
                            document.open();
                            document.write(data);
                            document.close();
                        });
                }
            });
        </script>
    </body>
</html>