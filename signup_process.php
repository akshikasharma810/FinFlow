<?php

error_reporting(E_ALL); // Report all PHP errors
ini_set('display_errors', 1); // Display errors directly in the browser

// Make sure no spaces/blank lines ABOVE this opening <?php tag in this file OR db_connect.php
include 'db_connect.php'; // Include your database connection file

// IMPORTANT: This header_sent check needs to be placed strategically
// The current placement might catch output from db_connect.php if it has issues.
// Let's keep it for now, but note its position.
if (headers_sent($filename, $linenum)) {
    echo "<pre>Headers already sent in $filename on line $linenum</pre>";
    exit; // Stop execution to see the message
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullName = $_POST['fullName'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // 1. Basic Validation
    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
        // Redirect to the new index.html file
        header("Location: index.html?error=emptyfields");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Redirect to the new index.html file
        header("Location: index.html?error=invalidemail");
        exit();
    }

    if ($password !== $confirmPassword) {
        // Redirect to the new index.html file
        header("Location: index.html?error=passwordnomatch");
        exit();
    }

    // 2. Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    // Ensure $conn is valid here. If db_connect.php failed and exited, this won't run.
    if (!$stmt) {
        // Handle error preparing statement, e.g., output $conn->error
        error_log("Failed to prepare statement for email check: " . $conn->error);
        // Redirect to the new index.html file
        header("Location: index.html?error=dberror_prepare_email");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Redirect to the new index.html file
        header("Location: index.html?error=emailtaken");
        exit();
    }
    $stmt->close();

    // 3. Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 4. Insert user into database
    // ************************************************************
    // *** CRITICAL FIX: Changed 'fullName' to 'full_name'      ***
    // *** to match your database table column name.              ***
    // ************************************************************
    $stmt = $conn->prepare("INSERT INTO users (fullName, email, password) VALUES (?, ?, ?)"); //
    if (!$stmt) {
        // Handle error preparing statement, e.g., output $conn->error
        error_log("Failed to prepare statement for insert: " . $conn->error);
        header("Location: signin.html?error=dberror_prepare_insert");
        exit();
    }
    $stmt->bind_param("sss", $fullName, $email, $hashedPassword);

    if ($stmt->execute()) {
        // Registration successful
        // Ensure no output happens before this point.
        // Re-check headers_sent immediately before this if you still have issues
        // if (headers_sent($filename, $linenum)) {
        //     echo "<pre>Headers already sent right before redirect in $filename on line $linenum</pre>";
        //     exit;
        // }
        header("Location: signin.html?success=registered"); //
        exit();
    } else {
        // Error in database insertion
        // You can log the specific error for debugging:
        error_log("Database insertion failed: " . $stmt->error);
        header("Location: signin.html?error=dberror_insert"); //
        exit();
    }

    $stmt->close(); // Close statement after use
    $conn->close(); // Close connection at the end of the script
} else {
    // If someone tries to access this script directly without POST request
    header("Location: signin.html"); //
    exit();
}
?>