<?php
/**
 * Activity Logs Page
 * Displays system activity and authentication logs
 * Restricted to super_admin role
 */

// Do not allow direct access
if (!defined('IS_INCLUDED')) {
    // Define a constant to indicate this file should be included via index.php
    define('IS_INCLUDED', true);
    
    // Redirect to index.php if accessed directly
    header('Location: ../index.php?page=activity_logs');
    exit;
}

// Ensure user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit;
}

// Check permissions - Strict super_admin only access
$userRole = $_SESSION['user_role'] ?? '';
if ($userRole !== 'super_admin') {
    $_SESSION['error'] = "Only Super Administrators can access activity logs.";
    include 'pages/access_denied.php';
    return;
}

// Connect to database
$conn = connectToCore2DB();

// Set default filter values
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
$userFilter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100; 

// Prepare filters for query
$whereClause = [];
$params = [];

// Add date range filter
$whereClause[] = "al.created_at BETWEEN ? AND ?";
$params[] = $startDate . ' 00:00:00';
$params[] = $endDate . ' 23:59:59';

// Add action filter if specified
if (!empty($actionFilter)) {
    $whereClause[] = "al.action = ?";
    $params[] = $actionFilter;
}

// Add user filter if specified
if ($userFilter > 0) {
    $whereClause[] = "al.user_id = ?";
    $params[] = $userFilter;
}

// Build the WHERE clause
$whereClauseStr = !empty($whereClause) ? " WHERE " . implode(" AND ", $whereClause) : "";

// Get all users for filter dropdown
$userQuery = "SELECT user_id, firstname, lastname, email FROM users ORDER BY firstname, lastname";
$userResult = $conn->query($userQuery);
$users = [];
if ($userResult && $userResult->num_rows > 0) {
    while ($user = $userResult->fetch_assoc()) {
        $users[] = $user;
    }
}

// Get activity log data with pagination
$query = "SELECT 
            al.log_id,
            al.user_id,
            al.email,
            al.action,
            CASE
                WHEN al.action = 'login' THEN 'Login'
                WHEN al.action = 'logout' THEN 'Logout'
                WHEN al.action = 'failed_login' THEN 'Failed Login'
                WHEN al.action = 'password_reset' THEN 'Password Reset'
                WHEN al.action = 'authentication' THEN 'Authentication'
                WHEN al.action = 'failed_authentication' THEN 'Failed Authentication'
                WHEN al.action = 'auto_login' THEN 'Auto Login'
                WHEN al.action = 'otp_success' THEN 'OTP Verification Success'
                WHEN al.action = 'otp_failed' THEN 'OTP Verification Failed'
                ELSE CONCAT(UPPER(SUBSTRING(al.action, 1, 1)), SUBSTRING(al.action, 2))
            END AS action_name,
            al.ip_address,
            al.user_agent,
            al.created_at,
            u.firstname,
            u.lastname,
            u.role
        FROM 
            auth_logs al
        LEFT JOIN 
            users u ON al.user_id = u.user_id
        $whereClauseStr
        ORDER BY 
            al.created_at DESC
        LIMIT ?";

// Add limit to params
$params[] = $limit;

// Create a prepared statement
$stmt = $conn->prepare($query);

if ($stmt) {
    // Build the types string for bind_param
    $types = str_repeat('s', count($params) - 1) . 'i'; // All params are strings except the last one (limit)
    
    // Bind parameters
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    // Execute the query
    $stmt->execute();
    
    // Get results
    $result = $stmt->get_result();
} else {
    // Handle preparation error
    $error = $conn->error;
    $result = false;
}

// Get login stats for the dashboard
$statsQuery = "SELECT 
        action, 
        COUNT(*) as count
    FROM 
        auth_logs
    WHERE 
        created_at BETWEEN ? AND ?
    GROUP BY 
        action
    ORDER BY 
        count DESC";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("ss", 
    $params[0], // Start date
    $params[1]  // End date
);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Activity Logs</h1>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter me-1"></i>
        Filter Logs
    </div>
    <div class="card-body">
        <form method="GET" class="d-flex flex-wrap align-items-end filter-form">
            <input type="hidden" name="page" value="activity_logs">
            <div class="form-group mb-3">
                <label for="start_date">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="form-group mb-3">
                <label for="end_date">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <div class="form-group mb-3">
                <label for="action">Action</label>
                <select class="form-control" id="action" name="action">
                    <option value="">All Actions</option>
                    <option value="login" <?php echo $actionFilter === 'login' ? 'selected' : ''; ?>>Login</option>
                    <option value="logout" <?php echo $actionFilter === 'logout' ? 'selected' : ''; ?>>Logout</option>
                    <option value="failed_login" <?php echo $actionFilter === 'failed_login' ? 'selected' : ''; ?>>Failed Login</option>
                    <option value="authentication" <?php echo $actionFilter === 'authentication' ? 'selected' : ''; ?>>Authentication</option>
                    <option value="failed_authentication" <?php echo $actionFilter === 'failed_authentication' ? 'selected' : ''; ?>>Failed Authentication</option>
                    <option value="auto_login" <?php echo $actionFilter === 'auto_login' ? 'selected' : ''; ?>>Auto Login</option>
                    <option value="otp_success" <?php echo $actionFilter === 'otp_success' ? 'selected' : ''; ?>>OTP Success</option>
                    <option value="otp_failed" <?php echo $actionFilter === 'otp_failed' ? 'selected' : ''; ?>>OTP Failed</option>
                    <option value="password_reset" <?php echo $actionFilter === 'password_reset' ? 'selected' : ''; ?>>Password Reset</option>
                </select>
            </div>
            <div class="form-group mb-3">
                <label for="user_id">User</label>
                <select class="form-control" id="user_id" name="user_id">
                    <option value="0">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo $userFilter === (int)$user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-3">
                <label for="limit">Rows</label>
                <select class="form-control" id="limit" name="limit">
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                    <option value="250" <?php echo $limit === 250 ? 'selected' : ''; ?>>250</option>
                    <option value="500" <?php echo $limit === 500 ? 'selected' : ''; ?>>500</option>
                    <option value="1000" <?php echo $limit === 1000 ? 'selected' : ''; ?>>1000</option>
                </select>
            </div>
            <div class="form-group mb-3 ms-2">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="index.php?page=activity_logs" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Activity Logs Table -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-history me-1"></i>
        Activity Logs
    </div>
    <div class="card-body">
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="activityLogsTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <?php if (!empty($log['firstname']) && !empty($log['lastname'])): ?>
                                        <?php echo htmlspecialchars($log['firstname'] . ' ' . $log['lastname']); ?>
                                    <?php else: ?>
                                        <em>Unknown</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['email']); ?></td>
                                <td>
                                    <span class="badge action-badge action-<?php echo $log['action']; ?>">
                                        <?php echo htmlspecialchars($log['action_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td>
                                    <?php if (!empty($log['role'])): ?>
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['role']))); ?>
                                    <?php else: ?>
                                        <em>Unknown</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No activity logs found for the selected criteria.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Stats Card -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-bar me-1"></i>
        Login Statistics
    </div>
    <div class="card-body">
        <?php if ($statsResult && $statsResult->num_rows > 0): ?>
            <div class="row">
                <?php while ($stat = $statsResult->fetch_assoc()): ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="card-title">
                                    <?php 
                                    switch($stat['action']) {
                                        case 'login': echo 'Successful Logins'; break;
                                        case 'logout': echo 'Logouts'; break;
                                        case 'failed_login': echo 'Failed Logins'; break;
                                        case 'password_reset': echo 'Password Resets'; break;
                                        case 'authentication': echo 'Authentications'; break;
                                        case 'failed_authentication': echo 'Failed Authentications'; break;
                                        case 'auto_login': echo 'Auto Logins'; break;
                                        case 'otp_success': echo 'OTP Successes'; break;
                                        case 'otp_failed': echo 'OTP Failures'; break;
                                        default: echo ucfirst(str_replace('_', ' ', $stat['action']));
                                    }
                                    ?>
                                </h5>
                                <p class="card-text h2"><?php echo $stat['count']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No statistics available for the selected period.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#activityLogsTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "searching": true,
        "paging": true
    });
});
</script>

<?php
// Close the database connection
$conn->close();
?> 