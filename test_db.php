<?php
include 'db_connect.php';
if ($conn) {
    echo "Database connection successful!";
    $conn->close();
} else {
    echo "Database connection failed. Check db_connect.php.";
}
?>