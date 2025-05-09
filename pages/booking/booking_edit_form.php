<?php
// Booking Edit Form - loaded via AJAX into the edit modal

// Include required files
if (!function_exists('connectToCore2DB')) {
    require_once '../../functions/db.php';
}

// Check if a booking ID was provided
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    echo '<div class="alert alert-danger">Invalid booking ID.</div>';
    exit;
}

$bookingId = (int)$_GET['booking_id'];

try {
    // Connect to databases
    $core2Conn = connectToCore2DB();
    $core1Conn = connectToCore1DB();
    
    if (!$core2Conn || !$core1Conn) {
        throw new Exception("Database connection failed");
    }
    
    // 1. First, get booking information from core2 database
    $bookingQuery = "SELECT 
        booking_id, customer_id, user_id, pickup_location, dropoff_location, 
        pickup_datetime, dropoff_datetime, vehicle_id, driver_id, booking_status, 
        fare_amount, distance_km, duration_minutes, special_instructions, 
        created_at, updated_at
    FROM bookings 
    WHERE booking_id = ?";
    
    $bookingStmt = $core2Conn->prepare($bookingQuery);
    
    if (!$bookingStmt) {
        throw new Exception("Prepare failed for booking query: " . $core2Conn->error);
    }
    
    $bookingStmt->bind_param('i', $bookingId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    
    if ($bookingResult->num_rows === 0) {
        echo '<div class="alert alert-danger">Booking not found.</div>';
        exit;
    }
    
    $bookingData = $bookingResult->fetch_assoc();
    $bookingStmt->close();
    
    // 2. Get customer info if available
    if (!empty($bookingData['customer_id'])) {
        // Get customer base data from core1 database
        $customerId = $bookingData['customer_id'];
        $customerQuery = "SELECT 
            customer_id, user_id
        FROM customers 
        WHERE customer_id = ?";
        
        $customerStmt = $core1Conn->prepare($customerQuery);
        
        if ($customerStmt) {
            $customerStmt->bind_param('i', $customerId);
            $customerStmt->execute();
            $customerResult = $customerStmt->get_result();
            
            if ($customerResult->num_rows > 0) {
                $customerData = $customerResult->fetch_assoc();
                
                // Get customer user details if available
                if (!empty($customerData['user_id'])) {
                    $userId = $customerData['user_id'];
                    $userQuery = "SELECT 
                        user_id, firstname, lastname, email, phone
                    FROM users 
                    WHERE user_id = ?";
                    
                    $userStmt = $core2Conn->prepare($userQuery);
                    
                    if ($userStmt) {
                        $userStmt->bind_param('i', $userId);
                        $userStmt->execute();
                        $userResult = $userStmt->get_result();
                        
                        if ($userResult->num_rows > 0) {
                            $userData = $userResult->fetch_assoc();
                            
                            // Add customer data to booking data
                            $bookingData['customer_firstname'] = $userData['firstname'];
                            $bookingData['customer_lastname'] = $userData['lastname'];
                            $bookingData['customer_email'] = $userData['email'];
                            $bookingData['customer_phone'] = $userData['phone'];
                        }
                        $userStmt->close();
                    }
                }
                $customerStmt->close();
            }
        }
    }
    
    // Get list of customers for the dropdown - do it without cross-database JOIN
    $customers = [];
    
    // First get all customer IDs from core1
    $customersQuery = "SELECT customer_id, user_id FROM customers";
    $customerStmt = $core1Conn->prepare($customersQuery);
    
    if ($customerStmt) {
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();
        
        $customerUserIds = [];
        $customerIdToUserIdMap = [];
        
        while ($customerRow = $customerResult->fetch_assoc()) {
            if (!empty($customerRow['user_id'])) {
                $customerUserIds[] = $customerRow['user_id'];
                $customerIdToUserIdMap[$customerRow['user_id']] = $customerRow['customer_id'];
            }
        }
        $customerStmt->close();
        
        // If we have customers, get their user details
        if (!empty($customerUserIds)) {
            $placeholders = implode(',', array_fill(0, count($customerUserIds), '?'));
            $userQuery = "SELECT user_id, firstname, lastname, phone, email, status 
                         FROM users 
                         WHERE user_id IN ($placeholders) AND status = 'active'
                         ORDER BY lastname, firstname";
            
            $userStmt = $core2Conn->prepare($userQuery);
            
            if ($userStmt) {
                // Create the types string (all integers)
                $types = str_repeat('i', count($customerUserIds));
                
                // Bind the parameters
                $userStmt->bind_param($types, ...$customerUserIds);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                
                while ($userRow = $userResult->fetch_assoc()) {
                    $customerId = $customerIdToUserIdMap[$userRow['user_id']];
                    $customers[] = [
                        'customer_id' => $customerId,
                        'firstname' => $userRow['firstname'],
                        'lastname' => $userRow['lastname'],
                        'phone' => $userRow['phone'],
                        'email' => $userRow['email']
                    ];
                }
                $userStmt->close();
            }
        }
    }
    
    // Get list of vehicles for the dropdown
    $vehiclesQuery = "SELECT 
        vehicle_id, model, plate_number, capacity, status
    FROM vehicles
    WHERE status = 'active'
    ORDER BY model";
    
    $vehicleStmt = $core1Conn->prepare($vehiclesQuery);
    $vehicles = [];
    
    if ($vehicleStmt) {
        $vehicleStmt->execute();
        $vehicleResult = $vehicleStmt->get_result();
        
        while ($vehicleRow = $vehicleResult->fetch_assoc()) {
            $vehicles[] = $vehicleRow;
        }
        $vehicleStmt->close();
    }
    
    // Get list of drivers for the dropdown - do it without cross-database JOIN
    $drivers = [];
    
    // First get all driver IDs from core1
    $driversQuery = "SELECT driver_id, user_id, license_number, status 
                    FROM drivers 
                    WHERE status IN ('available', 'busy', 'offline')";
    $driverStmt = $core1Conn->prepare($driversQuery);
    
    if ($driverStmt) {
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        
        $driverUserIds = [];
        $driverIdToDataMap = [];
        
        while ($driverRow = $driverResult->fetch_assoc()) {
            if (!empty($driverRow['user_id'])) {
                $driverUserIds[] = $driverRow['user_id'];
                $driverIdToDataMap[$driverRow['user_id']] = [
                    'driver_id' => $driverRow['driver_id'],
                    'license_number' => $driverRow['license_number'],
                    'status' => $driverRow['status']
                ];
            }
        }
        $driverStmt->close();
        
        // If we have drivers, get their user details
        if (!empty($driverUserIds)) {
            $placeholders = implode(',', array_fill(0, count($driverUserIds), '?'));
            $userQuery = "SELECT user_id, firstname, lastname, phone 
                         FROM users 
                         WHERE user_id IN ($placeholders)
                         ORDER BY lastname, firstname";
            
            $userStmt = $core2Conn->prepare($userQuery);
            
            if ($userStmt) {
                // Create the types string (all integers)
                $types = str_repeat('i', count($driverUserIds));
                
                // Bind the parameters
                $userStmt->bind_param($types, ...$driverUserIds);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                
                while ($userRow = $userResult->fetch_assoc()) {
                    $driverData = $driverIdToDataMap[$userRow['user_id']];
                    $drivers[] = [
                        'driver_id' => $driverData['driver_id'],
                        'firstname' => $userRow['firstname'],
                        'lastname' => $userRow['lastname'],
                        'phone' => $userRow['phone'],
                        'license_number' => $driverData['license_number'],
                        'status' => $driverData['status']
                    ];
                }
                $userStmt->close();
            }
        }
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error retrieving booking data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<form id="editBookingForm" class="needs-validation" novalidate>
    <input type="hidden" name="booking_id" value="<?php echo $bookingId; ?>">
    
    <div class="row mb-3">
        <div class="col-md-12">
            <label for="customer_id" class="form-label">Customer</label>
            <select class="form-select" id="customer_id" name="customer_id" required>
                <option value="">Select Customer</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['customer_id']; ?>" <?php echo ($customer['customer_id'] == $bookingData['customer_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname'] . ' (' . $customer['phone'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">
                Please select a customer.
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="pickup_location" class="form-label">Pickup Location</label>
            <input type="text" class="form-control" id="pickup_location" name="pickup_location" 
                   value="<?php echo htmlspecialchars($bookingData['pickup_location']); ?>" required>
            <div class="invalid-feedback">
                Please enter a pickup location.
            </div>
        </div>
        <div class="col-md-6">
            <label for="dropoff_location" class="form-label">Dropoff Location</label>
            <input type="text" class="form-control" id="dropoff_location" name="dropoff_location" 
                   value="<?php echo htmlspecialchars($bookingData['dropoff_location']); ?>" required>
            <div class="invalid-feedback">
                Please enter a dropoff location.
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="pickup_datetime" class="form-label">Pickup Date & Time</label>
            <?php 
                // Format datetime for datetime-local input (YYYY-MM-DDThh:mm)
                $pickupDateTime = date('Y-m-d\TH:i', strtotime($bookingData['pickup_datetime']));
            ?>
            <input type="datetime-local" class="form-control" id="pickup_datetime" name="pickup_datetime" 
                   value="<?php echo $pickupDateTime; ?>" required>
            <div class="invalid-feedback">
                Please select a pickup date and time.
            </div>
        </div>
        <div class="col-md-6">
            <label for="dropoff_datetime" class="form-label">Expected Dropoff Date & Time</label>
            <?php 
                // Format dropoff datetime if it exists
                $dropoffDateTime = !empty($bookingData['dropoff_datetime']) 
                    ? date('Y-m-d\TH:i', strtotime($bookingData['dropoff_datetime'])) 
                    : '';
            ?>
            <input type="datetime-local" class="form-control" id="dropoff_datetime" name="dropoff_datetime" 
                   value="<?php echo $dropoffDateTime; ?>">
            <small class="text-muted">Optional</small>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="vehicle_id" class="form-label">Vehicle</label>
            <select class="form-select" id="vehicle_id" name="vehicle_id">
                <option value="">Select Vehicle (Optional)</option>
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?php echo $vehicle['vehicle_id']; ?>" <?php echo ($vehicle['vehicle_id'] == $bookingData['vehicle_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($vehicle['model'] . ' (' . $vehicle['plate_number'] . ') - ' . $vehicle['capacity'] . ' seats'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="driver_id" class="form-label">Driver</label>
            <select class="form-select" id="driver_id" name="driver_id">
                <option value="">Select Driver (Optional)</option>
                <?php foreach ($drivers as $driver): ?>
                    <option value="<?php echo $driver['driver_id']; ?>" <?php echo ($driver['driver_id'] == $bookingData['driver_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($driver['firstname'] . ' ' . $driver['lastname'] . ' (' . $driver['phone'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="distance_km" class="form-label">Distance (km)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="distance_km" name="distance_km" 
                   value="<?php echo $bookingData['distance_km'] ?: ''; ?>">
            <small class="text-muted">Optional</small>
        </div>
        <div class="col-md-6">
            <label for="duration_minutes" class="form-label">Duration (minutes)</label>
            <input type="number" step="1" min="0" class="form-control" id="duration_minutes" name="duration_minutes" 
                   value="<?php echo $bookingData['duration_minutes'] ?: ''; ?>">
            <small class="text-muted">Optional</small>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="fare_amount" class="form-label">Fare Amount ($)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="fare_amount" name="fare_amount" 
                   value="<?php echo $bookingData['fare_amount'] ?: ''; ?>">
            <small class="text-muted">Optional</small>
        </div>
        <div class="col-md-6">
            <label for="booking_status" class="form-label">Status</label>
            <select class="form-select" id="booking_status" name="booking_status" required>
                <?php
                $statusOptions = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
                foreach ($statusOptions as $status) {
                    $selected = ($status == $bookingData['booking_status']) ? 'selected' : '';
                    $statusLabel = ucfirst(str_replace('_', ' ', $status));
                    echo "<option value=\"{$status}\" {$selected}>{$statusLabel}</option>";
                }
                ?>
            </select>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="special_instructions" class="form-label">Special Instructions</label>
        <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"><?php echo htmlspecialchars($bookingData['special_instructions'] ?: ''); ?></textarea>
    </div>
</form>

<script>
    // Initialize Google Places Autocomplete for address fields
    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        const pickupInput = document.getElementById('pickup_location');
        const dropoffInput = document.getElementById('dropoff_location');
        
        if (pickupInput && dropoffInput) {
            const pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput);
            const dropoffAutocomplete = new google.maps.places.Autocomplete(dropoffInput);
            
            // Calculate distance when both fields have values
            google.maps.event.addListener(pickupAutocomplete, 'place_changed', function() {
                calculateDistance();
            });
            
            google.maps.event.addListener(dropoffAutocomplete, 'place_changed', function() {
                calculateDistance();
            });
        }
    }
    
    function calculateDistance() {
        const pickup = document.getElementById('pickup_location').value;
        const dropoff = document.getElementById('dropoff_location').value;
        
        if (pickup && dropoff) {
            const directionsService = new google.maps.DirectionsService();
            const request = {
                origin: pickup,
                destination: dropoff,
                travelMode: google.maps.TravelMode.DRIVING
            };
            
            directionsService.route(request, function(result, status) {
                if (status == google.maps.DirectionsStatus.OK) {
                    // Get distance in meters and convert to kilometers
                    const distanceInMeters = result.routes[0].legs[0].distance.value;
                    const distanceInKm = (distanceInMeters / 1000).toFixed(2);
                    
                    // Get duration in seconds and convert to minutes
                    const durationInSeconds = result.routes[0].legs[0].duration.value;
                    const durationInMinutes = Math.round(durationInSeconds / 60);
                    
                    // Update the form fields
                    document.getElementById('distance_km').value = distanceInKm;
                    document.getElementById('duration_minutes').value = durationInMinutes;
                    
                    // Calculate estimated fare (example: base fare + distance rate)
                    const baseFare = 5;
                    const distanceRate = 1.5; // $1.50 per km
                    const estimatedFare = (baseFare + (distanceInKm * distanceRate)).toFixed(2);
                    
                    document.getElementById('fare_amount').value = estimatedFare;
                }
            });
        }
    }
</script> 