<?php
ob_start();
date_default_timezone_set('Asia/Singapore');

if (!isset($_SESSION)) session_start();

// intialize variables 
$locationAdded = false;
$showLoginRequired = false;
$attractionsFound = true;
$eateriesFound = true; 
$maxAttractions = false;
$maxEateries = false;

// connect to database 
include "connect-db.php";
connectDB(); 

// retrieve GET variables
if (isset($_GET['destination'])) {
    $_SESSION['destination'] = $_GET['destination'];
}

if (isset($_GET['startDate'])) {
    $_SESSION['startDate'] = $_GET['startDate'];
}

if (isset($_GET['endDate'])) {
    $_SESSION['endDate'] = $_GET['endDate'];
}

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
                // retrieve location details from database
                $placeID = $_GET['placeID'];
                $category = $_GET['category'];
                $query = "SELECT * FROM " . ($category == "attraction" ? "attractions" : "eateries") . " WHERE placeID = '$placeID'";
                $result = mysqli_query($handler, $query);
                $details = mysqli_fetch_assoc($result);
                
                // check if operating hours have been fetched
                if (is_null($details['operatingHours'])) {
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
                }
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

if (isset($_GET['message'])) {
    $showLoginRequired = true;
    $_SESSION['addExplore'] = true;
} else {
    $showLoginRequired = false;
    $_SESSION['addExplore'] = false;
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

// function to search for locations and store results in database
function fetchLocations($searchInput, $group, $ratingFilter, $categoryFilter, $maxPriceFilter) {
    global $handler;

    // retrieve all attractions and eateries in specified location
    $location = "";
    $attraction_placeIDs = array();
    $eatery_placeIDs = array();

    if (isset($_SESSION['searchInput'])) {
        $location = $_SESSION['searchInput'];
    } else if (isset($_SESSION['destination'])) {
        $location = $_SESSION['destination'];
    }

    if ($location !== "") {
        $attraction_query = "SELECT * FROM attractions WHERE location LIKE '%$location%'";
        $attraction_result = mysqli_query($handler, $attraction_query);
        $attractions = mysqli_fetch_all($attraction_result, MYSQLI_ASSOC);
        foreach($attractions as $attraction) {
            array_push($attraction_placeIDs, $attraction['placeID']);
        }

        $eatery_query = "SELECT * FROM eateries WHERE location LIKE '%$location%'";
        $eatery_result = mysqli_query($handler, $eatery_query);
        $eateries = mysqli_fetch_all($eatery_result, MYSQLI_ASSOC);
        foreach($eateries as $eatery) {
            array_push($eatery_placeIDs, $eatery['placeID']);
        }
    }

    // perform search query to Google Maps API
    $search_query = urlencode("Best $group in $searchInput");
    $key = 'AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM'; 
    $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query=$search_query&key=$key";

    // include filters in query string if applicable 
    if ($ratingFilter !== "") {
        $url .= "&minrating=$ratingFilter";
    }
    if ($categoryFilter !== "") {
        $url .= "&type=$categoryFilter";
    }
    if ($maxPriceFilter !== "") {
        $url .= "&maxprice=$maxPriceFilter";
    }

    $response = file_get_contents($url);
    $data = json_decode($response, true);

    // handle API reponse and extract details
    if ($data['status'] === 'OK') {
        $locations = $data['results'];

        foreach ($locations as $location) {
            // only suggest locations with all required details available
            if (isset($location['name']) && isset($location['formatted_address']) && isset($location['place_id']) && isset($location['geometry']['location']['lat']) && 
                isset($location['geometry']['location']['lng']) && isset($location['rating']) && isset($location['user_ratings_total']) && isset($location['photos'])) {
                $name = sanitize($handler, $location['name']);
                $address = sanitize($handler, $location['formatted_address']);
                $placeID = $location['place_id'];
                $latitude = $location['geometry']['location']['lat'];
                $longitude = $location['geometry']['location']['lng'];
                $rating = $location['rating'];
                $user_ratings_total = $location['user_ratings_total'];
                $price_level = isset($location['price_level']) ? $location['price_level'] : "No price level available";
                $types = isset($location['types']) ? implode(", ", $location['types']) : "[]";
                $photos = $location['photos'];

                // insert results into database 
                if ($group == "Attractions") {
                    // check if location exists before adding to database
                    if (!in_array($placeID, $attraction_placeIDs)) {
                        $query = "INSERT INTO attractions (placeID, location, name, rating, userRatingsTotal, address, latitude, longitude, types, nearbyLocations)
                            VALUES ('$placeID', '$searchInput', '$name', $rating, $user_ratings_total, '$address', '$latitude', '$longitude', '$types', '')";
                        mysqli_query($handler, $query);

                        // retrieve latest attractionID inserted
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
                } else if ($group == "Food") {
                    // check if location exists before adding to database
                    if (!in_array($placeID, $eatery_placeIDs)) {
                        $query = "INSERT INTO eateries (placeID, location, name, rating, userRatingsTotal, address, latitude, longitude, types, priceLevel, nearbyLocations)
                            VALUES ('$placeID', '$searchInput', '$name', $rating, $user_ratings_total, '$address', '$latitude', '$longitude', '$types', '$price_level', '')";
                        mysqli_query($handler, $query);

                        // retrieve latest eateryID inserted
                        $eateryID = mysqli_insert_id($handler);

                        // insert photos into photos table
                        foreach ($photos as $photo) {
                            $photo_reference = $photo['photo_reference'];
                            $photo_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=800&photoreference=$photo_reference&key=$key";
                            
                            // insert the photo url into the photos table
                            $query = "INSERT INTO photos (eateryID, url) VALUES ('$eateryID', '$photo_url')";
                            mysqli_query($handler, $query);
                        }
                    }
                }
            }
        } 
        return true;
    } else {
        return false;
    }
}

// check if search button is clicked 
if (isset($_POST['search'])) {
    // retrieve place ID and types of selected place from hidden fields
    $placeTypes = $_POST['place-types'];
    $placePlaceID = $_POST['place-id'];

    if (strpos($placeTypes, "locality") !== false || strpos($placeTypes, "sublocality") !== false || strpos($placeTypes, "administrative_area_level_1") !== false || strpos($placeTypes, "administrative_area_level_2") !== false || strpos($placeTypes, "administrative_area_level_3") !== false) {
        // selected place is a city 
        $searchInput = sanitize($handler, $_POST['searchInput']);
        $_SESSION['searchInput'] = $searchInput;
        
        if (isset($_SESSION['filtersApplied']) && $_SESSION['filtersApplied'] == false) {
            // check if search input keyword exists in database
            $query = "SELECT * FROM attractions WHERE location LIKE '%$searchInput%'";
            $result = mysqli_query($handler, $query);
            $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

            $query = "SELECT * FROM eateries WHERE location LIKE '%$searchInput%'";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (empty($attractions)) {
                // send API request to search for attractions
                $attractionsFound = fetchLocations($searchInput, "Attractions", "", "", "");

                // retrieve attractions from database
                $query = "SELECT * FROM attractions WHERE location LIKE '%$searchInput%'";
                $result = mysqli_query($handler, $query);
                $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }

            if (empty($eateries)) {
                // send API request to search for attractions
                $eateriesFound = fetchLocations($searchInput, "Food", "", "", "");

                // retrieve eateries from database
                $query = "SELECT * FROM eateries WHERE location LIKE '%$searchInput%'";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            } 
        }
    } else if (strpos($placeTypes, "point_of_interest") !== false || strpos($placeTypes, "establishment") !== false) {
        // selected place is a specific place
        if (!isset($_SESSION['destination']) && !isset($_SESSION['searchInput'])) {
            $searchError = "Please specify a city to visit first";
        } else {
            $placeName = sanitize($handler, $_POST['searchInput']);
            header("Location: location-details.php?placeID=$placePlaceID&placeName=$placeName");
        } 
    } else {
        // not a city or specific place 
        $searchError = "Please enter a valid city or specific place";
    }
}

// check if apply button is clicked 
if (isset($_POST['apply'])) {
    $locationAdded = false;
    
    // perform form validation 
    $errors = 0; // error count

    // check if at least one filter is applied 
    if (empty($_POST['attraction-category']) && empty($_POST['attraction-rating']) && empty($_POST['eatery-category']) && empty($_POST['eatery-rating']) && empty($_POST['maxprice'])) {
        $noFiltersError = "At least one filter must be applied";
        $errors += 1;
    }

    // validate attraction rating 
    if (!empty($_POST['attraction-rating'])) {
        if ($_POST['attraction-rating'] < 0 || $_POST['attraction-rating'] > 5) {
            $attractionRatingError = "Rating cannot be less than 0 or more than 5";
            $errors += 1;
        } else {
            $attractionRating = $_POST['attraction-rating'];
        }
    }

    // validate eatery rating 
    if (!empty($_POST['eatery-rating'])) {
        if ($_POST['eatery-rating'] < 0 || $_POST['eatery-rating'] > 5) {
            $eateryRatingError = "Rating cannot be less than 0 or more than 5";
            $errors += 1;
        } else {
            $eateryRating = $_POST['eatery-rating'];
        }
    }

    // attraction category 
    if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Amusement Park") {
        $attractionCategory = "amusement_park";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Aquarium") {
        $attractionCategory = "aquarium";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Art Gallery") {
        $attractionCategory = "art_gallery";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Landmark") {
        $attractionCategory = "tourist_attraction";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Library") {
        $attractionCategory = "library";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Movie Theatre") {
        $attractionCategory = "movie_theater";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Museum") {
        $attractionCategory = "museum";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Park") {
        $attractionCategory = "park";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Shopping Mall") {
        $attractionCategory = "shopping_mall";
    } else if (!empty($_POST['attraction-category']) && $_POST['attraction-category'] == "Zoo") {
        $attractionCategory = "zoo";
    }

    // eatery category 
    if (!empty($_POST['eatery-category']) && $_POST['eatery-category'] == "Bakery") {
        $eateryCategory = "bakery";
    } else if (!empty($_POST['eatery-category']) && $_POST['eatery-category'] == "Bar") {
        $eateryCategory = "bar";
    } else if (!empty($_POST['eatery-category']) && $_POST['eatery-category'] == "Cafe") {
        $eateryCategory = "cafe";
    } else if (!empty($_POST['eatery-category']) && $_POST['eatery-category'] == "Restaurant") {
        $eateryCategory = "restaurant";
    } else if (!empty($_POST['eatery-category']) && $_POST['eatery-category'] == "Supermarket") {
        $eateryCategory = "supermarket";
    }

    // supermarket has no price level 
    if (!empty($_POST['eatery-category']) && $_POST['eatery-category'] == "Supermarket" && !empty($_POST['maxprice'])) {
        $categoryError = "Supermarket has no price level available";
        $errors += 1;
    }

    if (!empty($_POST['maxprice'])) {
        $maxPrice = $_POST['maxprice'];
    }
   
    // if no errors found 
    if ($errors == 0) {
        $_SESSION['filtersApplied'] = true;

        // check if filtered places exist in database 
        $location = "";
        if (isset($_SESSION['searchInput'])) {
            $location = $_SESSION['searchInput'];
        } else if (isset($_SESSION['destination'])) {
            $location = $_SESSION['destination'];
        }

        // retrieve all attractions and eateries by default 
        $query = "SELECT * FROM attractions WHERE location LIKE '%$location%'";
        $result = mysqli_query($handler, $query);
        $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $query = "SELECT * FROM eateries WHERE location LIKE '%$location%'";
        $result = mysqli_query($handler, $query);
        $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

        // attraction rating filter 
        if (!empty($_POST['attraction-rating']) && empty($_POST['attraction-category'])) {
            $query = "SELECT * FROM attractions WHERE location LIKE '%$location%' AND rating >= $attractionRating";
            $result = mysqli_query($handler, $query);
            $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($attractions) < 5) {
                // fetch more attractions with specified filters
                $attractionsFound = fetchLocations($location, "Attractions", $attractionRating, "", "");
                $query = "SELECT * FROM attractions WHERE location LIKE '%$location%' AND rating >= $attractionRating";
                $result = mysqli_query($handler, $query);
                $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

                if (empty($attractions)) {
                    $attractionsFound = false;
                }
            }
        }
        
        // attraction category filter 
        if (!empty($_POST['attraction-category']) && empty($_POST['attraction-rating'])) {
            $query = "SELECT * FROM attractions WHERE location LIKE '%$location%' AND types LIKE '%$attractionCategory%'";
            $result = mysqli_query($handler, $query);
            $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($attractions) < 5) {
                // fetch more attractions with specified filters
                $attractionsFound = fetchLocations($location, "Attractions", "", $attractionCategory, "");
                $query = "SELECT * FROM attractions WHERE location LIKE '%$location%' AND types LIKE '%$attractionCategory%'";
                $result = mysqli_query($handler, $query);
                $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

                if (empty($attractions)) {
                    $attractionsFound = false;
                }
            }
        }

        // both attraction rating and category filters 
        if (!empty($_POST['attraction-category']) && !empty($_POST['attraction-rating'])) {
            $query = "SELECT * FROM attractions WHERE location LIKE '%$location%' AND types LIKE '%$attractionCategory%' AND rating >= $attractionRating";
            $result = mysqli_query($handler, $query);
            $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($attractions) < 5) {
                // fetch more attractions with specified filters
                $attractionsFound = fetchLocations($location, "Attractions", $attractionRating, $attractionCategory, "");
                $query = "SELECT * FROM attractions WHERE location LIKE '%$location%' AND types LIKE '%$attractionCategory%' AND rating >= $attractionRating";
                $result = mysqli_query($handler, $query);
                $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

                if (empty($attractions)) {
                    $attractionsFound = false;
                }
            }
        }

        // eatery rating filter 
        if (!empty($_POST['eatery-rating']) && empty($_POST['maxprice']) && empty($_POST['eatery-category'])) {
            $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($eateries) < 5) {
                // fetch more eateries with specified filters
                $eateriesFound = fetchLocations($location, "Food", $eateryRating, "", "");
                $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        }

        // eatery price filter 
        if  (!empty($_POST['maxprice']) && empty($_POST['eatery-rating']) && empty($_POST['eatery-category'])) {
            $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($eateries) < 5) {
                // fetch more eateries with specified filters
                $eateriesFound = fetchLocations($location, "Food", "", "", $maxPrice);
                $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        }

        // eatery category filter 
        if (!empty($_POST['eatery-category']) && empty($_POST['eatery-rating']) && empty($_POST['maxprice'])) {
            $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND types LIKE '%$eateryCategory%'";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($eateries) < 5) {
                // fetch more eateries with specified filters
                $eateriesFound = fetchLocations($location, "Food", "", $eateryCategory, "");
                $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND types LIKE '%$eateryCategory%'";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        }

        // eatery rating and price filters 
        if (!empty($_POST['eatery-rating']) && !empty($_POST['maxprice']) && empty($_POST['eatery-category'])) {
            $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($eateries) < 5) {
                // fetch more eateries with specified filters
                $eateriesFound = fetchLocations($location, "Food", $eateryRating, "", $maxPrice);
                $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }       
        }

        // eatery rating and category filters 
        if (!empty($_POST['eatery-rating']) && empty($_POST['maxprice']) && !empty($_POST['eatery-category'])) {
            $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating AND types LIKE '%$eateryCategory%'";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($eateries) < 5) {
                // fetch more eateries with specified filters
                $eateriesFound = fetchLocations($location, "Food", $eateryRating, $eateryCategory, "");
                $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating AND types LIKE '%$eateryCategory%'";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        }

        // eatery price and category filters 
        if (empty($_POST['eatery-rating']) && !empty($_POST['maxprice']) && !empty($_POST['eatery-category'])) {
            $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND types LIKE '%$eateryCategory%' AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($eateries) < 5) {
                // fetch more eateries with specified filters
                $eateriesFound = fetchLocations($location, "Food", "", $eateryCategory, $maxPrice);
                $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND types LIKE '%$eateryCategory%' AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        }   

        // all eatery rating, price, and category filters 
        if (!empty($_POST['eatery-rating']) && !empty($_POST['maxprice']) && !empty($_POST['eatery-category'])) {
            $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating AND types LIKE '%$eateryCategory%' AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
            $result = mysqli_query($handler, $query);
            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

            if (count($eateries) < 5) {
                // fetch more eateries with specified filters
                $eateriesFound = fetchLocations($location, "Food", $eateryRating, $eateryCategory, $maxPrice);
                $query = "SELECT * FROM eateries WHERE location LIKE '%$location%' AND rating >= $eateryRating AND types LIKE '%$eateryCategory%' AND (priceLevel != 'No price level available' AND priceLevel <= $maxPrice)";
                $result = mysqli_query($handler, $query);
                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        }
    } else {
        $_SESSION['filtersApplied'] = false;
    }
} else {
    $_SESSION['filtersApplied'] = false;
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Explore</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
        <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&key=AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM"></script>
    </head>

    <body> 
        <?php include "header.php"; ?>

        <div class="wrapper" style="width: 90%; height: auto; overflow: hidden;">
            <div class="title-container" style="transform: translate(75px, 0px);">Explore</div>
            <form id="search-location-form" action="explore.php" method="post"> 
                <div class="search-div">
                    <input class="form-input destination" style="font-size: 14px; height: 38px;" type="text" id="destination" name="searchInput" placeholder="Enter a city/region or specific place" required>   
                    <!--hidden fields to store placeID and types of selected place-->
                    <input type="hidden" id="place-types" name="place-types">
                    <input type="hidden" id="place-id" name="place-id">
                    <button id="search-btn" type="submit" name="search"><i class="fa-solid fa-magnifying-glass"></i>&nbsp;&nbsp;Search</button>
                    <?php
                        if (isset($searchError)) {
                            echo '<div class="form-error" style="transform: translate(104px, 43px); text-align: left;">';
                            echo $searchError;
                            echo "</div>";
                            unset($searchError);
                        }
                    ?>
                </div>
            </form>
            <div style="transform: translate(391px, -69px); text-align: left;">
                <i class="fa-solid fa-location-dot"></i>&nbsp;<span class="location-name">
                    <?php 
                    if (isset($_SESSION['searchInput'])) {
                        echo $_SESSION['searchInput'];
                        $stored = $_SESSION['searchInput'];

                        if (isset($_SESSION['filtersApplied']) && $_SESSION['filtersApplied'] == false) {
                            // retrieve attractions from database
                            $query = "SELECT * FROM attractions WHERE location LIKE '%$stored%'";
                            $result = mysqli_query($handler, $query);
                            $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

                            // retrieve eateries from database
                            $query = "SELECT * FROM eateries WHERE location LIKE '%$stored%'";
                            $result = mysqli_query($handler, $query);
                            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
                        }
                    } else if (isset($_SESSION['destination'])) { // from home page
                        echo $_SESSION['destination'];
                        $stored = $_SESSION['destination'];

                        if (isset($_SESSION['filtersApplied']) && $_SESSION['filtersApplied'] == false) {
                            // retrieve attractions from database
                            $query = "SELECT * FROM attractions WHERE location LIKE '%$stored%'";
                            $result = mysqli_query($handler, $query);
                            $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);

                            // retrieve eateries from database
                            $query = "SELECT * FROM eateries WHERE location LIKE '%$stored%'";
                            $result = mysqli_query($handler, $query);
                            $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);

                            if (empty($attractions)) {
                                // send API request to search for attractions
                                $attractionsFound = fetchLocations($stored, "Attractions", "", "", "");
                    
                                // retrieve attractions from database
                                $query = "SELECT * FROM attractions WHERE location LIKE '%$stored%'";
                                $result = mysqli_query($handler, $query);
                                $attractions = mysqli_fetch_all($result, MYSQLI_ASSOC);
                            }
                    
                            if (empty($eateries)) {
                                // send API request to search for attractions
                                $eateriesFound = fetchLocations($stored, "Food", "", "", "");
                    
                                // retrieve eateries from database
                                $query = "SELECT * FROM eateries WHERE location LIKE '%$stored%'";
                                $result = mysqli_query($handler, $query);
                                $eateries = mysqli_fetch_all($result, MYSQLI_ASSOC);
                            } 
                        }
                    } else {
                        echo "No city/region entered";
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="wrapper2">
            <!--filter section-->
            <div class="filter-section">
                <form actions="explore.php" method="post">
                    <!--filter section for attraction-->
                    <div class="attraction-filter">
                        <div class="filter-location" style="margin-top: -10px">
                            <div class="filter-title">Attractions</div>
                        </div>

                        <div class="filter-location">
                            <span><b>Category:</b></span>
                            <div class="category">
                                <div style="display: inline-block; float: left;">
                                    <input type="radio" id="amusement-park" name="attraction-category" value="Amusement Park" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Amusement Park") echo 'checked="checked"'; ?>>
                                    <label for="amusement-park">Amusement Park</label><br>
                                    <input type="radio" id="aquarium" name="attraction-category" value="Aquarium" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Aquarium") echo 'checked="checked"'; ?>>
                                    <label for="aquarium">Aquarium</label><br>
                                    <input type="radio" id="art-gallery" name="attraction-category" value="Art Gallery" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Art Gallery") echo 'checked="checked"'; ?>>
                                    <label for="art-gallery">Art Gallery</label><br>
                                    <input type="radio" id="landmark" name="attraction-category" value="Landmark"<?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Landmark") echo 'checked="checked"'; ?>>
                                    <label for="landmark">Landmark</label><br>
                                    <input type="radio" id="library" name="attraction-category" value="Library" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Library") echo 'checked="checked"'; ?>>
                                    <label for="library">Library</label><br>
                                </div>
                                    
                                <div style="display: inline-block; float: right;">
                                    <input type="radio" id="movie-theatre" name="attraction-category" value="Movie Theatre" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Movie Theatre") echo 'checked="checked"'; ?>>
                                    <label for="movie-theatre">Movie Theatre</label><br>
                                    <input type="radio" id="museum" name="attraction-category" value="Museum" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Museum") echo 'checked="checked"'; ?>>
                                    <label for="museum">Museum</label><br>
                                    <input type="radio" id="park" name="attraction-category" value="Park" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Park") echo 'checked="checked"'; ?>>
                                    <label for="park">Park</label><br>
                                    <input type="radio" id="shopping-mall" name="attraction-category" value="Shopping Mall" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Shopping Mall") echo 'checked="checked"'; ?>>
                                    <label for="shopping-mall">Shopping Mall</label><br>
                                    <input type="radio" id="zoo" name="attraction-category" value="Zoo" <?php if (isset($_POST['attraction-category']) && $_POST['attraction-category'] == "Zoo") echo 'checked="checked"'; ?>>
                                    <label for="zoo">Zoo</label> 
                                </div>
                                <div style="clear: both;"></div>  
                            </div>
                        </div>

                        <div class="filter-location">
                            <span><b>Rating:</b></span>
                            &nbsp;
                            <input class="rating" type="number" step="any" id="attraction-rating" name="attraction-rating" value="<?php if (isset($_POST['attraction-rating'])) echo $_POST['attraction-rating'] ?>">
                            <i class="fa-solid fa-star" style="color: #fbbc04;"></i>&nbsp;and Up
                            <?php
                                if (isset($attractionRatingError)) {
                                    echo '<div class="form-error" style="width: 127px; transform: translate(-4px, -11px);">';
                                    echo $attractionRatingError;
                                    echo "</div>";
                                    unset($attractionRatingError);
                                }
                            ?>
                        </div>
                    </div>

                    <!--filter section for restaurant-->
                    <div class="restaurant-filter">
                        <div class="filter-location">
                            <br><br>
                            <div class="filter-title" style="margin-top: -11px;">Eateries</div>
                        </div>

                        <div class="filter-location">
                            <span><b>Category:</b></span>
                            <div class="category">
                                <input type="radio" id="bakery" name="eatery-category" value="Bakery" <?php if (isset($_POST['eatery-category']) && $_POST['eatery-category'] == "Bakery") echo 'checked="checked"'; ?>>
                                <label for="bakery">Bakery</label><br>
                                <input type="radio" id="bar" name="eatery-category" value="Bar" <?php if (isset($_POST['eatery-category']) && $_POST['eatery-category'] == "Bar") echo 'checked="checked"'; ?>>
                                <label for="bar">Bar</label><br>
                                <input type="radio" id="cafe" name="eatery-category" value="Cafe" <?php if (isset($_POST['eatery-category']) && $_POST['eatery-category'] == "Cafe") echo 'checked="checked"'; ?>>
                                <label for="cafe">Cafe</label><br>
                                <input type="radio" id="restaurant" name="eatery-category" value="Restaurant" <?php if (isset($_POST['eatery-category']) && $_POST['eatery-category'] == "Restaurant") echo 'checked="checked"'; ?>>
                                <label for="restaurant">Restaurant</label><br>
                                <input type="radio" id="supermarket" name="eatery-category" value="Supermarket" <?php if (isset($_POST['eatery-category']) && $_POST['eatery-category'] == "Supermarket") echo 'checked="checked"'; ?>>
                                <label for="supermarket">Supermarket</label><br>
                                <?php
                                if (isset($categoryError)) {
                                    echo '<div class="form-error" style="width: 118px; transform: translate(98px, -31px);">';
                                    echo $categoryError;
                                    echo "</div>";
                                    unset($categoryError);
                                }
                            ?>
                            </div>
                        </div>

                        <div class="filter-location">
                            <span><b>Max Price:</b></span><br>
                            <input type="radio" id="1" name="maxprice" value="1" <?php if (isset($_POST['maxprice']) && $_POST['maxprice'] == "1") echo 'checked="checked"'; ?>>
                            <label for="1">$</label><br>
                            <input type="radio" id="2" name="maxprice" value="2" <?php if (isset($_POST['maxprice']) && $_POST['maxprice'] == "2") echo 'checked="checked"'; ?>>
                            <label for="2">$$</label><br>
                            <input type="radio" id="3" name="maxprice" value="3" <?php if (isset($_POST['maxprice']) && $_POST['maxprice'] == "3") echo 'checked="checked"'; ?>>
                            <label for="3">$$$</label><br>
                            <input type="radio" id="4" name="maxprice" value="4" <?php if (isset($_POST['maxprice']) && $_POST['maxprice'] == "4") echo 'checked="checked"'; ?>>
                            <label for="4">$$$$</label><br>
                        </div>

                        <div class="filter-location">
                            <span><b>Rating:</b></span>
                            &nbsp;
                            <input class="rating" type="number" step="any" id="eatery-rating" name="eatery-rating" value="<?php if (isset($_POST['eatery-rating'])) echo $_POST['eatery-rating'] ?>">
                            <i class="fa-solid fa-star" style="color: #fbbc04;"></i>&nbsp;and Up
                            <?php
                                if (isset($eateryRatingError)) {
                                    echo '<div class="form-error" style="width: 127px; transform: translate(-4px, -11px);">';
                                    echo $eateryRatingError;
                                    echo "</div>";
                                    unset($eateryRatingError);
                                }
                            ?>
                        </div>
                    </div>

                    <br><br><br>
                    <button id='apply-button' type="submit" name="apply">Apply Filter(s)</button>
                    <?php
                        if (isset($noFiltersError)) {
                            echo '<div class="form-error" style="transform: translate(-130px, 15px);">';
                            echo $noFiltersError;
                            echo "</div>";
                            unset($noFiltersError);
                        }
                    ?>
                </form>
                <a href="explore.php"><input id='reset-button' type="button" value="Clear Filter(s)"/></a>
            </div>

            <!-- location list-->
            <div class="location-list">
                <!-- attraction list -->
                <div>
                    <div class="attraction-filter-display">
                        <span style="color: black; font-size: 17.5px;"><b>Attractions</b></span>
                    </div>

                    <div class="location-display">
                        <?php
                        if (!isset($_SESSION['destination']) && !isset($_SESSION['searchInput'])) {
                            echo "
                                    <div class='no-locations-display'>
                                        <img class='no-trip-img' src='img/no-results-found.png'>
                                        <h3 style='font-weight: normal;'>Please enter a city/region in the search bar.</h3>
                                    </div>
                                ";
                        } else if ($attractionsFound == false) {
                            echo "
                                    <div class='no-locations-display'>
                                        <img class='no-trip-img' src='img/no-results-found.png'>
                                        <h3 style='font-weight: normal;'>No results found for filter(s) applied to Attractions.</h3>
                                    </div>
                                ";
                        } else {
                        ?>
                            <button id="slide-left1" class="slide-btn"><i class="fa-solid fa-angle-left"></i></button>
                            <div class="location-display-block" id="attraction-list">
                                <?php 
                                    $i = 1;
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

                                    foreach ($attractions as $attraction) {
                                        if ((isset($_SESSION['email']) && !in_array($attraction["name"], $insertedLocations)) || !isset($_SESSION['email'])) { // only display those locations that have not been added
                                            $i += 1;
                                ?>

                                <div class="location-box">
                                    <div class="location-box-content">
                                        <div class="location-title-div">
                                            <?php 
                                                if (!$loggedIn) {
                                                    ?>
                                                    <a href="explore.php?message=showLoginRequired"><button class="add-btn" title="Add to trip"><i class="fa-solid fa-plus" style="color: white;"></i></button></a>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <button class="add-btn" title="Add to trip" onclick="showAddDiv('add1-div<?php echo $i;?>')"><i class="fa-solid fa-plus" style="color: white;"></i></button>
                                                    <?php
                                                }
                                            ?>
                                            <div style="clear: both;"></div>
                                            <div class="add-div" id="add1-div<?php echo $i;?>" style="display: none;">
                                                <div class="add-text"><b>Add to</b></div>
                                                <?php
                                                    if (!empty($trips)) {
                                                        foreach ($trips as $trip) {
                                                            if ($trip["startDate"] > date('Y-m-d')) { // upcoming trip
                                                                $tripName = $trip["name"];
                                                                echo '<a href="explore.php?tripName='.$tripName.'&location='.urlencode($attraction["name"]).'&placeID='.$attraction["placeID"].'&category=attraction">'.$tripName.'</a>';
                                                            }
                                                        }
                                                    }
                                                ?>
                                                <a href="trip-details.php?fromExplore=true&location=<?php echo urlencode($attraction["name"]);?>&placeID=<?php echo $attraction["placeID"];?>&category=attraction" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;"><i class="fa-solid fa-plus"></i> Create New Trip</a>
                                            </div>
                                        </div>
                                        <a href='location-details.php?placeID=<?php echo $attraction["placeID"];?>&category=attraction'>
                                        <?php 
                                            // retrieve photo of attraction 
                                            $attractionID = $attraction["attractionID"];
                                            $query = "SELECT url FROM photos WHERE attractionID = '$attractionID'";
                                            $result = mysqli_query($handler, $query);
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $photo_url = $row['url'];
                                            }
                                        ?>
                                        <img class='location-box-img' src='<?php echo $photo_url; ?>'><br>
                                        <p class="img_link">View Location Details</p></a>

                                        <div class="location-details">
                                            <div class="location-title-container">
                                                <span class="location-title"><b><?php echo $attraction["name"];?></b></span>
                                            </div>
                                            <br><div style="margin-top: -14px;"><span style="color: #808080;">
                                            <?php 
                                            $rating = $attraction["rating"];
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
                                            (<?php echo number_format($attraction["userRatingsTotal"]);?>)</span></div>
                                            <br>
                                            <div style="line-height: 1em; margin-top: -26px; margin-bottom: 2px;">
                                                <?php 
                                                    $types = $attraction["types"];
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
                            <button id="slide-right1" class="slide-btn"><i class="fa-solid fa-angle-right"></i></button>
                        <?php
                        }
                        ?>
                    </div>
                </div>

                <!-- eatery list -->
                <div style="margin-top: -15px;">
                    <div class="attraction-filter-display">
                        <span style="color: black; font-size: 17.5px;"><b>Eateries</b></span>
                    </div>

                    <div class="location-display">
                        <?php 
                        if (!isset($_SESSION['destination']) && !isset($_SESSION['searchInput'])) {
                            echo "
                                <div class='no-locations-display'>
                                    <img class='no-trip-img' src='img/no-results-found.png'>
                                    <h3 style='font-weight: normal;'>Please enter a city/region in the search bar.</h3>
                                </div>
                                ";
                        } else if ($eateriesFound == false) {
                            echo "
                                    <div class='no-locations-display'>
                                        <img class='no-trip-img' src='img/no-results-found.png'>
                                        <h3 style='font-weight: normal;'>No results found for filter(s) applied to Eateries.</h3>
                                    </div>
                                ";
                        } else {
                        ?>
                            <button id="slide-left2" class="slide-btn"><i class="fa-solid fa-angle-left"></i></button>
                            <div class="location-display-block" id="restaurant-list">
                            <?php 
                                    $i = 1;
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
                                    
                                    foreach ($eateries as $eatery) {
                                        if ((isset($_SESSION['email']) && !in_array($eatery["name"], $insertedLocations)) || !isset($_SESSION['email'])) { // only display those locations that have not been added
                                            $i += 1;
                                ?>

                                <div class="location-box">
                                    <div class="location-box-content">
                                        <div class="location-title-div">
                                            <?php 
                                                if (!$loggedIn) {
                                                    ?>
                                                    <a href="explore.php?message=showLoginRequired"><button class="add-btn" title="Add to trip"><i class="fa-solid fa-plus" style="color: white;"></i></button></a>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <button class="add-btn" title="Add to trip" onclick="showAddDiv('add2-div<?php echo $i;?>')"><i class="fa-solid fa-plus" style="color: white;"></i></button>
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
                                                                echo '<a href="explore.php?tripName='.$tripName.'&location='.urlencode($eatery["name"]).'&placeID='.$eatery["placeID"].'&category=eatery">'.$tripName.'</a>';
                                                            }
                                                        }
                                                    }
                                                ?>
                                                <a href="trip-details.php?fromExplore=true&location=<?php echo urlencode($eatery["name"]);?>&placeID=<?php echo $eatery["placeID"];?>&category=eatery" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;"><i class="fa-solid fa-plus"></i> Create New Trip</a>
                                            </div>
                                        </div>
                                        <a href='location-details.php?placeID=<?php echo $eatery["placeID"];?>&category=eatery'>
                                        <?php 
                                            // retrieve photo of $eatery 
                                            $eateryID = $eatery["eateryID"];
                                            $query = "SELECT url FROM photos WHERE eateryID = '$eateryID'";
                                            $result = mysqli_query($handler, $query);
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $photo_url = $row['url'];
                                            }
                                        ?>
                                        <img class='location-box-img' src='<?php echo $photo_url; ?>'><br>
                                        <p class="img_link">View Location Details</p></a>

                                        <div class="location-details">
                                            <div class="location-title-container">
                                                <span class="location-title"><b><?php echo $eatery["name"];?></b></span>
                                            </div>
                                            <br><div style="margin-top: -14px;"><span style="color: #808080;">
                                            <?php 
                                            $rating = $eatery["rating"];
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
                                            (<?php echo number_format($eatery["userRatingsTotal"]);?>)</span></div>
                                            <br>
                                            <div style="line-height: 1em; margin-top: -26px; margin-bottom: 2px;">
                                                <?php
                                                    $price = $eatery["priceLevel"];
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
            
                                                    $types = $eatery["types"];
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
                            <button id="slide-right2" class="slide-btn"><i class="fa-solid fa-angle-right"></i></button>
                        <?php
                        }
                        ?>
                    </div>
                </div>

                <!--display message if users click add button without logging in-->
                <div id="login-required-div" style="display: none;">
                    <i class='fa-solid fa-right-to-bracket'></i><br>
                    <h3 style="margin-top: 20px;">Please log in to add location to trip.</h3>
                </div>

                <!--display successful message if location added to trip-->
                <div id="location-added-div" style="display: none;">
                    <i class='fa-regular fa-circle-check'></i><br>
                    <h3 id ="location-added-text" style="margin: auto; margin-top: 20px; width: 450px;"></h3>
                </div>

                <!--display max number of locations reached message-->
                <div id="max-div" style="display: none;">
                    <i class="fa-regular fa-circle-xmark"></i><br>
                    <h3 id="max-text" style="margin: auto; margin-top: 20px; width: 450px;"></h3>
                </div>
            </div>
        </div>
        <?php 
        if ((!isset($_SESSION['destination']) && !isset($_SESSION['searchInput'])) || ($attractionsFound == false && $eateriesFound == false)) {
            echo '<div style="width: 100%; margin-bottom: -15px;"></div>';
        } else if (($attractionsFound == false && $eateriesFound == true) || ($attractionsFound == true && $eateriesFound == false)) {
            echo '<div style="width: 100%; height: 120px;"></div>';
        } else {
            echo '<div style="width: 100%; height: 250px;"></div>';
        }
        ?>
        <?php include "footer.php";?>

        <script>
            window.onload = underlineExplore();
            function underlineExplore() {
                let div = document.getElementById("nav-explore");
                div.style.display = "block";
            }

            // location autocomplete using google places API
            $(document).ready(function() {
                let place_autocomplete = new google.maps.places.Autocomplete(document.getElementById("destination"));
                google.maps.event.addListener(place_autocomplete, 'place_changed', function() {
                    let place = place_autocomplete.getPlace();

                    // set place ID and types of selected place in hidden fields
                    document.getElementById("place-id").value = place.place_id;
                    document.getElementById("place-types").value = place.types.join(",");
                });
            });

            // hide left and right arrows if less than 4 locations
            <?php
            if (isset($attractions)) {
            ?>
                let attractionCount = "<?php echo count($attractions);?>";
                if (attractionCount < 4)
                {
                    document.getElementById("slide-left1").style.visibility = "hidden";
                    document.getElementById("slide-right1").style.visibility = "hidden";
                    document.getElementById("attraction-list").style.overflow = "hidden";
                }
            <?php
            }
            ?>

            <?php
            if (isset($eateries)) {
            ?>
                let eateryCount = "<?php echo count($eateries);?>";
                if (eateryCount < 4)
                {
                    document.getElementById("slide-left2").style.visibility = "hidden";
                    document.getElementById("slide-right2").style.visibility = "hidden";
                    document.getElementById("restaurant-list").style.overflow = "hidden";
                }
            <?php
            }
            ?>
            
            // scroll left and right 
            let leftArrow1 = document.getElementById("slide-left1");
            let rightArrow1 = document.getElementById("slide-right1");

            leftArrow1.onclick = function() {
                document.getElementById("attraction-list").scrollLeft -= 200;
            };
            rightArrow1.onclick = function() {
                document.getElementById('attraction-list').scrollLeft += 200;
            };

            let leftArrow2 = document.getElementById("slide-left2");
            let rightArrow2 = document.getElementById("slide-right2");

            leftArrow2.onclick = function() {
                document.getElementById("restaurant-list").scrollLeft -= 200;
            };
            rightArrow2.onclick = function() {
                document.getElementById('restaurant-list').scrollLeft += 200;
            };

            // function to save scroll position 
            function saveScrollPosition() {
                let position1 = document.getElementById("attraction-list").scrollLeft;
                localStorage.setItem("position1", position1);

                let position2 = document.getElementById("restaurant-list").scrollLeft;
                localStorage.setItem("position2", position2);

                <?php 
                $location = "";
                if (isset($_SESSION['searchInput'])) {
                    $location = $_SESSION['searchInput'];
                } else if (isset($_SESSION['destination'])) {
                    $location = $_SESSION['destination'];
                }
                echo 'localStorage.setItem("location", "' . $location . '");';
                ?>
            }

            // function to restore scroll position when page is reloaded
            function restoreScrollPosition() {
                let location = localStorage.getItem("location");
                <?php 
                $location = "";
                if (isset($_SESSION['searchInput'])) {
                    $location = $_SESSION['searchInput'];
                } else if (isset($_SESSION['destination'])) {
                    $location = $_SESSION['destination'];
                }
                echo 'let currentLocation = "'.$location.'";';
                ?>

                let savedPosition1 = localStorage.getItem("position1");
                if (savedPosition1 !== null && currentLocation === location) {
                    document.getElementById("attraction-list").scrollLeft = parseInt(savedPosition1);
                }

                let savedPosition2 = localStorage.getItem("position2");
                if (savedPosition2 !== null && currentLocation === location) {
                    document.getElementById("restaurant-list").scrollLeft = parseInt(savedPosition2);
                }
            }

            // show scroll position when page reloads 
            document.getElementById("attraction-list").addEventListener("scroll", saveScrollPosition);
            document.getElementById("restaurant-list").addEventListener("scroll", saveScrollPosition);
            window.addEventListener("load", restoreScrollPosition);

            // display login message if user clicks add button without logging in
            let loginDiv = document.getElementById("login-required-div");
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
            let addedDiv = document.getElementById("location-added-div");
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
            let maxDiv = document.getElementById("max-div");
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