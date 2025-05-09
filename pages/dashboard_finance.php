<?php
// Check if user has finance_staff role
if (!hasRole('finance_staff')) {
    echo '<div class="alert alert-danger">You do not have permission to access this dashboard.</div>';
    exit;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Finance Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="periodDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-calendar-alt"></i> This Month
            </button>
            <ul class="dropdown-menu" aria-labelledby="periodDropdown">
                <li><a class="dropdown-item" href="#">Today</a></li>
                <li><a class="dropdown-item" href="#">This Week</a></li>
                <li><a class="dropdown-item" href="#">This Month</a></li>
                <li><a class="dropdown-item" href="#">This Quarter</a></li>
                <li><a class="dropdown-item" href="#">This Year</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Custom Range</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Revenue</h5>
                        <h3 class="mb-0">₱154,750</h3>
                    </div>
                    <div>
                        <i class="fas fa-money-bill-wave fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="small mt-2">
                    <i class="fas fa-arrow-up"></i> 12% from last month
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Expenses</h5>
                        <h3 class="mb-0">₱89,320</h3>
                    </div>
                    <div>
                        <i class="fas fa-credit-card fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="small mt-2">
                    <i class="fas fa-arrow-down"></i> 5% from last month
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Profit</h5>
                        <h3 class="mb-0">₱65,430</h3>
                    </div>
                    <div>
                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="small mt-2">
                    <i class="fas fa-arrow-up"></i> 8% from last month
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Bookings</h5>
                        <h3 class="mb-0">254</h3>
                    </div>
                    <div>
                        <i class="fas fa-calendar-check fa-3x opacity-50"></i>
                    </div>
                </div>
                <div class="small mt-2">
                    <i class="fas fa-arrow-up"></i> 15% from last month
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue & Expense Charts -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i> Revenue & Expense Trends</h5>
            </div>
            <div class="card-body">
                <div id="revenue-chart-placeholder" style="height: 300px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                    <div class="text-center">
                        <i class="fas fa-chart-line fa-4x text-secondary mb-3"></i>
                        <h5>Monthly Revenue & Expense Chart</h5>
                        <p class="text-muted">Chart.js would render revenue and expense trend lines here</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Expense Breakdown</h5>
            </div>
            <div class="card-body">
                <div id="expense-pie-placeholder" style="height: 300px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                    <div class="text-center">
                        <i class="fas fa-chart-pie fa-4x text-secondary mb-3"></i>
                        <h5>Expense Categories</h5>
                        <p class="text-muted">Chart.js would render pie chart here</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions & Pending Actions -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i> Recent Transactions</h5>
                <a href="#" class="text-decoration-none small">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>#TRX-8742</strong></td>
                                <td>Today, 10:30 AM</td>
                                <td>Booking Payment</td>
                                <td>Booking #8742 - Maria Garcia</td>
                                <td class="text-success">₱550.00</td>
                                <td><span class="badge bg-success">Completed</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#TRX-8741</strong></td>
                                <td>Today, 9:15 AM</td>
                                <td>Booking Payment</td>
                                <td>Booking #8741 - Kevin Chen</td>
                                <td class="text-success">₱420.00</td>
                                <td><span class="badge bg-success">Completed</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#TRX-8740</strong></td>
                                <td>Yesterday, 4:45 PM</td>
                                <td>Fuel Expense</td>
                                <td>Vehicle #ABC-123 - 40L Diesel</td>
                                <td class="text-danger">-₱2,500.00</td>
                                <td><span class="badge bg-success">Completed</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#TRX-8739</strong></td>
                                <td>Yesterday, 3:30 PM</td>
                                <td>Booking Payment</td>
                                <td>Booking #8739 - Lisa Wong</td>
                                <td class="text-success">₱650.00</td>
                                <td><span class="badge bg-warning text-dark">Pending</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary">Process</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#TRX-8738</strong></td>
                                <td>Yesterday, 2:00 PM</td>
                                <td>Maintenance Expense</td>
                                <td>Vehicle #DEF-456 - Oil Change</td>
                                <td class="text-danger">-₱1,800.00</td>
                                <td><span class="badge bg-success">Completed</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary">Details</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> Pending Approvals</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Driver Reimbursement</h6>
                            <small>2 hours ago</small>
                        </div>
                        <p class="mb-1">Mike Johnson - Toll Fee Reimbursement (₱250)</p>
                        <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-sm btn-success me-2">Approve</button>
                            <button class="btn btn-sm btn-danger">Decline</button>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Maintenance Request</h6>
                            <small>5 hours ago</small>
                        </div>
                        <p class="mb-1">Vehicle #GHI-789 - Brake Replacement (₱4,500)</p>
                        <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-sm btn-success me-2">Approve</button>
                            <button class="btn btn-sm btn-danger">Decline</button>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Refund Request</h6>
                            <small>Yesterday</small>
                        </div>
                        <p class="mb-1">Booking #8736 - David Lee (₱350)</p>
                        <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-sm btn-success me-2">Approve</button>
                            <button class="btn btn-sm btn-danger">Decline</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-link me-2"></i> Quick Links</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="index.php?page=financial_reports" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-invoice-dollar me-2"></i> Generate Financial Reports
                    </a>
                    <a href="index.php?page=payment_processing" class="list-group-item list-group-item-action">
                        <i class="fas fa-money-check-alt me-2"></i> Process Payments
                    </a>
                    <a href="index.php?page=expense_management" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-invoice me-2"></i> Manage Expenses
                    </a>
                    <a href="index.php?page=payroll" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> Driver Payroll
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Here you could add any JavaScript specific to the Finance Staff dashboard
    console.log('Finance Dashboard loaded');
});
</script> 