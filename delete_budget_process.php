<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.html?error=notloggedin');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $budget_id = $_POST['budget_id'] ?? null;

    if ($budget_id) {
        $stmt = $conn->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $budget_id, $user_id);
            if ($stmt->execute()) {
                header('Location: budgets.php?status=success_deleted');
                exit();
            } else {
                error_log('DB Delete Error: ' . $stmt->error);
                header('Location: budgets.php?status=error_delete');
                exit();
            }
            $stmt->close();
        } else {
            error_log('DB Prepare Error: ' . $conn->error);
            header('Location: budgets.php?status=error_prepare');
            exit();
        }
    } else {
        header('Location: budgets.php?status=error_invalidid');
        exit();
    }
}

$conn->close();
header('Location: budgets.php');
exit();
?><?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.html?error=notloggedin');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $budget_id = $_POST['budget_id'] ?? null;

    if ($budget_id) {
        $stmt = $conn->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $budget_id, $user_id);
            if ($stmt->execute()) {
                header('Location: budgets.php?status=success_deleted');
                exit();
            } else {
                error_log('DB Delete Error: ' . $stmt->error);
                header('Location: budgets.php?status=error_delete');
                exit();
            }
            $stmt->close();
        } else {
            error_log('DB Prepare Error: ' . $conn->error);
            header('Location: budgets.php?status=error_prepare');
            exit();
        }
    } else {
        header('Location: budgets.php?status=error_invalidid');
        exit();
    }
}

$conn->close();
header('Location: budgets.php');
exit();
?>