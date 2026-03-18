<?php
session_start();

include 'db_connect.php';

// --- Basic Page Protection and Data Fetching ---
$loggedInUserName = 'Guest';
$user_id = null;

if (isset($_SESSION['full_name']) && isset($_SESSION['user_id'])) {
    $loggedInUserName = $_SESSION['full_name'];
    $user_id = $_SESSION['user_id'];
} else {
    header('Location: signin.html?error=notloggedin');
    exit();
}

// --- Get Current Month and Year for display and database query (No navigation) ---
$displayMonthYear = date('Y-m'); // YYYY-MM format for database query
$displayPeriodText = date('F Y');

// --- Fetch User's Budgets for the Current Month ---
$userBudgets = [];
$stmtBudgets = $conn->prepare('SELECT id, category, budget_limit, month_year FROM budgets WHERE user_id = ? AND month_year = ? ORDER BY category ASC');
if ($stmtBudgets) {
    $stmtBudgets->bind_param('is', $user_id, $displayMonthYear);
    $stmtBudgets->execute();
    $resultBudgets = $stmtBudgets->get_result();
    while ($row = $resultBudgets->fetch_assoc()) {
        $userBudgets[] = $row;
    }
    $stmtBudgets->close();
} else {
    error_log('Budgets fetch query prepare failed: ' . $conn->error);
}

$budgetsExist = (count($userBudgets) > 0);

// --- NEW LOGIC: If no budgets for the current month, carry over from last month ---
if (!$budgetsExist) {
    $lastMonthYear = date('Y-m', strtotime('last month'));
    $stmtLastMonthBudgets = $conn->prepare('SELECT category, budget_limit FROM budgets WHERE user_id = ? AND month_year = ?');
    if ($stmtLastMonthBudgets) {
        $stmtLastMonthBudgets->bind_param('is', $user_id, $lastMonthYear);
        $stmtLastMonthBudgets->execute();
        $resultLastMonthBudgets = $stmtLastMonthBudgets->get_result();
        
        $newBudgetsAdded = false;
        if ($resultLastMonthBudgets->num_rows > 0) {
            $stmtInsertNewBudget = $conn->prepare('INSERT INTO budgets (user_id, category, budget_limit, month_year) VALUES (?, ?, ?, ?)');
            if ($stmtInsertNewBudget) {
                while ($lastMonthBudget = $resultLastMonthBudgets->fetch_assoc()) {
                    $category = $lastMonthBudget['category'];
                    $limit = $lastMonthBudget['budget_limit'];
                    $stmtInsertNewBudget->bind_param('isds', $user_id, $category, $limit, $displayMonthYear);
                    $stmtInsertNewBudget->execute();
                }
                $stmtInsertNewBudget->close();
                $newBudgetsAdded = true;
            } else {
                error_log('Failed to prepare INSERT statement for new budgets: ' . $conn->error);
            }
        }
        $stmtLastMonthBudgets->close();

        if ($newBudgetsAdded) {
            $userBudgets = [];
            $stmtBudgets = $conn->prepare('SELECT id, category, budget_limit, month_year FROM budgets WHERE user_id = ? AND month_year = ? ORDER BY category ASC');
            if ($stmtBudgets) {
                $stmtBudgets->bind_param('is', $user_id, $displayMonthYear);
                $stmtBudgets->execute();
                $resultBudgets = $stmtBudgets->get_result();
                while ($row = $resultBudgets->fetch_assoc()) {
                    $userBudgets[] = $row;
                }
                $stmtBudgets->close();
            }
            $budgetsExist = true;
        }
    } else {
        error_log('Last month budgets fetch query prepare failed: ' . $conn->error);
    }
}
// --- END NEW LOGIC ---

// --- Fetch Current Spending for Each Budget Category (from transactions table) ---
$currentSpendingByCat = [];
if ($budgetsExist) {
    $currentMonthStartDate = date('Y-m-01');
    $currentMonthEndDate = date('Y-m-t');

    // Use case-insensitive matching for category to ensure 'salary' is tracked correctly
    $stmtSpending = $conn->prepare('SELECT LOWER(category) AS category, SUM(amount) AS total_spend FROM transactions WHERE user_id = ? AND type = \'expense\' AND transaction_date BETWEEN ? AND ? GROUP BY LOWER(category)');
    if ($stmtSpending) {
        $stmtSpending->bind_param('iss', $user_id, $currentMonthStartDate, $currentMonthEndDate);
        $stmtSpending->execute();
        $resultSpending = $stmtSpending->get_result();
        while ($row = $resultSpending->fetch_assoc()) {
            $currentSpendingByCat[$row['category']] = $row['total_spend'];
        }
        $stmtSpending->close();
    } else {
        error_log('Budget spending fetch query prepare failed: ' . $conn->error);
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Budgets - FinFlow</title>
    <link rel="icon" type="image/png" href="logo_transparent_background.png">
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0' />
    <style>
        body { background-color: #f8f9fa; }
        .budget-card {
            border: 1px solid #e0e0e0;
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05);
            transition: all 0.2s ease-in-out;
        }
        .budget-card.over-budget {
            border-color: #dc3545;
            box-shadow: 0 0.5rem 1.5rem rgba(220,53,69,0.2);
        }
        .budget-card .progress { height: 8px; margin-top: 0.5rem; background-color: #e9ecef; }
        .budget-card .progress-bar { border-radius: 0.5rem; }
        .budget-card .alert-icon { font-size: 1.5rem; vertical-align: middle; margin-right: 0.5rem; color: #dc3545; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class='container my-5'>
        <div class='d-flex justify-content-between align-items-center mb-4'>
            <div class='d-flex align-items-center'>
                <h2 class='mb-0'>Budgets for <?php echo $displayPeriodText; ?></h2>
            </div>
            <button class='btn btn-dark' data-bs-toggle='modal' data-bs-target='#setBudgetModal'>
                <span class='material-symbols-outlined'>add_box</span>
                Set Budget
            </button>
        </div>
        <div id='alertContainer' class='mt-3'></div>

        <?php if ($budgetsExist): ?>
        <div class='row g-4' id='budgetCardsContainer'>
            <?php foreach ($userBudgets as $budget):
                // Normalize category to lowercase for matching spending
                $normalizedCategory = strtolower($budget['category']);
                $currentSpend = $currentSpendingByCat[$normalizedCategory] ?? 0;
                $budgetLimit = $budget['budget_limit'];
                $remaining = $budgetLimit - $currentSpend;
                $progressPercentage = ($budgetLimit > 0) ? ($currentSpend / $budgetLimit) * 100 : 0;
                $progressBarClass = 'bg-success';
                $cardStatusClass = '';
                $overBudgetAlert = '';
                if ($progressPercentage > 100) {
                $progressBarClass = 'bg-danger';
                $cardStatusClass = 'over-budget';
                $overAmount = abs($remaining);
                $overBudgetAlert = '<span class="d-flex align-items-center gap-1 text-danger fw-bold"><span class="material-symbols-outlined" style="font-size: 1.3rem;">warning</span>₹' . number_format($overAmount, 2) . ' Over Budget</span>';
                } elseif ($progressPercentage >= 80) {
                    $progressBarClass = 'bg-warning';
                }
                $remainingText = '₹' . number_format(abs($remaining), 2);
                $remainingDirection = ($remaining >= 0) ? 'left' : 'over';
            ?>
            <div class='col-md-6 col-lg-4'>
                <div class='card budget-card h-100 shadow-sm <?php echo $cardStatusClass; ?>'>
                    <div class='card-body'>
                        <div class='d-flex justify-content-between align-items-center mb-2'>
                        <h5 class='card-title mb-0'><?php echo ucwords(htmlspecialchars($budget['category'])); ?></h5>
                            <?php if ($progressPercentage > 100) echo $overBudgetAlert; ?>
                        </div>
                        <p class='card-text text-muted mb-1'>
                            ₹<?php echo number_format($currentSpend, 2); ?> of ₹<?php echo number_format($budgetLimit, 2); ?>
                        </p>
                        <div class='progress' role='progressbar' aria-label='Budget Progress' aria-valuenow='<?php echo min(100, $progressPercentage); ?>' aria-valuemin='0' aria-valuemax='100'>
                            <div class='progress-bar <?php echo $progressBarClass; ?>' style='width: <?php echo min(100, $progressPercentage); ?>%'></div>
                        </div>
                        <div class='d-flex justify-content-between align-items-center mt-2'>
                            <small class='text-muted'>₹<?php echo number_format(abs($remaining), 2); ?> <?php echo $remainingDirection; ?></small>
                            <div>
                                <button class='btn btn-sm btn-light'
                                        data-bs-toggle='modal'
                                        data-bs-target='#editBudgetModal'
                                        data-id='<?php echo $budget['id']; ?>'
                                        data-category='<?php echo htmlspecialchars($budget['category']); ?>'
                                        data-limit='<?php echo $budget['budget_limit']; ?>'
                                        data-month-year='<?php echo $budget['month_year']; ?>'>
                                    <span class='material-symbols-outlined'>edit</span>
                                </button>
                                <button class='btn btn-sm btn-light'
                                        data-bs-toggle='modal'
                                        data-bs-target='#deleteBudgetModal'
                                        data-id='<?php echo $budget['id']; ?>'>
                                    <span class='material-symbols-outlined'>delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class='text-center py-5' id='noBudgetsFound'>
            <h4 class='mb-1'>No Budgets Found for <?php echo $displayPeriodText; ?></h4>
            <p class='text-muted'>Click 'Set Budget' to create one.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Set Budget Modal -->
    <div class='modal fade' id='setBudgetModal' tabindex='-1' aria-labelledby='setBudgetModalLabel' aria-hidden='true'>
      <div class='modal-dialog modal-dialog-centered'>
        <div class='modal-content'>
          <div class='modal-header border-0'>
            <h5 class='modal-title' id='setBudgetModalLabel'>Set New Budget</h5>
            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
          </div>
          <div class='modal-body'>
            <form action='set_budget_process.php' method='POST' id='budgetForm'>
              <div class='mb-3'>
                <label for='budgetCategory' class='form-label'>Category</label>
                <select class='form-select' id='budgetCategory' name='category' required>
                  <option value='' disabled selected>Select a category</option>
                  <option value='food'>Food</option>
                  <option value='transportation'>Transportation</option>
                  <option value='shopping'>Shopping</option>
                  <option value='health'>Health & Wellness</option>
                  <option value='entertainment'>Entertainment</option>
                  <option value='bills'>Bills & Subscriptions</option>
                  <option value='travel'>Travel</option>
                  <option value='rent'>Rent</option>
                  <option value='groceries'>Groceries</option>
                  <option value='study'>Study</option>
                  <option value='misc'>Miscellaneous</option>
                  <option value='custom'>Custom</option>
                </select>
              </div>
              <div class='mb-3' id='budgetCustomCategoryInput' style='display: none;'>
                  <label for='budgetCustomCategoryName' class='form-label'>Custom Category Name</label>
                  <input type='text' class='form-control' id='budgetCustomCategoryName' name='custom_category' placeholder='e.g., Hobby, Gadgets'>
              </div>
              <div class='mb-3'>
                <label for='budgetLimit' class='form-label'>Limit (₹)</label>
                <input type='number' class='form-control' id='budgetLimit' name='budget_limit' placeholder='Enter amount' step='0.01' required>
              </div>
                <input type='hidden' name='month_year' value='<?php echo $displayMonthYear; ?>'>
          </div>
          <div class='modal-footer border-0'>
            <button type='button' class='btn btn-light' data-bs-dismiss='modal'>Cancel</button>
            <button type='submit' class='btn btn-primary'>Save Budget</button>
          </div>
            </form>
        </div>
      </div>
    </div>

    <!-- Edit Budget Modal (reusing the structure of setBudgetModal for editing) -->
    <div class='modal fade' id='editBudgetModal' tabindex='-1' aria-labelledby='editBudgetModalLabel' aria-hidden='true'>
      <div class='modal-dialog modal-dialog-centered'>
        <div class='modal-content'>
          <div class='modal-header border-0'>
            <h5 class='modal-title' id='editBudgetModalLabel'>Edit Budget</h5>
            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
          </div>
          <div class='modal-body'>
            <form action='edit_budget_process.php' method='POST' id='editBudgetForm'>
              <input type='hidden' id='editBudgetId' name='budget_id'>
              <div class='mb-3'>
                <label for='editBudgetCategory' class='form-label'>Category</label>
                <select class='form-select' id='editBudgetCategory' name='category' required>
                  <option value='' disabled selected>Select a category</option>
                  <option value='food'>Food</option>
                  <option value='transportation'>Transportation</option>
                  <option value='shopping'>Shopping</option>
                  <option value='health'>Health & Wellness</option>
                  <option value='entertainment'>Entertainment</option>
                  <option value='bills'>Bills & Subscriptions</option>
                  <option value='travel'>Travel</option>
                  <option value='rent'>Rent</option>
                  <option value='groceries'>Groceries</option>
                  <option value='study'>Study</option>
                  <option value='salary'>Salary</option>
                  <option value='misc'>Miscellaneous</option>
                </select>
              </div>
              <div class='mb-3'>
                <label for='editBudgetLimit' class='form-label'>Limit (₹)</label>
                <input type='number' class='form-control' id='editBudgetLimit' name='budget_limit' placeholder='Enter amount' step='0.01' required>
              </div>
                <input type='hidden' name='month_year' id='editBudgetMonthYear' value='<?php echo $displayMonthYear; ?>'>
          </div>
          <div class='modal-footer border-0'>
            <button type='button' class='btn btn-light' data-bs-dismiss='modal'>Cancel</button>
            <button type='submit' class='btn btn-primary' form='editBudgetForm'>Save Changes</button>
          </div>
            </form>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class='modal fade' id='deleteBudgetModal' tabindex='-1' aria-labelledby='deleteBudgetModalLabel' aria-hidden='true'>
      <div class='modal-dialog'>
        <div class='modal-content'>
          <div class='modal-header'>
            <h5 class='modal-title' id='deleteBudgetModalLabel'>Confirm Deletion</h5>
            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
          </div>
          <div class='modal-body'>
            Are you sure you want to delete this budget? This action cannot be undone.
          </div>
          <div class='modal-footer'>
            <button type='button' class='btn btn-light' data-bs-dismiss='modal'>Cancel</button>
            <form action='delete_budget_process.php' method='POST' id='deleteBudgetForm'>
              <input type='hidden' name='budget_id' id='deleteBudgetIdInput'>
              <button type='submit' class='btn btn-danger'>Delete</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // JavaScript for showing/hiding custom category input for BUDGETS
            const budgetCategorySelect = document.getElementById('budgetCategory');
            const budgetCustomCategoryInputDiv = document.getElementById('budgetCustomCategoryInput');
            const budgetCustomCategoryNameField = document.getElementById('budgetCustomCategoryName');

            budgetCategorySelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    budgetCustomCategoryInputDiv.style.display = 'block';
                    budgetCustomCategoryNameField.setAttribute('required', 'required');
                } else {
                    budgetCustomCategoryInputDiv.style.display = 'none';
                    budgetCustomCategoryNameField.removeAttribute('required');
                    budgetCustomCategoryNameField.value = '';
                }
            });

            const editBudgetModal = document.getElementById('editBudgetModal');
            editBudgetModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const category = button.getAttribute('data-category');
                const limit = button.getAttribute('data-limit');
                const modalBudgetIdInput = editBudgetModal.querySelector('#editBudgetId');
                const modalCategorySelect = editBudgetModal.querySelector('#editBudgetCategory');
                const modalLimitInput = editBudgetModal.querySelector('#editBudgetLimit');
                const modalMonthYearInput = editBudgetModal.querySelector('#editBudgetMonthYear');
                modalBudgetIdInput.value = id;
                modalCategorySelect.value = category;
                modalLimitInput.value = limit;
                modalMonthYearInput.value = '<?php echo $displayMonthYear; ?>'; // Keep this dynamic
            });
            const deleteBudgetModal = document.getElementById('deleteBudgetModal');
            deleteBudgetModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const budgetId = button.getAttribute('data-id');
                const modalBudgetIdInput = deleteBudgetModal.querySelector('#deleteBudgetIdInput');
                modalBudgetIdInput.value = budgetId;
            });

            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const alertContainer = document.getElementById('alertContainer');
            if (alertContainer && status) {
                let message = '';
                let alertType = '';
                if (status === 'success_added') {
                    message = 'Budget saved successfully!';
                    alertType = 'alert-success';
                } else if (status === 'success_updated') {
                    message = 'Budget updated successfully!';
                    alertType = 'alert-success';
                } else if (status === 'success_deleted') {
                    message = 'Budget deleted successfully!';
                    alertType = 'alert-success';
                } else if (status === 'error_budget_exists') {
                    message = 'A budget for this category and month already exists. Please edit it instead.';
                    alertType = 'alert-danger';
                } else {
                    message = 'An error occurred. Please try again.';
                    alertType = 'alert-danger';
                }
                alertContainer.innerHTML = `<div class='alert ${alertType} alert-dismissible fade show' role='alert'>${message}<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>`;
                setTimeout(() => {
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.delete('status');
                    window.history.replaceState({}, document.title, newUrl.toString());
                }, 3000);
            }
        });
    </script>
</body>
</html>