<?php
// User Profile Page
if (!hasPermission('access_profile')) {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    exit;
}

// Connect to the database
$conn = connectToCore2DB();

// Get current user ID from session
$userId = $_SESSION['user_id'] ?? 0;

// Initialize variables
$message = '';
$messageType = '';
$userData = null;

// Function to handle profile picture upload
function handleProfilePictureUpload($userId) {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] == UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded
    }
    
    $file = $_FILES['profile_picture'];
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($detectedType, $allowedTypes)) {
        return false;
    }
    
    // Make sure directory exists
    $uploadDir = 'assets/img/users/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return false; // Failed to create directory
        }
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = 'user_' . $userId . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $newFilename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return $uploadPath;
    }
    
    return false;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
        $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        
        // Build the query
        $query = "UPDATE users SET 
                  firstname = '$firstname', 
                  lastname = '$lastname', 
                  phone = '$phone', 
                  updated_at = NOW()";
        
        // Handle password change if provided
        if (!empty($_POST['password']) && !empty($_POST['current_password'])) {
            // Verify current password
            $passwordQuery = "SELECT password FROM users WHERE user_id = $userId";
            $passwordResult = mysqli_query($conn, $passwordQuery);
            
            if ($passwordRow = mysqli_fetch_assoc($passwordResult)) {
                if (password_verify($_POST['current_password'], $passwordRow['password'])) {
                    // Current password is correct, add new password to update query
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $query .= ", password = '$hashedPassword'";
                } else {
                    $message = "Current password is incorrect. Profile updated without changing password.";
                    $messageType = "warning";
                }
            }
        }
        
        // Handle profile picture upload
        $profilePicturePath = handleProfilePictureUpload($userId);
        
        if ($profilePicturePath !== null) {
            if ($profilePicturePath === false) {
                $message = "Error uploading profile picture. Other profile data will still be updated.";
                $messageType = "warning";
            } else {
                // Add profile picture path to update query
                $profilePicturePath = mysqli_real_escape_string($conn, $profilePicturePath);
                $query .= ", profile_picture = '$profilePicturePath'";
            }
        }
        
        $query .= " WHERE user_id = $userId";
        
        if (mysqli_query($conn, $query)) {
            if (empty($message)) {
                $message = "Profile updated successfully.";
                $messageType = "success";
            }
            
            // Update session variables
            $_SESSION['user_firstname'] = $firstname;
            $_SESSION['user_lastname'] = $lastname;
            $_SESSION['user_full_name'] = $firstname . ' ' . $lastname;
        } else {
            $message = "Error updating profile: " . mysqli_error($conn);
            $messageType = "danger";
        }
    }
}

// Fetch user data
$query = "SELECT * FROM users WHERE user_id = $userId";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $userData = mysqli_fetch_assoc($result);
} else {
    $message = "Error retrieving user data.";
    $messageType = "danger";
}

// Close database connection
mysqli_close($conn);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">My Profile</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">My Profile</li>
    </ol>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-xl-4">
            <!-- Profile picture card -->
            <div class="card mb-4 mb-xl-0">
                <div class="card-header">Profile Picture</div>
                <div class="card-body text-center">
                    <?php if (!empty($userData['profile_picture'])): ?>
                        <img class="img-account-profile rounded-circle mb-2" src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" alt="User profile image" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="user-avatar-circle mb-2" style="width: 150px; height: 150px; background-color: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 64px; border-radius: 50%; margin: 0 auto;">
                            <?php 
                                $initials = strtoupper(substr($userData['firstname'], 0, 1) . substr($userData['lastname'], 0, 1));
                                echo $initials;
                            ?>
                        </div>
                    <?php endif; ?>
                    <div class="small font-italic text-muted mb-4">JPG, GIF or PNG no larger than 2MB</div>
                    <button class="btn btn-primary" type="button" onclick="document.getElementById('profilePictureInput').click();">
                        Upload new image
                    </button>
                </div>
            </div>
            
            <!-- Role information card -->
            <div class="card mb-4 mt-4">
                <div class="card-header">Account Information</div>
                <div class="card-body">
                    <p><strong>Role:</strong> <?php echo ucfirst($userData['role']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
                    <p><strong>Status:</strong> 
                        <?php if ($userData['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php elseif ($userData['status'] === 'inactive'): ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Suspended</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Last Login:</strong> 
                        <?php 
                            echo !empty($userData['last_login']) ? 
                                date('M d, Y H:i:s A', strtotime($userData['last_login'])) : 
                                'Never';
                        ?>
                    </p>
                    <p><strong>Member Since:</strong> 
                        <?php 
                            echo date('M d, Y', strtotime($userData['created_at']));
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8">
            <!-- Account details card -->
            <div class="card mb-4">
                <div class="card-header">Account Details</div>
                <div class="card-body">
                    <form id="profileForm" method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" style="display: none;">
                        
                        <!-- Form Group (first name)-->
                        <div class="mb-3">
                            <label class="small mb-1" for="firstname">First Name</label>
                            <input class="form-control" id="firstname" name="firstname" type="text" value="<?php echo htmlspecialchars($userData['firstname']); ?>" required>
                        </div>
                        
                        <!-- Form Group (last name)-->
                        <div class="mb-3">
                            <label class="small mb-1" for="lastname">Last Name</label>
                            <input class="form-control" id="lastname" name="lastname" type="text" value="<?php echo htmlspecialchars($userData['lastname']); ?>" required>
                        </div>
                        
                        <!-- Form Group (email)-->
                        <div class="mb-3">
                            <label class="small mb-1" for="email">Email address</label>
                            <input class="form-control" id="email" type="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly disabled>
                            <div class="form-text">Email cannot be changed. Contact admin for assistance.</div>
                        </div>
                        
                        <!-- Form Group (phone number)-->
                        <div class="mb-3">
                            <label class="small mb-1" for="phone">Phone number</label>
                            <input class="form-control" id="phone" name="phone" type="tel" value="<?php echo htmlspecialchars($userData['phone']); ?>" required>
                        </div>
                        
                        <hr class="my-4">
                        <h5 class="mb-3">Change Password</h5>
                        <p class="small text-muted mb-3">Leave these fields empty if you don't want to change your password.</p>
                        
                        <!-- Form Group (current password)-->
                        <div class="mb-3">
                            <label class="small mb-1" for="current_password">Current Password</label>
                            <input class="form-control" id="current_password" name="current_password" type="password">
                        </div>
                        
                        <!-- Form Group (new password)-->
                        <div class="mb-3">
                            <label class="small mb-1" for="password">New Password</label>
                            <input class="form-control" id="password" name="password" type="password">
                        </div>
                        
                        <!-- Form Group (confirm password)-->
                        <div class="mb-3">
                            <label class="small mb-1" for="confirm_password">Confirm Password</label>
                            <input class="form-control" id="confirm_password" name="confirm_password" type="password">
                            <div class="form-text" id="password-match-message"></div>
                        </div>
                        
                        <!-- Save changes button-->
                        <button class="btn btn-primary" type="submit">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show success/error messages with SweetAlert if they exist
    <?php if ($message): ?>
        Swal.fire({
            icon: '<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'error'); ?>',
            title: '<?php echo $messageType === 'success' ? 'Success' : ($messageType === 'warning' ? 'Warning' : 'Error'); ?>',
            text: '<?php echo addslashes($message); ?>',
            confirmButtonColor: '#3085d6'
        });
    <?php endif; ?>
    
    // Handle profile picture selection
    const profilePictureInput = document.getElementById('profilePictureInput');
    profilePictureInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            // Check file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Image size exceeds 2MB limit.',
                    confirmButtonColor: '#3085d6'
                });
                this.value = ''; // Clear the file input
                return;
            }
            
            // Check file type
            const fileType = file.type;
            if (fileType !== 'image/jpeg' && fileType !== 'image/png' && fileType !== 'image/gif') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Only JPG, PNG, and GIF images are allowed.',
                    confirmButtonColor: '#3085d6'
                });
                this.value = ''; // Clear the file input
                return;
            }
            
            // Preview the image
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.querySelector('.img-account-profile');
                if (img) {
                    img.src = e.target.result;
                } else {
                    // If no image element exists, replace the initials circle with an image
                    const circle = document.querySelector('.user-avatar-circle');
                    if (circle) {
                        circle.parentNode.innerHTML = `<img class="img-account-profile rounded-circle mb-2" src="${e.target.result}" alt="User profile image" style="width: 150px; height: 150px; object-fit: cover;">`;
                    }
                }
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Handle password confirmation
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordMatchMessage = document.getElementById('password-match-message');
    
    function checkPasswordMatch() {
        if (passwordInput.value && confirmPasswordInput.value) {
            if (passwordInput.value === confirmPasswordInput.value) {
                passwordMatchMessage.textContent = 'Passwords match!';
                passwordMatchMessage.className = 'form-text text-success';
                return true;
            } else {
                passwordMatchMessage.textContent = 'Passwords do not match!';
                passwordMatchMessage.className = 'form-text text-danger';
                return false;
            }
        } else {
            passwordMatchMessage.textContent = '';
            return true; // No validation needed if fields are empty
        }
    }
    
    passwordInput.addEventListener('input', checkPasswordMatch);
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    
    // Form validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = passwordInput.value;
        
        // If new password is provided, make sure current password is also provided
        if (newPassword && !currentPassword) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please enter your current password to change to a new password.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Check password match
        if (newPassword && !checkPasswordMatch()) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'New password and confirmation do not match.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        // Otherwise let form submit
    });
});
</script> 