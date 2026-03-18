f<?php
session_start();
include 'db_connect.php'; // Ensure db_connect.php is clean (no whitespace/BOM before <?php)

// REMOVE these error reporting lines once it's working in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debugging: Check for premature output immediately at the start of the script execution
if (headers_sent($filename, $linenum)) {
    echo "<pre>ATTENTION: Headers were already sent at script start! Cannot redirect.<br>";
    echo "Output started in: $filename on line $linenum</pre>";
    exit; // Stop execution to display this critical message
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Basic Validation
    if (empty($email) || empty($password)) {
        // Check if headers are already sent before redirecting
        if (headers_sent($filename, $linenum)) {
             echo "<pre>ATTENTION: Headers were already sent! Cannot redirect to signin (empty fields).<br>";
             echo "Output started in: $filename on line $linenum</pre>";
             exit;
        }
        header("Location: signin.html?error=emptyfields");
        exit();
    }

    // Fetch user from database
    $stmt = $conn->prepare("SELECT id, fullName, password FROM users WHERE email = ?"); // Using 'fullName'
    if (!$stmt) {
        error_log("DB Prepare Error (email check): " . $conn->error); // Log to XAMPP error logs
        // Check if headers are already sent before redirecting
        if (headers_sent($filename, $linenum)) {
             echo "<pre>ATTENTION: Headers were already sent! Cannot redirect to signin (DB prepare error).<br>";
             echo "Output started in: $filename on line $linenum</pre>";
             exit;
        }
        header("Location: signin.html?error=dberror_prepare_signin_email");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['fullName']; // Using 'fullName'

            // Check if headers have been sent BEFORE attempting redirect to dashboard
            if (headers_sent($filename, $linenum)) {
                 echo "<pre>ATTENTION: Headers were already sent! Cannot redirect to dashboard (success).<br>";
                 echo "Output started in: $filename on line $linenum</pre>";
                 exit;
            }
            header("Location: dashboard.php"); // Redirect to dashboard
            exit();
        } else {
            // Incorrect password
            // Check if headers have been sent BEFORE attempting redirect
            if (headers_sent($filename, $linenum)) {
                 echo "<pre>ATTENTION: Headers were already sent! Cannot redirect to signin (wrong password).<br>";
                 echo "Output started in: $filename on line $linenum</pre>";
                 exit;
            }
            header("Location: signin.html?error=wrongpassword"); // Redirect to signin with error
            exit();
        }
    } else {
        // User not found (email doesn't exist)
        // Check if headers have been sent BEFORE attempting redirect
        if (headers_sent($filename, $linenum)) {
             echo "<pre>ATTENTION: Headers were already sent! Cannot redirect to signin (no user).<br>";
             echo "Output started in: $filename on line $linenum</pre>";
             exit;
        }
        header("Location: signin.html?error=nouser");
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    // If not a POST request, redirect to signin
    // Check if headers are already sent before redirecting
    if (headers_sent($filename, $linenum)) {
         echo "<pre>ATTENTION: Headers were already sent! Cannot redirect to signin (not POST).<br>";
         echo "Output started in: $filename on line $linenum</pre>";
         exit;
    }
    header("Location: signin.html");
    exit();
}
?>