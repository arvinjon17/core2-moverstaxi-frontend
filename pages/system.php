<?php
// Make sure no output is sent before headers
ob_start();
session_start();
require_once 'functions/db.php';
require_once 'functions/auth.php';
require_once 'functions/otp.php';

// Check if user has permission
if (!hasPermission('manage_system_settings')) {
    $_SESSION['error'] = "You don't have permission to access system settings.";
    header("Location: index.php?page=dashboard");
    exit();
}

$conn = connectToCore2DB();

// Process form submissions first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle general settings update
        if ($_POST['action'] === 'update_settings' && hasPermission('manage_system_settings')) {
            // Process settings update
            $companyName = $conn->real_escape_string($_POST['company_name']);
            $companyEmail = $conn->real_escape_string($_POST['company_email']);
            $companyPhone = $conn->real_escape_string($_POST['company_phone']);
            $companyAddress = $conn->real_escape_string($_POST['company_address']);
            $bookingRatePerKm = (float)$_POST['booking_rate_per_km'];
            $baseFare = (float)$_POST['base_fare'];
            $minimumFare = (float)$_POST['minimum_fare'];
            $googleMapsApiKey = $conn->real_escape_string($_POST['google_maps_api_key']);
            $systemTimezone = $conn->real_escape_string($_POST['system_timezone']);
            
            $updateSettings = $conn->query("
                UPDATE system_settings SET 
                company_name = '$companyName',
                company_email = '$companyEmail',
                company_phone = '$companyPhone',
                company_address = '$companyAddress',
                booking_rate_per_km = $bookingRatePerKm,
                base_fare = $baseFare,
                minimum_fare = $minimumFare,
                google_maps_api_key = '$googleMapsApiKey',
                system_timezone = '$systemTimezone',
                updated_at = NOW()
                WHERE id = 1
            ");
            
            if ($updateSettings) {
                $_SESSION['success'] = "System settings updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update settings: " . $conn->error;
            }
            
            // Redirect to prevent form resubmission
            header("Location: index.php?page=system");
            exit();
        }
        
        // Handle security settings - OTP toggle
        if ($_POST['action'] === 'toggle_otp' && hasPermission('manage_otp_settings')) {
            $enableOtp = isset($_POST['enable_otp']) ? 1 : 0;
            $result = toggleGlobalOtp($enableOtp);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
            
            // Redirect to prevent form resubmission
            header("Location: index.php?page=system");
            exit();
        }
    }
}

// Fetch existing system settings
$systemSettings = [];
$getSystemSettings = $conn->query("SELECT * FROM system_settings LIMIT 1");

if ($getSystemSettings && $getSystemSettings->num_rows > 0) {
    $systemSettings = $getSystemSettings->fetch_assoc();
} else {
    // If no settings exist, create default ones
    $createDefaultSettings = $conn->query("
        INSERT INTO system_settings (
            company_name, 
            company_email, 
            company_phone, 
            company_address, 
            booking_rate_per_km, 
            base_fare, 
            minimum_fare, 
            google_maps_api_key,
            system_timezone,
            enable_otp,
            created_at, 
            updated_at
        ) VALUES (
            'Core 2 Trucking', 
            'info@core2trucking.com', 
            '+63 123 456 7890', 
            'Manila, Philippines', 
            25.00, 
            200.00, 
            300.00, 
            '',
            'Asia/Manila',
            0,
            NOW(), 
            NOW()
        )
    ");
    
    if ($createDefaultSettings) {
        // Fetch the newly created settings
        $getSystemSettings = $conn->query("SELECT * FROM system_settings LIMIT 1");
        if ($getSystemSettings && $getSystemSettings->num_rows > 0) {
            $systemSettings = $getSystemSettings->fetch_assoc();
        }
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">System Settings</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php?page=dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active">System Settings</li>
    </ol>
    
    <!-- Display Messages -->
    <?php if (isset($_SESSION['success'])) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Nav tabs for different settings sections -->
    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                <i class="fas fa-cogs me-1"></i> General Settings
            </button>
        </li>
        <?php if (hasPermission('manage_otp_settings')): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                <i class="fas fa-shield-alt me-1"></i> Security Settings
            </button>
        </li>
        <?php endif; ?>
    </ul>
    
    <!-- Tab content -->
    <div class="tab-content" id="settingsTabContent">
        <!-- General Settings Tab -->
        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-building me-1"></i>
                    Company Information & System Configuration
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-building me-1"></i> Company Information
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="company_name" class="form-label">Company Name</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?= $systemSettings['company_name'] ?? '' ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="company_email" class="form-label">Company Email</label>
                                            <input type="email" class="form-control" id="company_email" name="company_email" value="<?= $systemSettings['company_email'] ?? '' ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="company_phone" class="form-label">Company Phone</label>
                                            <input type="text" class="form-control" id="company_phone" name="company_phone" value="<?= $systemSettings['company_phone'] ?? '' ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="company_address" class="form-label">Company Address</label>
                                            <textarea class="form-control" id="company_address" name="company_address" rows="3" required><?= $systemSettings['company_address'] ?? '' ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-success text-white">
                                        <i class="fas fa-dollar-sign me-1"></i> Booking Rates
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="booking_rate_per_km" class="form-label">Rate per Kilometer (PHP)</label>
                                            <input type="number" class="form-control" id="booking_rate_per_km" name="booking_rate_per_km" step="0.01" min="0" value="<?= $systemSettings['booking_rate_per_km'] ?? '25.00' ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="base_fare" class="form-label">Base Fare (PHP)</label>
                                            <input type="number" class="form-control" id="base_fare" name="base_fare" step="0.01" min="0" value="<?= $systemSettings['base_fare'] ?? '200.00' ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="minimum_fare" class="form-label">Minimum Fare (PHP)</label>
                                            <input type="number" class="form-control" id="minimum_fare" name="minimum_fare" step="0.01" min="0" value="<?= $systemSettings['minimum_fare'] ?? '300.00' ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <i class="fas fa-cog me-1"></i> System Configuration
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="google_maps_api_key" class="form-label">Google Maps API Key</label>
                                            <input type="text" class="form-control" id="google_maps_api_key" name="google_maps_api_key" value="<?= $systemSettings['google_maps_api_key'] ?? '' ?>">
                                            <small class="text-muted">Used for maps and distance calculations</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="system_timezone" class="form-label">System Timezone</label>
                                            <select class="form-select" id="system_timezone" name="system_timezone">
                                                <?php
                                                $timezones = DateTimeZone::listIdentifiers();
                                                $currentTimezone = $systemSettings['system_timezone'] ?? 'Asia/Manila';
                                                foreach ($timezones as $timezone) {
                                                    $selected = ($timezone == $currentTimezone) ? 'selected' : '';
                                                    echo "<option value=\"$timezone\" $selected>$timezone</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Security Settings Tab -->
        <?php if (hasPermission('manage_otp_settings')): ?>
        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shield-alt me-1"></i>
                    Security & Authentication Settings
                </div>
                <div class="card-body">
                    <!-- OTP Settings Form -->
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="toggle_otp">
                        
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-key me-1"></i> Two-Factor Authentication (OTP)
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_otp" name="enable_otp" <?= (isset($systemSettings['enable_otp']) && $systemSettings['enable_otp'] == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_otp">Enable One-Time Password (OTP) Authentication</label>
                                    </div>
                                    <small class="text-muted">When enabled, users will be required to enter a verification code sent to their email after login.</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> 
                                    <strong>Note:</strong> OTP settings can be enabled/disabled on a per-user basis in the user profile settings.
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save me-1"></i> Save OTP Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize SweetAlert for success/error messages
    <?php if (isset($_SESSION['success'])) : ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: "<?= $_SESSION['success'] ?>",
        confirmButtonColor: '#3085d6'
    });
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])) : ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: "<?= $_SESSION['error'] ?>",
        confirmButtonColor: '#3085d6'
    });
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script> 