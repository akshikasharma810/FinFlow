<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - FinFlow</title>
    <link rel="icon" type="image/png" href="logo_transparent_background.png">
    <!-- Latest compiled and minified CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Icons for transaction categories -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* A few custom styles to better match the screenshot */
        body {
            background-color: #f8f9fa;
            /* A light gray background for the page */
        }

        .card-value {
            font-size: 1.8rem;
            font-weight: 500;
        }

        .material-symbols-outlined {
            /* Vertically align icons with text */
            vertical-align: middle;
            margin-right: 0.25rem;
        }

        .filter-card {
            border-radius: 0.75rem;
        }

        .material-symbols-outlined {
            vertical-align: middle;
            /* Helps align icons with text */
        }

        /* This rule makes the logo taller */
        .navbar-brand img {
            height: 45px;
            /* You can adjust this value */
        }
    </style>
</head>

<body>

<?php
// header.php
// This file contains the common navigation bar for FinFlow
// It assumes session_start() has been called in the main page already.

$loggedInUserName = "Guest";
if (isset($_SESSION['full_name'])) {
    $loggedInUserName = htmlspecialchars($_SESSION['full_name']);
}

// Get the current page name to set the active nav link
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-sm navbar-light bg-white border-bottom py-1">
    <div class="container">
        <!-- Logo -->
        <a href="dashboard.php" class="navbar-brand d-flex align-items-center">
            <!-- Make sure the src path to your logo is correct -->
            <img src="logo.png" alt="FinFlow Logo">
            <span class="fw-bold text-primary ms-2">FinFlow</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo ($currentPage == 'dashboard.php' ? 'active' : ''); ?>">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="transactions.php" class="nav-link <?php echo ($currentPage == 'transactions.php' ? 'active' : ''); ?>">Transactions</a>
                </li>
                <li class="nav-item">
                    <a href="budgets.php" class="nav-link <?php echo ($currentPage == 'budgets.php' ? 'active' : ''); ?>">Budgets</a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link <?php echo ($currentPage == 'reports.php' ? 'active' : ''); ?>">Reports</a>
                </li>
                <li class="nav-item">
                    <a href="contact.php" class="nav-link <?php echo ($currentPage == 'contact.php' ? 'active' : ''); ?>">Contact Us</a>
                </li>
            </ul>

            <div class="ms-auto d-flex align-items-center">
                <span class="navbar-text me-3">
                    Hello, <?php echo $loggedInUserName; ?>
                </span>
                <a href="signout_process.php" class="nav-link text-secondary">Sign Out</a>
            </div>
        </div>
    </div>
</nav>

<style>
    /* New CSS for active and hover effects on nav links */
    .nav-link {
        transition: all 0.2s ease-in-out;
    }
    .nav-link.active {
        font-weight: bold;
        border-bottom: 2px solid #0d6efd;
        padding-bottom: 0.25rem;
    }
    .nav-link:hover {
        color: #0d6efd !important;
        transform: translateY(-2px);
    }
    .navbar-text {
        color: #0d6efd !important;
    }
    .navbar-brand img {
        height: 45px;
    }
</style>