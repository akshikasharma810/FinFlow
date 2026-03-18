<?php
session_start();
include 'db_connect.php';

// --- Basic Page Protection ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    die("You must be logged in to download reports.");
}
$user_id = $_SESSION['user_id'];

// --- Get Filter Parameters (same logic as in reports.php) ---
$quickSelect = $_GET['quick_select'] ?? '';
$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';

$reportStartDate = null;
$reportEndDate = null;

if (!empty($quickSelect)) {
    switch ($quickSelect) {
        case 'this_month':
            $reportStartDate = date('Y-m-01');
            $reportEndDate = date('Y-m-t');
            break;
        case 'last_month':
            $reportStartDate = date('Y-m-01', strtotime('last month'));
            $reportEndDate = date('Y-m-t', strtotime('last month'));
            break;
        case 'this_year':
            $reportStartDate = date('Y-01-01');
            $reportEndDate = date('Y-12-31');
            break;
        case 'last_year':
            $reportStartDate = date('Y-01-01', strtotime('last year'));
            $reportEndDate = date('Y-12-31', strtotime('last year'));
            break;
        default:
            $reportStartDate = date('Y-m-01');
            $reportEndDate = date('Y-m-t');
            break;
    }
} elseif (!empty($customStartDate) && !empty($customEndDate)) {
    $reportStartDate = $customStartDate;
    $reportEndDate = $customEndDate;
} else {
    $reportStartDate = date('Y-m-01');
    $reportEndDate = date('Y-m-t');
}

if (!$reportStartDate || !$reportEndDate || $reportStartDate > $reportEndDate) {
    $reportStartDate = date('Y-m-01');
    $reportEndDate = date('Y-m-t');
}

// --- Fetch ALL Data from Database first to calculate totals and prepare detailed list ---
$allTransactions = [];
$totalIncome = 0;
$totalExpenses = 0;

$stmt = $conn->prepare("SELECT transaction_date, description, category, type, amount FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC");

if ($stmt) {
    $stmt->bind_param("iss", $user_id, $reportStartDate, $reportEndDate);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $allTransactions[] = $row;
        if ($row['type'] == 'income') {
            $totalIncome += $row['amount'];
        } else if ($row['type'] == 'expense') {
            $totalExpenses += $row['amount'];
        }
    }
    $stmt->close();
}


// --- Set CSV Headers to force a download ---
$filename = "finflow_report_" . date('Y-m-d', strtotime($reportStartDate)) . "_to_" . date('Y-m-d', strtotime($reportEndDate)) . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// --- Open a file stream to write to the browser's output ---
$output = fopen('php://output', 'w');

// --- Set the CSV Header Row for the detailed list ---
fputcsv($output, ['Detailed Transactions']);
fputcsv($output, ['Date', 'Description', 'Category', 'Type', 'Amount']);

// --- Write fetched data to CSV ---
foreach ($allTransactions as $row) {
    fputcsv($output, $row);
}

// --- ADDED: Summary Section at the bottom of the CSV ---
fputcsv($output, ['']); // Blank line for spacing
fputcsv($output, ['Financial Report Summary']);
fputcsv($output, ['Report Period:', date('M d, Y', strtotime($reportStartDate)) . ' to ' . date('M d, Y', strtotime($reportEndDate))]);
fputcsv($output, ['']); // Blank line for spacing
fputcsv($output, ['Total Income:', '₹' . number_format($totalIncome, 2)]);
fputcsv($output, ['Total Expenses:', '₹' . number_format($totalExpenses, 2)]);
fputcsv($output, ['Net Balance:', '₹' . number_format($totalIncome - $totalExpenses, 2)]);

fclose($output); // Close the file stream
$conn->close(); // Close the database connection
exit(); // End script execution
?>
