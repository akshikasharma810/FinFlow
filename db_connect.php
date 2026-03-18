<?php
// These are your InfinityFree database credentials
$servername = "sql309.infinityfree.com"; // This will be provided by InfinityFree
$username = "if0_39622400";     // The user you created in Step 3
$password = "Harsh1152";      // The password you set for the user
$dbname = "if0_39622400_finflow_db";      // The database name you created in Step 3

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>