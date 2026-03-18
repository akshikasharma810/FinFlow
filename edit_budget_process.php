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
    $category = $_POST['category'] ?? '';
    $budget_limit = $_POST['budget_limit'] ?? '';
    $month_year = $_POST['month_year'] ?? '';

    if (empty($budget_id) || empty($category) || empty($budget_limit) || empty($month_year)) {
        header('Location: budgets.php?status=error_emptyfields');
        exit();
    }
    if (!is_numeric($budget_limit) || $budget_limit <= 0) {
        header('Location: budgets.php?status=error_invalidlimit');
        exit();
    }

    $checkStmt = $conn->prepare('SELECT id FROM budgets WHERE user_id = ? AND category = ? AND month_year = ? AND id != ?');
    if ($checkStmt) {
        $checkStmt->bind_param('issi', $user_id, $category, $month_year, $budget_id);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            $checkStmt->close();
            header('Location: budgets.php?status=error_budget_exists');
            exit();
        }
        $checkStmt->close();
    }

    $stmt = $conn->prepare('UPDATE budgets SET category = ?, budget_limit = ?, month_year = ? WHERE id = ? AND user_id = ?');
    if ($stmt) {
        $stmt->bind_param('sdsii', $category, $budget_limit, $month_year, $budget_id, $user_id);
        if ($stmt->execute()) {
            header('Location: budgets.php?status=success_updated');
            exit();
        } else {
            error_log('DB Update Error: ' . $stmt->error);
            header('Location: budgets.php?status=error_update');
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