<?php 
// connect to server 
$handler = mysqli_connect("localhost", "root", "");

// check connection to server
if (!$handler) {
    die("Error connecting to server: ". mysqli_connect_error());
} else {
    echo "Successfully connected to server!";
}
echo "<br>";

// create database 
if (mysqli_query($handler, "CREATE DATABASE tripbuilderdb")) {
    echo "Successfully created database!";
} else {
    echo "Error creating database: " . mysqli_error($handler);
}
echo "<br>";

// connect to newly created database 
$handler = mysqli_connect("localhost", "root", "", "tripbuilderdb");

// check connection to database
if (!$handler) {
    die("Error connecting to database: ". mysqli_connect_error());
} else {
    echo "Successfully connected to database!";
}
echo "<br>";

// create table to store user details 
$user_query = "CREATE TABLE users (
    userID INT(3) AUTO_INCREMENT, 
    name VARCHAR(50),
    email VARCHAR(50),
    password VARCHAR(255),
    startTime TIME, 
    endTime TIME, 
    attractionStay INT(2), 
    eateryStay INT(2), 
    breakfastTime TIME, 
    lunchTime TIME, 
    dinnerTime TIME,
    PRIMARY KEY (userID)
    )";

// execute query to create users table 
if (mysqli_query($handler, $user_query)) {
    echo "Successfully created users table!";
} else {
    echo "Error creating users table: " . mysqli_error($handler);
}
echo "<br>";

// create table to store trip details 
$trip_query = "CREATE TABLE trips (
    tripID INT(3) AUTO_INCREMENT, 
    name VARCHAR(50),
    userEmail VARCHAR(50),
    destination VARCHAR(200),
    startDate DATE, 
    endDate DATE, 
    accomodation VARCHAR(200), 
    accomodationLat DECIMAL(16,13),
    accomodationLong DECIMAL(16,13),
    waypoints LONGTEXT,
    distanceMatrix LONGTEXT,
    itinerary LONGTEXT,
    imageFile VARCHAR(50),
    imageDirectory VARCHAR(100),
    PRIMARY KEY (tripID)
    )";

// execute query to create trips table 
if (mysqli_query($handler, $trip_query)) {
    echo "Successfully created trips table!";
} else {
    echo "Error creating trips table: " . mysqli_error($handler);
}
echo "<br>";

// create table to store attraction details 
$attraction_query = "CREATE TABLE attractions (
    attractionID BIGINT AUTO_INCREMENT, 
    placeID VARCHAR(50),
    location VARCHAR(200),
    name VARCHAR(100),
    rating DECIMAL(2,1),
    userRatingsTotal BIGINT,
    address VARCHAR(200), 
    latitude DECIMAL(16,13),
    longitude DECIMAL(16,13), 
    types VARCHAR(200), 
    description VARCHAR(500),
    website VARCHAR(500), 
    contactNum VARCHAR(30), 
    operatingHours VARCHAR(500), 
    nearbyLocations VARCHAR(1000),
    PRIMARY KEY (attractionID)
    )";

// execute query to create attractions table 
if (mysqli_query($handler, $attraction_query)) {
    echo "Successfully created attractions table!";
} else {
    echo "Error creating attractions table: " . mysqli_error($handler);
}
echo "<br>";

// create table to store eatery details 
$eatery_query = "CREATE TABLE eateries (
    eateryID BIGINT AUTO_INCREMENT, 
    placeID VARCHAR(50),
    location VARCHAR(200),
    name VARCHAR(100),
    rating DECIMAL(2,1),
    userRatingsTotal BIGINT,
    address VARCHAR(200), 
    latitude DECIMAL(16,13),
    longitude DECIMAL(16,13),  
    types VARCHAR(200), 
    priceLevel VARCHAR(30), 
    description VARCHAR(500),
    website VARCHAR(500), 
    contactNum VARCHAR(30), 
    operatingHours VARCHAR(500), 
    nearbyLocations VARCHAR(1000),
    PRIMARY KEY (eateryID)
    )";

// execute query to create eateries table 
if (mysqli_query($handler, $eatery_query)) {
    echo "Successfully created eateries table!";
} else {
    echo "Error creating eateries table: " . mysqli_error($handler);
}
echo "<br>";

// create table to store photos 
$photo_query = "CREATE TABLE photos (
    photoID BIGINT AUTO_INCREMENT, 
    attractionID BIGINT,
    eateryID BIGINT,
    url VARCHAR(1000),
    PRIMARY KEY (photoID)
    )";

// execute query to create photos table 
if (mysqli_query($handler, $photo_query)) {
    echo "Successfully created photos table!";
} else {
    echo "Error creating photos table: " . mysqli_error($handler);
}
echo "<br>";

// create table to store reviews 
$review_query = "CREATE TABLE reviews (
    reviewID BIGINT AUTO_INCREMENT, 
    attractionID BIGINT,
    eateryID BIGINT,
    authorName VARCHAR(50), 
    authorPic VARCHAR(1000),
    rating DECIMAL(2,1),
    text LONGTEXT,
    PRIMARY KEY (reviewID)
    )";

// execute query to create reviews table 
if (mysqli_query($handler, $review_query)) {
    echo "Successfully created reviews table!";
} else {
    echo "Error creating reviews table: " . mysqli_error($handler);
}
echo "<br>";
?>