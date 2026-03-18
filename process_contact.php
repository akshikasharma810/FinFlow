<?php
session_start(); // Start session
include 'db_connect.php'; // Include database connection

// REMOVE these error reporting lines once it's working in production
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and trim the input data
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // --- Validation ---
    if (empty($fullName) || empty($email) || empty($subject) || empty($message)) {
        header("Location: contact.php?status=invalid");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: contact.php?status=invalid");
        exit();
    }

    // --- Store Message in Database ---
    $stmt = $conn->prepare("INSERT INTO contact_messages (full_name, email, subject, message) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Contact DB Prepare Error: " . $conn->error);
        header("Location: contact.php?status=error");
        exit();
    }

    $stmt->bind_param("ssss", $fullName, $email, $subject, $message);

    if ($stmt->execute()) {
        // Database insertion successful
        $stmt->close();
        $conn->close();
        header("Location: contact.php?status=success");
        exit();
    } else {
        // Database insertion failed
        error_log("Contact DB Insert Error: " . $stmt->error);
        $stmt->close();
        $conn->close();
        header("Location: contact.php?status=error");
        exit();
    }

} else {
    // Not a POST request, redirect back to the contact page
    header("Location: contact.php");
    exit();
}
?>