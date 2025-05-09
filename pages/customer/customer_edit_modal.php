<?php
// Customer Edit Modal (Robust, No Cross-DB Joins)
header('Content-Type: text/html; charset=UTF-8');
require_once '../../functions/db.php';
require_once '../../functions/auth.php';

// Check permission
session_start();
if (!isset($_SESSION['user_id']) || !hasPermission('manage_customers')) {
    echo '<div class="alert alert-danger">Unauthorized access.</div>';
    exit;
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($userId <= 0) {
    echo '<div class="alert alert-danger">Invalid customer ID.</div>';
    exit;
}

// Fetch from core2_movers.users
$core2 = connectToCore2DB();
$userData = [];
$core2Error = '';
if ($core2) {
    $stmt = $core2->prepare('SELECT firstname, lastname, email, phone, status FROM users WHERE user_id = ? AND role = "customer"');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $userData = $result->fetch_assoc();
        } else {
            $core2Error = 'Customer not found in core2_movers.users.';
        }
        $stmt->close();
    } else {
        $core2Error = 'Query error: ' . $core2->error;
    }
    $core2->close();
} else {
    $core2Error = 'Could not connect to core2_movers.';
}

// Fetch from core1_movers.customers
$core1 = connectToCore1DB();
$customerData = [];
$core1Error = '';
if ($core1) {
    $stmt = $core1->prepare('SELECT address, city, state, zip, status FROM customers WHERE user_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $customerData = $result->fetch_assoc();
        } else {
            $core1Error = 'Customer not found in core1_movers.customers.';
        }
        $stmt->close();
    } else {
        $core1Error = 'Query error: ' . $core1->error;
    }
    $core1->close();
} else {
    $core1Error = 'Could not connect to core1_movers.';
}

// Render the form
?>
<div class="container-fluid">
    <?php if ($core2Error): ?>
        <div class="alert alert-warning mb-2"><?php echo htmlspecialchars($core2Error); ?></div>
    <?php endif; ?>
    <?php if ($core1Error): ?>
        <div class="alert alert-warning mb-2"><?php echo htmlspecialchars($core1Error); ?></div>
    <?php endif; ?>
    <form id="editCustomerNewForm" autocomplete="off">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="firstname" class="form-label">First Name</label>
                <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($userData['firstname'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="lastname" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($userData['lastname'] ?? ''); ?>" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">Phone (PH format)</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" pattern="^(\+639|09)\d{9}$" required>
            <div class="form-text">Format: +639XXXXXXXXX or 09XXXXXXXXX</div>
        </div>
        <div class="mb-3">
            <label for="account_status" class="form-label">Account Status</label>
            <select class="form-select" id="account_status" name="account_status" required>
                <option value="active" <?php echo (isset($userData['status']) && $userData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo (isset($userData['status']) && $userData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                <option value="suspended" <?php echo (isset($userData['status']) && $userData['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($customerData['address'] ?? ''); ?></textarea>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="city" class="form-label">City</label>
                <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($customerData['city'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label for="state" class="form-label">State</label>
                <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($customerData['state'] ?? ''); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label for="zip" class="form-label">ZIP Code</label>
                <input type="text" class="form-control" id="zip" name="zip" value="<?php echo htmlspecialchars($customerData['zip'] ?? ''); ?>">
            </div>
        </div>
        <div class="mb-3">
            <label for="current_status" class="form-label">Current Status</label>
            <select class="form-select" id="current_status" name="current_status" required>
                <option value="online" <?php echo (isset($customerData['status']) && $customerData['status'] === 'online') ? 'selected' : ''; ?>>Online</option>
                <option value="busy" <?php echo (isset($customerData['status']) && $customerData['status'] === 'busy') ? 'selected' : ''; ?>>Busy</option>
                <option value="offline" <?php echo (isset($customerData['status']) && $customerData['status'] === 'offline') ? 'selected' : ''; ?>>Offline</option>
            </select>
        </div>
    </form>
</div>
<script>
// PH phone validation
$(document).ready(function() {
    $('#phone').on('input', function() {
        const val = $(this).val();
        const phRegex = /^(\+639|09)\d{9}$/;
        if (!phRegex.test(val)) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">Please enter a valid PH number (+639XXXXXXXXX or 09XXXXXXXXX)</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
});
</script> 