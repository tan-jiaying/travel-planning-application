<?php
ob_start(); 
session_start(); 

// ensure user is logged in 
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
} else {
    // redirect user to login page 
    header("Location: login.php?message=notLoggedIn");
    exit();
}

// initialize variables
$tripAdded = false; 
$tripDeleted = false;
$tripName = "";
$locationName = "";

// retrieve GET variables
if (isset($_GET['add'])) {
    $tripAdded = true; 
    $tripName = $_GET['add'];
} else {
    $tripAdded = false;
}

if (isset($_GET['delete'])) {
    $tripDeleted = true;
    $tripName = $_GET['delete'];
} else {
    $tripDeleted = false;
}

if (isset($_GET['location'])) {
    $locationName = $_GET['location'];
} else {
    $locationName = "";
}

 if (isset($_SESSION['location'])) {
    unset($_SESSION['location']);
}

// connect to database 
include "connect-db.php";
connectDB(); 

// retrieve trips from database
$query = "SELECT * FROM trips WHERE userEmail='$email' ORDER BY tripID DESC";
$result = mysqli_query($handler, $query);
$trips = mysqli_fetch_all($result, MYSQLI_ASSOC);

// check if delete button is clicked 
if (isset($_POST['delete'])) {
    $tripID = $_GET['tripID'];

    // remove image file from uploads folder 
    $query = "SELECT imageDirectory FROM trips WHERE tripID='$tripID'";
    $result = mysqli_query($handler, $query); 
    while ($row = mysqli_fetch_array($result)) {
        $imageDirectory = $row[0];
    } 
    if (!is_null($imageDirectory)) {
        unlink($imageDirectory);
    }

    // delete trip from database 
    $query = "SELECT name FROM trips WHERE tripID='$tripID'";
    $result = mysqli_query($handler, $query);
    while ($row = mysqli_fetch_array($result)) {
        $tripName = $row[0];
    } 
    $query = "DELETE FROM trips WHERE tripID='$tripID'";
    mysqli_query($handler, $query);
    header("Location: trips.php?delete=$tripName");
    exit();
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Trips</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
    </head>

    <body>
        <?php include "header.php"; ?>

        <div class="wrapper" style="height: 75%">
            <div class="title-container">Trips</div>
            <div class="trips-div">
                <!--trip categories-->
                <div class="trip-categories-div" style="margin-top: -5px;">
                    <div class="trip-categories">
                        <button class="trip-tab" onclick="openTab(event, 'past')" style="margin-right: 20px;"><b>Past</b></button>
                        <button class="trip-tab" onclick="openTab(event, 'ongoing')" style="margin-right: 20px;"><b>Ongoing</b></button>
                        <button class="trip-tab" id="default-tab" onclick="openTab(event, 'upcoming')"><b>Upcoming</b></button>
                    </div>
                    <a href="trip-details.php"><button class="add-trip-btn" title="Create new trip"><i class="fa-solid fa-plus"></i></button></a>
                    <div style="clear: both;"></div>
                </div>
        
                <!--past trips-->
                <div class="trip-display" id="past">
                    <button id="slide-left4" class="slide-btn"><i class="fa-solid fa-angle-left"></i></button>
                    <div class="trip-display-block" id="past-list">
                        <?php 
                            $pastCount = 0;
                            if (!empty($trips)) {
                                foreach ($trips as $trip) {
                                    if ($trip["endDate"] < date('Y-m-d')) {
                                        $pastCount += 1;
                        ?>

                        <div class="trip-box">
                            <div class="trip-box-content">
                                <a href="trip-details.php?tripID=<?php echo $trip["tripID"]; ?>">
                                <img class="trip-box-img" src="
                                <?php 
                                    if (!is_null($trip['imageDirectory'])) {
                                        $img = "C:xampphtdocsTripBuilderuploads";
                                        $img_str = str_replace($img, "", stripslashes($trip["imageDirectory"]));
                                        echo "uploads/". $img_str;
                                    } else {
                                        echo "img/default-image.png";
                                    }
                                ?>
                                "><br>
                                <p class="img_link1">View Trip Details</p></a>

                                <div class="trip-title-div">
                                    <b><span class="trip-title"><?php echo $trip["name"]; ?></span></b>
                                </div>

                                <div class="trip-details">
                                    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <?php echo date("m/d/Y", strtotime($trip['startDate']));?> - <?php echo date("m/d/Y", strtotime($trip['endDate']));?>
                                    </span><br>
                                    <span style="transform: translate(0px, -2px);">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    <?php 
                                    if (!is_null($trip['waypoints'])) {
                                        $waypoints_array = explode('_ ', $trip['waypoints']); // array of waypoints
                                        echo count($waypoints_array);
                                    } else {
                                        echo "0";
                                    }
                                    ?> location(s)
                                    </span><br>
                                    <i class="fa-regular fa-calendar" style="color: black; font-size: 20px; transform: translate(0px, -53px);"></i><br>
                                    <i class="fa-solid fa-location-dot" style="color: black; font-size: 20px; transform: translate(1px, -55px);" ></i>
                                    <form id="delete-form" method="post" action="trips.php?tripID=<?php echo $trip["tripID"]?>">
                                        <div class="delete-icon" style="margin-top: -68px;">
                                            <button type="submit" name="delete" class="delete-btn" style="float: right;"><i class="fa-solid fa-trash-can"></i></button>
                                        </div> 
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php
                                    }
                                }
                                if ($pastCount == 0) {
                                    echo "
                                        <div class='no-trip'>
                                            <img class='no-trip-img' src='img/no-results-found.png'>
                                            <h3 style='font-weight: normal;'>No past trip to be displayed.</h3>
                                        </div>
                                    ";
                                }
                            } else {
                                echo "
                                    <div class='no-trip'>
                                        <img class='no-trip-img' src='img/no-results-found.png'>
                                        <h3 style='font-weight: normal;'>No past trip to be displayed.</h3>
                                    </div>
                                ";
                            }
                        ?>
                    </div>
                    <button id="slide-right4" class="slide-btn"><i class="fa-solid fa-angle-right"></i></button>
                </div>

                <!--ongoing trips-->
                <div class="trip-display" id="ongoing">
                    <button id="slide-left5" class="slide-btn"><i class="fa-solid fa-angle-left"></i></button>
                    <div class="trip-display-block" id="ongoing-list">
                        <?php 
                            $ongoingCount = 0;
                            if (!empty($trips)) {
                                foreach ($trips as $trip) {
                                    if ($trip['startDate'] <= date('Y-m-d') && $trip['endDate'] >= date('Y-m-d')) {
                                        $ongoingCount += 1;
                        ?>

                        <div class="trip-box">
                            <div class="trip-box-content">
                                <a href="trip-details.php?tripID=<?php echo $trip["tripID"]; ?>">
                                <img class="trip-box-img" src="
                                <?php 
                                    if (!is_null($trip['imageDirectory'])) {
                                        $img = "C:xampphtdocsTripBuilderuploads";
                                        $img_str = str_replace($img, "", stripslashes($trip["imageDirectory"]));
                                        echo "uploads/". $img_str;
                                    } else {
                                        echo "img/default-image.png";
                                    }
                                ?>
                                "><br>
                                <p class="img_link1">View Trip Details</p></a>

                                <div class="trip-title-div">
                                    <b><span class="trip-title"><?php echo $trip["name"]; ?></span></b>
                                </div>

                                <div class="trip-details">
                                    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <?php echo date("m/d/Y", strtotime($trip['startDate']));?> - <?php echo date("m/d/Y", strtotime($trip['endDate']));?>
                                    </span><br>
                                    <span style="transform: translate(0px, -2px);">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    <?php 
                                    if (!is_null($trip['waypoints'])) {
                                        $waypoints_array = explode('_ ', $trip['waypoints']); // array of waypoints
                                        echo count($waypoints_array);
                                    } else {
                                        echo "0";
                                    }
                                    ?> location(s)
                                    </span><br>
                                    <i class="fa-regular fa-calendar" style="color: black; font-size: 20px; transform: translate(0px, -53px);"></i><br>
                                    <i class="fa-solid fa-location-dot" style="color: black; font-size: 20px; transform: translate(1px, -55px);" ></i>
                                    <form id="delete-form" method="post" action="trips.php?tripID=<?php echo $trip["tripID"]?>">
                                        <div class="delete-icon" style="margin-top: -68px;">
                                            <button type="submit" name="delete" class="delete-btn" style="float: right;"><i class="fa-solid fa-trash-can"></i></button>
                                        </div> 
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php
                                    }
                                }
                                if ($ongoingCount == 0) {
                                    echo "
                                        <div class='no-trip'>
                                            <img class='no-trip-img' src='img/no-results-found.png'>
                                            <h3 style='font-weight: normal;'>No ongoing trip to be displayed.</h3>
                                        </div>
                                    ";
                                }
                            } else {
                                echo "
                                    <div class='no-trip'>
                                        <img class='no-trip-img' src='img/no-results-found.png'>
                                        <h3 style='font-weight: normal;'>No ongoing trip to be displayed.</h3>
                                    </div>
                                ";
                            }
                        ?>
                    </div>
                    <button id="slide-right5" class="slide-btn"><i class="fa-solid fa-angle-right"></i></button>
                </div>

                <!--upcomimg trips-->
                <div class="trip-display" id="upcoming">
                    <button id="slide-left6" class="slide-btn"><i class="fa-solid fa-angle-left"></i></button>
                    <div class="trip-display-block" id="upcoming-list">
                        <?php 
                            $upcomingCount = 0;
                            if (!empty($trips)) {
                                foreach ($trips as $trip) {
                                    if ($trip["startDate"] > date('Y-m-d')) {
                                        $upcomingCount += 1;
                        ?>

                        <div class="trip-box">
                            <div class="trip-box-content">
                                <a href="trip-details.php?tripID=<?php echo $trip["tripID"]; ?>">
                                <img class="trip-box-img" src="
                                <?php 
                                    if (!is_null($trip['imageDirectory'])) {
                                        $img = "C:xampphtdocsTripBuilderuploads";
                                        $img_str = str_replace($img, "", stripslashes($trip["imageDirectory"]));
                                        echo "uploads/". $img_str;
                                    } else {
                                        echo "img/default-image.png";
                                    }
                                ?>
                                "><br>
                                <p class="img_link1">View Trip Details</p></a>

                                <div class="trip-title-div">
                                    <b><span class="trip-title"><?php echo $trip["name"]; ?></span></b>
                                </div>

                                <div class="trip-details">
                                    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <?php echo date("m/d/Y", strtotime($trip['startDate']));?> - <?php echo date("m/d/Y", strtotime($trip['endDate']));?>
                                    </span><br>
                                    <span style="transform: translate(0px, -2px);">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    <?php 
                                    if (!is_null($trip['waypoints'])) {
                                        $waypoints_array = explode('_ ', $trip['waypoints']); // array of waypoints
                                        echo count($waypoints_array);
                                    } else {
                                        echo "0";
                                    }
                                    ?> location(s)
                                    </span><br>
                                    <i class="fa-regular fa-calendar" style="color: black; font-size: 20px; transform: translate(0px, -53px);"></i><br>
                                    <i class="fa-solid fa-location-dot" style="color: black; font-size: 20px; transform: translate(1px, -55px);" ></i>
                                    <form id="delete-form" method="post" action="trips.php?tripID=<?php echo $trip["tripID"]?>">
                                        <div class="delete-icon" style="margin-top: -68px;">
                                            <button type="submit" name="delete" class="delete-btn" style="float: right;"><i class="fa-solid fa-trash-can"></i></button>
                                        </div> 
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php
                                    }
                                }
                                if ($upcomingCount == 0) {
                                    echo "
                                        <div class='no-trip'>
                                            <img class='no-trip-img' src='img/no-results-found.png'>
                                            <h3 style='font-weight: normal;'>No upcoming trip to be displayed.</h3>
                                        </div>
                                    ";
                                }
                            } else {
                                echo "
                                    <div class='no-trip'>
                                        <img class='no-trip-img' src='img/no-results-found.png'>
                                        <h3 style='font-weight: normal;'>No upcoming trip to be displayed.</h3>
                                    </div>
                                ";
                            }
                        ?>
                    </div>
                    <button id="slide-right6" class="slide-btn"><i class="fa-solid fa-angle-right"></i></button>
                </div>

                <!--display trip added message-->
                <div id="added-div" style="display: none;">
                    <i class="fa-regular fa-circle-check"></i><br>
                    <h3 id="add-text" style="margin: auto; margin-top: 20px; width: 450px;"></h3>
                </div>

                <!--display trip deleted message-->
                <div id="deleted-div" style="display: none;">
                    <i class="fa-regular fa-circle-check"></i><br>
                    <h3 id="delete-text" style="margin: auto; margin-top: 20px;"></h3>
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

            // hide left and right arrows if less than 3 trips
            let pastCount = "<?php echo $pastCount?>";
            if (pastCount < 3)
            {
                document.getElementById("slide-left4").style.visibility = "hidden";
                document.getElementById("slide-right4").style.visibility = "hidden";
                document.getElementById("past-list").style.overflow = "hidden";
            }

            let ongoingCount = "<?php echo $ongoingCount?>";
            if (ongoingCount < 3)
            {
                document.getElementById("slide-left5").style.visibility = "hidden";
                document.getElementById("slide-right5").style.visibility = "hidden";
                document.getElementById("ongoing-list").style.overflow = "hidden";
            }

            let upcomingCount = "<?php echo $upcomingCount?>";
            if (upcomingCount < 3)
            {
                document.getElementById("slide-left6").style.visibility = "hidden";
                document.getElementById("slide-right6").style.visibility = "hidden";
                document.getElementById("upcoming-list").style.overflow = "hidden";
            }

            // scroll left and right 
            let leftArrow4 = document.getElementById("slide-left4");
            let rightArrow4 = document.getElementById("slide-right4");

            leftArrow4.onclick = function() {
                document.getElementById("past-list").scrollLeft -= 200;
            };
            rightArrow4.onclick = function() {
                document.getElementById('past-list').scrollLeft += 200;
            };

            let leftArrow5 = document.getElementById("slide-left5");
            let rightArrow5 = document.getElementById("slide-right5");

            leftArrow5.onclick = function() {
                document.getElementById("ongoing-list").scrollLeft -= 200;
            };
            rightArrow5.onclick = function() {
                document.getElementById('ongoing-list').scrollLeft += 200;
            };

            let leftArrow6 = document.getElementById("slide-left6");
            let rightArrow6 = document.getElementById("slide-right6");

            leftArrow6.onclick = function() {
                document.getElementById("upcoming-list").scrollLeft -= 200;
            };
            rightArrow6.onclick = function() {
                document.getElementById('upcoming-list').scrollLeft += 200;
            };

            // function to show respective trip list
            function openTab(event, category) {
                content = document.getElementsByClassName("trip-display");
                for (let i = 0; i < content.length; i++) {
                    content[i].style.display = "none";
                }

                tab = document.getElementsByClassName("trip-tab");
                for (let i=0; i < tab.length; i++) {
                    tab[i].className = tab[i].className.replace(" active", "");
                }

                document.getElementById(category).style.display = "block";
                event.currentTarget.className += " active";
            }

            // show upcoming trips by default 
            document.getElementById("default-tab").click();

            // display corresponding message
            let addedDiv = document.getElementById("added-div");
            let tripAdded = "<?php echo $tripAdded?>";
            let tripName = "<?php echo $tripName?>";
            let locationName = "<?php echo $locationName?>";
            if (tripAdded) {
                addedDiv.style.display = "block";
                if (locationName !== "") {
                    document.getElementById("add-text").innerHTML = tripName + " has been created with " + locationName + " added.";
                } else {
                    document.getElementById("add-text").innerHTML = tripName + " has been created.";
                }
            }

            let deletedDiv = document.getElementById("deleted-div");
            let tripDeleted = "<?php echo $tripDeleted?>";
            if (tripDeleted) {
                deletedDiv.style.display = "block";
                document.getElementById("delete-text").innerHTML = tripName + " has been deleted.";
            }

            // set fading effect
            if (addedDiv.style.display === "block") {
                setTimeout(() => {
                    addedDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    addedDiv.style.display = "none";
                }, 3000);
            }

            if (deletedDiv.style.display === "block") {
                setTimeout(() => {
                    deletedDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    deletedDiv.style.display = "none";
                }, 3000);
            }
        </script>
    </body>
</html>