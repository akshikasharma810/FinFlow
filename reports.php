<?php
session_start();

include 'db_connect.php';

// Basic protection: Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.html?error=notloggedin");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Get Filter Parameters ---
$quickSelect = $_GET['quick_select'] ?? '';
$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';

// --- Determine Effective Report Date Range ---
$reportStartDate = null;
$reportEndDate = null;
$displayDateRangeText = '';

if (!empty($quickSelect)) {
    switch ($quickSelect) {
        case 'this_month':
            $reportStartDate = date('Y-m-01');
            $reportEndDate = date('Y-m-t');
            $displayDateRangeText = 'This Month (' . date('F Y') . ')';
            break;
        case 'last_month':
            $reportStartDate = date('Y-m-01', strtotime('last month'));
            $reportEndDate = date('Y-m-t', strtotime('last month'));
            $displayDateRangeText = 'Last Month (' . date('F Y', strtotime('last month')) . ')';
            break;
        case 'this_year':
            $reportStartDate = date('Y-01-01');
            $reportEndDate = date('Y-12-31');
            $displayDateRangeText = 'This Year (' . date('Y') . ')';
            break;
        case 'last_year':
            $reportStartDate = date('Y-01-01', strtotime('last year'));
            $reportEndDate = date('Y-12-31', strtotime('last year'));
            $displayDateRangeText = 'Last Year (' . date('Y', strtotime('last year')) . ')';
            break;
    }
} elseif (!empty($customStartDate) && !empty($customEndDate)) {
    $reportStartDate = $customStartDate;
    $reportEndDate = $customEndDate;
    $displayDateRangeText = date('M d, Y', strtotime($reportStartDate)) . ' to ' . date('M d, Y', strtotime($reportEndDate));
} else {
    $reportStartDate = date('Y-m-01');
    $reportEndDate = date('Y-m-t');
    $displayDateRangeText = 'This Month (' . date('F Y') . ')';
    $quickSelect = 'this_month';
}

if (!$reportStartDate || !$reportEndDate) {
    $reportStartDate = date('Y-m-01');
    $reportEndDate = date('Y-m-t');
    $displayDateRangeText = 'Current Month (' . date('F Y') . ')';
}

// --- Data Fetching for Income vs. Expense Trend Chart ---
$incomeValues = [];
$expenseValues = [];
$chartLabels = [];
$start    = new DateTime($reportStartDate);
$end      = new DateTime($reportEndDate);
$interval = DateInterval::createFromDateString('1 month');
$period   = new DatePeriod($start, $interval, $end);
$monthIndex = 0;
foreach ($period as $dt) {
    $chartLabels_ym[$dt->format("Y-m")] = $dt->format("M Y");
    $monthIndex++;
}
if (!isset($chartLabels_ym[$end->format("Y-m")])) {
    $chartLabels_ym[$end->format("Y-m")] = $end->format("M Y");
}
ksort($chartLabels_ym);
$fetchedData = [];
$stmtChart = $conn->prepare("SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month_year_str, type, SUM(amount) as total_amount FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ? GROUP BY month_year_str, type ORDER BY month_year_str ASC");
if ($stmtChart) {
    $stmtChart->bind_param("iss", $user_id, $reportStartDate, $reportEndDate);
    $stmtChart->execute();
    $resultChart = $stmtChart->get_result();
    while ($row = $resultChart->fetch_assoc()) {
        $fetchedData[$row['month_year_str']][$row['type']] = $row['total_amount'];
    }
    $stmtChart->close();
    $chartLabels = array_values($chartLabels_ym);
    foreach ($chartLabels_ym as $ym => $label) {
        $incomeValues[] = $fetchedData[$ym]['income'] ?? 0;
        $expenseValues[] = $fetchedData[$ym]['expense'] ?? 0;
    }
} else {
    error_log("Reports chart query prepare failed: " . $conn->error);
}

// --- Data Fetching for Expense Breakdown Summary Table and Pie Chart ---
$expenseSummary = [];
$totalExpensesForSummary = 0;
$stmtExpenseSummary = $conn->prepare("SELECT category, SUM(amount) AS total_spent FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ? GROUP BY category ORDER BY total_spent DESC");
if ($stmtExpenseSummary) {
    $stmtExpenseSummary->bind_param("iss", $user_id, $reportStartDate, $reportEndDate);
    $stmtExpenseSummary->execute();
    $resultExpenseSummary = $stmtExpenseSummary->get_result();
    
    while ($row = $resultExpenseSummary->fetch_assoc()) {
        $expenseSummary[] = $row;
        $totalExpensesForSummary += $row['total_spent'];
    }
    $stmtExpenseSummary->close();
} else {
    error_log("Expense Summary query prepare failed: " . $conn->error);
}
$conn->close();

$pieChartLabels = [];
$pieChartData = [];
foreach ($expenseSummary as $item) {
    $pieChartLabels[] = htmlspecialchars($item['category']);
    $pieChartData[] = $item['total_spent'];
}
$pieChartColors = ['#0d6efd','#dc3545','#ffc107','#198754','#6f42c1','#fd7e14','#20c997','#6c757d','#e83e8c','#0dcaf0','#d63384','#6610f2'];
$actualPieChartColors = [];
for ($i = 0; $i < count($pieChartLabels); $i++) {
    $actualPieChartColors[] = $pieChartColors[$i % count($pieChartColors)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FinFlow</title>
    <link rel="icon" type="image/png" href="logo_transparent_background.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Inter', sans-serif;
        }
        .card-value { font-size: 1.8rem; font-weight: 500; }
        .material-symbols-outlined { vertical-align: middle; margin-right: 0.25rem; }
        .filter-card { border-radius: 0.75rem; }
        .navbar-brand img { height: 45px; }
        .chart-container { position: relative; height: 400px; width: 100%; }
        .summary-table { 
            font-size: 0.9rem; 
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }
        .summary-table th, .summary-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .summary-table tr:last-child th, .summary-table tr:last-child td {
            border-bottom: none;
        }
        .card {
            border: 1px solid #e0e0e0;
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05);
        }
        /* New styles for the summary table header to match the screenshot */
        .summary-table thead tr {
            text-transform: uppercase;
            font-weight: 700;
            color: #495057;
        }
        .summary-table th {
            border-bottom: 2px solid #212529 !important;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        /* Align text for category in table body */
        .summary-table tbody tr td:first-child {
            font-weight: 500;
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="container my-5">
        <div class="mb-4">
            <h2 class="mb-1">Financial Reports</h2>
            <p class="text-muted mb-0">Showing report from <?php echo $displayDateRangeText; ?></p>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form action="reports.php" method="GET">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="quickSelect" class="form-label">Quick Select</label>
                            <select class="form-select" id="quickSelect" name="quick_select">
                                <option value="" <?php echo (empty($quickSelect) && empty($customStartDate) ? 'selected' : ''); ?>>Custom Range</option>
                                <option value="this_month" <?php echo ($quickSelect == 'this_month' ? 'selected' : ''); ?>>This Month</option>
                                <option value="last_month" <?php echo ($quickSelect == 'last_month' ? 'selected' : ''); ?>>Last Month</option>
                                <option value="this_year" <?php echo ($quickSelect == 'this_year' ? 'selected' : ''); ?>>This Year</option>
                                <option value="last_year" <?php echo ($quickSelect == 'last_year' ? 'selected' : ''); ?>>Last Year</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo htmlspecialchars($customStartDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo htmlspecialchars($customEndDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row g-3 mb-4">
                <a href="download_csv.php?<?php echo http_build_query($_GET); ?>" class="btn btn-secondary w-100">
                    <span class="material-symbols-outlined">download</span> Download CSV
                </a>
        </div>

        <div class="row g-4 mb-4">
            <!-- Income vs. Expense Bar Chart -->
            <div class="col-lg-12">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title">Income vs. Expense Trend</h5>
                        <?php if (empty($incomeValues) && empty($expenseValues)): ?>
                            <p class="text-muted text-center py-5">No financial data available for the selected period.</p>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="incomeExpenseChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-4">
            <!-- Expense Breakdown Pie Chart -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Expense Breakdown</h5>
                        <?php if (empty($expenseSummary)): ?>
                            <p class="text-muted text-center py-5">No expense data to show.</p>
                        <?php else: ?>
                            <div class="chart-container">
                                <canvas id="expensePieChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Category Summary Table -->
            <div class="col-lg-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Category Summary</h5>
                        <?php if (empty($expenseSummary)): ?>
                            <p class="text-muted text-center py-5">No expense data to show.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm summary-table">
                                    <thead>
                                        <tr>
                                            <th class="text-start">CATEGORY</th>
                                            <th class="text-end">AMOUNT SPENT</th>
                                            <th class="text-end">% OF TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenseSummary as $item): ?>
                                            <tr>
                                                <td class="text-start"><?php echo ucwords(htmlspecialchars($item['category'])); ?></td>
                                                <td class="text-end">₹<?php echo number_format($item['total_spent'], 2); ?></td>
                                                <td class="text-end"><?php echo ($totalExpensesForSummary > 0) ? number_format(($item['total_spent'] / $totalExpensesForSummary) * 100, 1) : 0; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="fw-bold">
                                            <td class="text-start">Total</td>
                                            <td class="text-end">₹<?php echo number_format($totalExpensesForSummary, 2); ?></td>
                                            <td class="text-end">100%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="py-3"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chartLabels = <?php echo json_encode($chartLabels); ?>;
            const incomeValues = <?php echo json_encode($incomeValues); ?>;
            const expenseValues = <?php echo json_encode($expenseValues); ?>;
            const expenseSummary = <?php echo json_encode($expenseSummary); ?>;
            const totalExpensesForSummary = <?php echo json_encode($totalExpensesForSummary); ?>;

            if (incomeValues.some(val => val > 0) || expenseValues.some(val => val > 0)) {
                const ctxBar = document.getElementById('incomeExpenseChart').getContext('2d');
                new Chart(ctxBar, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Income',
                            data: incomeValues,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1
                        }, {
                            label: 'Expenses',
                            data: expenseValues,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Amount (₹)' }
                            },
                            x: {
                                title: { display: true, text: 'Month / Period' }
                            }
                        },
                        plugins: {
                            legend: { display: true, position: 'top' },
                            title: { display: true, text: 'Income vs. Expense Over Time' }
                        }
                    }
                });
            }

            if (totalExpensesForSummary > 0) {
                const pieChartLabels = expenseSummary.map(item => item.category);
                const pieChartData = expenseSummary.map(item => item.total_spent);
                const pieChartColors = ['#0d6efd','#dc3545','#ffc107','#198754','#6f42c1','#fd7e14','#20c997','#6c757d','#e83e8c','#0dcaf0','#d63384','#6610f2'];
                const actualPieChartColors = pieChartLabels.map((_, i) => pieChartColors[i % pieChartColors.length]);
                const ctxPie = document.getElementById('expensePieChart').getContext('2d');
                
                new Chart(ctxPie, {
                    type: 'pie',
                    data: {
                        labels: pieChartLabels,
                        datasets: [{
                            label: 'Expenses by Category (₹)',
                            data: pieChartData,
                            backgroundColor: actualPieChartColors,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, position: 'bottom' },
                            title: { display: true, text: 'Expense Breakdown by Category' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) { label += ': '; }
                                        const value = context.parsed;
                                        const percentage = ((value / totalExpensesForSummary) * 100).toFixed(1) + '%';
                                        label += `₹${value.toLocaleString()} (${percentage})`;
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            const quickSelectElement = document.getElementById('quickSelect');
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            quickSelectElement.addEventListener('change', function() {
                if (this.value !== '') {
                    startDateInput.value = '';
                    endDateInput.value = '';
                }
            });
            startDateInput.addEventListener('change', function() { if (this.value !== '') { quickSelectElement.value = ''; } });
            endDateInput.addEventListener('change', function() { if (this.value !== '') { quickSelectElement.value = ''; } });
        });
    </script>
</body>
</html>
