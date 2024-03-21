<?php 
ob_start();
date_default_timezone_set('Asia/Singapore');
if (!isset($_SESSION)) session_start();

// intialize variables 
$locationAdded = false;
$showLoginRequired = false;
$maxAttractions = false;
$maxEateries = false;
$placeID = isset($_GET['placeID']) ? $_GET['placeID'] : "";
$category = isset($_GET['category']) ? $_GET['category'] : "";

// connect to database 
include "connect-db.php";
connectDB();

// retrieve GET variables
if (isset($_GET['message'])) {
    $showLoginRequired = true;
    $_SESSION['addLocationDetails'] = true;
    $_SESSION['placeID'] = $_GET['placeID'];
    $_SESSION['category'] = $_GET['category'];
} else {
    $showLoginRequired = false;
    $_SESSION['addLocationDetails'] = false;
    unset($_SESSION['placeID']);
    unset($_SESSION['category']);
} 

if (isset($_GET['placeID']) && !isset($_SESSION['category'])) {
    if (isset($_GET['placeName'])) {
        $placeName = $_GET['placeName']; 
        $placeName = explode(",", $placeName);
        $placeName = trim($placeName[0]);
    }

    // check if place name exists in database 
    $query = "SELECT * FROM attractions WHERE placeID = '$placeID'";
    $result = mysqli_query($handler, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $attraction = mysqli_fetch_assoc($result);
        $category = "attraction";
    } else {
        $query = "SELECT * FROM eateries WHERE placeID = '$placeID'";
        $result = mysqli_query($handler, $query);

        if (mysqli_num_rows($result) > 0) {
            $eatery = mysqli_fetch_assoc($result);
            $category = "eatery";
        }   
    }

    // fetch details if placeID doesn't exist in database
    if (!isset($attraction) && !isset($eatery)) { 
        fetchLocationDetails($placeID, "", "");
        $category = $_SESSION['placeCategory'];
        // fetch nearby locations
        fetchNearbyLocations($_SESSION['placeLatitude'], $_SESSION['placeLongitude'], '', $category, $_SESSION['placeLocationID'], $_SESSION['placeArea']);
    }
}

// retrieve location details from database 
$query = "SELECT * FROM " . ($category == "attraction" ? "attractions" : "eateries") . " WHERE placeID = '$placeID'";
$result = mysqli_query($handler, $query);
$details = mysqli_fetch_assoc($result);
$locationID = $category == "attraction" ? $details['attractionID'] : $details['eateryID'];
$area = $details['location'];
$name = $details['name'];
$rating = $details['rating'];
$userRatingsTotal = $details['userRatingsTotal'];
$types = $details['types'];
$price = isset($details['priceLevel']) ? $details['priceLevel'] : "No price level available";
$address = $details['address'];
$lat = $details['latitude'];
$long = $details['longitude'];
$nearbyLocations = $details['nearbyLocations'];
$description = $details['description'];
$website = $details['website'];
$contactNum = $details['contactNum'];
$operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";

// check if add button is clicked
if (isset($_GET['tripName'])) {
    $locationAdded = true;

    // retrieve waypoints from database
    $tripName1 = $_GET['tripName'];
    $query = "SELECT waypoints FROM trips WHERE name='$tripName1'";
    $result = mysqli_query($handler, $query);
    while ($row = mysqli_fetch_array($result)) {
        $waypoints = $row[0];
    }
    
    if (is_null($waypoints)) { // first waypoint
        $waypoints = $_GET['location']; 

        // update waypoints of trip in database 
        $query = "UPDATE trips SET waypoints = '$waypoints' WHERE name='$tripName1'";
        mysqli_query($handler, $query);
    } else {
        $waypoints_array = explode('_ ', $waypoints); // array of waypoints
        $attractionCount = 0;
        $eateryCount = 0;

        if ($_GET['category'] == "attraction") {
            // count number of attractions
            foreach ($waypoints_array as $waypoint) {
                $query = "SELECT * FROM attractions WHERE name = '$waypoint'";
                $result = mysqli_query($handler, $query);
                $attraction = mysqli_fetch_all($result, MYSQLI_ASSOC);

                if (!empty($attraction)) {
                    $attractionCount += 1;
                }
            }

            if ($attractionCount > 24) {
                $maxAttractions = true;
            } else {
                $maxAttractions = false;
            }
        } else if ($_GET['category'] == "eatery") {
            // count number of eateries 
            foreach ($waypoints_array as $waypoint) {
                $query = "SELECT * FROM eateries WHERE name = '$waypoint'";
                $result = mysqli_query($handler, $query);
                $eatery = mysqli_fetch_all($result, MYSQLI_ASSOC);

                if (!empty($eatery)) {
                    $eateryCount += 1;
                }
            }

            if ($eateryCount > 24) {
                $maxEateries = true;
            } else {
                $maxEateries = false;
            }
        }
        
        // only allow adding of locations if max number is not reached 
        if ($_GET['category'] == "attraction" && $attractionCount <= 24 || $_GET['category'] == "eatery" && $eateryCount <= 24) {
            // make sure location is not added before
            if (!in_array($_GET['location'], $waypoints_array)) {
                $waypoints .= '_ ' . $_GET['location'];

                // update waypoints of trip in database and reset distance matrix and itinerary
                $query = "UPDATE trips SET 
                            waypoints = '$waypoints', 
                            distanceMatrix = NULL, 
                            itinerary = NULL 
                WHERE name='$tripName1'";
                mysqli_query($handler, $query);
            }
        } 
    }
} else {
    $locationAdded = false;
}

// retrieve other details from database 
if ($nearbyLocations == '') {
    fetchLocationDetails($placeID, $category, $locationID);
    fetchNearbyLocations($lat, $long, $nearbyLocations, $category, $locationID, $area);

    // retrieve updated details from database 
    $query = "SELECT * FROM " . ($category == "attraction" ? "attractions" : "eateries") . " WHERE placeID = '$placeID'";
    $result = mysqli_query($handler, $query);
    $details = mysqli_fetch_assoc($result);
    $nearbyLocations = $details['nearbyLocations'];
    $description = $details['description'];
    $website = $details['website'];
    $contactNum = $details['contactNum'];
    $operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";
} 

// check if user is logged in
$loggedIn = false;
if (isset($_SESSION['email'])) {
    $loggedIn = true;
    $email = $_SESSION['email'];
} else {
    $loggedIn = false;
}

// retrieve trips from database
if (isset($_SESSION['email'])) {
    $query = "SELECT * FROM trips WHERE userEmail='$email'";
    $result = mysqli_query($handler, $query);
    $trips = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// function to sanitize input
function sanitize($handler, $input) {
    $input = trim($input);
    $input = htmlentities($input);
    return mysqli_real_escape_string($handler, $input);
}

// function to fetch location details and store results in database
function fetchLocationDetails($placeID, $category, $locationID) {
    global $handler;

    // perform search query to Google Maps API
    $key = 'AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM';
    $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$placeID&key=$key";
    $response = file_get_contents($url);

    // check if request was successful 
    if ($response !== false) {
        $data = json_decode($response, true);

        // handle API reponse and extract details
        if ($data['status'] === 'OK') {
            $placeDetails = $data['result'];
            
            if ($category == "") {
                $name = sanitize($handler, $placeDetails['name']);
                $address = sanitize($handler, $placeDetails['formatted_address']);
                $placeID = $placeDetails['place_id'];
                $latitude = $placeDetails['geometry']['location']['lat'];
                $longitude = $placeDetails['geometry']['location']['lng'];
                $rating = isset($placeDetails['rating']) ? $placeDetails['rating'] : "No rating available";
                $user_ratings_total = isset($placeDetails['user_ratings_total']) ? $placeDetails['user_ratings_total'] : "No user ratings total available";
                $price_level = isset($placeDetailsn['price_level']) ? $placeDetails['price_level'] : "No price level available";
                $types = isset($placeDetails['types']) ? implode(", ", $placeDetails['types']) : "[]";
                $_SESSION['placeLatitude'] = $latitude;
                $_SESSION['placeLongitude'] = $longitude;
            }

            $description = isset($placeDetails['editorial_summary']) ? sanitize($handler, $placeDetails['editorial_summary']['overview']) : "No description available";
            $website = isset($placeDetails['website']) ? $placeDetails['website'] : "No website available";
            $contactNum = isset($placeDetails['international_phone_number']) ? $placeDetails['international_phone_number'] : "No contact number available";
            $operatingHours = isset($placeDetails['opening_hours']['weekday_text']) ? implode("_ ", $placeDetails['opening_hours']['weekday_text']) : "No operating hours available";
            $photos = isset($placeDetails['photos']) ? $placeDetails['photos'] : "No photos available";
            $reviews = isset($placeDetails['reviews']) ? $placeDetails['reviews'] : "No reviews available";

            if ($category == "") {
                if (isset($_SESSION['searchInput'])) {
                    $location1 = $_SESSION['searchInput'];
                } else if (isset($_SESSION['destination'])) {
                    $location1 = $_SESSION['destination'];
                }
                $_SESSION['placeArea'] = $location1;

                // check if place is an attraction or eatery 
                if (in_array('food', $placeDetails['types'])) { // eatery
                    $query = "INSERT INTO eateries (placeID, location, name, rating, userRatingsTotal, address, latitude, longitude, types, priceLevel, description, website, contactNum, operatingHours, nearbyLocations)
                            VALUES ('$placeID', '$location1', '$name', $rating, $user_ratings_total, '$address', '$latitude', '$longitude', '$types', '$price_level', '$description', '$website', '$contactNum', '$operatingHours', '')";
                    $_SESSION['placeCategory'] = "eatery";
                    $category = "eatery";
                } else { // attraction
                    $query = "INSERT INTO attractions (placeID, location, name, rating, userRatingsTotal, address, latitude, longitude, types, description, website, contactNum, operatingHours, nearbyLocations)
                        VALUES ('$placeID', '$location1', '$name', $rating, $user_ratings_total, '$address', '$latitude', '$longitude', '$types', '$description', '$website', '$contactNum', '$operatingHours', '')";
                    $_SESSION['placeCategory'] = "attraction";
                    $category = "attraction";
                }
                mysqli_query($handler, $query);

                // retrieve latest locationID inserted
                $locationID = mysqli_insert_id($handler);
                $_SESSION['placeLocationID'] = $locationID;
            } else {
                // update other location details in database
                if ($category == "attraction") {
                    $query = "UPDATE attractions SET
                            description = '" . $description . "', 
                            website = '" . $website . "', 
                            contactNum = '" . $contactNum . "',
                            operatingHours = '" . $operatingHours . "'
                        WHERE attractionID='$locationID'";
                } else {
                    $query = "UPDATE eateries SET
                            description = '" . $description . "', 
                            website = '" . $website . "', 
                            contactNum = '" . $contactNum . "',
                            operatingHours = '" . $operatingHours . "'
                        WHERE eateryID='$locationID'";
                }
                mysqli_query($handler, $query);
            }
            
            // insert photos into photos table if available 
            if ($photos !== "No photos available") {
                // retrieve existing photo from database if available
                if ($category == "attraction") {
                    $query = "SELECT url FROM photos WHERE attractionID = '$locationID'";
                } else {
                    $query = "SELECT url FROM photos WHERE eateryID = '$locationID'";
                }  
                $result = mysqli_query($handler, $query);
                while ($row = mysqli_fetch_assoc($result)) {
                    $existing_url = $row['url'];
                }

                foreach ($photos as $photo) {
                    $photo_reference = $photo['photo_reference'];
                    $photo_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=800&photoreference=$photo_reference&key=$key";
        
                    // only insert photo if it doesn't exist in database
                    if ((isset($existing_url) && ($photo_url !== $existing_url)) || !isset($existing_url)) {
                        if ($category == "attraction") {
                            $query = "INSERT INTO photos (attractionID, url) VALUES ('$locationID', '$photo_url')";
                        } else {
                            $query = "INSERT INTO photos (eateryID, url) VALUES ('$locationID', '$photo_url')";
                        }
                        mysqli_query($handler, $query);
                    }
                } 
            }
            
            // insert reviews into reviews table if available 
            if ($reviews !== "No reviews available") {
                foreach ($reviews as $review) {
                    $authorName = sanitize($handler, $review['author_name']);
                    $authorPic = $review['profile_photo_url'];
                    $authorRating = $review['rating']; 
                    $text = sanitize($handler, $review['text']);
            
                    if ($category == "attraction") {
                        $query = "INSERT INTO reviews (attractionID, authorName, authorPic, rating, text) VALUES ('$locationID', '$authorName', '$authorPic', '$authorRating', '$text')";
                    } else {
                        $query = "INSERT INTO reviews (eateryID, authorName, authorPic, rating, text) VALUES ('$locationID', '$authorName', '$authorPic', '$authorRating', '$text')";
                    }
                    mysqli_query($handler, $query);
                }
            }
        }
    } 
}

// function to find nearby locations 
function fetchNearbyLocations($lat, $long, $nearbyLocations, $category, $locationID, $area) {
    global $handler; 

    // perform search query to Google Maps API
    $key = 'AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM';
    $radius = 5000; // in meters
    $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=$lat,$long&radius=$radius&type=tourist_attraction&minrating=4&key=$key";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    // handle API reponse and extract details
    if ($data['status'] === 'OK') {
        $locations = $data['results'];

        foreach ($locations as $location) {
            $types = isset($location['types']) ? $location['types'] : [];
            $address = isset($location['vicinity']) ? $location['vicinity'] : "N/A";

            // check if lodging and travel agency are not included 
            if ((!in_array("lodging", $types)) && (!in_array("travel_agency", $types)) && (strpos($address, ',') !== false)) {
                // only suggest locations with all required details available 
                if (isset($location['name']) && isset($location['place_id']) && isset($location['geometry']['location']['lat']) && isset($location['geometry']['location']['lng']) 
                    && isset($location['rating']) && isset($location['user_ratings_total']) && isset($location['photos'])) {
                    $name = sanitize($handler, $location['name']);
                    $address = sanitize($handler, $location['vicinity']);
                    $placeID = $location['place_id'];
                    $latitude = $location['geometry']['location']['lat'];
                    $longitude = $location['geometry']['location']['lng'];
                    $rating = $location['rating'];
                    $user_ratings_total = $location['user_ratings_total'];
                    $types = implode(", ", $types);
                    $photos = $location['photos'];

                    // append location name to $nearbyLocations
                    $nearbyLocations .= $name . '_ ';

                    // retieve existing locations from database 
                    $query = "SELECT placeID FROM attractions";
                    $result = mysqli_query($handler, $query);
                    $placeIDs = array();
                    while ($row = mysqli_fetch_array($result)) {
                        array_push($placeIDs, $row[0]);
                    }

                    // insert results if location doesn't exist in database 
                    if (!in_array($placeID, $placeIDs)) {
                        $query = "INSERT INTO attractions (placeID, location, name, rating, userRatingsTotal, address, latitude, longitude, types, nearbyLocations)
                        VALUES ('$placeID', '$area', '$name', $rating, $user_ratings_total, '$address', '$latitude', '$longitude', '$types', '')";
                        mysqli_query($handler, $query);

                        // // retrieve latest attractionID inserted
                        $attractionID = mysqli_insert_id($handler);

                        // insert photos into photos table
                        foreach ($photos as $photo) {
                            $photo_reference = $photo['photo_reference'];
                            $photo_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=800&photoreference=$photo_reference&key=$key";
                            
                            // insert the photo url into the photos table
                            $query = "INSERT INTO photos (attractionID, url) VALUES ('$attractionID', '$photo_url')";
                            mysqli_query($handler, $query);
                        }
                    }
                }
            }    
        }

        // remove trailing underscore and whitespace
        $nearbyLocations = rtrim($nearbyLocations, '_ ');

        // update nearbyLocations in database 
        if ($category == "attraction") {
            $query = "UPDATE attractions SET nearbyLocations = '$nearbyLocations' WHERE attractionID='$locationID'";
        } else {
            $query = "UPDATE eateries SET nearbyLocations = '$nearbyLocations' WHERE eateryID='$locationID'";
        }
        mysqli_query($handler, $query);
    } else {
        echo "No locations found nearby.";
    }
}

// retrieve photos of location
$query = "SELECT url FROM photos WHERE " . ($category == "attraction" ? "attractionID" : "eateryID") . " = '$locationID'";
$result = mysqli_query($handler, $query);
$photos = mysqli_fetch_all($result, MYSQLI_ASSOC);

// retreive reviews of location
$query = "SELECT * FROM reviews WHERE " . ($category == "attraction" ? "attractionID" : "eateryID") . " = '$locationID'";
$result = mysqli_query($handler, $query);
$reviews = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Location Details</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    </head>

    <body>
        <?php include "header.php"; ?>

        <div class="wrapper" style="height: 70%; margin-bottom: -110px;">
            <div class="title-container">Location Details</div>
            <div style="transform: translate(0px, -23px);">
                <a href="explore.php" id="back-explore">< Back to Explore Page</a>
            </div>

            <div class="wrapper3">
                <!--images-->
                <div class="images-div">
                    <?php
                        // display images if photos are available 
                        if (isset($photos[1]['url'])) {
                            $url1 = $photos[1]['url'];
                        } else {
                            $url1 = "img/default-image.png";
                        }
                        if (isset($photos[2]['url'])) {
                            $url2 = $photos[2]['url'];
                        } else {
                            $url2 = "img/default-image.png";
                        }
                        if (isset($photos[3]['url'])) {
                            $url3 = $photos[3]['url'];
                        } else {
                            $url3 = "img/default-image.png";
                        }
                    ?>
                    <img id="img1" src='<?php echo $url1;?>'>
                    <img id="img2" src='<?php echo $url2;?>'>
                    <img id="img3" src='<?php echo $url3;?>'>
                    <br>
                    <div style="transform: translate(0px, -10px);">
                        <button id="see-photos" onclick="showPhotos()">See photos</button>
                    </div>
                </div>

                <!--location details-->
                <div class="details-display">
                    <div class="details-div">
                        <div>
                            <div style="float: left; line-height: 20px;">
                                <b id="details-title"><?php echo $name;?></b><br>
                                <span style="color: #808080; font-size: 13px;" >
                                <?php 
                                echo $rating . '&nbsp;';
                                if ($rating <= 1.2) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                } else if ($rating > 1.2 && $rating < 2) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                } else if ($rating <= 2.2) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                } else if ($rating > 2.2 && $rating < 3) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                } else if ($rating <= 3.2) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                } else if ($rating > 3.2 && $rating < 4) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                } else if ($rating <= 4.2) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                } else if ($rating > 4.2 && $rating < 5) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704"></i>';
                                    echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                } else if ($rating == 5) {
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                    echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                }
                     
                                ?>
                                (<?php echo number_format($userRatingsTotal);?>)<button id="see-reviews" onclick="showReviews()">See reviews</button>
                                </span><br>
                                <span style="color: #808080; font-size: 13px;">
                                <?php 
                                    if ($category == "attraction") {
                                        $types = explode(", ", $types);
                                        $types_string = "";
                                        $count = 0;
                                        foreach ($types as $type) {
                                            if (($type !== "tourist_attraction") && ($type !== "point_of_interest") && ($type !== "establishment") && ($type !== "store") && ($type !== "home_goods_store")) {
                                                $type = ucwords(str_replace("_", " ", $type));
                                                $types_string .= $type . " · ";
                                                $count += 1; 

                                                if ($count > 2) {
                                                    break;
                                                }
                                            }
                                        }
                                        if ($count == 0) {
                                            $types_string .= "Tourist Attraction";
                                        }
                                        $types_string = rtrim($types_string, " · ");
                                        echo $types_string;
                                    } else {
                                        if ($price !== "No price level available") {
                                            if ($price == "1") {
                                                echo '$ · ';
                                            } else if ($price == "2") {
                                                echo '$$ · ';
                                            } else if ($price == "3") {
                                                echo '$$$ · ';
                                            } else if ($price == "4") {
                                                echo '$$$$ · ';
                                            }
                                        }
                                        $types = explode(", ", $types);
                                        $types_string = "";
                                        $count = 0;
                                        foreach ($types as $type) {
                                            if (($type !== "point_of_interest") && ($type !== "food") && ($type !== "establishment") && ($type !== "meal_delivery") && ($type !== "meal_takeaway") && ($type !== "store")) {
                                                $type = ucwords(str_replace("_", " ", $type));
                                                $types_string .= $type . " · ";
                                                $count += 1;

                                                if ($count > 2) {
                                                    break;
                                                }
                                            }
                                        }
                                        if ($count == 0) {
                                            $types_string .= "Food";
                                        }
                                        $types_string = rtrim($types_string, " · ");
                                        echo $types_string;
                                    }
                                ?>
                                </span>
                            </div>
                            <?php 
                                if (!$loggedIn) {
                                    ?>
                                    <a href="location-details.php?message=showLoginRequired&placeID=<?php echo $placeID;?>&category=<?php echo $category;?>"><button class="add-to-btn">Add to&nbsp;&nbsp;<i class="fa-solid fa-angle-down"></i></button></a>
                                    <?php
                                } else {
                                    ?>
                                    <button class="add-to-btn" onclick="showAddDiv('add1-div')">Add to&nbsp;&nbsp;<i class="fa-solid fa-angle-down"></i></button>
                                    <?php
                                }
                            ?>
                            <div class="add-div" id="add1-div" style="display: none; transform: translate(594.5px, 32px);">
                                <?php
                                    if (!empty($trips)) {
                                        foreach ($trips as $trip) {
                                            if ($trip["startDate"] > date('Y-m-d')) { // upcoming trip
                                                $tripName = $trip["name"];
                                                echo '<a href="location-details.php?placeID='.$placeID.'&category='.$category.'&tripName='.$tripName.'&location='.urlencode($name).'">'.$tripName.'</a>';
                                            }
                                        }
                                    }
                                ?>
                                <a href="trip-details.php?fromExplore=true&location=<?php echo urlencode($name);?>&placeID=<?php echo $placeID;?>&category=<?php echo $category;?>" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;"><i class="fa-solid fa-plus"></i> Create New Trip</a>
                            </div>
                            <div style="clear: both;"></div>

                            <!--display message if users click add button without logging in-->
                            <div id="login-required-div1" style="display: none;">
                                <i class='fa-solid fa-right-to-bracket'></i><br>
                                <h3 style="margin-top: 20px;">Please log in to add location to trip.</h3>
                            </div>

                            <!--display successful message if location added to trip-->
                            <div id="location-added-div1" style="display: none;">
                                <i class='fa-regular fa-circle-check'></i><br>
                                <h3 id ="location-added-text" style="margin: auto; margin-top: 20px; width: 450px;"></h3>
                            </div>

                            <!--display max number of locations reached message-->
                            <div id="max-div1" style="display: none;">
                                <i class="fa-regular fa-circle-xmark"></i><br>
                                <h3 id="max-text" style="margin: auto; margin-top: 20px; width: 450px;"></h3>
                            </div>
                        </div>
            
                        <p id="description" style="margin-top: 6px; margin-bottom: -3px;">
                            <?php echo $description;?>
                        </p>

                        <div class="other-details">
                            <p style="transform: translate(30px, 8px);"><?php echo $address; ?></p>
                            <p style="transform: translate(30px, 7px);"><a href="<?php echo $website;?>" id="website"><?php echo $website;?></a></p>
                            <p style="transform: translate(30px, 6px);"><?php echo $contactNum;?></p>
                            <table style="transform: translate(26px, 5px);">
                                <tr>
                                    <td>Monday</td>
                                    <td>
                                        <?php 
                                            if ($operatingHours == "No operating hours available") {
                                                echo "No operating hours available";
                                            } else {
                                                $hours = explode(':', $operatingHours[0], 2);
                                                $hours = trim($hours[1]);
                                                echo $hours;
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Tuesday</td>
                                    <td>
                                        <?php 
                                            if ($operatingHours == "No operating hours available") {
                                                echo "No operating hours available";
                                            } else {
                                                $hours = explode(':', $operatingHours[1], 2);
                                                $hours = trim($hours[1]);
                                                echo $hours;
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Wednesday&nbsp;&nbsp;&nbsp;</td>
                                    <td>
                                        <?php 
                                            if ($operatingHours == "No operating hours available") {
                                                echo "No operating hours available";
                                            } else {
                                                $hours = explode(':', $operatingHours[2], 2);
                                                $hours = trim($hours[1]);
                                                echo $hours;
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Thursday</td>
                                    <td>
                                        <?php 
                                            if ($operatingHours == "No operating hours available") {
                                                echo "No operating hours available";
                                            } else {
                                                $hours = explode(':', $operatingHours[3], 2);
                                                $hours = trim($hours[1]);
                                                echo $hours;
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Friday</td>
                                    <td>
                                        <?php 
                                            if ($operatingHours == "No operating hours available") {
                                                echo "No operating hours available";
                                            } else {
                                                $hours = explode(':', $operatingHours[4], 2);
                                                $hours = trim($hours[1]);
                                                echo $hours;
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Saturday</td>
                                    <td>
                                        <?php 
                                            if ($operatingHours == "No operating hours available") {
                                                echo "No operating hours available";
                                            } else {
                                                $hours = explode(':', $operatingHours[5], 2);
                                                $hours = trim($hours[1]);
                                                echo $hours;
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Sunday</td>
                                    <td>
                                        <?php 
                                            if ($operatingHours == "No operating hours available") {
                                                echo "No operating hours available";
                                            } else {
                                                $hours = explode(':', $operatingHours[6], 2);
                                                $hours = trim($hours[1]);
                                                echo $hours;
                                            }
                                        ?>
                                    </td>
                                </tr>
                            </table>

                            <i class="fa-solid fa-map-location-dot" style="color: #75A3C8; font-size: 20px; transform: translate(0px, -231px);"></i><br>
                            <i class="fa-solid fa-globe" style="color: #75A3C8; font-size: 20px; transform: translate(0px, -222px);" ></i><br>
                            <i class="fa-solid fa-phone" style="color: #75A3C8; font-size: 20px; transform: translate(0px, -213px);"></i><br>
                            <i class="fa-solid fa-clock" style="color: #75A3C8; font-size: 20px; transform: translate(0px, -204px);"></i><br>
                        </div>
                    </div>
                </div>
                <div style="clear: both;"></div>

                <!--display photo slider-->
                <div id="photos-div" style="display: none;">
                    <button class="photos-btn" style="font-size: 24px; transform: translate(395px, -241px); padding: 10px 14px;" onclick="hidePhotos()"><i class="fa-solid fa-xmark"></i></button>
                    <button class="photos-btn" style="transform: translate(-430px, 0px);" id="previous-btn"><i class="fa-solid fa-angle-left"></i></button>
                    <button class="photos-btn" style="transform: translate(380px, 0px);" id="next-btn"><i class="fa-solid fa-angle-right"></i></button>
                    <div id="photos-slider">
                        <?php
                            if ($photos !== "No photos available") {
                                foreach($photos as $photo) {
                                    $url = $photo['url'];
                                    echo '<img class="photo-slide" src="'. $url .'">';
                                }
                            } else {
                                echo '<h3>No photos available</h3>';
                            }
                        ?>
                    </div>
                </div>

                <!--display reviews-->
                <div id="reviews-div" style="display: none;">
                    <button class="photos-btn" style="font-size: 24px; transform: translate(335px, -241px); padding: 10px 14px;" onclick="hideReviews()"><i class="fa-solid fa-xmark"></i></button>
                    <p id="reviews-name"><?php echo $name;?></p>
                    <p id="rewiews-address"><?php echo $address;?></p>
                    <div id="reviews-rating"><?php 
                        echo '<span style="font-size: 28px;">'.$rating.'</span>&nbsp;<p style="transform: translate(50px, -46px); font-size: 19px;">';
                        if ($rating <= 1.2) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                        } else if ($rating > 1.2 && $rating < 2) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                        } else if ($rating <= 2.2) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                        } else if ($rating > 2.2 && $rating < 3) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                        } else if ($rating <= 3.2) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                        } else if ($rating > 3.2 && $rating < 4) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                        } else if ($rating <= 4.2) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                        } else if ($rating > 4.2 && $rating < 5) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704"></i>';
                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                        } else if ($rating == 5) {
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                        }
                        ?></p>
                        <p style="font-size: 15px; transform: translate(168px, -81px);"><?php echo number_format($userRatingsTotal);?> reviews</p>
                    </div>
                    <div class="review-box">
                        <?php 
                            if ($reviews !== "No reviews available") {
                                foreach($reviews as $review) {
                                    echo '<div class="review-text">';
                                    $url = $review['authorPic'];
                                    echo '<img class="author-pic" src="'.$url.'">';
                                    echo '<p style="transform: translate(47px, -57px);">'.$review['authorName'].'</p>';
                                    echo '<div style="font-size: 11px; transform: translate(47px, -67px);">';
                                        $rating = $review['rating'];
                                        if ($rating <= 1.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                        } else if ($rating > 1.2 && $rating < 2) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                        } else if ($rating <= 2.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663"></i>';
                                        } else if ($rating > 2.2 && $rating < 3) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                        } else if ($rating <= 3.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                        } else if ($rating > 3.2 && $rating < 4) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                        } else if ($rating <= 4.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #FDD663;"></i>';
                                        } else if ($rating > 4.2 && $rating < 5) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #FDD663;"></i>';
                                        } else if ($rating == 5) {
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #FDD663;"></i>';
                                        }
                          
                                    echo '</div>';
                                    echo '<p class="author-text">'.$review['text'].'</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<h3>No reviews available</h3>';
                            }
                        ?>
                    </div>
                </div>
            </div>

            <!--nearby locations-->
            <div class="nearby-div">
                <div class="nearby-title">
                    <span style="color: black; font-size: 15px;"><b>Nearby Attractions</b></span>
                </div>
                <div class="location-display">
                    <button id="slide-left3" class="slide-btn"><i class="fa-solid fa-angle-left"></i></button>
                        <div class="nearby-display-block" id="nearby-list">
                            <?php 
                                $i = 1;
                                $nearbyLocations = explode('_ ', $nearbyLocations);

                                // retrieve waypoints from database 
                                $insertedLocations = array();
                                if (isset($_SESSION['email'])) {
                                    $query = "SELECT waypoints FROM trips WHERE userEmail='$email'";
                                    $result = mysqli_query($handler, $query);
                                    $waypointsDatabase = mysqli_fetch_all($result, MYSQLI_ASSOC);
                                    $insertedLocations = "";
                                    $k = 1;
                                    foreach ($waypointsDatabase as $added) {
                                        if (!is_null($added['waypoints'])) { 
                                            if ($k == 1) {
                                                $insertedLocations = $added['waypoints'];
                                            } else {
                                                $insertedLocations .= '_ ' . $added['waypoints'];
                                            }
                                            $k += 1;
                                        }
                                    }
                                    $insertedLocations = explode('_ ', $insertedLocations); // array of inserted locations
                                }
                                
                                foreach ($nearbyLocations as $location) {
                                    if ((isset($_SESSION['email']) && $location !== $name && !in_array($location, $insertedLocations)) || (!isset($_SESSION['email']) && $location !== $name)) {
                                        $i += 1;
                                        // retrieve location details from database 
                                        $query = "SELECT * FROM attractions WHERE name = '$location'";
                                        $result = mysqli_query($handler, $query);
                                        $details1 = mysqli_fetch_assoc($result);
                            ?>

                            <div class="location-box">
                                <div class="location-box-content" style="text-align: left;">
                                    <div class="location-title-div">
                                        <?php 
                                            if (!$loggedIn) {
                                                ?>
                                                <a href="location-details.php?message=showLoginRequired&placeID=<?php echo $placeID;?>&category=<?php echo $category;?>"><button class="add-btn" title="Add to trip" style="float: right;"><i class="fa-solid fa-plus" style="color: white;"></i></button></a>
                                                <?php
                                            } else {
                                                ?>
                                                <button class="add-btn" title="Add to trip" onclick="showAddDiv('add2-div<?php echo $i;?>')" style="float: right;"><i class="fa-solid fa-plus" style="color: white;"></i></button>
                                                <?php
                                            }
                                        ?>
                                        <div style="clear: both;"></div>
                                        <div class="add-div" id="add2-div<?php echo $i;?>" style="display: none;">
                                            <div class="add-text"><b>Add to</b></div>
                                            <?php
                                                if (!empty($trips)) {
                                                    foreach ($trips as $trip) {
                                                        if ($trip["startDate"] > date('Y-m-d')) { // upcoming trip
                                                            $tripName = $trip["name"];
                                                            echo '<a href="location-details.php?placeID='.$placeID.'&category='.$category.'&tripName='.$tripName.'&location='.urlencode($location).'">'.$tripName.'</a>';
                                                        }
                                                    }
                                                }
                                            ?>
                                            <a href="trip-details.php?fromExplore=true&location=<?php echo urlencode($location);?>&placeID=<?php echo $placeID;?>&category=<?php echo $category;?>" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;"><i class="fa-solid fa-plus"></i> Create New Trip</a>
                                        </div>
                                    </div>
                                    <a href='location-details.php?placeID=<?php echo $details1["placeID"];?>&category=attraction'>
                                    <?php 
                                        // retrieve photo of location
                                        $attractionID = $details1["attractionID"];
                                        $query = "SELECT url FROM photos WHERE attractionID = '$attractionID'";
                                        $result = mysqli_query($handler, $query);
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $photo_url = $row['url'];
                                        }
                                    ?>
                                    <img class='location-box-img' src='<?php echo $photo_url; ?>'><br>
                                    <p class="img_link">View Location Details</p></a>

                                    <div class="location-details" style="margin-top: 13px;">
                                        <div class="location-title-container">
                                            <span class="location-title"><b><?php echo $location;?></b></span>
                                        </div>
                                        <br><div style="margin-top: -14px;"><span style="color: #808080;">   
                                        <?php 
                                        $rating = $details1["rating"];
                                        echo $rating . '&nbsp;';
                                        if ($rating <= 1.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                        } else if ($rating > 1.2 && $rating < 2) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                        } else if ($rating <= 2.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                        } else if ($rating > 2.2 && $rating < 3) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                        } else if ($rating <= 3.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                        } else if ($rating > 3.2 && $rating < 4) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                        } else if ($rating <= 4.2) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-regular fa-star" style="color: #F7B704;"></i>';
                                        } else if ($rating > 4.2 && $rating < 5) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704"></i>';
                                            echo '<i class="fa-solid fa-star-half-stroke" style="color: #F7B704;"></i>';
                                        } else if ($rating == 5) {
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                            echo '<i class="fa-solid fa-star" style="color: #F7B704;"></i>';
                                        }
                                        ?>
                                        (<?php echo number_format($details1["userRatingsTotal"]);?>)</span></div>
                                        <br>
                                        <div style="line-height: 1em; margin-top: -26px; margin-bottom: 2px;">
                                            <?php 
                                                $types = $details1["types"];
                                                $types = explode(", ", $types);
                                                $types_string = "";
                                                $count = 0;
                                                foreach ($types as $type) {
                                                    if (($type !== "tourist_attraction") && ($type !== "point_of_interest") && ($type !== "establishment") && ($type !== "store") && ($type !== "home_goods_store")) {
                                                        $type = ucwords(str_replace("_", " ", $type));
                                                        $types_string .= $type . " · ";
                                                        $count += 1;

                                                        if ($count > 2) {
                                                            break;
                                                        }
                                                    }
                                                }
                                                if ($count == 0) {
                                                    $types_string .= "Tourist Attraction";
                                                }
                                                $types_string = rtrim($types_string, " · ");
                                                echo $types_string;
                                            ?>
                                        </div> 
                                    </div>

                                </div>
                            </div>
                            
                            <?php
                                    }
                                }
                            ?>
                        </div>
                    <button id="slide-right3" class="slide-btn"><i class="fa-solid fa-angle-right"></i></button>
                </div>
            </div>
        </div>

        <?php include "footer.php";?>

        <script>
            window.onload = underlineExplore();
            function underlineExplore() {
                let div = document.getElementById("nav-explore");
                div.style.display = "block";
            }

            // handle photos slider 
            $(document).ready(function() {
                // add class 'active' to current photo
                $('.photo-slide').first().addClass('active');
                $('.photo-slide').hide(); 
                $('.active').show();

                // hide previous button on first slide 
                $('#previous-btn').hide();

                // assign class 'active' to next photo when next button is clicked 
                $('#next-btn').click(function() {
                    $('.active').removeClass('active').addClass('lastActive');
                    if ($('.lastActive').is(':last-child')) {
                        $('.photo-slide').first().addClass('active');
                    } else {
                        $('.lastActive').next().addClass('active');
                    }
                    $('.lastActive').removeClass('lastActive');
                    $('.photo-slide').hide(); 
                    $('.active').show();

                    // show previous button if not on first slide 
                    $('#previous-btn').show();

                    // hide next button on last slide 
                    if ($('.active').is(':last-child')) {
                        $('#next-btn').hide();
                    }
                });

                // assign class 'active' to previous photo when previous button is clicked
                $('#previous-btn').click(function() {
                    $('.active').removeClass('active').addClass('lastActive');
                    if ($('.lastActive').is(':first-child')) {
                        $('.photo-slide').last().addClass('active');
                    } else {
                        $('.lastActive').prev().addClass('active');
                    }
                    $('.lastActive').removeClass('lastActive');
                    $('.photo-slide').hide();
                    $('.active').show();

                    // show next button if not on last slide
                    $('#next-btn').show();

                    // hide previous button on first slide
                    if ($('.active').is(':first-child')) {
                        $('#previous-btn').hide();
                    }
                });
            });

            // function to show photos when "see photos" button is clicked 
            function showPhotos() {
                document.getElementById("photos-div").style.display = "block";
            } 

            // function to hide photos when x button is clicked
            function hidePhotos() {
                document.getElementById("photos-div").style.display = "none";
            } 

            // function to show reviews when "see reviews" button is clicked 
            function showReviews() {
                document.getElementById("reviews-div").style.display = "block";
            } 

            // function to hide reviews when x button is clicked 
            function hideReviews() {
                document.getElementById("reviews-div").style.display = "none";
            } 

            // scroll left and right 
            let leftArrow3 = document.getElementById("slide-left3");
            let rightArrow3 = document.getElementById("slide-right3");

            leftArrow3.onclick = function() {
                document.getElementById("nearby-list").scrollLeft -= 200;
            };
            rightArrow3.onclick = function() {
                document.getElementById('nearby-list').scrollLeft += 200;
            };

             // function to save scroll position 
             function saveScrollPosition() {
                let position = document.getElementById("nearby-list").scrollLeft;
                localStorage.setItem("position", position);

                let placeID = <?php echo "'" . $placeID . "'"?>;
                localStorage.setItem("placeID", placeID);
            }

            // function to restore scroll position when page is reloaded
            function restoreScrollPosition() {
                let savedPosition = localStorage.getItem("position");
                let placeID = localStorage.getItem("placeID");
                let currentPlaceID = <?php echo "'" . $placeID . "'"; ?>;
                if (savedPosition !== null && currentPlaceID === placeID) {
                    document.getElementById("nearby-list").scrollLeft = parseInt(savedPosition);
                }
            }

            // show scroll position when page reloads 
            document.getElementById("nearby-list").addEventListener("scroll", saveScrollPosition);
            window.addEventListener("load", restoreScrollPosition);

            // display login message if user clicks add button without logging in
            let loginDiv = document.getElementById("login-required-div1");
            let showLoginRequired = "<?php echo $showLoginRequired?>";
            if (showLoginRequired) {
                loginDiv.style.display = "block";
            }

            // set fading effect
            if (loginDiv.style.display === "block") {
                setTimeout(() => {
                    loginDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    loginDiv.style.display = "none";
                }, 3000);
            }

            // function to show add div
            function showAddDiv(number) {
                let div = document.getElementById(number);
                if (div.style.display === "none") {
                    div.style.display = "block";
                } else {
                    div.style.display = "none";
                }
            }

            // display successful message if location added to trip 
            let addedDiv = document.getElementById("location-added-div1");
            let locationAdded = "<?php echo $locationAdded?>";
            <?php 
                if (isset($_GET['tripName']) && isset($_GET['location'])) {
            ?>
            if (locationAdded) {
                addedDiv.style.display = "block";
                document.getElementById("location-added-text").innerHTML = "<?php echo $_GET['location']; ?>" + " has been added to " + "<?php echo $_GET['tripName']; ?>" + "!";
            }
            <?php
                }
            ?>

            // set fading effect
            if (addedDiv.style.display === "block") {
                setTimeout(() => {
                    addedDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    addedDiv.style.display = "none";
                }, 3000);
            }

            // display max number of locations reached message 
            let maxDiv = document.getElementById("max-div1");
            let maxAttractions = "<?php echo $maxAttractions?>";
            let maxEateries = "<?php echo $maxEateries?>";

            if (maxAttractions) {
                maxDiv.style.display = "block";
                document.getElementById("max-text").innerHTML = "Only a maximum of 24 attractions is allowed to be added to a trip.";
            } else if (maxEateries) {
                maxDiv.style.display = "block";
                document.getElementById("max-text").innerHTML = "Only a maximum of 24 eateries is allowed to be added to a trip.";
            } 

            // set fading effect
            if (maxDiv.style.display === "block") {
                setTimeout(() => {
                    maxDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    maxDiv.style.display = "none";
                }, 3000);
            }
        </script>
    </body>
</html>