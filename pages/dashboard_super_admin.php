<?php
// Super Admin Dashboard
if (!hasRole('super_admin')) {
    header('Location: index.php?page=access_denied');
    exit;
}

// Connect to databases
$conn1 = connectToCore1DB();
$conn2 = connectToCore2DB();

// Get statistics from Core1
$vehicleStatsQuery = "SELECT 
    COUNT(*) AS total_vehicles,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_vehicles,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_vehicles,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_vehicles
FROM vehicles";
$vehicleStatsResult = $conn1->query($vehicleStatsQuery);
if ($vehicleStatsResult) {
    $vehicleStats = $vehicleStatsResult->fetch_assoc();
} else {
    // Set default values if query failed
    $vehicleStats = [
        'total_vehicles' => 0,
        'active_vehicles' => 0, 
        'maintenance_vehicles' => 0,
        'inactive_vehicles' => 0
    ];
    // Optionally log the error
    error_log("Database query failed: " . $conn1->error);
}

$driverStatsQuery = "SELECT 
    COUNT(*) AS total_drivers,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_drivers,
    SUM(CASE WHEN status = 'busy' THEN 1 ELSE 0 END) AS busy_drivers,
    SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) AS offline_drivers
FROM drivers";
$driverStatsResult = $conn1->query($driverStatsQuery);
if ($driverStatsResult) {
    $driverStats = $driverStatsResult->fetch_assoc();
} else {
    // Set default values if query failed
    $driverStats = [
        'total_drivers' => 0,
        'available_drivers' => 0,
        'busy_drivers' => 0,
        'offline_drivers' => 0
    ];
    // Optionally log the error
    error_log("Database query failed: " . $conn1->error);
}

// Get statistics from core2_movers.bookings.booking_status enum 'pending','confirmed','in_progress','completed','cancelled'
$bookingStatsQuery = "SELECT 
    COUNT(*) AS total_bookings,
    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) AS pending_bookings,
    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_bookings,
    SUM(CASE WHEN booking_status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_bookings,
    SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) AS completed_bookings,
    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings
FROM core1_movers2.bookings";
$bookingStatsResult = $conn2->query($bookingStatsQuery);
if ($bookingStatsResult) {
    $bookingStats = $bookingStatsResult->fetch_assoc();
} else {
    // Set default values if query failed
    $bookingStats = [
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'confirmed_bookings' => 0,
        'in_progress_bookings' => 0,
        'completed_bookings' => 0,
        'cancelled_bookings' => 0
    ];
    // Optionally log the error
    error_log("Database query failed: " . $conn2->error);
}

$userStatsQuery = "SELECT 
    COUNT(*) AS total_users,
    SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) AS super_admin_count,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin_count,
    SUM(CASE WHEN role = 'dispatch' THEN 1 ELSE 0 END) AS dispatch_count,
    SUM(CASE WHEN role = 'finance' THEN 1 ELSE 0 END) AS finance_count,
    SUM(CASE WHEN role = 'driver' THEN 1 ELSE 0 END) AS driver_count,
    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) AS customer_count
FROM users";
$userStatsResult = $conn2->query($userStatsQuery);
if ($userStatsResult) {
    $userStats = $userStatsResult->fetch_assoc();
} else {
    // Set default values if query failed
    $userStats = [
        'total_users' => 0,
        'super_admin_count' => 0,
        'admin_count' => 0,
        'dispatch_count' => 0,
        'finance_count' => 0,
        'driver_count' => 0,
        'customer_count' => 0
    ];
    // Optionally log the error
    error_log("Database query failed: " . $conn2->error);
}

// Get recent activities from auth_logs
$recentActivityQuery = "SELECT al.*, u.firstname, u.lastname 
                      FROM auth_logs al
                      LEFT JOIN users u ON al.user_id = u.user_id
                      ORDER BY al.created_at DESC LIMIT 10";
$recentActivityResult = $conn2->query($recentActivityQuery);
if (!$recentActivityResult) {
    // Log the error
    error_log("Database query failed: " . $conn2->error);
    // Set $recentActivityResult to false to handle it in the display section
    $recentActivityResult = false;
}

// Close connections
$conn1->close();
$conn2->close();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Super Admin Dashboard</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>
    
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $vehicleStats['total_vehicles']; ?></h5>
                    <p class="card-text">Total Vehicles</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-light text-primary"><?php echo $vehicleStats['active_vehicles']; ?> Active</span>
                        <span class="badge bg-warning text-dark"><?php echo $vehicleStats['maintenance_vehicles']; ?> Maintenance</span>
                        <span class="badge bg-danger"><?php echo $vehicleStats['inactive_vehicles']; ?> Inactive</span>
                    </div>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $driverStats['total_drivers']; ?></h5>
                    <p class="card-text">Total Drivers</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-light text-success"><?php echo $driverStats['available_drivers']; ?> Available</span>
                        <span class="badge bg-warning text-dark"><?php echo $driverStats['busy_drivers']; ?> Busy</span>
                        <span class="badge bg-danger"><?php echo $driverStats['offline_drivers']; ?> Offline</span>
                    </div>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $bookingStats['total_bookings']; ?></h5>
                    <p class="card-text">Total Bookings</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-warning text-dark"><?php echo $bookingStats['pending_bookings']; ?> Pending</span>
                        <span class="badge bg-primary"><?php echo $bookingStats['confirmed_bookings']; ?> Confirmed</span>
                        <span class="badge bg-info text-white"><?php echo $bookingStats['in_progress_bookings']; ?> In Progress</span>
                        <span class="badge bg-light text-dark"><?php echo $bookingStats['completed_bookings']; ?> Completed</span>
                    </div>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-secondary text-white mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $userStats['total_users']; ?></h5>
                    <p class="card-text">Total Users</p>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=users">View Users</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    User Distribution
                </div>
                <div class="card-body">
                    <canvas id="userRoleChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-car me-1"></i>
                    Vehicle Status Distribution
                </div>
                <div class="card-body">
                    <canvas id="vehicleStatusChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-history me-1"></i>
            Recent Login Activity
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>IP Address</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentActivityResult): ?>
                    <?php while ($activity = $recentActivityResult->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php 
                            if (!empty($activity['firstname']) && !empty($activity['lastname'])) {
                                echo htmlspecialchars($activity['firstname'] . ' ' . $activity['lastname']);
                            } else {
                                echo htmlspecialchars($activity['email'] ?? 'Unknown');
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            $actionClass = '';
                            switch($activity['action']) {
                                case 'login': $actionClass = 'text-success'; break;
                                case 'logout': $actionClass = 'text-secondary'; break;
                                case 'failed_login': $actionClass = 'text-danger'; break;
                                default: $actionClass = '';
                            }
                            ?>
                            <span class="<?php echo $actionClass; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                        <td><?php echo date('M d, Y H:i:s', strtotime($activity['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="4">No recent activities found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // User Role Chart
    var userCtx = document.getElementById('userRoleChart').getContext('2d');
    var userChart = new Chart(userCtx, {
        type: 'pie',
        data: {
            labels: ['Super Admin', 'Admin', 'Dispatch', 'Finance', 'Driver', 'Customer'],
            datasets: [{
                data: [
                    <?php echo $userStats['super_admin_count']; ?>,
                    <?php echo $userStats['admin_count']; ?>,
                    <?php echo $userStats['dispatch_count']; ?>,
                    <?php echo $userStats['finance_count']; ?>,
                    <?php echo $userStats['driver_count']; ?>,
                    <?php echo $userStats['customer_count']; ?>
                ],
                backgroundColor: [
                    '#dc3545', // Red
                    '#fd7e14', // Orange
                    '#ffc107', // Yellow
                    '#20c997', // Teal
                    '#0d6efd', // Blue
                    '#6f42c1'  // Purple
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Vehicle Status Chart
    var vehicleCtx = document.getElementById('vehicleStatusChart').getContext('2d');
    var vehicleChart = new Chart(vehicleCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Maintenance', 'Inactive'],
            datasets: [{
                data: [
                    <?php echo $vehicleStats['active_vehicles']; ?>,
                    <?php echo $vehicleStats['maintenance_vehicles']; ?>,
                    <?php echo $vehicleStats['inactive_vehicles']; ?>
                ],
                backgroundColor: [
                    '#28a745', // Green
                    '#ffc107', // Yellow
                    '#dc3545'  // Red
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
});
</script> 