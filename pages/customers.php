<?php
// Customer Management Page
// Include db.php if it's not already included
if (!function_exists('connectToCore2DB')) {
    require_once 'functions/db.php';
}

// Ensure auth is included
if (!function_exists('hasPermission')) {
    require_once 'functions/auth.php';
}

// Explicitly check for auth and permissions
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Please log in to access this page.</div>';
    exit;
}

if (!hasPermission('manage_customers')) {
    // For debugging permission issues
    if (function_exists('debugPermission')) {
        debugPermission('manage_customers');
    }
    
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    exit;
}

// See if we need to refresh due to cache issues
if (isset($_GET['refresh']) && $_GET['refresh'] == 'true') {
    // This is a refresh request - log it
    error_log("Customer page refreshed to address DataTable display issues");
}

// Initialize variables
$message = '';
$messageType = '';
$customers = [];
$error = '';

// Check for session message (e.g., after fixing orphaned customers)
if (isset($_SESSION['message']) && !empty($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    // Clear the session message to avoid showing it again on refresh
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get customer data without cross-database joins
try {
    // Check for 'orphaned' customers - users with role 'customer' that don't have records in core1_movers.customers
    // Avoid cross-database queries by fetching data separately and comparing in PHP
    
    // Step 1: Get all customers from core2_movers.users
    $customerUsersQuery = "SELECT user_id FROM users WHERE role = 'customer'";
    $customerUsers = getRows($customerUsersQuery, 'core2');
    
    // Step 2: Get all customer records from core1_movers.customers
    $existingCustomersQuery = "SELECT user_id FROM customers";
    $existingCustomers = getRows($existingCustomersQuery, 'core1');
    
    // Step 3: Create lookup array of existing customer user_ids
    $existingCustomerIds = [];
    foreach ($existingCustomers as $customer) {
        $existingCustomerIds[$customer['user_id']] = true;
    }
    
    // Step 4: Find orphaned customers (in users table but not in customers table)
    $orphanedCount = 0;
    foreach ($customerUsers as $user) {
        if (!isset($existingCustomerIds[$user['user_id']])) {
            $orphanedCount++;
        }
    }
    
    // Display message if orphaned customers found
    if ($orphanedCount > 0) {
        $message = "Found {$orphanedCount} customers in core2_movers.users that don't have records in core1_movers.customers. 
                  <a href='api/customers/fix_orphaned.php' class='alert-link'>Click here to fix</a>";
        $messageType = "warning";
        error_log("Found {$orphanedCount} orphaned customers");
    }
    
    // Step 1: Get basic customer data from core1 database
    $customersQuery = "SELECT 
        customer_id, user_id, address, city, state, zip, notes, status,
        created_at, updated_at
    FROM customers
    ORDER BY created_at DESC";
    
    error_log("Executing customers query: $customersQuery");
    $customersData = getRows($customersQuery, 'core1');
    $customerCount = count($customersData);
    error_log("Found $customerCount basic customers");
    
    // Initialize the combined customers array
    $customers = [];
    
    // If we have basic customer data, fetch the additional details separately
    if (!empty($customersData)) {
        // Process each customer
        foreach ($customersData as $customer) {
            $completeCustomer = $customer;
            
            // Step 2: Get user data if available
            if (!empty($customer['user_id'])) {
                $userId = (int)$customer['user_id'];
                $userQuery = "SELECT 
                    firstname, lastname, email, phone, role, status, 
                    last_login
                    FROM users 
                    WHERE user_id = $userId";
                $userData = getRows($userQuery, 'core2');
                
                if (!empty($userData[0])) {
                    // Add user data to the complete customer
                    $completeCustomer['firstname'] = $userData[0]['firstname'];
                    $completeCustomer['lastname'] = $userData[0]['lastname'];
                    $completeCustomer['email'] = $userData[0]['email'];
                    $completeCustomer['phone'] = $userData[0]['phone'];
                    $completeCustomer['role'] = $userData[0]['role'];
                    $completeCustomer['user_status'] = $userData[0]['status'];
                    $completeCustomer['last_login'] = $userData[0]['last_login'] ?? null;
                    // Map status field to account_status for compatibility with existing code
                    $completeCustomer['account_status'] = $userData[0]['status'] ?? 'inactive';
                }
            }
            
            // Step 3: Get booking count for this customer
            $customerId = (int)$customer['customer_id'];
            $bookingCountQuery = "SELECT 
                COUNT(*) as booking_count
                FROM bookings 
                WHERE customer_id = $customerId";
            $bookingCountData = getRows($bookingCountQuery, 'core2');
            
            if (!empty($bookingCountData[0])) {
                $completeCustomer['booking_count'] = $bookingCountData[0]['booking_count'];
            } else {
                $completeCustomer['booking_count'] = 0;
            }
            
            // Add the complete customer to the customers array
            $customers[] = $completeCustomer;
        }
    }
    
    $customerCount = count($customers);
    error_log("Final combined customers count: $customerCount");
    
    if (empty($customers)) {
        error_log("No customers found in the final combined result");
    } else {
        // Log the first customer for debugging
        error_log("First combined customer: " . json_encode($customers[0]));
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Customers query failed in customers.php: " . $error);
    echo '<div class="alert alert-danger">Error fetching customers: ' . $error . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - CORE Movers</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        /* Improved styling for customer table */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .activity-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .activity-dot-online {
            background-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
        }
        
        .activity-dot-busy {
            background-color: #ffc107;
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.2);
        }
        
        .activity-dot-offline {
            background-color: #6c757d;
            box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.2);
        }
        
        .view-btn-modern {
            border-radius: 20px;
            transition: all 0.2s;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .view-btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-badges-container {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .modern-view-btn {
            background-color: #3498db;
            border: none;
            border-radius: 50px;
            color: white;
            padding: 5px 12px;
            font-size: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .modern-view-btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.5);
        }

        .modern-view-btn i {
            margin-right: 5px;
        }

        .status-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 100px; /* Ensure enough width for status badges */
            overflow: visible; /* Don't cut off content */
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap; /* Prevent text from wrapping */
        }

        .account-status {
            border: 1px solid #ddd;
        }

        .active-badge {
            background-color: #e8f7ee;
            color: #28a745;
        }

        .inactive-badge {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .suspended-badge {
            background-color: #feecef;
            color: #dc3545;
        }

        .activity-status {
            display: inline-flex;
            align-items: center;
            white-space: nowrap; /* Prevent text from wrapping */
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .online-dot {
            background-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
        }

        .busy-dot {
            background-color: #ffc107;
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.2);
        }

        .offline-dot {
            background-color: #6c757d;
            box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.2);
        }
    </style>
</head>
<body>
    <main class="content">
<div class="container-fluid px-4">
    <h1 class="mt-4">Customer Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Customer Management</li>
    </ol>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Debug information to verify data -->
    <?php if (empty($customers)): ?>
        <div class="alert alert-warning">
                    No customers found in the database. Please check if any users with the role 'customer' exist in both core1_movers and core2_movers databases.
        </div>
    <?php else: ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
                    Found <?php echo count($customers); ?> customers in the cross-database query.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Customer List
        </div>
        <div class="card-body">
            <table id="customersTable" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Profile</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <?php 
                            // Get profile image URL using new function signature
                            $profileImageUrl = getUserProfileImageUrl(
                                $customer['user_id'], 
                                'customer', 
                                $customer['firstname'], 
                                $customer['lastname']
                            );
                            
                            // Debug each customer
                            error_log("Customer for DataTable: ID={$customer['user_id']}, Name={$customer['firstname']} {$customer['lastname']}, Role=customer");
                        ?>
                        <tr data-customer-id="<?php echo $customer['user_id']; ?>">
                            <td><?php echo $customer['user_id']; ?></td>
                            <td class="text-center">
                                <?php if ($profileImageUrl && $profileImageUrl !== 'assets/img/default_user.jpg' && file_exists($profileImageUrl)): ?>
                                    <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; margin: 0 auto;">
                                        <?php echo strtoupper(substr($customer['firstname'], 0, 1) . substr($customer['lastname'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td>
                                <?php 
                                    $address = $customer['address'] ?? '';
                                    $city = $customer['city'] ?? '';
                                    $state = $customer['state'] ?? '';
                                    $zip = $customer['zip'] ?? '';
                                    
                                    $fullAddress = trim($address);
                                    
                                    if (!empty($city) || !empty($state) || !empty($zip)) {
                                        $cityStateZip = trim("{$city}, {$state} {$zip}", ", ");
                                        if (!empty($fullAddress) && !empty($cityStateZip)) {
                                            $fullAddress .= ', ' . $cityStateZip;
                                        } else {
                                            $fullAddress .= $cityStateZip;
                                        }
                                    }
                                    
                                    echo htmlspecialchars($fullAddress ?: 'No address provided');
                                ?>
                            </td>
                            <td>
                                <div class="status-container">
                                    <div class="status-badge account-status 
                                        <?php 
                                        // Account status from users table
                                        $accountStatus = $customer['account_status'] ?? 'inactive';
                                        if ($accountStatus == 'active') echo 'active-badge';
                                        else if ($accountStatus == 'inactive') echo 'inactive-badge';
                                        else if ($accountStatus == 'suspended') echo 'suspended-badge';
                                        ?>">
                                        <i class="fas fa-lock me-1"></i>
                                        <?php echo $accountStatus ? ucfirst($accountStatus) : 'Unknown'; ?>
                                    </div>
                                    <div class="status-badge activity-status">
                                        <span class="status-dot 
                                            <?php 
                                            // Activity status from customers table
                                            $activityStatus = $customer['activity_status'] ?? 'offline';
                                            if ($activityStatus == 'online') echo 'online-dot';
                                            else if ($activityStatus == 'busy') echo 'busy-dot';
                                            else echo 'offline-dot';
                                            ?>"></span>
                                        <?php echo $activityStatus ? ucfirst($activityStatus) : 'Offline'; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo isset($customer['last_login']) && $customer['last_login'] ? date('M d, Y H:i', strtotime($customer['last_login'])) : 'Never'; ?>
                            </td>
                            <td>
                                        <div class="d-flex flex-column flex-md-row gap-1">
                                    <button type="button" class="modern-view-btn view-customer-btn" data-id="<?php echo $customer['user_id']; ?>" data-bs-toggle="modal" data-bs-target="#customerDetailsModal">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info edit-customer-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editCustomerModal" 
                                                    data-id="<?php echo $customer['user_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if (($customer['account_status'] ?? 'inactive') === 'active'): ?>
                                            <button type="button" class="btn btn-sm btn-warning deactivate-customer-btn" 
                                                    data-id="<?php echo $customer['user_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?>"
                                                    title="Deactivate Customer">
                                                <i class="fas fa-user-slash"></i> Deactivate
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-success activate-customer-btn" 
                                                    data-id="<?php echo $customer['user_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?>"
                                                    title="Activate Customer">
                                                <i class="fas fa-user-check"></i> Activate
                                </button>
                                            <?php endif; ?>
                                        </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    </main>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="customerDetailsModalLabel">
                    <i class="fas fa-user me-2"></i> Customer Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="progress" style="height: 4px;">
                <div class="progress-bar progress-bar-striped bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary edit-customer-btn">
                    <i class="fas fa-edit me-1"></i> Edit
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCustomerModalLabel">Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editCustomerContent" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading customer edit form...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCustomerBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Edit Customer Modal -->
    <div class="modal fade" id="editCustomerNewModal" tabindex="-1" aria-labelledby="editCustomerNewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCustomerNewModalLabel">Edit Customer (New)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editCustomerNewContent" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading customer edit form...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCustomerNewBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer location modal -->
    <div class="modal fade" id="selectCustomerLocationModal" tabindex="-1" aria-labelledby="selectCustomerLocationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="selectCustomerLocationModalLabel">
                        <i class="fas fa-map-marker-alt me-2"></i> Select Customer Location
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Map column -->
                        <div class="col-lg-8 position-relative">
                            <div id="customerLocationMap" style="height: 500px; width: 100%;"></div>
                            <div class="map-controls position-absolute" style="top: 10px; right: 10px; z-index: 1;">
                                <button id="centerMapBtn" class="btn btn-light shadow-sm" title="Center Map">
                                    <i class="fas fa-crosshairs"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Customers list column -->
                        <div class="col-lg-4 border-start">
                            <div class="p-3 border-bottom">
                                <div class="input-group">
                                    <input type="text" id="customerSearchInput" class="form-control" placeholder="Search customers...">
                                    <button class="btn btn-primary" id="searchCustomerBtn" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="p-2 bg-light border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Customers (<span id="customerCount">0</span>)</span>
                                    <button class="btn btn-sm btn-outline-secondary reload-customers-btn">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="customer-list list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                                <!-- Customer list will be loaded here -->
                                <div class="list-group-item text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Loading customers...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="form-check me-auto">
                        <input class="form-check-input" type="checkbox" id="updateCustomerLocationCheck" checked>
                        <label class="form-check-label" for="updateCustomerLocationCheck">
                            Update customer location in database
                        </label>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCustomerLocationBtn" disabled>
                        <i class="fas fa-save me-1"></i> Save Location
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS libraries (jQuery and Bootstrap must be loaded before any other scripts) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS (after jQuery) - Using specific version known to work with Bootstrap 5 -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Fallback for DataTables if CDN fails -->
<script>
    if (typeof $.fn.DataTable !== 'function') {
        console.warn('DataTables not loaded from primary CDN, attempting to load from fallback...');
        document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"><\/script>');
        document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js"><\/script>');
}
</script>

    <!-- SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GMAP_API_KEY&libraries=places"></script>

    <!-- Customer Details Modal Script -->
    <script src="assets/js/customer-details-modal.js"></script>

<script>
    $(document).ready(function() {
        console.log('jQuery ready. Initializing customer management.');
        
        // Debug info for troubleshooting
        console.log('Document ready state:', document.readyState);
        console.log('jQuery version:', $.fn.jquery);
        console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? bootstrap.Modal.VERSION : 'not loaded');
        console.log('DataTables loaded:', typeof $.fn.DataTable === 'function');
        console.log('Customer details modal element exists:', $('#customerDetailsModal').length > 0);
        console.log('Edit customer modal element exists:', $('#editCustomerModal').length > 0);
        
        // Edit Customer Modal logic
        $(document).on('click', '.edit-customer-btn', function() {
            const customerId = $(this).data('id');
            $('#editCustomerContent').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading customer edit form...</p>
                </div>
            `);
            $.ajax({
                url: '/api/customers/edit_modal.php',
                data: { user_id: customerId },
                method: 'GET',
                success: function(response) {
                    $('#editCustomerContent').html(response);
                },
                error: function(xhr, status, error) {
                    $('#editCustomerContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading the edit form. Please try again.<br>
                            <div class="mt-2 small">Error details: ${error || 'Unknown error'}</div>
                        </div>
                    `);
                }
            });
        });
        
        // Save handler for edit customer modal
        $(document).on('click', '#saveCustomerBtn', function() {
            const $form = $('#editCustomerNewForm');
            if ($form.length === 0) {
                Swal.fire({
                    title: 'Error',
                    text: 'Edit form not found.',
                    icon: 'error'
                });
                return;
            }
            // Client-side validation for PH phone
            const phone = $form.find('#phone').val();
            const phRegex = /^(\+639|09)\d{9}$/;
            if (!phRegex.test(phone)) {
                $form.find('#phone').addClass('is-invalid');
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please enter a valid PH number (+639XXXXXXXXX or 09XXXXXXXXX)',
                    icon: 'error'
                });
                return;
            }
            // Validate required fields
            let missing = [];
            $form.find('[required]').each(function() {
                if (!$(this).val().trim()) missing.push($(this).attr('name'));
            });
            if (missing.length > 0) {
                Swal.fire({
                    title: 'Validation Error',
                    html: 'Please fill all required fields:<br>' + missing.join(', '),
                    icon: 'error'
                });
                return;
            }
            // Submit via AJAX using FormData for file upload
            var formData = new FormData($form[0]);
            $.ajax({
                url: 'api/customers/update_new.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            $('#editCustomerModal').modal('hide');
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while saving. Please try again.',
                        icon: 'error'
                    });
                }
            });
        });
        
        // Ensure jQuery and DataTables are available
        if (typeof $.fn.DataTable !== 'function') {
            console.error('DataTables library not loaded correctly!');
            
            // Try to recover by loading from another CDN
            $.getScript('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js')
                .done(function() {
                    console.log('Successfully loaded DataTables from alternate source');
                    // Now load the Bootstrap 5 integration
                    $.getScript('https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js')
                        .done(function() {
                            console.log('Successfully loaded DataTables Bootstrap 5 integration');
                            initializeDataTable();
                        })
                        .fail(function() {
                            console.error('Failed to load DataTables Bootstrap 5 integration');
                        });
                })
                .fail(function() {
                    console.error('Failed to load DataTables dynamically');
                    // Show error message
                    Swal.fire({
                        title: 'Error Loading DataTables',
                        html: 'Failed to load the DataTables library. Please refresh the page or contact support.',
                        icon: 'error'
                    });
                });
        } else {
            // DataTables is loaded, proceed with initialization
            initializeDataTable();
        }
        
        function initializeDataTable() {
            try {
                let customersTable = $('#customersTable').DataTable({
                    order: [[2, 'asc']], // Sort by name
                    pageLength: 10,
                    responsive: true,
                    columnDefs: [
                        { width: "120px", targets: 6 } // Set width for status column (index 6)
                    ]
                });
                console.log('DataTable initialized for customers table');
            } catch (error) {
                console.error('Error initializing DataTable:', error);
                Swal.fire({
                    title: 'DataTable Error',
                    html: 'Failed to initialize data table:<br><code>' + error.message + '</code><br><br>Try refreshing the page or check console for more details.',
                    icon: 'error'
                });
            }
        }
        
        // Check if required endpoints exist
        const requiredEndpoints = [
            'pages/customer/customer_content.php',
            'pages/customer/customer_edit_form.php',
            'api/customers/update_status.php',
            'api/customers/update.php',
            'api/get_customer_details.php'
        ];
        
        $.each(requiredEndpoints, function(index, endpoint) {
            // Just log - we're not actually making the request
            console.log('Required endpoint:', endpoint);
        });
        
        // Customer Details Modal functionality has been moved to customer-details-modal.js
        
        // Add tooltips to buttons
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Reset modal on close
        $('#customerDetailsModal').on('hidden.bs.modal', function () {
            const progressBar = $(this).find('.progress-bar');
            progressBar.css('width', '0%');
            progressBar.removeClass('bg-danger').addClass('bg-info');
        });

        // Handle activate/deactivate customer buttons
        $('.activate-customer-btn, .deactivate-customer-btn').on('click', function() {
            const customerId = $(this).data('id');
            const customerName = $(this).data('name');
            const action = $(this).hasClass('activate-customer-btn') ? 'activate' : 'deactivate';
            const newStatus = action === 'activate' ? 'active' : 'inactive';
            
            console.log(`${action} button clicked for customer ID ${customerId} (${customerName})`);
            
            // Show confirmation dialog
            Swal.fire({
                title: `${action === 'activate' ? 'Activate' : 'Deactivate'} Customer`,
                html: `Are you sure you want to ${action} <strong>${customerName}</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'activate' ? '#28a745' : '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: action === 'activate' ? 'Yes, Activate' : 'Yes, Deactivate',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        html: `<div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <p class="mt-2">Updating customer status...</p>`,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Send AJAX request to update customer status
                    $.ajax({
                        url: 'api/customers/update_status.php',
                        method: 'POST',
                        data: {
                            user_id: customerId,
                            status: newStatus,
                            action: action
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: `Customer has been ${action}d successfully.`,
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Reload the page to refresh the data
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: response.message || `Failed to ${action} customer.`,
                                    icon: 'error'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error(`Error ${action}ing customer:`, error, xhr.responseText);
                            Swal.fire({
                                title: 'Error',
                                text: `An error occurred while trying to ${action} the customer. Please try again.`,
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        });

        // Add profile image preview for customer edit modal (unified with driver logic)
        $(document).on('change', '#profile_picture', function() {
            const file = this.files[0];
            if (file) {
                // Validate file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'Profile picture must be less than 2MB.'
                    });
                    this.value = '';
                    return;
                }
                
                // Update image preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#profilePicPreview').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        // Add location update button to customer actions
        $('.edit-customer-btn').each(function() {
            const userId = $(this).data('id');
            const actionColumn = $(this).closest('td');
            
            // Add location button after edit button (before deactivate/activate button)
            $(this).after(`
                <button type="button" class="btn btn-sm btn-info update-location-btn ms-1" 
                        data-bs-toggle="modal" 
                        data-bs-target="#selectCustomerLocationModal" 
                        data-id="${userId}">
                    <i class="fas fa-map-marker-alt"></i> Location
                </button>
            `);
        });
        
        // Customer location functionality
        let customerLocationMap;
        let customerLocationMarker;
        let selectedCustomer = null;

        // Initialize the customer location modal
        $('#selectCustomerLocationModal').on('shown.bs.modal', function(e) {
            // Get the customer ID from the button that triggered the modal
            const customerId = $(e.relatedTarget).data('id');
            
            // Initialize the map
            initCustomerLocationMap();
            
            // Load the customer's details
            loadCustomerDetails(customerId);
        });

        // Initialize the customer location map
        function initCustomerLocationMap() {
            if (!customerLocationMap) {
                const defaultLocation = { lat: 14.5995, lng: 120.9842 }; // Manila, Philippines
                
                customerLocationMap = new google.maps.Map(document.getElementById('customerLocationMap'), {
                    center: defaultLocation,
                    zoom: 13,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: false
                });
                
                // Create a marker for the selected location
                customerLocationMarker = new google.maps.Marker({
                    position: defaultLocation,
                    map: customerLocationMap,
                    draggable: true,
                    animation: google.maps.Animation.DROP,
                    visible: false // Hide until a customer is selected
                });
                
                // Add event listener for marker dragend
                customerLocationMarker.addListener('dragend', function() {
                    // Enable the save button
                    $('#saveCustomerLocationBtn').prop('disabled', false);
                });
                
                // Add event listener for map click
                customerLocationMap.addListener('click', function(e) {
                    if (selectedCustomer) {
                        // Update marker position
                        customerLocationMarker.setPosition(e.latLng);
                        customerLocationMarker.setVisible(true);
                        
                        // Enable the save button
                        $('#saveCustomerLocationBtn').prop('disabled', false);
                    }
                });
                
                // Initialize the center map button
                $('#centerMapBtn').on('click', function() {
                    // Re-center the map to the marker position or default position
                    if (customerLocationMarker.getVisible()) {
                        customerLocationMap.setCenter(customerLocationMarker.getPosition());
                        customerLocationMap.setZoom(15);
                    } else {
                        customerLocationMap.setCenter(defaultLocation);
                        customerLocationMap.setZoom(13);
                    }
                });
                
                // Initialize the save location button
                $('#saveCustomerLocationBtn').on('click', function() {
                    saveCustomerLocation();
                });
            }
        }

        // Load customer details
        function loadCustomerDetails(customerId) {
            $.ajax({
                url: 'api/customers/search.php',
                method: 'GET',
                data: {
                    term: customerId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        const customer = response.data[0];
                        
                        // Store selected customer
                        selectedCustomer = {
                            customer_id: customer.customer_id,
                            user_id: customer.user_id,
                            latitude: parseFloat(customer.latitude || 0),
                            longitude: parseFloat(customer.longitude || 0),
                            name: `${customer.firstname} ${customer.lastname}`
                        };
                        
                        // Update marker position if coordinates are valid
                        if (selectedCustomer.latitude && selectedCustomer.longitude && 
                            selectedCustomer.latitude !== 0 && selectedCustomer.longitude !== 0) {
                            const position = new google.maps.LatLng(selectedCustomer.latitude, selectedCustomer.longitude);
                            customerLocationMarker.setPosition(position);
                            customerLocationMarker.setVisible(true);
                            
                            // Center map on the customer location
                            customerLocationMap.setCenter(position);
                            customerLocationMap.setZoom(15);
                            
                            // Show info message
                            Swal.fire({
                                title: 'Existing Location',
                                text: 'This customer already has a location. You can drag the marker to update it.',
                                icon: 'info',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        } else {
                            // Try to geocode the address
                            const address = [
                                customer.address,
                                customer.city,
                                customer.state,
                                customer.zip
                            ].filter(Boolean).join(', ');
                            
                            if (address && address !== '') {
                                geocodeAddress(address);
                            } else {
                                // No valid coordinates or address
                                customerLocationMarker.setVisible(false);
                                
                                // Center map on default location
                                customerLocationMap.setCenter({ lat: 14.5995, lng: 120.9842 });
                                customerLocationMap.setZoom(13);
                                
                                // Show info message
                                Swal.fire({
                                    title: 'No Location',
                                    text: 'This customer does not have a location set. Click on the map to set a location.',
                                    icon: 'info',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000
                                });
                            }
                        }
                        
                        // Enable the save button only if we have a valid marker position
                        $('#saveCustomerLocationBtn').prop('disabled', !customerLocationMarker.getVisible());
                    } else {
                        // Customer not found
                        Swal.fire({
                            title: 'Customer Not Found',
                            text: 'Could not find customer details.',
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load customer details: ' + error,
                        icon: 'error'
                    });
                }
            });
        }

        // Geocode an address
        function geocodeAddress(address) {
            if (!address) return;
            
            // Show loading indicator on the map
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'map-loading-overlay';
            loadingDiv.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-2">Geocoding address...</div>
            `;
            document.getElementById('customerLocationMap').appendChild(loadingDiv);
            
            // Use Google's Geocoding API
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ address: address }, function(results, status) {
                // Remove loading indicator
                if (document.getElementById('customerLocationMap').contains(loadingDiv)) {
                    document.getElementById('customerLocationMap').removeChild(loadingDiv);
                }
                
                if (status === 'OK' && results[0]) {
                    const position = results[0].geometry.location;
                    
                    // Update marker position
                    customerLocationMarker.setPosition(position);
                    customerLocationMarker.setVisible(true);
                    
                    // Center map on the geocoded location
                    customerLocationMap.setCenter(position);
                    customerLocationMap.setZoom(15);
                    
                    // Enable the save button
                    $('#saveCustomerLocationBtn').prop('disabled', false);
                } else {
                    // Geocoding failed
                    customerLocationMarker.setVisible(false);
                    
                    // Show error message
                    Swal.fire({
                        title: 'Geocoding Failed',
                        text: 'Could not find coordinates for the address. Please select a location manually on the map.',
                        icon: 'warning',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });
        }

        // Save customer location
        function saveCustomerLocation() {
            if (!selectedCustomer || !customerLocationMarker.getVisible()) {
                return;
            }
            
            // Get marker position
            const position = customerLocationMarker.getPosition();
            const latitude = position.lat();
            const longitude = position.lng();
            
            // Check if we should update the customer location in the database
            const updateInDatabase = $('#updateCustomerLocationCheck').is(':checked');
            
            // If we should update in the database
            if (updateInDatabase) {
                // Show loading state
                Swal.fire({
                    title: 'Saving...',
                    text: 'Updating customer location...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Call API to update customer location
                $.ajax({
                    url: 'api/customers/update_location.php',
                    method: 'POST',
                    data: {
                        customer_id: selectedCustomer.customer_id,
                        latitude: latitude,
                        longitude: longitude
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Close the modal
                            $('#selectCustomerLocationModal').modal('hide');
                            
                            // Show success message
                            Swal.fire({
                                title: 'Location Updated',
                                text: `Location updated for customer ${selectedCustomer.name}`,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                // Update the data attribute on the customer row
                                $(`tr[data-customer-id="${selectedCustomer.user_id}"]`).attr('data-lat', latitude).attr('data-lng', longitude);
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                title: 'Error',
                                text: response.message || 'Failed to update customer location',
                                icon: 'error'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to connect to the server. Please try again.',
                            icon: 'error'
                        });
                    }
                });
            } else {
                // If we're not updating in the database, just close the modal
                $('#selectCustomerLocationModal').modal('hide');
                
                // Show toast message
                Swal.fire({
                    title: 'Location Selected',
                    text: `Selected location for ${selectedCustomer.name} (not saved to database)`,
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        }

        // Initialize search handler
        $('#searchCustomerBtn').on('click', function() {
            const searchTerm = $('#customerSearchInput').val().trim();
            
            if (searchTerm) {
                // Search for customers
                $.ajax({
                    url: 'api/customers/search.php',
                    method: 'GET',
                    data: {
                        term: searchTerm
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const customers = response.data;
                            
                            // Update the count badge
                            $('#customerCount').text(customers.length);
                            
                            // Clear the list
                            $('.customer-list').empty();
                            
                            if (customers.length === 0) {
                                $('.customer-list').html(`
                                    <div class="list-group-item text-center py-3">
                                        <div class="text-muted">No customers found</div>
                                        <button class="btn btn-sm btn-secondary mt-2 reload-customers-btn">
                                            <i class="fas fa-sync-alt me-1"></i> Show All Customers
                                        </button>
                                    </div>
                                `);
                                
                                $('.reload-customers-btn').on('click', function() {
                                    $('#customerSearchInput').val('');
                                    loadCustomersList();
                                });
                            } else {
                                // Add each customer to the list
                                customers.forEach(customer => {
                                    const hasLocation = customer.latitude && customer.longitude && 
                                        customer.latitude != '0' && customer.longitude != '0';
                                    
                                    $('.customer-list').append(`
                                        <div class="list-group-item customer-item" data-customer-id="${customer.customer_id}"
                                            data-lat="${customer.latitude || 0}" data-lng="${customer.longitude || 0}">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-bold">${customer.firstname || ''} ${customer.lastname || ''}</div>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-phone me-1"></i> ${customer.phone || 'N/A'}
                                                    </div>
                                                    <div class="small text-muted">
                                                        ${customer.address ? (customer.address + (customer.city ? ', ' + customer.city : '')) : 'No address'}
                                                    </div>
                                                </div>
                                                <div>
                                                    ${hasLocation ? 
                                                        '<span class="badge bg-success"><i class="fas fa-map-marker-alt"></i> Has location</span>' : 
                                                        '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> No location</span>'}
                                                </div>
                                            </div>
                                        </div>
                                    `);
                                });
                                
                                // Add click handler for customer items
                                $('.customer-item').on('click', function() {
                                    // Remove active class from all items
                                    $('.customer-item').removeClass('active');
                                    
                                    // Add active class to selected item
                                    $(this).addClass('active');
                                    
                                    // Get customer data
                                    const customerId = $(this).data('customer-id');
                                    const latitude = parseFloat($(this).data('lat'));
                                    const longitude = parseFloat($(this).data('lng'));
                                    
                                    // Store selected customer
                                    selectedCustomer = {
                                        customer_id: customerId,
                                        latitude: latitude,
                                        longitude: longitude,
                                        name: $(this).find('.fw-bold').text().trim()
                                    };
                                    
                                    // Update marker position if coordinates are valid
                                    if (latitude && longitude && latitude != 0 && longitude != 0) {
                                        const position = new google.maps.LatLng(latitude, longitude);
                                        customerLocationMarker.setPosition(position);
                                        customerLocationMarker.setVisible(true);
                                        
                                        // Center map on the customer location
                                        customerLocationMap.setCenter(position);
                                        customerLocationMap.setZoom(15);
                                    } else {
                                        // Try to geocode the address
                                        const address = $(this).find('.text-muted').eq(1).text().trim();
                                        if (address && address !== 'No address') {
                                            geocodeAddress(address);
                                        } else {
                                            // No valid coordinates or address
                                            customerLocationMarker.setVisible(false);
                                            
                                            // Center map on default location
                                            customerLocationMap.setCenter({ lat: 14.5995, lng: 120.9842 });
                                            customerLocationMap.setZoom(13);
                                            
                                            // Show info message
                                            Swal.fire({
                                                title: 'No Location',
                                                text: 'This customer does not have a location set. Click on the map to set a location.',
                                                icon: 'info',
                                                toast: true,
                                                position: 'top-end',
                                                showConfirmButton: false,
                                                timer: 3000
                                            });
                                        }
                                    }
                                    
                                    // Enable the save button only if we have a valid marker position
                                    $('#saveCustomerLocationBtn').prop('disabled', !customerLocationMarker.getVisible());
                                });
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.customer-list').html(`
                            <div class="list-group-item text-center py-3">
                                <div class="text-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Search error: ${error}
                                </div>
                                <button class="btn btn-sm btn-secondary mt-2 reload-customers-btn">
                                    <i class="fas fa-sync-alt me-1"></i> Show All Customers
                                </button>
                            </div>
                        `);
                    }
                });
            }
        });

        // Handle pressing Enter in the search input
        $('#customerSearchInput').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                $('#searchCustomerBtn').click();
            }
        });

        // Initialize CSS for map loading overlay
        $('<style>').text(`
            .map-loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 255, 255, 0.8);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }
        `).appendTo('head');
    });
</script> 

<!-- Customer details modal handler -->
<script src="assets/js/customer-details-modal.js"></script>
</body>
</html> 