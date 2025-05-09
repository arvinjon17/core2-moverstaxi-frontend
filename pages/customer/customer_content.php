<?php
/**
 * Customer Content
 * Displays customer-specific information for the dashboard
 */

// Add CSS styles for status badges
echo '
<style>
    /* Status badge styles */
    .bg-success {
        background-color: #28a745 !important;
        color: white;
    }
    .bg-warning {
        background-color: #ffc107 !important;
        color: #212529;
    }
    .bg-secondary {
        background-color: #6c757d !important;
        color: white;
    }
    .bg-danger {
        background-color: #dc3545 !important;
        color: white;
    }
    
    /* Badge styling */
    .badge {
        display: inline-block;
        padding: 0.25em 0.4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }
</style>
';

// Ensure this file isn't accessed directly
if (!defined('BASE_PATH')) {
    // If accessed directly, check if the user is logged in and has permission to manage customers
    session_start();
    
    // Check if we're viewing a specific customer from the customer management page
    $isManagementView = false;
    $specificCustomerId = 0;
    
    // If user_id parameter is provided, we're viewing a specific customer's details
    if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
        // Check if the current user is logged in
        if (isset($_SESSION['user_id'])) {
            // Load auth functions if not already loaded
            if (!function_exists('hasPermission')) {
                require_once '../../functions/auth.php';
            }
            
            // Check if the user has permission to manage customers
            if (function_exists('hasPermission') && hasPermission('manage_customers')) {
                $specificCustomerId = (int)$_GET['user_id'];
                $isManagementView = true;
            }
        }
    }
    
    // If not accessing as management and not a customer, deny access
    if (!$isManagementView) {
        // Only check for customer role if not in management view
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            echo '<div class="alert alert-danger">Unauthorized access. Please log in as a customer or administrator.</div>';
            exit;
        }
    }
    
    // Determine the include path based on how the file is being accessed
    $includeBasePath = '';
    
    // Check if being accessed directly or through index.php
    if (strpos($_SERVER['PHP_SELF'], '/pages/customer/') !== false) {
        $includeBasePath = '../../';
    }
} else {
    // If BASE_PATH is defined, use it
    $includeBasePath = BASE_PATH;
    $isManagementView = isset($isManagementView) && $isManagementView;
    $specificCustomerId = isset($specificCustomerId) ? $specificCustomerId : 0;
}

// Default include path if nothing else worked
if (empty($includeBasePath)) {
    $includeBasePath = '../../';
}

// Database connection
require_once $includeBasePath . 'functions/db.php';

// Include profile image functions
require_once $includeBasePath . 'functions/profile_images.php';

// Get current user ID from session
if ($isManagementView && $specificCustomerId > 0) {
    // We're viewing a specific customer as an admin/manager
    $userId = $specificCustomerId;
    
    // Get the customer info from database
    $conn = connectToCore2DB();
    if ($conn) {
        $query = "SELECT firstname, lastname FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $userData = $result->fetch_assoc();
                $firstname = $userData['firstname'];
                $lastname = $userData['lastname'];
                $fullName = "{$firstname} {$lastname}";
            }
            $stmt->close();
        }
        // Don't close the connection here, we'll reuse it later
    }
} else {
    // Regular customer viewing their own dashboard
    $userId = $_SESSION['user_id'] ?? 0;
    $firstname = $_SESSION['user_firstname'] ?? '';
    $lastname = $_SESSION['user_lastname'] ?? '';
    $fullName = $_SESSION['user_full_name'] ?? "{$firstname} {$lastname}";
}

// For debugging
error_log("Loading customer_content.php for user: {$userId} - " . ($fullName ?? 'Unknown'));

// Fetch customer details from the database
$customerData = [];
$bookingCount = 0;
$walletBalance = 0;
$recentBookings = []; // Array to store recent bookings

// Connect to the database for Core2
$conn2 = connectToCore2DB();
if ($conn2) {
    try {
        // First, get the basic user info from core2_movers
        $query2 = "SELECT 
            user_id, 
            firstname, 
            lastname, 
            email, 
            phone, 
            status, 
            last_login
        FROM 
            users 
        WHERE 
            user_id = ? 
            AND role = 'customer'";
        
        $stmt2 = $conn2->prepare($query2);
        
        if (!$stmt2) {
            throw new Exception("Failed to prepare user query: " . $conn2->error);
        }
        
        $stmt2->bind_param('i', $userId);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        if ($result2 && $result2->num_rows > 0) {
            $customerData = $result2->fetch_assoc();
            $stmt2->close();
            
            // Now get the additional customer data from core1_movers
            $conn1 = connectToCore1DB();
            
            if ($conn1) {
                $query1 = "SELECT 
                    customer_id,
                    address, 
                    city, 
                    state, 
                    zip,
                    status
                FROM 
                    customers 
                WHERE 
                    user_id = ?";
                
                $stmt1 = $conn1->prepare($query1);
                
                if (!$stmt1) {
                    throw new Exception("Failed to prepare customer query: " . $conn1->error);
                }
                
                $stmt1->bind_param('i', $userId);
                $stmt1->execute();
                $result1 = $stmt1->get_result();
                
                if ($result1 && $result1->num_rows > 0) {
                    $customerExtras = $result1->fetch_assoc();
                    // Merge the results
                    $customerData = array_merge($customerData, $customerExtras);
                    
                    // Now count bookings using the customer_id
                    $customer_id = $customerExtras['customer_id'];
                    
                    $bookingCountQuery = "SELECT 
                        COUNT(*) AS total_bookings
                    FROM 
                        core1_movers2.bookings 
                    WHERE 
                        customer_id = ?";
                    
                    $bookingStmt = $conn2->prepare($bookingCountQuery);
                    
                    if ($bookingStmt) {
                        $bookingStmt->bind_param('i', $customer_id);
                        $bookingStmt->execute();
                        $bookingResult = $bookingStmt->get_result();
                        
                        if ($bookingResult && $bookingResult->num_rows > 0) {
                            $bookingRow = $bookingResult->fetch_assoc();
                            $bookingCount = $bookingRow['total_bookings'];
                        }
                        
                        $bookingStmt->close();
                        
                        // Get recent bookings if there are any
                        if ($bookingCount > 0) {
                            $recentBookingsQuery = "SELECT 
                                booking_id, 
                                pickup_time AS booking_date, 
                                pickup_location, 
                                dropoff_location, 
                                booking_status AS status, 
                                fare_amount
                            FROM 
                                core1_movers2.bookings 
                            WHERE 
                                customer_id = ?
                            ORDER BY 
                                pickup_time DESC
                            LIMIT 5";
                            
                            $recentStmt = $conn2->prepare($recentBookingsQuery);
                            
                            if ($recentStmt) {
                                $recentStmt->bind_param('i', $customer_id);
                                $recentStmt->execute();
                                $recentResult = $recentStmt->get_result();
                                
                                if ($recentResult) {
                                    while ($booking = $recentResult->fetch_assoc()) {
                                        $recentBookings[] = $booking;
                                    }
                                }
                                
                                $recentStmt->close();
                            }
                        }
                    }
                } else {
                    error_log("No customer data found in core1_movers for user ID: {$userId}");
                }
                
                $stmt1->close();
                $conn1->close();
            } else {
                error_log("Failed to connect to core1_movers database");
            }
        } else {
            error_log("No customer data found in core1_movers2 for user ID: {$userId}");
        }
    } catch (Exception $e) {
        error_log("Error fetching customer data: " . $e->getMessage());
    } finally {
        if ($conn2) {
            $conn2->close();
        }
    }
} else {
    error_log("Failed to connect to core1_movers2 database for customer data");
}

// Get the customer's profile image URL
$profileImageUrl = getUserProfileImageUrl($userId, 'customer', $customerData['firstname'] ?? '', $customerData['lastname'] ?? '');

// Add meta refresh to force page reload if needed for DataTable
if (isset($_GET['refresh']) && $_GET['refresh'] == 'true') {
    echo '<meta http-equiv="refresh" content="1;url=' . $_SERVER['PHP_SELF'] . '?page=customers">';
    echo '<p class="text-center">Refreshing page...</p>';
}

// Determine display mode based on viewing context
$compactView = $isManagementView; // Use compact view for modal in management page
?>

<!-- Customer content starts here -->
<?php if ($compactView): // Compact view for modal display ?>

<style>
    /* Additional styles for modern customer details modal */
    .customer-profile-modal {
        padding: 10px;
    }
    
    .customer-profile-img {
        border: 4px solid #f8f9fa;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .customer-info-card {
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 15px;
    }
    
    .info-group {
        padding: 8px 15px;
        margin-bottom: 8px;
        background-color: #f8f9fa;
        border-radius: 8px;
    }
    
    .info-label {
        color: #6c757d;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 3px;
    }
    
    .info-value {
        font-weight: 500;
    }
    
    .status-container {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .status-item {
        flex: 1;
        min-width: 120px;
    }
</style>

<div class="customer-profile-modal">
    <div class="row mb-4">
        <div class="col-lg-4 col-md-5 text-center mb-3">
            <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile Image" class="img-fluid rounded-circle customer-profile-img" style="max-width: 160px;">
            <h4 class="mt-3 mb-1"><?php echo htmlspecialchars($fullName); ?></h4>
            <p class="text-muted">Customer #<?php echo $customerData['user_id'] ?? 'N/A'; ?></p>
        </div>
        <div class="col-lg-8 col-md-7">
            <div class="customer-info-card card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="fas fa-user-circle me-2"></i>Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label"><i class="fas fa-envelope me-1"></i> Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($customerData['email'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label"><i class="fas fa-phone me-1"></i> Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($customerData['phone'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Address</div>
                        <div class="info-value">
                        <?php 
                            $addressParts = [];
                            if (!empty($customerData['address'])) $addressParts[] = $customerData['address'];
                            if (!empty($customerData['city'])) $addressParts[] = $customerData['city'];
                            if (!empty($customerData['state'])) $addressParts[] = $customerData['state'];
                            if (!empty($customerData['zip'])) $addressParts[] = $customerData['zip'];
                            
                            echo !empty($addressParts) ? htmlspecialchars(implode(', ', $addressParts)) : 'N/A';
                        ?>
                        </div>
                    </div>
                    
                    <div class="status-container my-3">
                        <div class="status-item">
                            <div class="info-label"><i class="fas fa-user-shield me-1"></i> Account Status</div>
                            <?php 
                            // Account status from core2_movers.users
                            $accountStatus = isset($customerData['status']) ? $customerData['status'] : 'inactive';
                            $accountStatusClass = '';
                            
                            switch ($accountStatus) {
                                case 'active':
                                    $accountStatusClass = 'success';
                                    break;
                                case 'suspended':
                                    $accountStatusClass = 'danger';
                                    break;
                                case 'inactive':
                                default:
                                    $accountStatusClass = 'secondary';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $accountStatusClass; ?> px-3 py-2">
                                <i class="fas fa-<?php echo $accountStatus === 'active' ? 'check-circle' : ($accountStatus === 'suspended' ? 'ban' : 'times-circle'); ?> me-1"></i>
                                <?php echo ucfirst($accountStatus); ?>
                            </span>
                        </div>
                        
                        <div class="status-item">
                            <div class="info-label"><i class="fas fa-signal me-1"></i> Activity Status</div>
                            <?php 
                            // Activity status from core1_movers.customers
                            $activityStatus = $customerData['status'] ?? 'offline';
                            $activityStatusClass = '';
                            $activityStatusIcon = '';
                            
                            switch ($activityStatus) {
                                case 'online':
                                    $activityStatusClass = 'success';
                                    $activityStatusIcon = 'circle';
                                    break;
                                case 'busy':
                                    $activityStatusClass = 'warning';
                                    $activityStatusIcon = 'hourglass-half';
                                    break;
                                case 'offline':
                                default:
                                    $activityStatusClass = 'secondary';
                                    $activityStatusIcon = 'power-off';
                                    $activityStatus = 'offline';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $activityStatusClass; ?> px-3 py-2">
                                <i class="fas fa-<?php echo $activityStatusIcon; ?> me-1"></i>
                                <?php echo ucfirst($activityStatus); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label"><i class="fas fa-shopping-cart me-1"></i> Total Bookings</div>
                                <div class="info-value"><span class="badge bg-info px-3 py-2"><?php echo $bookingCount; ?></span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label"><i class="fas fa-wallet me-1"></i> Wallet Balance</div>
                                <div class="info-value"><span class="badge bg-primary px-3 py-2">₱<?php echo number_format($walletBalance, 2); ?></span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label"><i class="fas fa-clock me-1"></i> Last Login</div>
                        <div class="info-value">
                            <?php 
                            $lastLogin = $customerData['last_login'] ?? null;
                            echo $lastLogin ? date('M d, Y H:i', strtotime($lastLogin)) : 'Never'; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (count($recentBookings) > 0): ?>
    <div class="card customer-info-card">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Recent Bookings</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Fare</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $booking): ?>
                        <tr>
                            <td><span class="fw-bold">#<?php echo $booking['booking_id']; ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    switch ($booking['status']) {
                                        case 'completed': echo 'success'; break;
                                        case 'cancelled': echo 'danger'; break;
                                        case 'in_progress': echo 'primary'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <i class="fas fa-<?php 
                                    switch ($booking['status']) {
                                        case 'completed': echo 'check-circle'; break;
                                        case 'cancelled': echo 'times-circle'; break;
                                        case 'in_progress': echo 'spinner'; break;
                                        default: echo 'clock';
                                    }
                                    ?> me-1"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                </span>
                            </td>
                            <td><span class="fw-bold">₱<?php echo number_format($booking['fare_amount'], 2); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php else: // Full dashboard view for customer ?>

<div class="customer-dashboard">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile Image" class="img-fluid rounded-circle mb-3" style="max-width: 150px;">
                    <h5 class="card-title"><?php echo htmlspecialchars($fullName); ?></h5>
                    <p class="card-text">Customer</p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($customerData['email'] ?? 'N/A'); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($customerData['phone'] ?? 'N/A'); ?></p>
                            <p><strong>Address:</strong> <?php 
                                $addressParts = [];
                                if (!empty($customerData['address'])) $addressParts[] = $customerData['address'];
                                if (!empty($customerData['city'])) $addressParts[] = $customerData['city'];
                                if (!empty($customerData['state'])) $addressParts[] = $customerData['state'];
                                if (!empty($customerData['zip'])) $addressParts[] = $customerData['zip'];
                                
                                echo !empty($addressParts) ? htmlspecialchars(implode(', ', $addressParts)) : 'N/A';
                            ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Bookings:</strong> <?php echo $bookingCount; ?></p>
                            <p><strong>Wallet Balance:</strong> $<?php echo number_format($walletBalance, 2); ?></p>
                            <p><strong>Status:</strong> 
                                <?php 
                                // Get customer status from core1_movers.customers
                                $status = $customerData['status'] ?? 'offline';
                                $statusClass = '';
                                
                                // Set the appropriate badge class based on status
                                switch ($status) {
                                    case 'online':
                                        $statusClass = 'success';
                                        break;
                                    case 'busy':
                                        $statusClass = 'warning';
                                        break;
                                    case 'offline':
                                        $statusClass = 'secondary';
                                        break;
                                    default:
                                        $statusClass = 'secondary';
                                        $status = 'offline';
                                }
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Bookings</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recentBookings) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Date</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Status</th>
                                    <th>Fare</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['booking_id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                                    <td title="<?php echo htmlspecialchars($booking['pickup_location']); ?>">
                                        <?php echo htmlspecialchars(strlen($booking['pickup_location']) > 20 ? substr($booking['pickup_location'], 0, 20) . '...' : $booking['pickup_location']); ?>
                                    </td>
                                    <td title="<?php echo htmlspecialchars($booking['dropoff_location']); ?>">
                                        <?php echo htmlspecialchars(strlen($booking['dropoff_location']) > 20 ? substr($booking['dropoff_location'], 0, 20) . '...' : $booking['dropoff_location']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch ($booking['status']) {
                                                case 'completed': echo 'success'; break;
                                                case 'cancelled': echo 'danger'; break;
                                                case 'in_progress': echo 'primary'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($booking['fare_amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center">No recent bookings to display.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
<!-- Customer content ends here --> 

<?php
// Remove entire closing block since connections are already closed earlier
?> 