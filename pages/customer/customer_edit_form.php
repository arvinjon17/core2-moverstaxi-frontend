<?php
/**
 * Customer Edit Form
 * This file provides the form to edit customer details
 * It is loaded via AJAX when editing a customer from the customer management page
 */

// Ensure this file isn't accessed directly
if (!defined('BASE_PATH')) {
    // If accessed directly, check if the user is logged in and has permission to manage customers
    session_start();
    
    // Check if user_id parameter is provided
    $specificCustomerId = 0;
    
    // If user_id parameter is provided, we're editing a specific customer's details
    if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
        // Check if the current user is logged in
        if (isset($_SESSION['user_id'])) {
            // Make sure auth functions are loaded
            if (!function_exists('hasPermission')) {
                require_once '../../functions/auth.php';
            }
            
            // Now check if the user has permission to manage customers
            if (hasPermission('manage_customers')) {
                $specificCustomerId = (int)$_GET['user_id'];
            } else {
                echo '<div class="alert alert-danger">Unauthorized access. You do not have permission to edit customer details.</div>';
                exit;
            }
        } else {
            echo '<div class="alert alert-danger">Unauthorized access. Please log in to edit customer details.</div>';
            exit;
        }
    } else {
        echo '<div class="alert alert-danger">Invalid request. Customer ID is required.</div>';
        exit;
    }
    
    // Determine the include path based on how the file is being accessed
    $includeBasePath = '';
    
    // Check if being accessed directly or through index.php
    if (strpos($_SERVER['PHP_SELF'], '/pages/customer/') !== false) {
        $includeBasePath = '../../';
    }
} else {
    // If BASE_PATH is defined, use it
    $includeBasePath = BASE_PATH;
    $specificCustomerId = isset($specificCustomerId) ? $specificCustomerId : 0;
}

// Default include path if nothing else worked
if (empty($includeBasePath)) {
    $includeBasePath = '../../';
}

// Check if we're missing required functions
if (!function_exists('connectToCore2DB')) {
    require_once $includeBasePath . 'functions/db.php';
}

// Include role management if needed
if (!function_exists('hasPermission')) {
    require_once $includeBasePath . 'functions/auth.php';
}

// Include profile image functions
if (!function_exists('getUserProfileImageUrl')) {
    require_once $includeBasePath . 'functions/profile_images.php';
}

// Debugging log
error_log("Loading customer_edit_form.php for customer ID: {$specificCustomerId}");

// --- Robust customer data fetch: always render form, never exit on missing DB/data ---
$customerData = [];
$customerAddress = '';
$customerCity = '';
$customerState = '';
$customerZip = '';
$customerCurrentStatus = '';
$missingCore2 = false;
$missingCore1 = false;
$core2Error = '';
$core1Error = '';

// Try to get customer info from core2_movers.users
try {
$conn = connectToCore2DB();
if ($conn) {
        $query = "SELECT * FROM users WHERE user_id = ? AND role = 'customer'";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $specificCustomerId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $customerData = $result->fetch_assoc();
            } else {
                $missingCore2 = true;
            }
            $stmt->close();
        } else {
            $core2Error = 'Failed to prepare statement: ' . $conn->error;
            $missingCore2 = true;
        }
        $conn->close();
    } else {
        $core2Error = 'Could not connect to core2_movers DB.';
        $missingCore2 = true;
    }
} catch (Exception $e) {
    $core2Error = $e->getMessage();
    $missingCore2 = true;
}

// Try to get customer info from core1_movers.customers
try {
$conn = connectToCore1DB();
if ($conn) {
        $query = "SELECT * FROM customers WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $specificCustomerId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $core1CustomerData = $result->fetch_assoc();
                $customerAddress = $core1CustomerData['address'] ?? '';
                $customerCity = $core1CustomerData['city'] ?? '';
                $customerState = $core1CustomerData['state'] ?? '';
                $customerZip = $core1CustomerData['zip'] ?? '';
                $customerCurrentStatus = $core1CustomerData['status'] ?? 'offline';
            } else {
                $missingCore1 = true;
            }
            $stmt->close();
        } else {
            $core1Error = 'Failed to prepare statement: ' . $conn->error;
            $missingCore1 = true;
        }
        $conn->close();
    } else {
        $core1Error = 'Could not connect to core1_movers DB.';
        $missingCore1 = true;
    }
    } catch (Exception $e) {
    $core1Error = $e->getMessage();
    $missingCore1 = true;
}

// Get the profile image URL
$profileImageUrl = getUserProfileImageUrl(
    $specificCustomerId, 
    'customer', 
    $customerData['firstname'] ?? '', 
    $customerData['lastname'] ?? ''
);

// For debugging
error_log("Customer data loaded for editing. Name: " . ($customerData['firstname'] ?? 'Unknown') . " " . ($customerData['lastname'] ?? 'Unknown'));
?>

<style>
    /* Status styling */
    .form-select option[value="active"] {
        background-color: #e8f5e9; /* Light green background */
    }
    
    .form-select option[value="inactive"] {
        background-color: #f5f5f5; /* Light gray background */
    }
    
    .form-select option[value="suspended"] {
        background-color: #ffebee; /* Light red background */
    }
    
    .form-select option[value="online"] {
        background-color: #e8f5e9; /* Light green background */
    }
    
    .form-select option[value="busy"] {
        background-color: #fff8e1; /* Light yellow background */
    }
    
    .form-select option[value="offline"] {
        background-color: #f5f5f5; /* Light gray background */
    }
    
    /* Status badges for option labels */
    .status-badge {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 5px;
    }
    
    .status-badge-active, .status-badge-online {
        background-color: #28a745;
    }
    
    .status-badge-busy {
        background-color: #ffc107;
    }
    
    .status-badge-inactive, .status-badge-offline {
        background-color: #6c757d;
    }
    
    .status-badge-suspended {
        background-color: #dc3545;
    }
</style>

<form id="editCustomerForm" enctype="multipart/form-data">
    <input type="hidden" name="user_id" value="<?php echo $specificCustomerId; ?>">
    
    <div class="row">
        <div class="col-md-4 text-center mb-3">
            <div class="profile-image-container mb-3">
                <?php if ($profileImageUrl && $profileImageUrl !== 'assets/img/default_user.jpg'): ?>
                    <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile Image" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width: 150px; height: 150px; margin: 0 auto;">
                        <?php 
                        $initials = '';
                        if (!empty($customerData['firstname'])) $initials .= strtoupper(substr($customerData['firstname'], 0, 1));
                        if (!empty($customerData['lastname'])) $initials .= strtoupper(substr($customerData['lastname'], 0, 1));
                        echo $initials ?: '?';
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label for="profile_picture" class="form-label">Profile Picture</label>
                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                <small class="text-muted">Upload a new profile picture (optional)</small>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="firstname" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($customerData['firstname'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="lastname" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($customerData['lastname'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customerData['email'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="phone" class="form-label">Phone</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customerData['phone'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="account_status" class="form-label">Account Status</label>
                <select class="form-select" id="account_status" name="account_status" required>
                    <option value="active" <?php echo ($customerData['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($customerData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo ($customerData['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
                <small class="text-muted">Controls whether the user can log in to their account</small>
            </div>
            
            <div class="mb-3">
                <label for="current_status" class="form-label">Current Status</label>
                <select class="form-select" id="current_status" name="current_status" required>
                    <option value="online" <?php echo ($customerCurrentStatus ?? '') === 'online' ? 'selected' : ''; ?>>Online</option>
                    <option value="busy" <?php echo ($customerCurrentStatus ?? '') === 'busy' ? 'selected' : ''; ?>>Busy</option>
                    <option value="offline" <?php echo ($customerCurrentStatus ?? '') === 'offline' ? 'selected' : ''; ?>>Offline</option>
                </select>
                <small class="text-muted">Indicates the customer's current activity status</small>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($customerAddress); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="city" class="form-label">City</label>
                    <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($customerCity); ?>">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="state" class="form-label">State</label>
                    <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($customerState); ?>">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="zip" class="form-label">ZIP Code</label>
                    <input type="text" class="form-control" id="zip" name="zip" value="<?php echo htmlspecialchars($customerZip); ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="password" name="password">
                <small class="text-muted">Leave blank to keep the current password</small>
            </div>
            
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
            </div>
        </div>
    </div>
</form>

<?php if ($missingCore2): ?>
    <div class="alert alert-warning">
        Warning: Customer not found in core2_movers.users. Some fields may be unavailable.<br>
        <?php if ($core2Error) echo htmlspecialchars($core2Error); ?>
    </div>
<?php endif; ?>
<?php if ($missingCore1): ?>
    <div class="alert alert-warning">
        Warning: Customer not found in core1_movers.customers. Address and status fields may be unavailable.<br>
        <?php if ($core1Error) echo htmlspecialchars($core1Error); ?>
    </div>
<?php endif; ?>

<script>
// Client-side validation for the form
$(document).ready(function() {
    // Password confirmation validation
    $('#confirm_password').on('input', function() {
        const password = $('#password').val();
        const confirmPassword = $(this).val();
        
        if (password && password !== confirmPassword) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">Passwords do not match</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Email validation
    $('#email').on('input', function() {
        const email = $(this).val();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">Please enter a valid email address</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Phone number validation
    $('#phone').on('input', function() {
        const phone = $(this).val();
        // Basic phone validation (allows various formats)
        const phoneRegex = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/;
        
        if (phone && !phoneRegex.test(phone)) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">Please enter a valid phone number</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // ZIP code validation
    $('#zip').on('input', function() {
        const zip = $(this).val();
        // US and Canadian postal code formats
        const zipRegex = /(^\d{5}(-\d{4})?$)|(^[ABCEGHJKLMNPRSTVXY]{1}\d{1}[A-Z]{1} *\d{1}[A-Z]{1}\d{1}$)/;
        
        if (zip && !zipRegex.test(zip)) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">Please enter a valid postal/ZIP code</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Required fields validation
    $('#firstname, #lastname, #email, #status').on('input change', function() {
        if (!$(this).val().trim()) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">This field is required</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Profile image validation
    $('#profile_picture').change(function() {
        const file = this.files[0];
        
        // Remove any existing validation message
        $(this).removeClass('is-invalid');
        $(this).next('.invalid-feedback').remove();
        
        if (file) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">Only image files (JPG, PNG, GIF) are allowed</div>');
                this.value = ''; // Clear the input
                return;
            }
            
            // Validate file size (max 5MB)
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if (file.size > maxSize) {
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">File size must be less than 5MB</div>');
                this.value = ''; // Clear the input
                return;
            }
            
            // Preview image if validation passes
            const reader = new FileReader();
            reader.onload = function(e) {
                $('.profile-image-container img').attr('src', e.target.result);
                // If there's no img element yet, create one
                if ($('.profile-image-container img').length === 0) {
                    $('.profile-image-container div').hide();
                    $('.profile-image-container').append('<img src="' + e.target.result + '" alt="Profile Image" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">');
                }
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Store initial form values to detect changes
    const initialFormValues = {};
    
    // Track form field values to detect changes
    function trackFormChanges() {
        const $form = $('#editCustomerForm');
        const formValues = {};
        
        // Get initial values of all form fields
        $form.find('input, select, textarea').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            const value = $field.val();
            
            if (name) {
                initialFormValues[name] = value;
                formValues[name] = value;
            }
        });
        
        // Check for changes to notify parent window
        $form.on('change input', 'input, select, textarea', function() {
            let hasChanges = false;
            
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const value = $field.val();
                
                if (name && initialFormValues[name] !== value) {
                    console.log(`Field ${name} changed from "${initialFormValues[name]}" to "${value}"`);
                    hasChanges = true;
                }
            });
            
            // Notify parent window of changes
            if (window.parent && typeof window.parent.customerFormHasChanges === 'function') {
                window.parent.customerFormHasChanges(hasChanges);
            }
        });
    }
    
    // Initialize form change tracking
    trackFormChanges();
    
    // Define form validation function that will be called from the parent window
    window.validateCustomerForm = function() {
        console.log('Validating customer form...');
        
        const $form = $('#editCustomerForm');
        const result = {
            isValid: true,
            errorFields: []
        };
        
        // Validate required fields
        const requiredFields = [
            { name: 'firstname', label: 'First Name' },
            { name: 'lastname', label: 'Last Name' },
            { name: 'email', label: 'Email' },
            { name: 'account_status', label: 'Account Status' },
            { name: 'current_status', label: 'Current Status' }
        ];
        
        requiredFields.forEach(field => {
            const $field = $form.find(`[name="${field.name}"]`);
            const value = $field.val();
            
            if (!value || value.trim() === '') {
                result.isValid = false;
                result.errorFields.push(field.label);
                $field.addClass('is-invalid');
            } else {
                $field.removeClass('is-invalid');
            }
        });
        
        // Validate email format
        const emailField = $form.find('[name="email"]');
        const emailValue = emailField.val();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (emailValue && !emailRegex.test(emailValue)) {
            result.isValid = false;
            if (!result.errorFields.includes('Email')) {
                result.errorFields.push('Email (invalid format)');
            }
            emailField.addClass('is-invalid');
        }
        
        // Validate password confirmation match (if new password is provided)
        const passwordField = $form.find('[name="password"]');
        const confirmField = $form.find('[name="confirm_password"]');
        const passwordValue = passwordField.val();
        const confirmValue = confirmField.val();
        
        if (passwordValue && passwordValue !== confirmValue) {
            result.isValid = false;
            result.errorFields.push('Password Confirmation (does not match)');
            confirmField.addClass('is-invalid');
        } else {
            confirmField.removeClass('is-invalid');
        }
        
        // Validate account status
        const accountStatusField = $form.find('[name="account_status"]');
        const accountStatusValue = accountStatusField.val();
        const validAccountStatuses = ['active', 'inactive', 'suspended'];
        
        if (!validAccountStatuses.includes(accountStatusValue)) {
            result.isValid = false;
            result.errorFields.push('Account Status (invalid value)');
            accountStatusField.addClass('is-invalid');
        } else {
            accountStatusField.removeClass('is-invalid');
        }
        
        // Validate current status
        const currentStatusField = $form.find('[name="current_status"]');
        const currentStatusValue = currentStatusField.val();
        const validCurrentStatuses = ['online', 'busy', 'offline'];
        
        if (!validCurrentStatuses.includes(currentStatusValue)) {
            result.isValid = false;
            result.errorFields.push('Current Status (invalid value)');
            currentStatusField.addClass('is-invalid');
        } else {
            currentStatusField.removeClass('is-invalid');
        }
        
        console.log('Validation result:', result);
        return result;
    };
    
    // Attempt to find the parent window's save button
    try {
        const saveBtn = window.parent.document.getElementById('saveCustomerBtn');
        if (saveBtn) {
            console.log('Found save button in parent window');
        } else {
            console.warn('Could not find saveCustomerBtn in parent window');
        }
    } catch (e) {
        console.error('Error accessing parent window elements:', e);
    }
    
    // Handle form submission internally for validation
    $('#editCustomerForm').on('submit', function(e) {
        e.preventDefault();
        
        const validationResult = window.validateCustomerForm();
        if (validationResult.isValid) {
            console.log('Form is valid, ready to submit');
        } else {
            console.warn('Form validation failed');
        }
    });
});

// Function to be called by the parent window
function initializeFormElements() {
    // Any additional initialization (e.g., select2, datepicker, etc.)
    console.log('Initializing form elements');
}
</script> 