<?php
// Fleet management page
require_once 'functions/auth.php';
require_once 'functions/role_management.php';

// Verify user has permission
if (!hasPermission('access_fleet')) {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    exit;
}

// Add error logging for debugging
error_log("Loading fleet.php page");

// Get database connection
$conn = connectToCore1DB();

// Initialize variables
$vehicles = [];
$dbError = null;

// Fetch vehicles from database
if ($conn) {
    try {
        error_log("Fleet page: Connected to database successfully");
        
        // Check if vehicles table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'vehicles'");
        if ($tableCheck->num_rows === 0) {
            throw new Exception("Vehicles table does not exist in the database");
        }
        
        // Modified query to avoid cross-database access
        // This query doesn't join with core1_movers2.users directly
        $query = "SELECT v.*, d.license_number 
                  FROM vehicles v 
                  LEFT JOIN drivers d ON v.assigned_driver_id = d.driver_id
                  ORDER BY v.status, v.model";
        
        error_log("Fleet page executing modified query: " . $query);
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Query error: " . $conn->error);
        }
        
        error_log("Query executed successfully, fetching results");
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // If we have an assigned driver, fetch their name from drivers table instead
                if (!empty($row['assigned_driver_id'])) {
                    // Try to get driver name from the drivers table which should include this information
                    $driverQuery = "SELECT firstname, lastname FROM drivers WHERE driver_id = " . intval($row['assigned_driver_id']);
                    $driverResult = $conn->query($driverQuery);
                    
                    if ($driverResult && $driverResult->num_rows > 0) {
                        $driverData = $driverResult->fetch_assoc();
                        $row['driver_name'] = $driverData['firstname'] . ' ' . $driverData['lastname'];
                    } else {
                        $row['driver_name'] = 'Driver #' . $row['assigned_driver_id'];
                    }
                }
                
                $vehicles[] = $row;
            }
            error_log("Successfully fetched " . count($vehicles) . " vehicles");
        } else {
            error_log("No vehicles found in the database");
        }
    } catch (Exception $e) {
        error_log("Error in fleet.php: " . $e->getMessage());
        $dbError = $e->getMessage();
    } finally {
        $conn->close();
    }
} else {
    error_log("Fleet page: Failed to connect to database");
    $dbError = "Failed to connect to database";
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Fleet Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAddVehicle">
                <i class="fas fa-plus"></i> Add Vehicle
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-file-export"></i> Export
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
            <i class="fas fa-filter"></i> Filter
        </button>
    </div>
</div>

<!-- Vehicle Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Active Vehicles</h5>
                        <h3 class="mb-0"><?php echo count(array_filter($vehicles, function($v) { return $v['status'] === 'active'; })); ?></h3>
                    </div>
                    <div>
                        <i class="fas fa-car-side fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Maintenance</h5>
                        <h3 class="mb-0"><?php echo count(array_filter($vehicles, function($v) { return $v['status'] === 'maintenance'; })); ?></h3>
                    </div>
                    <div>
                        <i class="fas fa-tools fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Inactive</h5>
                        <h3 class="mb-0"><?php echo count(array_filter($vehicles, function($v) { return $v['status'] === 'inactive'; })); ?></h3>
                    </div>
                    <div>
                        <i class="fas fa-car-crash fa-3x opacity-50"></i>
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
                        <h5 class="mb-0">Total Fleet</h5>
                        <h3 class="mb-0"><?php echo count($vehicles); ?></h3>
                    </div>
                    <div>
                        <i class="fas fa-taxi fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vehicles Table -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-taxi me-2"></i> Vehicle Inventory</h5>
    </div>
    <div class="card-body">
        <?php if ($dbError): ?>
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Database Error</h5>
            <p><?php echo htmlspecialchars($dbError); ?></p>
            <button class="btn btn-outline-danger btn-sm mt-2" onclick="window.location.reload();">
                <i class="fas fa-sync-alt me-1"></i> Retry
            </button>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Plate Number</th>
                        <th>Model</th>
                        <th>Year</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Assigned Driver</th>
                        <th>Last Maintenance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No vehicles found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($vehicle['vehicle_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($vehicle['vehicle_image']); ?>" alt="Vehicle" width="50" height="50" class="rounded">
                                    <?php else: ?>
                                        <i class="fas fa-car fa-2x text-secondary"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($vehicle['plate_number']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['year']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['capacity']); ?></td>
                                <td>
                                    <?php if ($vehicle['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($vehicle['status'] === 'maintenance'): ?>
                                        <span class="badge bg-warning text-dark">Maintenance</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($vehicle['driver_name'])): ?>
                                        <?php echo htmlspecialchars($vehicle['driver_name']); ?> (<?php echo htmlspecialchars($vehicle['license_number']); ?>)
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($vehicle['last_maintenance'])): ?>
                                        <?php echo date('M d, Y', strtotime($vehicle['last_maintenance'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not recorded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column flex-md-row gap-1">
                                        <button type="button" class="btn btn-sm btn-info view-vehicle" 
                                            data-id="<?php echo $vehicle['vehicle_id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary edit-vehicle" 
                                            data-id="<?php echo $vehicle['vehicle_id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-vehicle" 
                                            data-id="<?php echo $vehicle['vehicle_id']; ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit Vehicle -->
<div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vehicleModalTitle">Add New Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="vehicleForm">
                    <div class="mb-3">
                        <label for="plate_number" class="form-label">Plate Number</label>
                        <input type="text" class="form-control" id="plate_number" name="plate_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="vin" class="form-label">VIN</label>
                        <input type="text" class="form-control" id="vin" name="vin" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model" required>
                        </div>
                        <div class="col">
                            <label for="year" class="form-label">Year</label>
                            <input type="number" class="form-control" id="year" name="year" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label for="capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" required>
                        </div>
                        <div class="col">
                            <label for="fuel_type" class="form-label">Fuel Type</label>
                            <select class="form-select" id="fuel_type" name="fuel_type" required>
                                <option value="Gasoline">Gasoline</option>
                                <option value="Diesel">Diesel</option>
                                <option value="Electric">Electric</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required data-critical="true">
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <div class="form-text">Current status of the vehicle</div>
                    </div>
                    <div class="mb-3">
                        <label for="assigned_driver_id" class="form-label">Assigned Driver</label>
                        <select class="form-select" id="assigned_driver_id" name="assigned_driver_id">
                            <option value="">None</option>
                            <!-- This would be populated from database in a real implementation -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="vehicle_image" class="form-label">Vehicle Image</label>
                        <input type="file" class="form-control" id="vehicle_image" name="vehicle_image">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveVehicle">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Viewing Vehicle Details -->
<div class="modal fade" id="viewVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="viewVehicleDetailTitle">Vehicle Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="vehicleModalBody">
                <div class="row mb-4">
                    <div class="col-md-4 text-center" id="vehicleImageContainer">
                        ${vehicle.vehicle_image ? 
                            `<img src="${vehicle.vehicle_image}" alt="Vehicle Image" class="img-fluid rounded mb-3" style="max-height: 200px; width: auto; object-fit: contain;">` :
                            `<div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px;"><i class="fas fa-car fa-4x text-secondary"></i></div>`
                        }
                    </div>
                    <div class="col-md-8">
                        <h4 id="vehicleDetailTitle"></h4>
                        <div class="mb-3" id="vehicleStatusBadge"></div>
                        <p class="text-muted" id="vehicleDetailVIN"></p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">Plate Number:</th>
                                        <td id="vehicleDetailPlate"></td>
                                    </tr>
                                    <tr>
                                        <th>Year:</th>
                                        <td id="vehicleDetailYear"></td>
                                    </tr>
                                    <tr>
                                        <th>Capacity:</th>
                                        <td id="vehicleDetailCapacity"></td>
                                    </tr>
                                    <tr>
                                        <th>Fuel Type:</th>
                                        <td id="vehicleDetailFuelType"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Assignment Details</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">Assigned Driver:</th>
                                        <td id="vehicleDetailDriver"></td>
                                    </tr>
                                    <tr>
                                        <th>License Number:</th>
                                        <td id="vehicleDetailLicense"></td>
                                    </tr>
                                    <tr>
                                        <th>Assignment Date:</th>
                                        <td id="vehicleDetailAssignDate"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Maintenance Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">Last Maintenance:</th>
                                        <td id="vehicleDetailLastMaintenance"></td>
                                    </tr>
                                    <tr>
                                        <th>Next Due:</th>
                                        <td id="vehicleDetailNextMaintenance"></td>
                                    </tr>
                                </table>
                                <div id="maintenanceRecordContainer">
                                    <h6 class="mt-3">Recent Maintenance Records</h6>
                                    <div id="recentMaintenanceRecords" class="mt-2">
                                        <div class="text-center py-3 text-muted" id="noMaintenanceRecords">
                                            <i class="fas fa-clipboard fa-2x mb-2"></i><br>
                                            No maintenance records found
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-gas-pump me-2"></i>Performance Metrics</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="40%">Total Distance:</th>
                                        <td id="vehicleDetailDistance"></td>
                                    </tr>
                                    <tr>
                                        <th>Fuel Consumed:</th>
                                        <td id="vehicleDetailFuelConsumed"></td>
                                    </tr>
                                    <tr>
                                        <th>Avg. Consumption:</th>
                                        <td id="vehicleDetailAvgConsumption"></td>
                                    </tr>
                                    <tr>
                                        <th>Total Bookings:</th>
                                        <td id="vehicleDetailTotalBookings"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editVehicleBtn">Edit Vehicle</button>
            </div>
        </div>
    </div>
</div>

<!-- Ajax handler for Vehicle View -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add Vehicle button event
    document.getElementById('btnAddVehicle').addEventListener('click', function() {
        document.getElementById('vehicleForm').reset();
        document.getElementById('vehicleModalTitle').textContent = 'Add New Vehicle';
        const saveBtn = document.getElementById('saveVehicle');
        saveBtn.dataset.mode = 'add';
        saveBtn.dataset.id = '';
        
        // Remove any previous image notes
        const existingNotes = document.querySelectorAll('#vehicleForm .alert');
        existingNotes.forEach(note => note.remove());
        
        // Load available drivers
        fetchDrivers();
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('vehicleModal'));
        modal.show();
    });
    
    // Save Vehicle button event
    document.getElementById('saveVehicle').addEventListener('click', function() {
        const mode = this.dataset.mode;
        const vehicleId = this.dataset.id;
        
        // Get form data
        const form = document.getElementById('vehicleForm');
        const formData = new FormData(form);
        
        // Get status value directly from the dropdown
        const statusField = document.getElementById('status');
        const statusValue = statusField.value;
        
        console.log("Status value from dropdown:", statusValue);
        
        // Validate status field
        if (!statusValue || statusValue === '') {
            console.error("Status field is empty or invalid:", statusValue);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please select a valid status for the vehicle'
            });
            return; // Stop form submission
        }
        
        // Create a new FormData to ensure all fields are included
        const newFormData = new FormData();
        
        // Add all form fields to the new FormData
        for (const [key, value] of formData.entries()) {
            if (key !== 'status') { // Skip status for now
                newFormData.append(key, value);
            }
        }
        
        // Add the status field explicitly to ensure it's set correctly
        newFormData.append('status', statusValue);
        
        // Add action and vehicle_id (if editing)
        newFormData.append('action', mode === 'edit' ? 'update' : 'add');
        if (mode === 'edit') {
            newFormData.append('vehicle_id', vehicleId);
        }
        
        // Log all form data for debugging
        console.log("Form data being submitted:");
        for (const pair of newFormData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Show loading state
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        
        // Send the request directly to the API
        fetch('api/save_vehicle.php', {
            method: 'POST',
            body: newFormData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message with SweetAlert2
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: mode === 'edit' ? 'Vehicle updated successfully!' : 'Vehicle added successfully!',
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Reload the page to reflect changes
                    window.location.reload();
                });
            } else {
                // Enhanced error handling with SweetAlert2
                console.error("API Error:", data.message);
                
                // Show detailed error information if available
                const errorMessage = data.details 
                    ? `${data.message}<br><small class="text-muted mt-2">${data.details}</small>` 
                    : data.message;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: errorMessage,
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            console.error('Error saving vehicle:', error);
            
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Failed to save vehicle. Please try again. Error: ' + error.message,
                confirmButtonText: 'OK'
            });
        })
        .finally(() => {
            // Reset button state
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = 'Save';
            }, 1000);
        });
    });
    
    // View Vehicle details event
    const viewButtons = document.querySelectorAll('.view-vehicle');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const vehicleId = this.getAttribute('data-id');
            viewVehicleDetails(vehicleId);
        });
    });
    
    // Edit Vehicle event
    const editButtons = document.querySelectorAll('.edit-vehicle');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const vehicleId = this.getAttribute('data-id');
            loadVehicleForEdit(vehicleId);
        });
    });
    
    // Delete Vehicle event
    const deleteButtons = document.querySelectorAll('.delete-vehicle');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const vehicleId = this.getAttribute('data-id');
            deleteVehicle(vehicleId);
        });
    });
    
    // Edit button in view modal
    document.getElementById('editVehicleBtn').addEventListener('click', function() {
        const vehicleId = this.getAttribute('data-id');
        
        // Close view modal
        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewVehicleModal'));
        viewModal.hide();
        
        // Open edit modal with the same vehicle ID
        loadVehicleForEdit(vehicleId);
    });
    
    // Function to view vehicle details
    function viewVehicleDetails(vehicleId) {
        // Show loading state in the modal
        const modal = new bootstrap.Modal(document.getElementById('viewVehicleModal'));
        modal.show();
        
        document.getElementById('viewVehicleDetailTitle').textContent = 'Loading...';
        
        // Show loading spinner
        document.getElementById('vehicleModalBody').innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading vehicle information...</p>
            </div>
        `;
        
        // AJAX request to get vehicle details
        fetch('api/get_vehicle_details.php?id=' + vehicleId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                // Check if the response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, get the text and throw an error
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Received HTML instead of JSON. Please check server logs.');
                    });
                }
                
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Populate vehicle details in the modal
                    populateVehicleDetails(data.vehicle, data.maintenance_records, data.performance);
                    
                    // Store vehicle ID in edit button for later use
                    document.getElementById('editVehicleBtn').setAttribute('data-id', vehicleId);
                } else {
                    // Show error message
                    document.getElementById('vehicleModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <h5>Error Loading Vehicle Details</h5>
                            <p>${data.message || 'An error occurred while loading vehicle details.'}</p>
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-primary" onclick="viewVehicleDetails(${vehicleId})">
                                    <i class="fas fa-sync-alt me-1"></i> Retry
                                </button>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching vehicle details:', error);
                
                // Show error message with retry button
                document.getElementById('vehicleModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Server Error</h5>
                        <p>Failed to load vehicle details. Please try again.</p>
                        <small class="d-block mb-3">${error.message}</small>
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary" onclick="viewVehicleDetails(${vehicleId})">
                                <i class="fas fa-sync-alt me-1"></i> Retry
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Close
                            </button>
                        </div>
                    </div>
                `;
            });
    }
    
    // Function to load vehicle data for editing
    function loadVehicleForEdit(vehicleId) {
        // Reset the form to clear previous data
        document.getElementById('vehicleForm').reset();
        document.getElementById('vehicleModalTitle').textContent = 'Edit Vehicle';
        
        // Set save button data attributes
        const saveBtn = document.getElementById('saveVehicle');
        saveBtn.dataset.mode = 'edit';
        saveBtn.dataset.id = vehicleId;
        
        console.log("Loading vehicle for edit, ID:", vehicleId);
        
        // Disable form fields during loading
        const formFields = document.querySelectorAll('#vehicleForm input, #vehicleForm select');
        formFields.forEach(field => {
            field.disabled = true;
        });
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('vehicleModal'));
        modal.show();
        
        // Fetch drivers for the dropdown
        fetchDrivers(vehicleId);
        
        // Clear any existing image notes
        const existingNotes = document.querySelectorAll('#vehicleForm .alert');
        existingNotes.forEach(note => note.remove());
        
        // Add loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'alert alert-info';
        loadingIndicator.id = 'loadingIndicator';
        loadingIndicator.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span>Loading vehicle data...</span>
            </div>
        `;
        document.getElementById('vehicleForm').prepend(loadingIndicator);
        
        // Fetch vehicle data
        fetch('api/get_vehicle.php?id=' + vehicleId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                // Check if the response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, get the text and throw an error
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Received HTML instead of JSON. Please check server logs.');
                    });
                }
                
                return response.json();
            })
            .then(data => {
                // Remove loading indicator
                const loadingIndicator = document.getElementById('loadingIndicator');
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
                
                if (data.success) {
                    const vehicle = data.vehicle;
                    
                    // Debug: Log entire vehicle data object
                    console.log("Vehicle data received:", vehicle);
                    
                    // Set form values
                    document.getElementById('plate_number').value = vehicle.plate_number || '';
                    document.getElementById('vin').value = vehicle.vin || '';
                    document.getElementById('model').value = vehicle.model || '';
                    document.getElementById('year').value = vehicle.year || '';
                    document.getElementById('capacity').value = vehicle.capacity || '';
                    document.getElementById('fuel_type').value = vehicle.fuel_type || 'Gasoline';
                    
                    // Get status dropdown
                    const statusDropdown = document.getElementById('status');
                    if (statusDropdown) {
                        // Get status value from API response, default to 'active' if not available
                        const statusValue = (vehicle.status || 'active').toLowerCase().trim();
                        console.log("Setting status dropdown to:", statusValue);
                        
                        // Update the dropdown value
                        for (let i = 0; i < statusDropdown.options.length; i++) {
                            if (statusDropdown.options[i].value === statusValue) {
                                statusDropdown.selectedIndex = i;
                                break;
                            }
                        }
                        
                        // Verify the value was set correctly
                        console.log("Status dropdown value after setting:", statusDropdown.value);
                    }
                    
                    // Set assigned driver if available
                    if (vehicle.assigned_driver_id) {
                        setTimeout(() => {
                            // Small delay to ensure the drivers dropdown has been populated
                            const driverDropdown = document.getElementById('assigned_driver_id');
                            if (driverDropdown && vehicle.assigned_driver_id) {
                                driverDropdown.value = vehicle.assigned_driver_id;
                            }
                        }, 500);
                    }
                    
                    // Enable form fields
                    formFields.forEach(field => {
                        field.disabled = false;
                    });
                    
                    // If there's an image, add notes about it
                    if (vehicle.vehicle_image) {
                        const imageNote = document.createElement('div');
                        imageNote.className = 'alert alert-info mt-2';
                        imageNote.innerHTML = `
                            <small>Current image: ${vehicle.vehicle_image.split('/').pop()}</small>
                            <div class="mt-2">
                                <img src="${vehicle.vehicle_image}" alt="Current Vehicle Image" style="max-height: 100px; max-width: 100%;" class="img-thumbnail">
                            </div>
                        `;
                        document.getElementById('vehicle_image').after(imageNote);
                    }
                } else {
                    // Show error message
                    console.error("API Error:", data.message);
                    
                    // Show error in the form
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger';
                    errorAlert.innerHTML = `
                        <h5>Error Loading Vehicle</h5>
                        <p>${data.message || 'An error occurred while loading vehicle data.'}</p>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="loadVehicleForEdit(${vehicleId})">
                                <i class="fas fa-sync-alt me-1"></i> Retry
                            </button>
                        </div>
                    `;
                    document.getElementById('vehicleForm').innerHTML = '';
                    document.getElementById('vehicleForm').appendChild(errorAlert);
                }
            })
            .catch(error => {
                console.error('Error fetching vehicle data:', error);
                
                // Remove loading indicator
                const loadingIndicator = document.getElementById('loadingIndicator');
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
                
                // Show error in a SweetAlert
                Swal.fire({
                    icon: 'error',
                    title: 'Error Loading Vehicle',
                    html: `Failed to load vehicle data for editing.<br><small class="text-muted">${error.message}</small>`,
                    confirmButtonText: 'Close',
                    showCancelButton: true,
                    cancelButtonText: 'Retry',
                }).then((result) => {
                    if (result.dismiss === Swal.DismissReason.cancel) {
                        // Retry loading
                        loadVehicleForEdit(vehicleId);
                    } else {
                        // Close the modal
                        bootstrap.Modal.getInstance(document.getElementById('vehicleModal')).hide();
                    }
                });
            });
    }
    
    // Function to fetch drivers for the dropdown
    function fetchDrivers(vehicleId = 0) {
        const driverSelect = document.getElementById('assigned_driver_id');
        driverSelect.innerHTML = '<option value="">Loading drivers...</option>';
        
        fetch('api/get_drivers.php?vehicle_id=' + vehicleId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                // Check if the response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, get the text and throw an error
                    return response.text().then(text => {
                        throw new Error('Received HTML instead of JSON: ' + text.substring(0, 150) + '...');
                    });
                }
                
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Start with an empty option
                    driverSelect.innerHTML = '<option value="">-- Not Assigned --</option>';
                    
                    // Add drivers to the dropdown
                    data.drivers.forEach(driver => {
                        const option = document.createElement('option');
                        option.value = driver.driver_id;
                        option.textContent = `${driver.firstname} ${driver.lastname} (${driver.license_number})`;
                        
                        // If the driver is already assigned to another vehicle, indicate it
                        if (driver.current_vehicle_id && driver.current_vehicle_id != vehicleId) {
                            option.textContent += ` - Currently assigned to ${driver.current_vehicle}`;
                            option.classList.add('text-warning');
                        }
                        
                        driverSelect.appendChild(option);
                    });
                } else {
                    driverSelect.innerHTML = '<option value="">-- Error loading drivers --</option>';
                    console.error('Error loading drivers:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching drivers:', error);
                driverSelect.innerHTML = '<option value="">-- Error loading drivers --</option>';
            });
    }
    
    // Function to delete a vehicle
    function deleteVehicle(vehicleId) {
        // Use SweetAlert2 for confirmation
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this deletion!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading indicator
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait while we delete the vehicle',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // AJAX request to delete vehicle
                fetch('api/delete_vehicle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id=' + vehicleId
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Vehicle has been deleted successfully.',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Reload the page to update the vehicle list
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to delete vehicle',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error deleting vehicle:', error);
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to delete vehicle. Please try again. Error: ' + error.message,
                        confirmButtonText: 'OK'
                    });
                });
            }
        });
    }
    
    // Helper function to populate vehicle details in the view modal
    function populateVehicleDetails(vehicle, maintenanceRecords, performance) {
        // Update the modal title
        document.getElementById('viewVehicleDetailTitle').textContent = 'Vehicle Details';
        
        // Create the content HTML
        let html = `
            <div class="row mb-4">
                <div class="col-md-4 text-center" id="vehicleImageContainer">
                    ${vehicle.vehicle_image ? 
                        `<img src="${vehicle.vehicle_image}" alt="Vehicle Image" class="img-fluid rounded mb-3" style="max-height: 200px; width: auto; object-fit: contain;">` :
                        `<div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px;"><i class="fas fa-car fa-4x text-secondary"></i></div>`
                    }
                </div>
                <div class="col-md-8">
                    <h4>${vehicle.model} (${vehicle.year})</h4>
                    <div class="mb-3">
                        ${vehicle.status === 'active' ? 
                            '<span class="badge bg-success">Active</span>' :
                            vehicle.status === 'maintenance' ? 
                            '<span class="badge bg-warning text-dark">Maintenance</span>' :
                            '<span class="badge bg-danger">Inactive</span>'
                        }
                    </div>
                    <p class="text-muted">VIN: ${vehicle.vin}</p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">Plate Number:</th>
                                    <td>${vehicle.plate_number}</td>
                                </tr>
                                <tr>
                                    <th>Year:</th>
                                    <td>${vehicle.year}</td>
                                </tr>
                                <tr>
                                    <th>Capacity:</th>
                                    <td>${vehicle.capacity}</td>
                                </tr>
                                <tr>
                                    <th>Fuel Type:</th>
                                    <td>${vehicle.fuel_type}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Assignment Details</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">Assigned Driver:</th>
                                    <td>${vehicle.driver_name || 'Not assigned'}</td>
                                </tr>
                                <tr>
                                    <th>License Number:</th>
                                    <td>${vehicle.license_number || 'N/A'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Maintenance Information</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">Last Maintenance:</th>
                                    <td>${vehicle.last_maintenance ? new Date(vehicle.last_maintenance).toLocaleDateString() : 'Not recorded'}</td>
                                </tr>
                            </table>
                            <div>
                                <h6 class="mt-3">Recent Maintenance Records</h6>
                                <div class="mt-2">
                                    ${maintenanceRecords && maintenanceRecords.length > 0 ? 
                                        `<ul class="list-group">
                                            ${maintenanceRecords.map(record => `
                                                <li class="list-group-item p-2">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <strong>${record.service_type}</strong><br>
                                                            <small>${record.description}</small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-secondary">${new Date(record.service_date).toLocaleDateString()}</span><br>
                                                            <small>$${parseFloat(record.cost).toFixed(2)}</small>
                                                        </div>
                                                    </div>
                                                </li>
                                            `).join('')}
                                        </ul>` :
                                        `<div class="text-center py-3 text-muted">
                                            <i class="fas fa-clipboard fa-2x mb-2"></i><br>
                                            No maintenance records found
                                        </div>`
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-gas-pump me-2"></i>Performance Metrics</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">Total Distance:</th>
                                    <td>${performance && performance.total_distance ? performance.total_distance + ' km' : 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Fuel Consumed:</th>
                                    <td>${performance && performance.fuel_consumed ? performance.fuel_consumed + ' L' : 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Avg. Consumption:</th>
                                    <td>${performance && performance.fuel_consumed && performance.total_distance && performance.total_distance > 0 ? 
                                        (performance.fuel_consumed / performance.total_distance * 100).toFixed(2) + ' L/100km' : 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Total Bookings:</th>
                                    <td>${performance && performance.total_bookings ? performance.total_bookings : 'N/A'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Update the modal body
        document.getElementById('vehicleModalBody').innerHTML = html;
    }
});
</script> 

<!-- Add SweetAlert2 for better alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 