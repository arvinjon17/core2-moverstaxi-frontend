<?php
// Include auth functions
require_once 'functions/auth.php';
require_once 'functions/role_management.php';

// Get user information
$userRole = $_SESSION['user_role'] ?? '';
$rolePermissions = getRolePermissions($userRole);

// Get database connections
$conn1 = connectToCore1DB();
$conn2 = connectToCore2DB();

// Dashboard statistics
$stats = [
    'total_vehicles' => 0,
    'active_vehicles' => 0,
    'total_drivers' => 0,
    'available_drivers' => 0,
    'pending_bookings' => 0,
    'completed_bookings' => 0,
    'today_revenue' => 0,
    'month_revenue' => 0
];

// Get statistics from Core 1 database
if ($conn1) {
    // Total vehicles
    $query = "SELECT COUNT(*) as count FROM vehicles";
    $result = $conn1->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_vehicles'] = $row['count'];
    }
    
    // Active vehicles
    $query = "SELECT COUNT(*) as count FROM vehicles WHERE status = 'active'";
    $result = $conn1->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['active_vehicles'] = $row['count'];
    }
    
    // Total drivers
    $query = "SELECT COUNT(*) as count FROM drivers";
    $result = $conn1->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_drivers'] = $row['count'];
    }
    
    // Available drivers
    $query = "SELECT COUNT(*) as count FROM drivers WHERE status = 'available'";
    $result = $conn1->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['available_drivers'] = $row['count'];
    }
    
    $conn1->close();
}

// Get statistics from Core 2 database
if ($conn2) {
    // Pending bookings
    $query = "SELECT COUNT(*) as count FROM bookings WHERE booking_status IN ('pending', 'confirmed')";
    $result = $conn2->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['pending_bookings'] = $row['count'];
    }
    
    // Completed bookings
    $query = "SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'completed'";
    $result = $conn2->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['completed_bookings'] = $row['count'];
    }
    
    // Today's revenue
    $query = "SELECT SUM(amount) as total FROM payments 
              WHERE payment_status = 'completed' 
              AND DATE(payment_date) = CURDATE()";
    $result = $conn2->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['today_revenue'] = $row['total'] ?? 0;
    }
    
    // Monthly revenue
    $query = "SELECT SUM(amount) as total FROM payments 
              WHERE payment_status = 'completed' 
              AND MONTH(payment_date) = MONTH(CURDATE()) 
              AND YEAR(payment_date) = YEAR(CURDATE())";
    $result = $conn2->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['month_revenue'] = $row['total'] ?? 0;
    }
    
    $conn2->close();
}

// Format revenue numbers
$stats['today_revenue'] = number_format($stats['today_revenue'], 2);
$stats['month_revenue'] = number_format($stats['month_revenue'], 2);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
            <i class="fas fa-calendar-alt"></i> This Week
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Total Bookings</h5>
                        <h3 class="mb-0">254</h3>
                    </div>
                    <div>
                        <i class="fas fa-calendar-check fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Active Vehicles</h5>
                        <h3 class="mb-0">42</h3>
                    </div>
                    <div>
                        <i class="fas fa-car-side fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Active Drivers</h5>
                        <h3 class="mb-0">38</h3>
                    </div>
                    <div>
                        <i class="fas fa-user-check fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-dark">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Revenue</h5>
                        <h3 class="mb-0">₱154,750</h3>
                    </div>
                    <div>
                        <i class="fas fa-coins fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Access Modules -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-th me-2"></i> Quick Access</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4 col-sm-6">
                        <a href="index.php?page=fleet" class="text-decoration-none">
                            <div class="card text-center h-100 border-primary">
                                <div class="card-body">
                                    <i class="fas fa-taxi fa-4x text-primary mb-3"></i>
                                    <h5 class="card-title">Fleet Management</h5>
                                    <p class="card-text small text-muted">Manage all vehicles in the system</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="index.php?page=drivers" class="text-decoration-none">
                            <div class="card text-center h-100 border-info">
                                <div class="card-body">
                                    <i class="fas fa-id-card fa-4x text-info mb-3"></i>
                                    <h5 class="card-title">Driver Management</h5>
                                    <p class="card-text small text-muted">View and manage all drivers</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="index.php?page=bookings" class="text-decoration-none">
                            <div class="card text-center h-100 border-success">
                                <div class="card-body">
                                    <i class="fas fa-book fa-4x text-success mb-3"></i>
                                    <h5 class="card-title">Bookings</h5>
                                    <p class="card-text small text-muted">Manage all taxi bookings</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Activity</h5>
        <a href="#" class="text-decoration-none small">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">New vehicle added</h6>
                    <small>3 hours ago</small>
                </div>
                <p class="mb-1">Toyota Innova (ABC-123) has been added to the fleet</p>
                <small class="text-muted">by John Smith</small>
            </div>
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">Driver status updated</h6>
                    <small>5 hours ago</small>
                </div>
                <p class="mb-1">Mike Johnson status changed from 'on leave' to 'active'</p>
                <small class="text-muted">by Sarah Wilson</small>
            </div>
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">New booking created</h6>
                    <small>8 hours ago</small>
                </div>
                <p class="mb-1">New booking #8735 created for tomorrow at 9:00 AM</p>
                <small class="text-muted">by Reservation System</small>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Here you could add any JavaScript specific to the default dashboard
    console.log('Default Dashboard loaded');
});
</script> 