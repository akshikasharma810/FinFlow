<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.html?error=notloggedin');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $category = $_POST['category'] ?? '';
    $custom_category = $_POST['custom_category'] ?? '';
    $budget_limit = $_POST['budget_limit'] ?? '';
    $month_year = $_POST['month_year'] ?? '';

    $final_category = ($category === 'custom') ? $custom_category : $category;

    if (empty($final_category) || empty($budget_limit) || empty($month_year)) {
        header('Location: budgets.php?status=error_emptyfields');
        exit();
    }
    if (!is_numeric($budget_limit) || $budget_limit <= 0) {
        header('Location: budgets.php?status=error_invalidlimit');
        exit();
    }

    $checkStmt = $conn->prepare('SELECT id FROM budgets WHERE user_id = ? AND category = ? AND month_year = ?');
    if (!$checkStmt) {
        error_log('Budget Check DB Prepare Error: ' . $conn->error);
        header('Location: budgets.php?status=error_prepare');
        exit();
    }
    $checkStmt->bind_param('iss', $user_id, $final_category, $month_year);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        header('Location: budgets.php?status=error_budget_exists');
        exit();
    }
    $checkStmt->close();

    $stmt = $conn->prepare('INSERT INTO budgets (user_id, category, budget_limit, month_year) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('isds', $user_id, $final_category, $budget_limit, $month_year);
        if ($stmt->execute()) {
            header('Location: budgets.php?status=success_added');
            exit();
        } else {
            error_log('DB Insert Error: ' . $stmt->error);
            header('Location: budgets.php?status=error_insert');
            exit();
        }
        $stmt->close();
    } else {
        error_log('DB Prepare Error: ' . $conn->error);
        header('Location: budgets.php?status=error_prepare');
        exit();
    }
}

$conn->close();
header('Location: budgets.php');
exit();
?>
