<?php 
ob_start();
date_default_timezone_set('Asia/Singapore');

if (!isset($_SESSION)) session_start();
$email = $_SESSION['email'];

// connect to database 
include "connect-db.php";
connectDB();

// initialize variables
$itineraryEdited = false;
$edit = "";
$errorMessage = "";
$editedLocation = "";
$editedDayNum = 0;
$newDuration = 0;
$newDay = 0;
$newStop = 0;
$isFreeNode = "";
$editedCategory = "";
$editError = "";

// retrieve user preferences from database
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

// retrieve trip details from database
$tripID = $_SESSION['tripID'];
$query = "SELECT * FROM trips WHERE tripID = $tripID";
$result = mysqli_query($handler, $query);
$trip = mysqli_fetch_assoc($result);
$name = $trip['name'];
$startDate = $trip['startDate'];
$endDate = $trip['endDate'];
$accomodation = $trip['accomodation'];
$accomodationLat = (float)$trip['accomodationLat'];
$accomodationLong = (float)$trip['accomodationLong'];
$waypoints = $trip['waypoints']; 
$waypoints = explode('_ ', $trip['waypoints']); // array of waypoints
$distanceMatrix = json_decode($trip['distanceMatrix'], true);
$itinerary = json_decode($trip['itinerary'], true);

// store preferred mealtimes in an array 
$mealtimes = [];
$mealtimes[1] = $breakfastTime;
$mealtimes[2] = $lunchTime;
$mealtimes[3] = $dinnerTime;

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

// Node class to store relevant data for each location 
class Node {
    // variables
    public $location; 
    public $category;
    public $lat;
    public $long;
    public $ETA; 
    public $ETD; 
    public $operatingHours;
    public $mealCount;
    public $parent; 
    public $duration; 
    public $distance;
    public $mode;
    public $stay;

    // constructor
    public function __construct($location, $category, $lat, $long, $ETA, $ETD, $operatingHours, $mealCount, $parent, $duration, $distance, $mode, $stay) {
        $this->location = $location; 
        $this->category = $category; 
        $this->lat = $lat; 
        $this->long = $long; 
        $this->ETA = $ETA; 
        $this->ETD = $ETD; 
        $this->operatingHours = $operatingHours; 
        $this->mealCount = $mealCount; 
        $this->parent = $parent; 
        $this->duration = $duration;
        $this->distance = $distance;
        $this->mode = $mode;
        $this->stay = $stay;
    }
}

// function to get traveling distance and duration between two locations
function getRouteInfo($origin, $destination) {
    $mode = 'walking';
    $key = 'AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM'; 
    $duration_value = 0;
    $originStr = implode(',', $origin);
    $destinationStr = implode(',', $destination);

    // send request to directions API 
    $url = "https://maps.googleapis.com/maps/api/directions/json?origin=$originStr&destination=$destinationStr&mode=$mode&key=$key";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data['status'] == 'OK') {
        // extract data from response 
        $routes = $data['routes'];
        $duration = $routes[0]['legs'][0]['duration']['text'];
        $value = explode(" ", $duration);

        if (in_array('hour', $value) || in_array('hours', $value)) {
            $hours = (int)((int)$value[0] * 60);
            $minutes = (int)$value[2];
            $duration_value = $hours + $minutes;
        } else {
            $duration_value = $value[0];
        }

        $routeInfo['distance'] = isset($routes[0]['legs'][0]['distance']['text']) ? $routes[0]['legs'][0]['distance']['text'] : "N/A";
        $routeInfo['duration'] = isset($routes[0]['legs'][0]['duration']['text']) ? $duration_value : "N/A";
      
        return $routeInfo;
    }  
}

// function to fetch distance matrix
function fetchDistanceMatrix($origin, $destinations) {
    $mode = 'driving';
    $key = 'AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM'; 
    $result = [];

    // extract coordinates from inputs
    [$originName, $originLat, $originLong] = $origin;
    $originStr = $originLat . ',' . $originLong;
    $destinationStr = [];
    foreach ($destinations as $destination) {
        [$destName, $destLat, $destLong] = $destination;
        $destinationStr[] = $destLat . ',' . $destLong;
    }
    $destinationStr = implode('|', $destinationStr);

    // send request to distance matrix API
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$originStr&destinations=$destinationStr&mode=$mode&key=$key";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data['status'] == 'OK') {
        // extract data from response 
        $elements = $data['rows'][0]['elements'];

        foreach ($elements as $index => $element) {
            if ($element['status'] == 'OK') {
                $distance = isset($element['distance']['text']) ? $element['distance']['text'] : "N/A";
                $duration = isset($element['duration']['text']) ? $element['duration']['text'] : "N/A";

                if ($duration !== "N/A") {
                    $value = explode(" ", $duration);

                    if (in_array('hour', $value) || in_array('hours', $value)) {
                        $hours = (int)((int)$value[0] * 60);
                        $minutes = (int)$value[2];
                        $duration = $hours + $minutes;
                    } else {
                        $duration = $value[0];
                    }
                }

                $result[$originName][] = [
                    "name" => $destinations[$index][0], 
                    "duration" => $duration,
                    "distance" => $distance,
                ];
            } else {
                $result[$originName][] = [
                    "name" => $destinations[$index][0], 
                    "duration" => "N/A",
                    "distance" => "N/A",
                ];
            }
        }
 
        return $result;
    } 
}

// fetch distance matrix if it doesn't exist 
if (is_null($distanceMatrix)) {
    $distanceMatrix = [];

    // accomomodation to all attractions 
    $origin = [$accomodation, $accomodationLat, $accomodationLong];
    $destinations = [];
    foreach ($attractions as $attraction) {
        // retrieve coordinates from database
        $query = "SELECT * FROM attractions WHERE name = '$attraction'";
        $result = mysqli_query($handler, $query);
        $details = mysqli_fetch_assoc($result);
        $lat = (float)$details['latitude'];
        $long = (float)$details['longitude'];
        $coord = [$attraction, $lat, $long];
        array_push($destinations, $coord);
    }
    $response = fetchDistanceMatrix($origin, $destinations);
    array_push($distanceMatrix, $response);

    // accomodation to all eateries 
    $origin = [$accomodation, $accomodationLat, $accomodationLong];
    $destinations = [];
    foreach ($eateries as $eatery) {
        // retrieve coordinates from database
        $query = "SELECT * FROM eateries WHERE name = '$eatery'";
        $result = mysqli_query($handler, $query);
        $details = mysqli_fetch_assoc($result);
        $lat = (float)$details['latitude'];
        $long = (float)$details['longitude'];
        $coord = [$eatery, $lat, $long];
        array_push($destinations, $coord);
    }
    $response = fetchDistanceMatrix($origin, $destinations);
    array_push($distanceMatrix, $response);

    // each attraction to all other attractions 
    foreach ($attractions as $attraction) {
        $query = "SELECT * FROM attractions WHERE name = '$attraction'";
        $result = mysqli_query($handler, $query);
        $details = mysqli_fetch_assoc($result);
        $lat = (float)$details['latitude'];
        $long = (float)$details['longitude'];
        $origin = [$attraction, $lat, $long];
        $destinations = [];
        foreach ($attractions as $attraction1) {
            if ($attraction !== $attraction1) {
                $query = "SELECT * FROM attractions WHERE name = '$attraction1'";
                $result = mysqli_query($handler, $query);
                $details = mysqli_fetch_assoc($result);
                $lat = (float)$details['latitude'];
                $long = (float)$details['longitude'];
                $coord = [$attraction1, $lat, $long];
                array_push($destinations, $coord);
            }
        }
        $response = fetchDistanceMatrix($origin, $destinations);
        array_push($distanceMatrix, $response);
    }

    // each attraction to all eateries and accomodation
    foreach ($attractions as $attraction) {
        $query = "SELECT * FROM attractions WHERE name = '$attraction'";
        $result = mysqli_query($handler, $query);
        $details = mysqli_fetch_assoc($result);
        $lat = (float)$details['latitude'];
        $long = (float)$details['longitude'];
        $origin = [$attraction, $lat, $long];
        $destinations = [];
        foreach ($eateries as $eatery) {
            $query = "SELECT * FROM eateries WHERE name = '$eatery'";
            $result = mysqli_query($handler, $query);
            $details = mysqli_fetch_assoc($result);
            $lat = (float)$details['latitude'];
            $long = (float)$details['longitude'];
            $coord = [$eatery, $lat, $long];
            array_push($destinations, $coord);
        }
        $coord = [$accomodation, $accomodationLat, $accomodationLong];
        array_push($destinations, $coord);
        $response = fetchDistanceMatrix($origin, $destinations);
        array_push($distanceMatrix, $response);
    }

    // each eatery to all other eateries
    foreach ($eateries as $eatery) {
        $query = "SELECT * FROM eateries WHERE name = '$eatery'";
        $result = mysqli_query($handler, $query);
        $details = mysqli_fetch_assoc($result);
        $lat = (float)$details['latitude'];
        $long = (float)$details['longitude'];
        $origin = [$eatery, $lat, $long];
        $destinations = [];
        foreach ($eateries as $eatery1) {
            if ($eatery !== $eatery1) {
                $query = "SELECT * FROM eateries WHERE name = '$eatery1'";
                $result = mysqli_query($handler, $query);
                $details = mysqli_fetch_assoc($result);
                $lat = (float)$details['latitude'];
                $long = (float)$details['longitude'];
                $coord = [$eatery1, $lat, $long];
                array_push($destinations, $coord);
            }
        }
        $response = fetchDistanceMatrix($origin, $destinations);
        array_push($distanceMatrix, $response);
    }

    // each eatery to all attractions and accomodation
    foreach ($eateries as $eatery) {
        $query = "SELECT * FROM eateries WHERE name = '$eatery'";
        $result = mysqli_query($handler, $query);
        $details = mysqli_fetch_assoc($result);
        $lat = (float)$details['latitude'];
        $long = (float)$details['longitude'];
        $origin = [$eatery, $lat, $long];
        $destinations = [];
        foreach ($attractions as $attraction) {
            $query = "SELECT * FROM attractions WHERE name = '$attraction'";
            $result = mysqli_query($handler, $query);
            $details = mysqli_fetch_assoc($result);
            $lat = (float)$details['latitude'];
            $long = (float)$details['longitude'];
            $coord = [$attraction, $lat, $long];
            array_push($destinations, $coord);
        }
        $coord = [$accomodation, $accomodationLat, $accomodationLong];
        array_push($destinations, $coord);
        $response = fetchDistanceMatrix($origin, $destinations);
        array_push($distanceMatrix, $response);
    }

    // update distance matrix in database 
    $query = "UPDATE trips SET distanceMatrix = '" . mysqli_real_escape_string($handler, json_encode($distanceMatrix)) . "' WHERE tripID =" . $tripID;
    mysqli_query($handler, $query);

    // retrieve distance matrix from database
    $query = "SELECT * FROM trips WHERE tripID = $tripID";
    $result = mysqli_query($handler, $query);
    $trip = mysqli_fetch_assoc($result);
    $distanceMatrix = json_decode($trip['distanceMatrix'], true);
}

// create itinerary if it doesn't exist 
if (is_null($itinerary)) {
    $itinerary = createItinerary($accomodation, $attractions, $eateries, $startDate, $startTime, $endTime, $numOfDays, $mealtimes);

    // update itinerary in database 
    $query = "UPDATE trips SET itinerary = '" . mysqli_real_escape_string($handler, json_encode($itinerary)) . "' WHERE tripID =" . $tripID;
    mysqli_query($handler, $query);

    // retrieve itinerary from database
    $query = "SELECT * FROM trips WHERE tripID = $tripID";
    $result = mysqli_query($handler, $query);
    $trip = mysqli_fetch_assoc($result);
    $itinerary = json_decode($trip['itinerary'], true);
}

// function to create itinerary 
function createItinerary($accomodation, $attractions, $eateries, $startDate, $startTime, $endTime, $numOfDays, $mealtimes) {
    // intialize variables
    $itinerary = [];
    $visitedAttractions = [];
    $visitedEateries = [];
    $currentDate = $startDate;
    $startLocation = $accomodation;
    $endLocation = $accomodation;

    // change format of start time
    $startTime = DateTime::createFromFormat('H:i:s', $startTime);
    $startTime = $startTime->format('h:i A');

    // define global variables
    global $handler;
    global $accomodation;
    global $accomodationLat;
    global $accomodationLong;
    global $attractionStay;
    global $eateryStay;
    global $distanceMatrix;

    // create daily itinerary
    while (count($itinerary) < $numOfDays) {
        $sequence = []; 
        $startNode = new Node($startLocation, null, $accomodationLat, $accomodationLong, null, $startTime, null, 0, null, null, null, null, 0);
        array_push($sequence, $startNode);

        // get sequence of locations for each day
        while (end($sequence)->ETD !== null) { // end location is not reached
            // get last location in sequence as current node
            $currentNode = end($sequence);
            $children = [];
            $remainingAttractions = array_diff($attractions, $visitedAttractions);
            $remainingEateries = array_diff($eateries, $visitedEateries);

            // check if it is mealtime 
            $isMealTime = false; 
            foreach($mealtimes as $index => $mealtime) {
                if (strtotime($mealtime) <= strtotime($currentNode->ETD) && $index > $currentNode->mealCount) {
                    $isMealTime = true;
                    break;
                }
            }
            
            $endash = html_entity_decode('&#x2013;', ENT_COMPAT, 'UTF-8');
            $operatingHours = "";
            if ($isMealTime) { // add eatery 
                foreach($remainingEateries as $location) {
                    // fetch location details from database 
                    $query = "SELECT * FROM eateries WHERE name = '$location'";
                    $result = mysqli_query($handler, $query);
                    $details = mysqli_fetch_assoc($result);
                    $lat = (float)$details['latitude'];
                    $long = (float)$details['longitude'];
                    $operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";

                    // get opening and closing time
                    $openingTimes = [];
                    $closingTimes = [];
                    if ($operatingHours !== "No operating hours available") {
                        $day = date('l', strtotime($currentDate));
                        if ($day == "Monday") {
                            $hours = str_replace($endash, '-', $operatingHours[0]);
                        } else if ($day == "Tuesday") {
                            $hours = str_replace($endash, '-', $operatingHours[1]);
                        } else if ($day == "Wednesday") {
                            $hours = str_replace($endash, '-', $operatingHours[2]);
                        } else if ($day == "Thursday") {
                            $hours = str_replace($endash, '-', $operatingHours[3]);
                        } else if ($day == "Friday") {
                            $hours = str_replace($endash, '-', $operatingHours[4]);
                        } else if ($day == "Saturday") {
                            $hours = str_replace($endash, '-', $operatingHours[5]);
                        } else if ($day == "Sunday") {
                            $hours = str_replace($endash, '-', $operatingHours[6]);
                        } 

                        $operatingHours = substr($hours, strpos($hours, ':') + 2);
                        $hours = explode(",", substr($hours, strpos($hours, ':') + 2)); // consider multiple operating hours
                        foreach ($hours as $hour) {
                            if ($hour == "Closed") { 
                                $openingTime = "N/A"; 
                                $closingTime = "N/A";
                            } else if ($hour == "Open 24 hours") {
                                $openingTime = "5:00 AM"; 
                                $closingTime = "11:59 PM";
                            } else {
                                $hour = explode("-", $hour);
                                $openingTime = trim($hour[0]);
                                $openingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($openingTime));
                                $closingTime = trim($hour[1]);
                                $closingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($closingTime));

                                // add AM or PM to opening time if needed
                                if (stripos($closingTime, 'AM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                    $openingTime .= ' AM';
                                } else if (stripos($closingTime, 'PM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                    $openingTime .= ' PM';
                                }
                                if ($closingTime == "12:00AM") { 
                                    $closingTime = "11:59 PM";
                                }
                            }

                            $openingTimes[] = $openingTime;
                            $closingTimes[] = $closingTime;
                        }
                    } else { // treat as open 24 hours
                        $openingTimes[] = "5:00 AM";
                        $closingTimes[] = "11:59 PM";
                    }

                    $count = count($openingTimes);
                    if ($count === 1) {
                        $openingTimes = $openingTimes[0];
                        $closingTimes = $closingTimes[0];
                    }

                    if (is_array($openingTimes)) { // multiple operating hours
                        for ($i = 0; $i < $count; $i++) {
                            $openingTime = $openingTimes[$i];
                            $closingTime = $closingTimes[$i];

                            // get traveling distance and duration from distance matrix 
                            $origin = $currentNode->location; 
                            $destination = $location;
                            $travelingDuration = 0;
                            $travelingDistance = "";
                            $travelingMode = "driving";

                            foreach ($distanceMatrix as $entry) {
                                if (isset($entry[$origin])) {
                                    $originEntry = $entry[$origin];
                                    foreach ($originEntry as $info) {
                                        if ($info['name'] == $destination) {
                                            $travelingDuration = (int)$info['duration']; 
                                            $travelingDistance = $info['distance']; 
                                            break;
                                        }
                                    }
                                }
                            }

                            // calculate ETA
                            $ETA = new DateTime($currentNode->ETD);
                            $ETA->modify("+$travelingDuration minutes");
                            $ETA = $ETA->format('h:i A');
                        
                            // check if location fits criteria to be child of current node
                            $ETADateTime = new DateTime($ETA);
                            $closingDateTime = new DateTime($closingTime);
                            $ETAtoClosing = $closingDateTime->diff($ETADateTime)->h; // time difference between ETA and closing time in hours
    
                            $ETDDateTime = new DateTime($ETA);
                            $ETDDateTime->modify("+$eateryStay hours"); // ETA + duration stay
                            $ETD = $ETDDateTime->format('h:i A');
    
                            if ((strtotime($ETA) >= strtotime($openingTime)) && (strtotime($ETA) < strtotime($closingTime)) && ($ETAtoClosing >= $eateryStay)) {
                                if (is_null($currentNode->ETA)) {
                                    $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                    array_push($children, $childNode);
                                    break;
                                } else {
                                    if ($travelingDuration <= 20) {
                                        $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                        array_push($children, $childNode);
                                        break;
                                    }
                                } 
                            } 
                        }
                    } else {    
                        if ($openingTimes !== "N/A") {
                            // get traveling distance and duration from distance matrix 
                            $origin = $currentNode->location; 
                            $destination = $location;
                            $travelingDuration = 0;
                            $travelingDistance = "";
                            $travelingMode = "driving";

                            foreach ($distanceMatrix as $entry) {
                                if (isset($entry[$origin])) {
                                    $originEntry = $entry[$origin];
                                    foreach ($originEntry as $info) {
                                        if ($info['name'] == $destination) {
                                            $travelingDuration = (int)$info['duration']; 
                                            $travelingDistance = $info['distance']; 
                                            break;
                                        }
                                    }
                                }
                            }
    
                            // calculate ETA
                            $ETA = new DateTime($currentNode->ETD);
                            $ETA->modify("+$travelingDuration minutes");
                            $ETA = $ETA->format('h:i A');
                        
                            // check if location fits criteria to be child of current node
                            $ETADateTime = new DateTime($ETA);
                            $closingDateTime = new DateTime($closingTimes);
                            $ETAtoClosing = $closingDateTime->diff($ETADateTime)->h; // time difference between ETA and closing time in hours
    
                            $ETDDateTime = new DateTime($ETA);
                            $ETDDateTime->modify("+$eateryStay hours"); // ETA + duration stay
                            $ETD = $ETDDateTime->format('h:i A');
    
                            if ((strtotime($ETA) >= strtotime($openingTimes)) && (strtotime($ETA) < strtotime($closingTimes)) && ($ETAtoClosing >= $eateryStay)) {
                                if (is_null($currentNode->ETA)) {
                                    $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                    array_push($children, $childNode);
                                    break;
                                } else {
                                    if ($travelingDuration <= 20) {
                                        $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                        array_push($children, $childNode);
                                        break;
                                    }
                                } 
                            } 
                        }
                    }
                }
            } else { // add attraction
                foreach($remainingAttractions as $location) {
                    // fetch location details from database 
                    $query = "SELECT * FROM attractions WHERE name = '$location'";
                    $result = mysqli_query($handler, $query);
                    $details = mysqli_fetch_assoc($result);
                    $lat = (float)$details['latitude'];
                    $long = (float)$details['longitude'];
                    $operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";

                    // get opening and closing time 
                    $openingTimes = [];
                    $closingTimes = [];
                    if ($operatingHours !== "No operating hours available") {
                        $day = date('l', strtotime($currentDate));
                        if ($day == "Monday") {
                            $hours = str_replace($endash, '-', $operatingHours[0]);
                        } else if ($day == "Tuesday") {
                            $hours = str_replace($endash, '-', $operatingHours[1]);
                        } else if ($day == "Wednesday") {
                            $hours = str_replace($endash, '-', $operatingHours[2]);
                        } else if ($day == "Thursday") {
                            $hours = str_replace($endash, '-', $operatingHours[3]);
                        } else if ($day == "Friday") {
                            $hours = str_replace($endash, '-', $operatingHours[4]);
                        } else if ($day == "Saturday") {
                            $hours = str_replace($endash, '-', $operatingHours[5]);
                        } else if ($day == "Sunday") {
                            $hours = str_replace($endash, '-', $operatingHours[6]);
                        } 

                        $operatingHours = substr($hours, strpos($hours, ':') + 2);
                        $hours = explode(",", substr($hours, strpos($hours, ':') + 2)); // consider multiple operating hours
                        foreach ($hours as $hour) {
                            if ($hour == "Closed") { 
                                $openingTime = "N/A"; 
                                $closingTime = "N/A";
                            } else if ($hour == "Open 24 hours") {
                                $openingTime = "5:00 AM"; 
                                $closingTime = "11:59 PM";
                            } else {
                                $hour = explode("-", $hour);
                                $openingTime = trim($hour[0]);
                                $openingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($openingTime));
                                $closingTime = trim($hour[1]);
                                $closingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($closingTime));

                                // add AM or PM to opening time if needed
                                if (stripos($closingTime, 'AM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                    $openingTime .= ' AM';
                                } else if (stripos($closingTime, 'PM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                    $openingTime .= ' PM';
                                }
                                if ($closingTime == "12:00AM") { 
                                    $closingTime = "11:59 PM";
                                }
                            }

                            $openingTimes[] = $openingTime;
                            $closingTimes[] = $closingTime;
                        }
                    } else { // treat as open 24 hours
                        $openingTimes[] = "5:00 AM";
                        $closingTimes[] = "11:59 PM";
                    }

                    $count = count($openingTimes);
                    if ($count === 1) {
                        $openingTimes = $openingTimes[0];
                        $closingTimes = $closingTimes[0];
                    }

                    if (is_array($openingTimes)) { // multiple operating hours
                        for ($i = 0; $i < $count; $i++) {
                            $openingTime = $openingTimes[$i];
                            $closingTime = $closingTimes[$i];

                            // get traveling distance and duration from distance matrix 
                            $origin = $currentNode->location; 
                            $destination = $location;
                            $travelingDuration = 0;
                            $travelingDistance = "";
                            $travelingMode = "driving";

                            foreach ($distanceMatrix as $entry) {
                                if (isset($entry[$origin])) {
                                    $originEntry = $entry[$origin];
                                    foreach ($originEntry as $info) {
                                        if ($info['name'] == $destination) {
                                            $travelingDuration = (int)$info['duration']; 
                                            $travelingDistance = $info['distance']; 
                                            break;
                                        }
                                    }
                                }
                            }

                            // calculate ETA
                            $ETA = new DateTime($currentNode->ETD);
                            $ETA->modify("+$travelingDuration minutes");
                            $ETA = $ETA->format('h:i A');
                        
                            // check if location fits criteria to be child of current node
                            $ETADateTime = new DateTime($ETA);
                            $closingDateTime = new DateTime($closingTime);
                            $ETAtoClosing = $closingDateTime->diff($ETADateTime)->h; // time difference between ETA and closing time in hours
    
                            $ETDDateTime = new DateTime($ETA);
                            $ETDDateTime->modify("+$attractionStay hours"); // ETA + duration stay
                            $ETD = $ETDDateTime->format('h:i A');
                            $endDateTime = new DateTime($endTime);
                            $endDateTime->modify("-30 minutes"); // end time - 30 mins buffer
                            $bufferEndTime = $endDateTime->format('h:i A');
    
                            if ((strtotime($ETA) >= strtotime($openingTime)) && (strtotime($ETA) < strtotime($closingTime)) && ($ETAtoClosing >= $attractionStay) && (strtotime($ETD) <= strtotime($bufferEndTime))) {
                                $childNode = new Node($location, "attraction", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $attractionStay);
                                array_push($children, $childNode);
                                break;
                            } 
                        }
                    } else {
                        if ($openingTimes !== "N/A") {
                            // get traveling distance and duration from distance matrix 
                            $origin = $currentNode->location; 
                            $destination = $location;
                            $travelingDuration = 0;
                            $travelingDistance = "";
                            $travelingMode = "driving";

                            foreach ($distanceMatrix as $entry) {
                                if (isset($entry[$origin])) {
                                    $originEntry = $entry[$origin];
                                    foreach ($originEntry as $info) {
                                        if ($info['name'] == $destination) {
                                            $travelingDuration = (int)$info['duration']; 
                                            $travelingDistance = $info['distance']; 
                                            break;
                                        }
                                    }
                                }
                            }
    
                            // calculate ETA
                            $ETA = new DateTime($currentNode->ETD);
                            $ETA->modify("+$travelingDuration minutes");
                            $ETA = $ETA->format('h:i A');
                        
                            // check if location fits criteria to be child of current node
                            $ETADateTime = new DateTime($ETA);
                            $closingDateTime = new DateTime($closingTimes);
                            $ETAtoClosing = $closingDateTime->diff($ETADateTime)->h; // time difference between ETA and closing time in hours
    
                            $ETDDateTime = new DateTime($ETA);
                            $ETDDateTime->modify("+$attractionStay hours"); // ETA + duration stay
                            $ETD = $ETDDateTime->format('h:i A');
                            $endDateTime = new DateTime($endTime);
                            $endDateTime->modify("-30 minutes"); // end time - 30 mins buffer
                            $bufferEndTime = $endDateTime->format('h:i A');
    
                            if ((strtotime($ETA) >= strtotime($openingTimes)) && (strtotime($ETA) < strtotime($closingTimes)) && ($ETAtoClosing >= $attractionStay) && (strtotime($ETD) <= strtotime($bufferEndTime))) {
                                $childNode = new Node($location, "attraction", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $attractionStay);
                                array_push($children, $childNode);
                            } 
                        }
                    }
                }
            }

            if (empty($children)) {
                // check if end time is approaching
                $ETDDateTime = new DateTime($currentNode->ETD);
                $ETDDateTime->modify("+$attractionStay hours"); // current ETD + duration stay
                $newETA = $ETDDateTime->format('h:i A');
                $endDateTime = new DateTime($endTime);
                $endDateTime->modify("-30 minutes"); // end time - 30 mins buffer
                $bufferEndTime = $endDateTime->format('h:i A');

                if ((strtotime($newETA) > strtotime($bufferEndTime)) && $currentNode->mealCount == 3) { // add end node to sequence
                    // get traveling distance and duration from distance matrix 
                    $origin = $currentNode->location; 
                    $destination = $accomodation;
                    $travelingDuration = 0;
                    $travelingDistance = "";
                    $travelingMode = "driving";

                    foreach ($distanceMatrix as $entry) {
                        if (isset($entry[$origin])) {
                            $originEntry = $entry[$origin];
                            foreach ($originEntry as $info) {
                                if ($info['name'] == $destination) {
                                    $travelingDuration = (int)$info['duration']; 
                                    $travelingDistance = $info['distance']; 
                                    break;
                                }
                            }
                        }
                    }

                    // if traveling duration < 4 minutes, change traveling mode to walking
                    if ($travelingDuration < 4) {
                        $originCoord = [$currentNode->lat, $currentNode->long];
                        $destinationCoord = [$accomodationLat, $accomodationLong];

                        $routeInfo = getRouteInfo($originCoord, $destinationCoord);
                        $travelingDuration = $routeInfo['duration'] !== "N/A" ? $routeInfo['duration'] : "N/A";
                        $travelingDistance = $routeInfo['distance'] !== "N/A" ? $routeInfo['distance'] : "N/A";
                        $travelingMode = "walking";
                    }

                    // calculate new ETA
                    $ETA = new DateTime($currentNode->ETD);
                    $ETA->modify("+$travelingDuration minutes");
                    $ETA = $ETA->format('h:i A');

                    $endNode = new Node($endLocation, null, $accomodationLat, $accomodationLong, $ETA, null, null, $currentNode->mealCount, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, 0);
                    array_push($sequence, $endNode);
                } else { 
                    if ($isMealTime) {
                        // check if there are any available eatery for dinner
                        foreach($remainingEateries as $location) {
                            // fetch location details from database 
                            $query = "SELECT * FROM eateries WHERE name = '$location'";
                            $result = mysqli_query($handler, $query);
                            $details = mysqli_fetch_assoc($result);
                            $lat = (float)$details['latitude'];
                            $long = (float)$details['longitude'];
                            $operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";
        
                            // get opening and closing time 
                            $openingTimes = [];
                            $closingTimes = [];
                            if ($operatingHours !== "No operating hours available") {
                                $day = date('l', strtotime($currentDate));
                                if ($day == "Monday") {
                                    $hours = str_replace($endash, '-', $operatingHours[0]);
                                } else if ($day == "Tuesday") {
                                    $hours = str_replace($endash, '-', $operatingHours[1]);
                                } else if ($day == "Wednesday") {
                                    $hours = str_replace($endash, '-', $operatingHours[2]);
                                } else if ($day == "Thursday") {
                                    $hours = str_replace($endash, '-', $operatingHours[3]);
                                } else if ($day == "Friday") {
                                    $hours = str_replace($endash, '-', $operatingHours[4]);
                                } else if ($day == "Saturday") {
                                    $hours = str_replace($endash, '-', $operatingHours[5]);
                                } else if ($day == "Sunday") {
                                    $hours = str_replace($endash, '-', $operatingHours[6]);
                                } 
        
                                $operatingHours = substr($hours, strpos($hours, ':') + 2);
                                $hours = explode(",", substr($hours, strpos($hours, ':') + 2)); // consider multiple operating hours
                                foreach ($hours as $hour) {
                                    if ($hour == "Closed") { 
                                        $openingTime = "N/A"; 
                                        $closingTime = "N/A";
                                    } else if ($hour == "Open 24 hours") {
                                        $openingTime = "5:00 AM"; 
                                        $closingTime = "11:59 PM";
                                    } else {
                                        $hour = explode("-", $hour);
                                        $openingTime = trim($hour[0]);
                                        $openingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($openingTime));
                                        $closingTime = trim($hour[1]);
                                        $closingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($closingTime));
        
                                        // add AM or PM to opening time if needed
                                        if (stripos($closingTime, 'AM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                            $openingTime .= ' AM';
                                        } else if (stripos($closingTime, 'PM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                            $openingTime .= ' PM';
                                        }
                                        if ($closingTime == "12:00AM") { 
                                            $closingTime = "11:59 PM";
                                        }
                                    }
        
                                    $openingTimes[] = $openingTime;
                                    $closingTimes[] = $closingTime;
                                }
                            } else { // treat as open 24 hours
                                $openingTimes[] = "5:00 AM";
                                $closingTimes[] = "11:59 PM";
                            }

                            $count = count($openingTimes);
                            if ($count === 1) {
                                $openingTimes = $openingTimes[0];
                                $closingTimes = $closingTimes[0];
                            }

                            if (is_array($openingTimes)) { // multiple operating hours
                                for ($i = 0; $i < $count; $i++) {
                                    $openingTime = $openingTimes[$i];
                                    $closingTime = $closingTimes[$i];

                                    // get traveling distance and duration from distance matrix 
                                    $origin = $currentNode->location; 
                                    $destination = $location;
                                    $travelingDuration = 0;
                                    $travelingDistance = "";
                                    $travelingMode = "driving";

                                    foreach ($distanceMatrix as $entry) {
                                        if (isset($entry[$origin])) {
                                            $originEntry = $entry[$origin];
                                            foreach ($originEntry as $info) {
                                                if ($info['name'] == $destination) {
                                                    $travelingDuration = (int)$info['duration']; 
                                                    $travelingDistance = $info['distance']; 
                                                    break;
                                                }
                                            }
                                        }
                                    }
            
                                    // calculate ETA
                                    $ETA = new DateTime($currentNode->ETD);
                                    $ETA->modify("+$travelingDuration minutes");
                                    $ETA = $ETA->format('h:i A');
                                
                                    // check if location fits criteria to be child of current node
                                    $ETADateTime = new DateTime($ETA);
                                    $closingDateTime = new DateTime($closingTime);
                                    $ETAtoClosing = $closingDateTime->diff($ETADateTime)->h; // time difference between ETA and closing time in hours
            
                                    $ETDDateTime = new DateTime($ETA);
                                    $ETDDateTime->modify("+$eateryStay hours"); // ETA + duration stay
                                    $ETD = $ETDDateTime->format('h:i A');
            
                                    if ((strtotime($ETA) >= strtotime($openingTime)) && (strtotime($ETA) < strtotime($closingTime)) && ($ETAtoClosing >= $eateryStay)) {
                                        if (is_null($currentNode->ETA)) {
                                            $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                            array_push($children, $childNode);
                                            break;
                                        } else {
                                            if ($travelingDuration <= 20) {
                                                $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                                array_push($children, $childNode);
                                                break;
                                            }
                                        } 
                                    } 
                                }
                            } else {    
                                if ($openingTimes !== "N/A") {
                                    // get traveling distance and duration from distance matrix 
                                    $origin = $currentNode->location; 
                                    $destination = $location;
                                    $travelingDuration = 0;
                                    $travelingDistance = "";
                                    $travelingMode = "driving";

                                    foreach ($distanceMatrix as $entry) {
                                        if (isset($entry[$origin])) {
                                            $originEntry = $entry[$origin];
                                            foreach ($originEntry as $info) {
                                                if ($info['name'] == $destination) {
                                                    $travelingDuration = (int)$info['duration']; 
                                                    $travelingDistance = $info['distance']; 
                                                    break;
                                                }
                                            }
                                        }
                                    }
            
                                    // calculate ETA
                                    $ETA = new DateTime($currentNode->ETD);
                                    $ETA->modify("+$travelingDuration minutes");
                                    $ETA = $ETA->format('h:i A');
                                
                                    // check if location fits criteria to be child of current node
                                    $ETADateTime = new DateTime($ETA);
                                    $closingDateTime = new DateTime($closingTimes);
                                    $ETAtoClosing = $closingDateTime->diff($ETADateTime)->h; // time difference between ETA and closing time in hours
            
                                    $ETDDateTime = new DateTime($ETA);
                                    $ETDDateTime->modify("+$eateryStay hours"); // ETA + duration stay
                                    $ETD = $ETDDateTime->format('h:i A');
            
                                    if ((strtotime($ETA) >= strtotime($openingTimes)) && (strtotime($ETA) < strtotime($closingTimes)) && ($ETAtoClosing >= $eateryStay)) {
                                        if (is_null($currentNode->ETA)) {
                                            $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                            array_push($children, $childNode);
                                            break;
                                        } else {
                                            if ($travelingDuration <= 20) {
                                                $childNode = new Node($location, "eatery", $details['latitude'], $details['longitude'], $ETA, $ETD, $operatingHours, $currentNode->mealCount + 1, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, $eateryStay);
                                                array_push($children, $childNode);
                                                break;
                                            }
                                        } 
                                    } 
                                }
                            }
                        }

                        if (empty($children)) {
                            // allocate free node for meal session
                            $ETDDateTime = new DateTime($currentNode->ETD);
                            $ETDDateTime->modify("+$eateryStay hours"); // ETA + duration stay
                            $ETD = $ETDDateTime->format('h:i A');
                            $freeNode = new Node($currentNode->location, "eatery", $currentNode->lat, $currentNode->long, $currentNode->ETD, $ETD, null, ($currentNode->mealCount) + 1, $currentNode, null, null, null, $eateryStay);
                            array_push($sequence, $freeNode);
                        } else {
                            // add closest node to sequence of locations and visited locations
                            usort($children, function($a, $b) {
                                return strtotime($a->ETA) - strtotime($b->ETA);
                            });

                            // if traveling duration < 4 minutes, change traveling mode to walking
                            if ($children[0]->duration < 4) {
                                $originCoord = [$currentNode->lat, $currentNode->long];
                                $destinationCoord = [$children[0]->lat, $children[0]->long];

                                $routeInfo = getRouteInfo($originCoord, $destinationCoord);
                                $travelingDuration = $routeInfo['duration'] !== "N/A" ? $routeInfo['duration'] : "N/A";
                                $travelingDistance = $routeInfo['distance'] !== "N/A" ? $routeInfo['distance'] : "N/A";
                                $travelingMode = "walking";

                                // calculate new ETA
                                $ETA = new DateTime($currentNode->ETD);
                                $ETA->modify("+$travelingDuration minutes");
                                $ETA = $ETA->format('h:i A');

                                // calculate new ETD
                                $ETDDateTime = new DateTime($ETA);
                                if ($children[0]->category == "attraction") {
                                    $ETDDateTime->modify("+$attractionStay hours"); // ETA + duration stay
                                } else {
                                    $ETDDateTime->modify("+$eateryStay hours"); // ETA + duration stay
                                }
                                $ETD = $ETDDateTime->format('h:i A');

                                // update node details 
                                $children[0]->ETA = $ETA;
                                $children[0]->ETD = $ETD;
                                $children[0]->duration = $travelingDuration;
                                $children[0]->distance = $travelingDistance;
                                $children[0]->mode = $travelingMode;
                            }

                            array_push($sequence, $children[0]);
                            array_push($visitedEateries, $children[0]->location);
                        }
                        
                    } else {
                        $nextReachableLocations = []; // next reachable locations with opening time after current node's ETD and not in current node's chain of parents
                        foreach($remainingAttractions as $location) {
                            // fetch location details from database 
                            $query = "SELECT * FROM attractions WHERE name = '$location'";
                            $result = mysqli_query($handler, $query);
                            $details = mysqli_fetch_assoc($result);
                            $operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";
        
                            // get opening time 
                            $openingTimes = [];
                            if ($operatingHours !== "No operating hours available") {
                                $day = date('l', strtotime($currentDate));
                                if ($day == "Monday") {
                                    $hours = str_replace($endash, '-', $operatingHours[0]);
                                } else if ($day == "Tuesday") {
                                    $hours = str_replace($endash, '-', $operatingHours[1]);
                                } else if ($day == "Wednesday") {
                                    $hours = str_replace($endash, '-', $operatingHours[2]);
                                } else if ($day == "Thursday") {
                                    $hours = str_replace($endash, '-', $operatingHours[3]);
                                } else if ($day == "Friday") {
                                    $hours = str_replace($endash, '-', $operatingHours[4]);
                                } else if ($day == "Saturday") {
                                    $hours = str_replace($endash, '-', $operatingHours[5]);
                                } else if ($day == "Sunday") {
                                    $hours = str_replace($endash, '-', $operatingHours[6]);
                                } 

                                $operatingHours = substr($hours, strpos($hours, ':') + 2);
                                $hours = explode(",", substr($hours, strpos($hours, ':') + 2)); // consider multiple operating hours
                                foreach ($hours as $hour) {
                                    if ($hour == "Closed") { 
                                        $openingTime = "N/A"; 
                                    } else if ($hour == "Open 24 hours") {
                                        $openingTime = date("g:i A", strtotime($currentNode->ETD));
                                    } else {
                                        $hour = explode("-", $hour);
                                        $openingTime = trim($hour[0]);
                                        $openingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($openingTime));
                                        $closingTime = trim($hour[1]);
                                        $closingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($closingTime));
        
                                        // add AM or PM to opening time if needed
                                        if (stripos($closingTime, 'AM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                            $openingTime .= ' AM';
                                        } else if (stripos($closingTime, 'PM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                            $openingTime .= ' PM';
                                        }
                                    }

                                    $openingTimes[] = $openingTime;
                                }
                            } else { // treat as open 24 hours
                                $openingTimes[] = date("g:i A", strtotime($currentNode->ETD));
                            }

                            $count = count($openingTimes);
                            if ($count === 1) {
                                $openingTimes = $openingTimes[0];
                            }

                            if (is_array($openingTimes)) { // multiple operating hours
                                for ($i = 0; $i < $count; $i++) {
                                    $openingTime = $openingTimes[$i];

                                    // find next reachable locations with opening time after current node's ETD
                                    $ETDDateTime = new DateTime($currentNode->ETD);
                                    $openingDateTime = new DateTime($openingTime);
                                    if ($openingDateTime >= $ETDDateTime) {
                                        // check if location is in current node's chain of parents
                                        $isInParentChain = false;
                                        $parentNode = $currentNode->parent;
                                        while ($parentNode !== null) {
                                            if ($parentNode->location === $location) {
                                                $isInParentChain = true;
                                                break;
                                            }
                                            $parentNode = $parentNode->parent;
                                        }

                                        if (!$isInParentChain) {
                                            array_push($nextReachableLocations, $location);
                                            break;
                                        }
                                    }
                                }
                            } else {
                                if ($openingTimes !== "N/A") {
                                    // find next reachable locations with opening time after current node's ETD
                                    $ETDDateTime = new DateTime($currentNode->ETD);
                                    $openingDateTime = new DateTime($openingTimes);
                                    if ($openingDateTime >= $ETDDateTime) {
                                        // check if location is in current node's chain of parent
                                        $isInParentChain = false;
                                        $parentNode = $currentNode->parent;
                                        while ($parentNode !== null) {
                                            if ($parentNode->location === $location) {
                                                $isInParentChain = true;
                                                break;
                                            }
                                            $parentNode = $parentNode->parent;
                                        }
    
                                        if (!$isInParentChain) {
                                            array_push($nextReachableLocations, $location);
                                        }
                                    }
                                }
                            }
                        }

                        // add end node to sequence if no more reachable location
                        if (empty($nextReachableLocations)) {
                            // get traveling distance and duration from distance matrix 
                            $origin = $currentNode->location; 
                            $destination = $accomodation;
                            $travelingDuration = 0;
                            $travelingDistance = "";
                            $travelingMode = "driving";

                            foreach ($distanceMatrix as $entry) {
                                if (isset($entry[$origin])) {
                                    $originEntry = $entry[$origin];
                                    foreach ($originEntry as $info) {
                                        if ($info['name'] == $destination) {
                                            $travelingDuration = (int)$info['duration']; 
                                            $travelingDistance = $info['distance']; 
                                            break;
                                        }
                                    }
                                }
                            }

                            // if traveling duration < 4 minutes, change traveling mode to walking
                            if ($travelingDuration < 4) {
                                $originCoord = [$currentNode->lat, $currentNode->long];
                                $destinationCoord = [$accomodationLat, $accomodationLong];

                                $routeInfo = getRouteInfo($originCoord, $destinationCoord);
                                $travelingDuration = $routeInfo['duration'] !== "N/A" ? $routeInfo['duration'] : "N/A";
                                $travelingDistance = $routeInfo['distance'] !== "N/A" ? $routeInfo['distance'] : "N/A";
                                $travelingMode = "walking";
                            }

                            // calculate new ETA
                            $ETA = new DateTime($currentNode->ETD);
                            $ETA->modify("+$travelingDuration minutes");
                            $ETA = $ETA->format('h:i A');

                            $endNode = new Node($endLocation, null, $accomodationLat, $accomodationLong, $ETA, null, null, $currentNode->mealCount, $currentNode, $travelingDuration, $travelingDistance, $travelingMode, 0);
                            array_push($sequence, $endNode);

                        } else { // there are still locations to be visited but none available at current node's ETD
                            $earliestOpeningTime = "11:59 PM";
                            $earliestOpeningDateTime = new DateTime($earliestOpeningTime);
                            $earliestOpeningLocation = "";
                            $ETDDateTime = new DateTime($currentNode->ETD);
                            foreach($nextReachableLocations as $nextReachableLocation) {
                                // fetch location details from database 
                                $query = "SELECT * FROM attractions WHERE name = '$nextReachableLocation'";
                                $result = mysqli_query($handler, $query);
                                $details = mysqli_fetch_assoc($result);
                                $operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";
    
                                // get earliest opening time 
                                $openingTimes = [];
                                if ($operatingHours !== "No operating hours available") {
                                    $day = date('l', strtotime($currentDate));
                                    if ($day == "Monday") {
                                        $hours = str_replace($endash, '-', $operatingHours[0]);
                                    } else if ($day == "Tuesday") {
                                        $hours = str_replace($endash, '-', $operatingHours[1]);
                                    } else if ($day == "Wednesday") {
                                        $hours = str_replace($endash, '-', $operatingHours[2]);
                                    } else if ($day == "Thursday") {
                                        $hours = str_replace($endash, '-', $operatingHours[3]);
                                    } else if ($day == "Friday") {
                                        $hours = str_replace($endash, '-', $operatingHours[4]);
                                    } else if ($day == "Saturday") {
                                        $hours = str_replace($endash, '-', $operatingHours[5]);
                                    } else if ($day == "Sunday") {
                                        $hours = str_replace($endash, '-', $operatingHours[6]);
                                    } 

                                    $operatingHours = substr($hours, strpos($hours, ':') + 2);
                                    $hours = explode(",", substr($hours, strpos($hours, ':') + 2)); // consider multiple operating hours
                                    foreach ($hours as $hour) {
                                        if ($hour == "Closed") { 
                                            $openingTime = "N/A"; 
                                        } else if ($hour == "Open 24 hours") {
                                            $openingTime = date("g:i A", strtotime($currentNode->ETD));
                                        } else {
                                            $hour = explode("-", $hour);
                                            $openingTime = trim($hour[0]);
                                            $openingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($openingTime));
                                            $closingTime = trim($hour[1]);
                                            $closingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($closingTime));
            
                                            // add AM or PM to opening time if needed
                                            if (stripos($closingTime, 'AM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                                $openingTime .= ' AM';
                                            } else if (stripos($closingTime, 'PM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                                $openingTime .= ' PM';
                                            }
                                        }

                                        $openingTimes[] = $openingTime;
                                    } 
                                } else { // treat as open 24 hours
                                    $openingTimes[] = date("g:i A", strtotime($currentNode->ETD));
                                }

                                $count = count($openingTimes);
                                if ($count === 1) {
                                    $openingTimes = $openingTimes[0];
                                }

                                if (is_array($openingTimes)) { // multiple operating hours
                                    for ($i = 0; $i < $count; $i++) {
                                        $openingTime = $openingTimes[$i];
                                        $openingDateTime = new DateTime($openingTime);

                                        if ($openingDateTime < $earliestOpeningDateTime && $openingDateTime >= $ETDDateTime) {
                                            $openingTime = $openingDateTime->format('h:i A');
                                            $earliestOpeningTime = $openingTime;
                                        }
                                    }
                                } else {
                                    $openingDateTime = new DateTime($openingTimes); 
                                    if ($openingDateTime < $earliestOpeningDateTime) {
                                        $openingTime = $openingDateTime->format('h:i A');
                                        $earliestOpeningTime = $openingTime;
                                        $earliestOpeningLocation = $nextReachableLocation;
                                    }
                                } 
                            }

                            // get traveling distance and duration from distance matrix 
                            $origin = $currentNode->location; 
                            $destination = $earliestOpeningLocation;
                            $travelingDuration = 0;
                            $travelingDistance = "";
                            $travelingMode = "driving";

                            foreach ($distanceMatrix as $entry) {
                                if (isset($entry[$origin])) {
                                    $originEntry = $entry[$origin];
                                    foreach ($originEntry as $info) {
                                        if ($info['name'] == $destination) {
                                            $travelingDuration = (int)$info['duration']; 
                                            $travelingDistance = $info['distance']; 
                                            break;
                                        }
                                    }
                                }
                            }

                            // if traveling duration < 4 minutes, change traveling mode to walking
                            if ($travelingDuration < 4) {
                                // retrieve lat and long
                                $query = "SELECT * FROM attractions WHERE name = '$earliestOpeningLocation'";
                                $result = mysqli_query($handler, $query);
                                $details = mysqli_fetch_assoc($result);
                                $lat = (float)$details['latitude'];
                                $long = (float)$details['longitude'];

                                $originCoord = [$currentNode->lat, $currentNode->long];
                                $destinationCoord = [$lat, $long];

                                $routeInfo = getRouteInfo($originCoord, $destinationCoord);
                                $travelingDuration = $routeInfo['duration'] !== "N/A" ? $routeInfo['duration'] : "N/A";
                                $travelingDistance = $routeInfo['distance'] !== "N/A" ? $routeInfo['distance'] : "N/A";
                                $travelingMode = "walking";
                            }

                            // calculate ETD
                            $ETD = new DateTime($earliestOpeningTime);
                            $ETD->modify("-$travelingDuration minutes");
                            $ETD = $ETD->format('h:i A');

                            // calculate interval between ETA and ETD
                            $nodeETA = new DateTime($currentNode->ETD);
                            $nodeETD = new DateTime($ETD);
                            $interval = $nodeETA->diff($nodeETD);
                            $interval = $interval->h * 60 + $interval->i;
    
                            // allocate free node for free session
                            $freeNode = new Node($currentNode->location, "attraction", $currentNode->lat, $currentNode->long, $currentNode->ETD, $ETD, null, $currentNode->mealCount, $currentNode, null, null, null, $interval);
                            array_push($sequence, $freeNode);
                        }
                    }
                }
            } else { 
                // add closest node to sequence of locations and visited locations
                usort($children, function($a, $b) {
                    return strtotime($a->ETA) - strtotime($b->ETA);
                });

                // if traveling duration < 4 minutes, change traveling mode to walking
                if ($children[0]->duration < 4) {
                    $originCoord = [$currentNode->lat, $currentNode->long];
                    $destinationCoord = [$children[0]->lat, $children[0]->long];

                    $routeInfo = getRouteInfo($originCoord, $destinationCoord);
                    $travelingDuration = $routeInfo['duration'] !== "N/A" ? $routeInfo['duration'] : "N/A";
                    $travelingDistance = $routeInfo['distance'] !== "N/A" ? $routeInfo['distance'] : "N/A";
                    $travelingMode = "walking";

                    // calculate new ETA
                    $ETA = new DateTime($currentNode->ETD);
                    $ETA->modify("+$travelingDuration minutes");
                    $ETA = $ETA->format('h:i A');

                    // calculate new ETD
                    $ETDDateTime = new DateTime($ETA);
                    if ($children[0]->category == "attraction") {
                        $ETDDateTime->modify("+$attractionStay hours"); // ETA + duration stay
                    } else {
                        $ETDDateTime->modify("+$eateryStay hours"); // ETA + duration stay
                    }
                    $ETD = $ETDDateTime->format('h:i A');

                    // update node details 
                    $children[0]->ETA = $ETA;
                    $children[0]->ETD = $ETD;
                    $children[0]->duration = $travelingDuration;
                    $children[0]->distance = $travelingDistance;
                    $children[0]->mode = $travelingMode;
                }

                array_push($sequence, $children[0]);
                if ($children[0]->category == "attraction") {
                    array_push($visitedAttractions, $children[0]->location);
                } else if ($children[0]->category == "eatery") {
                    array_push($visitedEateries, $children[0]->location);
                }
            }
        }

        // add sequence to itinerary 
        array_push($itinerary, $sequence);

        // update current date
        $currentDateObj = new DateTime($currentDate);
        $currentDateObj->modify('+1 day'); 
        $currentDate = $currentDateObj->format('Y-m-d');
    }

    return $itinerary;
}

// check if yes button is clicked 
if (isset($_POST['yes'])) {
    // update itinerary in database
    $query = "UPDATE trips SET itinerary = '" . mysqli_real_escape_string($handler, json_encode($_SESSION['itinerary'])) . "' WHERE tripID =" . $tripID;
    mysqli_query($handler, $query);

    // retrieve updated itinerary from database
    $query = "SELECT * FROM trips WHERE tripID = $tripID";
    $result = mysqli_query($handler, $query);
    $trip = mysqli_fetch_assoc($result);
    $itinerary = json_decode($trip['itinerary'], true);

    // retrieve relevant information from session variables
    $edit = $_SESSION['edit'];
    $editedLocation = $_SESSION['editedLocation']; 
    $editedDayNum = $_SESSION['editedDayNum']; 
    $newStop = $_SESSION['newStop']; 
    $newDuration = $_SESSION['newDuration']; 
    $newDay = $_SESSION['newDay']; 
    $isFreeNode = $_SESSION['isFreeNode']; 
    $editedCategory = $_SESSION['editedCategory']; 

    $errorMessage = "";
    $itineraryEdited = true;
} 

// check if edit form is submitted 
if (isset($_POST['save'])) {
    $editedDayNum = (int)$_POST['dayNum'];
    if (!isset($_POST['edit'])) { 
        $editError = "Please select one radio button";
        $itineraryEdited = false;
    } else {
        $editError = "";
        $dayOfWeek = "";
        // retrieve form values
        $edit = $_POST['edit'];
        $editedLocation = $_POST['location'];
        $isFreeNode = $_POST['freeNode'];

        // find location in current day's itinerary
        $locationIndex = -1;
        $count = 0;
        foreach ($itinerary[$editedDayNum - 1] as $key => $node) {
            if ($node['location'] == $editedLocation) {
                $count += 1;
                $locationIndex = $key;
                $editedCategory = $node['category'];
                if ($isFreeNode == "no") {
                    break;
                } else {
                    if ($count == 2) {
                        break;
                    }
                }
            }
        }

        // make changes accordingly
        if ($edit == "sequence") {
            $newStop = (int)$_POST['stop'];
            
            // insert location at desired index
            $removedLocation = array_splice($itinerary[$editedDayNum - 1], $locationIndex, 1);
            array_splice($itinerary[$editedDayNum - 1], $newStop - 1, 0, $removedLocation);
        } else if ($edit == "day") {
            $newDay = (int)$_POST['day'];
            $newDayDate = date('Y-m-d', strtotime($startDate . ' +' . ($newDay - 1) . ' days'));

            // retrieve operating hours of location on new day 
            $editedNode = null; 
            $editedIndex = 0;
            foreach ($itinerary[$editedDayNum - 1] as $key => $node) {
                if ($node['location'] == $editedLocation) {
                    $editedNode = $node;
                    $editedIndex = $key;
                }
            }
            $query = "SELECT * FROM " . ($editedNode['category'] == "attraction" ? "attractions" : "eateries") . " WHERE name = '$editedLocation'";
            $result = mysqli_query($handler, $query);
            $details = mysqli_fetch_assoc($result);
            $operatingHours = $details['operatingHours'] !== "No operating hours available" ? explode("_ ", $details['operatingHours']) : "No operating hours available";

            // get opening and closing time
            $openingTimes = [];
            $closingTimes = [];
            $endash = html_entity_decode('&#x2013;', ENT_COMPAT, 'UTF-8');
            if ($operatingHours !== "No operating hours available") {
                $dayOfWeek = date('l', strtotime($newDayDate));
                if ($dayOfWeek == "Monday") {
                    $hours = str_replace($endash, '-', $operatingHours[0]);
                } else if ($dayOfWeek == "Tuesday") {
                    $hours = str_replace($endash, '-', $operatingHours[1]);
                } else if ($dayOfWeek == "Wednesday") {
                    $hours = str_replace($endash, '-', $operatingHours[2]);
                } else if ($dayOfWeek == "Thursday") {
                    $hours = str_replace($endash, '-', $operatingHours[3]);
                } else if ($dayOfWeek == "Friday") {
                    $hours = str_replace($endash, '-', $operatingHours[4]);
                } else if ($dayOfWeek == "Saturday") {
                    $hours = str_replace($endash, '-', $operatingHours[5]);
                } else if ($dayOfWeek == "Sunday") {
                    $hours = str_replace($endash, '-', $operatingHours[6]);
                } 
                $operatingHours = substr($hours, strpos($hours, ':') + 2);
            } 
            $itinerary[$editedDayNum - 1][$editedIndex]['operatingHours'] = $operatingHours;

            // removed location from current day's itinerary 
            $removedLocation = null;
            foreach ($itinerary[$editedDayNum - 1] as $key => $node) {
                if ($node['location'] == $editedLocation) {
                    $removedLocation = array_splice($itinerary[$editedDayNum - 1], $key, 1);
                    break;
                }
            }

            // calculate traveling duration between each node in new day and location and find shortest
            $index = -1;
            $shortestDuration = PHP_INT_MAX;

            foreach ($itinerary[$newDay - 1] as $key => $node) {
                if ($key !== count($itinerary[$newDay - 1]) - 1) { // not including end node
                    // retrieve traveling duration
                    $destination = $editedLocation;
                    $origin = $itinerary[$newDay - 1][$key]['location'];
                    $travelingDuration = 0;

                    foreach ($distanceMatrix as $entry) {
                        if (isset($entry[$origin])) {
                            $originEntry = $entry[$origin];
                            foreach ($originEntry as $info) {
                                if ($info['name'] == $destination) {
                                    $travelingDuration = (int)$info['duration']; 
                                    break;
                                }
                            }
                        }
                    }

                    // update index when shorter duration is found
                    if ($travelingDuration < $shortestDuration) {
                        $shortestDuration = $travelingDuration;
                        $index = $key;
                    }
                }   
            }
    
            // insert location after node with shortest traveling duration
            array_splice($itinerary[$newDay - 1], $index + 1, 0, $removedLocation);

        } else if ($edit == "duration") {
            $newDuration = (int)$_POST['duration'];
            // recalculate ETD based on new duration
            $ETD = new DateTime($itinerary[$editedDayNum - 1][$locationIndex]['ETA']);
            $ETD->modify("+$newDuration hours"); 
            $ETD = $ETD->format('h:i A');
            $itinerary[$editedDayNum - 1][$locationIndex]['ETD'] = $ETD;
            $itinerary[$editedDayNum - 1][$locationIndex]['stay'] = $newDuration;

            // update ETA and ETD of each location after edited location
            foreach($itinerary[$editedDayNum - 1] as $key => $node) {
                if ($key > $locationIndex) {
                    // calculate ETA 
                    $ETA = new DateTime($itinerary[$editedDayNum - 1][$key - 1]['ETD']);
                    if (!is_null($itinerary[$editedDayNum - 1][$key]['duration'])) {
                        $travelingDuration = $itinerary[$editedDayNum - 1][$key]['duration'];
                        $ETA->modify("+$travelingDuration minutes");
                    }
                    $ETA = $ETA->format('h:i A');
                    
                    // calculate ETD 
                    $ETD = new DateTime($ETA);
                    $interval = $node['stay'];
                    $ETD->modify("+$interval hours"); 
                    $ETD = $ETD->format('h:i A');

                    // update node details 
                    $itinerary[$editedDayNum - 1][$key]['ETA'] = $ETA;
                    $itinerary[$editedDayNum - 1][$key]['ETD'] = $ETD;
                }
            }
        } else if ($edit == "remove") {
            // remove node with locationIndex
            unset($itinerary[$editedDayNum - 1][$locationIndex]);
            $itinerary[$editedDayNum - 1] = array_values($itinerary[$editedDayNum - 1]);
        }

        if ($edit !== "duration") {
            // recalculate ETA and ETD for current day
            foreach($itinerary[$editedDayNum - 1] as $key => $node) {
                $travelingDuration = 0;
                $travelingDistance = "";
                $travelingMode = "driving";

                if ($key !== 0) { // not start node
                    // consider free session or meal session
                    if (is_null($node['operatingHours']) && $key !== count($itinerary[$editedDayNum - 1]) - 1) {
                        $itinerary[$editedDayNum - 1][$key]['location'] = $itinerary[$editedDayNum - 1][$key - 1]['location']; // previous node's location
                        $itinerary[$editedDayNum - 1][$key]['lat'] = $itinerary[$editedDayNum - 1][$key - 1]['lat']; 
                        $itinerary[$editedDayNum - 1][$key]['long'] = $itinerary[$editedDayNum - 1][$key - 1]['long'];
                        $itinerary[$editedDayNum - 1][$key]['ETA'] =  $itinerary[$editedDayNum - 1][$key - 1]['ETD'];

                        // calculate ETD
                        $ETD = new DateTime($itinerary[$editedDayNum - 1][$key]['ETA']);
                        $interval = $itinerary[$editedDayNum - 1][$key]['stay'];
                        if ($itinerary[$editedDayNum - 1][$key]['category'] == "attraction") { // free session
                            $ETD->modify("+$interval minutes");
                        } else { // meal session
                            $ETD->modify("+$interval hours"); 
                        }
                        $ETD = $ETD->format('h:i A');
                        $itinerary[$editedDayNum - 1][$key]['ETD'] = $ETD;
                        
                    } else {
                        // retrieve traveling duration and distance
                        $destination = $node['location'];
                        $origin = $itinerary[$editedDayNum - 1][$key - 1]['location'];

                        foreach ($distanceMatrix as $entry) {
                            if (isset($entry[$origin])) {
                                $originEntry = $entry[$origin];
                                foreach ($originEntry as $info) {
                                    if ($info['name'] == $destination) {
                                        $travelingDuration = (int)$info['duration']; 
                                        $travelingDistance = $info['distance']; 
                                        break;
                                    }
                                }
                            }
                        }

                        // if traveling duration < 4 minutes, change traveling mode to walking
                        if ($travelingDuration < 4) {
                            $destinationCoord = [$node['lat'], $node['long']];
                            $originCoord = [$itinerary[$editedDayNum - 1][$key - 1]['lat'], $itinerary[$editedDayNum - 1][$key - 1]['long']];

                            $routeInfo = getRouteInfo($originCoord, $destinationCoord);
                            $travelingDuration = $routeInfo['duration'] !== "N/A" ? $routeInfo['duration'] : "N/A";
                            $travelingDistance = $routeInfo['distance'] !== "N/A" ? $routeInfo['distance'] : "N/A";
                            $travelingMode = "walking";
                        }

                        // calculate ETA 
                        $ETA = new DateTime($itinerary[$editedDayNum - 1][$key - 1]['ETD']);
                        $ETA->modify("+$travelingDuration minutes");
                        $ETA = $ETA->format('h:i A');

                        // update node details 
                        $itinerary[$editedDayNum - 1][$key]['ETA'] = $ETA;
                        $itinerary[$editedDayNum - 1][$key]['duration'] = $travelingDuration;
                        $itinerary[$editedDayNum - 1][$key]['distance'] = $travelingDistance;
                        $itinerary[$editedDayNum - 1][$key]['mode'] = $travelingMode;

                        if ($key !== count($itinerary[$editedDayNum - 1]) - 1) { // not end node 
                            // calculate ETD 
                            $ETD = new DateTime($ETA);
                            $interval = $node['stay'];
                            $ETD->modify("+$interval hours"); 
                            $ETD = $ETD->format('h:i A');
                            $itinerary[$editedDayNum - 1][$key]['ETD'] = $ETD;
                        }
                    }
                }
            }
        }  
        
        if ($edit == "day") {
            // recalculate ETA and ETD for new day
            foreach($itinerary[$newDay - 1] as $key => $node) {
                $travelingDuration = 0;
                $travelingDistance = "";
                $travelingMode = "driving";

                if ($key !== 0) { // not start node
                    // consider free session or meal session
                    if (is_null($node['operatingHours']) && $key !== count($itinerary[$newDay - 1]) - 1) {
                        $itinerary[$newDay - 1][$key]['location'] = $itinerary[$newDay - 1][$key - 1]['location']; // previous node's location
                        $itinerary[$newDay - 1][$key]['lat'] = $itinerary[$newDay - 1][$key - 1]['lat']; 
                        $itinerary[$newDay - 1][$key]['long']= $itinerary[$newDay - 1][$key - 1]['long'];
                        $itinerary[$newDay - 1][$key]['ETA']=  $itinerary[$newDay - 1][$key - 1]['ETD'];

                        // calculate ETD
                        $ETD = new DateTime($itinerary[$newDay - 1][$key]['ETA']);
                        $interval = $itinerary[$newDay - 1][$key]['stay'];
                        if ($itinerary[$newDay - 1][$key]['category'] == "attraction") { // free session
                            $ETD->modify("+$interval minutes");
                        } else { // meal session
                            $ETD->modify("+$interval hours"); 
                        }
                        $ETD = $ETD->format('h:i A');
                        $itinerary[$newDay - 1][$key]['ETD'] = $ETD;
                    } else {
                        // retrieve traveling duration and distance
                        $destination = $node['location'];
                        $origin = $itinerary[$newDay - 1][$key - 1]['location'];

                        foreach ($distanceMatrix as $entry) {
                            if (isset($entry[$origin])) {
                                $originEntry = $entry[$origin];
                                foreach ($originEntry as $info) {
                                    if ($info['name'] == $destination) {
                                        $travelingDuration = (int)$info['duration']; 
                                        $travelingDistance = $info['distance']; 
                                        break;
                                    }
                                }
                            }
                        }

                        // if traveling duration < 4 minutes, change traveling mode to walking
                        if ($travelingDuration < 4) {
                            $destinationCoord = [$node['lat'], $node['long']];
                            $originCoord = [$itinerary[$newDay - 1][$key - 1]['lat'], $itinerary[$newDay - 1][$key - 1]['long']];

                            $routeInfo = getRouteInfo($originCoord, $destinationCoord);
                            $travelingDuration = $routeInfo['duration'] !== "N/A" ? $routeInfo['duration'] : "N/A";
                            $travelingDistance = $routeInfo['distance'] !== "N/A" ? $routeInfo['distance'] : "N/A";
                            $travelingMode = "walking";
                        }

                        // calculate ETA 
                        $ETA = new DateTime($itinerary[$newDay - 1][$key - 1]['ETD']);
                        $ETA->modify("+$travelingDuration minutes");
                        $ETA = $ETA->format('h:i A');

                        // update node details 
                        $itinerary[$newDay - 1][$key]['ETA'] = $ETA;
                        $itinerary[$newDay - 1][$key]['duration'] = $travelingDuration;
                        $itinerary[$newDay - 1][$key]['distance'] = $travelingDistance;
                        $itinerary[$newDay - 1][$key]['mode'] = $travelingMode;

                        if ($key !== count($itinerary[$newDay - 1]) - 1) { // not end node 
                            // calculate ETD 
                            $ETD = new DateTime($ETA);
                            $interval = $node['stay'];
                            $ETD->modify("+$interval hours"); 
                            $ETD = $ETD->format('h:i A');
                            $itinerary[$newDay - 1][$key]['ETD'] = $ETD;
                        }
                    }
                }
            }
        }
        
        // check if ETA and ETD within operating hours for edited itinerary
        foreach($itinerary[$editedDayNum - 1] as $key => $node) {
            // get opening and closing time
            if (!is_null($node['operatingHours'])) {
                $openingTimes = [];
                $closingTimes = [];
                if ($node['operatingHours'] !== "No operating hours available") {
                    $hours = explode(",", $node['operatingHours']); // consider multiple operating hours
                    foreach ($hours as $hour) {
                        if ($hour == "Open 24 hours") {
                            $openingTime = "5:00 AM"; 
                            $closingTime = "11:59 PM";
                        } else {
                            $hour = explode("-", $hour);
                            $openingTime = trim($hour[0]);
                            $openingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($openingTime));
                            $closingTime = trim($hour[1]);
                            $closingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($closingTime));
                    
                            // add AM or PM to opening time if needed
                            if (stripos($closingTime, 'AM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                $openingTime .= ' AM';
                            } else if (stripos($closingTime, 'PM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                $openingTime .= ' PM';
                            }
                            if ($closingTime == "12:00AM") { 
                                $closingTime = "11:59 PM";
                            }
                        }
                    
                        $openingTimes[] = $openingTime;
                        $closingTimes[] = $closingTime;
                    }
                } else { // treat as open 24 hours
                    $openingTimes[] = "5:00 AM";
                    $closingTimes[] = "11:59 PM";
                }
    
                $count = count($openingTimes);
                if ($count === 1) {
                    $openingTimes = $openingTimes[0];
                    $closingTimes = $closingTimes[0];
                }
    
                // check if ETA and ETD are within opening and closing time
                if (is_array($openingTimes)) { // multiple operating hours
                    for ($i = 0; $i < $count; $i++) {
                        $openingTime = $openingTimes[$i];
                        $closingTime = $closingTimes[$i];
    
                        if (strtotime($node['ETA']) < strtotime($openingTime) || strtotime($node['ETD']) > strtotime($closingTime)) {
                            $errorMessage = "You will be at a location beyond its operating hours on Day ". $editedDayNum .". Are you sure you want to make this change to the itinerary?";
                            break;
                        }
                    }
                } else {
                    if (strtotime($node['ETA']) < strtotime($openingTimes) || strtotime($node['ETD']) > strtotime($closingTimes)) {
                        $errorMessage = "You will be at a location beyond its operating hours on Day ". $editedDayNum .". Are you sure you want to make this change to the itinerary?"; 
                    }
                }
            }
        }

        // check if ETA and ETD within operating hours for the day at which location is moved to
        if ($edit == "day") {
            foreach($itinerary[$newDay - 1] as $key => $node) {
                if (!is_null($node['operatingHours'])) {
                    if ($node['operatingHours'] == "Closed") {
                        $errorMessage = $editedLocation . " is closed on " . $dayOfWeek .". Are you sure you want to make this change to the itinerary?"; 
                        break;
                    } else {
                        // get opening and closing time
                        $openingTimes = [];
                        $closingTimes = [];
                        if ($node['operatingHours'] !== "No operating hours available") {
                            $hours = explode(",", $node['operatingHours']); // consider multiple operating hours
                            foreach ($hours as $hour) {
                                if ($hour == "Open 24 hours") {
                                    $openingTime = "5:00 AM"; 
                                    $closingTime = "11:59 PM";
                                } else {
                                    $hour = explode("-", $hour);
                                    $openingTime = trim($hour[0]);
                                    $openingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($openingTime));
                                    $closingTime = trim($hour[1]);
                                    $closingTime = preg_replace('/[^\d:\sA-Za-z]/', '', trim($closingTime));
                            
                                    // add AM or PM to opening time if needed
                                    if (stripos($closingTime, 'AM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                        $openingTime .= ' AM';
                                    } else if (stripos($closingTime, 'PM') !== false && !stripos($openingTime, 'AM') && !stripos($openingTime, 'PM')) {
                                        $openingTime .= ' PM';
                                    }
                                    if ($closingTime == "12:00AM") { 
                                        $closingTime = "11:59 PM";
                                    }
                                }
                            
                                $openingTimes[] = $openingTime;
                                $closingTimes[] = $closingTime;
                            }
                        } else { // treat as open 24 hours
                            $openingTimes[] = "5:00 AM";
                            $closingTimes[] = "11:59 PM";
                        }
                        
                        $count = count($openingTimes);
                        if ($count === 1) {
                            $openingTimes = $openingTimes[0];
                            $closingTimes = $closingTimes[0];
                        }
    
                        // check if ETA and ETD are within opening and closing time
                        if (is_array($openingTimes)) { // multiple operating hours
                            for ($i = 0; $i < $count; $i++) {
                                $openingTime = $openingTimes[$i];
                                $closingTime = $closingTimes[$i];
    
                                if (strtotime($node['ETA']) < strtotime($openingTime) || strtotime($node['ETD']) > strtotime($closingTime)) {
                                    $errorMessage = "You will be at a location beyond its operating hours on Day ". $newDay .". Are you sure you want to make this change to the itinerary?"; 
                                    break;
                                }
                            }
                        } else {
                            if (strtotime($node['ETA']) < strtotime($openingTimes) || strtotime($node['ETD']) > strtotime($closingTimes)) {
                                $errorMessage = "You will be at a location beyond its operating hours on Day ". $newDay .". Are you sure you want to make this change to the itinerary?"; 
                            }
                        }
                    }
                }
            }
        }

        // check if end time is exceeded
        if ($errorMessage == "") {
            if (strtotime($itinerary[$editedDayNum - 1][count($itinerary[$editedDayNum - 1]) - 1]['ETA']) > strtotime($endTime)) {
                $errorMessage = "You will reach your accomodation beyond the desired end time on Day ". $editedDayNum .". Are you sure you want to make this change to the itinerary?"; 
            }
    
            // check if end time is exceeded for the day at which location is moved to
            if ($edit == "day") {
                if (strtotime($itinerary[$newDay - 1][count($itinerary[$newDay - 1]) - 1]['ETA']) > strtotime($endTime)) {
                    $errorMessage = "You will reach your accomodation beyond the desired end time on Day ". $newDay .". Are you sure you want to make this change to the itinerary?"; 
                }
            }
        }
        
        // store relevant information as session variables 
        $_SESSION['itinerary'] = $itinerary;
        $_SESSION['edit'] = $edit;
        $_SESSION['editedLocation'] = $editedLocation; 
        $_SESSION['editedDayNum'] = $editedDayNum; 
        $_SESSION['newStop'] = $newStop; 
        $_SESSION['newDuration'] = $newDuration; 
        $_SESSION['newDay'] = $newDay; 
        $_SESSION['isFreeNode'] = $isFreeNode;
        $_SESSION['editedCategory'] = $editedCategory;

        // if all location visits are within operating hours and end time is not exceeded
        if ($errorMessage == "") {
            // update itinerary in database
            $query = "UPDATE trips SET itinerary = '" . mysqli_real_escape_string($handler, json_encode($itinerary)) . "' WHERE tripID =" . $tripID;
            mysqli_query($handler, $query);

            // retrieve updated itinerary from database
            $query = "SELECT * FROM trips WHERE tripID = $tripID";
            $result = mysqli_query($handler, $query);
            $trip = mysqli_fetch_assoc($result);
            $itinerary = json_decode($trip['itinerary'], true);

            $itineraryEdited = true;
        }
    }
} 
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Itinerary</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="img/person-walking-luggage-solid.svg">
        <script src="https://kit.fontawesome.com/ffaaf7e5e9.js" crossorigin="anonymous"></script>
        <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&key=AIzaSyA9z_VKgS6hG9p899oN5v8kcTHbSdEHJEM"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                <a href="trip-details.php?tripID=<?php echo $_SESSION['tripID'];?>"><button class="tab" onclick="openTab(event, 'past')" style="margin-right: 13px;"><b>1&nbsp;&nbsp;&nbsp;Details</b></button></a>
                <a href="locations.php?tripID=<?php echo $_SESSION['tripID'];?>"><button class="tab" onclick="openTab(event, 'ongoing')" style="margin-right: 13px;"><b>2&nbsp;&nbsp;&nbsp;Locations</b></button></a>
                <a href="itinerary.php"><button class="tab active" onclick="openTab(event, 'upcoming')" disabled><b>3&nbsp;&nbsp;&nbsp;Itinerary</b></button></a>
            </div>
            <div class="empty-div3"></div>

            <!--itinerary-->
            <div class="itinerary-div">
                <!--slider for day number-->
                <div class="day-number"> 
                    <div style="transform: translate(-360px, -10px);">
                        <label class="label" style="color: #4178A4;">Day</label>
                    </div>
                    <div class="day-slider">
                        <?php 
                            for ($i = 1; $i <= $numOfDays; $i++) {
                                ?>
                                <button class="number-tab" onclick="openTable(event, 'table<?php echo $i;?>')"><b><?php echo $i;?></b></button>
                                <?php
                            }
                        ?>
                    </div>
                </div>
                <div style="width:100%; height: 15px;"></div>
              
                <?php 
                    $currentDate = strtotime($startDate);
                    $lastDate = strtotime($endDate);
                    $formattedDates = array(); 
                    while ($currentDate <= $lastDate) {
                        $formattedDate = date("F d, Y (l)", $currentDate); // September 28, 2023 (Thursday)
                        array_push($formattedDates, $formattedDate); // array of formatted dates to be printed
                        $currentDate = strtotime("+1 day", $currentDate);
                    }

                    $i = 0;
                    foreach($itinerary as $sequence) {
                        $i += 1;
                ?>
                <!--display itinerary edited message-->
                <div id="itinerary-edited-div" style="display: none;">
                    <i class="fa-regular fa-circle-check"></i><br>
                    <h3 id="edit-text" style="margin: auto; margin-top: 20px; width: 460px;"></h3>
                </div>

                <!--display select radio button message-->
                <div id="select-div" style="display: none;">
                    <i class="fa-regular fa-circle-xmark"></i><br>
                    <h3 id="select-text" style="margin: auto; margin-top: 20px; width: 460px;">Please select one radio button before making changes to the itinerary.</h3>
                </div>

                <!--display confirmation popup-->
                <div id="confirmation-popup" style="display: none;">
                    <i class="fa-regular fa-circle-xmark"></i><br>
                    <h3 id="popup-text" style="margin: auto; margin-top: 20px; width: 460px;"></h3>
                    <form id="confirm-edit" action="itinerary.php" method="post">
                        <button id="yes-btn" type="submit" name="yes">Yes</button>
                        <a href="itinerary.php"><button id="no-btn">No</button></a>
                    </form>
                </div>

                <!--itinerary table--> 
                <div class="table-div" id="table<?php echo $i;?>">
                    <div class="table-title">Day <?php echo $i;?>:&nbsp;&nbsp;<?php echo $formattedDates[$i - 1];?></div>
                    <div class="itinerary-list" id="list<?php echo $i;?>">
                        <table class="itinerary-table">
                            <?php 
                                $locationNum = count($sequence);
                                for ($k = 1; $k <= $locationNum; $k++) {
                                    
                                    if ($k == 1) { // no ETA and operating hours
                                        ?>
                                            <tr>
                                                <td style="text-align: right;"> 
                                                    <div style="transform: translate(-60px, 0px);"><span class="time-span"><?php echo $sequence[$k - 1]['ETD'];?></span></div>
                                                    <div class="left-dashed"></div>
                                                </td>
                                                <td>
                                                    <div style="transform: translate(-68px, 0px);">
                                                        <button class="bed-icon"><?php echo $k;?></button>
                                                        <div class="vertical-line" style="height: 35px;"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="transform: translate(-68px, 0px);">
                                                        <?php 
                                                            $location = explode(",", $sequence[$k - 1]['location']);
                                                            $location = trim($location[0]);
                                                        ?>
                                                        <span class="location-span"><?php echo $location;?></span><br>
                                                        <br>
                                                        <?php 
                                                            if ($sequence[$k]['mode'] == "walking") {
                                                        ?>
                                                            <i class="fa-solid fa-person-walking"></i><span class="duration-span">&nbsp;&nbsp;<?php echo $sequence[$k]['duration'] . " mins (" . $sequence[$k]['distance'] . ")" ;?></span>
                                                            <div class="right-dashed"></div>
                                                        <?php
                                                            } else if ($sequence[$k]['mode'] == "driving") {
                                                        ?>
                                                            <i class="fa-solid fa-car"></i><span class="duration-span">&nbsp;&nbsp;<?php echo $sequence[$k]['duration'] . " mins (" . $sequence[$k]['distance'] . ")" ;?></span>
                                                            <div class="right-dashed"></div>
                                                        <?php
                                                            } else {
                                                        ?>
                                                            <div class="right-dashed" style="width: 452px; transform: translate(-4px, 7.5px);"></div>
                                                        <?php
                                                            }
                                                        ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                    } else if ($k == $locationNum) { // no ETD, operating hours, traveling time & duration
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="time-span"><?php echo $sequence[$k - 1]['ETA'];?></span>
                                                </td>
                                                <td>
                                                    <div style="transform: translate(-68px, 0px); margin-top: -2px;"><button class="bed-icon"><?php echo $k;?></button></div>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $location = explode(",", $sequence[$k - 1]['location']);
                                                        $location = trim($location[0]);
                                                    ?>
                                                    <div style="transform: translate(-68px, 0px); margin-top: -2px;"><span class="location-span"><?php echo $location;?></span></div>
                                                </td>
                                            </tr>
                                        <?php 
                                    } else {
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="time-span"><?php echo $sequence[$k - 1]['ETA'] . " - " . $sequence[$k - 1]['ETD'];?></span>
                                                    <?php 
                                                        if (is_null($sequence[$k - 1]['operatingHours'])) { // free node
                                                            echo '<div class="left-dashed" style="transform: translate(0px, 20px);"></div>';
                                                        } else {
                                                            echo '<div class="left-dashed" style="transform: translate(0px, 39px);"></div>';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div style="transform: translate(-68px, 0px); margin-top: -2px;">
                                                        <?php 
                                                            if ($sequence[$k - 1]['category'] == "attraction") {
                                                                echo "<button class='attraction-icon'>" . $k . "</button>";
                                                            } else {
                                                                echo "<button class='eat-icon'>" . $k . "</button>";
                                                            }   

                                                        ?>
                                                        <?php 
                                                            if (is_null($sequence[$k - 1]['operatingHours'])) { // free node
                                                                echo '<div class="vertical-line" style="height: 35px;"></div>';
                                                            } else {
                                                                echo '<div class="vertical-line"></div>';
                                                            }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="transform: translate(-68px, 0px); margin-top: -2px;">
                                                        <!--edit form for user to make changes to itinerary-->
                                                        <form id="edit-form" action="itinerary.php" method="post">
                                                            <div class="edit-div" id="edit-div<?php echo $i . $k; ?>" style="display: none;">
                                                                <?php 
                                                                    if (is_null($sequence[$k - 1]['operatingHours'])) { // free node
                                                                        if ($sequence[$k - 1]['category'] == "attraction") {
                                                                            echo '<div class="waypoint-text">Free Session</div><br>';
                                                                        } else {
                                                                            echo '<div class="waypoint-text">Meal Session</div><br>';
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <div class="waypoint-text"><?php echo $sequence[$k - 1]['location'];?></div><br>
                                                                        <?php
                                                                    }
                                                                ?>
                                                                
                                                                <input type="radio" id="sequence" name="edit" value="sequence" style="margin-left: 30px;">
                                                                <label for="sequence">Move to another stop:&nbsp;</label>
                                                                <select name="stop" id="stop">
                                                                    <?php 
                                                                        for ($j = 1; $j <= $locationNum; $j++) {
                                                                            if (is_null($sequence[$k - 1]['operatingHours'])) { // free node
                                                                                if (($sequence[$k - 1]['location'] !== $sequence[$j - 1]['location'] && ($j - 1) !== 0 && ($j - 1) !== ($locationNum - 1)) || ($sequence[$k - 1]['location'] == $sequence[$j - 1]['location'] && !is_null($sequence[$j - 1]['operatingHours']))) {
                                                                                    echo "<option value='$j'>Stop $j</option>";
                                                                                }
                                                                            } else {
                                                                                if (($sequence[$k - 1]['location'] !== $sequence[$j - 1]['location'] && ($j - 1) !== 0 && ($j - 1) !== ($locationNum - 1)) || ($sequence[$k - 1]['location'] == $sequence[$j - 1]['location'] && is_null($sequence[$j - 1]['operatingHours']))) {
                                                                                    echo "<option value='$j'>Stop $j</option>";
                                                                                }
                                                                            }
                                                                        }
                                                                    ?>
                                                                </select><br>

                                                                <?php 
                                                                if (!is_null($sequence[$k - 1]['operatingHours'])) { // not a free node
                                                                    ?>
                                                                    <input type="radio" id="day" name="edit" value="day" style="margin-left: 30px; margin-top: 7px;">
                                                                    <label for="day">Move to another day:&nbsp;&nbsp;</label>
                                                                    <select name="day" id="day">
                                                                        <?php 
                                                                            for ($j = 1; $j <= $numOfDays; $j++) {
                                                                                if ($j !== $i) {
                                                                                    echo "<option value='$j'>Day $j</option>";
                                                                                }
                                                                            }
                                                                        ?>
                                                                    </select><br>

                                                                    <input type="radio" id="duration" name="edit" value="duration" style="margin-left: 30px; margin-top: 7px;">
                                                                    <label for="duration">Edit duration:&nbsp;&nbsp;</label>
                                                                    <input type="number" id="duration" name="duration" min="1" max="6" value="<?php echo $sequence[$k - 1]['stay'];?>">&nbsp;&nbsp;hour(s)<br>
                                                                    <?php
                                                                }
                                                                ?>
                                                                
                                                                <input type="radio" id="remove" name="edit" value="remove" style="margin-left: 30px; margin-top: 7px;">
                                                                <label for="remove">Remove from itinerary</label><br>

                                                                <?php 
                                                                if (is_null($sequence[$k - 1]['operatingHours'])) { // free node
                                                                    echo '<input type="hidden" id="freeNode" name="freeNode" value="yes">';
                                                                } else {
                                                                    echo '<input type="hidden" id="freeNode" name="freeNode" value="no">';
                                                                }
                                                                ?>
                                                                <input type="hidden" id="location" name="location" value="<?php echo $sequence[$k - 1]['location'];?>">
                                                                <input type="hidden" id="dayNum" name="dayNum" value="<?php echo $i;?>">
                                                                <button class="save-btn1" type="submit" name="save">Save Changes</button>
                                                            </div>
                                                        </form>
                                                        <?php 
                                                            if (is_null($sequence[$k - 1]['operatingHours'])) { // free node 
                                                                if ($sequence[$k - 1]['category'] == "attraction") {
                                                                ?>
                                                                    <div style="transform: translate(0px, -5px);"><span class="location-span">Free Session</span><button class="edit-btn" onclick="showEditDiv('edit-div<?php echo $i . $k;?>')"><i class="fa-solid fa-pen"></i></button></div>
                                                                    <br>
                                                                <?php
                                                                } else if ($sequence[$k - 1]['category'] == "eatery")  {
                                                                ?>
                                                                    <div style="transform: translate(0px, -5px);"><span class="location-span">Meal Session</span><button class="edit-btn" onclick="showEditDiv('edit-div<?php echo $i . $k;?>')"><i class="fa-solid fa-pen"></i></button></div>
                                                                    <br>
                                                                <?php
                                                                }

                                                                if ($sequence[$k]['mode'] == "walking") {
                                                                ?>
                                                                    <div style="transform: translate(0px, -10px);"><i class="fa-solid fa-person-walking"></i><span class="duration-span">&nbsp;&nbsp;<?php echo $sequence[$k]['duration'] . " mins (" . $sequence[$k]['distance'] . ")" ;?></span></div>
                                                                    <div class="right-dashed" style="transform: translate(115px, -17.5px);"></div>
                                                                <?php
                                                                } else if ($sequence[$k]['mode'] == "driving") {
                                                                ?>
                                                                    <div style="transform: translate(0px, -10px);"><i class="fa-solid fa-car"></i><span class="duration-span">&nbsp;&nbsp;<?php echo $sequence[$k]['duration'] . " mins (" . $sequence[$k]['distance'] . ")" ;?></span></div>
                                                                    <div class="right-dashed" style="transform: translate(115px, -17.5px);"></div>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <div class="right-dashed" style="width: 452px; transform: translate(-2px, 5.5px);"></div>
                                                                <?php
                                                                }
                                                
                                                            } else {
                                                                ?>
                                                                <div style="transform: translate(0px, -5px); margin-top: -2px;">
                                                                    <span class="location-span"><?php echo $sequence[$k - 1]['location'];?></span><button class="edit-btn" onclick="showEditDiv('edit-div<?php echo $i . $k;?>')"><i class="fa-solid fa-pen"></i></button><br>
                                                                    <div class="hours-div"><i class="fa-solid fa-clock"></i><span class="hours-span">&nbsp;&nbsp;<?php echo $sequence[$k - 1]['operatingHours'];?></span></div><br>
                                                                    <?php 
                                                                        if ($sequence[$k]['mode'] == "walking") {
                                                                    ?>
                                                                        <i class="fa-solid fa-person-walking"></i><span class="duration-span">&nbsp;&nbsp;<?php echo $sequence[$k]['duration'] . " mins (" . $sequence[$k]['distance'] . ")" ;?></span>
                                                                        <div class="right-dashed"></div>
                                                                    <?php
                                                                        } else if ($sequence[$k]['mode'] == "driving") {
                                                                    ?>
                                                                        <i class="fa-solid fa-car"></i><span class="duration-span">&nbsp;&nbsp;<?php echo $sequence[$k]['duration'] . " mins (" . $sequence[$k]['distance'] . ")" ;?></span>
                                                                        <div class="right-dashed"></div>
                                                                    <?php
                                                                        } else {
                                                                    ?>
                                                                        <div class="right-dashed" style="width: 452px; transform: translate(-2px, 5.5px);"></div>
                                                                    <?php
                                                                        }
                                                                    ?>
                                                                </div>
                                                                <?php
                                                            }
                                                        ?> 
                                                    </div>
                                                </td>  
                                            </tr>
                                        <?php
                                    }
                                }
                            ?>
                        </table>
                    </div>
                </div>
                <?php
                    }
                ?>
            </div>
        </div>

        
        <?php include "footer.php";?>
        <script>
            window.onload = underlineTrips();
            function underlineTrips() {
                let div = document.getElementById("nav-trips");
                div.style.display = "block";
            }

            // function to set default day for which itinerary is shown
            function setDefaultDay(editedDayNum, newDay) {
                // remove id from current default button 
                let currentDefaultBtn = document.getElementById('default-day');
                if (currentDefaultBtn) {
                    currentDefaultBtn.removeAttribute('id');
                }

                // assign id to new default button 
                let newDefaultBtn;
                if (newDay !== 0) {
                    newDefaultBtn = document.querySelector(`.day-slider .number-tab:nth-child(${newDay})`);
                } else if (editedDayNum !== 0) {
                    newDefaultBtn = document.querySelector(`.day-slider .number-tab:nth-child(${editedDayNum})`);
                } else { // show itinerary for day 1 by default
                    newDefaultBtn = document.querySelector('.day-slider .number-tab:nth-child(1)');
                }

                if (newDefaultBtn) {
                    newDefaultBtn.id = 'default-day';
                }
            }

            window.addEventListener('load', () => {
                setDefaultDay(<?php echo $editedDayNum; ?>, <?php echo $newDay; ?>);
                document.getElementById("default-day").click();
            });

            // function to show respective itinerary list based on day number selected
            function openTable(event, number) {
                table = document.getElementsByClassName("table-div");
                for (let i = 0; i < table.length; i++) {
                    table[i].style.display = "none";
                }

                tab = document.getElementsByClassName("number-tab");
                for (let i=0; i < tab.length; i++) {
                    tab[i].className = tab[i].className.replace(" active", "");
                }

                document.getElementById(number).style.display = "block";
                event.currentTarget.className += " active";
            }

            // function to show edit div
            function showEditDiv(number) {
                let div = document.getElementById(number);
                if (div.style.display === "none") {
                    div.style.display = "block";
                } else {
                    div.style.display = "none";
                }
            }

            // display itinerary edited message
            let editedDiv = document.getElementById("itinerary-edited-div");
            let itineraryEdited = "<?php echo $itineraryEdited?>";
            let edit = "<?php echo $edit?>";
            let editedLocation = "<?php echo $editedLocation?>";
            let editedDayNum = "<?php echo $editedDayNum?>";
            let newStop = "<?php echo $newStop?>";
            let newDuration = "<?php echo $newDuration?>";
            let newDay = "<?php echo $newDay?>";
            let isFreeNode = "<?php echo $isFreeNode?>";
            let editedCategory = "<?php echo $editedCategory?>";
            if (isFreeNode == "yes") {
                if (editedCategory == "attraction") {
                    editedLocation = "Free Session";
                } else {
                    editedLocation = "Meal Session";
                }
            }
            if (itineraryEdited) {
                editedDiv.style.display = "block";
                if (edit == "sequence") {
                    document.getElementById("edit-text").innerHTML = editedLocation + " has been moved to Stop " + newStop + ".";
                } else if (edit == "day") {
                    document.getElementById("edit-text").innerHTML = editedLocation + " has been moved to Day " + newDay + ".";
                } else if (edit == "duration") {
                    document.getElementById("edit-text").innerHTML = "Staying duration at " + editedLocation + " has been changed to " + newDuration + " hour(s).";
                } else if (edit == "remove") {
                    document.getElementById("edit-text").innerHTML = editedLocation + " has been removed from itinerary.";
                }
            }

            // set fading effect
            if (editedDiv.style.display === "block") {
                setTimeout(() => {
                    editedDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    editedDiv.style.display = "none";
                }, 3000);
            }

            // display error message when no radio button is selected 
            let editError = "<?php echo $editError?>";
            let errorDiv = document.getElementById("select-div");
            if (editError !== "") {
                errorDiv.style.display = "block";
            }

            // set fading effect
            if (errorDiv.style.display === "block") {
                setTimeout(() => {
                    errorDiv.classList.add("fadeout-effect");
                }, 2000); 
                setTimeout(() => {
                    errorDiv.style.display = "none";
                }, 3000);
            }

            // display confirmation popup
            let errorMessage = "<?php echo $errorMessage?>";
            if (errorMessage !== "") {
                document.getElementById("confirmation-popup").style.display = "block";
                document.getElementById("popup-text").innerHTML = errorMessage;
            } else {
                document.getElementById("confirmation-popup").style.display = "none";  
            }
        </script>
    </body>
</html>