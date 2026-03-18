<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.html?error=notloggedin");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $transaction_id = $_POST['transaction_id'] ?? null;
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $type = $_POST['type'] ?? '';
    $transaction_date = $_POST['transaction_date'] ?? '';

    if (empty($transaction_id) || empty($description) || empty($amount) || empty($type) || empty($transaction_date)) {
        header("Location: transactions.php?error=emptyfields");
        exit();
    }

    if (!is_numeric($amount) || $amount <= 0) {
        header("Location: transactions.php?error=invalidamount");
        exit();
    }

    $stmt = $conn->prepare("UPDATE transactions SET description = ?, amount = ?, type = ?, transaction_date = ? WHERE id = ? AND user_id = ?");

    if (!$stmt) {
        error_log("DB Update Error: " . $conn->error);
        header("Location: transactions.php?error=dberror_prepare");
        exit();
    }

    // 's' for description (string)
    // 'd' for amount (double/decimal)
    // 's' for type (string)
    // 's' for transaction_date (string)
    // 'i' for id (integer)
    // 'i' for user_id (integer)
    $stmt->bind_param("sdssii", $description, $amount, $type, $transaction_date, $transaction_id, $user_id);

    if ($stmt->execute()) {
        header("Location: transactions.php?success=updated");
        exit();
    } else {
        error_log("DB Update Error: " . $stmt->error);
        header("Location: transactions.php?error=dberror_update");
        exit();
    }

    $stmt->close();
    $conn->close();
}

header("Location: transactions.php");
exit();
?><?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.html?error=notloggedin");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $transaction_id = $_POST['transaction_id'] ?? null;
    $description = $_POST['description'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $type = $_POST['type'] ?? '';
    $transaction_date = $_POST['transaction_date'] ?? '';

    if (empty($transaction_id) || empty($description) || empty($amount) || empty($type) || empty($transaction_date)) {
        header("Location: transactions.php?error=emptyfields");
        exit();
    }

    if (!is_numeric($amount) || $amount <= 0) {
        header("Location: transactions.php?error=invalidamount");
        exit();
    }

    $stmt = $conn->prepare("UPDATE transactions SET description = ?, amount = ?, type = ?, transaction_date = ? WHERE id = ? AND user_id = ?");

    if (!$stmt) {
        error_log("DB Update Error: " . $conn->error);
        header("Location: transactions.php?error=dberror_prepare");
        exit();
    }

    // 's' for description (string)
    // 'd' for amount (double/decimal)
    // 's' for type (string)
    // 's' for transaction_date (string)
    // 'i' for id (integer)
    // 'i' for user_id (integer)
    $stmt->bind_param("sdssii", $description, $amount, $type, $transaction_date, $transaction_id, $user_id);

    if ($stmt->execute()) {
        header("Location: transactions.php?success=updated");
        exit();
    } else {
        error_log("DB Update Error: " . $stmt->error);
        header("Location: transactions.php?error=dberror_update");
        exit();
    }

    $stmt->close();
    $conn->close();
}

header("Location: transactions.php");
exit();
?>