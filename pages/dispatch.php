<?php
// Dispatch Management Page
// Include db.php if it's not already included
if (!function_exists('connectToCore2DB')) {
    require_once 'functions/db.php';
}

// Ensure auth is included
if (!function_exists('hasPermission')) {
    require_once 'functions/auth.php';
}

// Check auth and permissions
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Please log in to access this page.</div>';
    exit;
}

if (!hasPermission('manage_bookings')) {
    // For debugging permission issues
    if (function_exists('debugPermission')) {
        debugPermission('manage_bookings');
    }
    
    echo '<div class="alert alert-danger">You do not have permission to access the dispatch page.</div>';
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Management - CORE Movers</title>
    <style>
        #map {
            height: 600px;
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .driver-card, .customer-card {
            transition: all 0.2s ease;
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        
        .driver-card:hover, .customer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .driver-card.active {
            border-left-color: #3498db;
        }
        
        .customer-card.active {
            border-left-color: #2ecc71;
        }
        
        .status-badge {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 12px;
        }
        
        .driver-available {
            background-color: #e8f7ee;
            color: #28a745;
        }
        
        .driver-busy {
            background-color: #fff3cd;
            color: #ffc107;
        }
        
        .driver-offline {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .location-recent {
            color: #28a745;
        }
        
        .location-old {
            color: #ffc107;
        }
        
        .location-outdated {
            color: #dc3545;
        }
        
        .infowindow-content {
            padding: 8px;
        }
        
        .map-buttons {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .map-button {
            background-color: white;
            border: none;
            border-radius: 4px;
            padding: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }
        
        .map-button:hover {
            background-color: #f8f9fa;
        }
        
        .map-button i {
            font-size: 16px;
        }
        
        /* Animation for tracking pulses */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(52, 152, 219, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(52, 152, 219, 0);
            }
        }
        
        .driver-marker {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <main class="content">
        <div class="container-fluid px-4">
            <h1 class="mt-4">Dispatch Management</h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Dispatch Management</li>
            </ol>
            
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                This page allows you to track driver locations, view nearby customers, and manage bookings.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            
            <div class="row">
                <!-- Map Section -->
                <div class="col-lg-8 position-relative mb-4">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-map-marked-alt me-1"></i>
                                Live Map View
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary active" id="showAllBtn">
                                    <i class="fas fa-globe"></i> All
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="showDriversBtn">
                                    <i class="fas fa-car"></i> Drivers
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="showCustomersBtn">
                                    <i class="fas fa-user"></i> Customers
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="map"></div>
                            <div class="map-buttons">
                                <button class="map-button" id="refreshMapBtn" title="Refresh Map">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button class="map-button" id="centerMapBtn" title="Center Map">
                                    <i class="fas fa-crosshairs"></i>
                                </button>
                                <button class="map-button" id="findNearestBtn" title="Find Nearest Driver to Selected Customer">
                                    <i class="fas fa-project-diagram"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Drivers Panel -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-car me-1"></i>
                                Available Drivers (<span id="driverCount">0</span>)
                            </div>
                            <button class="btn btn-sm btn-outline-primary" id="refreshDriversBtn">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush drivers-list">
                                <div class="list-group-item text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Loading drivers...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customers Panel -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-users me-1"></i>
                                Nearby Customers (<span id="customerCount">0</span>)
                            </div>
                            <div class="input-group input-group-sm w-50">
                                <input type="number" class="form-control" id="searchRadiusInput" value="10" min="1" max="50">
                                <span class="input-group-text">km</span>
                                <button class="btn btn-sm btn-outline-primary" id="searchCustomersBtn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush customers-list">
                                <div class="list-group-item text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Loading customers...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions Row -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-bolt me-1"></i>
                            Quick Actions
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary" id="assignNearestDriverBtn" disabled>
                                            <i class="fas fa-user-check me-2"></i>
                                            Assign Nearest Driver to Selected Booking
                                        </button>
                                        <button class="btn btn-info" id="viewDriversWithVehiclesBtn">
                                            <i class="fas fa-truck me-2"></i>
                                            View Available Drivers with Vehicles
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" id="updateCustomerLocationBtn" disabled>
                                            <i class="fas fa-map-marker-alt me-2"></i>
                                            Update Selected Customer Location
                                        </button>
                                        <button class="btn btn-secondary" id="viewBookingsMapBtn">
                                            <i class="fas fa-route me-2"></i>
                                            View All Pending Bookings on Map
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript Libraries (jQuery, Bootstrap) are loaded in the main template -->
    
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCS7rhxuCiYKeXpraOxq-GJCrYTmPiSaMU&libraries=places&callback=initMap" async defer></script>
    
    <!-- Main JavaScript for Dispatch -->
    <script>
        // Global variables
        let map;
        let markers = {
            drivers: [],
            customers: []
        };
        let selectedDriver = null;
        let selectedCustomer = null;
        let selectedBooking = null;
        
        // Initialize the map
        function initMap() {
            // Create the map centered on Manila
            const defaultLocation = { lat: 14.5995, lng: 120.9842 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                center: defaultLocation,
                zoom: 12,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: false,
                styles: [
                    {
                        featureType: 'transit',
                        elementType: 'labels.icon',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });
            
            // Initialize data fetching
            loadDrivers();
            loadNearbyCustomers();
            
            // Set up event listeners
            setupEventListeners();
        }
        
        // Event listeners setup
        function setupEventListeners() {
            // Refresh buttons
            $('#refreshMapBtn').on('click', function() {
                refreshMap();
            });
            
            $('#refreshDriversBtn').on('click', function() {
                loadDrivers();
            });
            
            $('#searchCustomersBtn').on('click', function() {
                loadNearbyCustomers();
            });
            
            // Center map button
            $('#centerMapBtn').on('click', function() {
                map.setCenter({ lat: 14.5995, lng: 120.9842 });
                map.setZoom(12);
            });
            
            // Find nearest driver to selected customer
            $('#findNearestBtn').on('click', function() {
                if (selectedCustomer) {
                    findNearestDriver(selectedCustomer);
                } else {
                    Swal.fire({
                        title: 'No Customer Selected',
                        text: 'Please select a customer first',
                        icon: 'warning'
                    });
                }
            });
            
            // Quick action buttons
            $('#assignNearestDriverBtn').on('click', function() {
                if (selectedBooking) {
                    assignNearestDriverToBooking(selectedBooking.booking_id);
                }
            });
            
            $('#updateCustomerLocationBtn').on('click', function() {
                if (selectedCustomer) {
                    // Redirect to customer page with the location modal open
                    window.location.href = `index.php?page=customers&action=update_location&customer_id=${selectedCustomer.customer_id}`;
                }
            });
            
            // Map view filters
            $('#showAllBtn').on('click', function() {
                $('.btn-group .btn').removeClass('active');
                $(this).addClass('active');
                showAllMarkers();
            });
            
            $('#showDriversBtn').on('click', function() {
                $('.btn-group .btn').removeClass('active');
                $(this).addClass('active');
                showOnlyDriverMarkers();
            });
            
            $('#showCustomersBtn').on('click', function() {
                $('.btn-group .btn').removeClass('active');
                $(this).addClass('active');
                showOnlyCustomerMarkers();
            });
            
            // View drivers with vehicles button
            $('#viewDriversWithVehiclesBtn').on('click', function() {
                loadDriversWithVehicles();
            });
            
            // View bookings map button
            $('#viewBookingsMapBtn').on('click', function() {
                window.location.href = 'index.php?page=bookings';
            });
        }
        
        // Auto-refresh data every 60 seconds
        setInterval(function() {
            loadDrivers();
            // Don't auto-refresh customers as they don't move as often
        }, 60000);
        
        // Load drivers
        function loadDrivers() {
            // Show loading state
            $('.drivers-list').html(`
                <div class="list-group-item text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Loading drivers...</span>
                </div>
            `);
            
            // Clear existing driver markers
            clearMarkers('drivers');
            
            // Make the API call
            $.ajax({
                url: 'api/drivers/get_nearest_drivers.php',
                data: {
                    latitude: map.getCenter().lat(),
                    longitude: map.getCenter().lng(),
                    limit: 20,
                    max_distance: 50
                },
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const drivers = response.data;
                        
                        // Update the count
                        $('#driverCount').text(drivers.length);
                        
                        if (drivers.length === 0) {
                            $('.drivers-list').html(`
                                <div class="list-group-item text-center py-3">
                                    <div class="text-muted">No nearby drivers found</div>
                                </div>
                            `);
                        } else {
                            // Clear the list
                            $('.drivers-list').empty();
                            
                            // Add each driver to the list and the map
                            drivers.forEach(driver => {
                                // Add to the list
                                addDriverToList(driver);
                                
                                // Add to the map if it has valid coordinates
                                if (driver.latitude && driver.longitude && 
                                    driver.latitude !== 0 && driver.longitude !== 0) {
                                    addDriverMarker(driver);
                                }
                            });
                            
                            // Add click event to driver list items
                            $('.driver-card').on('click', function() {
                                const driverId = $(this).data('driver-id');
                                selectDriver(driverId);
                            });
                        }
                    } else {
                        // Show error
                        $('.drivers-list').html(`
                            <div class="list-group-item text-center py-3">
                                <div class="text-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    ${response.message || 'Failed to load drivers'}
                                </div>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error
                    $('.drivers-list').html(`
                        <div class="list-group-item text-center py-3">
                            <div class="text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error loading drivers: ${error}
                            </div>
                        </div>
                    `);
                }
            });
        }
        
        // Add driver to the list
        function addDriverToList(driver) {
            // Format the status badge
            let statusBadgeClass = '';
            
            switch (driver.status) {
                case 'available':
                    statusBadgeClass = 'driver-available';
                    break;
                case 'busy':
                    statusBadgeClass = 'driver-busy';
                    break;
                default:
                    statusBadgeClass = 'driver-offline';
            }
            
            // Format the location age
            let locationAgeText = '';
            let locationAgeClass = '';
            
            if (driver.location_age_seconds !== null) {
                if (driver.location_age_seconds < 300) { // Less than 5 minutes
                    locationAgeText = 'Updated just now';
                    locationAgeClass = 'location-recent';
                } else if (driver.location_age_seconds < 3600) { // Less than 1 hour
                    const minutes = Math.floor(driver.location_age_seconds / 60);
                    locationAgeText = `Updated ${minutes} min ago`;
                    locationAgeClass = 'location-old';
                } else {
                    const hours = Math.floor(driver.location_age_seconds / 3600);
                    locationAgeText = `Updated ${hours} hr ago`;
                    locationAgeClass = 'location-outdated';
                }
            } else {
                locationAgeText = 'Location unknown';
                locationAgeClass = 'location-outdated';
            }
            
            // Create the list item
            $('.drivers-list').append(`
                <div class="list-group-item driver-card" data-driver-id="${driver.driver_id}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold">${driver.firstname} ${driver.lastname}</div>
                            <div class="small text-muted">
                                <i class="fas fa-phone me-1"></i> ${driver.phone || 'N/A'}
                            </div>
                            <div class="small ${locationAgeClass}">
                                <i class="fas fa-clock me-1"></i> ${locationAgeText}
                            </div>
                        </div>
                        <div>
                            <span class="badge status-badge ${statusBadgeClass}">
                                ${driver.status ? driver.status.charAt(0).toUpperCase() + driver.status.slice(1) : 'Unknown'}
                            </span>
                        </div>
                    </div>
                    ${driver.vehicle_model ? `
                    <div class="mt-2 small text-muted">
                        <div><i class="fas fa-car me-1"></i> ${driver.vehicle_model} (${driver.plate_number})</div>
                    </div>
                    ` : ''}
                </div>
            `);
        }
        
        // Add driver marker to the map
        function addDriverMarker(driver) {
            const position = {
                lat: parseFloat(driver.latitude),
                lng: parseFloat(driver.longitude)
            };
            
            // Create the info window content
            const contentString = `
                <div class="infowindow-content">
                    <h6>${driver.firstname} ${driver.lastname}</h6>
                    <div class="mb-2">
                        <span class="badge status-badge ${driver.status === 'available' ? 'driver-available' : driver.status === 'busy' ? 'driver-busy' : 'driver-offline'}">
                            ${driver.status ? driver.status.charAt(0).toUpperCase() + driver.status.slice(1) : 'Unknown'}
                        </span>
                    </div>
                    <div class="small mb-1"><i class="fas fa-phone me-1"></i> ${driver.phone || 'N/A'}</div>
                    ${driver.vehicle_model ? `
                        <div class="small mb-1"><i class="fas fa-car me-1"></i> ${driver.vehicle_model}</div>
                        <div class="small mb-1"><i class="fas fa-hashtag me-1"></i> ${driver.plate_number}</div>
                    ` : ''}
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary select-driver-btn" data-driver-id="${driver.driver_id}">
                            Select Driver
                        </button>
                    </div>
                </div>
            `;
            
            // Create info window
            const infowindow = new google.maps.InfoWindow({
                content: contentString,
                maxWidth: 300
            });
            
            // Create marker
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: `${driver.firstname} ${driver.lastname}`,
                icon: {
                    url: 'assets/img/car-marker.svg',
                    scaledSize: new google.maps.Size(32, 32)
                },
                animation: google.maps.Animation.DROP,
                optimized: false, // Required for CSS animations in some browsers
                zIndex: 10
            });
            
            // Add class for animation
            marker.addListener('load', function() {
                const element = this.getElement();
                if (element) {
                    element.classList.add('driver-marker');
                }
            });
            
            // Add click event
            marker.addListener('click', function() {
                // Close all other info windows
                markers.drivers.forEach(m => {
                    if (m.infowindow && m.infowindow !== infowindow) {
                        m.infowindow.close();
                    }
                });
                
                // Open this info window
                infowindow.open(map, marker);
                
                // Select this driver
                selectDriver(driver.driver_id);
            });
            
            // Add event listener for the select button in the info window
            google.maps.event.addListener(infowindow, 'domready', function() {
                $('.select-driver-btn').on('click', function() {
                    const driverId = $(this).data('driver-id');
                    selectDriver(driverId);
                    infowindow.close();
                });
            });
            
            // Store the info window with the marker
            marker.infowindow = infowindow;
            
            // Store driver data with the marker
            marker.driver = driver;
            
            // Add to the markers array
            markers.drivers.push(marker);
            
            return marker;
        }
        
        // Select a driver
        function selectDriver(driverId) {
            // Find the driver in the markers array
            const markerIndex = markers.drivers.findIndex(marker => marker.driver.driver_id === driverId);
            
            if (markerIndex !== -1) {
                // Get the marker and driver data
                const marker = markers.drivers[markerIndex];
                selectedDriver = marker.driver;
                
                // Update UI
                $('.driver-card').removeClass('active');
                $(`.driver-card[data-driver-id="${driverId}"]`).addClass('active');
                
                // Scroll to the selected driver in the list
                const driverCard = $(`.driver-card[data-driver-id="${driverId}"]`);
                if (driverCard.length) {
                    const driversList = $('.drivers-list');
                    driversList.animate({
                        scrollTop: driverCard.position().top + driversList.scrollTop() - driversList.height()/2 + driverCard.height()/2
                    }, 500);
                }
                
                // Pan to the marker
                map.panTo(marker.getPosition());
                
                // Recenter map at higher zoom level
                map.setZoom(15);
                
                // Bounce the marker briefly
                marker.setAnimation(google.maps.Animation.BOUNCE);
                setTimeout(() => {
                    marker.setAnimation(null);
                }, 1500);
                
                // Check if we should enable the assign button
                updateActionButtonsState();
                
                // If we have a selected customer, show the route
                if (selectedCustomer) {
                    showRoute(selectedDriver, selectedCustomer);
                }
            }
        }
        
        // Clear markers
        function clearMarkers(type) {
            if (type === 'drivers' || type === 'all') {
                // Remove each marker from the map
                markers.drivers.forEach(marker => {
                    marker.setMap(null);
                    if (marker.infowindow) {
                        marker.infowindow.close();
                    }
                });
                
                // Clear the array
                if (type === 'all') {
                    markers.drivers = [];
                } else {
                    // Keep the array reference but empty it
                    markers.drivers.length = 0;
                }
            }
            
            if (type === 'customers' || type === 'all') {
                // Remove each marker from the map
                markers.customers.forEach(marker => {
                    marker.setMap(null);
                    if (marker.infowindow) {
                        marker.infowindow.close();
                    }
                });
                
                // Clear the array
                if (type === 'all') {
                    markers.customers = [];
                } else {
                    // Keep the array reference but empty it
                    markers.customers.length = 0;
                }
            }
        }
        
        // Load nearby customers
        function loadNearbyCustomers() {
            // Show loading state
            $('.customers-list').html(`
                <div class="list-group-item text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Loading customers...</span>
                </div>
            `);
            
            // Clear existing customer markers
            clearMarkers('customers');
            
            // Get search radius
            const radius = parseInt($('#searchRadiusInput').val()) || 10;
            
            // Make the API call
            $.ajax({
                url: 'api/customers/get_nearby_customers.php',
                data: {
                    latitude: map.getCenter().lat(),
                    longitude: map.getCenter().lng(),
                    limit: 20,
                    max_distance: radius
                },
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const customers = response.data;
                        
                        // Update the count
                        $('#customerCount').text(customers.length);
                        
                        if (customers.length === 0) {
                            $('.customers-list').html(`
                                <div class="list-group-item text-center py-3">
                                    <div class="text-muted">No nearby customers found</div>
                                </div>
                            `);
                        } else {
                            // Clear the list
                            $('.customers-list').empty();
                            
                            // Add each customer to the list and the map
                            customers.forEach(customer => {
                                // Add to the list
                                addCustomerToList(customer);
                                
                                // Add to the map if it has valid coordinates
                                if (customer.latitude && customer.longitude && 
                                    customer.latitude !== 0 && customer.longitude !== 0) {
                                    addCustomerMarker(customer);
                                }
                            });
                            
                            // Add click event to customer list items
                            $('.customer-card').on('click', function() {
                                const customerId = $(this).data('customer-id');
                                selectCustomer(customerId);
                            });
                        }
                    } else {
                        // Show error
                        $('.customers-list').html(`
                            <div class="list-group-item text-center py-3">
                                <div class="text-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    ${response.message || 'Failed to load customers'}
                                </div>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error
                    $('.customers-list').html(`
                        <div class="list-group-item text-center py-3">
                            <div class="text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error loading customers: ${error}
                            </div>
                        </div>
                    `);
                }
            });
        }
        
        // Add customer to the list
        function addCustomerToList(customer) {
            // Format the location age
            let locationAgeText = '';
            let locationAgeClass = '';
            
            if (customer.location_age_seconds !== null) {
                if (customer.location_age_seconds < 300) { // Less than 5 minutes
                    locationAgeText = 'Updated just now';
                    locationAgeClass = 'location-recent';
                } else if (customer.location_age_seconds < 3600) { // Less than 1 hour
                    const minutes = Math.floor(customer.location_age_seconds / 60);
                    locationAgeText = `Updated ${minutes} min ago`;
                    locationAgeClass = 'location-old';
                } else {
                    const hours = Math.floor(customer.location_age_seconds / 3600);
                    locationAgeText = `Updated ${hours} hr ago`;
                    locationAgeClass = 'location-outdated';
                }
            } else {
                locationAgeText = 'Location unknown';
                locationAgeClass = 'location-outdated';
            }
            
            // Create the list item
            $('.customers-list').append(`
                <div class="list-group-item customer-card" data-customer-id="${customer.customer_id}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold">${customer.firstname} ${customer.lastname}</div>
                            <div class="small text-muted">
                                <i class="fas fa-phone me-1"></i> ${customer.phone || 'N/A'}
                            </div>
                            <div class="small ${locationAgeClass}">
                                <i class="fas fa-clock me-1"></i> ${locationAgeText}
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-secondary">
                                ${customer.distance_km.toFixed(1)} km
                            </span>
                        </div>
                    </div>
                    <div class="mt-2 small text-muted">
                        <i class="fas fa-map-marker-alt me-1"></i> ${customer.address || 'No address'}
                    </div>
                    ${customer.pending_booking ? `
                    <div class="mt-2 alert alert-info py-1 px-2 mb-0">
                        <small>
                            <i class="fas fa-calendar-check me-1"></i> Has pending booking
                        </small>
                    </div>
                    ` : ''}
                </div>
            `);
        }
        
        // Add customer marker to the map
        function addCustomerMarker(customer) {
            const position = {
                lat: parseFloat(customer.latitude),
                lng: parseFloat(customer.longitude)
            };
            
            // Create the info window content
            const contentString = `
                <div class="infowindow-content">
                    <h6>${customer.firstname} ${customer.lastname}</h6>
                    <div class="small mb-1"><i class="fas fa-phone me-1"></i> ${customer.phone || 'N/A'}</div>
                    <div class="small mb-1"><i class="fas fa-envelope me-1"></i> ${customer.email || 'N/A'}</div>
                    <div class="small mb-1"><i class="fas fa-map-marker-alt me-1"></i> ${customer.address || 'No address'}</div>
                    ${customer.pending_booking ? `
                        <div class="alert alert-info py-1 px-2 mt-2 mb-1">
                            <small>
                                <i class="fas fa-calendar-check me-1"></i> Has pending booking
                            </small>
                        </div>
                    ` : ''}
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary select-customer-btn" data-customer-id="${customer.customer_id}">
                            Select Customer
                        </button>
                    </div>
                </div>
            `;
            
            // Create info window
            const infowindow = new google.maps.InfoWindow({
                content: contentString,
                maxWidth: 300
            });
            
            // Create marker
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: `${customer.firstname} ${customer.lastname}`,
                icon: {
                    url: 'assets/img/customer-marker.svg',
                    scaledSize: new google.maps.Size(24, 24)
                },
                animation: google.maps.Animation.DROP
            });
            
            // Add click event
            marker.addListener('click', function() {
                // Close all other info windows
                markers.customers.forEach(m => {
                    if (m.infowindow && m.infowindow !== infowindow) {
                        m.infowindow.close();
                    }
                });
                
                // Open this info window
                infowindow.open(map, marker);
                
                // Select this customer
                selectCustomer(customer.customer_id);
            });
            
            // Add event listener for the select button in the info window
            google.maps.event.addListener(infowindow, 'domready', function() {
                $('.select-customer-btn').on('click', function() {
                    const customerId = $(this).data('customer-id');
                    selectCustomer(customerId);
                    infowindow.close();
                });
            });
            
            // Store the info window with the marker
            marker.infowindow = infowindow;
            
            // Store customer data with the marker
            marker.customer = customer;
            
            // Add to the markers array
            markers.customers.push(marker);
            
            return marker;
        }
        
        // Select a customer
        function selectCustomer(customerId) {
            // Find the customer in the markers array
            const markerIndex = markers.customers.findIndex(marker => marker.customer.customer_id === customerId);
            
            if (markerIndex !== -1) {
                // Get the marker and customer data
                const marker = markers.customers[markerIndex];
                selectedCustomer = marker.customer;
                
                // Update UI
                $('.customer-card').removeClass('active');
                $(`.customer-card[data-customer-id="${customerId}"]`).addClass('active');
                
                // Scroll to the selected customer in the list
                const customerCard = $(`.customer-card[data-customer-id="${customerId}"]`);
                if (customerCard.length) {
                    const customersList = $('.customers-list');
                    customersList.animate({
                        scrollTop: customerCard.position().top + customersList.scrollTop() - customersList.height()/2 + customerCard.height()/2
                    }, 500);
                }
                
                // Pan to the marker
                map.panTo(marker.getPosition());
                
                // Recenter map at higher zoom level
                map.setZoom(15);
                
                // Bounce the marker briefly
                marker.setAnimation(google.maps.Animation.BOUNCE);
                setTimeout(() => {
                    marker.setAnimation(null);
                }, 1500);
                
                // If this customer has a pending booking, store it
                if (selectedCustomer.pending_booking) {
                    selectedBooking = selectedCustomer.pending_booking;
                } else {
                    selectedBooking = null;
                }
                
                // Update action buttons state
                updateActionButtonsState();
                
                // If we have a selected driver, show the route
                if (selectedDriver) {
                    showRoute(selectedDriver, selectedCustomer);
                }
            }
        }
        
        // Show route between driver and customer
        function showRoute(driver, customer) {
            // Remove any existing directions
            if (window.directionsRenderer) {
                window.directionsRenderer.setMap(null);
            }
            
            // Create directions service and renderer
            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({
                suppressMarkers: true,
                polylineOptions: {
                    strokeColor: '#3498db',
                    strokeWeight: 5,
                    strokeOpacity: 0.7
                }
            });
            
            // Set the map for the renderer
            directionsRenderer.setMap(map);
            
            // Store renderer for later removal
            window.directionsRenderer = directionsRenderer;
            
            // Calculate route
            directionsService.route(
                {
                    origin: {
                        lat: parseFloat(driver.latitude),
                        lng: parseFloat(driver.longitude)
                    },
                    destination: {
                        lat: parseFloat(customer.latitude),
                        lng: parseFloat(customer.longitude)
                    },
                    travelMode: google.maps.TravelMode.DRIVING
                },
                function(response, status) {
                    if (status === 'OK') {
                        directionsRenderer.setDirections(response);
                        
                        // Get route details
                        const route = response.routes[0];
                        const leg = route.legs[0];
                        
                        // Show route info
                        Swal.fire({
                            title: 'Route Information',
                            html: `
                                <div class="text-start">
                                    <p><strong>Distance:</strong> ${leg.distance.text}</p>
                                    <p><strong>Duration:</strong> ${leg.duration.text}</p>
                                    <p><strong>From:</strong> ${driver.firstname} ${driver.lastname}</p>
                                    <p><strong>To:</strong> ${customer.firstname} ${customer.lastname}</p>
                                </div>
                            `,
                            icon: 'info'
                        });
                    } else {
                        console.error('Directions request failed due to ' + status);
                        
                        // Show error message
                        Swal.fire({
                            title: 'Route Calculation Failed',
                            text: 'Could not calculate the route between the driver and customer.',
                            icon: 'error'
                        });
                    }
                }
            );
        }
        
        // Find nearest driver to a customer
        function findNearestDriver(customer) {
            // Show loading state
            Swal.fire({
                title: 'Finding Nearest Driver',
                text: 'Searching for the nearest available driver...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make the API call
            $.ajax({
                url: 'api/drivers/get_nearest_drivers.php',
                data: {
                    latitude: customer.latitude,
                    longitude: customer.longitude,
                    limit: 1,
                    max_distance: 50
                },
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        const nearestDriver = response.data[0];
                        
                        // Look for this driver in our existing drivers
                        const driverIndex = markers.drivers.findIndex(
                            marker => marker.driver.driver_id === nearestDriver.driver_id
                        );
                        
                        if (driverIndex !== -1) {
                            // We have this driver loaded, select it
                            selectDriver(nearestDriver.driver_id);
                            
                            // Show the route
                            showRoute(nearestDriver, customer);
                        } else {
                            // We don't have this driver loaded yet, add it
                            addDriverMarker(nearestDriver);
                            addDriverToList(nearestDriver);
                            
                            // Select it
                            selectDriver(nearestDriver.driver_id);
                            
                            // Show the route
                            showRoute(nearestDriver, customer);
                        }
                        
                        // Show success message
                        Swal.fire({
                            title: 'Nearest Driver Found',
                            html: `
                                <p>Driver: ${nearestDriver.firstname} ${nearestDriver.lastname}</p>
                                <p>Distance: ${nearestDriver.distance_km.toFixed(1)} km</p>
                                <p>ETA: ${nearestDriver.eta_minutes} minutes</p>
                            `,
                            icon: 'success'
                        });
                    } else {
                        // No driver found
                        Swal.fire({
                            title: 'No Driver Found',
                            text: 'Could not find any available driver near this customer.',
                            icon: 'warning'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Show error
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to find nearest driver: ' + error,
                        icon: 'error'
                    });
                }
            });
        }
        
        // Assign nearest driver to a booking
        function assignNearestDriverToBooking(bookingId) {
            // Show loading state
            Swal.fire({
                title: 'Assigning Driver',
                text: 'Finding and assigning the nearest available driver to this booking...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make the API call
            $.ajax({
                url: 'api/bookings/assign_nearest_driver.php',
                method: 'POST',
                data: {
                    booking_id: bookingId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        Swal.fire({
                            title: 'Driver Assigned',
                            html: `
                                <p>Successfully assigned driver to booking.</p>
                                <p><strong>Driver:</strong> ${response.driver.name}</p>
                                <p><strong>Distance:</strong> ${response.driver.distance_km.toFixed(1)} km</p>
                                <p><strong>ETA:</strong> ${response.driver.eta_minutes} minutes</p>
                                <p><strong>Vehicle:</strong> ${response.vehicle.model} (${response.vehicle.plate_number})</p>
                            `,
                            icon: 'success'
                        }).then(() => {
                            // Reload the drivers to reflect the updated status
                            loadDrivers();
                        });
                    } else {
                        // Show error
                        Swal.fire({
                            title: 'Assignment Failed',
                            text: response.message || 'Failed to assign driver to booking',
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Show error
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to assign driver: ' + error,
                        icon: 'error'
                    });
                }
            });
        }
        
        // Load available drivers with vehicles
        function loadDriversWithVehicles() {
            // Show loading state
            Swal.fire({
                title: 'Loading Drivers',
                text: 'Fetching available drivers with vehicles...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make the API call
            $.ajax({
                url: 'api/drivers/get_available_with_vehicles.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const drivers = response.data;
                        
                        if (drivers.length === 0) {
                            // No drivers found
                            Swal.fire({
                                title: 'No Drivers Available',
                                text: 'There are no drivers with vehicles currently available.',
                                icon: 'info'
                            });
                        } else {
                            // Create HTML for the drivers list
                            let driversHtml = `
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Driver</th>
                                                <th>Status</th>
                                                <th>Vehicle</th>
                                                <th>Phone</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            `;
                            
                            drivers.forEach(driver => {
                                let statusClass = '';
                                
                                switch (driver.status) {
                                    case 'available':
                                        statusClass = 'text-success';
                                        break;
                                    case 'busy':
                                        statusClass = 'text-warning';
                                        break;
                                    default:
                                        statusClass = 'text-secondary';
                                }
                                
                                driversHtml += `
                                    <tr>
                                        <td>${driver.firstname} ${driver.lastname}</td>
                                        <td><span class="${statusClass}">${driver.status}</span></td>
                                        <td>${driver.vehicle_model} (${driver.plate_number})</td>
                                        <td>${driver.phone}</td>
                                    </tr>
                                `;
                            });
                            
                            driversHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            `;
                            
                            // Show the drivers list
                            Swal.fire({
                                title: 'Available Drivers with Vehicles',
                                html: driversHtml,
                                icon: 'info',
                                confirmButtonText: 'Close',
                                width: '800px'
                            });
                        }
                    } else {
                        // Show error
                        Swal.fire({
                            title: 'Error',
                            text: response.message || 'Failed to load drivers with vehicles',
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Show error
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load drivers: ' + error,
                        icon: 'error'
                    });
                }
            });
        }
        
        // Show all markers
        function showAllMarkers() {
            markers.drivers.forEach(marker => {
                marker.setMap(map);
            });
            
            markers.customers.forEach(marker => {
                marker.setMap(map);
            });
        }
        
        // Show only driver markers
        function showOnlyDriverMarkers() {
            markers.drivers.forEach(marker => {
                marker.setMap(map);
            });
            
            markers.customers.forEach(marker => {
                marker.setMap(null);
            });
        }
        
        // Show only customer markers
        function showOnlyCustomerMarkers() {
            markers.drivers.forEach(marker => {
                marker.setMap(null);
            });
            
            markers.customers.forEach(marker => {
                marker.setMap(map);
            });
        }
        
        // Refresh the map (reload all data)
        function refreshMap() {
            // Clear all markers
            clearMarkers('all');
            
            // Reload data
            loadDrivers();
            loadNearbyCustomers();
            
            // Reset selection
            selectedDriver = null;
            selectedCustomer = null;
            selectedBooking = null;
            
            // Remove any directions
            if (window.directionsRenderer) {
                window.directionsRenderer.setMap(null);
            }
            
            // Reset UI
            $('.driver-card').removeClass('active');
            $('.customer-card').removeClass('active');
            
            // Reset action buttons
            updateActionButtonsState();
            
            // Reset map position and zoom
            map.setCenter({ lat: 14.5995, lng: 120.9842 });
            map.setZoom(12);
        }
        
        // Update the state of action buttons
        function updateActionButtonsState() {
            // Update the assign driver button
            if (selectedBooking) {
                $('#assignNearestDriverBtn').prop('disabled', false);
                $('#assignNearestDriverBtn').html(`
                    <i class="fas fa-user-check me-2"></i>
                    Assign Nearest Driver to Booking #${selectedBooking.booking_id}
                `);
            } else {
                $('#assignNearestDriverBtn').prop('disabled', true);
                $('#assignNearestDriverBtn').html(`
                    <i class="fas fa-user-check me-2"></i>
                    Assign Nearest Driver to Selected Booking
                `);
            }
            
            // Update the customer location button
            if (selectedCustomer) {
                $('#updateCustomerLocationBtn').prop('disabled', false);
                $('#updateCustomerLocationBtn').html(`
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Update Location for ${selectedCustomer.firstname} ${selectedCustomer.lastname}
                `);
            } else {
                $('#updateCustomerLocationBtn').prop('disabled', true);
                $('#updateCustomerLocationBtn').html(`
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Update Selected Customer Location
                `);
            }
        }
    </script>
</body>
</html> 