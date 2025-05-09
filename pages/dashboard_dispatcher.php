<?php
// Check if user has dispatcher role
if (!hasRole('dispatcher') && !hasPermission('dispatch')) {
    echo '<div class="alert alert-danger">You do not have permission to access this dashboard.</div>';
    exit;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dispatcher Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDashboard">
                <i class="fas fa-sync"></i> Refresh
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-filter"></i> Filter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#">All Bookings</a></li>
                <li><a class="dropdown-item" href="#">Pending Bookings</a></li>
                <li><a class="dropdown-item" href="#">Active Bookings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Today's Bookings</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Quick Action Buttons -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="index.php?page=bookings" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i> Manage Bookings
                    </a>
                    <a href="index.php?page=test_driver_locations" class="btn btn-info text-white">
                        <i class="fas fa-map-marker-alt me-2"></i> Track Drivers
                    </a>
                    <a href="index.php?page=customers" class="btn btn-success">
                        <i class="fas fa-users me-2"></i> Manage Customers
                    </a>
                    <a href="index.php?page=drivers" class="btn btn-warning text-dark">
                        <i class="fas fa-id-card me-2"></i> Manage Drivers
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Map and Booking Controls -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i> Live Fleet Map</h5>
            </div>
            <div class="card-body p-0">
                <div id="driverLocationMap" style="height: 400px; background-color: #e9ecef;">
                    <!-- Map will be loaded here by JS -->
                    <div class="text-center p-5 d-none" id="map-placeholder">
                        <i class="fas fa-map-marked-alt fa-4x text-secondary mb-3"></i>
                        <h5>Interactive Map View</h5>
                        <p class="text-muted">Loading driver locations...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-taxi me-2"></i> Active Vehicles</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="activeDriversList">
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Toyota Innova (ABC-123)</h6>
                                <small class="text-muted">Driver: John Smith</small>
                            </div>
                            <span class="badge bg-success rounded-pill">Available</span>
                        </div>
                        <div class="d-grid gap-2 d-md-block mt-2">
                            <button class="btn btn-sm btn-outline-primary">Assign</button>
                            <button class="btn btn-sm btn-outline-info">Contact</button>
                            <button class="btn btn-sm btn-outline-secondary">Details</button>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Honda Civic (DEF-456)</h6>
                                <small class="text-muted">Driver: Mike Johnson</small>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill">On Trip</span>
                        </div>
                        <div class="d-grid gap-2 d-md-block mt-2">
                            <button class="btn btn-sm btn-outline-primary" disabled>Assign</button>
                            <button class="btn btn-sm btn-outline-info">Contact</button>
                            <button class="btn btn-sm btn-outline-secondary">Details</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Bookings -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Pending Bookings</h5>
        <a href="index.php?page=bookings" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-plus me-1"></i> New Booking
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Pickup Location</th>
                        <th>Dropoff Location</th>
                        <th>Pickup Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pendingBookingsList">
                    <tr>
                        <td><strong>#8742</strong></td>
                        <td>Maria Garcia</td>
                        <td>123 Main St, Manila</td>
                        <td>Ayala Mall, Makati</td>
                        <td>Today, 2:30 PM</td>
                        <td><span class="badge bg-warning text-dark">Pending</span></td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-primary">Assign</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary">Details</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>#8743</strong></td>
                        <td>James Wilson</td>
                        <td>456 Oak Ave, Quezon City</td>
                        <td>SM Megamall, Mandaluyong</td>
                        <td>Today, 3:15 PM</td>
                        <td><span class="badge bg-warning text-dark">Pending</span></td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-primary">Assign</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary">Details</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Current Trips & Driver Status -->
<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-route me-2"></i> Current Trips</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Booking ID</th>
                                <th>Driver</th>
                                <th>Vehicle</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>ETA</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="currentTripsList">
                            <tr>
                                <td><strong>#8735</strong></td>
                                <td>Mike Johnson</td>
                                <td>DEF-456</td>
                                <td>David Lee</td>
                                <td><span class="badge bg-info">In Progress</span></td>
                                <td>20 min</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary">Track</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user-check me-2"></i> Driver Status Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border rounded p-3 mb-3">
                            <h3 class="text-success" id="availableDriversCount">12</h3>
                            <div class="small text-muted">Available</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 mb-3">
                            <h3 class="text-primary" id="onTripDriversCount">8</h3>
                            <div class="small text-muted">On Trip</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 mb-3">
                            <h3 class="text-secondary" id="offlineDriversCount">5</h3>
                            <div class="small text-muted">Offline</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to make this dashboard interactive -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dispatcher Dashboard loaded');
    
    // Refresh dashboard data every 30 seconds
    const refreshInterval = 30000; // 30 seconds
    let refreshTimer;
    
    // Function to refresh dashboard data
    function refreshDashboardData() {
        console.log('Refreshing dashboard data...');
        
        // Fetch driver locations
        if (typeof driverLocationsModule !== 'undefined') {
            driverLocationsModule.refresh();
        }
        
        // Fetch pending bookings (placeholder for now)
        // In a real implementation, this would make an AJAX call to fetch real data
        
        // Reset refresh timer
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshDashboardData, refreshInterval);
    }
    
    // Initialize dashboard components
    function initDashboard() {
        // Initialize driver locations map if the module is available
        if (typeof driverLocationsModule !== 'undefined') {
            driverLocationsModule.init('driverLocationMap', refreshInterval);
        } else {
            console.warn('Driver locations module not loaded');
            document.getElementById('map-placeholder').classList.remove('d-none');
        }
        
        // Set up refresh button
        document.getElementById('refreshDashboard').addEventListener('click', function() {
            refreshDashboardData();
        });
        
        // Initial data load
        refreshDashboardData();
    }
    
    // Initialize the dashboard
    initDashboard();
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        clearTimeout(refreshTimer);
        if (typeof driverLocationsModule !== 'undefined') {
            driverLocationsModule.cleanup();
        }
    });
});
</script> 