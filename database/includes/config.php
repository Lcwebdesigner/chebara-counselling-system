<?php
/**
 * Chebara TVC Online Counselling Booking System
 * Database Configuration
 *
 * IMPORTANT:
 * Replace the placeholder database details with
 * the credentials provided by InfinityFree.
 */

// Database server
$db_host = "YOUR_DATABASE_HOST";

// Database name
$db_name = "YOUR_DATABASE_NAME";

// Database username
$db_user = "YOUR_DATABASE_USERNAME";

// Database password
$db_pass = "YOUR_DATABASE_PASSWORD";

// Create database connection
$conn = new mysqli(
    $db_host,
    $db_user,
    $db_pass,
    $db_name
);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Set UTF-8 character encoding
$conn->set_charset("utf8mb4");
?>
