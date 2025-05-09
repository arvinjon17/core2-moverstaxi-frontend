<?php
// Check if user has permission to access this page
if (!hasPermission('access_dashboard')) {
    echo '<div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">Access Denied!</h4>
            <p>You do not have permission to access this page.</p>
          </div>';
    exit;
}

// Connect to databases
$core1DB = connectToCore1DB();
$core2DB = connectToCore2DB();

// Get fleet statistics
$fleetQuery = "SELECT 
                COUNT(*) as total_vehicles,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vehicles,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_vehicles,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_vehicles
              FROM vehicles";
$fleetResult = $core1DB->query($fleetQuery);
$fleetStats = $fleetResult->fetch_assoc();

// Get inventory statistics
$inventoryQuery = "SELECT 
                    COUNT(*) as total_items,
                    SUM(quantity) as total_quantity,
                    SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock_items
                   FROM inventory_items";
$inventoryResult = $core1DB->query($inventoryQuery);
$inventoryStats = $inventoryResult->fetch_assoc();

// Get fuel consumption statistics for the last 30 days
$fuelQuery = "SELECT 
                SUM(amount) as total_fuel_amount,
                AVG(amount) as avg_daily_consumption,
                COUNT(DISTINCT vehicle_id) as vehicles_fueled
              FROM fuel_logs
              WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$fuelResult = $core1DB->query($fuelQuery);
$fuelStats = $fuelResult->fetch_assoc();

// Get maintenance alerts
$maintenanceQuery = "SELECT v.plate_number, v.model, m.issue_description, m.scheduled_date, m.status
                     FROM maintenance_logs m
                     JOIN vehicles v ON m.vehicle_id = v.vehicle_id
                     WHERE m.status IN ('scheduled', 'in-progress')
                     ORDER BY m.scheduled_date ASC
                     LIMIT 5";
$maintenanceResult = $core1DB->query($maintenanceQuery);

// Get low stock inventory items
$lowStockQuery = "SELECT item_name, category, quantity, reorder_level, unit
                  FROM inventory_items
                  WHERE quantity <= reorder_level
                  ORDER BY (quantity/reorder_level) ASC
                  LIMIT 5";
$lowStockResult = $core1DB->query($lowStockQuery);

// Close database connections
$core1DB->close();
$core2DB->close();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Inventory Staff Dashboard</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>
    
    <!-- Fleet & Inventory Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Total Vehicles</div>
                            <div class="h3"><?php echo $fleetStats['total_vehicles']; ?></div>
                        </div>
                        <i class="fas fa-car fa-2x text-white-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">
                        <span class="me-2"><i class="fas fa-circle text-success"></i> Active: <?php echo $fleetStats['active_vehicles']; ?></span>
                        <span class="me-2"><i class="fas fa-circle text-warning"></i> Maintenance: <?php echo $fleetStats['maintenance_vehicles']; ?></span>
                        <span><i class="fas fa-circle text-danger"></i> Inactive: <?php echo $fleetStats['inactive_vehicles']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Inventory Items</div>
                            <div class="h3"><?php echo $inventoryStats['total_items']; ?></div>
                        </div>
                        <i class="fas fa-boxes fa-2x text-white-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">
                        <span class="me-2">Total Quantity: <?php echo $inventoryStats['total_quantity']; ?></span>
                        <span class="ms-2 text-warning"><i class="fas fa-exclamation-triangle"></i> Low Stock: <?php echo $inventoryStats['low_stock_items']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Fuel Usage (30 days)</div>
                            <div class="h3"><?php echo round($fuelStats['total_fuel_amount'], 2); ?> L</div>
                        </div>
                        <i class="fas fa-gas-pump fa-2x text-white-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">
                        <span>Avg. Daily: <?php echo round($fuelStats['avg_daily_consumption'], 2); ?> L</span>
                        <span class="ms-2">Vehicles: <?php echo $fuelStats['vehicles_fueled']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">Maintenance Alerts</div>
                            <div class="h3"><?php echo $maintenanceResult->num_rows; ?></div>
                        </div>
                        <i class="fas fa-wrench fa-2x text-white-50"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?page=fleet">View Details</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    Fleet Status Overview
                </div>
                <div class="card-body">
                    <canvas id="fleetStatusChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-line me-1"></i>
                    Fuel Consumption (Last 7 Days)
                </div>
                <div class="card-body">
                    <canvas id="fuelConsumptionChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Maintenance Alerts & Low Stock Tables -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-wrench me-1"></i>
                    Upcoming Maintenance
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Issue</th>
                                    <th>Scheduled Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($maintenanceResult->num_rows > 0) {
                                    while ($row = $maintenanceResult->fetch_assoc()) {
                                        $statusClass = ($row['status'] == 'in-progress') ? 'warning' : 'info';
                                        echo '<tr>';
                                        echo '<td>' . $row['plate_number'] . ' (' . $row['model'] . ')</td>';
                                        echo '<td>' . $row['issue_description'] . '</td>';
                                        echo '<td>' . date('M d, Y', strtotime($row['scheduled_date'])) . '</td>';
                                        echo '<td><span class="badge bg-' . $statusClass . '">' . $row['status'] . '</span></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center">No scheduled maintenance</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer small text-muted">
                    <a href="index.php?page=fleet" class="btn btn-sm btn-primary">View All Maintenance</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Low Stock Inventory
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Reorder Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($lowStockResult->num_rows > 0) {
                                    while ($row = $lowStockResult->fetch_assoc()) {
                                        $ratio = $row['quantity'] / $row['reorder_level'];
                                        $rowClass = ($ratio < 0.5) ? 'table-danger' : 'table-warning';
                                        
                                        echo '<tr class="' . $rowClass . '">';
                                        echo '<td>' . $row['item_name'] . '</td>';
                                        echo '<td>' . $row['category'] . '</td>';
                                        echo '<td>' . $row['quantity'] . ' ' . $row['unit'] . '</td>';
                                        echo '<td>' . $row['reorder_level'] . ' ' . $row['unit'] . '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center">No low stock items</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer small text-muted">
                    <a href="index.php?page=storeroom" class="btn btn-sm btn-primary">View Inventory</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fleet Status Chart
    var fleetCtx = document.getElementById("fleetStatusChart");
    var fleetStatusChart = new Chart(fleetCtx, {
        type: 'doughnut',
        data: {
            labels: ["Active", "Maintenance", "Inactive"],
            datasets: [{
                data: [
                    <?php echo $fleetStats['active_vehicles']; ?>,
                    <?php echo $fleetStats['maintenance_vehicles']; ?>,
                    <?php echo $fleetStats['inactive_vehicles']; ?>
                ],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(220, 53, 69, 0.8)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Sample data for fuel consumption (in a real app, this would come from the database)
    var lastWeekDates = [];
    var fuelData = [];
    
    // Generate last 7 days for the chart
    for (var i = 6; i >= 0; i--) {
        var d = new Date();
        d.setDate(d.getDate() - i);
        lastWeekDates.push(d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        // Random fuel consumption between 50 and 150 liters
        fuelData.push(Math.floor(Math.random() * 100) + 50);
    }
    
    // Fuel Consumption Chart
    var fuelCtx = document.getElementById("fuelConsumptionChart");
    var fuelConsumptionChart = new Chart(fuelCtx, {
        type: 'line',
        data: {
            labels: lastWeekDates,
            datasets: [{
                label: "Fuel Consumption (Liters)",
                data: fuelData,
                backgroundColor: "rgba(255, 193, 7, 0.2)",
                borderColor: "rgba(255, 193, 7, 1)",
                borderWidth: 2,
                pointBackgroundColor: "rgba(255, 193, 7, 1)",
                pointRadius: 4,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Liters'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });
});
</script> 