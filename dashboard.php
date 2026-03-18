<?php
session_start();
include 'header1.php';
include 'db_connect.php';

// --- Basic Page Protection ---
$loggedInUserName = "Guest";
$user_id = null;
$overBudgetCategories = [];
$currentMonthYear = date('Y-m');

if (isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $loggedInUserName = $_SESSION['full_name'];
    $user_id = $_SESSION['user_id'];
} else {
    header("Location: signin.html?error=notloggedin");
    exit();
}

// --- Get Current Date and Period Type from URL ---
$currentDateStr = $_GET['current_date'] ?? date('Y-m-d');
$periodType = $_GET['period_type'] ?? 'monthly';


// Ensure current_date is a valid date for calculations
$currentDate = new DateTime($currentDateStr);

// --- Determine Date Range (startDate, endDate) Based on Period Type and Current Date ---
$startDate = clone $currentDate;
$endDate = clone $currentDate;
$displayPeriodText = "";

switch ($periodType) {
    case 'daily':
        $displayPeriodText = $currentDate->format('F d, Y');
        break;
    case 'weekly':
        $startDate->modify('last sunday');
        $endDate = (clone $startDate)->modify('+6 days');
        $displayPeriodText = $startDate->format('M d') . ' - ' . $endDate->format('M d, Y');
        break;
    case 'monthly':
        $startDate->modify('first day of this month');
        $endDate->modify('last day of this month');
        $displayPeriodText = $currentDate->format('F Y');
        break;
    case 'yearly':
        $startDate->modify('first day of january ' . $currentDate->format('Y'));
        $endDate->modify('last day of december ' . $currentDate->format('Y'));
        $displayPeriodText = $currentDate->format('Y');
        break;
    default:
        $periodType = 'monthly';
        $startDate->modify('first day of this month');
        $endDate->modify('last day of this month');
        $displayPeriodText = $currentDate->format('F Y');
        break;
}

$sqlStartDate = $startDate->format('Y-m-d');
$sqlEndDate = $endDate->format('Y-m-d');


// --- Data Fetching for Summary Cards ---
$totalIncome = 0;
$totalExpenses = 0;
$netBalance = 0;
$savingPercentage = 0;
$dailyExpenseAvg = 0;

// Initialize chart data arrays
$monthlyExpensesData = array_fill(1, 12, 0); // For yearly overview bar chart
$monthlyIncomeData = array_fill(1, 12, 0); // NEW: For yearly overview bar chart income
$chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; // For yearly overview bar chart
$pieChartData = []; // For spending analysis pie chart
$pieChartLabels = []; // For spending analysis pie chart
$actualPieChartColors = []; // For spending analysis pie chart


if ($user_id) {
    // Fetch total income for the selected period (for summary cards)
    $stmtIncomeSummary = $conn->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
    if ($stmtIncomeSummary) {
        $stmtIncomeSummary->bind_param("iss", $user_id, $sqlStartDate, $sqlEndDate);
        $stmtIncomeSummary->execute();
        $resultIncomeSummary = $stmtIncomeSummary->get_result();
        if ($row = $resultIncomeSummary->fetch_assoc()) {
            $totalIncome = $row['total'] ?? 0;
        }
        $stmtIncomeSummary->close();
    } else {
        error_log("Dashboard income summary query prepare failed: " . $conn->error);
    }

    // Fetch total expenses for the selected period (for summary cards and pie chart)
    $stmtExpensesSummary = $conn->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?");
    if ($stmtExpensesSummary) {
        $stmtExpensesSummary->bind_param("iss", $user_id, $sqlStartDate, $sqlEndDate);
        $stmtExpensesSummary->execute();
        $resultExpensesSummary = $stmtExpensesSummary->get_result();
        if ($row = $resultExpensesSummary->fetch_assoc()) {
            $totalExpenses = $row['total'] ?? 0;
        }
        $stmtExpensesSummary->close();
    } else {
        error_log("Dashboard expenses summary query prepare failed: " . $conn->error);
    }

    // Calculate Net Balance, Saving Percentage, Daily Avg
    $netBalance = $totalIncome - $totalExpenses;
    if ($totalIncome > 0) {
        $savingPercentage = (($totalIncome - $totalExpenses) / $totalIncome) * 100;
    } else if ($totalExpenses > 0) {
        $savingPercentage = -100;
    } else {
        $savingPercentage = 0;
    }

    $interval = $startDate->diff($endDate);
    $totalDaysInPeriod = $interval->days + 1;
    if ($totalDaysInPeriod > 0) {
        $dailyExpenseAvg = $totalExpenses / $totalDaysInPeriod;
    }

    // --- Data Fetching for Yearly Overview Bar Chart (Expenses & INCOME by Month for the displayed year) ---
    $displayedYear = $currentDate->format('Y'); // Get the year from the current reference date
    
    // Fetch Monthly Expenses for Bar Chart
    $stmtBarExpenses = $conn->prepare("SELECT MONTH(transaction_date) as month_num, SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense' AND YEAR(transaction_date) = ? GROUP BY month_num ORDER BY month_num ASC");
    if ($stmtBarExpenses) {
        $stmtBarExpenses->bind_param("ii", $user_id, $displayedYear);
        $stmtBarExpenses->execute();
        $resultBarExpenses = $stmtBarExpenses->get_result();
        while ($row = $resultBarExpenses->fetch_assoc()) {
            $monthNum = (int)$row['month_num'];
            $monthlyExpensesData[$monthNum] = $row['total'];
        }
        $stmtBarExpenses->close();
    } else {
        error_log("Dashboard Yearly Overview expenses chart query prepare failed: " . $conn->error);
    }

    // NEW: Fetch Monthly Income for Bar Chart
    $stmtBarIncome = $conn->prepare("SELECT MONTH(transaction_date) as month_num, SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income' AND YEAR(transaction_date) = ? GROUP BY month_num ORDER BY month_num ASC");
    if ($stmtBarIncome) {
        $stmtBarIncome->bind_param("ii", $user_id, $displayedYear);
        $stmtBarIncome->execute();
        $resultBarIncome = $stmtBarIncome->get_result();
        while ($row = $resultBarIncome->fetch_assoc()) {
            $monthNum = (int)$row['month_num'];
            $monthlyIncomeData[$monthNum] = $row['total'];
        }
        $stmtBarIncome->close();
    } else {
        error_log("Dashboard Yearly Overview income chart query prepare failed: " . $conn->error);
    }


    // --- Data Fetching for Spending Analysis Pie Chart (Expenses by Category for the selected period) ---
    $categoryExpenses = [];
    $stmtPieChart = $conn->prepare("SELECT category, SUM(amount) as total_amount FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ? GROUP BY category ORDER BY total_amount DESC");
    if ($stmtPieChart) {
        $stmtPieChart->bind_param("iss", $user_id, $sqlStartDate, $sqlEndDate);
        $stmtPieChart->execute();
        $resultPieChart = $stmtPieChart->get_result();

        while ($row = $resultPieChart->fetch_assoc()) {
            $categoryExpenses[htmlspecialchars($row['category'])] = $row['total_amount'];
        }
        $stmtPieChart->close();
    } else {
        error_log("Dashboard Spending Analysis chart query prepare failed: " . $conn->error);
    }
    
    // Prepare data for Chart.js Pie Chart
    $pieChartLabels = array_keys($categoryExpenses);
    $pieChartData = array_values($categoryExpenses);
    $pieChartColors = ['#0d6efd','#dc3545','#ffc107','#198754','#6f42c1','#fd7e14','#20c997','#6c757d','#e83e8c','#0dcaf0','#d63384','#6610f2'];
    $actualPieChartColors = [];
    for ($i = 0; $i < count($pieChartLabels); $i++) {
        $actualPieChartColors[] = $pieChartColors[$i % count($pieChartColors)];
    }
    // --- Budget Alert Logic ---
    $overBudget = false;
    $overBudgetCategories = [];
    $overBudget = false;
    $overBudgetCategories = [];
    $currentMonthYear = date('Y-m');

    $stmtBudgets = $conn->prepare("SELECT category, budget_limit FROM budgets WHERE user_id = ? AND month_year = ?");
    if ($stmtBudgets) {
        $stmtBudgets->bind_param("is", $user_id, $currentMonthYear);
        $stmtBudgets->execute();
        $resultBudgets = $stmtBudgets->get_result();
        $budgets = [];
        while ($row = $resultBudgets->fetch_assoc()) {
            $budgets[$row['category']] = $row['budget_limit'];
        }
        $stmtBudgets->close();

        if (!empty($budgets)) {
            $currentMonthStartDate = date('Y-m-01');
            $currentMonthEndDate = date('Y-m-t');
            $placeholders = implode(',', array_fill(0, count($budgets), '?'));
            $categories = array_keys($budgets);
            $paramTypes = "iss" . str_repeat("s", count($categories));
            $params = array_merge([$user_id, $currentMonthStartDate, $currentMonthEndDate], $categories);

            $stmtSpending = $conn->prepare("SELECT category, SUM(amount) AS total_spend FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ? AND category IN ($placeholders) GROUP BY category");

            if ($stmtSpending) {
                $bind_names = [$paramTypes];
                for ($i = 0; $i < count($params); $i++) {
                    $bind_name = 'bind' . $i;
                    $$bind_name = $params[$i];
                    $bind_names[] = &$$bind_name;
                }
                call_user_func_array([$stmtSpending, 'bind_param'], $bind_names);

                $stmtSpending->execute();
                $resultSpending = $stmtSpending->get_result();

                while ($row = $resultSpending->fetch_assoc()) {
                    $category = $row['category'];
                    $spend = $row['total_spend'];
                    if (isset($budgets[$category]) && $spend > $budgets[$category]) {
                        $overBudget = true;
                        $overBudgetCategories[] = $category;
                    }
                }
                $stmtSpending->close();
            } else {
                error_log("Dashboard Budget Alert query prepare failed: " . $conn->error);
            }
        }
    } else {
        error_log("Dashboard Budgets query prepare failed: " . $conn->error);
    }
}
$conn->close();

// --- Spending Alert Logic (reusing calculated values) ---
$displaySpendingAlert = false;
$spendingAlertMessage = '';
$spendingAlertType = '';

$spendingRatio = ($totalIncome > 0) ? ($totalExpenses / $totalIncome) * 100 : 0;

if ($spendingRatio >= 75) {
    $displaySpendingAlert = true;
    $spendingAlertType = 'alert-warning';
    if ($spendingRatio >= 100) {
        $spendingAlertMessage = 'Your expenses for this period have exceeded your income.';
    } 
    // elseif ($spendingRatio >= 0) {
    //     $spendingAlertMessage = 'Watch out! Either you forget to add income or you are spending without income.';
    // }
    else {
        $spendingAlertMessage = 'Your spending has reached ' . round($spendingRatio) . '% of your income for this period. Be mindful!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - FinFlow</title>
    <!-- Latest compiled and minified CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Icons for the alert boxes -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-value {
            font-size: 1.8rem;
            font-weight: 500;
        }
        .material-symbols-outlined {
            vertical-align: middle;
            margin-right: 0.25rem;
        }
        .filter-card {
            border-radius: 0.75rem;
        }
        .navbar-brand img {
            height: 45px;
        }
        /* Style for period navigation buttons */
        /* Style for period navigation buttons */
        .period-nav-btn {
            background-color: transparent;
            border: 1px solid #ced4da;
            color: #6c757d;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.2s ease-in-out;
        }
        .period-nav-btn:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
            color: #495057;
        }
        .period-nav-btn .material-symbols-outlined {
            font-size: 1.2rem;
            margin: 0;
        }
        /* Make chart containers responsive */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body>
     
    <!-- Main Navigation Bar (included from header.php) -->

    <div class="container mt-4">
        
        <!-- Dashboard Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h2 class="mb-0">Dashboard</h2>
                <!-- Dynamic Period Display and Navigation Buttons -->
                <p class="text-muted mb-0 d-flex align-items-center" style="margin-top:20px">
                    <button type="button" id="prevPeriodBtn" class="btn btn-sm btn-outline-secondary period-nav-btn me-2" onclick="navigatePeriod(-1);">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    Your financial summary for &nbsp;<span id="displayPeriodTextSpan"><?php echo $displayPeriodText; ?></span>
                    <button type="button" id="nextPeriodBtn" class="btn btn-sm btn-outline-secondary period-nav-btn ms-2" onclick="navigatePeriod(1);">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                </p>
            </div>
            <div>
                <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#setBudgetModal">
                <span class="material-symbols-outlined">add_box</span>
                Set Budget
            </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">+Add Transaction</button>
                <button class="btn btn-outline-secondary ms-2" id="refreshDashboardBtn" type="button">
                    <span class="material-symbols-outlined">refresh</span> Refresh
                </button>
    </div>
        <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addTransactionModalLabel">New Transaction</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form action="add_transaction_process.php" method="POST">
              <div class="mb-3">
                <label for="transactionDescription" class="form-label">Description</label>
                <input type="text" class="form-control" id="transactionDescription" name="description" required>
              </div>
              <div class="row">
                  <div class="col">
                      <div class="mb-3">
                        <label for="transactionAmount" class="form-label">Amount</label>
                        <input type="number" class="form-control" id="transactionAmount" name="amount" placeholder="0.00" step="0.01" required>
                      </div>
                  </div>
                  <div class="col">
                      <div class="mb-3">
                        <label for="transactionDate" class="form-label">Date</label>
                        <input type="date" class="form-control" id="transactionDate" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                      </div>
                  </div>
              </div>
              <div class="row">
                  <div class="col">
                      <div class="mb-3">
                        <label for="transactionType" class="form-label">Type</label>
                        <select class="form-select" id="transactionType" name="type" required>
                          <option value="expense" selected>Expense</option>
                          <option value="income">Income</option>
                        </select>
                      </div>
                  </div>
                  <div class="col">
                      <div class="mb-3">
                        <label for="transactionCategory" class="form-label">Category</label>
                        <select class="form-select" id="transactionCategory" name="category" required>
                          <option selected>Select a category</option>
                          <option value="food">Food</option>
                          <option value="transportation">Transportation</option>
                          <option value="shopping">Shopping</option>
                          <option value="health">Health & Wellness</soption>
                          <option value="entertainment">Entertainment</option>
                          <option value="bills">Bills & Subscriptions</option>
                          <option value="travel">Travel</option>
                          <option value="rent">Rent</option>
                          <option value="groceries">Groceries</option>
                          <option value="study">Study</option>
                          <option value="salary">Salary (Income)</option>
                          <option value="misc">Miscellaneous</option>
                          <option value="custom">Custom</option>
                        </select>
                      </div>
                  </div>
                  <div class="col" id="customCategoryInput" style="display: none;">
                                <div class="mb-3">
                                    <label for="transactionCustomCategory" class="form-label">Custom Category
                                        Name</label>
                                    <input type="text" class="form-control" id="transactionCustomCategory"
                                        name="custom_category" placeholder="e.g., Hobby, Gadgets">
                                </div>
                            </div>
              </div>
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Transaction</button>
          </div>
            </form>
        </div>
      </div>
    </div>

    <!-- Set Budget Modal -->
     <div class="modal fade" id="setBudgetModal" tabindex="-1" aria-labelledby="setBudgetModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header border-0">
                <h5 class="modal-title" id="setBudgetModalLabel">Set New Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <form action="set_budget_process.php" method="POST">
                  <div class="mb-3">
                    <label for="budgetCategory" class="form-label">Category</label>
                    <select class="form-select" id="budgetCategory" name="category" required>
                      <option selected>Select a category</option>
                      <option value="food">Food</option>
                      <option value="transportation">Transportation</option>
                      <option value="shopping">Shopping</option>
                      <option value="health">Health & Wellness</option>
                      <option value="entertainment">Entertainment</option>
                      <option value="bills">Bills & Subscriptions</option>
                      <option value="travel">Travel</option>
                      <option value="rent">Rent</option>
                      <option value="groceries">Groceries</option>
                      <option value="study">Study</option>
                      <option value="salary">Salary (Income)</option>
                      <option value="misc">Miscellaneous</option>
                      <option value="custom">Custom</option>
                    </select>
                  </div>
                  <div class="mb-3" id="budgetCustomCategoryInput" style="display: none;">
                      <label for="budgetCustomCategoryName" class="form-label">Custom Category Name</label>
                      <input type="text" class="form-control" id="budgetCustomCategoryName" name="custom_category" placeholder="e.g., Hobby, Gadgets">
                  </div>
                  <div class="mb-3">
                    <label for="budgetLimit" class="form-label">Limit (₹)</label>
                    <input type="number" class="form-control" id="budgetLimit" name="budget_limit" placeholder="Enter amount" step="0.01" required>
                  </div>
                  <div class="mb-3">
                    <label for="budgetMonthYear" class="form-label">Month & Year</label>
                    <input type="month" class="form-control" id="budgetMonthYear" name="month_year" value="<?php echo $currentMonthYear; ?>" required>
                  </div>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Budget</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>


        <!-- Time Period Toggles -->
        <div class="mb-4">
            <form action="dashboard.php" method="GET" id="periodForm">
                <input type="hidden" id="currentDateInput" name="current_date" value="<?php echo $currentDate->format('Y-m-d'); ?>">
                <div class="btn-group" role="group" aria-label="Time period toggle">
                    <input type="radio" class="btn-check" name="period_type" id="btnradio1" value="daily" onchange="this.form.submit();" autocomplete="off" <?php echo ($periodType == 'daily' ? 'checked' : ''); ?>>
                    <label class="btn btn-outline-primary" for="btnradio1" style="margin-top:12px">Daily</label>

                    <input type="radio" class="btn-check" name="period_type" id="btnradio2" value="weekly" onchange="this.form.submit();" autocomplete="off" <?php echo ($periodType == 'weekly' ? 'checked' : ''); ?>>
                    <label class="btn btn-outline-primary" for="btnradio2" style="margin-top:12px">Weekly</label>

                    <input type="radio" class="btn-check" name="period_type" id="btnradio3" value="monthly" onchange="this.form.submit();" autocomplete="off" <?php echo ($periodType == 'monthly' ? 'checked' : ''); ?>>
                    <label class="btn btn-outline-primary" for="btnradio3" style="margin-top:12px">Monthly</label>

                    <input type="radio" class="btn-check" name="period_type" id="btnradio4" value="yearly" onchange="this.form.submit();" autocomplete="off" <?php echo ($periodType == 'yearly' ? 'checked' : ''); ?>>
                    <label class="btn btn-outline-primary" for="btnradio4" style="margin-top:12px">Yearly</label>
                </div>
            </form>
        </div>

        <!-- Alerts Section -->
        <?php if ($displaySpendingAlert): ?>
        <div class="alert <?php echo $spendingAlertType; ?> d-flex align-items-center" role="alert">
            <span class="material-symbols-outlined">info</span>
            <div>
                <strong>Spending Alert:</strong> <?php echo $spendingAlertMessage; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($overBudget):
            $overBudgetCategoriesText = implode(', ', $overBudgetCategories);
        ?>
        <div class="alert alert-danger d-flex justify-content-between align-items-center" role="alert">
            <div>
                <span class="material-symbols-outlined">warning</span>
                <strong>Budget Alert:</strong> You are over budget this month in: <?php echo htmlspecialchars($overBudgetCategoriesText); ?>.
            </div>
            <a href="budgets.php" class="alert-link fw-bold">View Budgets</a>
        </div>
        <?php endif; ?>


        <!-- Financial Summary Cards (DYNAMIC) -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Total Income</h6>
                        <p class="card-value text-success mb-0">₹<?php echo number_format($totalIncome, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Total Expenses</h6>
                        <p class="card-value text-danger mb-0">₹<?php echo number_format($totalExpenses, 2); ?></p>
                        <small class="text-muted">₹<?php echo number_format($dailyExpenseAvg, 2); ?> / day</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Net Balance</h6>
                        <p class="card-value <?php echo ($netBalance < 0 ? 'text-danger' : 'text-success'); ?> mb-0">₹<?php echo number_format($netBalance, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Saving Percentage</h6>
                        <p class="card-value <?php echo ($savingPercentage < 0 ? 'text-danger' : 'text-primary'); ?> mb-0"><?php echo round($savingPercentage); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title">Yearly Overview (Expenses & Income by Month - <?php echo $displayedYear; ?>)</h5>
                        <div class="chart-container">
                            <canvas id="yearlyOverviewChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title">Spending Analysis (Expenses by Category for <?php echo $displayPeriodText; ?>)</h5>
                        <div class="chart-container">
                            <canvas id="spendingAnalysisChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Add a little padding at the bottom -->
    <div class="py-3"></div>

    <!-- Latest compiled JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Making navigatePeriod global for onclick
        function navigatePeriod(direction) {
            const periodForm = document.getElementById('periodForm');
            const currentDateInput = document.getElementById('currentDateInput');
            let currentDate = new Date(currentDateInput.value);
            const periodType = document.querySelector('input[name="period_type"]:checked').value;
            let newDate = new Date(currentDate);

            switch (periodType) {
                case 'daily':
                    newDate.setDate(currentDate.getDate() + direction);
                    break;
                case 'weekly':
                    newDate.setDate(currentDate.getDate() + (direction * 7));
                    break;
                case 'monthly':
                    newDate.setMonth(currentDate.getMonth() + direction);
                    newDate.setDate(1);
                    break;
                case 'yearly':
                    newDate.setFullYear(currentDate.getFullYear() + direction);
                    break;
            }

            const year = newDate.getFullYear();
            const month = String(newDate.getMonth() + 1).padStart(2, '0');
            const day = String(newDate.getDate()).padStart(2, '0');

            currentDateInput.value = `${year}-${month}-${day}`;
            periodForm.submit();
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Refresh button logic: always redirect to current month
            const refreshBtn = document.getElementById('refreshDashboardBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    const today = new Date();
                    const year = today.getFullYear();
                    const month = String(today.getMonth() + 1).padStart(2, '0');
                    const day = '01';
                    // Always go to first day of current month, period_type monthly
                    window.location.href = `dashboard.php?current_date=${year}-${month}-${day}&period_type=monthly`;
                });
            }

            // --- Chart.js for Yearly Overview Bar Chart (Income & Expenses) ---
            const ctxBar = document.getElementById('yearlyOverviewChart');
            if (ctxBar) { // Check if canvas element exists
                const chartLabels = <?php echo json_encode($chartLabels); ?>;
                const monthlyExpensesData = <?php echo json_encode(array_values($monthlyExpensesData)); ?>;
                const monthlyIncomeData = <?php echo json_encode(array_values($monthlyIncomeData)); ?>; // NEW: Income data

                // Only create chart if there's data to display (either income or expenses)
                if (monthlyExpensesData.some(val => val > 0) || monthlyIncomeData.some(val => val > 0)) {
                    new Chart(ctxBar.getContext('2d'), {
                        type: 'bar', // Bar chart for yearly overview
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Monthly Income (₹)', // NEW: Income label
                                data: monthlyIncomeData, // NEW: Income data
                                backgroundColor: 'rgba(13, 110, 253, 0.7)', // Blue color for income
                                borderColor: 'rgba(13, 110, 253, 1)',
                                borderWidth: 1
                            }, {
                                label: 'Monthly Expenses (₹)',
                                data: monthlyExpensesData,
                                backgroundColor: 'rgba(220, 53, 69, 0.7)', // Red color for expenses
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
                                    title: {
                                        display: true,
                                        text: 'Amount (₹)'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Month'
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                title: {
                                    display: true,
                                    text: 'Monthly Income vs. Expenses' // Updated title
                                }
                            }
                        }
                    });
                } else {
                    // Display a message if no data for the bar chart
                    const barChartContainer = document.querySelector('#yearlyOverviewChart').parentNode;
                    barChartContainer.innerHTML = '<p class="text-muted text-center mt-5">No income or expense data for this year to show overview.</p>';
                    barChartContainer.style.height = 'auto'; // Adjust height if message is displayed
                }
            }


            // --- Chart.js for Spending Analysis Pie Chart ---
            const ctxPie = document.getElementById('spendingAnalysisChart');
            if (ctxPie) { // Check if canvas element exists
                const pieChartLabels = <?php echo json_encode($pieChartLabels); ?>;
                const pieChartData = <?php echo json_encode(array_values($pieChartData)); ?>;
                const pieChartColors = <?php echo json_encode($actualPieChartColors); ?>;
                
                // Only create pie chart if there's data to avoid rendering empty chart
                if (pieChartData.some(val => val > 0)) {
                    new Chart(ctxPie.getContext('2d'), {
                        type: 'pie',
                        data: {
                            labels: pieChartLabels,
                            datasets: [{
                                label: 'Expenses by Category (₹)',
                                data: pieChartData,
                                backgroundColor: pieChartColors,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'right' // Place legend on the right for better readability
                                },
                                title: {
                                    display: true,
                                    text: 'Expenses by Category'
                                },
                                tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed) {
                                        const value = context.parsed;
                                        label += `₹${value.toLocaleString()}`; // Only display the amount
                                    }
                                    return label;
                                }
                            }
                        }
                        }
                    }
                });
                } else {
                    // Display a message if no data for the pie chart
                    const pieChartContainer = document.querySelector('#spendingAnalysisChart').parentNode;
                    pieChartContainer.innerHTML = '<p class="text-muted text-center mt-5">No expense data for this period to show analysis.</p>';
                    pieChartContainer.style.height = 'auto'; // Adjust height if message is displayed
                }
            }
        });
    </script>

</body>
</html>