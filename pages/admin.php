<?php
// Admin Dashboard Page
if (!hasRole(['super_admin', 'admin'])) {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    exit;
}

// Connect to databases
$conn1 = connectToCore1DB();
$conn2 = connectToCore2DB();

// Initialize statistics
$totalVehicles = 0;
$activeVehicles = 0;
$totalDrivers = 0;
$activeDrivers = 0;
$totalUsers = 0;
$pendingBookings = 0;
$todayBookings = 0;
$totalRevenue = 0;

// Get vehicle statistics from core1
if ($conn1) {
    // Total vehicles
    $query = "SELECT COUNT(*) as total FROM vehicles";
    $result = mysqli_query($conn1, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $totalVehicles = $row['total'];
    }
    
    // Active vehicles
    $query = "SELECT COUNT(*) as total FROM vehicles WHERE status = 'active'";
    $result = mysqli_query($conn1, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $activeVehicles = $row['total'];
    }
    
    // Total drivers
    $query = "SELECT COUNT(*) as total FROM drivers";
    $result = mysqli_query($conn1, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $totalDrivers = $row['total'];
    }
    
    // Active drivers
    $query = "SELECT COUNT(*) as total FROM drivers WHERE status = 'available' OR status = 'busy'";
    $result = mysqli_query($conn1, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $activeDrivers = $row['total'];
    }
    
    mysqli_close($conn1);
}

// Get user and booking statistics from core2
if ($conn2) {
    // Total users
    $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
    $result = mysqli_query($conn2, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $totalUsers = $row['total'];
    }
    
    // Pending bookings
    $query = "SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'";
    $result = mysqli_query($conn2, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $pendingBookings = $row['total'];
    }
    
    // Today's bookings
    $today = date('Y-m-d');
    $query = "SELECT COUNT(*) as total FROM bookings WHERE DATE(pickup_datetime) = '$today'";
    $result = mysqli_query($conn2, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $todayBookings = $row['total'];
    }
    
    // Total revenue
    $query = "SELECT SUM(amount) as total FROM payments WHERE status = 'completed'";
    $result = mysqli_query($conn2, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $totalRevenue = $row['total'] ?: 0;
    }
    
    // Get recent users
    $recentUsers = [];
    $query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
    $result = mysqli_query($conn2, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recentUsers[] = $row;
        }
    }
    
    // Get recent bookings
    $recentBookings = [];
    $query = "SELECT b.*, c.user_id, CONCAT(u.firstname, ' ', u.lastname) as customer_name 
              FROM bookings b 
              LEFT JOIN customers c ON b.customer_id = c.customer_id 
              LEFT JOIN users u ON c.user_id = u.user_id 
              ORDER BY b.created_at DESC LIMIT 5";
    $result = mysqli_query($conn2, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recentBookings[] = $row;
        }
    }
    
    mysqli_close($conn2);
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Admin Dashboard</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Admin Dashboard</li>
    </ol>
    
    <!-- Main Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Active Vehicles</div>
                            <div class="large"><?php echo $activeVehicles; ?> / <?php echo $totalVehicles; ?></div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-car"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=fleet">View Fleet Management</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Active Drivers</div>
                            <div class="large"><?php echo $activeDrivers; ?> / <?php echo $totalDrivers; ?></div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=drivers">View Driver Management</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Pending Bookings</div>
                            <div class="large"><?php echo $pendingBookings; ?></div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=booking">View Booking Management</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Total Revenue</div>
                            <div class="large">₱<?php echo number_format($totalRevenue, 2); ?></div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=payment">View Payment Management</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-secondary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Total Users</div>
                            <div class="large"><?php echo $totalUsers; ?></div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=users">View User Management</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Today's Bookings</div>
                            <div class="large"><?php echo $todayBookings; ?></div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=booking">View Booking Management</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-dark text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Vehicle Availability</div>
                            <div class="large"><?php echo $activeVehicles; ?> Active</div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-car-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=storeroom">View Vehicle Availability</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">System Settings</div>
                            <div class="large">Manage Settings</div>
                        </div>
                        <div class="fa-3x">
                            <i class="fas fa-cogs"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=system">View System Settings</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity Section -->
    <div class="row">
        <!-- Recent Users -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-plus me-1"></i>
                    Recently Added Users
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Date Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentUsers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No recent users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $user['role'] === 'super_admin' ? 'danger' : 
                                                        ($user['role'] === 'admin' ? 'primary' : 
                                                        ($user['role'] === 'driver' ? 'success' : 
                                                        ($user['role'] === 'customer' ? 'info' : 'secondary'))); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="index.php?page=users" class="btn btn-primary btn-sm">View All Users</a>
                </div>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-check me-1"></i>
                    Recent Bookings
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Pickup Location</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBookings)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No recent bookings found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBookings as $booking): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars(substr($booking['pickup_location'], 0, 30) . (strlen($booking['pickup_location']) > 30 ? '...' : '')); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $booking['status'] === 'completed' ? 'success' : 
                                                        ($booking['status'] === 'pending' ? 'warning' : 
                                                        ($booking['status'] === 'in_progress' ? 'info' : 
                                                        ($booking['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                                ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="index.php?page=booking" class="btn btn-primary btn-sm">View All Bookings</a>
                </div>
            </div>
        </div>
    </div>
</div> 