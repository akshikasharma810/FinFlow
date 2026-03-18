<?php
// add_transaction_process.php
// Display all PHP errors for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug point 1: Script execution start
echo "<pre>DEBUG: Script add_transaction_process.php started.<br>\n";

// Start session
session_start();
echo "DEBUG: Session started.<br>\n"; // Debug point 2

// Include database connection. CRITICAL: Ensure db_connect.php is clean (no spaces/blank lines before <?php)
include 'db_connect.php';
if ($conn->connect_error) {
    die("DEBUG: Database connection failed: " . $conn->connect_error . "<br>\n"); // Debug point 3a - Script stops if DB connection fails
}
echo "DEBUG: Database connected.<br>\n"; // Debug point 3b

// Check for "Headers already sent" immediately after includes.
// This is key if redirects are not working.
if (headers_sent($filename, $linenum)) {
    echo "<pre>DEBUG: Headers were already sent BEFORE main logic in $filename on line $linenum. This will prevent redirects.<br></pre>"; // Debug point 4
    // We will *not* exit here, to see if the rest of the script logic executes and prints further debug messages.
}

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "DEBUG: POST request detected.<br>\n"; // Debug point 7

    // Debug point 8: See all received POST data - THIS IS CRUCIAL NOW
    echo "<pre>DEBUG: Raw POST Data received:\n";
    var_dump($_POST);
    echo "</pre>";

    // Retrieve and sanitize POST data
    $user_id = $_SESSION['user_id'] ?? null; // Ensure user_id is set
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $type = $_POST['type'] ?? '';
    $category = $_POST['category'] ?? '';
    $transaction_date = $_POST['transaction_date'] ?? '';
    $custom_category = $_POST['custom_category'] ?? '';

    // Validate user_id from session
    if ($user_id === null) {
        echo "DEBUG: User ID not found in session. Redirecting to login.<br>\n"; // Debug point 5
        if (headers_sent($filename, $linenum)) {
            echo "<pre>DEBUG: Headers sent before NOT LOGGED IN redirect in $filename on line $linenum.<br></pre>";
        }
        header("Location: signin.html?error=notloggedin");
        exit();
    }
    echo "DEBUG: User ID found in session: " . $user_id . "<br>\n"; // Debug point 6

    // Determine final category (handling 'custom')
    $final_category = $category;
    if ($category === 'custom') {
        if (!empty($custom_category)) {
            $final_category = $custom_category;
        } else {
            echo "DEBUG: Custom category selected but custom name is empty. Redirecting.<br>\n"; // Debug point 9
            if (headers_sent($filename, $linenum)) {
                echo "<pre>DEBUG: Headers sent before EMPTY CUSTOM CATEGORY redirect in $filename on line $linenum.<br></pre>";
            }
            header("Location: transactions.php?error=emptycustomcategory");
            exit();
        }
    }
    echo "DEBUG: Final Category determined: " . htmlspecialchars($final_category) . "<br>\n"; // Debug point 10

    // Basic validation of all required fields
    if (empty($description) || empty($amount) || empty($type) || empty($transaction_date) || empty($final_category)) {
        echo "DEBUG: One or more required fields are empty. Redirecting.<br>\n"; // Debug point 11
        if (headers_sent($filename, $linenum)) {
            echo "<pre>DEBUG: Headers sent before EMPTY FIELDS redirect in $filename on line $linenum.<br></pre>";
        }
        header("Location: transactions.php?error=emptyfields");
        exit();
    }

    // Validate amount format
    if (!is_numeric($amount) || $amount <= 0) {
        echo "DEBUG: Invalid amount detected. Redirecting.<br>\n"; // Debug point 12
        if (headers_sent($filename, $linenum)) {
            echo "<pre>DEBUG: Headers sent before INVALID AMOUNT redirect in $filename on line $linenum.<br></pre>";
        }
        header("Location: transactions.php?error=invalidamount");
        exit();
    }
    echo "DEBUG: All validations passed. Proceeding to database insertion.<br>\n"; // Debug point 13

    // Prepare and execute the INSERT statement
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, description, amount, type, category, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo "DEBUG: Failed to prepare SQL statement for insertion: " . $conn->error . "<br>\n"; // Debug point 14
        error_log("Add Transaction DB Prepare Error: " . $conn->error);
        if (headers_sent($filename, $linenum)) {
            echo "<pre>DEBUG: Headers sent before DB PREPARE ERROR redirect in $filename on line $linenum.<br></pre>";
        }
        header("Location: transactions.php?error=dberror_prepare");
        exit();
    }
    
    echo "DEBUG: SQL statement prepared. Binding parameters.<br>\n"; // Debug point 15
    $stmt->bind_param("isdsss", $user_id, $description, $amount, $type, $final_category, $transaction_date);

    echo "DEBUG: Executing SQL statement.<br>\n"; // Debug point 16
    if ($stmt->execute()) {
        echo "DEBUG: Transaction insertion successful! Attempting redirect to success page.<br>\n"; // Debug point 17
        if (headers_sent($filename, $linenum)) {
            echo "<pre>DEBUG: Headers sent before SUCCESS redirect in $filename on line $linenum.<br>";
            echo "Output started: $filename on line $linenum</pre>";
            // If headers are sent, the redirect won't work. We'll show a message instead.
            echo "<h2>Transaction successfully added, but automatic redirection failed. Please <a href='transactions.php'>click here to return to Transactions</a>.</h2>";
            exit; // Exit to prevent further output
        }
        header("Location: transactions.php?success=added");
        exit(); // Crucial to stop script execution after redirect
    } else {
        echo "DEBUG: Transaction insertion FAILED: " . $stmt->error . "<br>\n"; // Debug point 18
        error_log("Add Transaction DB Insert Error: " . $stmt->error);
        if (headers_sent($filename, $linenum)) {
            echo "<pre>DEBUG: Headers sent before DB INSERT ERROR redirect in $filename on line $linenum.<br></pre>";
        }
        header("Location: transactions.php?error=dberror_insert");
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    echo "DEBUG: Not a POST request. Redirecting.<br>\n"; // Debug point 19
    if (headers_sent($filename, $linenum)) {
        echo "<pre>DEBUG: Headers sent before NOT_POST redirect in $filename on line $linenum.<br></pre>";
    }
    header("Location: transactions.php");
    exit();
}
echo "</pre>"; // Close the pre tag if script somehow finishes
?>