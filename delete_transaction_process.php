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

    if ($transaction_id) {
        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $transaction_id, $user_id);
            if ($stmt->execute()) {
                header("Location: transactions.php?success=deleted");
                exit();
            } else {
                error_log("DB Delete Error: " . $stmt->error);
                header("Location: transactions.php?error=dberror_delete");
                exit();
            }
            $stmt->close();
        } else {
            error_log("DB Prepare Error: " . $conn->error);
            header("Location: transactions.php?error=dberror_prepare");
            exit();
        }
    } else {
        header("Location: transactions.php?error=invalidid");
        exit();
    }
}

$conn->close();
header("Location: transactions.php");
exit();
?>