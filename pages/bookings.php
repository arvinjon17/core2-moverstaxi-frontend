<?php
// Booking Management Page
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
if (!function_exists('connectToCore2DB')) {
    require_once 'functions/db.php';
}

// Ensure auth is included
if (!function_exists('hasPermission')) {
    require_once 'functions/auth.php';
}

// Check authentication and permissions
if (!isLoggedIn()) {
    echo '<div class="alert alert-danger">Please log in to access this page.</div>';
    exit;
}

// Check role-based permissions for bookings page
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'super_admin', 'dispatch']) && 
    !hasPermission('manage_bookings')) {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    exit;
}

// Debug information
error_log("DB_NAME_CORE1: " . (defined('DB_NAME_CORE1') ? DB_NAME_CORE1 : 'Not defined'));
error_log("DB_NAME_CORE2: " . (defined('DB_NAME_CORE2') ? DB_NAME_CORE2 : 'Not defined'));

// Check permissions
if (!hasPermission('manage_bookings')) {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    exit;
}

// Debug section - only show if debug parameter is present
if (isset($_GET['debug'])) {
    echo '<div class="alert alert-info">';
    echo '<h4>Debugging Information</h4>';
    
    // Check database definitions
    echo '<p><strong>Database Configuration:</strong></p>';
    echo '<ul>';
    echo '<li>DB_NAME_CORE1: ' . (defined('DB_NAME_CORE1') ? DB_NAME_CORE1 : 'Not defined') . '</li>';
    echo '<li>DB_NAME_CORE2: ' . (defined('DB_NAME_CORE2') ? DB_NAME_CORE2 : 'Not defined') . '</li>';
    echo '</ul>';
    
    // Test connections
    echo '<p><strong>Testing Database Connections:</strong></p>';
    echo '<ul>';
    try {
        $conn1 = connectToCore1DB();
        echo '<li>Core1 DB Connection: <span class="text-success">Success</span></li>';
        
        // Test a simple query
        $testResult1 = $conn1->query("SELECT 1 as test");
        if ($testResult1) {
            $row1 = $testResult1->fetch_assoc();
            echo '<li>Core1 DB Query Test: <span class="text-success">Success - ' . $row1['test'] . '</span></li>';
        } else {
            echo '<li>Core1 DB Query Test: <span class="text-danger">Failed - ' . $conn1->error . '</span></li>';
        }
    } catch (Exception $e) {
        echo '<li>Core1 DB Connection: <span class="text-danger">Failed - ' . $e->getMessage() . '</span></li>';
    }
    
    try {
        $conn2 = connectToCore2DB();
        echo '<li>Core2 DB Connection: <span class="text-success">Success</span></li>';
        
        // Test a simple query
        $testResult2 = $conn2->query("SELECT 1 as test");
        if ($testResult2) {
            $row2 = $testResult2->fetch_assoc();
            echo '<li>Core2 DB Query Test: <span class="text-success">Success - ' . $row2['test'] . '</span></li>';
        } else {
            echo '<li>Core2 DB Query Test: <span class="text-danger">Failed - ' . $conn2->error . '</span></li>';
        }
        
        // Check if bookings table exists
        $checkBookingsTable = $conn2->query("SHOW TABLES LIKE 'bookings'");
        if ($checkBookingsTable && $checkBookingsTable->num_rows > 0) {
            echo '<li>Bookings Table: <span class="text-success">Exists</span></li>';
            
            // Count bookings
            $countResult = $conn2->query("SELECT COUNT(*) as count FROM bookings");
            if ($countResult) {
                $countRow = $countResult->fetch_assoc();
                echo '<li>Booking Count: ' . $countRow['count'] . '</li>';
            }
        } else {
            echo '<li>Bookings Table: <span class="text-danger">Not Found</span></li>';
        }
    } catch (Exception $e) {
        echo '<li>Core2 DB Connection: <span class="text-danger">Failed - ' . $e->getMessage() . '</span></li>';
    }
    echo '</ul>';
    
    echo '<p><a href="?page=bookings&test_insert=1" class="btn btn-warning btn-sm">Insert Test Booking</a></p>';
    echo '</div>';
}

// Initialize variables
$message = '';
$messageType = '';
$bookings = [];
$error = '';
$stats = [
    'pending' => 0,
    'confirmed' => 0,
    'in_progress' => 0, 
    'completed' => 0,
    'cancelled' => 0,
    'total' => 0
];

// Check if bookings table exists
try {
    $checkTableQuery = "SHOW TABLES FROM " . DB_NAME_CORE2 . " LIKE 'bookings'";
    $tableResult = getRows($checkTableQuery, 'core2');
    if (empty($tableResult)) {
        error_log("Bookings table not found in " . DB_NAME_CORE2);
        echo '<div class="alert alert-danger">Bookings table not found in the database. Please check your database structure.</div>';
    } else {
        error_log("Bookings table exists in " . DB_NAME_CORE2);
        
        // Check table structure
        $tableStructureQuery = "DESCRIBE " . DB_NAME_CORE2 . ".bookings";
        $structureResult = getRows($tableStructureQuery, 'core2');
        error_log("Bookings table structure: " . json_encode($structureResult));
    }
} catch (Exception $e) {
    error_log("Error checking bookings table: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error checking database structure: ' . $e->getMessage() . '</div>';
}

// Retrieve booking statistics
try {
    $statsQuery = "SELECT booking_status, COUNT(*) as count 
                  FROM " . DB_NAME_CORE2 . ".bookings 
                  GROUP BY booking_status";
    
    $statsResult = getRows($statsQuery, 'core2');
    
    if ($statsResult) {
        foreach ($statsResult as $row) {
            $stats[$row['booking_status']] = (int)$row['count'];
            $stats['total'] += (int)$row['count'];
        }
    }
} catch (Exception $e) {
    error_log("Error retrieving booking statistics: " . $e->getMessage());
}

// Try a simpler query first to isolate the issue
try {
    $simpleQuery = "SELECT booking_id, customer_id, pickup_location, dropoff_location, booking_status FROM " . DB_NAME_CORE2 . ".bookings LIMIT 10";
    error_log("Executing simple query: $simpleQuery");
    
    $simpleBookings = getRows($simpleQuery, 'core2');
    if (!empty($simpleBookings)) {
        error_log("Simple query returned " . count($simpleBookings) . " bookings");
        error_log("First simple booking: " . json_encode($simpleBookings[0]));
    } else {
        error_log("No bookings found in simple query - table might be empty");
    }
} catch (Exception $e) {
    error_log("Simple query failed: " . $e->getMessage());
    echo '<div class="alert alert-warning">Simple query failed: ' . $e->getMessage() . '</div>';
}

// Get bookings without trying to do cross-database joins
try {
    // Step 1: Get basic booking data from core2 database
    $bookingQuery = "SELECT 
        booking_id, customer_id, user_id, pickup_location, dropoff_location, 
        pickup_datetime, dropoff_datetime, vehicle_id, driver_id, booking_status, 
        fare_amount, distance_km, duration_minutes, special_instructions, 
        cancellation_reason, created_at, updated_at
    FROM bookings
ORDER BY 
    CASE 
            WHEN booking_status = 'pending' THEN 1
            WHEN booking_status = 'confirmed' THEN 2
            WHEN booking_status = 'in_progress' THEN 3
            WHEN booking_status = 'completed' THEN 4
            WHEN booking_status = 'cancelled' THEN 5
        END, 
        pickup_datetime DESC";
    
    error_log("Executing bookings query: $bookingQuery");
    $bookingsData = getRows($bookingQuery, 'core2');
    $bookingCount = count($bookingsData);
    error_log("Found $bookingCount basic bookings");
    
    // Initialize the combined bookings array
    $bookings = [];
    
    // If we have basic booking data, fetch the additional details separately
    if (!empty($bookingsData)) {
        // Process each booking
        foreach ($bookingsData as $booking) {
            $completeBooking = $booking;
            
            // Step 2: Get customer data if available
            if (!empty($booking['customer_id'])) {
                $customerId = (int)$booking['customer_id'];
                $customerQuery = "SELECT 
                    c.address, c.city, c.state, c.zip, c.notes, c.status, c.user_id
                    FROM customers c 
                    WHERE c.customer_id = $customerId";
                $customerData = getRows($customerQuery, 'core1');
                
                if (!empty($customerData[0])) {
                    // Add customer data to the complete booking
                    $completeBooking['customer_address'] = $customerData[0]['address'];
                    $completeBooking['customer_city'] = $customerData[0]['city'];
                    $completeBooking['customer_state'] = $customerData[0]['state'];
                    $completeBooking['customer_zip'] = $customerData[0]['zip'];
                    $completeBooking['customer_notes'] = $customerData[0]['notes'];
                    $completeBooking['customer_status'] = $customerData[0]['status'];
                    
                    // Get user info for the customer
                    $userId = (int)$customerData[0]['user_id'];
                    if ($userId > 0) {
                        $userQuery = "SELECT 
                            firstname, lastname, email, phone
                            FROM users
                            WHERE user_id = $userId";
                        $userData = getRows($userQuery, 'core2');
                        
                        if (!empty($userData[0])) {
                            $completeBooking['customer_firstname'] = $userData[0]['firstname'];
                            $completeBooking['customer_lastname'] = $userData[0]['lastname'];
                            $completeBooking['customer_email'] = $userData[0]['email'];
                            $completeBooking['customer_phone'] = $userData[0]['phone'];
                        }
                    }
                }
            }
            
            // Step 3: Get driver data if available
            if (!empty($booking['driver_id'])) {
                $driverId = (int)$booking['driver_id'];
                $driverQuery = "SELECT 
                    d.license_number, d.license_expiry, d.rating, d.status, d.user_id
                    FROM drivers d
                    WHERE d.driver_id = $driverId";
                $driverData = getRows($driverQuery, 'core1');
                
                if (!empty($driverData[0])) {
                    // Add driver data to the complete booking
                    $completeBooking['license_number'] = $driverData[0]['license_number'];
                    $completeBooking['license_expiry'] = $driverData[0]['license_expiry'];
                    $completeBooking['driver_rating'] = $driverData[0]['rating'];
                    $completeBooking['driver_status'] = $driverData[0]['status'];
                    
                    // Get user info for the driver
                    $driverUserId = (int)$driverData[0]['user_id'];
                    if ($driverUserId > 0) {
                        $driverUserQuery = "SELECT 
                            firstname, lastname, email, phone
                            FROM users
                            WHERE user_id = $driverUserId";
                        $driverUserData = getRows($driverUserQuery, 'core2');
                        
                        if (!empty($driverUserData[0])) {
                            $completeBooking['driver_firstname'] = $driverUserData[0]['firstname'];
                            $completeBooking['driver_lastname'] = $driverUserData[0]['lastname'];
                            $completeBooking['driver_email'] = $driverUserData[0]['email'];
                            $completeBooking['driver_phone'] = $driverUserData[0]['phone'];
                        }
                    }
                }
            }
            
            // Step 4: Get vehicle data if available
            if (!empty($booking['vehicle_id'])) {
                $vehicleId = (int)$booking['vehicle_id'];
                $vehicleQuery = "SELECT 
                    model, plate_number, year, capacity
                    FROM vehicles
                    WHERE vehicle_id = $vehicleId";
                $vehicleData = getRows($vehicleQuery, 'core1');
                
                if (!empty($vehicleData[0])) {
                    // Add vehicle data to the complete booking
                    $completeBooking['vehicle_model'] = $vehicleData[0]['model'];
                    $completeBooking['plate_number'] = $vehicleData[0]['plate_number'];
                    $completeBooking['vehicle_year'] = $vehicleData[0]['year'];
                    $completeBooking['vehicle_capacity'] = $vehicleData[0]['capacity'];
                }
            }
            
            // Add the complete booking to the bookings array
            $bookings[] = $completeBooking;
        }
    }
    
    $bookingCount = count($bookings);
    error_log("Final combined bookings count: $bookingCount");
    
    if (empty($bookings)) {
        error_log("No bookings found in the final combined result");
    } else {
        // Log the first booking for debugging
        error_log("First combined booking: " . json_encode($bookings[0]));
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Bookings query failed in bookings.php: " . $error);
    echo '<div class="alert alert-danger">Error fetching bookings: ' . $error . '</div>';
}

// Get active drivers with location data for the map
$driversQuery = "SELECT 
    d.driver_id, d.user_id, d.status as driver_status, d.license_number,
    u.firstname, u.lastname, u.phone, u.email,
    d.latitude, d.longitude, d.location_updated_at as last_updated
FROM 
    " . DB_NAME_CORE1 . ".drivers d
JOIN 
    " . DB_NAME_CORE2 . ".users u ON d.user_id = u.user_id
WHERE 
    d.status = 'available'";

try {
    $availableDrivers = getRows($driversQuery, 'core1');
} catch (Exception $e) {
    error_log("Error fetching available drivers: " . $e->getMessage());
    $availableDrivers = [];
}

// Function to insert a test booking for debugging purposes
function insertTestBooking() {
    try {
        // Generate a unique identifier for testing
        $testId = 'TEST_' . time();
        
        // Insert test booking
        $insertQuery = "INSERT INTO " . DB_NAME_CORE2 . ".bookings 
            (customer_id, user_id, pickup_location, dropoff_location, pickup_datetime, booking_status, created_at, updated_at) 
            VALUES 
            (1, 1, 'Test Pickup $testId', 'Test Dropoff $testId', NOW(), 'pending', NOW(), NOW())";
        
        error_log("Executing test insert: $insertQuery");
        $conn = connectToCore2DB();
        
        if (!$conn) {
            error_log("Failed to get database connection for test insert");
            return false;
        }
        
        $result = $conn->query($insertQuery);
        
        if ($result) {
            $lastId = $conn->insert_id;
            error_log("Test booking inserted with ID: $lastId");
            return true;
        } else {
            error_log("Failed to insert test booking: " . $conn->error);
            return false;
        }
    } catch (Exception $e) {
        error_log("Test booking insertion failed: " . $e->getMessage());
        return false;
    }
}

// Try to insert a test booking if no bookings are found
if (empty($bookings) && isset($_GET['test_insert'])) {
    error_log("No bookings found, attempting to insert a test booking");
    $testResult = insertTestBooking();
    if ($testResult) {
        echo '<div class="alert alert-success">Test booking inserted successfully. Refresh the page to see it.</div>';
    } else {
        echo '<div class="alert alert-danger">Failed to insert test booking. Check the error logs.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management | Movers Taxi</title>
    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <!-- Include Font Awesome -->
    <link rel="stylesheet" href="assets/css/all.min.css">
    <!-- Include DataTables CSS -->
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/responsive.bootstrap5.min.css">
    <!-- Include SweetAlert2 CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <!-- Include Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GMAP_API_KEY&libraries=places,geometry" async defer></script>
    <style>
        .status-tab {
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }
        .status-tab.active {
            border-bottom: 2px solid #0d6efd;
            font-weight: 500;
        }
        
        .driver-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .driver-item:hover {
            background-color: #f8f9fa;
        }
        
        .driver-item.active {
            background-color: #e9f0fe;
            border-left: 3px solid #0d6efd;
        }
        
        /* Map styles */
        #driverMap {
            height: 350px;
            width: 100%;
            border-radius: 4px;
        }
        
        .customer-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .customer-item:hover {
            background-color: #f8f9fa;
        }
        
        .customer-item.active {
            background-color: #e9f0fe;
            border-left: 3px solid #0d6efd;
        }
        
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
            z-index: 999;
        }
        
        .custom-map-control {
            background-color: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
            margin: 10px;
            padding: 5px;
        }
        
        .driver-info-window, .customer-info-window {
            min-width: 200px;
        }
    </style>
</head>
<body>
    <main class="content">
        <div class="container-fluid px-4">
            <h1 class="mt-4">Booking Management</h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Booking Management</li>
            </ol>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Booking Statistics -->
            <div class="row mb-4">
                <!-- Total Bookings -->
                <div class="col-md-4 col-xl-2 mb-4">
                    <div class="stats-card card bg-primary text-white h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <div class="icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="count"><?php echo $stats['total']; ?></div>
                            <div class="label">Total Bookings</div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Bookings -->
                <div class="col-md-4 col-xl-2 mb-4">
                    <div class="stats-card card bg-warning text-dark h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <div class="icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="count"><?php echo $stats['pending']; ?></div>
                            <div class="label">Pending</div>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmed Bookings -->
                <div class="col-md-4 col-xl-2 mb-4">
                    <div class="stats-card card bg-info text-white h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="count"><?php echo $stats['confirmed']; ?></div>
                            <div class="label">Confirmed</div>
                        </div>
                    </div>
                </div>
                
                <!-- In Progress Bookings -->
                <div class="col-md-4 col-xl-2 mb-4">
                    <div class="stats-card card bg-primary text-white h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <div class="icon">
                                <i class="fas fa-truck-moving"></i>
                            </div>
                            <div class="count"><?php echo $stats['in_progress']; ?></div>
                            <div class="label">In Progress</div>
                        </div>
                    </div>
                </div>
                
                <!-- Completed Bookings -->
                <div class="col-md-4 col-xl-2 mb-4">
                    <div class="stats-card card bg-success text-white h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <div class="icon">
                                <i class="fas fa-check-double"></i>
                            </div>
                            <div class="count"><?php echo $stats['completed']; ?></div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                </div>
                
                <!-- Cancelled Bookings -->
                <div class="col-md-4 col-xl-2 mb-4">
                    <div class="stats-card card bg-danger text-white h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <div class="icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="count"><?php echo $stats['cancelled']; ?></div>
                            <div class="label">Cancelled</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Driver Tracking Map -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-map-marked-alt me-1"></i>
                            Driver Tracking
                            <div class="float-end">
                                <button class="btn btn-sm btn-primary refresh-map">
                                    <i class="fas fa-sync-alt"></i> Refresh Map
                                </button>
                                <button class="btn btn-sm btn-info simulate-locations">
                                    <i class="fas fa-magic"></i> Simulate Locations
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Map View -->
                                <div class="col-md-8">
                                    <div class="map-container">
                                        <div id="driverMap"></div>
                                    </div>
                                </div>
                                
                                <!-- Available Drivers List -->
                                <div class="col-md-4">
                                    <h5 class="mb-3">Available Drivers</h5>
                                    <div class="driver-list" style="max-height: 350px; overflow-y: auto;">
                                        <?php if (empty($availableDrivers)): ?>
                                            <div class="alert alert-info">
                                                No drivers currently available.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($availableDrivers as $driver): ?>
                                                <?php 
                                                    $hasGps = isset($driver['latitude']) && isset($driver['longitude']) && 
                                                         $driver['latitude'] != '0' && $driver['longitude'] != '0';
                                                ?>
                                                <div class="card driver-card mb-2" data-driver-id="<?php echo $driver['driver_id']; ?>" 
                                                     data-lat="<?php echo $driver['latitude'] ?? '0'; ?>" 
                                                     data-lng="<?php echo $driver['longitude'] ?? '0'; ?>">
                                                    <div class="card-body py-2">
                                                        <h6 class="mb-0">
                                                            <?php if ($hasGps): ?>
                                                                <span class="badge bg-success rounded-circle" style="width: 10px; height: 10px; display: inline-block; margin-right: 5px;"></span>
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($driver['firstname'] . ' ' . $driver['lastname']); ?>
                                                            <span class="badge <?php echo $driver['status'] === 'available' ? 'bg-success' : 'bg-warning'; ?> float-end">
                                                                <?php echo htmlspecialchars($driver['status']); ?>
                                                            </span>
                                                        </h6>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($driver['phone']); ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-id-card me-1"></i> License: <?php echo htmlspecialchars($driver['license_number']); ?>
                                                        </div>
                                                        <?php if (isset($driver['last_updated'])): ?>
                                                            <div class="small text-success">
                                                                <i class="fas fa-clock me-1"></i> Updated: <?php echo date('M d, H:i', strtotime($driver['last_updated'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($hasGps): ?>
                                                            <div class="small text-primary">
                                                                <i class="fas fa-map-marker-alt me-1"></i> GPS active
                                                    </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <a href="#" class="btn btn-primary btn-sm view-driver-details d-block border-top-0 rounded-0 rounded-bottom" 
                                                       data-bs-toggle="modal" 
                                                       data-bs-target="#viewDriverDetailsModal" 
                                                       data-driver-id="<?php echo $driver['driver_id']; ?>">
                                                        <i class="fas fa-info-circle"></i> View Details
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bookings Table Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Booking List
                    <div class="float-end">
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                            <i class="fas fa-plus"></i> Add Booking
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Booking Tabs -->
                    <ul class="nav nav-tabs" id="bookingTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="all-tab" data-bs-toggle="tab" href="#all" role="tab" 
                               aria-controls="all" aria-selected="true" data-status="all">
                                All Bookings <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="pending-tab" data-bs-toggle="tab" href="#pending" role="tab" 
                               aria-controls="pending" aria-selected="false" data-status="pending">
                                Pending <span class="badge bg-warning"><?php echo $stats['pending']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="confirmed-tab" data-bs-toggle="tab" href="#confirmed" role="tab" 
                               aria-controls="confirmed" aria-selected="false" data-status="confirmed">
                                Confirmed <span class="badge bg-primary"><?php echo $stats['confirmed']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="in-progress-tab" data-bs-toggle="tab" href="#in_progress" role="tab" 
                               aria-controls="in_progress" aria-selected="false" data-status="in_progress">
                                In Progress <span class="badge bg-info"><?php echo $stats['in_progress']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="completed-tab" data-bs-toggle="tab" href="#completed" role="tab" 
                               aria-controls="completed" aria-selected="false" data-status="completed">
                                Completed <span class="badge bg-success"><?php echo $stats['completed']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="cancelled-tab" data-bs-toggle="tab" href="#cancelled" role="tab" 
                               aria-controls="cancelled" aria-selected="false" data-status="cancelled">
                                Cancelled <span class="badge bg-danger"><?php echo $stats['cancelled']; ?></span>
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="bookingTabsContent">
                        <!-- All Bookings Tab -->
                        <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                            <table id="allBookingsTable" class="table table-striped table-bordered table-hover booking-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Pickup Location</th>
                                        <th>Dropoff Location</th>
                                        <th>Pickup Time</th>
                                        <th>Status</th>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Fare</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr data-status="<?php echo $booking['booking_status']; ?>">
                                            <td><?php echo $booking['booking_id']; ?></td>
                                            <td>
                                                <?php if (!empty($booking['customer_firstname'])): ?>
                                                    <a href="customers.php?view=<?php echo $booking['user_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?>
                                                    </a>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Unknown Customer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['dropoff_location']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($booking['pickup_datetime'])); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $booking['booking_status']; ?>-badge">
                                                    <?php echo ucfirst($booking['booking_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($booking['driver_firstname'])): ?>
                                                    <a href="drivers.php?view=<?php echo $booking['driver_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($booking['driver_firstname'] . ' ' . $booking['driver_lastname']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($booking['vehicle_model'])): ?>
                                                    <?php echo htmlspecialchars($booking['vehicle_model'] . ' (' . $booking['plate_number'] . ')'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($booking['fare_amount'])): ?>
                                                    ₱<?php echo number_format($booking['fare_amount'], 2); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not Set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column flex-md-row gap-1">
                                                    <button type="button" class="btn btn-sm btn-primary view-booking-btn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewBookingModal" 
                                                            data-id="<?php echo $booking['booking_id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if (in_array($booking['booking_status'], ['pending', 'confirmed'])): ?>
                                                        <button type="button" class="btn btn-sm btn-info edit-booking-btn"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($booking['booking_status'] == 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-success confirm-booking-btn"
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($booking['booking_status'] == 'confirmed'): ?>
                                                        <button type="button" class="btn btn-sm btn-warning start-booking-btn"
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($booking['booking_status'] == 'in_progress'): ?>
                                                        <button type="button" class="btn btn-sm btn-success complete-booking-btn"
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($booking['booking_status'], ['pending', 'confirmed'])): ?>
                                                        <button type="button" class="btn btn-sm btn-danger cancel-booking-btn"
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Other status tabs will have similar tables but filtered by status -->
                        <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                            <!-- Pending Bookings Table -->
                            <table id="pendingBookingsTable" class="table table-striped table-bordered table-hover booking-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Pickup Location</th>
                                        <th>Dropoff Location</th>
                                        <th>Pickup Time</th>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Fare</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <?php if ($booking['booking_status'] == 'pending'): ?>
                                            <tr data-status="pending">
                                                <td><?php echo $booking['booking_id']; ?></td>
                                                <td>
                                                    <?php if (!empty($booking['customer_firstname'])): ?>
                                                        <a href="customers.php?view=<?php echo $booking['user_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?>
                                                        </a>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown Customer</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                                <td><?php echo htmlspecialchars($booking['dropoff_location']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($booking['pickup_datetime'])); ?></td>
                                                <td>
                                                    <?php if (!empty($booking['driver_firstname'])): ?>
                                                        <a href="drivers.php?view=<?php echo $booking['driver_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['driver_firstname'] . ' ' . $booking['driver_lastname']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['vehicle_model'])): ?>
                                                        <?php echo htmlspecialchars($booking['vehicle_model'] . ' (' . $booking['plate_number'] . ')'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['fare_amount'])): ?>
                                                        ₱<?php echo number_format($booking['fare_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column flex-md-row gap-1">
                                                        <button type="button" class="btn btn-sm btn-primary view-booking-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-info edit-booking-btn"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-success update-status"
                                                                data-id="<?php echo $booking['booking_id']; ?>"
                                                                data-status="confirmed">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-danger update-status"
                                                                data-id="<?php echo $booking['booking_id']; ?>"
                                                                data-status="cancelled">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="tab-pane fade" id="confirmed" role="tabpanel" aria-labelledby="confirmed-tab">
                            <!-- Confirmed Bookings Table -->
                            <table id="confirmedBookingsTable" class="table table-striped table-bordered table-hover booking-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Pickup Location</th>
                                        <th>Dropoff Location</th>
                                        <th>Pickup Time</th>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Fare</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <?php if ($booking['booking_status'] == 'confirmed'): ?>
                                            <tr data-status="confirmed">
                                                <td><?php echo $booking['booking_id']; ?></td>
                                                <td>
                                                    <?php if (!empty($booking['customer_firstname'])): ?>
                                                        <a href="customers.php?view=<?php echo $booking['user_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?>
                                                        </a>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown Customer</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                                <td><?php echo htmlspecialchars($booking['dropoff_location']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($booking['pickup_datetime'])); ?></td>
                                                <td>
                                                    <?php if (!empty($booking['driver_firstname'])): ?>
                                                        <a href="drivers.php?view=<?php echo $booking['driver_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['driver_firstname'] . ' ' . $booking['driver_lastname']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['vehicle_model'])): ?>
                                                        <?php echo htmlspecialchars($booking['vehicle_model'] . ' (' . $booking['plate_number'] . ')'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['fare_amount'])): ?>
                                                        ₱<?php echo number_format($booking['fare_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column flex-md-row gap-1">
                                                        <button type="button" class="btn btn-sm btn-primary view-booking-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-info edit-booking-btn"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-warning update-status"
                                                                data-id="<?php echo $booking['booking_id']; ?>"
                                                                data-status="in_progress">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-danger update-status"
                                                                data-id="<?php echo $booking['booking_id']; ?>"
                                                                data-status="cancelled">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="tab-pane fade" id="in_progress" role="tabpanel" aria-labelledby="in-progress-tab">
                            <!-- In Progress Bookings Table -->
                            <table id="in_progressBookingsTable" class="table table-striped table-bordered table-hover booking-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Pickup Location</th>
                                        <th>Dropoff Location</th>
                                        <th>Pickup Time</th>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Fare</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <?php if ($booking['booking_status'] == 'in_progress'): ?>
                                            <tr data-status="in_progress">
                                                <td><?php echo $booking['booking_id']; ?></td>
                                                <td>
                                                    <?php if (!empty($booking['customer_firstname'])): ?>
                                                        <a href="customers.php?view=<?php echo $booking['user_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?>
                                                        </a>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown Customer</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                                <td><?php echo htmlspecialchars($booking['dropoff_location']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($booking['pickup_datetime'])); ?></td>
                                                <td>
                                                    <?php if (!empty($booking['driver_firstname'])): ?>
                                                        <a href="drivers.php?view=<?php echo $booking['driver_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['driver_firstname'] . ' ' . $booking['driver_lastname']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['vehicle_model'])): ?>
                                                        <?php echo htmlspecialchars($booking['vehicle_model'] . ' (' . $booking['plate_number'] . ')'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['fare_amount'])): ?>
                                                        ₱<?php echo number_format($booking['fare_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column flex-md-row gap-1">
                                                        <button type="button" class="btn btn-sm btn-primary view-booking-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-success update-status"
                                                                data-id="<?php echo $booking['booking_id']; ?>"
                                                                data-status="completed">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-danger update-status"
                                                                data-id="<?php echo $booking['booking_id']; ?>"
                                                                data-status="cancelled">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                            <!-- Completed Bookings Table -->
                            <table id="completedBookingsTable" class="table table-striped table-bordered table-hover booking-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Pickup Location</th>
                                        <th>Dropoff Location</th>
                                        <th>Pickup Time</th>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Fare</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <?php if ($booking['booking_status'] == 'completed'): ?>
                                            <tr data-status="completed">
                                                <td><?php echo $booking['booking_id']; ?></td>
                                                <td>
                                                    <?php if (!empty($booking['customer_firstname'])): ?>
                                                        <a href="customers.php?view=<?php echo $booking['user_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?>
                                                        </a>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown Customer</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                                <td><?php echo htmlspecialchars($booking['dropoff_location']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($booking['pickup_datetime'])); ?></td>
                                                <td>
                                                    <?php if (!empty($booking['driver_firstname'])): ?>
                                                        <a href="drivers.php?view=<?php echo $booking['driver_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['driver_firstname'] . ' ' . $booking['driver_lastname']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['vehicle_model'])): ?>
                                                        <?php echo htmlspecialchars($booking['vehicle_model'] . ' (' . $booking['plate_number'] . ')'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['fare_amount'])): ?>
                                                        ₱<?php echo number_format($booking['fare_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column flex-md-row gap-1">
                                                        <button type="button" class="btn btn-sm btn-primary view-booking-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="tab-pane fade" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                            <!-- Cancelled Bookings Table -->
                            <table id="cancelledBookingsTable" class="table table-striped table-bordered table-hover booking-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Pickup Location</th>
                                        <th>Dropoff Location</th>
                                        <th>Pickup Time</th>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Fare</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <?php if ($booking['booking_status'] == 'cancelled'): ?>
                                            <tr data-status="cancelled">
                                                <td><?php echo $booking['booking_id']; ?></td>
                                                <td>
                                                    <?php if (!empty($booking['customer_firstname'])): ?>
                                                        <a href="customers.php?view=<?php echo $booking['user_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['customer_firstname'] . ' ' . $booking['customer_lastname']); ?>
                                                        </a>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown Customer</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['pickup_location']); ?></td>
                                                <td><?php echo htmlspecialchars($booking['dropoff_location']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($booking['pickup_datetime'])); ?></td>
                                                <td>
                                                    <?php if (!empty($booking['driver_firstname'])): ?>
                                                        <a href="drivers.php?view=<?php echo $booking['driver_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($booking['driver_firstname'] . ' ' . $booking['driver_lastname']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['vehicle_model'])): ?>
                                                        <?php echo htmlspecialchars($booking['vehicle_model'] . ' (' . $booking['plate_number'] . ')'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($booking['fare_amount'])): ?>
                                                        ₱<?php echo number_format($booking['fare_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column flex-md-row gap-1">
                                                        <button type="button" class="btn btn-sm btn-primary view-booking-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewBookingModal" 
                                                                data-id="<?php echo $booking['booking_id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- View Booking Modal -->
    <div class="modal fade" id="viewBookingModal" tabindex="-1" aria-labelledby="viewBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="viewBookingModalLabel">
                        <i class="fas fa-calendar-alt me-2"></i> Booking Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar progress-bar-striped bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading booking details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary edit-booking-from-view">
                        <i class="fas fa-edit me-1"></i> Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Booking Modal -->
    <div class="modal fade" id="editBookingModal" tabindex="-1" aria-labelledby="editBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="editBookingModalLabel">
                        <i class="fas fa-edit me-2"></i> Edit Booking
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar progress-bar-striped bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="modal-body">
                    <div id="editBookingContent" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading booking edit form...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBookingBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add New Booking Modal -->
    <div class="modal fade" id="addBookingModal" tabindex="-1" aria-labelledby="addBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="addBookingModalLabel">
                        <i class="fas fa-plus me-2"></i> Add New Booking
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addBookingForm" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                        <option value="">-- Select Customer --</option>
                                </select>
                                    <div class="invalid-feedback">Please select a customer.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="pickup_datetime" class="form-label">Pickup Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="pickup_datetime" name="pickup_datetime" required>
                                    <div class="invalid-feedback">Please select pickup date and time.</div>
                        </div>
                        
                                <div class="mb-3">
                                <label for="pickup_location" class="form-label">Pickup Location</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="pickup_location" name="pickup_location" placeholder="Enter pickup address" required>
                                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#locationMapModal" data-location-type="pickup">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <input type="hidden" id="pickup_lat" name="pickup_lat">
                                        <input type="hidden" id="pickup_lng" name="pickup_lng">
                                </div>
                                    <div class="invalid-feedback">Please enter pickup location.</div>
                            </div>

                                <div class="mb-3">
                                <label for="dropoff_location" class="form-label">Dropoff Location</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="dropoff_location" name="dropoff_location" placeholder="Enter dropoff address" required>
                                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#locationMapModal" data-location-type="dropoff">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <input type="hidden" id="dropoff_lat" name="dropoff_lat">
                                        <input type="hidden" id="dropoff_lng" name="dropoff_lng">
                                </div>
                                    <div class="invalid-feedback">Please enter dropoff location.</div>
                        </div>
                        
                                <div class="card mb-3">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <span>Route Details</span>
                                        <button type="button" id="calculateRouteBtn" class="btn btn-sm btn-primary">
                                            <i class="fas fa-calculator me-1"></i> Calculate
                                        </button>
                                </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <div id="routeMap" style="height: 200px; width: 100%; border-radius: 4px;"></div>
                            </div>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <div class="small text-muted">Distance</div>
                                                <div class="fw-bold" id="routeDistance">-</div>
                                                <input type="hidden" id="distance_km" name="distance_km" value="">
                                            </div>
                                            <div class="col-md-4">
                                                <div class="small text-muted">Duration</div>
                                                <div class="fw-bold" id="routeDuration">-</div>
                                                <input type="hidden" id="duration_minutes" name="duration_minutes" value="">
                                            </div>
                                            <div class="col-md-4">
                                                <div class="small text-muted">Estimated Fare</div>
                                                <div class="fw-bold" id="routeFare">-</div>
                                                <input type="hidden" id="fare_amount" name="fare_amount" value="">
                                            </div>
                                        </div>
                            </div>
                        </div>
                        
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Booking Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any special instructions or notes"></textarea>
                            </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Select Driver & Vehicle</label>
                                    <div class="card">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                            <div>
                                                <select class="form-select form-select-sm" id="driver_sort">
                                                    <option value="nearest">Sort by Nearest</option>
                                                    <option value="status">Sort by Availability</option>
                                                    <option value="name">Sort by Name</option>
                                </select>
                            </div>
                                            <span class="badge bg-primary" id="driverCount">0 drivers</span>
                        </div>
                                        <div class="card-body p-0">
                                            <div class="list-group list-group-flush" id="availableDriversList" style="max-height: 450px; overflow-y: auto;">
                                                <div class="list-group-item text-center py-3">
                                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                            </div>
                                                    <span class="ms-2">Loading available drivers...</span>
                            </div>
                        </div>
                            </div>
                                        <div class="card-footer bg-light py-2">
                                            <div class="small text-muted">
                                                <i class="fas fa-info-circle me-1"></i> Click on a driver to select
                            </div>
                        </div>
                                    </div>
                                    <input type="hidden" id="driver_id" name="driver_id" value="">
                                    <input type="hidden" id="vehicle_id" name="vehicle_id" value="">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="createBookingBtn">
                        <i class="fas fa-plus me-1"></i> Create Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Map Modal -->
    <div class="modal fade" id="locationMapModal" tabindex="-1" aria-labelledby="locationMapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationMapModalLabel">Select Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Search for a location" id="mapSearchInput">
                        <button class="btn btn-outline-secondary" type="button" id="mapSearchButton">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="locationPickerMap" style="height: 400px; width: 100%;"></div>
                    <div class="mt-3">
                        <strong>Selected Address:</strong>
                        <p id="selectedAddress" class="mb-0">No location selected</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmLocationBtn">Confirm Location</button>
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
                                    <button class="btn btn-outline-warning" id="debugCustomerListBtn" type="button">
                                        <i class="fas fa-bug"></i> Debug
                                    </button>
                                </div>
                            </div>
                            <div class="p-2 bg-light border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Customers (<span id="customerCount">0</span>)</span>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary" id="refreshCustomersBtn" title="Refresh Customers">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <div class="form-check form-switch d-inline-block ms-2">
                                            <input class="form-check-input" type="checkbox" id="autoRefreshToggle" checked>
                                            <label class="form-check-label small" for="autoRefreshToggle">Auto</label>
                                        </div>
                                        <span class="small ms-1 text-muted refresh-countdown d-none">5s</span>
                                    </div>
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
                <div id="debugOutput" class="d-none mt-3 alert alert-secondary overflow-auto" style="max-height: 150px;"></div>
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

    <!-- SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Global variables for map functionality
        let map;
        let driverMarkers = {};
        let availableDrivers = <?php echo json_encode($availableDrivers); ?>;
        let locationPickerMap;
        let locationMarker;
        let currentLocationType = 'pickup'; // 'pickup' or 'dropoff'
        let routeMap;
        let directionsService;
        let directionsRenderer;
        let pickupLatLng;
        let dropoffLatLng;
        
        // Initialize Google Maps when API is loaded
        function initMap() {
            console.log('Google Maps API initialized');
            
            // Setup driver map
            setupDriverMap();
            
            // Initialize DataTables for bookings
            initializeDataTables();
            
            // Setup tab functionality
            setupStatusTabs();
            
            // Setup booking view modal
            setupViewBookingModal();
            
            // Setup booking edit modal
            setupEditBookingModal();
            
            // Setup add booking modal
            setupAddBookingModal();

            // Setup driver details modal
            setupDriverDetailsModal();
            
            // Setup status update functionality
            setupStatusUpdateButtons();
            
            // Setup map refresh
            $('.refresh-map').on('click', function() {
                refreshMap();
            });
            
            // Setup simulate locations
            $('.simulate-locations').on('click', function() {
                simulateDriverLocations();
            });
            
            // Setup customer location selection
            setupCustomerLocationSelection();
            
            // Setup nearest driver dispatch
            setupNearestDriverDispatch();
            
            // Auto-refresh map every 30 seconds
            setInterval(refreshMap, 30000);
            
            // Assign driver button click handler
            $(document).on('click', '.btn-assign-driver', function() {
                const bookingId = $(this).data('id');
                assignDriverToBooking(bookingId);
            });
        }
        
        // Document ready function
        $(document).ready(function() {
            // If Google Maps API is already loaded
            if (typeof google !== 'undefined' && google.maps) {
                initMap();
            }
        });
        
        // Initialize DataTables
        function initializeDataTables() {
            try {
                $('.booking-table').DataTable({
                    order: [[0, 'desc']],
                    pageLength: 10,
                    responsive: true,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search Bookings...",
                        emptyTable: "No bookings found",
                        info: "Showing _START_ to _END_ of _TOTAL_ bookings",
                    }
                });
            } catch (error) {
                console.error('Error initializing DataTable:', error);
            }
        }
        
        // Setup status tabs
        function setupStatusTabs() {
            // Handle tab changes
            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const status = $(e.target).data('status');
                
                // Show all rows if 'all' status, otherwise filter by status
                if (status === 'all') {
                    $('.booking-table tbody tr').show();
                } else {
                    $('.booking-table tbody tr').hide();
                    $(`.booking-table tbody tr[data-status="${status}"]`).show();
                }
            });
        }
        
        // Setup status update buttons
        function setupStatusUpdateButtons() {
            $('.update-status').on('click', function() {
                const bookingId = $(this).data('id');
                const newStatus = $(this).data('status');
                
                updateBookingStatus(bookingId, newStatus);
            });
        }
        
        // Update booking status
        function updateBookingStatus(bookingId, newStatus) {
            // Show confirmation dialog
            Swal.fire({
                title: 'Confirm Status Change',
                text: `Are you sure you want to change the status of this booking to ${newStatus.replace('_', ' ')}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, update it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading indicator
                    Swal.fire({
                        title: 'Updating...',
                        html: `<div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <p class="mt-2">Updating booking status...</p>`,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Send AJAX request to update status
                    $.ajax({
                        url: 'api/bookings/update_status.php',
                        type: 'POST',
                        data: {
                            booking_id: bookingId,
                            status: newStatus
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Show success message
                                Swal.fire({
                                    title: 'Success!',
                                    text: 'Booking status updated successfully.',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Reload the page to show updated data
                                    window.location.reload();
                                });
                            } else {
                                // Show error message
                                Swal.fire({
                                    title: 'Error',
                                    text: response.message || 'Failed to update booking status.',
                                    icon: 'error'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error updating booking status:', error, xhr.responseText);
                            
                            // Show error message
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred while updating the booking status. Please try again.',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        }
        
        // Setup view booking modal
        function setupViewBookingModal() {
            // Handle view booking button click
            $('.view-booking-btn').on('click', function() {
                const bookingId = $(this).data('id');
                viewBookingDetails(bookingId);
            });
        }
        
        // View booking details
        function viewBookingDetails(bookingId) {
            const modal = $('#viewBookingModal');
            const modalBody = modal.find('.modal-body');
            const progressBar = modal.find('.progress-bar');
            
            // Reset progress bar
            progressBar.css('width', '0%').removeClass('bg-danger').addClass('bg-info');
            
            // Show loading spinner
            modalBody.html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-3">Loading booking details...</div>
                </div>
            `);
            
            // Animate progress bar
            progressBar.animate({width: '50%'}, 500);
            
            // Fetch booking details
            $.ajax({
                url: `api/bookings/get_details.php?booking_id=${bookingId}`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    progressBar.animate({width: '100%'}, 500);
                    
                    if (response.success && response.data) {
                        const booking = response.data;
                        
                        // Build HTML with booking details
                        const detailsHtml = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Booking Information</h5>
                                    <div class="mb-3">
                                        <div class="text-muted small">Booking ID</div>
                                        <div class="fw-bold">${booking.booking_id}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Status</div>
                                        <div>
                                            <span class="status-badge ${booking.booking_status}-badge">
                                                ${booking.booking_status.charAt(0).toUpperCase() + booking.booking_status.slice(1)}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Pickup Location</div>
                                        <div>${booking.pickup_location}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Dropoff Location</div>
                                        <div>${booking.dropoff_location}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Pickup Time</div>
                                        <div>${new Date(booking.pickup_datetime).toLocaleString()}</div>
                                    </div>
                                    ${booking.dropoff_datetime ? `
                                        <div class="mb-3">
                                            <div class="text-muted small">Expected Dropoff Time</div>
                                            <div>${new Date(booking.dropoff_datetime).toLocaleString()}</div>
                                        </div>
                                    ` : ''}
                                    ${booking.special_instructions ? `
                                        <div class="mb-3">
                                            <div class="text-muted small">Special Instructions</div>
                                            <div class="p-2 bg-light rounded">${booking.special_instructions}</div>
                                        </div>
                                    ` : ''}
                                    ${booking.cancellation_reason ? `
                                        <div class="mb-3">
                                            <div class="text-muted small">Cancellation Reason</div>
                                            <div class="p-2 bg-light rounded text-danger">${booking.cancellation_reason}</div>
                                        </div>
                                    ` : ''}
                                </div>
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Customer Information</h5>
                                    ${booking.customer_firstname ? `
                                        <div class="mb-3">
                                            <div class="text-muted small">Customer</div>
                                            <div class="fw-bold">${booking.customer_firstname} ${booking.customer_lastname}</div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="text-muted small">Contact</div>
                                            <div>
                                                <i class="fas fa-phone me-1"></i> ${booking.customer_phone || 'N/A'}<br>
                                                <i class="fas fa-envelope me-1"></i> ${booking.customer_email || 'N/A'}
                                            </div>
                                        </div>
                                    ` : '<div class="alert alert-warning">No customer information available.</div>'}
                                    
                                    <h5 class="border-bottom pb-2 mb-3 mt-4">Vehicle & Driver</h5>
                                    ${booking.vehicle_model ? `
                                        <div class="mb-3">
                                            <div class="text-muted small">Vehicle</div>
                                            <div>
                                                ${booking.vehicle_model} (${booking.vehicle_year})<br>
                                                <div class="text-muted small">Plate: ${booking.plate_number}</div>
                                                <div class="text-muted small">Capacity: ${booking.vehicle_capacity} passengers</div>
                                            </div>
                                        </div>
                                    ` : '<div class="alert alert-info">No vehicle assigned.</div>'}
                                    
                                    ${booking.driver_firstname ? `
                                        <div class="mb-3">
                                            <div class="text-muted small">Driver</div>
                                            <div class="fw-bold">${booking.driver_firstname} ${booking.driver_lastname}</div>
                                            <div>
                                                <i class="fas fa-phone me-1"></i> ${booking.driver_phone || 'N/A'}<br>
                                                <i class="fas fa-id-card me-1"></i> License: ${booking.license_number || 'N/A'}
                                            </div>
                                        </div>
                                    ` : '<div class="alert alert-info">No driver assigned.</div>'}
                                    
                                    <h5 class="border-bottom pb-2 mb-3 mt-4">Trip Details</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="text-muted small">Distance</div>
                                                <div>${booking.distance_km ? booking.distance_km + ' km' : 'N/A'}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="text-muted small">Duration</div>
                                                <div>${booking.duration_minutes ? booking.duration_minutes + ' min' : 'N/A'}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Fare Amount</div>
                                        <div class="fw-bold fs-4 text-success">
                                            ${booking.fare_amount ? '$' + parseFloat(booking.fare_amount).toFixed(2) : 'Not set'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        modalBody.html(detailsHtml);
                        
                        // Update the edit button
                        const editBtn = modal.find('.edit-booking-from-view');
                        if (['pending', 'confirmed'].includes(booking.booking_status)) {
                            editBtn.show().off('click').on('click', function() {
                                $('#viewBookingModal').modal('hide');
                                $('#editBookingModal').modal('show');
                                loadBookingEditForm(booking.booking_id);
                            });
                        } else {
                            editBtn.hide();
                        }
                    } else {
                        progressBar.removeClass('bg-info').addClass('bg-danger');
                        modalBody.html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${response.message || 'Failed to load booking details.'}
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    progressBar.css('width', '100%').removeClass('bg-info').addClass('bg-danger');
                    
                    // Try to parse the response if it's JSON
                    let errorMessage = error || 'Unknown error';
                    let responseDetails = '';
                    
                    try {
                        // Check if there's a responseText
                        if (xhr.responseText) {
                            // Log for debugging
                            console.log('Raw response:', xhr.responseText);
                            
                            // Try to parse it as JSON first
                            try {
                                const jsonResponse = JSON.parse(xhr.responseText);
                                if (jsonResponse.message) {
                                    errorMessage = jsonResponse.message;
                                }
                            } catch (jsonError) {
                                // Not valid JSON, so get a snippet to help debug
                                responseDetails = `<div class="mt-2 small text-break">
                                    <strong>Response snippet:</strong> 
                                    ${xhr.responseText.substring(0, 100)}${xhr.responseText.length > 100 ? '...' : ''}
                                </div>`;
                            }
                        }
                    } catch (e) {
                        console.error('Error processing error response:', e);
                    }
                    
                    modalBody.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            An error occurred while loading the booking details.
                            <div class="mt-2 small"><strong>Error:</strong> ${errorMessage}</div>
                            ${responseDetails}
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-secondary retry-load-booking">
                                    <i class="fas fa-sync-alt me-1"></i> Retry
                                </button>
                            </div>
                        </div>
                    `);
                    
                    // Add retry functionality
                    $('.retry-load-booking').on('click', function() {
                        viewBookingDetails(bookingId);
                    });
                    
                    console.error('Error loading booking details:', error, xhr);
                }
            });
        }
        
        function refreshMap() {
            console.log('Refreshing map and driver locations...');
            
            // Fetch the latest driver locations
            $.ajax({
                url: 'api/drivers/get_all_drivers.php?t=' + new Date().getTime(),
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Driver locations refresh response:', response);
                    
                    if (response.success) {
                        availableDrivers = response.data;
                        addDriverMarkers();
                        updateDriverList();
                    } else {
                        console.error('Error refreshing driver locations:', response.message);
                        Swal.fire({
                            title: 'Error',
                            text: response.message || 'Failed to refresh driver locations.',
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error refreshing driver locations:', error);
                    console.log('XHR Status:', status);
                    console.log('XHR Response Text:', xhr.responseText);
                    
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to refresh the map. Please try again.',
                        icon: 'error'
                    });
                }
            });
        }
        
        function updateDriverList() {
            const driverList = $('.driver-list');
            driverList.empty();
            
            if (availableDrivers.length === 0) {
                driverList.html(`
                    <div class="alert alert-info">
                        No drivers currently available.
                    </div>
                `);
                return;
            }
            
            availableDrivers.forEach(driver => {
                // Determine if driver has valid location data
                const hasLocation = driver.latitude && driver.longitude && 
                                  !isNaN(parseFloat(driver.latitude)) && 
                                  !isNaN(parseFloat(driver.longitude)) &&
                                  (parseFloat(driver.latitude) !== 0 && parseFloat(driver.longitude) !== 0);
                
                // Format location updated time if available
                let updatedTime = '';
                if (driver.last_updated) {
                    // Create a more readable format from the timestamp
                    const updatedDate = new Date(driver.last_updated);
                    // Check if it's a valid date
                    if (!isNaN(updatedDate.getTime())) {
                        updatedTime = `<div class="small text-success">
                            <i class="fas fa-clock me-1"></i> Updated: ${updatedDate.toLocaleString()}
                        </div>`;
                    }
                }
                
                // Create a location dot indicator - green if GPS is active, orange if not
                const locationDot = hasLocation ? 
                    `<span class="badge bg-success rounded-circle" style="width: 12px; height: 12px; display: inline-block; margin-right: 5px;"></span>` : 
                    `<span class="badge bg-warning rounded-circle" style="width: 12px; height: 12px; display: inline-block; margin-right: 5px;"></span>`;
                
                // Add driver status badge
                let statusBadge = '';
                if (driver.status) {
                    let statusClass = 'bg-secondary';
                    if (driver.status === 'available') statusClass = 'bg-success';
                    if (driver.status === 'busy') statusClass = 'bg-warning';
                    if (driver.status === 'offline') statusClass = 'bg-danger';
                    
                    statusBadge = `<span class="badge ${statusClass} ms-1">${driver.status}</span>`;
                }
                
                // Get coordinates for data attributes
                const lat = hasLocation ? parseFloat(driver.latitude) : 0;
                const lng = hasLocation ? parseFloat(driver.longitude) : 0;
                
                // Add a special style for drivers with active locations
                const cardStyle = hasLocation ? 'border-left: 3px solid #28a745;' : '';
                    
                driverList.append(`
                    <div class="card driver-card mb-2" data-driver-id="${driver.driver_id}" 
                         data-lat="${lat}" data-lng="${lng}" style="${cardStyle}">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    ${locationDot} ${driver.firstname} ${driver.lastname}
                                </h6>
                                <div>
                                    ${statusBadge}
                                </div>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-phone me-1"></i> ${driver.phone || 'N/A'}
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-id-card me-1"></i> License: ${driver.license_number || 'N/A'}
                            </div>
                            ${updatedTime}
                            ${hasLocation ? 
                                `<div class="small text-primary">
                                    <i class="fas fa-map-marker-alt me-1"></i> GPS active
                                </div>` : 
                                `<div class="small text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i> No GPS data
                                </div>`
                            }
                                </div>
                        <a href="#" class="btn btn-primary btn-sm view-driver-details d-block border-top-0 rounded-0 rounded-bottom" 
                           data-bs-toggle="modal" 
                           data-bs-target="#viewDriverDetailsModal" 
                           data-driver-id="${driver.driver_id}">
                            <i class="fas fa-info-circle"></i> View Details
                        </a>
                    </div>
                `);
            });
            
            // Reattach click handlers for the driver cards
            $('.driver-card').on('click', function() {
                const driverId = $(this).data('driver-id');
                const lat = parseFloat($(this).data('lat'));
                const lng = parseFloat($(this).data('lng'));
                
                // Center the map on this driver only if they have valid coordinates
                if (lat && lng && !isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
                    map.setCenter({ lat, lng });
                    map.setZoom(14);
                    
                    // Highlight this driver marker
                    if (driverMarkers[driverId]) {
                        driverMarkers[driverId].setAnimation(google.maps.Animation.BOUNCE);
                        
                        // Stop animation after 1.5 seconds
                        setTimeout(() => {
                            if (driverMarkers[driverId]) {
                                driverMarkers[driverId].setAnimation(null);
                            }
                        }, 1500);
                    }
                } else {
                    // Show a notification that there's no location data
                    Swal.fire({
                        title: 'No Location Data',
                        text: 'This driver does not have active GPS coordinates',
                        icon: 'info',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
                
                // Toggle the active class
                $('.driver-card').removeClass('active');
                $(this).addClass('active');
            });
            
            // Add click handler for the view details buttons
            $('.view-driver-details').on('click', function(e) {
                e.stopPropagation(); // Prevent triggering the card click event
                const driverId = $(this).data('driver-id');
                viewDriverDetails(driverId);
            });
        }
        
        function setupEditBookingModal() {
            // Handle edit booking button click
            $(document).on('click', '.edit-booking-btn', function() {
                const bookingId = $(this).data('id');
                loadBookingEditForm(bookingId);
            });
            
            // Handle save button click
            $('#saveBookingBtn').on('click', function() {
                saveBookingEdit();
            });
        }
        
        function loadBookingEditForm(bookingId) {
            // Show loading spinner
            $('#editBookingContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading booking edit form...</p>
                </div>
            `);
            
            // Load the edit form via AJAX
            $.ajax({
                url: 'pages/booking/booking_edit_form.php',
                data: { booking_id: bookingId },
                method: 'GET',
                success: function(response) {
                    $('#editBookingContent').html(response);
                    
                    // Initialize date/time pickers and address autocomplete if needed
                    initializeFormElements();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading booking edit form:', error);
                    $('#editBookingContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading the edit form. Please try again.
                            <div class="mt-2 small">Error details: ${error || 'Unknown error'}</div>
                        </div>
                    `);
                }
            });
        }
        
        function saveBookingEdit() {
            // Get the form
            const $form = $('#editBookingForm');
            
            if ($form.length === 0) {
                console.error('Booking edit form not found');
                return;
            }
            
            // Validate the form
            if (!$form[0].checkValidity()) {
                $form.addClass('was-validated');
                return;
            }
            
            // Show loading indicator
            Swal.fire({
                title: 'Saving...',
                html: `<div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <p class="mt-2">Saving booking changes...</p>`,
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create FormData object
            const formData = new FormData($form[0]);
            
            // Submit the form via AJAX
            $.ajax({
                url: 'api/bookings/update.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        Swal.fire({
                            title: 'Success!',
                            text: 'Booking information updated successfully.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Close the modal
                            $('#editBookingModal').modal('hide');
                            
                            // Reload the page to show updated data
                            window.location.reload();
                        });
                    } else {
                        // Show error message
                        Swal.fire({
                            title: 'Error',
                            text: response.message || 'Failed to update booking information.',
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error submitting form:', error, xhr.responseText);
                    
                    // Show error message
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while saving the booking information. Please try again.',
                        icon: 'error'
                    });
                }
            });
        }
        
        function setupAddBookingModal() {
            // Load customers, vehicles, and drivers when modal opens
            $('#addBookingModal').on('show.bs.modal', function() {
                // Reset form
                $('#addBookingForm')[0].reset();
                $('#pickup_lat, #pickup_lng, #dropoff_lat, #dropoff_lng').val('');
                $('#routeDistance, #routeDuration, #routeFare').text('-');
                
                // Load customers from core1_movers database
                loadCustomersFromCore1();
                
                // Load available drivers with vehicles
                loadAvailableDriversWithVehicles();
                
                // Initialize route map if Google Maps is available
                if (typeof google !== 'undefined' && google.maps) {
                    initRouteMap();
                }
                
                // Set default pickup time to current time + 1 hour
                const defaultPickupTime = new Date();
                defaultPickupTime.setHours(defaultPickupTime.getHours() + 1);
                
                // Format for datetime-local input (YYYY-MM-DDThh:mm)
                const formattedDate = defaultPickupTime.toISOString().slice(0, 16);
                $('#pickup_datetime').val(formattedDate);
            });
            
            // Initialize Place Autocomplete for pickup and dropoff locations
            if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                const pickupInput = document.getElementById('pickup_location');
                const dropoffInput = document.getElementById('dropoff_location');
                
                if (pickupInput && dropoffInput) {
                    const pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput);
                    const dropoffAutocomplete = new google.maps.places.Autocomplete(dropoffInput);
                    
                    // Handle place selection for pickup
                    pickupAutocomplete.addListener('place_changed', function() {
                        const place = pickupAutocomplete.getPlace();
                        if (place.geometry && place.geometry.location) {
                            $('#pickup_lat').val(place.geometry.location.lat());
                            $('#pickup_lng').val(place.geometry.location.lng());
                            pickupLatLng = place.geometry.location;
                        }
                    });
                    
                    // Handle place selection for dropoff
                    dropoffAutocomplete.addListener('place_changed', function() {
                        const place = dropoffAutocomplete.getPlace();
                        if (place.geometry && place.geometry.location) {
                            $('#dropoff_lat').val(place.geometry.location.lat());
                            $('#dropoff_lng').val(place.geometry.location.lng());
                            dropoffLatLng = place.geometry.location;
                        }
                    });
                }
            }
            
            // Location Map Modal handlers
            $('#locationMapModal').on('shown.bs.modal', initLocationPickerMap);
            $('#mapSearchButton').on('click', searchMapLocation);
            $('#confirmLocationBtn').on('click', confirmSelectedLocation);
            $('#calculateRouteBtn').on('click', calculateRoute);
            
            // Setup driver sorting
            $('#driver_sort').on('change', sortDriverList);
            
            // Handle create button click
            $('#createBookingBtn').on('click', function() {
                createNewBooking();
            });
        }
        
        function initRouteMap() {
            // Create a map centered at a default location (e.g., Manila)
            const defaultLocation = { lat: 14.5995, lng: 120.9842 }; // Manila
            
            routeMap = new google.maps.Map(document.getElementById('routeMap'), {
                center: defaultLocation,
                zoom: 10,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });
            
            // Initialize directions renderer
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: routeMap,
                suppressMarkers: false,
                polylineOptions: {
                    strokeColor: '#4285F4',
                    strokeWeight: 5
                }
            });
        }
        
        function initLocationPickerMap() {
            // Get the type of location (pickup or dropoff)
            currentLocationType = $('#locationMapModal').data('location-type') || 'pickup';
            
            // Update modal title based on location type
            $('#locationMapModalLabel').text(`Select ${currentLocationType.charAt(0).toUpperCase() + currentLocationType.slice(1)} Location`);
            
            // Create a map centered at a default location (e.g., Manila)
            const defaultLocation = { lat: 14.5995, lng: 120.9842 }; // Manila
            
            if (!locationPickerMap) {
                locationPickerMap = new google.maps.Map(document.getElementById('locationPickerMap'), {
                    center: defaultLocation,
                    zoom: 12,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: false
                });
                
                // Create a marker for selected location
                locationMarker = new google.maps.Marker({
                    position: defaultLocation,
                    map: locationPickerMap,
                    draggable: true,
                    animation: google.maps.Animation.DROP
                });
                
                // When marker is dragged, update selected address
                locationMarker.addListener('dragend', function() {
                    updateSelectedAddress(locationMarker.getPosition());
                });
                
                // When map is clicked, move marker and update selected address
                locationPickerMap.addListener('click', function(event) {
                    locationMarker.setPosition(event.latLng);
                    updateSelectedAddress(event.latLng);
                });
                
                // Initialize search box for location search
                const searchInput = document.getElementById('mapSearchInput');
                const searchBox = new google.maps.places.SearchBox(searchInput);
                
                // Bias the SearchBox results towards current map's viewport
                locationPickerMap.addListener('bounds_changed', function() {
                    searchBox.setBounds(locationPickerMap.getBounds());
                });
                
                // Listen for the event fired when the user selects a prediction
                searchBox.addListener('places_changed', function() {
                    const places = searchBox.getPlaces();
                    
                    if (places.length === 0) {
                        return;
                    }
                    
                    // For each place, get the location and set marker
                    const bounds = new google.maps.LatLngBounds();
                    places.forEach(function(place) {
                        if (!place.geometry || !place.geometry.location) {
                            console.log("Returned place contains no geometry");
                            return;
                        }
                        
                        // Set the marker to the found place
                        locationMarker.setPosition(place.geometry.location);
                        updateSelectedAddress(place.geometry.location);
                        
                        if (place.geometry.viewport) {
                            // Only geocodes have viewport
                            bounds.union(place.geometry.viewport);
                        } else {
                            bounds.extend(place.geometry.location);
                        }
                    });
                    locationPickerMap.fitBounds(bounds);
                });
            }
            
            // Check if there's already a selected location for this type
            const lat = currentLocationType === 'pickup' ? $('#pickup_lat').val() : $('#dropoff_lat').val();
            const lng = currentLocationType === 'pickup' ? $('#pickup_lng').val() : $('#dropoff_lng').val();
            
            if (lat && lng && !isNaN(parseFloat(lat)) && !isNaN(parseFloat(lng))) {
                // Use existing coordinates
                const position = new google.maps.LatLng(parseFloat(lat), parseFloat(lng));
                locationMarker.setPosition(position);
                locationPickerMap.setCenter(position);
                locationPickerMap.setZoom(15);
                updateSelectedAddress(position);
            } else {
                // Try to geocode the current address in the input field
                const address = currentLocationType === 'pickup' ? $('#pickup_location').val() : $('#dropoff_location').val();
                if (address) {
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: address }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            const position = results[0].geometry.location;
                            locationMarker.setPosition(position);
                            locationPickerMap.setCenter(position);
                            locationPickerMap.setZoom(15);
                            updateSelectedAddress(position);
                        }
                    });
                }
            }
        }
        
        function searchMapLocation() {
            const searchText = $('#mapSearchInput').val();
            if (!searchText) return;
            
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ address: searchText }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    const location = results[0].geometry.location;
                    locationMarker.setPosition(location);
                    locationPickerMap.setCenter(location);
                    locationPickerMap.setZoom(15);
                    updateSelectedAddress(location);
                } else {
                    Swal.fire({
                        title: 'Location Not Found',
                        text: 'Could not find the location. Please try a different search term.',
                        icon: 'warning'
                    });
                }
            });
        }
        
        function updateSelectedAddress(latLng) {
            // Use reverse geocoding to get address from coordinates
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ location: latLng }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    $('#selectedAddress').text(results[0].formatted_address);
                } else {
                    $('#selectedAddress').text(`Lat: ${latLng.lat().toFixed(6)}, Lng: ${latLng.lng().toFixed(6)}`);
                }
            });
        }
        
        function confirmSelectedLocation() {
            // Get the position of the marker
            const position = locationMarker.getPosition();
            
            // Get the formatted address
            const address = $('#selectedAddress').text();
            
            // Update the appropriate input field based on location type
            if (currentLocationType === 'pickup') {
                $('#pickup_location').val(address);
                $('#pickup_lat').val(position.lat());
                $('#pickup_lng').val(position.lng());
                pickupLatLng = position;
            } else {
                $('#dropoff_location').val(address);
                $('#dropoff_lat').val(position.lat());
                $('#dropoff_lng').val(position.lng());
                dropoffLatLng = position;
            }
            
            // Reset driver sorting if we now have both pickup and dropoff coordinates
            if ($('#pickup_lat').val() && $('#pickup_lng').val() && $('#dropoff_lat').val() && $('#dropoff_lng').val()) {
                loadAvailableDriversWithVehicles();
            }
            
            // Close the modal
            $('#locationMapModal').modal('hide');
        }
        
        function calculateRoute() {
            // Check if we have both pickup and dropoff locations
            if (!pickupLatLng || !dropoffLatLng) {
                Swal.fire({
                    title: 'Missing Locations',
                    text: 'Please select both pickup and dropoff locations first.',
                    icon: 'warning'
                });
                return;
            }
            
            // Request directions
            const directionsService = new google.maps.DirectionsService();
            const request = {
                origin: pickupLatLng,
                destination: dropoffLatLng,
                travelMode: google.maps.TravelMode.DRIVING
            };
            
            directionsService.route(request, function(response, status) {
                if (status === 'OK') {
                    // Display the route on the map
                    directionsRenderer.setDirections(response);
                    
                    // Get distance and duration
                    const route = response.routes[0];
                    if (route.legs.length > 0) {
                        const leg = route.legs[0];
                        
                        // Update distance and duration fields
                        const distanceInMeters = leg.distance.value;
                        const distanceInKm = (distanceInMeters / 1000).toFixed(2);
                        const durationInSeconds = leg.duration.value;
                        const durationInMinutes = Math.ceil(durationInSeconds / 60);
                        
                        $('#distance_km').val(distanceInKm);
                        $('#duration_minutes').val(durationInMinutes);
                        
                        // Update route info display
                        $('#routeDistance').text(leg.distance.text);
                        $('#routeDuration').text(leg.duration.text);
                        
                        // Calculate fare (example formula)
                        const baseFare = 50; // Base fare in ₱
                        const ratePerKm = 15; // Rate per km in ₱
                        const estimatedFare = baseFare + (distanceInKm * ratePerKm);
                        
                        $('#fare_amount').val(estimatedFare.toFixed(2));
                        $('#routeFare').text('₱' + estimatedFare.toFixed(2));
                        
                        // Update driver list sorting if sorted by nearest
                        if ($('#driver_sort').val() === 'nearest') {
                            sortDriverList();
                        }
                    }
                } else {
                    Swal.fire({
                        title: 'Route Calculation Failed',
                        text: 'Could not calculate a route between the selected locations.',
                        icon: 'error'
                    });
                }
            });
        }
        
        function loadCustomersFromCore1() {
            $.ajax({
                url: 'api/customers/get_core1_customers.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const $select = $('#customer_id');
                        $select.find('option:not(:first)').remove();
                        
                        response.data.forEach(customer => {
                            $select.append(`<option value="${customer.customer_id}">${customer.firstname} ${customer.lastname} (${customer.phone})</option>`);
                        });
                    } else {
                        console.error('Error loading customers:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading customers:', error);
                }
            });
        }
        
        function loadAvailableDriversWithVehicles() {
            // Show loading state
            $('#availableDriversList').html(`
                <div class="list-group-item text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Loading available drivers...</span>
                </div>
            `);
            
            // Fetch drivers with assigned vehicles from API
            $.ajax({
                url: 'api/drivers/get_available_with_vehicles.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Store drivers data globally
                        const driversWithVehicles = response.data;
                        
                        // Sort drivers based on current sort setting
                        sortAndDisplayDrivers(driversWithVehicles);
                    } else {
                        $('#availableDriversList').html(`
                            <div class="list-group-item text-center py-3 text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${response.message || 'Error loading drivers'}
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading drivers with vehicles:', error);
                    $('#availableDriversList').html(`
                        <div class="list-group-item text-center py-3 text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading drivers. Please try again.
                        </div>
                    `);
                }
            });
        }
        
        function sortAndDisplayDrivers(drivers) {
            // Get current sort method
            const sortMethod = $('#driver_sort').val();
            
            // Sort the drivers based on the selected method
            if (sortMethod === 'nearest') {
                // Calculate distance from pickup for each driver if coordinates are available
                if (pickupLatLng) {
                    drivers.forEach(driver => {
                        if (driver.latitude && driver.longitude && 
                            driver.latitude !== '0' && driver.longitude !== '0') {
                            const driverLatLng = new google.maps.LatLng(
                                parseFloat(driver.latitude), 
                                parseFloat(driver.longitude)
                            );
                            // Calculate distance in km
                            driver.distance = google.maps.geometry.spherical.computeDistanceBetween(
                                pickupLatLng, driverLatLng
                            ) / 1000;
                        } else {
                            driver.distance = Infinity; // Put drivers without location at the end
                        }
                    });
                    
                    // Sort by distance
                    drivers.sort((a, b) => a.distance - b.distance);
                }
            } else if (sortMethod === 'status') {
                // First by status (available first, then busy, then others)
                // Then alphabetically by name
                drivers.sort((a, b) => {
                    if (a.status === 'available' && b.status !== 'available') return -1;
                    if (a.status !== 'available' && b.status === 'available') return 1;
                    if (a.status === 'busy' && b.status !== 'busy') return -1;
                    if (a.status !== 'busy' && b.status === 'busy') return 1;
                    return `${a.firstname} ${a.lastname}`.localeCompare(`${b.firstname} ${b.lastname}`);
                });
            } else {
                // Sort alphabetically by name
                drivers.sort((a, b) => 
                    `${a.firstname} ${a.lastname}`.localeCompare(`${b.firstname} ${b.lastname}`)
                );
            }
            
            // Generate HTML for the drivers list
            let html = '';
            
            if (drivers.length === 0) {
                html = `
                    <div class="list-group-item text-center py-3">
                        <i class="fas fa-info-circle me-2"></i>
                        No drivers with vehicles available
                    </div>
                `;
            } else {
                drivers.forEach(driver => {
                    const hasLocation = driver.latitude && driver.longitude && 
                                      driver.latitude !== '0' && driver.longitude !== '0';
                    
                    const distanceText = driver.distance ? 
                                       `<div class="small text-muted mb-1"><i class="fas fa-route me-1"></i> ${driver.distance.toFixed(1)} km away</div>` : '';
                    
                    html += `
                        <div class="list-group-item driver-item py-2" data-driver-id="${driver.driver_id}" data-vehicle-id="${driver.vehicle_id}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">${driver.firstname} ${driver.lastname}</div>
                                    <div class="small text-muted mb-1"><i class="fas fa-phone me-1"></i> ${driver.phone}</div>
                                    ${distanceText}
                                    <div class="small">
                                        <span class="badge bg-${driver.status === 'available' ? 'success' : (driver.status === 'busy' ? 'warning' : 'secondary')}">
                                            ${driver.status}
                                        </span>
                                        ${hasLocation ? '<span class="badge bg-info ms-1">GPS active</span>' : ''}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="small fw-bold mb-1">${driver.vehicle_model}</div>
                                    <div class="small text-muted">${driver.plate_number}</div>
                                    <div class="small text-muted">${driver.vehicle_year} · ${driver.capacity} seats</div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            // Update the list
            $('#availableDriversList').html(html);
            
            // Add click event to driver items
            $('.driver-item').on('click', function() {
                // Remove active class from all drivers
                $('.driver-item').removeClass('active');
                
                // Add active class to selected driver
                $(this).addClass('active');
                
                // Get driver and vehicle data
                const driverId = $(this).data('driver-id');
                const vehicleId = $(this).data('vehicle-id');
                
                // Set hidden input values
                $('#driver_id').val(driverId);
                $('#vehicle_id').val(vehicleId);
            });
        }
        
        function sortDriverList() {
            const sortMethod = $('#driver_sort').val();
            
            // Get current drivers list from the API again
            loadAvailableDriversWithVehicles();
        }
        
        function createNewBooking() {
            // Get the form
            const $form = $('#addBookingForm');
            
            // Validate the form
            if (!$form[0].checkValidity()) {
                $form.addClass('was-validated');
                return;
            }
            
            // Additional validation
            if (!$('#driver_id').val()) {
                Swal.fire({
                    title: 'Driver Required',
                    text: 'Please select a driver for this booking.',
                    icon: 'warning'
                });
                return;
            }
            
            // Show loading indicator
            Swal.fire({
                title: 'Creating Booking...',
                html: `<div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <p class="mt-2">Creating new booking...</p>`,
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create FormData object
            const formData = new FormData($form[0]);
            
            // Submit the form via AJAX
            $.ajax({
                url: 'api/bookings/create.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        Swal.fire({
                            title: 'Success!',
                            text: 'New booking created successfully.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Close the modal
                            $('#addBookingModal').modal('hide');
                            
                            // Reload the page to show the new booking
                            window.location.reload();
                        });
                    } else {
                        // Show error message
                        Swal.fire({
                            title: 'Error',
                            text: response.message || 'Failed to create booking.',
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error creating booking:', error, xhr.responseText);
                    
                    // Show error message
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while creating the booking. Please try again.',
                        icon: 'error'
                    });
                }
            });
        }
        
        // Setup driver details modal
        function setupDriverDetailsModal() {
            // Handle click on driver details button
            $(document).on('click', '.view-driver-details', function(e) {
                e.preventDefault(); // Prevent default anchor behavior
                e.stopPropagation(); // Prevent the card click event
                
                const driverId = $(this).data('driver-id');
                viewDriverDetails(driverId);
            });
        }
        
        // View driver details
        function viewDriverDetails(driverId) {
            const modal = $('#viewDriverDetailsModal');
            const modalBody = modal.find('.driver-details-content');
            const progressBar = modal.find('.progress-bar');
            
            // Reset progress bar
            progressBar.css('width', '0%').removeClass('bg-danger').addClass('bg-info');
            
            // Show loading spinner
            modalBody.html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-3">Loading driver details...</div>
                </div>
            `);
            
            // Animate progress bar
            progressBar.animate({width: '50%'}, 500);
            
            // Fetch driver details - now using proper authentication
            $.ajax({
                url: `api/drivers/get_details.php?driver_id=${driverId}`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    progressBar.animate({width: '100%'}, 500);
                    
                    if (response.success && response.data) {
                        const driver = response.data;
                        const currentBooking = response.current_booking;
                        
                        // Set the modal title
                        $('#viewDriverDetailsModalLabel').html(
                            `<i class="fas fa-user-tie me-2"></i> ${driver.firstname} ${driver.lastname}`
                        );
                        
                        // Build HTML with driver details
                        let detailsHtml = `
                            <div class="row">
                                <div class="col-md-4 text-center mb-4">
                                    <div class="avatar-wrapper bg-light rounded-circle mx-auto mb-3" style="width: 150px; height: 150px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user-tie fa-5x text-primary"></i>
                                    </div>
                                    <h4>${driver.firstname} ${driver.lastname}</h4>
                                    <p class="badge ${driver.status === 'available' ? 'bg-success' : 'bg-warning'} mb-2">
                                        ${driver.status.charAt(0).toUpperCase() + driver.status.slice(1)}
                                    </p>
                                    <div>
                                        <i class="fas fa-star text-warning"></i> 
                                        <span class="fw-bold">${driver.rating ? driver.rating + '/5' : 'No ratings yet'}</span>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="border-bottom pb-2 mb-3">Personal Information</h5>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="text-muted small">Driver ID</div>
                                            <div class="fw-bold">${driver.driver_id}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">User ID</div>
                                            <div class="fw-bold">${driver.user_id}</div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="text-muted small">Phone</div>
                                            <div><i class="fas fa-phone me-1"></i> ${driver.phone}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Email</div>
                                            <div><i class="fas fa-envelope me-1"></i> ${driver.email || 'N/A'}</div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="text-muted small">License Number</div>
                                            <div>${driver.license_number}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">License Expiry</div>
                                            <div>${driver.license_expiry ? new Date(driver.license_expiry).toLocaleDateString() : 'N/A'}</div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="border-bottom pb-2 mb-3 mt-4">Current Location</h5>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="text-muted small">Location Status</div>
                                            <div>
                                                ${driver.latitude && driver.longitude && driver.latitude !== '0' && driver.longitude !== '0' 
                                                    ? '<span class="badge bg-success"><i class="fas fa-map-marker-alt me-1"></i> GPS Active</span>' 
                                                    : '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i> No GPS Data</span>'}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small">Last Updated</div>
                                            <div>${driver.location_updated_at ? new Date(driver.location_updated_at).toLocaleString() : 'N/A'}</div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="border-bottom pb-2 mb-3 mt-4">Current Booking Status</h5>
                                    <div class="current-booking-section p-3 ${currentBooking ? 'bg-light border rounded' : ''}">
                        `;
                        
                        if (currentBooking) {
                            // Driver has a current booking
                            detailsHtml += `
                                <div class="alert alert-info">
                                    <i class="fas fa-car me-2"></i> This driver is currently assigned to an active booking
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="text-muted small">Booking ID</div>
                                        <div class="fw-bold">#${currentBooking.booking_id}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Status</div>
                                        <div><span class="badge ${currentBooking.booking_status}-badge">${currentBooking.booking_status}</span></div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="text-muted small">Customer</div>
                                    <div>${currentBooking.customer_firstname || ''} ${currentBooking.customer_lastname || 'Unknown'}</div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="text-muted small">Pickup</div>
                                        <div>${currentBooking.pickup_location}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Dropoff</div>
                                        <div>${currentBooking.dropoff_location}</div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="text-muted small">Pickup Time</div>
                                        <div>${new Date(currentBooking.pickup_datetime).toLocaleString()}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted small">Estimated Fare</div>
                                        <div class="fw-bold text-success">₱${parseFloat(currentBooking.fare_amount || 0).toFixed(2)}</div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="#" class="btn btn-sm btn-primary view-booking-details" data-booking-id="${currentBooking.booking_id}">
                                        <i class="fas fa-calendar-alt me-1"></i> View Booking Details
                                    </a>
                                </div>
                            `;
                        } else {
                            // Driver has no current booking
                            detailsHtml += `
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i> This driver is available and not currently assigned to any active booking
                                </div>
                                <p class="text-center">
                                    <a href="#" class="btn btn-outline-primary assign-booking-btn" data-driver-id="${driver.driver_id}">
                                        <i class="fas fa-calendar-plus me-1"></i> Assign a Booking
                                    </a>
                                </p>
                            `;
                        }
                        
                        detailsHtml += `
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        modalBody.html(detailsHtml);
                        
                        // Add event listeners for buttons in the modal
                        $('.view-booking-details').on('click', function(e) {
                            e.preventDefault();
                            const bookingId = $(this).data('booking-id');
                            $('#viewDriverDetailsModal').modal('hide');
                            // Show booking details modal
                            $('#viewBookingModal').modal('show');
                            viewBookingDetails(bookingId);
                        });
                        
                        $('.assign-booking-btn').on('click', function(e) {
                            e.preventDefault();
                            const driverId = $(this).data('driver-id');
                            $('#viewDriverDetailsModal').modal('hide');
                            // Show assign booking dialog
                            assignBookingToDriver(driverId);
                        });
                        
                    } else {
                        // Error fetching driver details
                        progressBar.removeClass('bg-info').addClass('bg-danger');
                        modalBody.html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${response.message || 'Failed to load driver details.'}
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    progressBar.css('width', '100%').removeClass('bg-info').addClass('bg-danger');
                    
                    // Try to parse the response
                    let errorMessage = error || 'Unknown error';
                    let responseDetails = '';
                    
                    try {
                        if (xhr.responseText) {
                            console.log('Raw response:', xhr.responseText);
                            
                            try {
                                const jsonResponse = JSON.parse(xhr.responseText);
                                if (jsonResponse.message) {
                                    errorMessage = jsonResponse.message;
                                }
                            } catch (jsonError) {
                                responseDetails = `<div class="mt-2 small text-break">
                                    <strong>Response snippet:</strong> 
                                    ${xhr.responseText.substring(0, 100)}${xhr.responseText.length > 100 ? '...' : ''}
                                </div>`;
                            }
                        }
                    } catch (e) {
                        console.error('Error processing error response:', e);
                    }
                    
                    modalBody.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            An error occurred while loading the driver details.
                            <div class="mt-2 small"><strong>Error:</strong> ${errorMessage}</div>
                            ${responseDetails}
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-secondary retry-load-driver">
                                    <i class="fas fa-sync-alt me-1"></i> Retry
                                </button>
                            </div>
                        </div>
                    `);
                    
                    // Add retry functionality
                    $('.retry-load-driver').on('click', function() {
                        viewDriverDetails(driverId);
                    });
                    
                    console.error('Error loading driver details:', error, xhr);
                }
            });
        }
        
        // Function to assign a booking to a driver
        function assignBookingToDriver(driverId) {
            // Get list of pending bookings that need a driver
            $.ajax({
                url: 'api/bookings/get_pending.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        // Create booking selection options
                        let bookingOptions = '';
                        response.data.forEach(function(booking) {
                            const pickupTime = new Date(booking.pickup_datetime).toLocaleString();
                            bookingOptions += `<option value="${booking.booking_id}">
                                #${booking.booking_id} - ${booking.pickup_location} to ${booking.dropoff_location} (${pickupTime})
                            </option>`;
                        });
                        
                        // Show booking selection dialog
                        Swal.fire({
                            title: 'Assign Booking',
                            html: `
                                <div class="form-group">
                                    <label for="booking-select">Select Booking to Assign</label>
                                    <select id="booking-select" class="form-control">
                                        <option value="">-- Select a booking --</option>
                                        ${bookingOptions}
                                    </select>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Assign',
                            showLoaderOnConfirm: true,
                            preConfirm: () => {
                                const bookingId = document.getElementById('booking-select').value;
                                if (!bookingId) {
                                    Swal.showValidationMessage('Please select a booking');
                                    return false;
                                }
                                
                                return new Promise((resolve) => {
                                    $.ajax({
                                        url: 'api/bookings/assign_driver.php',
                                        type: 'POST',
                                        data: {
                                            booking_id: bookingId,
                                            driver_id: driverId
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                resolve(response);
                                            } else {
                                                Swal.showValidationMessage(response.message || 'Failed to assign driver');
                                                resolve(null);
                                            }
                                        },
                                        error: function() {
                                            Swal.showValidationMessage('Network error. Please try again.');
                                            resolve(null);
                                        }
                                    });
                                });
                            },
                            allowOutsideClick: () => !Swal.isLoading()
                        }).then((result) => {
                            if (result.isConfirmed && result.value) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Booking Assigned',
                                    text: 'The booking has been successfully assigned to this driver.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Reload page to refresh data
                                    window.location.reload();
                                });
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'No Bookings Available',
                            text: 'There are no pending bookings available to assign.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to fetch pending bookings. Please try again later.'
                    });
                }
            });
        }

        // Setup Driver Map
        function setupDriverMap() {
            // Create the map centered on a default location (e.g., Manila)
            const defaultLocation = { lat: 14.5995, lng: 120.9842 }; // Manila, Philippines
            
            map = new google.maps.Map(document.getElementById('driverMap'), {
                center: defaultLocation,
                zoom: 12,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true,
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ]
            });
            
            // Add drivers to the map
            addDriverMarkers();
            
            // Add customer markers if any
            addCustomerMarkers();
        }

        // Add driver markers to the map
        function addDriverMarkers() {
            // Clear existing markers first
            for (const driverId in driverMarkers) {
                if (driverMarkers[driverId]) {
                    driverMarkers[driverId].setMap(null);
                }
            }
            driverMarkers = {};
            
            // Add markers for each available driver
            availableDrivers.forEach(driver => {
                // Skip if no valid coordinates
                if (!driver.latitude || !driver.longitude || 
                    driver.latitude == 0 || driver.longitude == 0) {
                    return;
                }
                
                const driverLatLng = new google.maps.LatLng(
                    parseFloat(driver.latitude), 
                    parseFloat(driver.longitude)
                );
                
                // Create a custom marker icon
                const driverIcon = {
                    url: 'assets/img/car-marker.png', // Create this image or use a different icon
                    scaledSize: new google.maps.Size(32, 32),
                    origin: new google.maps.Point(0, 0),
                    anchor: new google.maps.Point(16, 16)
                };
                
                // Create the marker
                const marker = new google.maps.Marker({
                    position: driverLatLng,
                    map: map,
                    title: driver.firstname + ' ' + driver.lastname,
                    icon: driverIcon,
                    driverId: driver.driver_id
                });
                
                // Create an info window for this driver
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div class="driver-info-window">
                            <div class="fw-bold">${driver.firstname} ${driver.lastname}</div>
                            <div><strong>Status:</strong> ${driver.status}</div>
                            <div><strong>Phone:</strong> ${driver.phone}</div>
                            ${driver.vehicle_id ? 
                                `<div><strong>Vehicle:</strong> ${driver.vehicle_model} (${driver.plate_number})</div>` : 
                                ''}
                            <div class="mt-2">
                                <button class="btn btn-sm btn-primary view-driver-info-btn" 
                                        data-driver-id="${driver.driver_id}">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </div>
                        </div>
                    `
                });
                
                // Add click event to the marker
                marker.addListener('click', function() {
                    // Close any open info windows
                    for (const id in driverMarkers) {
                        if (driverMarkers[id].infoWindow) {
                            driverMarkers[id].infoWindow.close();
                        }
                    }
                    
                    // Open this driver's info window
                    infoWindow.open(map, marker);
                    
                    // Highlight the driver in the list
                    $('.driver-card').removeClass('active');
                    $(`.driver-card[data-driver-id="${driver.driver_id}"]`).addClass('active');
                    
                    // Attach click handlers to the info window buttons
                    google.maps.event.addListenerOnce(infoWindow, 'domready', function() {
                        $('.view-driver-info-btn').on('click', function() {
                            const driverId = $(this).data('driver-id');
                            infoWindow.close();
                            
                            // Open driver details modal
                            $('#viewDriverDetailsModal').modal('show');
                            viewDriverDetails(driverId);
                        });
                    });
                });
                
                // Store infoWindow reference
                marker.infoWindow = infoWindow;
                
                // Store marker reference in the global object
                driverMarkers[driver.driver_id] = marker;
            });
        }

        // Add customer markers to the map
        function addCustomerMarkers() {
            // Fetch nearby customers with location data
            $.ajax({
                url: 'api/customers/get_nearby_customers.php',
                method: 'GET',
                data: {
                    // Use current map center as reference point
                    latitude: map.getCenter().lat(),
                    longitude: map.getCenter().lng(),
                    max_distance: 25, // 25km radius
                    limit: 30 // Up to 30 customers
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        // Create customer markers
                        response.data.forEach(customer => {
                            // Skip if no valid coordinates
                            if (!customer.latitude || !customer.longitude || 
                                customer.latitude == 0 || customer.longitude == 0) {
                                return;
                            }
                            
                            const customerLatLng = new google.maps.LatLng(
                                parseFloat(customer.latitude), 
                                parseFloat(customer.longitude)
                            );
                            
                            // Create a custom marker icon
                            const customerIcon = {
                                url: 'assets/img/customer-marker.png', // Create this image
                                scaledSize: new google.maps.Size(24, 24),
                                origin: new google.maps.Point(0, 0),
                                anchor: new google.maps.Point(12, 12)
                            };
                            
                            // Create the marker
                            const marker = new google.maps.Marker({
                                position: customerLatLng,
                                map: map,
                                title: (customer.firstname || '') + ' ' + (customer.lastname || ''),
                                icon: customerIcon,
                                customerId: customer.customer_id
                            });
                            
                            // Create an info window for this customer
                            const infoContent = `
                                <div class="customer-info-window">
                                    <div class="fw-bold">${customer.firstname || ''} ${customer.lastname || ''}</div>
                                    <div><strong>Phone:</strong> ${customer.phone || 'N/A'}</div>
                                    <div><strong>Address:</strong> ${customer.address || 'N/A'}</div>
                                    ${customer.pending_booking ? 
                                        `<div class="mt-2 alert alert-warning p-1">
                                            <small><strong>Pending Booking:</strong> ${customer.pending_booking.pickup_location} to ${customer.pending_booking.dropoff_location}</small>
                                        </div>
                                        <div class="mt-1">
                                            <button class="btn btn-sm btn-primary view-booking-map-btn" 
                                                    data-booking-id="${customer.pending_booking.booking_id}">
                                                <i class="fas fa-calendar"></i> View Booking
                                            </button>
                                            <button class="btn btn-sm btn-success dispatch-nearest-btn" 
                                                    data-booking-id="${customer.pending_booking.booking_id}">
                                                <i class="fas fa-truck"></i> Dispatch
                                            </button>
                                        </div>` : 
                                        '<div class="mt-2 text-muted">No pending bookings</div>'}
                                </div>
                            `;
                            
                            const infoWindow = new google.maps.InfoWindow({
                                content: infoContent
                            });
                            
                            // Add click event to the marker
                            marker.addListener('click', function() {
                                // Close all open info windows first
                                for (const id in driverMarkers) {
                                    if (driverMarkers[id].infoWindow) {
                                        driverMarkers[id].infoWindow.close();
                                    }
                                }
                                
                                // Open this customer's info window
                                infoWindow.open(map, marker);
                                
                                // Attach click handlers to the info window buttons
                                google.maps.event.addListenerOnce(infoWindow, 'domready', function() {
                                    $('.view-booking-map-btn').on('click', function() {
                                        const bookingId = $(this).data('booking-id');
                                        infoWindow.close();
                                        
                                        // Show booking details modal
                                        $('#viewBookingModal').modal('show');
                                        viewBookingDetails(bookingId);
                                    });
                                    
                                    $('.dispatch-nearest-btn').on('click', function() {
                                        const bookingId = $(this).data('booking-id');
                                        infoWindow.close();
                                        
                                        // Dispatch nearest driver
                                        dispatchNearestDriver(bookingId);
                                    });
                                });
                            });
                        });
                        
                        console.log(`Added ${response.data.length} customer markers to the map`);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching customer locations:', error);
                }
            });
        }

        // Setup customer location selection
        function setupCustomerLocationSelection() {
            // Add a button to the map for customer location selection
            const selectCustomerLocationBtn = document.createElement('div');
            selectCustomerLocationBtn.className = 'custom-map-control';
            selectCustomerLocationBtn.innerHTML = `
                <button type="button" class="btn btn-primary select-customer-location-btn">
                    <i class="fas fa-user-alt me-1"></i> Select Customer Location
                </button>
            `;
            
            map.controls[google.maps.ControlPosition.TOP_RIGHT].push(selectCustomerLocationBtn);
            
            // Add click handler for the button
            $(selectCustomerLocationBtn).find('.select-customer-location-btn').on('click', function() {
                // Show customer selection modal
                $('#selectCustomerLocationModal').modal('show');
            });
        }

        // Setup nearest driver dispatch
        function setupNearestDriverDispatch() {
            // Add a button for dispatching the nearest driver to a booking
            $(document).on('click', '.dispatch-nearest-btn', function() {
                const bookingId = $(this).data('booking-id');
                dispatchNearestDriver(bookingId);
            });
        }

        // Dispatch nearest driver to a booking
        function dispatchNearestDriver(bookingId) {
            // Show confirmation dialog
            Swal.fire({
                title: 'Dispatch Nearest Driver',
                text: 'Are you sure you want to dispatch the nearest available driver to this booking?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, dispatch',
                cancelButtonText: 'No, cancel',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve, reject) => {
                        // Call the API to assign the nearest driver
                        $.ajax({
                            url: 'api/bookings/assign_nearest_driver.php',
                            method: 'POST',
                            data: {
                                booking_id: bookingId
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    resolve(response);
                                } else {
                                    reject(response.message || 'Failed to dispatch driver');
                                }
                            },
                            error: function(xhr, status, error) {
                                reject('Error connecting to the server. Please try again.');
                            }
                        });
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    const response = result.value;
                    
                    // Show success message
                    Swal.fire({
                        title: 'Driver Dispatched!',
                        html: `
                            <div class="text-success mb-3"><i class="fas fa-check-circle fa-2x"></i></div>
                            <p>Driver ${response.driver.name} has been assigned to the booking.</p>
                            <div class="driver-info mt-3">
                                <div><strong>Distance:</strong> ${response.driver.distance_km} km away</div>
                                <div><strong>ETA:</strong> Approximately ${response.driver.eta_minutes} minutes</div>
                                <div><strong>Vehicle:</strong> ${response.vehicle.model} (${response.vehicle.plate_number})</div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Refresh the page to show the updated booking
                        window.location.reload();
                    });
                }
            }).catch((error) => {
                // Show error message
                Swal.fire({
                    title: 'Error',
                    text: error || 'Failed to dispatch driver',
                    icon: 'error'
                });
            });
        }

        // Simulate driver locations (for testing)
        function simulateDriverLocations() {
            // Show loading indicator
            Swal.fire({
                title: 'Simulating...',
                text: 'Generating random driver locations...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Call the simulation API
            $.ajax({
                url: 'api/drivers/simulate_locations.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Close loading indicator
                        Swal.close();
                        
                        // Show success message
                        Swal.fire({
                            title: 'Locations Simulated',
                            text: `Generated random locations for ${response.count} drivers`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Refresh the map
                            refreshMap();
                        });
                    } else {
                        // Show error message
                        Swal.fire({
                            title: 'Error',
                            text: response.message || 'Failed to simulate driver locations',
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
        }

        // Customer location selection modal functionality
        let customerLocationMap;
        let customerLocationMarker;
        let selectedCustomer = null;

        // Global variables for customer location modal
        window.selectedCustomer = null;
        window.customerLocationMap = null;
        window.customerLocationMarker = null;
        window.geocoder = null;
        window.lastCustomerResponse = null;
        
        // Initialize the customer location modal
        $('#selectCustomerLocationModal').on('shown.bs.modal', function() {
            console.log('Customer location modal shown');
            
            // Initialize the map
            initCustomerLocationMap();
            
            // Initial load of customers list
            loadCustomersList();
            
            // Set up event handlers
            setupCustomerSearchHandler();
            
            // Start auto-refresh timer
            startAutoRefresh();
        });

        // Add debug event for modal failures
        $('#selectCustomerLocationModal').on('show.bs.modal', function() {
            console.log('Customer location modal about to show');
        });

        $('#selectCustomerLocationModal').on('hide.bs.modal', function() {
            console.log('Customer location modal hiding');
            // Stop auto-refresh timer when modal is closed
            stopAutoRefresh();
        });

        $('#selectCustomerLocationModal').on('hidden.bs.modal', function() {
            console.log('Customer location modal hidden');
        });
        
        // Setup customer search handler
        function setupCustomerSearchHandler() {
            // Search button click
            $('#searchCustomerBtn').on('click', function() {
                const searchTerm = $('#customerSearchInput').val().trim();
                if (searchTerm.length > 0) {
                    searchCustomers(searchTerm);
                } else {
                    loadCustomersList(); // Load all customers if search is empty
                }
            });
            
            // Enter key in search input
            $('#customerSearchInput').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    const searchTerm = $(this).val().trim();
                    if (searchTerm.length > 0) {
                        searchCustomers(searchTerm);
                    } else {
                        loadCustomersList(); // Load all customers if search is empty
                    }
                    e.preventDefault();
                }
            });
        }
        
        // Search customers
        function searchCustomers(term) {
            console.log('Searching customers for:', term);
            
            // Show loading state
            $('.customer-list').html(`
                <div class="list-group-item text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Searching customers...</span>
                </div>
            `);
            
            // Call search API
            $.ajax({
                url: 'api/customers/search.php',
                method: 'GET',
                data: { term: term },
                dataType: 'json',
                success: function(response) {
                    console.log('Customer search API response:', response);
                    
                    // Store response for debugging
                    window.lastCustomerResponse = response;
                    
                    if (response.success && Array.isArray(response.data)) {
                        const customers = response.data;
                        
                        // Update the count badge
                        $('#customerCount').text(customers.length);
                        
                        if (customers.length === 0) {
                            $('.customer-list').html(`
                                <div class="list-group-item text-center py-3">
                                    <div class="text-muted">No customers found matching "${term}"</div>
                                    <button class="btn btn-sm btn-outline-secondary mt-2" id="showAllCustomersBtn">
                                        <i class="fas fa-list me-1"></i> Show All Customers
                                    </button>
                                </div>
                            `);
                            
                            $('#showAllCustomersBtn').on('click', function() {
                                $('#customerSearchInput').val('');
                                loadCustomersList();
                            });
                        } else {
                            $('.customer-list').empty();
                            
                            // Add each customer to the list
                            customers.forEach(customer => {
                                // Safety checks for required fields
                                if (!customer.customer_id) {
                                    console.error('Customer missing customer_id:', customer);
                                    return; // Skip this customer
                                }
                                
                                const hasLocation = customer.latitude && customer.longitude && 
                                    parseFloat(customer.latitude) !== 0 && parseFloat(customer.longitude) !== 0;
                                
                                const firstName = customer.firstname || '';
                                const lastName = customer.lastname || '';
                                const fullName = (firstName + ' ' + lastName).trim() || 'Unknown Customer';
                                const phone = customer.phone || 'N/A';
                                
                                // Format address with failsafes
                                let addressText = 'No address';
                                if (customer.address) {
                                    addressText = customer.address;
                                    if (customer.city) {
                                        addressText += ', ' + customer.city;
                                    }
                                    if (customer.state) {
                                        addressText += ', ' + customer.state;
                                    }
                                }
                                
                                // Create list item
                                const isSelected = window.selectedCustomer && 
                                    window.selectedCustomer.customer_id == customer.customer_id;
                                const itemHtml = `
                                    <div class="list-group-item list-group-item-action customer-item ${isSelected ? 'active' : ''}" 
                                        data-customer-id="${customer.customer_id}">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1">${fullName}</h6>
                                            ${hasLocation ? 
                                                '<span class="badge bg-success">Has Location</span>' : 
                                                '<span class="badge bg-warning">No Location</span>'}
                                        </div>
                                        <p class="mb-1">
                                            <i class="fas fa-phone-alt text-secondary fa-fw"></i> ${phone}
                                        </p>
                                        <p class="mb-1 small text-muted" title="${addressText}">
                                            <i class="fas fa-map-marker-alt text-secondary fa-fw"></i> 
                                            ${addressText.length > 50 ? addressText.substring(0, 47) + '...' : addressText}
                                        </p>
                                    </div>
                                `;
                                $('.customer-list').append(itemHtml);
                            });
                            
                            // Add click handler for customer items
                            $('.customer-item').on('click', function() {
                                // Get customer ID
                                const customerId = $(this).data('customer-id');
                                
                                // Remove active class from all items
                                $('.customer-item').removeClass('active');
                                
                                // Add active class to clicked item
                                $(this).addClass('active');
                                
                                // Select customer
                                selectCustomer(customerId, customers);
                            });
                        }
                    } else {
                        // Error message with debugging info
                        const errorMsg = response.message || 'Unknown error occurred';
                        console.error('Customer search API error:', errorMsg, response);
                        
                        $('.customer-list').html(`
                            <div class="list-group-item text-center py-3">
                                <div class="text-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    ${errorMsg}
                                </div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-secondary" id="showAllCustomersBtn">
                                        <i class="fas fa-sync-alt me-1"></i> Show All Customers
                                    </button>
                                </div>
                            </div>
                        `);
                        
                        $('#showAllCustomersBtn').on('click', function() {
                            $('#customerSearchInput').val('');
                            loadCustomersList();
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error searching customers:', status, error);
                    console.log('XHR object:', xhr);
                    
                    // Construct a more detailed error message
                    let errorDetails = '';
                    if (xhr.responseJSON) {
                        errorDetails = xhr.responseJSON.message || '';
                    } else if (xhr.responseText) {
                        errorDetails = xhr.responseText.substring(0, 200) + (xhr.responseText.length > 200 ? '...' : '');
                    }
                    
                    $('.customer-list').html(`
                        <div class="list-group-item text-center py-3">
                            <div class="text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error searching customers: ${error || 'Unknown error'}
                            </div>
                            <div class="small text-muted mt-2">
                                ${errorDetails ? `Details: ${errorDetails}` : ''}
                            </div>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-secondary" id="showAllCustomersBtn">
                                    <i class="fas fa-sync-alt me-1"></i> Show All Customers
                                </button>
                            </div>
                        </div>
                    `);
                    
                    $('#showAllCustomersBtn').on('click', function() {
                        $('#customerSearchInput').val('');
                        loadCustomersList();
                    });
                }
            });
        }
        
        // Save customer location
        function saveCustomerLocation() {
            // Safety checks
            if (!window.selectedCustomer) {
                console.error('No customer selected');
                return;
            }
            
            if (!window.customerLocationMarker || !window.customerLocationMarker.getPosition) {
                console.error('No marker position available');
                return;
            }
            
            try {
                // Get marker position
                const position = window.customerLocationMarker.getPosition();
                const latitude = position.lat();
                const longitude = position.lng();
                
                console.log('Saving customer location:', { 
                    customer_id: window.selectedCustomer.customer_id,
                    latitude: latitude,
                    longitude: longitude
                });
                
                // Only update in database if checkbox is checked
                const updateInDatabase = $('#updateCustomerLocationCheck').is(':checked');
                
                if (updateInDatabase) {
                    // Disable save button to prevent double submission
                    $('#saveCustomerLocationBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                    
                    // Send AJAX request to update customer location
                    $.ajax({
                        url: 'api/customers/update_location.php',
                        method: 'POST',
                        data: {
                            customer_id: window.selectedCustomer.customer_id,
                            latitude: latitude,
                            longitude: longitude
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Location update response:', response);
                            
                            if (response.success) {
                                // Update local customer data
                                window.selectedCustomer.latitude = latitude;
                                window.selectedCustomer.longitude = longitude;
                                
                                // Close the modal with success message
                                $('#selectCustomerLocationModal').modal('hide');
                                
                                // Show success message
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Location Updated',
                                    text: 'Customer location has been updated successfully.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                
                                // Update the pickup or dropoff location in the form
                                if (currentLocationType === 'pickup') {
                                    $('#pickupLocation').val(window.selectedCustomer.address || '');
                                    $('#pickupLat').val(latitude);
                                    $('#pickupLng').val(longitude);
                                } else if (currentLocationType === 'dropoff') {
                                    $('#dropoffLocation').val(window.selectedCustomer.address || '');
                                    $('#dropoffLat').val(latitude);
                                    $('#dropoffLng').val(longitude);
                                }
                                
                                // Trigger change event to update any dependent elements
                                $('#pickupLocation, #dropoffLocation').trigger('change');
                            } else {
                                // Show error message
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Update Failed',
                                    text: response.message || 'Failed to update customer location.',
                                });
                                
                                // Re-enable save button
                                $('#saveCustomerLocationBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Location');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error updating location:', status, error);
                            
                            // Show error message
                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: 'Failed to update customer location. Please try again.',
                            });
                            
                            // Re-enable save button
                            $('#saveCustomerLocationBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Location');
                        }
                    });
                } else {
                    // Just close the modal and update the form without saving to database
                    $('#selectCustomerLocationModal').modal('hide');
                    
                    // Update the pickup or dropoff location in the form
                    if (currentLocationType === 'pickup') {
                        $('#pickupLocation').val(window.selectedCustomer.address || '');
                        $('#pickupLat').val(latitude);
                        $('#pickupLng').val(longitude);
                    } else if (currentLocationType === 'dropoff') {
                        $('#dropoffLocation').val(window.selectedCustomer.address || '');
                        $('#dropoffLat').val(latitude);
                        $('#dropoffLng').val(longitude);
                    }
                    
                    // Trigger change event to update any dependent elements
                    $('#pickupLocation, #dropoffLocation').trigger('change');
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Location Selected',
                        text: 'Customer location has been set.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } catch (error) {
                console.error('Error saving customer location:', error);
                
                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while saving the location: ' + error.message,
                });
                
                // Re-enable save button
                $('#saveCustomerLocationBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Location');
            }
        }

        // Initialize the customer location map
        function initCustomerLocationMap() {
            console.log('Initializing customer location map');
            
            try {
                // Check if Google Maps is available
                if (typeof google === 'undefined' || !google.maps) {
                    console.error('Google Maps API not loaded');
                    
                    // Show error message
                    $('#customerLocationMap').html(`
                        <div class="alert alert-danger m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Google Maps API is not loaded. Please refresh the page and try again.
                        </div>
                    `);
                    return;
                }
                
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
                    
                    console.log('Customer location map initialized successfully');
                } else {
                    console.log('Customer location map already initialized, reusing existing map');
                }
            } catch (error) {
                console.error('Error initializing customer location map:', error);
                
                // Show error message
                $('#customerLocationMap').html(`
                    <div class="alert alert-danger m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Failed to initialize map: ${error.message || 'Unknown error'}
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-primary retry-map-btn">
                                <i class="fas fa-sync-alt me-1"></i> Retry
                            </button>
                        </div>
                    </div>
                `);
                
                // Add handler for retry button
                $('.retry-map-btn').on('click', function() {
                    // Try to initialize the map again
                    initCustomerLocationMap();
                });
            }
        }
    </script>

    <!-- View Driver Details Modal -->
    <div class="modal fade" id="viewDriverDetailsModal" tabindex="-1" aria-labelledby="viewDriverDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewDriverDetailsModalLabel">
                        <i class="fas fa-user-tie me-2"></i> Driver Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar progress-bar-striped bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="modal-body driver-details-content">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading driver details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 