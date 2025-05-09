<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use correct absolute paths for includes with dirname()
require_once dirname(__FILE__) . '/../functions/auth.php';
require_once dirname(__FILE__) . '/../functions/role_management.php';
require_once dirname(__FILE__) . '/../functions/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Store error message in session and redirect using JavaScript at end of file
    $_SESSION['error'] = "You need to log in to access this page";
    $redirectTo = "/login.php";
    $needRedirect = true;
} else {
    $needRedirect = false;
    
    // Get playground data if available
    $conn = connectToCore2DB();
    
    // Check if playgrounds table exists
    $checkTableQuery = "SHOW TABLES LIKE 'playgrounds'";
    $tableExists = false;
    
    if ($result = $conn->query($checkTableQuery)) {
        $tableExists = ($result->num_rows > 0);
    }
    
    // Initialize variables
    $playgrounds = [];
    $message = '';
    $messageType = '';
    
    // If table exists, get playground data
    if ($tableExists) {
        $sql = "SELECT * FROM playgrounds ORDER BY name";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $playgrounds[] = $row;
            }
        }
    } else {
        // Sample data if table doesn't exist
        $playgrounds = [
            [
                'id' => 1,
                'name' => 'Rizal Park Playground',
                'address' => 'Roxas Blvd, Malate, Manila',
                'lat' => 14.5832,
                'lng' => 120.9790,
                'capacity' => 50,
                'hourly_rate' => 500,
                'opening_time' => '08:00:00',
                'closing_time' => '18:00:00',
                'features' => 'Swings, Slides, Monkey Bars, Basketball Court',
                'status' => 'active'
            ],
            [
                'id' => 2,
                'name' => 'Ayala Triangle Gardens Playground',
                'address' => 'Ayala Triangle, Makati City',
                'lat' => 14.5577,
                'lng' => 121.0225,
                'capacity' => 30,
                'hourly_rate' => 800,
                'opening_time' => '07:00:00',
                'closing_time' => '20:00:00',
                'features' => 'Climbing Wall, Sandbox, Water Play Area',
                'status' => 'active'
            ],
            [
                'id' => 3,
                'name' => 'BGC Adventure Playground',
                'address' => 'Bonifacio Global City, Taguig',
                'lat' => 14.5508,
                'lng' => 121.0510,
                'capacity' => 40,
                'hourly_rate' => 1000,
                'opening_time' => '09:00:00',
                'closing_time' => '21:00:00',
                'features' => 'Rope Course, Zipline, Trampolines',
                'status' => 'active'
            ],
            [
                'id' => 4,
                'name' => 'QC Memorial Circle Playground',
                'address' => 'Elliptical Road, Quezon City',
                'lat' => 14.6515,
                'lng' => 121.0493,
                'capacity' => 60,
                'hourly_rate' => 400,
                'opening_time' => '06:00:00',
                'closing_time' => '19:00:00',
                'features' => 'Biking Path, Mini Zoo, Picnic Area',
                'status' => 'active'
            ],
            [
                'id' => 5,
                'name' => 'Las Piñas Bamboo Organ Park Playground',
                'address' => 'St. Joseph Street, Las Piñas City',
                'lat' => 14.4487,
                'lng' => 120.9829,
                'capacity' => 25,
                'hourly_rate' => 300,
                'opening_time' => '08:00:00',
                'closing_time' => '17:00:00',
                'features' => 'Traditional Games Area, Bamboo Maze',
                'status' => 'maintenance'
            ]
        ];
    }
    
    // Now get active drivers (will be used for simulation)
    $driversQuery = "SELECT 
        d.driver_id, d.user_id, d.status as driver_status,
        u.firstname, u.lastname, u.phone, u.email
    FROM 
        " . DB_NAME_CORE1 . ".drivers d
    JOIN 
        " . DB_NAME_CORE2 . ".users u ON d.user_id = u.user_id
    WHERE 
        d.status IN ('available', 'busy')
    LIMIT 10";

    try {
        $availableDrivers = getRows($driversQuery, 'core1');
    } catch (Exception $e) {
        error_log("Error fetching available drivers: " . $e->getMessage());
        $availableDrivers = [];
    }
    
    // Get active customers for simulation
    $customersQuery = "SELECT 
        c.customer_id, c.user_id, c.status as customer_status,
        u.firstname, u.lastname, u.phone, u.email
    FROM 
        " . DB_NAME_CORE1 . ".customers c
    JOIN 
        " . DB_NAME_CORE2 . ".users u ON c.user_id = u.user_id
    WHERE 
        c.status = 'active'
    LIMIT 10";

    try {
        $activeCustomers = getRows($customersQuery, 'core1');
    } catch (Exception $e) {
        error_log("Error fetching active customers: " . $e->getMessage());
        $activeCustomers = [];
    }
    
    // Close database connection
    $conn->close();
}
?>

<!-- If we need to redirect, we'll do it with JavaScript at the end of the file -->
<?php if (!$needRedirect): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playground Booking - CORE Movers</title>
    
    <!-- Google Maps API with places library -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCS7rhxuCiYKeXpraOxq-GJCrYTmPiSaMU&libraries=places"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        #map {
            height: 500px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .simulation-panel {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .playground-card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .playground-card:hover {
            transform: translateY(-5px);
        }
        
        .playground-card .card-header {
            font-weight: bold;
        }
        
        .active-badge {
            background-color: #28a745;
        }
        
        .maintenance-badge {
            background-color: #ffc107;
            color: #212529;
        }
        
        .closed-badge {
            background-color: #dc3545;
        }
        
        .simulation-user {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .simulation-user:hover {
            background-color: #f1f1f1;
        }
        
        .simulation-user.selected {
            background-color: #cfe2ff;
            border-color: #9ec5fe;
        }
        
        .booking-history {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .booking-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }
        
        .marker-controls {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container-fluid px-4">
            <h1 class="mt-4">Playground Booking</h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Playground Booking</li>
            </ol>
            
            <?php if (isset($message) && !empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- View Toggle Tabs -->
            <ul class="nav nav-tabs mb-4" id="viewTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="map-tab" data-bs-toggle="tab" data-bs-target="#map-view" type="button" role="tab" aria-controls="map-view" aria-selected="true">
                        <i class="fas fa-map-marked-alt me-1"></i> Map View
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-view" type="button" role="tab" aria-controls="list-view" aria-selected="false">
                        <i class="fas fa-list me-1"></i> List View
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="simulation-tab" data-bs-toggle="tab" data-bs-target="#simulation-view" type="button" role="tab" aria-controls="simulation-view" aria-selected="false">
                        <i class="fas fa-gamepad me-1"></i> Simulation
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings-view" type="button" role="tab" aria-controls="bookings-view" aria-selected="false">
                        <i class="fas fa-calendar-alt me-1"></i> Bookings
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="viewTabsContent">
                <!-- Map View Tab -->
                <div class="tab-pane fade show active" id="map-view" role="tabpanel" aria-labelledby="map-tab">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-map-marked-alt me-1"></i> Playground Locations</span>
                            <div>
                                <button id="refresh-map-btn" class="btn btn-sm btn-primary">
                                    <i class="fas fa-sync-alt"></i> Refresh Map
                                </button>
                                <button id="locate-me-btn" class="btn btn-sm btn-info text-white ms-2">
                                    <i class="fas fa-location-arrow"></i> Locate Me
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" id="search-location" class="form-control" placeholder="Search location...">
                                        <button class="btn btn-primary" id="search-location-btn" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select id="filter-status" class="form-select">
                                        <option value="all">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="show-simulation" checked>
                                        <label class="form-check-label" for="show-simulation">Show Simulation Users</label>
                                    </div>
                                </div>
                            </div>
                            <div id="map"></div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div id="selected-playground-info">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Select a playground on the map to see details and book
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div id="booking-form" style="display: none;">
                                        <div class="card">
                                            <div class="card-header bg-primary text-white">
                                                Book Playground
                                            </div>
                                            <div class="card-body">
                                                <form id="playground-booking-form">
                                                    <input type="hidden" id="booking-playground-id" name="playground_id">
                                                    <div class="mb-3">
                                                        <label for="booking-date" class="form-label">Date</label>
                                                        <input type="date" class="form-control" id="booking-date" name="booking_date" required>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="booking-start-time" class="form-label">Start Time</label>
                                                            <input type="time" class="form-control" id="booking-start-time" name="start_time" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="booking-end-time" class="form-label">End Time</label>
                                                            <input type="time" class="form-control" id="booking-end-time" name="end_time" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="booking-participants" class="form-label">Number of Participants</label>
                                                        <input type="number" class="form-control" id="booking-participants" name="participants" min="1" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="booking-notes" class="form-label">Notes</label>
                                                        <textarea class="form-control" id="booking-notes" name="notes" rows="2"></textarea>
                                                    </div>
                                                    <div class="d-grid">
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check me-1"></i> Book Now
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- List View Tab -->
                <div class="tab-pane fade" id="list-view" role="tabpanel" aria-labelledby="list-tab">
                    <div class="row">
                        <?php foreach ($playgrounds as $playground): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card playground-card" data-id="<?php echo $playground['id']; ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($playground['name']); ?>
                                        <span class="badge <?php echo $playground['status']; ?>-badge">
                                            <?php echo ucfirst($playground['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($playground['address']); ?></p>
                                        <p><i class="fas fa-clock me-2"></i> <?php echo date('g:i A', strtotime($playground['opening_time'])); ?> - <?php echo date('g:i A', strtotime($playground['closing_time'])); ?></p>
                                        <p><i class="fas fa-users me-2"></i> Capacity: <?php echo $playground['capacity']; ?> people</p>
                                        <p><i class="fas fa-peso-sign me-2"></i> ₱<?php echo number_format($playground['hourly_rate'], 2); ?> per hour</p>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-sm btn-outline-primary view-on-map-btn" data-id="<?php echo $playground['id']; ?>">
                                                <i class="fas fa-map-marked-alt me-1"></i> View on Map
                                            </button>
                                            <button class="btn btn-sm btn-success book-now-btn" data-id="<?php echo $playground['id']; ?>" <?php echo $playground['status'] !== 'active' ? 'disabled' : ''; ?>>
                                                <i class="fas fa-calendar-plus me-1"></i> Book Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Simulation View Tab -->
                <div class="tab-pane fade" id="simulation-view" role="tabpanel" aria-labelledby="simulation-tab">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="simulation-panel">
                                <h5><i class="fas fa-info-circle me-2"></i> Simulation Controls</h5>
                                <p class="text-muted">Use this panel to simulate customer and driver movements for testing the booking system.</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-primary text-white">
                                                <i class="fas fa-users me-1"></i> Customers
                                            </div>
                                            <div class="card-body">
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="show-customers" checked>
                                                    <label class="form-check-label" for="show-customers">Show Customers on Map</label>
                                                </div>
                                                
                                                <div class="customers-list">
                                                    <?php if (empty($activeCustomers)): ?>
                                                        <div class="alert alert-info">No customers available for simulation</div>
                                                    <?php else: ?>
                                                        <?php foreach ($activeCustomers as $index => $customer): ?>
                                                            <div class="simulation-user customer-item" data-id="<?php echo $customer['customer_id']; ?>">
                                                                <div class="d-flex justify-content-between">
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?></strong>
                                                                        <div class="small text-muted"><?php echo htmlspecialchars($customer['phone']); ?></div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="badge bg-primary">Customer</span>
                                                                    </div>
                                                                </div>
                                                                <div class="marker-controls">
                                                                    <button class="btn btn-sm btn-outline-primary random-location-btn" data-type="customer" data-id="<?php echo $customer['customer_id']; ?>">
                                                                        <i class="fas fa-random"></i> Random Location
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-secondary goto-playground-btn" data-type="customer" data-id="<?php echo $customer['customer_id']; ?>">
                                                                        <i class="fas fa-map-marker-alt"></i> Go to Playground
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-success text-white">
                                                <i class="fas fa-car me-1"></i> Drivers
                                            </div>
                                            <div class="card-body">
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="show-drivers" checked>
                                                    <label class="form-check-label" for="show-drivers">Show Drivers on Map</label>
                                                </div>
                                                
                                                <div class="drivers-list">
                                                    <?php if (empty($availableDrivers)): ?>
                                                        <div class="alert alert-info">No drivers available for simulation</div>
                                                    <?php else: ?>
                                                        <?php foreach ($availableDrivers as $index => $driver): ?>
                                                            <div class="simulation-user driver-item" data-id="<?php echo $driver['driver_id']; ?>">
                                                                <div class="d-flex justify-content-between">
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($driver['firstname'] . ' ' . $driver['lastname']); ?></strong>
                                                                        <div class="small text-muted"><?php echo htmlspecialchars($driver['phone']); ?></div>
                                                                    </div>
                                                                    <div>
                                                                        <span class="badge bg-success">Driver</span>
                                                                    </div>
                                                                </div>
                                                                <div class="marker-controls">
                                                                    <button class="btn btn-sm btn-outline-primary random-location-btn" data-type="driver" data-id="<?php echo $driver['driver_id']; ?>">
                                                                        <i class="fas fa-random"></i> Random Location
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-secondary goto-customer-btn" data-type="driver" data-id="<?php echo $driver['driver_id']; ?>">
                                                                        <i class="fas fa-user"></i> Go to Customer
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-info animate-route-btn" data-type="driver" data-id="<?php echo $driver['driver_id']; ?>">
                                                                        <i class="fas fa-route"></i> Animate Route
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header bg-info text-white">
                                                <i class="fas fa-cogs me-1"></i> Simulation Actions
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <button id="simulate-booking-btn" class="btn btn-primary w-100 mb-2">
                                                            <i class="fas fa-calendar-plus me-1"></i> Simulate Random Booking
                                                        </button>
                                                        <button id="simulate-multiple-bookings-btn" class="btn btn-info text-white w-100 mb-2">
                                                            <i class="fas fa-calendar-alt me-1"></i> Simulate Multiple Bookings
                                                        </button>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <button id="randomize-all-locations-btn" class="btn btn-warning w-100 mb-2">
                                                            <i class="fas fa-map-marked-alt me-1"></i> Randomize All Locations
                                                        </button>
                                                        <button id="reset-simulation-btn" class="btn btn-danger w-100 mb-2">
                                                            <i class="fas fa-trash me-1"></i> Reset Simulation
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bookings View Tab -->
                <div class="tab-pane fade" id="bookings-view" role="tabpanel" aria-labelledby="bookings-tab">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-calendar-alt me-1"></i> Booking History
                                </div>
                                <div class="card-body">
                                    <div class="booking-history" id="booking-history">
                                        <!-- Booking history will be populated via JavaScript -->
                                        <div class="text-center py-5">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2">Loading booking history...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Playground Info Modal -->
            <div class="modal fade" id="playgroundInfoModal" tabindex="-1" aria-labelledby="playgroundInfoModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="playgroundInfoModalLabel">Playground Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="modal-playground-details">
                                Loading...
                            </div>
                            <div class="text-center mt-3" id="modal-book-buttons">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success modal-book-btn">Book Now</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Core JS libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Global variables
            let map;
            let playgroundMarkers = [];
            let customerMarkers = {};
            let driverMarkers = {};
            let infoWindow;
            let directionsService;
            let directionsRenderer;
            let selectedPlayground = null;
            let simulationData = {
                customers: {},
                drivers: {},
                bookings: []
            };
            
            // Initialize the map
            function initMap() {
                // Center map on Manila
                const manila = { lat: 14.5995, lng: 120.9842 };
                
                map = new google.maps.Map(document.getElementById("map"), {
                    zoom: 12,
                    center: manila,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true,
                });
                
                // Initialize info window
                infoWindow = new google.maps.InfoWindow();
                
                // Initialize directions service and renderer
                directionsService = new google.maps.DirectionsService();
                directionsRenderer = new google.maps.DirectionsRenderer({
                    map: map,
                    suppressMarkers: true,
                    polylineOptions: {
                        strokeColor: "#0d6efd",
                        strokeWeight: 5,
                        strokeOpacity: 0.7
                    }
                });
                
                // Add playground markers
                addPlaygroundMarkers();
                
                // Initialize simulation data
                initializeSimulationData();
                
                // Add search box functionality
                const searchInput = document.getElementById('search-location');
                const autocomplete = new google.maps.places.Autocomplete(searchInput, {
                    componentRestrictions: { country: "ph" }
                });
                
                autocomplete.addListener('place_changed', function() {
                    const place = autocomplete.getPlace();
                    if (!place.geometry) {
                        alert("No location found for: " + place.name);
                        return;
                    }
                    
                    map.setCenter(place.geometry.location);
                    map.setZoom(15);
                });
                
                // Add search button click event
                document.getElementById('search-location-btn').addEventListener('click', function() {
                    const address = searchInput.value;
                    if (!address) {
                        alert("Please enter a location to search");
                        return;
                    }
                    
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: address + ", Philippines" }, function(results, status) {
                        if (status === "OK" && results[0]) {
                            map.setCenter(results[0].geometry.location);
                            map.setZoom(15);
                        } else {
                            alert("Geocode was not successful for the following reason: " + status);
                        }
                    });
                });
                
                // Add status filter functionality
                document.getElementById('filter-status').addEventListener('change', function() {
                    const status = this.value;
                    filterPlaygroundMarkers(status);
                });
                
                // Add "Locate Me" button functionality
                document.getElementById('locate-me-btn').addEventListener('click', function() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            function(position) {
                                const userLocation = {
                                    lat: position.coords.latitude,
                                    lng: position.coords.longitude
                                };
                                
                                map.setCenter(userLocation);
                                map.setZoom(15);
                                
                                // Add a marker for user's location
                                new google.maps.Marker({
                                    position: userLocation,
                                    map: map,
                                    icon: {
                                        url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                                        scaledSize: new google.maps.Size(32, 32)
                                    },
                                    title: 'Your Location',
                                    animation: google.maps.Animation.DROP
                                });
                                
                                // Show toast notification
                                Swal.fire({
                                    title: 'Location Found',
                                    text: 'Map centered to your current location',
                                    icon: 'success',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000
                                });
                            },
                            function(error) {
                                console.error('Error getting user location:', error);
                                Swal.fire({
                                    title: 'Location Error',
                                    text: 'Could not determine your location: ' + error.message,
                                    icon: 'error'
                                });
                            }
                        );
                    } else {
                        Swal.fire({
                            title: 'Geolocation Not Supported',
                            text: 'Your browser does not support geolocation',
                            icon: 'error'
                        });
                    }
                });
                
                // Add refresh button functionality
                document.getElementById('refresh-map-btn').addEventListener('click', function() {
                    refreshMap();
                });
                
                // Add simulation controls
                document.getElementById('show-simulation').addEventListener('change', function() {
                    toggleSimulationMarkers(this.checked);
                });
                
                document.getElementById('show-customers').addEventListener('change', function() {
                    toggleCustomerMarkers(this.checked);
                });
                
                document.getElementById('show-drivers').addEventListener('change', function() {
                    toggleDriverMarkers(this.checked);
                });
                
                // Setup tab change events
                const viewTabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
                viewTabs.forEach(tab => {
                    tab.addEventListener('shown.bs.tab', function(event) {
                        if (event.target.id === 'map-tab') {
                            // Trigger a resize event to make sure the map renders correctly
                            google.maps.event.trigger(map, 'resize');
                        } else if (event.target.id === 'simulation-tab') {
                            // Refresh simulation data when tab is shown
                            refreshSimulationPanel();
                        } else if (event.target.id === 'bookings-tab') {
                            // Load booking history when tab is shown
                            loadBookingHistory();
                        }
                    });
                });
                
                // Add click events for list view buttons
                document.querySelectorAll('.view-on-map-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const playgroundId = parseInt(this.getAttribute('data-id'));
                        viewPlaygroundOnMap(playgroundId);
                    });
                });
                
                document.querySelectorAll('.book-now-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const playgroundId = parseInt(this.getAttribute('data-id'));
                        openBookingForm(playgroundId);
                    });
                });
                
                // Setup booking form submission
                document.getElementById('playground-booking-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitBookingForm();
                });
                
                // Add simulation action buttons
                document.getElementById('simulate-booking-btn').addEventListener('click', function() {
                    simulateRandomBooking();
                });
                
                document.getElementById('simulate-multiple-bookings-btn').addEventListener('click', function() {
                    simulateMultipleBookings();
                });
                
                document.getElementById('randomize-all-locations-btn').addEventListener('click', function() {
                    randomizeAllLocations();
                });
                
                document.getElementById('reset-simulation-btn').addEventListener('click', function() {
                    resetSimulation();
                });
                
                // Setup simulation user click events
                document.querySelectorAll('.random-location-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const type = this.getAttribute('data-type');
                        const id = parseInt(this.getAttribute('data-id'));
                        randomizeLocation(type, id);
                    });
                });
                
                document.querySelectorAll('.goto-playground-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = parseInt(this.getAttribute('data-id'));
                        goToRandomPlayground('customer', id);
                    });
                });
                
                document.querySelectorAll('.goto-customer-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = parseInt(this.getAttribute('data-id'));
                        goToRandomCustomer('driver', id);
                    });
                });
                
                document.querySelectorAll('.animate-route-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = parseInt(this.getAttribute('data-id'));
                        animateRoute(id);
                    });
                });
                
                // Initialize the simulation data for customers and drivers
                initializeSimulationData();
                
                // Load initial booking history
                loadBookingHistory();
            }
            
            // Add playground markers to the map
            function addPlaygroundMarkers() {
                // Clear existing markers
                playgroundMarkers.forEach(marker => marker.setMap(null));
                playgroundMarkers = [];
                
                // Get playground data from PHP
                const playgrounds = <?php echo json_encode($playgrounds); ?>;
                
                // Add markers for each playground
                playgrounds.forEach(playground => {
                    const position = { lat: parseFloat(playground.lat), lng: parseFloat(playground.lng) };
                    
                    // Set marker icon based on status
                    let iconUrl;
                    switch (playground.status) {
                        case 'active':
                            iconUrl = 'https://maps.google.com/mapfiles/ms/icons/green-dot.png';
                            break;
                        case 'maintenance':
                            iconUrl = 'https://maps.google.com/mapfiles/ms/icons/yellow-dot.png';
                            break;
                        case 'closed':
                            iconUrl = 'https://maps.google.com/mapfiles/ms/icons/red-dot.png';
                            break;
                        default:
                            iconUrl = 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png';
                    }
                    
                    const marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: playground.name,
                        icon: {
                            url: iconUrl,
                            scaledSize: new google.maps.Size(32, 32)
                        },
                        animation: google.maps.Animation.DROP,
                        playground: playground
                    });
                    
                    // Add click listener to show info window
                    marker.addListener('click', function() {
                        selectedPlayground = playground;
                        
                        // Format status for display
                        const statusClass = playground.status === 'active' ? 'success' : 
                                           (playground.status === 'maintenance' ? 'warning' : 'danger');
                        
                        // Format opening/closing times
                        const openingTime = new Date(`2000-01-01T${playground.opening_time}`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const closingTime = new Date(`2000-01-01T${playground.closing_time}`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        
                        // Create info window content
                        const content = `
                            <div style="max-width: 300px; padding: 10px;">
                                <h5>${playground.name}</h5>
                                <div class="mb-2">
                                    <span class="badge bg-${statusClass}">${playground.status.toUpperCase()}</span>
                                </div>
                                <p><i class="fas fa-map-marker-alt me-2"></i> ${playground.address}</p>
                                <p><i class="fas fa-clock me-2"></i> ${openingTime} - ${closingTime}</p>
                                <p><i class="fas fa-users me-2"></i> Capacity: ${playground.capacity} people</p>
                                <p><i class="fas fa-peso-sign me-2"></i> ₱${playground.hourly_rate.toFixed(2)} per hour</p>
                                <div class="d-grid gap-2">
                                    <button id="info-window-book-btn" class="btn btn-sm btn-success" ${playground.status !== 'active' ? 'disabled' : ''}>
                                        <i class="fas fa-calendar-plus me-1"></i> Book Now
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        // Set info window content and open it
                        infoWindow.setContent(content);
                        infoWindow.open(map, marker);
                        
                        // Add click listener to book button inside info window
                        google.maps.event.addListenerOnce(infoWindow, 'domready', function() {
                            document.getElementById('info-window-book-btn').addEventListener('click', function() {
                                openBookingForm(playground.id);
                                infoWindow.close();
                            });
                        });
                        
                        // Update playground info in the sidebar
                        updatePlaygroundInfo(playground);
                    });
                    
                    playgroundMarkers.push(marker);
                });
                
                // Fit map to markers if there are any
                if (playgroundMarkers.length > 0) {
                    const bounds = new google.maps.LatLngBounds();
                    playgroundMarkers.forEach(marker => bounds.extend(marker.getPosition()));
                    map.fitBounds(bounds);
                    
                    // Don't zoom in too far
                    google.maps.event.addListenerOnce(map, 'idle', function() {
                        if (map.getZoom() > 15) {
                            map.setZoom(15);
                        }
                    });
                }
            }
            
            // Filter playground markers by status
            function filterPlaygroundMarkers(status) {
                playgroundMarkers.forEach(marker => {
                    if (status === 'all' || marker.playground.status === status) {
                        marker.setVisible(true);
                    } else {
                        marker.setVisible(false);
                    }
                });
            }
            
            // Update playground info in the sidebar
            function updatePlaygroundInfo(playground) {
                const infoContainer = document.getElementById('selected-playground-info');
                
                // Format status for display
                const statusClass = playground.status === 'active' ? 'success' : 
                                  (playground.status === 'maintenance' ? 'warning' : 'danger');
                
                // Format opening/closing times
                const openingTime = new Date(`2000-01-01T${playground.opening_time}`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const closingTime = new Date(`2000-01-01T${playground.closing_time}`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                // Create HTML content
                const content = `
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            ${playground.name}
                            <span class="badge bg-${statusClass}">${playground.status.toUpperCase()}</span>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-map-marker-alt me-2"></i> ${playground.address}</p>
                            <p><i class="fas fa-clock me-2"></i> ${openingTime} - ${closingTime}</p>
                            <p><i class="fas fa-users me-2"></i> Capacity: ${playground.capacity} people</p>
                            <p><i class="fas fa-peso-sign me-2"></i> ₱${playground.hourly_rate.toFixed(2)} per hour</p>
                            <p><i class="fas fa-list-ul me-2"></i> Features: ${playground.features}</p>
                            <div class="d-grid">
                                <button id="sidebar-book-btn" class="btn btn-success" ${playground.status !== 'active' ? 'disabled' : ''}>
                                    <i class="fas fa-calendar-plus me-1"></i> Book This Playground
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                infoContainer.innerHTML = content;
                
                // Add click listener to the book button
                if (playground.status === 'active') {
                    document.getElementById('sidebar-book-btn').addEventListener('click', function() {
                        openBookingForm(playground.id);
                    });
                }
                
                // Show booking form
                if (playground.status === 'active') {
                    document.getElementById('booking-form').style.display = 'block';
                } else {
                    document.getElementById('booking-form').style.display = 'none';
                }
            }
            
            // Initialize simulation data
            function initializeSimulationData() {
                // Get customer data from PHP
                const customers = <?php echo json_encode($activeCustomers); ?>;
                
                // Add random location to each customer
                customers.forEach(customer => {
                    // Generate random coordinates around Metro Manila
                    // Make sure we have non-null coordinates for each customer
                    let lat, lng;
                    
                    // Check if latitude/longitude are already available from the database
                    if (customer.latitude && customer.longitude) {
                        lat = parseFloat(customer.latitude);
                        lng = parseFloat(customer.longitude);
                    } else {
                        // Generate random coordinates if not available
                        lat = 14.58 + (Math.random() * 0.2 - 0.1);
                        lng = 120.98 + (Math.random() * 0.2 - 0.1);
                    }
                    
                    simulationData.customers[customer.customer_id] = {
                        id: customer.customer_id,
                        user_id: customer.user_id,
                        name: customer.firstname + ' ' + customer.lastname,
                        phone: customer.phone,
                        email: customer.email,
                        status: customer.customer_status,
                        lat: lat,
                        lng: lng,
                        lastUpdated: new Date()
                    };
                    
                    // Add marker for customer
                    addCustomerMarker(customer.customer_id);
                });
                
                // Get driver data from PHP
                const drivers = <?php echo json_encode($availableDrivers); ?>;
                
                // Add random location to each driver
                drivers.forEach(driver => {
                    // Check if latitude/longitude are already available from the database
                    let lat, lng;
                    
                    if (driver.latitude && driver.longitude) {
                        lat = parseFloat(driver.latitude);
                        lng = parseFloat(driver.longitude);
                    } else {
                        // Generate random coordinates if not available
                        lat = 14.58 + (Math.random() * 0.2 - 0.1);
                        lng = 120.98 + (Math.random() * 0.2 - 0.1);
                    }
                    
                    simulationData.drivers[driver.driver_id] = {
                        id: driver.driver_id,
                        user_id: driver.user_id,
                        name: driver.firstname + ' ' + driver.lastname,
                        phone: driver.phone,
                        email: driver.email,
                        status: driver.driver_status,
                        lat: lat,
                        lng: lng,
                        lastUpdated: new Date()
                    };
                    
                    // Add marker for driver
                    addDriverMarker(driver.driver_id);
                });
            }
            
            // Add customer marker to the map
            function addCustomerMarker(customerId) {
                // Remove existing marker if it exists
                if (customerMarkers[customerId]) {
                    customerMarkers[customerId].setMap(null);
                }
                
                const customer = simulationData.customers[customerId];
                if (!customer) return;
                
                const position = { lat: customer.lat, lng: customer.lng };
                
                const marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                        scaledSize: new google.maps.Size(24, 24)
                    },
                    title: `Customer: ${customer.name}`,
                    customer: customer
                });
                
                // Add click listener
                marker.addListener('click', function() {
                    const content = `
                        <div style="width: 200px; padding: 10px;">
                            <h6>Customer: ${customer.name}</h6>
                            <p><i class="fas fa-phone me-1"></i> ${customer.phone}</p>
                            <p><i class="fas fa-envelope me-1"></i> ${customer.email}</p>
                            <p><i class="fas fa-clock me-1"></i> Last Updated: ${customer.lastUpdated.toLocaleTimeString()}</p>
                        </div>
                    `;
                    
                    infoWindow.setContent(content);
                    infoWindow.open(map, marker);
                });
                
                customerMarkers[customerId] = marker;
            }
            
            // Add driver marker to the map
            function addDriverMarker(driverId) {
                // Remove existing marker if it exists
                if (driverMarkers[driverId]) {
                    driverMarkers[driverId].setMap(null);
                }
                
                const driver = simulationData.drivers[driverId];
                if (!driver) return;
                
                const position = { lat: driver.lat, lng: driver.lng };
                
                const marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                        scaledSize: new google.maps.Size(24, 24)
                    },
                    title: `Driver: ${driver.name}`,
                    driver: driver
                });
                
                // Add click listener
                marker.addListener('click', function() {
                    const content = `
                        <div style="width: 200px; padding: 10px;">
                            <h6>Driver: ${driver.name}</h6>
                            <p><i class="fas fa-phone me-1"></i> ${driver.phone}</p>
                            <p><i class="fas fa-envelope me-1"></i> ${driver.email}</p>
                            <p><i class="fas fa-clock me-1"></i> Last Updated: ${driver.lastUpdated.toLocaleTimeString()}</p>
                        </div>
                    `;
                    
                    infoWindow.setContent(content);
                    infoWindow.open(map, marker);
                });
                
                driverMarkers[driverId] = marker;
            }
            
            // Initialize after page load
            initMap();
            
            // View playground on map
            function viewPlaygroundOnMap(playgroundId) {
                // Find the playground marker
                const marker = playgroundMarkers.find(m => m.playground.id === playgroundId);
                
                if (marker) {
                    // Center map on the playground
                    map.setCenter(marker.getPosition());
                    map.setZoom(15);
                    
                    // Trigger the click event on the marker
                    google.maps.event.trigger(marker, 'click');
                    
                    // Switch to map tab
                    document.getElementById('map-tab').click();
                }
            }
            
            // Open booking form for a playground
            function openBookingForm(playgroundId) {
                // Find the playground
                const playground = <?php echo json_encode($playgrounds); ?>.find(p => p.id === playgroundId);
                
                if (playground) {
                    // Set selected playground
                    selectedPlayground = playground;
                    
                    // Set form values
                    document.getElementById('booking-playground-id').value = playground.id;
                    
                    // Set default date to today
                    const today = new Date();
                    const formattedDate = today.toISOString().split('T')[0];
                    document.getElementById('booking-date').value = formattedDate;
                    
                    // Set default start time to next available hour
                    const nextHour = new Date();
                    nextHour.setHours(nextHour.getHours() + 1, 0, 0, 0);
                    const startTime = nextHour.toTimeString().substring(0, 5);
                    document.getElementById('booking-start-time').value = startTime;
                    
                    // Set default end time to two hours after start
                    const endHour = new Date(nextHour);
                    endHour.setHours(endHour.getHours() + 2);
                    const endTime = endHour.toTimeString().substring(0, 5);
                    document.getElementById('booking-end-time').value = endTime;
                    
                    // Set default participants to half capacity
                    document.getElementById('booking-participants').value = Math.ceil(playground.capacity / 2);
                    document.getElementById('booking-participants').max = playground.capacity;
                    
                    // Clear notes
                    document.getElementById('booking-notes').value = '';
                    
                    // Show booking form
                    document.getElementById('booking-form').style.display = 'block';
                    
                    // Switch to map tab if not already there
                    document.getElementById('map-tab').click();
                    
                    // Update playground info
                    updatePlaygroundInfo(playground);
                }
            }
            
            // Submit booking form
            function submitBookingForm() {
                // Get form values
                const playgroundId = document.getElementById('booking-playground-id').value;
                const bookingDate = document.getElementById('booking-date').value;
                const startTime = document.getElementById('booking-start-time').value;
                const endTime = document.getElementById('booking-end-time').value;
                const participants = document.getElementById('booking-participants').value;
                const notes = document.getElementById('booking-notes').value;
                
                // Basic validation
                if (!playgroundId || !bookingDate || !startTime || !endTime || !participants) {
                    Swal.fire({
                        title: 'Validation Error',
                        text: 'Please fill in all required fields',
                        icon: 'error'
                    });
                    return;
                }
                
                // Show loading
                Swal.fire({
                    title: 'Processing',
                    html: 'Creating your booking...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // In a real implementation, this would be an AJAX call to the server
                // For demonstration, we'll just simulate a successful booking
                setTimeout(() => {
                    // Create a "virtual" booking
                    const booking = {
                        id: Math.floor(Math.random() * 10000),
                        playground_id: playgroundId,
                        playground_name: selectedPlayground.name,
                        booking_date: bookingDate,
                        start_time: startTime,
                        end_time: endTime,
                        participants: participants,
                        notes: notes,
                        status: 'confirmed',
                        created_at: new Date().toISOString(),
                        total_amount: calculateBookingCost(startTime, endTime, selectedPlayground.hourly_rate)
                    };
                    
                    // Add to simulation data
                    simulationData.bookings.unshift(booking);
                    
                    // Show success message
                    Swal.fire({
                        title: 'Booking Confirmed!',
                        html: `
                            <p>Your booking for <strong>${selectedPlayground.name}</strong> has been confirmed.</p>
                            <p>Date: ${formatDate(bookingDate)}</p>
                            <p>Time: ${formatTime(startTime)} - ${formatTime(endTime)}</p>
                            <p>Total Cost: ₱${booking.total_amount.toFixed(2)}</p>
                        `,
                        icon: 'success'
                    });
                    
                    // Reset form
                    document.getElementById('booking-form').style.display = 'none';
                    
                    // Update booking history
                    loadBookingHistory();
                    
                    // Switch to bookings tab
                    document.getElementById('bookings-tab').click();
                }, 1500);
            }
            
            // Calculate booking cost based on duration and hourly rate
            function calculateBookingCost(startTime, endTime, hourlyRate) {
                // Parse times
                const start = new Date(`2000-01-01T${startTime}`);
                const end = new Date(`2000-01-01T${endTime}`);
                
                // Calculate duration in hours
                const durationMs = end - start;
                const durationHours = durationMs / (1000 * 60 * 60);
                
                // Calculate cost
                return durationHours * hourlyRate;
            }
            
            // Load booking history
            function loadBookingHistory() {
                const historyContainer = document.getElementById('booking-history');
                
                // In a real implementation, this would be an AJAX call to the server
                // For demonstration, we'll use the simulated bookings
                
                if (simulationData.bookings.length === 0) {
                    historyContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No booking history found. Create a booking to see it here.
                        </div>
                    `;
                    return;
                }
                
                // Create HTML for booking history
                let html = '';
                
                simulationData.bookings.forEach(booking => {
                    const statusClass = booking.status === 'confirmed' ? 'success' : 
                                      (booking.status === 'pending' ? 'warning' : 'danger');
                    
                    html += `
                        <div class="booking-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6>${booking.playground_name}</h6>
                                <span class="badge bg-${statusClass}">${booking.status.toUpperCase()}</span>
                            </div>
                            <p><i class="fas fa-calendar me-2"></i> ${formatDate(booking.booking_date)}</p>
                            <p><i class="fas fa-clock me-2"></i> ${formatTime(booking.start_time)} - ${formatTime(booking.end_time)}</p>
                            <p><i class="fas fa-users me-2"></i> ${booking.participants} participants</p>
                            <p><i class="fas fa-money-bill-wave me-2"></i> Total: ₱${booking.total_amount.toFixed(2)}</p>
                        </div>
                    `;
                });
                
                historyContainer.innerHTML = html;
            }
            
            // Refresh map
            function refreshMap() {
                // Show loading indicator
                const refreshBtn = document.getElementById('refresh-map-btn');
                const originalContent = refreshBtn.innerHTML;
                refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Refreshing...';
                refreshBtn.disabled = true;
                
                // In a real implementation, this would be an AJAX call to refresh data
                // For demonstration, we'll just refresh the map
                setTimeout(() => {
                    // Refresh playground markers
                    addPlaygroundMarkers();
                    
                    // Refresh simulation markers
                    Object.keys(simulationData.customers).forEach(id => {
                        addCustomerMarker(id);
                    });
                    
                    Object.keys(simulationData.drivers).forEach(id => {
                        addDriverMarker(id);
                    });
                    
                    // Show success toast
                    Swal.fire({
                        title: 'Map Refreshed',
                        text: 'Map data has been updated',
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                    
                    // Restore refresh button
                    refreshBtn.innerHTML = originalContent;
                    refreshBtn.disabled = false;
                }, 1000);
            }
            
            // Toggle all simulation markers
            function toggleSimulationMarkers(show) {
                toggleCustomerMarkers(show);
                toggleDriverMarkers(show);
            }
            
            // Toggle customer markers
            function toggleCustomerMarkers(show) {
                Object.values(customerMarkers).forEach(marker => {
                    marker.setVisible(show);
                });
                
                // Update checkbox if different from current state
                const checkbox = document.getElementById('show-customers');
                if (checkbox.checked !== show) {
                    checkbox.checked = show;
                }
            }
            
            // Toggle driver markers
            function toggleDriverMarkers(show) {
                Object.values(driverMarkers).forEach(marker => {
                    marker.setVisible(show);
                });
                
                // Update checkbox if different from current state
                const checkbox = document.getElementById('show-drivers');
                if (checkbox.checked !== show) {
                    checkbox.checked = show;
                }
            }
            
            // Refresh simulation panel
            function refreshSimulationPanel() {
                // Currently nothing to update in the simulation panel
                // This function would be used to refresh data from the server
            }
            
            // Simulate random booking
            function simulateRandomBooking() {
                // Get random playground, customer, and driver
                const playgrounds = <?php echo json_encode($playgrounds); ?>;
                const activePlaygrounds = playgrounds.filter(p => p.status === 'active');
                
                if (activePlaygrounds.length === 0) {
                    Swal.fire({
                        title: 'Simulation Error',
                        text: 'No active playgrounds available for booking',
                        icon: 'error'
                    });
                    return;
                }
                
                const customerIds = Object.keys(simulationData.customers);
                if (customerIds.length === 0) {
                    Swal.fire({
                        title: 'Simulation Error',
                        text: 'No customers available for simulation',
                        icon: 'error'
                    });
                    return;
                }
                
                // Select random entities
                const randomPlayground = activePlaygrounds[Math.floor(Math.random() * activePlaygrounds.length)];
                const randomCustomerId = customerIds[Math.floor(Math.random() * customerIds.length)];
                const randomCustomer = simulationData.customers[randomCustomerId];
                
                // Generate random booking details
                const today = new Date();
                const bookingDate = new Date(today);
                bookingDate.setDate(today.getDate() + Math.floor(Math.random() * 7));
                
                const startHour = 9 + Math.floor(Math.random() * 8); // Between 9 AM and 4 PM
                const startTime = `${startHour.toString().padStart(2, '0')}:00`;
                
                const durationHours = 1 + Math.floor(Math.random() * 3); // 1-3 hours
                const endHour = startHour + durationHours;
                const endTime = `${endHour.toString().padStart(2, '0')}:00`;
                
                const participants = 1 + Math.floor(Math.random() * randomPlayground.capacity);
                
                // Calculate total cost
                const totalAmount = calculateBookingCost(startTime, endTime, randomPlayground.hourly_rate);
                
                // Create the booking
                const booking = {
                    id: Math.floor(Math.random() * 10000),
                    playground_id: randomPlayground.id,
                    playground_name: randomPlayground.name,
                    customer_id: randomCustomer.id,
                    customer_name: randomCustomer.name,
                    booking_date: bookingDate.toISOString().split('T')[0],
                    start_time: startTime,
                    end_time: endTime,
                    participants: participants,
                    notes: 'Simulated booking',
                    status: 'confirmed',
                    created_at: new Date().toISOString(),
                    total_amount: totalAmount
                };
                
                // Move customer to playground location
                simulationData.customers[randomCustomerId].lat = randomPlayground.lat;
                simulationData.customers[randomCustomerId].lng = randomPlayground.lng;
                simulationData.customers[randomCustomerId].lastUpdated = new Date();
                
                // Update customer marker
                addCustomerMarker(randomCustomerId);
                
                // Add to simulation data
                simulationData.bookings.unshift(booking);
                
                // Show success notification
                Swal.fire({
                    title: 'Booking Simulated',
                    html: `
                        <p>Created booking for <strong>${randomCustomer.name}</strong> at <strong>${randomPlayground.name}</strong>.</p>
                        <p>Date: ${formatDate(booking.booking_date)}</p>
                        <p>Time: ${formatTime(startTime)} - ${formatTime(endTime)}</p>
                        <p>Total Cost: ₱${totalAmount.toFixed(2)}</p>
                    `,
                    icon: 'success'
                });
                
                // Update booking history
                loadBookingHistory();
            }
            
            // Simulate multiple bookings
            function simulateMultipleBookings() {
                // Ask how many bookings to simulate
                Swal.fire({
                    title: 'Simulate Multiple Bookings',
                    text: 'How many bookings would you like to simulate?',
                    input: 'number',
                    inputValue: 5,
                    inputAttributes: {
                        min: 1,
                        max: 20,
                        step: 1
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Simulate',
                    showLoaderOnConfirm: true,
                    preConfirm: (count) => {
                        return new Promise((resolve) => {
                            setTimeout(() => {
                                // Simulate the bookings one by one
                                for (let i = 0; i < count; i++) {
                                    simulateRandomBooking();
                                }
                                resolve();
                            }, 1000);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Simulation Complete',
                            text: `Successfully simulated ${result.value} bookings`,
                            icon: 'success'
                        });
                    }
                });
            }
            
            // Randomize all locations
            function randomizeAllLocations() {
                // Ask for confirmation
                Swal.fire({
                    title: 'Randomize All Locations',
                    text: 'This will randomize the locations of all customers and drivers. Continue?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Randomize'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Randomize customer locations
                        Object.keys(simulationData.customers).forEach(id => {
                            randomizeLocation('customer', parseInt(id));
                        });
                        
                        // Randomize driver locations
                        Object.keys(simulationData.drivers).forEach(id => {
                            randomizeLocation('driver', parseInt(id));
                        });
                        
                        // Show success notification
                        Swal.fire({
                            title: 'Locations Randomized',
                            text: 'All customer and driver locations have been randomized',
                            icon: 'success',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                });
            }
            
            // Randomize location of a user
            function randomizeLocation(type, id) {
                // Generate random coordinates around Metro Manila
                const lat = 14.58 + (Math.random() * 0.2 - 0.1);
                const lng = 120.98 + (Math.random() * 0.2 - 0.1);
                
                // Update simulation data
                if (type === 'customer' && simulationData.customers[id]) {
                    simulationData.customers[id].lat = lat;
                    simulationData.customers[id].lng = lng;
                    simulationData.customers[id].lastUpdated = new Date();
                    
                    // Update marker
                    addCustomerMarker(id);
                    
                    // Save to database if needed - API call would go here
                    saveCustomerLocation(id, lat, lng);
                } else if (type === 'driver' && simulationData.drivers[id]) {
                    simulationData.drivers[id].lat = lat;
                    simulationData.drivers[id].lng = lng;
                    simulationData.drivers[id].lastUpdated = new Date();
                    
                    // Update marker
                    addDriverMarker(id);
                    
                    // Save to database if needed - API call would go here
                    saveDriverLocation(id, lat, lng);
                }
            }
            
            // Go to random playground
            function goToRandomPlayground(type, id) {
                const playgrounds = <?php echo json_encode($playgrounds); ?>;
                if (playgrounds.length === 0) return;
                
                // Select random playground
                const randomPlayground = playgrounds[Math.floor(Math.random() * playgrounds.length)];
                
                // Move user to playground location
                if (type === 'customer' && simulationData.customers[id]) {
                    simulationData.customers[id].lat = parseFloat(randomPlayground.lat);
                    simulationData.customers[id].lng = parseFloat(randomPlayground.lng);
                    simulationData.customers[id].lastUpdated = new Date();
                    
                    // Update marker
                    addCustomerMarker(id);
                    
                    // Center map on new location
                    map.setCenter({
                        lat: parseFloat(randomPlayground.lat),
                        lng: parseFloat(randomPlayground.lng)
                    });
                    map.setZoom(15);
                    
                    // Show toast notification
                    Swal.fire({
                        title: 'Customer Moved',
                        text: `Customer moved to ${randomPlayground.name}`,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else if (type === 'driver' && simulationData.drivers[id]) {
                    // For driver, we'll animate the movement
                    const startLocation = {
                        lat: simulationData.drivers[id].lat,
                        lng: simulationData.drivers[id].lng
                    };
                    
                    const endLocation = {
                        lat: parseFloat(randomPlayground.lat),
                        lng: parseFloat(randomPlayground.lng)
                    };
                    
                    // Calculate route and animate
                    calculateAndAnimateRoute(id, startLocation, endLocation);
                }
            }
            
            // Go to random customer
            function goToRandomCustomer(type, id) {
                const customerIds = Object.keys(simulationData.customers);
                if (customerIds.length === 0) return;
                
                // Select random customer
                const randomCustomerId = customerIds[Math.floor(Math.random() * customerIds.length)];
                const randomCustomer = simulationData.customers[randomCustomerId];
                
                // Only for drivers
                if (type === 'driver' && simulationData.drivers[id]) {
                    const startLocation = {
                        lat: simulationData.drivers[id].lat,
                        lng: simulationData.drivers[id].lng
                    };
                    
                    const endLocation = {
                        lat: randomCustomer.lat,
                        lng: randomCustomer.lng
                    };
                    
                    // Calculate route and animate
                    calculateAndAnimateRoute(id, startLocation, endLocation);
                }
            }
            
            // Animate route for a driver
            function animateRoute(driverId) {
                const driver = simulationData.drivers[driverId];
                if (!driver) return;
                
                // Generate random destination
                const lat = 14.58 + (Math.random() * 0.2 - 0.1);
                const lng = 120.98 + (Math.random() * 0.2 - 0.1);
                
                const startLocation = {
                    lat: driver.lat,
                    lng: driver.lng
                };
                
                const endLocation = { lat, lng };
                
                // Calculate route and animate
                calculateAndAnimateRoute(driverId, startLocation, endLocation);
            }
            
            // Calculate route and animate driver movement
            function calculateAndAnimateRoute(driverId, startLocation, endLocation) {
                // Calculate route
                directionsService.route(
                    {
                        origin: startLocation,
                        destination: endLocation,
                        travelMode: google.maps.TravelMode.DRIVING
                    },
                    function(response, status) {
                        if (status === "OK") {
                            // Display the route
                            directionsRenderer.setDirections(response);
                            
                            const route = response.routes[0];
                            const path = route.overview_path;
                            
                            // Zoom to show the entire route
                            const bounds = new google.maps.LatLngBounds();
                            path.forEach(point => bounds.extend(point));
                            map.fitBounds(bounds);
                            
                            // Show toast notification
                            Swal.fire({
                                title: 'Route Calculated',
                                text: 'Driver is now moving along the route',
                                icon: 'info',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000
                            });
                            
                            // Animate driver along the route
                            animateDriverAlongRoute(driverId, path);
                        } else {
                            console.error("Directions request failed due to " + status);
                            Swal.fire({
                                title: 'Route Error',
                                text: 'Could not calculate route: ' + status,
                                icon: 'error'
                            });
                        }
                    }
                );
            }
            
            // Animate driver along a route
            function animateDriverAlongRoute(driverId, path) {
                const driver = simulationData.drivers[driverId];
                if (!driver) return;
                
                // Remove existing marker
                if (driverMarkers[driverId]) {
                    driverMarkers[driverId].setMap(null);
                }
                
                // Create new marker for animation
                const marker = new google.maps.Marker({
                    position: path[0],
                    map: map,
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                        scaledSize: new google.maps.Size(24, 24)
                    },
                    title: `Driver: ${driver.name}`,
                    driver: driver
                });
                
                // Store new marker
                driverMarkers[driverId] = marker;
                
                // Variables for animation
                let currentPathIndex = 0;
                const numSteps = 100; // Number of steps between each path point
                let step = 0;
                const speed = 50; // Animation speed (milliseconds)
                
                function animate() {
                    if (currentPathIndex < path.length - 1) {
                        step++;
                        
                        if (step >= numSteps) {
                            step = 0;
                            currentPathIndex++;
                        }
                        
                        if (currentPathIndex < path.length - 1) {
                            const p1 = path[currentPathIndex];
                            const p2 = path[currentPathIndex + 1];
                            
                            // Interpolate between the two points
                            const lat = p1.lat() + (p2.lat() - p1.lat()) * (step / numSteps);
                            const lng = p1.lng() + (p2.lng() - p1.lng()) * (step / numSteps);
                            
                            const newPos = new google.maps.LatLng(lat, lng);
                            marker.setPosition(newPos);
                            
                            // Update driver data
                            simulationData.drivers[driverId].lat = lat;
                            simulationData.drivers[driverId].lng = lng;
                            simulationData.drivers[driverId].lastUpdated = new Date();
                            
                            // Save location to database every 10 steps
                            if (step % 10 === 0) {
                                saveDriverLocation(driverId, lat, lng);
                            }
                            
                            // Calculate marker heading for rotation
                            const heading = google.maps.geometry.spherical.computeHeading(p1, p2);
                            
                            // Request next animation frame
                            setTimeout(animate, speed);
                        } else {
                            // Animation complete
                            Swal.fire({
                                title: 'Destination Reached',
                                text: 'Driver has arrived at the destination',
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000
                            });
                            
                            // Save final location to database
                            const finalLocation = path[path.length - 1];
                            saveDriverLocation(driverId, finalLocation.lat(), finalLocation.lng());
                            
                            // Clear directions
                            directionsRenderer.setDirections({ routes: [] });
                        }
                    }
                }
                
                // Start animation
                animate();
            }
            
            // Reset simulation
            function resetSimulation() {
                // Ask for confirmation
                Swal.fire({
                    title: 'Reset Simulation',
                    text: 'This will reset all simulation data including bookings, driver and customer positions. Continue?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Reset',
                    confirmButtonColor: '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Clear all markers
                        Object.values(customerMarkers).forEach(marker => marker.setMap(null));
                        Object.values(driverMarkers).forEach(marker => marker.setMap(null));
                        
                        // Reset customer and driver markers
                        customerMarkers = {};
                        driverMarkers = {};
                        
                        // Reset simulation data
                        simulationData.bookings = [];
                        
                        // Reinitialize simulation data
                        initializeSimulationData();
                        
                        // Clear directions
                        directionsRenderer.setDirections({ routes: [] });
                        
                        // Update booking history
                        loadBookingHistory();
                        
                        // Show success notification
                        Swal.fire({
                            title: 'Simulation Reset',
                            text: 'All simulation data has been reset',
                            icon: 'success'
                        });
                    }
                });
            }
            
            // Format date for display
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
            
            // Format time for display
            function formatTime(timeString) {
                const time = new Date(`2000-01-01T${timeString}`);
                return time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
            
            // Add function to save customer location to database
            function saveCustomerLocation(customerId, lat, lng) {
                // Make an AJAX call to update the customer location
                $.ajax({
                    url: '/api/customers/update_location.php',
                    method: 'POST',
                    data: {
                        customer_id: customerId,
                        latitude: lat,
                        longitude: lng
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Customer location updated successfully:', response.message);
                        } else {
                            console.warn('Failed to update customer location:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating customer location:', error);
                    }
                });
            }
            
            // Add function to save driver location to database
            function saveDriverLocation(driverId, lat, lng) {
                // Make an AJAX call to update the driver location
                $.ajax({
                    url: '/api/drivers/update_location.php',
                    method: 'POST',
                    data: {
                        driver_id: driverId,
                        latitude: lat,
                        longitude: lng
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Driver location updated successfully:', response.message);
                        } else {
                            console.warn('Failed to update driver location:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating driver location:', error);
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php endif; ?>

<?php
// Add JavaScript redirect if needed
if (isset($needRedirect) && $needRedirect) {
    echo '<script>window.location.href = "' . $redirectTo . '";</script>';
    exit;
}
?> 