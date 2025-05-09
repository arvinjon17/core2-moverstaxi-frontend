<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Driver Management Page
// Include necessary files and check permissions
require_once 'functions/db.php';
require_once 'functions/auth.php';
require_once 'functions/role_management.php';
require_once 'functions/profile_images.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has permission
if (!isLoggedIn()) {
    $_SESSION['error'] = "You need to log in to access this page";
    header("Location: login.php");
    exit;
} else if (!hasPermission('manage_drivers')) {
    $_SESSION['error'] = "You don't have permission to access this page";
    header("Location: index.php?page=dashboard");
    exit;
}

// Get database connections
$conn = connectToCore1DB();
$core2Conn = connectToCore2DB();

// Initialize variables
$drivers = [];
$availableCount = 0;
$busyCount = 0;
$offlineCount = 0;
$errorMessage = '';
$messageType = '';

// Check if a success or error message was set in the session
if (isset($_SESSION['success_message'])) {
    $errorMessage = $_SESSION['success_message'];
    $messageType = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    $messageType = 'danger';
    unset($_SESSION['error_message']);
}

// Fetch drivers data
try {
    // First, get all users with 'driver' role from core2_movers
    $userQuery = "SELECT user_id, firstname, lastname, email, phone, profile_picture, status, last_login 
                 FROM users 
                 WHERE role = 'driver'
                 ORDER BY firstname, lastname";
    
    $userResult = $core2Conn->query($userQuery);
    
    if (!$userResult) {
        throw new Exception("Error fetching driver users: " . $core2Conn->error);
    }
    
    $userData = [];
    while ($user = $userResult->fetch_assoc()) {
        $userData[$user['user_id']] = $user;
    }
    
    if (empty($userData)) {
        $errorMessage = "No users with driver role found.";
        $messageType = 'warning';
    } else {
        // Get user IDs to fetch driver details from core1_movers
        $userIds = array_keys($userData);
        $userIdList = implode(',', $userIds);
        
        // Get driver details for these users
        $driverQuery = "SELECT d.*, v.vehicle_id, v.plate_number, v.model, v.year
                       FROM drivers d
                       LEFT JOIN vehicles v ON d.driver_id = v.assigned_driver_id
                       WHERE d.user_id IN ($userIdList)
                       ORDER BY d.status, d.driver_id";
        
        $driverResult = $conn->query($driverQuery);
        
        if (!$driverResult) {
            throw new Exception("Error fetching driver details: " . $conn->error);
        }
        
        // Combine data
        while ($driver = $driverResult->fetch_assoc()) {
            $userId = $driver['user_id'];
            
            // Combine with user data
            if (isset($userData[$userId])) {
                $combinedDriver = array_merge($userData[$userId], $driver);
                $drivers[] = $combinedDriver;
                
                // Count by status
                if ($driver['status'] === 'available') {
                    $availableCount++;
                } else if ($driver['status'] === 'busy') {
                    $busyCount++;
                } else if ($driver['status'] === 'offline') {
                    $offlineCount++;
                }
            }
        }
        
        // If no drivers found after joining
        if (empty($drivers) && !empty($userData)) {
            // Need to create driver records for these users
            $errorMessage = "Users with driver role exist but no corresponding driver records found in the drivers table.";
            $messageType = 'warning';
        }
    }
} catch (Exception $e) {
    $errorMessage = "Error: " . $e->getMessage();
    $messageType = 'danger';
}

// Check if the vehicle_assignment_history table exists
try {
    $checkVehicleHistoryTable = $conn->query("SHOW TABLES LIKE 'vehicle_assignment_history'");
    $vehicleHistoryTableExists = $checkVehicleHistoryTable && $checkVehicleHistoryTable->num_rows > 0;
    
    if (!$vehicleHistoryTableExists) {
        // Add a warning message that some functionality might not work fully
        if (empty($errorMessage)) {
            $errorMessage = "Warning: The vehicle assignment history tracking is not available. Some driver management features may be limited.";
            $messageType = 'warning';
        }
    }
} catch (Exception $e) {
    // Ignore any error here as it's not critical
}

// Fetch available vehicles for assignment
$vehicles = [];
try {
    $vehicleQuery = "SELECT vehicle_id, plate_number, model, year, assigned_driver_id, status 
                    FROM vehicles 
                    WHERE status='active' 
                    ORDER BY plate_number";
    $vehicleResult = $conn->query($vehicleQuery);
    
    if ($vehicleResult) {
        while ($row = $vehicleResult->fetch_assoc()) {
            $vehicles[] = $row;
        }
    } else {
        $errorMessage = "Error loading vehicles: " . $conn->error;
        $messageType = 'danger';
    }
} catch (Exception $e) {
    $errorMessage = "Error loading vehicles: " . $e->getMessage();
    $messageType = 'danger';
}

// Close database connections
if ($conn) $conn->close();
if ($core2Conn) $core2Conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Management - CORE Movers</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <main class="content">
        <div class="container-fluid p-0">
            <h1 class="h3 mb-3">Driver Management</h1>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <strong><?= $messageType === 'success' ? 'Success!' : ($messageType === 'warning' ? 'Warning!' : 'Error!') ?></strong> <?= $errorMessage ?>
                    
                    <?php if ($messageType === 'warning' && isset($vehicleHistoryTableExists) && !$vehicleHistoryTableExists): ?>
                        <div class="mt-2">
                            <a href="db_fixes/create_vehicle_history_table.php" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-wrench me-1"></i> Fix Vehicle Assignment History
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="container-fluid px-4">
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Driver Management</li>
                </ol>
                
                <!-- Driver Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-success text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="mb-0">Available Drivers</h5>
                                        <h3 class="mb-0"><?= $availableCount ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-user-check fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-warning text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="mb-0">Busy Drivers</h5>
                                        <h3 class="mb-0"><?= $busyCount ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-user-clock fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-danger text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="mb-0">Offline Drivers</h5>
                                        <h3 class="mb-0"><?= $offlineCount ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-user-slash fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-primary text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="mb-0">Total Drivers</h5>
                                        <h3 class="mb-0"><?= count($drivers) ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-users fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add Driver Button -->
                <div class="mb-4">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                        <i class="fas fa-plus me-1"></i> Add New Driver
                    </button>
                </div>
                
                <!-- Drivers Table -->
                <div class="card mb-4">
                    <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-id-card me-1"></i>
                        Driver Directory</h5>
                    </div>
                    <div class="card-body">
                        <table id="driversTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>License Number</th>
                                    <th>Contact</th>
                                    <th>Experience</th>
                                    <th>Assigned Vehicle</th>
                                    <th>Online Status</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($drivers)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No drivers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($drivers as $driver): ?>
                                    <tr class="<?= strtolower($driver['status']) === 'inactive' ? 'table-secondary text-muted' : '' ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php echo getProfileImageHtml($driver, $driver, 40, 'me-2'); ?>
                                                <div>
                                                    <div class="fw-bold"><?= $driver['firstname'] ?> <?= $driver['lastname'] ?></div>
                                                    <small><?= $driver['email'] ?></small>
                                                    <?php if (strtolower($driver['status']) === 'inactive'): ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= $driver['license_number'] ?><br>
                                            <small>Expires: <?= date('M d, Y', strtotime($driver['license_expiry'])) ?></small>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-phone me-1"></i> <?= $driver['phone'] ?></div>
                                            <div><i class="fas fa-envelope me-1"></i> <?= $driver['email'] ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $experienceYears = null;
                                            if (!empty($driver['created_at'])) {
                                                $start = new DateTime($driver['created_at']);
                                                $now = new DateTime();
                                                $interval = $start->diff($now);
                                                $experienceYears = $interval->y;
                                                $experienceMonths = $interval->m;
                                                
                                                if ($experienceYears > 0) {
                                                    echo $experienceYears . ' year' . ($experienceYears > 1 ? 's' : '');
                                                    if ($experienceMonths > 0) {
                                                        echo ', ' . $experienceMonths . ' month' . ($experienceMonths > 1 ? 's' : '');
                                                    }
                                                } else {
                                                    echo $experienceMonths . ' month' . ($experienceMonths > 1 ? 's' : '');
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($driver['vehicle_id'])): ?>
                                                <div><?= $driver['plate_number'] ?></div>
                                                <small><?= $driver['model'] ?> (<?= $driver['year'] ?>)</small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Vehicle</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (strtolower($driver['status']) === 'inactive'): ?>
                                                <span class="badge bg-secondary text-light">Inactive</span>
                                            <?php else: ?>
                                                <span class="badge bg-<?= 
                                                strtolower($driver['status']) === 'available' ? 'success' : 
                                                (strtolower($driver['status']) === 'busy' ? 'warning' : 'danger') ?> text-light">
                                                    <?= ucfirst($driver['status']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $rating = !empty($driver['rating']) ? floatval($driver['rating']) : 0;
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                } elseif ($i - 0.5 <= $rating) {
                                                    echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-warning"></i>';
                                                }
                                            }
                                            ?>
                                            <small class="d-block"><?= number_format($rating, 1) ?>/5.0</small>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column flex-md-row gap-1">
                                                <button type="button" class="btn btn-sm btn-info view-driver" 
                                                    data-bs-toggle="modal" data-bs-target="#viewDriverModal" 
                                                    data-id="<?= $driver['driver_id'] ?>"
                                                    data-name="<?= $driver['firstname'] ?> <?= $driver['lastname'] ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                
                                                <?php if (strtolower($driver['status']) !== 'inactive'): ?>
                                                    <button type="button" class="btn btn-sm btn-primary edit-driver" 
                                                        data-bs-toggle="modal" data-bs-target="#editDriverModal" 
                                                        data-id="<?= $driver['driver_id'] ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-driver" 
                                                        data-id="<?= $driver['driver_id'] ?>" 
                                                        data-name="<?= $driver['firstname'] ?> <?= $driver['lastname'] ?>">
                                                        <i class="fas fa-user-slash"></i> Deactivate
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success activate-driver"
                                                        data-id="<?= $driver['driver_id'] ?>" 
                                                        data-name="<?= $driver['firstname'] ?> <?= $driver['lastname'] ?>">
                                                        <i class="fas fa-user-check"></i> Activate
                                                    </button>
                                                <?php endif; ?>
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
        </div>
    </main>

    <!-- View Driver Modal -->
    <div class="modal fade" id="viewDriverModal" tabindex="-1" aria-labelledby="viewDriverModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="viewDriverModalLabel">Driver Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="driverDetails">
                        <!-- Driver details will be loaded here via AJAX with skeleton UI -->
                        <div class="text-center p-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading driver information...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editFromViewBtn">Edit Driver</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Driver Modal -->
    <div class="modal fade" id="addDriverModal" tabindex="-1" aria-labelledby="addDriverModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDriverModalLabel">Add New Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDriverForm" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col">
                                <label for="add_firstname" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="add_firstname" name="firstname" required>
                            </div>
                            <div class="col">
                                <label for="add_lastname" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="add_lastname" name="lastname" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="add_email" name="email" required>
                            <div class="form-text">This will be used as the driver's login</div>
                        </div>

                        <div class="mb-3">
                            <label for="add_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="add_phone" name="phone" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                <label for="add_license_number" class="form-label">License Number</label>
                                <input type="text" class="form-control" id="add_license_number" name="license_number" required>
                            </div>
                            <div class="col">
                                <label for="add_license_expiry" class="form-label">License Expiry</label>
                                <input type="date" class="form-control" id="add_license_expiry" name="license_expiry" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="add_status" class="form-label">Driver Status</label>
                            <select class="form-select" id="add_status" name="status" required>
                                <option value="available">Available</option>
                                <option value="busy">Busy</option>
                                <option value="offline" selected>Offline</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="add_vehicle_id" class="form-label">Assign Vehicle (Optional)</label>
                            <select class="form-select" id="add_vehicle_id" name="vehicle_id">
                                <option value="">-- Select Vehicle --</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?= $vehicle['vehicle_id'] ?>" <?= $vehicle['assigned_driver_id'] ? 'disabled' : '' ?>>
                                        <?= $vehicle['plate_number'] ?> - <?= $vehicle['model'] ?> <?= $vehicle['year'] ?>
                                        <?= $vehicle['assigned_driver_id'] ? ' (Already Assigned)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="add_profile_image" class="form-label">Profile Image (Optional)</label>
                            <input type="file" class="form-control" id="add_profile_image" name="profile_image" accept="image/*">
                            <div class="form-text">Maximum file size: 2MB. Recommended dimensions: 300x300 pixels.</div>
                            <div id="add_image_preview" class="mt-2"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveNewDriverBtn">Add Driver</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Driver Modal -->
    <div class="modal fade" id="editDriverModal" tabindex="-1" aria-labelledby="editDriverModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDriverModalLabel">Edit Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Edit form -->
                    <form id="editDriverForm" enctype="multipart/form-data">
                        <input type="hidden" id="edit_driver_id" name="driver_id">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        
                        <div class="text-center mb-3">
                            <div id="edit_profile_preview" class="rounded-circle mx-auto mb-2" style="width: 100px; height: 100px; background-size: cover; background-position: center;"></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label for="edit_firstname" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_firstname" name="firstname" required>
                            </div>
                            <div class="col">
                                <label for="edit_lastname" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_lastname" name="lastname" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                <label for="edit_license_number" class="form-label">License Number</label>
                                <input type="text" class="form-control" id="edit_license_number" name="license_number" required>
                            </div>
                            <div class="col">
                                <label for="edit_license_expiry" class="form-label">License Expiry</label>
                                <input type="date" class="form-control" id="edit_license_expiry" name="license_expiry" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                <label for="edit_status" class="form-label">Driver Status</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="available">Available</option>
                                    <option value="busy">Busy</option>
                                    <option value="offline">Offline</option>
                                </select>
                            </div>
                            <div class="col">
                                <label for="edit_rating" class="form-label">Rating</label>
                                <input type="number" class="form-control" id="edit_rating" name="rating" min="0" max="5" step="0.1" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_vehicle_id" class="form-label">Assign Vehicle</label>
                            <select class="form-select" id="edit_vehicle_id" name="vehicle_id">
                                <option value="">-- No Vehicle --</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?= $vehicle['vehicle_id'] ?>" <?= $vehicle['assigned_driver_id'] ? 'disabled' : '' ?>>
                                        <?= $vehicle['plate_number'] ?> - <?= $vehicle['model'] ?> <?= $vehicle['year'] ?>
                                        <?= $vehicle['assigned_driver_id'] ? ' (Already Assigned)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_profile_image" class="form-label">Update Profile Image (Optional)</label>
                            <input type="file" class="form-control" id="edit_profile_image" name="profile_image" accept="image/*">
                            <div class="form-text">Leave blank to keep current image. Maximum file size: 2MB.</div>
                            <div id="edit_image_preview" class="mt-2"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateDriverBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS (must be after jQuery) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- SweetAlert2 for better alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom scripts -->
    <script src="assets/js/drivers.js"></script>
</body>
</html>