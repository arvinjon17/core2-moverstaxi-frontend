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

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Please log in to access this page.</div>';
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
    dl.latitude, dl.longitude, dl.last_updated
FROM 
    " . DB_NAME_CORE1 . ".drivers d
JOIN 
    " . DB_NAME_CORE2 . ".users u ON d.user_id = u.user_id
LEFT JOIN 
    " . DB_NAME_CORE1 . ".driver_locations dl ON d.driver_id = dl.driver_id
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
    <title>Booking Management - CORE Movers</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Add Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCS7rhxuCiYKeXpraOxq-GJCrYTmPiSaMU&libraries=places"></script>
    
    <!-- Add DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <style>
        /* Custom styling for the booking management page */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
        }
        
        .pending-badge {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .confirmed-badge {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .in-progress-badge {
            background-color: #e8f7ee;
            color: #28a745;
            border: 1px solid #d4edda;
        }
        
        .completed-badge {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .cancelled-badge {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-card {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card .icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stats-card .count {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stats-card .label {
            font-size: 1rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .map-container {
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        #driverMap {
            height: 100%;
            width: 100%;
        }
        
        .driver-card {
            margin-bottom: 1rem;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .driver-card:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .driver-card.active {
            border-left: 4px solid #007bff;
            background-color: #e8f4ff;
        }
        
        .tab-content {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: 0;
            border-radius: 0 0 0.25rem 0.25rem;
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
                                                <div class="card driver-card mb-2" data-driver-id="<?php echo $driver['driver_id']; ?>" 
                                                     data-lat="<?php echo $driver['latitude'] ?? '0'; ?>" 
                                                     data-lng="<?php echo $driver['longitude'] ?? '0'; ?>">
                                                    <div class="card-body py-2">
                                                        <h6 class="mb-0">
                                                            <?php echo htmlspecialchars($driver['firstname'] . ' ' . $driver['lastname']); ?>
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
                                                    </div>
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
                                                        <?php echo number_format($booking['fare_amount'], 2); ?>
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
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="addBookingModalLabel">
                        <i class="fas fa-plus me-2"></i> Add New Booking
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addBookingForm" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">Select Customer</option>
                                    <!-- Customer options will be loaded via AJAX -->
                                </select>
                                <div class="invalid-feedback">
                                    Please select a customer.
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="pickup_location" class="form-label">Pickup Location</label>
                                <input type="text" class="form-control" id="pickup_location" name="pickup_location" required>
                                <div class="invalid-feedback">
                                    Please enter a pickup location.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="dropoff_location" class="form-label">Dropoff Location</label>
                                <input type="text" class="form-control" id="dropoff_location" name="dropoff_location" required>
                                <div class="invalid-feedback">
                                    Please enter a dropoff location.
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="pickup_datetime" class="form-label">Pickup Date & Time</label>
                                <input type="datetime-local" class="form-control" id="pickup_datetime" name="pickup_datetime" required>
                                <div class="invalid-feedback">
                                    Please select a pickup date and time.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="dropoff_datetime" class="form-label">Expected Dropoff Date & Time</label>
                                <input type="datetime-local" class="form-control" id="dropoff_datetime" name="dropoff_datetime">
                                <small class="text-muted">Optional</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="vehicle_id" class="form-label">Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="vehicle_id">
                                    <option value="">Select Vehicle (Optional)</option>
                                    <!-- Vehicle options will be loaded via AJAX -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="driver_id" class="form-label">Driver</label>
                                <select class="form-select" id="driver_id" name="driver_id">
                                    <option value="">Select Driver (Optional)</option>
                                    <!-- Driver options will be loaded via AJAX -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="distance_km" class="form-label">Distance (km)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="distance_km" name="distance_km">
                                <small class="text-muted">Optional</small>
                            </div>
                            <div class="col-md-6">
                                <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                                <input type="number" step="1" min="0" class="form-control" id="duration_minutes" name="duration_minutes">
                                <small class="text-muted">Optional</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fare_amount" class="form-label">Fare Amount ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="fare_amount" name="fare_amount">
                                <small class="text-muted">Optional</small>
                            </div>
                            <div class="col-md-6">
                                <label for="booking_status" class="form-label">Status</label>
                                <select class="form-select" id="booking_status" name="booking_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="special_instructions" class="form-label">Special Instructions</label>
                            <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="createBookingBtn">Create Booking</button>
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
        
        // Document ready function
        $(document).ready(function() {
            // Initialize DataTables for bookings
            initializeDataTables();
            
            // Initialize Google Map
            if (typeof google !== 'undefined' && google.maps) {
                initializeMap();
            }
            
            // Setup tab functionality
            setupStatusTabs();
            
            // Setup booking view modal
            setupViewBookingModal();
            
            // Setup booking edit modal
            setupEditBookingModal();
            
            // Setup add booking modal
            setupAddBookingModal();
            
            // Setup status update functionality
            setupStatusUpdateButtons();
            
            // Setup map refresh
            $('#refreshMapBtn').on('click', function() {
                refreshMap();
            });
            
            // Auto-refresh map every 30 seconds
            setInterval(refreshMap, 30000);
            
            // Assign driver button click handler
            $(document).on('click', '.btn-assign-driver', function() {
                const bookingId = $(this).data('id');
                assignDriverToBooking(bookingId);
            });
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
                    
                    modalBody.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            An error occurred while loading the booking details.
                            <div class="mt-2 small">Error details: ${error || 'Unknown error'}</div>
                        </div>
                    `);
                    
                    console.error('Error loading booking details:', error, xhr.responseText);
                }
            });
        }
        
        function initializeMap() {
            // Initialize the map centered at a default location
            const mapOptions = {
                center: { lat: 40.7128, lng: -74.0060 }, // New York as default
                zoom: 10,
                mapTypeControl: true,
                fullscreenControl: true,
                streetViewControl: false
            };
            
            // Create the map
            map = new google.maps.Map(document.getElementById('driverMap'), mapOptions);
            
            // Add driver markers to the map
            addDriverMarkers();
            
            // Add click handlers for the driver cards
            $('.driver-card').on('click', function() {
                const driverId = $(this).data('driver-id');
                const lat = parseFloat($(this).data('lat'));
                const lng = parseFloat($(this).data('lng'));
                
                // Center the map on this driver
                if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
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
                    
                    // Toggle the active class
                    $('.driver-card').removeClass('active');
                    $(this).addClass('active');
                }
            });
        }
        
        function addDriverMarkers() {
            // Clear existing markers
            for (const id in driverMarkers) {
                if (driverMarkers[id]) {
                    driverMarkers[id].setMap(null);
                }
            }
            
            driverMarkers = {};
            
            // Add markers for each driver
            availableDrivers.forEach(driver => {
                if (driver.latitude && driver.longitude) {
                    const position = {
                        lat: parseFloat(driver.latitude),
                        lng: parseFloat(driver.longitude)
                    };
                    
                    if (isNaN(position.lat) || isNaN(position.lng)) {
                        console.warn(`Invalid coordinates for driver ${driver.driver_id}`);
                        return;
                    }
                    
                    // Create marker
                    const marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: `${driver.firstname} ${driver.lastname}`,
                        icon: {
                            url: 'assets/img/driver-marker.png',
                            scaledSize: new google.maps.Size(32, 32)
                        }
                    });
                    
                    // Create info window
                    const infoContent = `
                        <div class="driver-info-window">
                            <h6>${driver.firstname} ${driver.lastname}</h6>
                            <div class="small">
                                <i class="fas fa-phone"></i> ${driver.phone}<br>
                                <i class="fas fa-id-card"></i> License: ${driver.license_number}
                            </div>
                            ${driver.last_updated ? `
                                <div class="small text-success mt-1">
                                    <i class="fas fa-clock"></i> Updated: ${new Date(driver.last_updated).toLocaleString()}
                                </div>
                            ` : ''}
                        </div>
                    `;
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: infoContent
                    });
                    
                    // Add click listener to marker
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                        
                        // Highlight the corresponding driver in the list
                        $('.driver-card').removeClass('active');
                        $(`.driver-card[data-driver-id="${driver.driver_id}"]`).addClass('active');
                    });
                    
                    // Store the marker
                    driverMarkers[driver.driver_id] = marker;
                }
            });
            
            // If we have drivers with location, fit the map to show all of them
            if (Object.keys(driverMarkers).length > 0) {
                const bounds = new google.maps.LatLngBounds();
                for (const id in driverMarkers) {
                    bounds.extend(driverMarkers[id].getPosition());
                }
                map.fitBounds(bounds);
                
                // Don't zoom in too far
                const listener = google.maps.event.addListener(map, 'idle', function() {
                    if (map.getZoom() > 15) {
                        map.setZoom(15);
                    }
                    google.maps.event.removeListener(listener);
                });
            }
        }
        
        function refreshMap() {
            // Fetch the latest driver locations
            $.ajax({
                url: 'api/drivers/get_locations.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        availableDrivers = response.data;
                        addDriverMarkers();
                        updateDriverList();
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message || 'Failed to refresh driver locations.',
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error refreshing driver locations:', error);
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
                driverList.append(`
                    <div class="card driver-card mb-2" data-driver-id="${driver.driver_id}" 
                         data-lat="${driver.latitude || '0'}" 
                         data-lng="${driver.longitude || '0'}">
                    <div class="card-body py-2">
                        <h6 class="mb-0">
                            ${driver.firstname} ${driver.lastname}
                        </h6>
                        <div class="small text-muted">
                            <i class="fas fa-phone me-1"></i> ${driver.phone}
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-id-card me-1"></i> License: ${driver.license_number}
                        </div>
                        ${driver.last_updated ? `
                            <div class="small text-success">
                                <i class="fas fa-clock me-1"></i> Updated: ${new Date(driver.last_updated).toLocaleString()}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `);
            });
            
            // Reattach click handlers for the driver cards
            $('.driver-card').on('click', function() {
                const driverId = $(this).data('driver-id');
                const lat = parseFloat($(this).data('lat'));
                const lng = parseFloat($(this).data('lng'));
                
                // Center the map on this driver
                if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
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
                    
                    // Toggle the active class
                    $('.driver-card').removeClass('active');
                    $(this).addClass('active');
                }
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
                loadCustomersForSelect();
                loadVehiclesForSelect();
                loadDriversForSelect();
                
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
                    
                    // Calculate distance when both fields have values
                    google.maps.event.addListener(pickupAutocomplete, 'place_changed', calculateDistance);
                    google.maps.event.addListener(dropoffAutocomplete, 'place_changed', calculateDistance);
                }
            }
            
            // Handle create button click
            $('#createBookingBtn').on('click', function() {
                createNewBooking();
            });
        }
        
        function loadCustomersForSelect() {
            $.ajax({
                url: 'api/customers/get_list.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const $select = $('#customer_id');
                        $select.find('option:not(:first)').remove();
                        
                        response.data.forEach(customer => {
                            $select.append(`<option value="${customer.customer_id}">${customer.firstname} ${customer.lastname} (${customer.phone})</option>`);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading customers:', error);
                }
            });
        }
        
        function loadVehiclesForSelect() {
            $.ajax({
                url: 'api/vehicles/get_list.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const $select = $('#vehicle_id');
                        $select.find('option:not(:first)').remove();
                        
                        response.data.forEach(vehicle => {
                            $select.append(`<option value="${vehicle.vehicle_id}">${vehicle.model} (${vehicle.plate_number}) - ${vehicle.capacity} seats</option>`);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading vehicles:', error);
                }
            });
        }
        
        function loadDriversForSelect() {
            $.ajax({
                url: 'api/drivers/get_list.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const $select = $('#driver_id');
                        $select.find('option:not(:first)').remove();
                        
                        response.data.forEach(driver => {
                            $select.append(`<option value="${driver.driver_id}">${driver.firstname} ${driver.lastname} (${driver.phone})</option>`);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading drivers:', error);
                }
            });
        }
        
        function calculateDistance() {
            const pickup = $('#pickup_location').val();
            const dropoff = $('#dropoff_location').val();
            
            if (pickup && dropoff) {
                const directionsService = new google.maps.DirectionsService();
                
                directionsService.route({
                    origin: pickup,
                    destination: dropoff,
                    travelMode: google.maps.TravelMode.DRIVING
                }, function(response, status) {
                    if (status === google.maps.DirectionsStatus.OK) {
                        const route = response.routes[0];
                        
                        // Get distance in kilometers
                        const distanceInMeters = route.legs[0].distance.value;
                        const distanceInKm = (distanceInMeters / 1000).toFixed(2);
                        
                        // Get duration in minutes
                        const durationInSeconds = route.legs[0].duration.value;
                        const durationInMinutes = Math.round(durationInSeconds / 60);
                        
                        // Update form fields
                        $('#distance_km').val(distanceInKm);
                        $('#duration_minutes').val(durationInMinutes);
                        
                        // Calculate suggested fare - example formula: $2 base + $1.5 per km
                        const baseFare = 2;
                        const ratePerKm = 1.5;
                        const suggestedFare = (baseFare + (distanceInKm * ratePerKm)).toFixed(2);
                        
                        $('#fare_amount').val(suggestedFare);
                    }
                });
            }
        }
        
        function createNewBooking() {
            // Get the form
            const $form = $('#addBookingForm');
            
            // Validate the form
            if (!$form[0].checkValidity()) {
                $form.addClass('was-validated');
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
        
        // Initialize any form elements like date pickers or address autocomplete
        function initializeFormElements() {
            // Initialize Google Places Autocomplete for address fields
            if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                const pickupInput = document.getElementById('pickup_location');
                const dropoffInput = document.getElementById('dropoff_location');
                
                if (pickupInput && dropoffInput) {
                    const pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput);
                    const dropoffAutocomplete = new google.maps.places.Autocomplete(dropoffInput);
                    
                    // Calculate distance when both fields have values
                    google.maps.event.addListener(pickupAutocomplete, 'place_changed', calculateDistance);
                    google.maps.event.addListener(dropoffAutocomplete, 'place_changed', calculateDistance);
                }
            }
            
            // Other initializations if needed
        }
        
        // Function to assign driver to booking
        function assignDriverToBooking(bookingId) {
            // Get list of available drivers
            $.ajax({
                url: 'api/drivers/get_locations.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        // Create driver selection options
                        let driverOptions = '';
                        response.data.forEach(function(driver) {
                            driverOptions += `<option value="${driver.driver_id}">${driver.first_name} ${driver.last_name} (${driver.license_number})</option>`;
                        });
                        
                        // Show driver selection dialog
                        Swal.fire({
                            title: 'Assign Driver',
                            html: `
                                <div class="form-group">
                                    <label for="driver-select">Select Driver</label>
                                    <select id="driver-select" class="form-control">
                                        <option value="">-- Select a driver --</option>
                                        ${driverOptions}
                                    </select>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Assign',
                            showLoaderOnConfirm: true,
                            preConfirm: () => {
                                const driverId = document.getElementById('driver-select').value;
                                if (!driverId) {
                                    Swal.showValidationMessage('Please select a driver');
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
                                    title: 'Driver Assigned',
                                    text: 'The driver has been successfully assigned to this booking.',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Refresh the bookings table
                                    $('#bookings-table').DataTable().ajax.reload();
                                    // Refresh the map if it exists
                                    if (typeof updateDriversMap === 'function') {
                                        updateDriversMap();
                                    }
                                });
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'No Drivers Available',
                            text: 'There are no active drivers available for assignment.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to fetch drivers. Please try again later.'
                    });
                }
            });
        }
        
        // Other functions...
    </script>
</body>
</html> 