<?php
// System Logs Page
// Displays system logs and API settings

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
if (!function_exists('connectToCore2DB')) {
    require_once 'functions/db.php';
}

// Ensure auth is included
if (!function_exists('hasPermission')) {
    require_once 'functions/auth.php';
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Please log in to access this page.</div>';
    exit;
}

// Check permissions - Allow access only for admin and super_admin
$userRole = $_SESSION['user_role'] ?? '';
if (!($userRole === 'admin' || $userRole === 'super_admin')) {
    echo '<div class="alert alert-danger">You do not have permission to access System Logs.</div>';
    exit;
}

// Get database connection
$conn = connectToCore2DB();

// Process API settings update if submitted
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_api_settings') {
        // Process API settings update
        $apiDebugMode = isset($_POST['api_debug_mode']) ? 1 : 0;
        $apiRateLimit = (int)$_POST['api_rate_limit'];
        $apiVersion = $conn->real_escape_string($_POST['api_version']);
        $apiAuthToken = $conn->real_escape_string($_POST['api_auth_token']);
        
        // Check if settings exist
        $checkSettings = $conn->query("SELECT id FROM api_settings LIMIT 1");
        
        if ($checkSettings && $checkSettings->num_rows > 0) {
            // Update existing settings
            $settingsId = $checkSettings->fetch_assoc()['id'];
            $query = "UPDATE api_settings SET 
                        debug_mode = $apiDebugMode,
                        rate_limit = $apiRateLimit,
                        api_version = '$apiVersion',
                        auth_token = '$apiAuthToken',
                        updated_at = NOW()
                        WHERE id = $settingsId";
        } else {
            // Insert new settings
            $query = "INSERT INTO api_settings (debug_mode, rate_limit, api_version, auth_token, created_at, updated_at)
                      VALUES ($apiDebugMode, $apiRateLimit, '$apiVersion', '$apiAuthToken', NOW(), NOW())";
        }
        
        if ($conn->query($query)) {
            $message = "API settings updated successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to update API settings: " . $conn->error;
            $messageType = "danger";
        }
    }
    
    // Process log clearing if requested
    if ($_POST['action'] === 'clear_logs' && $userRole === 'super_admin') {
        $logType = $conn->real_escape_string($_POST['log_type']);
        
        if ($logType === 'all') {
            $query = "DELETE FROM system_logs";
        } else {
            $query = "DELETE FROM system_logs WHERE log_type = '$logType'";
        }
        
        if ($conn->query($query)) {
            $message = "Logs cleared successfully.";
            $messageType = "success";
        } else {
            $message = "Failed to clear logs: " . $conn->error;
            $messageType = "danger";
        }
    }
}

// Fetch API settings
$apiSettings = [
    'debug_mode' => 0,
    'rate_limit' => 100,
    'api_version' => '1.0',
    'auth_token' => '',
];

$getApiSettings = $conn->query("SELECT * FROM api_settings LIMIT 1");
if ($getApiSettings && $getApiSettings->num_rows > 0) {
    $apiSettings = $getApiSettings->fetch_assoc();
}

// Get log type filter
$logType = isset($_GET['log_type']) ? $_GET['log_type'] : 'all';
$validLogTypes = ['all', 'error', 'warning', 'info', 'debug', 'api'];

if (!in_array($logType, $validLogTypes)) {
    $logType = 'all';
}

// Build SQL query for logs
if ($logType === 'all') {
    $logQuery = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 1000";
} else {
    $logType = $conn->real_escape_string($logType);
    $logQuery = "SELECT * FROM system_logs WHERE log_type = '$logType' ORDER BY created_at DESC LIMIT 1000";
}

// Execute query
$logs = [];
$result = $conn->query($logQuery);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Count logs by type
$logCounts = [];
$countQuery = "SELECT log_type, COUNT(*) as count FROM system_logs GROUP BY log_type";
$countResult = $conn->query($countQuery);

if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $logCounts[$row['log_type']] = $row['count'];
    }
}

// Total log count
$totalLogs = array_sum($logCounts);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">System Logs</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">System Logs</li>
    </ol>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-4" id="sysTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab" aria-controls="logs" aria-selected="true">
                <i class="fas fa-list me-1"></i> System Logs
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="api-settings-tab" data-bs-toggle="tab" data-bs-target="#api-settings" type="button" role="tab" aria-controls="api-settings" aria-selected="false">
                <i class="fas fa-cogs me-1"></i> API Settings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="test-api-tab" data-bs-toggle="tab" data-bs-target="#test-api" type="button" role="tab" aria-controls="test-api" aria-selected="false">
                <i class="fas fa-vial me-1"></i> Test API
            </button>
        </li>
    </ul>
    
    <!-- Tab content -->
    <div class="tab-content" id="sysTabContent">
        <!-- Logs Tab -->
        <div class="tab-pane fade show active" id="logs" role="tabpanel" aria-labelledby="logs-tab">
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Total Logs</div>
                                    <div class="text-lg fw-bold"><?php echo $totalLogs; ?></div>
                                </div>
                                <i class="fas fa-file-alt fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Errors</div>
                                    <div class="text-lg fw-bold"><?php echo $logCounts['error'] ?? 0; ?></div>
                                </div>
                                <i class="fas fa-exclamation-circle fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">Warnings</div>
                                    <div class="text-lg fw-bold"><?php echo $logCounts['warning'] ?? 0; ?></div>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <div class="text-white-75 small">API Logs</div>
                                    <div class="text-lg fw-bold"><?php echo $logCounts['api'] ?? 0; ?></div>
                                </div>
                                <i class="fas fa-code fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-list me-1"></i> System Logs
                    </div>
                    <div class="btn-group">
                        <a href="?page=system_logs&log_type=all" class="btn btn-sm <?php echo $logType === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                        <a href="?page=system_logs&log_type=error" class="btn btn-sm <?php echo $logType === 'error' ? 'btn-danger' : 'btn-outline-danger'; ?>">Errors</a>
                        <a href="?page=system_logs&log_type=warning" class="btn btn-sm <?php echo $logType === 'warning' ? 'btn-warning' : 'btn-outline-warning'; ?>">Warnings</a>
                        <a href="?page=system_logs&log_type=info" class="btn btn-sm <?php echo $logType === 'info' ? 'btn-success' : 'btn-outline-success'; ?>">Info</a>
                        <a href="?page=system_logs&log_type=api" class="btn btn-sm <?php echo $logType === 'api' ? 'btn-info' : 'btn-outline-info'; ?>">API</a>
                    </div>
                    <?php if ($userRole === 'super_admin'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to clear these logs? This action cannot be undone.');">
                        <input type="hidden" name="action" value="clear_logs">
                        <input type="hidden" name="log_type" value="<?php echo $logType; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash me-1"></i> Clear <?php echo ucfirst($logType); ?> Logs
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="logsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Message</th>
                                    <th>User</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No logs found</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['log_id']; ?></td>
                                    <td>
                                        <?php 
                                        switch($log['log_type']) {
                                            case 'error':
                                                echo '<span class="badge bg-danger">Error</span>';
                                                break;
                                            case 'warning':
                                                echo '<span class="badge bg-warning text-dark">Warning</span>';
                                                break;
                                            case 'info':
                                                echo '<span class="badge bg-success">Info</span>';
                                                break;
                                            case 'debug':
                                                echo '<span class="badge bg-secondary">Debug</span>';
                                                break;
                                            case 'api':
                                                echo '<span class="badge bg-info">API</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-primary">' . ucfirst($log['log_type']) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['message']); ?></td>
                                    <td><?php echo $log['user_id'] ? 'User #' . $log['user_id'] : 'System'; ?></td>
                                    <td><?php echo $log['ip_address']; ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- API Settings Tab -->
        <div class="tab-pane fade" id="api-settings" role="tabpanel" aria-labelledby="api-settings-tab">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-cogs me-1"></i> API Configuration
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="update_api_settings">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="api_debug_mode" name="api_debug_mode" <?php echo $apiSettings['debug_mode'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="api_debug_mode">Debug Mode</label>
                                    <div class="form-text">When enabled, API endpoints will return detailed error information.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="api_rate_limit" class="form-label">Rate Limit (requests per minute)</label>
                                    <input type="number" class="form-control" id="api_rate_limit" name="api_rate_limit" value="<?php echo $apiSettings['rate_limit']; ?>" min="1" max="1000">
                                    <div class="form-text">Maximum number of API requests allowed per minute per client.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="api_version" class="form-label">API Version</label>
                                    <input type="text" class="form-control" id="api_version" name="api_version" value="<?php echo $apiSettings['api_version']; ?>">
                                    <div class="form-text">Current API version (e.g., 1.0, 2.1).</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="api_auth_token" class="form-label">Default API Auth Token</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="api_auth_token" name="api_auth_token" value="<?php echo $apiSettings['auth_token']; ?>">
                                        <button type="button" class="btn btn-outline-secondary" id="generateToken">
                                            <i class="fas fa-sync-alt"></i> Generate
                                        </button>
                                    </div>
                                    <div class="form-text">Default authentication token for API access.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save API Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- API Documentation Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-book me-1"></i> API Documentation
                </div>
                <div class="card-body">
                    <p>This section contains information about available API endpoints and how to use them.</p>
                    
                    <div class="mb-4">
                        <h5>Authentication</h5>
                        <p>All API requests require an authentication token passed in the header:</p>
                        <pre><code>Authorization: Bearer YOUR_API_TOKEN</code></pre>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Available Endpoints</h5>
                        
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <strong>GET /api/bookings/get_details.php</strong>
                            </div>
                            <div class="card-body">
                                <p>Retrieve detailed information about a specific booking.</p>
                                <p><strong>Parameters:</strong></p>
                                <ul>
                                    <li><code>booking_id</code> (required) - The ID of the booking</li>
                                </ul>
                                <p><strong>Example:</strong></p>
                                <pre><code>GET /api/bookings/get_details.php?booking_id=123</code></pre>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-light">
                                <strong>GET /api/bookings/list.php</strong>
                            </div>
                            <div class="card-body">
                                <p>List all bookings with optional filtering.</p>
                                <p><strong>Parameters:</strong></p>
                                <ul>
                                    <li><code>status</code> (optional) - Filter by booking status</li>
                                    <li><code>limit</code> (optional) - Maximum number of results to return</li>
                                    <li><code>offset</code> (optional) - Number of results to skip</li>
                                </ul>
                                <p><strong>Example:</strong></p>
                                <pre><code>GET /api/bookings/list.php?status=confirmed&limit=10&offset=0</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test API Tab -->
        <div class="tab-pane fade" id="test-api" role="tabpanel" aria-labelledby="test-api-tab">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-vial me-1"></i> API Test Tool
                </div>
                <div class="card-body">
                    <p>Use this interface to test API endpoints directly from the browser.</p>
                    <a href="test_api.php" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i> Open API Test Tool
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTables for logs
    $(document).ready(function() {
        $('#logsTable').DataTable({
            order: [[0, 'desc']], // Sort by ID descending
            pageLength: 25,
            lengthMenu: [25, 50, 100, 250, 500],
            responsive: true
        });
        
        // Generate random API token
        $('#generateToken').click(function() {
            const tokenLength = 32;
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let token = '';
            
            for (let i = 0; i < tokenLength; i++) {
                token += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            $('#api_auth_token').val(token);
        });
    });
</script> 