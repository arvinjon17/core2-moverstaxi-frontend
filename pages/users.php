<?php
// User Management Page
if (!hasPermission('access_users')) {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    exit;
}

// Connect to the database
$conn = connectToCore2DB();

// Include profile image utility functions
require_once 'functions/profile_images.php';

// Define available roles
$roles = ['super_admin', 'admin', 'finance', 'dispatch', 'driver', 'customer'];

// Handle form submissions
$message = '';
$messageType = '';

// Function to handle profile picture upload
function handleProfilePictureUpload($userId, $userData = null) {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] == UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded
    }
    
    // If we have userData, use it directly
    // Otherwise, fetch user data from database to get role information
    if (!$userData) {
        $conn = connectToCore2DB();
        $query = "SELECT firstname, lastname, role FROM users WHERE user_id = " . (int)$userId;
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $userData = mysqli_fetch_assoc($result);
        } else {
            // If can't get user data, still proceed with basic upload
            $userData = [
                'firstname' => 'user',
                'lastname' => $userId,
                'role' => 'user'
            ];
        }
        mysqli_close($conn);
    }
    
    // Use the standardized upload function with the user's role
    $profileImageResult = uploadProfileImage($_FILES['profile_picture'], $userData['role'], $userId, $userData);
    
    if ($profileImageResult['success']) {
        // Return the relative path to be stored in the database
        return $profileImageResult['filename'];
    }
    
    // Log the error
    error_log("Profile image upload failed: " . $profileImageResult['message']);
    return false;
}

// Note: getUserProfileImageUrl function is now provided by functions/profile_images.php

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Create new user
    if ($_POST['action'] === 'create') {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
        $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $password = $_POST['password'];
        
        // Check if email already exists
        $checkQuery = "SELECT user_id FROM users WHERE email = '$email'";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            $message = "Error: Email already exists.";
            $messageType = "danger";
        } else {
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert the new user
            $query = "INSERT INTO users (email, password, firstname, lastname, phone, role, status, created_at) 
                      VALUES ('$email', '$hashedPassword', '$firstname', '$lastname', '$phone', '$role', '$status', NOW())";
            
            if (mysqli_query($conn, $query)) {
                $userId = mysqli_insert_id($conn);
                
                // If the role is 'customer', also create a record in core1_movers.customers table
                if ($role === 'customer') {
                    // Connect to core1_movers database
                    $conn1 = connectToCore1DB();
                    if ($conn1) {
                        // First check if the customer record already exists
                        $checkQuery = "SELECT customer_id FROM customers WHERE user_id = $userId";
                        $checkResult = mysqli_query($conn1, $checkQuery);
                        
                        // Only insert if customer record doesn't exist
                        if (mysqli_num_rows($checkResult) === 0) {
                            // Create a new customer record with default values
                            $insertCustomerQuery = "INSERT INTO customers 
                                                  (user_id, status, created_at, updated_at) 
                                                  VALUES ($userId, 'offline', NOW(), NOW())";
                            
                            if (!mysqli_query($conn1, $insertCustomerQuery)) {
                                error_log("Error creating customer record: " . mysqli_error($conn1));
                            } else {
                                error_log("Created customer record for user ID: $userId");
                            }
                        } else {
                            error_log("Customer record already exists for user ID: $userId - skipping creation");
                        }
                        mysqli_close($conn1);
                    }
                }
                
                // Handle profile picture upload
                $userData = [
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'role' => $role
                ];
                $profilePicturePath = handleProfilePictureUpload($userId, $userData);
                
                if ($profilePicturePath !== null) {
                    if ($profilePicturePath === false) {
                        $message = "User created successfully, but there was an error uploading the profile picture.";
                        $messageType = "warning";
                    } else {
                        // Update the user with the profile picture path
                        $profilePicturePath = mysqli_real_escape_string($conn, $profilePicturePath);
                        $updateQuery = "UPDATE users SET profile_picture = '$profilePicturePath' WHERE user_id = $userId";
                        mysqli_query($conn, $updateQuery);
                        $message = "User created successfully with profile picture.";
                        $messageType = "success";
                    }
                } else {
                    $message = "User created successfully.";
                    $messageType = "success";
                }
            } else {
                $message = "Error creating user: " . mysqli_error($conn);
                $messageType = "danger";
            }
        }
    }
    
    // Update existing user
    else if ($_POST['action'] === 'update' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
        $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        // Check if email exists for another user
        $checkQuery = "SELECT user_id FROM users WHERE email = '$email' AND user_id != $userId";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            $message = "Error: Email already exists for another user.";
            $messageType = "danger";
        } else {
            // Build query for updating user
            $query = "UPDATE users SET 
                      email = '$email', 
                      firstname = '$firstname', 
                      lastname = '$lastname', 
                      phone = '$phone', 
                      role = '$role', 
                      status = '$status', 
                      updated_at = NOW()";
            
            // If new password is provided, update it
            if (!empty($_POST['password'])) {
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query .= ", password = '$hashedPassword'";
            }
            
            // Handle profile picture upload
            $userData = [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'role' => $role
            ];
            $profilePicturePath = handleProfilePictureUpload($userId, $userData);
            
            if ($profilePicturePath !== null) {
                if ($profilePicturePath === false) {
                    $message = "Error uploading profile picture. Other user data will still be updated.";
                    $messageType = "warning";
                } else {
                    // Add profile picture path to update query
                    $profilePicturePath = mysqli_real_escape_string($conn, $profilePicturePath);
                    $query .= ", profile_picture = '$profilePicturePath'";
                }
            }
            
            $query .= " WHERE user_id = $userId";
            
            if (mysqli_query($conn, $query)) {
                if ($messageType !== "warning") {  // Only set success message if no warning
                    $message = "User updated successfully.";
                    $messageType = "success";
                }
                
                // If the role is changed to 'customer', ensure they exist in core1_movers.customers
                if ($role === 'customer') {
                    // Connect to core1_movers database
                    $conn1 = connectToCore1DB();
                    if ($conn1) {
                        // First check if the customer record already exists
                        $checkQuery = "SELECT customer_id FROM customers WHERE user_id = $userId";
                        $checkResult = mysqli_query($conn1, $checkQuery);
                        
                        // Only insert if customer record doesn't exist
                        if (mysqli_num_rows($checkResult) === 0) {
                            // Create a new customer record with default values
                            $insertCustomerQuery = "INSERT INTO customers 
                                                  (user_id, status, created_at, updated_at) 
                                                  VALUES ($userId, 'offline', NOW(), NOW())";
                            
                            if (!mysqli_query($conn1, $insertCustomerQuery)) {
                                error_log("Error creating customer record during role update: " . mysqli_error($conn1));
                            } else {
                                error_log("Created customer record for user ID: $userId during role update");
                            }
                        } else {
                            error_log("Customer record already exists for user ID: $userId - skipping creation");
                        }
                        mysqli_close($conn1);
                    }
                }
            } else {
                $message = "Error updating user: " . mysqli_error($conn);
                $messageType = "danger";
            }
        }
    }
    
    // Deactivate user (previously delete)
    else if ($_POST['action'] === 'deactivate' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        
        // Check if the user exists and is not the current user
        $checkQuery = "SELECT user_id FROM users WHERE user_id = $userId";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) === 0) {
            $message = "Error: User does not exist.";
            $messageType = "danger";
        } else if ($userId === $_SESSION['user_id']) {
            $message = "Error: You cannot deactivate your own account.";
            $messageType = "danger";
        } else {
            // Deactivate the user instead of deleting
            $query = "UPDATE users SET status = 'inactive', updated_at = NOW() WHERE user_id = $userId";
            
            if (mysqli_query($conn, $query)) {
                $message = "User deactivated successfully.";
                $messageType = "success";
            } else {
                $message = "Error deactivating user: " . mysqli_error($conn);
                $messageType = "danger";
            }
        }
    }
}

// Get the list of users
$query = "SELECT * FROM users ORDER BY role, lastname, firstname";
$result = mysqli_query($conn, $query);
$users = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

// Close the database connection
mysqli_close($conn);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">User Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">User Management</li>
    </ol>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            User Management
            <button type="button" class="btn btn-primary float-end" id="addNewUserBtn" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
        <div class="card-body">
            <table id="usersTable" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Profile</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Account Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $profileImageUrl = getUserProfileImageUrl($user); ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td class="text-center">
                                <?php if ($profileImageUrl && $profileImageUrl !== 'assets/img/default_user.jpg'): ?>
                                    <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; margin: 0 auto;">
                                        <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td>
                                <span class="badge bg-<?php
                                    switch($user['role']) {
                                        case 'super_admin': echo 'danger'; break;
                                        case 'admin': echo 'warning'; break;
                                        case 'finance': echo 'success'; break;
                                        case 'dispatch': echo 'info'; break;
                                        case 'driver': echo 'primary'; break;
                                        case 'customer': echo 'secondary'; break;
                                        default: echo 'dark';
                                    }
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : ($user['status'] === 'inactive' ? 'secondary' : 'warning'); ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                            </td>
                            <td>
                                <!-- Edit User Button -->
                                <button type="button" 
                                    class="btn btn-sm btn-info edit-user-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal"
                                    data-id="<?php echo htmlspecialchars($user['user_id']); ?>" 
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-firstname="<?php echo htmlspecialchars($user['firstname']); ?>"
                                    data-lastname="<?php echo htmlspecialchars($user['lastname']); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                    data-status="<?php echo htmlspecialchars($user['status']); ?>"
                                    data-profile="<?php echo htmlspecialchars($user['profile_picture'] ?? ''); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <!-- Deactivate User Button -->
                                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                    <button type="button" 
                                        class="btn btn-sm btn-warning deactivate-user-btn" 
                                        data-id="<?php echo htmlspecialchars($user['user_id']); ?>" 
                                        data-name="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="createUserForm" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title" id="createUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstname" name="firstname" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                        <div class="form-text">Upload a profile picture (optional). Maximum file size: 2MB.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
                        <div class="form-text">Password must be at least 8 characters.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="super_admin">Super Admin</option>
                                <option value="admin">Admin</option>
                                <option value="finance">Finance</option>
                                <option value="dispatch">Dispatch</option>
                                <option value="driver">Driver</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editUserForm" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div id="edit_profile_preview" class="rounded-circle mx-auto mb-2" style="width: 100px; height: 100px; background-size: cover; background-position: center;"></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="edit_firstname" name="firstname" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="edit_lastname" name="lastname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_profile_picture" class="form-label">Profile Picture</label>
                        <input type="file" class="form-control" id="edit_profile_picture" name="profile_picture" accept="image/*">
                        <div class="form-text">Upload a new profile picture (optional). Leave blank to keep current image.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password" minlength="8" autocomplete="new-password">
                        <div class="form-text">Password must be at least 8 characters.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="super_admin">Super Admin</option>
                                <option value="admin">Admin</option>
                                <option value="finance">Finance</option>
                                <option value="dispatch">Dispatch</option>
                                <option value="driver">Driver</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Deactivate User Form (hidden) -->
<form id="deactivateUserForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="deactivate">
    <input type="hidden" name="user_id" id="deactivate_user_id">
</form>

<!-- Include DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<!-- Include SweetAlert2 from CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">

<!-- Make sure jQuery is loaded first -->
<script>
// Check if jQuery is already loaded from the main page
if (typeof jQuery === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js"><\/script>');
}
</script>

<!-- Include DataTables JS (ensure it's after jQuery) -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<!-- Include SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded. Checking for Bootstrap...');
    
    // Initialize DataTable
    try {
        // Use delegated event handling for edit buttons instead of direct attachment
        // This ensures buttons work even when the table is redrawn for pagination
        $(document).on('click', '.edit-user-btn', handleEditButtonClick);
        $(document).on('click', '.deactivate-user-btn', handleDeactivateButtonClick);
        
        const usersTable = $('#usersTable').DataTable({
            order: [[0, 'asc']],
            pageLength: 10,
            responsive: true,
            // Ensure DOM elements are properly created for each row
            createdRow: function(row, data, dataIndex) {
                // Log for debugging
                console.log(`Row created for data index: ${dataIndex}`);
            },
            // Configure language for better messages
            language: {
                search: "Search users:",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            // After the table is fully initialized and drawn
            initComplete: function(settings, json) {
                console.log('DataTable initialization complete');
                
                // Check if buttons have proper data attributes
                $('.edit-user-btn').each(function() {
                    const userId = $(this).data('id');
                    if (!userId) {
                        console.warn('Found edit button without user ID');
                    }
                });
            },
            // After each redraw (e.g., pagination, search, etc.)
            drawCallback: function(settings) {
                console.log('DataTable redrawn - page length:', this.api().page.len());
                console.log('DataTable redrawn - current page:', this.api().page.info().page);
                
                // Verify data attributes after redraw
                setTimeout(() => {
                    $('.edit-user-btn').each(function(index) {
                        const userId = $(this).attr('data-id');
                        const email = $(this).attr('data-email');
                        console.log(`Button ${index} user ID: ${userId}, email: ${email}`);
                    });
                }, 100);
            }
        });
        
        console.log('DataTable initialized with delegated event handling');
    } catch (e) {
        console.error('Error initializing DataTable:', e);
    }
    
    // Handler function for edit buttons - using delegated events
    function handleEditButtonClick(e) {
        const button = e.currentTarget;
        const userId = button.getAttribute('data-id');
        console.log('Edit button clicked for user ID:', userId);
        
        if (!userId) {
            console.error('Error: User ID not found on button');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Could not determine which user to edit. Please try again.'
            });
            return;
        }
        
        // Clear any previous form values first to avoid data persistence
        document.getElementById('edit_user_id').value = '';
        document.getElementById('edit_email').value = '';
        document.getElementById('edit_firstname').value = '';
        document.getElementById('edit_lastname').value = '';
        document.getElementById('edit_phone').value = '';
        document.getElementById('edit_role').value = '';
        document.getElementById('edit_status').value = '';
        document.getElementById('edit_password').value = '';
        
        try {
            // Get the data attributes from the clicked button
            const email = button.getAttribute('data-email');
            const firstname = button.getAttribute('data-firstname');
            const lastname = button.getAttribute('data-lastname');
            const phone = button.getAttribute('data-phone');
            const role = button.getAttribute('data-role');
            const status = button.getAttribute('data-status');
            const profilePicture = button.getAttribute('data-profile');
            
            // Check if we got all the required data
            if (!email || !firstname || !lastname || !role || !status) {
                console.warn('Some data attributes missing, falling back to AJAX fetch');
                // If any essential data is missing, fetch it via AJAX
                fetchUserDataViaAjax(userId);
                return;
            }
            
            // Set the values from data attributes to form fields
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_firstname').value = firstname;
            document.getElementById('edit_lastname').value = lastname;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_status').value = status;
            
            // Log data for debugging
            console.log('Loaded user data from attributes:', { 
                userId, email, firstname, lastname, phone, role, status, profilePicture 
            });
            
            // Update profile picture preview
            updateProfilePicturePreview(button, firstname, lastname);
            
        } catch (error) {
            console.error('Error loading user data from attributes:', error);
            // If there's an error, fall back to AJAX
            fetchUserDataViaAjax(userId);
        }
    }
    
    // Function to update the profile picture preview
    function updateProfilePicturePreview(button, firstname, lastname) {
        const previewElement = document.getElementById('edit_profile_preview');
        
        // Find the profile image in the table row
        const userRow = button.closest('tr');
        const imgElement = userRow.querySelector('td:nth-child(2) img');
        
        if (imgElement) {
            // Use the image src from the table
            previewElement.style.backgroundImage = `url(${imgElement.src})`;
            previewElement.textContent = '';
            previewElement.style.backgroundColor = '';
            previewElement.style.display = 'block';
        } else {
            // Show initials if no profile picture
            previewElement.style.backgroundImage = '';
            previewElement.style.backgroundColor = '#6c757d'; // Secondary color
            previewElement.style.color = 'white';
            previewElement.style.display = 'flex';
            previewElement.style.alignItems = 'center';
            previewElement.style.justifyContent = 'center';
            previewElement.style.fontSize = '2rem';
            previewElement.textContent = firstname.charAt(0).toUpperCase() + lastname.charAt(0).toUpperCase();
        }
    }
    
    // Function to fetch user data via AJAX if data attributes approach fails
    function fetchUserDataViaAjax(userId) {
        // Show loading indicator
        Swal.fire({
            title: 'Loading...',
            text: 'Fetching user data',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Create a form data object
        const formData = new FormData();
        formData.append('action', 'get_user_data');
        formData.append('user_id', userId);
        
        // Send AJAX request to get user data
        fetch('ajax/user_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Populate the form with the received data
                document.getElementById('edit_user_id').value = data.user.user_id;
                document.getElementById('edit_email').value = data.user.email;
                document.getElementById('edit_firstname').value = data.user.firstname;
                document.getElementById('edit_lastname').value = data.user.lastname;
                document.getElementById('edit_phone').value = data.user.phone || '';
                document.getElementById('edit_role').value = data.user.role;
                document.getElementById('edit_status').value = data.user.status;
                
                // Update profile picture preview if applicable
                const previewElement = document.getElementById('edit_profile_preview');
                if (data.user.profile_image_url) {
                    previewElement.style.backgroundImage = `url(${data.user.profile_image_url})`;
                    previewElement.textContent = '';
                    previewElement.style.backgroundColor = '';
                    previewElement.style.display = 'block';
                } else {
                    // Show initials if no profile picture
                    previewElement.style.backgroundImage = '';
                    previewElement.style.backgroundColor = '#6c757d';
                    previewElement.style.color = 'white';
                    previewElement.style.display = 'flex';
                    previewElement.style.alignItems = 'center';
                    previewElement.style.justifyContent = 'center';
                    previewElement.style.fontSize = '2rem';
                    previewElement.textContent = data.user.firstname.charAt(0).toUpperCase() + 
                                               data.user.lastname.charAt(0).toUpperCase();
                }
                
                console.log('Successfully loaded user data via AJAX');
                Swal.close();
            } else {
                throw new Error(data.message || 'Failed to fetch user data');
            }
        })
        .catch(error => {
            console.error('Error fetching user data:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load user data. Please try again.'
            });
        });
    }
    
    // Handler function for deactivate buttons - using delegated events
    function handleDeactivateButtonClick(e) {
        e.preventDefault();
        const button = e.currentTarget;
        const userId = button.getAttribute('data-id');
        const userName = button.getAttribute('data-name');
        
        console.log('Deactivate button clicked for user ID:', userId);
        
        Swal.fire({
            title: 'Deactivate User',
            text: `Are you sure you want to deactivate ${userName}? The user will be marked as inactive but their data will be preserved.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f0ad4e',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, deactivate this user!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deactivate_user_id').value = userId;
                document.getElementById('deactivateUserForm').submit();
            }
        });
    }
    
    // Show success/error messages with SweetAlert if they exist
    <?php if ($message): ?>
        Swal.fire({
            icon: '<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'error'); ?>',
            title: '<?php echo $messageType === 'success' ? 'Success' : ($messageType === 'warning' ? 'Warning' : 'Error'); ?>',
            text: '<?php echo addslashes($message); ?>',
            confirmButtonColor: '#3085d6'
        });
    <?php endif; ?>
    
    // Profile Picture Preview for Create Form
    const profilePictureInput = document.getElementById('profile_picture');
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                // Check file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'Profile picture must be less than 2MB.'
                    });
                    this.value = ''; // Clear the file input
                    return;
                }
            }
        });
    }
    
    // Profile Picture Preview for Edit Form
    const editProfilePictureInput = document.getElementById('edit_profile_picture');
    if (editProfilePictureInput) {
        editProfilePictureInput.addEventListener('change', function() {
            const previewElement = document.getElementById('edit_profile_preview');
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Check file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'Profile picture must be less than 2MB.'
                    });
                    this.value = ''; // Clear the file input
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.style.backgroundImage = `url(${e.target.result})`;
                    previewElement.textContent = ''; // Clear any text content
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Handle Create User Form Submit with SweetAlert confirmation
    const createUserForm = document.getElementById('createUserForm');
    if (createUserForm) {
        createUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            
            Swal.fire({
                title: 'Create New User',
                text: 'Are you sure you want to create this user?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, create user!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    }
    
    // Handle Edit User Form Submit with SweetAlert confirmation
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            
            Swal.fire({
                title: 'Update User',
                text: 'Are you sure you want to update this user?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update user!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    }
    
    // Log to ensure the script is running
    console.log('User management script initialized');
});
</script> 