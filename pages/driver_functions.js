function updateDriverList() {
    const driverList = $('.driver-list');
    driverList.empty();
    
    if (availableDrivers.length === 0) {
        driverList.html(`
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No drivers currently available.
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary simulate-drivers-btn">
                        <i class="fas fa-play-circle"></i> Simulate Drivers
                    </button>
                </div>
            </div>
        `);
        
        // Add click handler for the simulate button
        $('.simulate-drivers-btn').on('click', function() {
            simulateDriverLocations();
        });
        
        return;
    }
    
    // Add refresh and simulate buttons at the top
    driverList.append(`
        <div class="mb-3 d-flex justify-content-between">
            <button class="btn btn-sm btn-primary refresh-map-btn">
                <i class="fas fa-sync-alt"></i> Refresh Map
            </button>
            <button class="btn btn-sm btn-warning simulate-drivers-btn">
                <i class="fas fa-play-circle"></i> Simulate
            </button>
        </div>
    `);
    
    availableDrivers.forEach(driver => {
        // Determine status class
        const statusClass = driver.status === 'available' ? 'text-success' : 
                          (driver.status === 'busy' ? 'text-warning' : 'text-secondary');
        
        driverList.append(`
            <div class="card driver-card mb-2" data-driver-id="${driver.driver_id}" 
                 data-lat="${driver.latitude || '0'}" 
                 data-lng="${driver.longitude || '0'}">
                <div class="card-body py-2">
                    <h6 class="mb-0">
                        ${driver.firstname || 'Driver'} ${driver.lastname || '#' + driver.driver_id}
                        ${driver.simulated ? '<span class="badge bg-warning text-dark ms-1">Simulated</span>' : ''}
                    </h6>
                    <div class="small text-muted">
                        <i class="fas fa-phone me-1"></i> ${driver.phone || 'N/A'}
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-id-card me-1"></i> License: ${driver.license_number || 'N/A'}
                    </div>
                    <div class="small ${statusClass}">
                        <i class="fas fa-circle me-1"></i> ${driver.status || 'unknown'}
                    </div>
                    ${driver.last_updated ? `
                        <div class="small text-success">
                            <i class="fas fa-clock me-1"></i> Updated: ${new Date(driver.last_updated).toLocaleString()}
                        </div>
                    ` : ''}
                </div>
            </div>
        `);
    });
    
    // Reattach click handlers for the driver cards
    $('.driver-card').on('click', function() {
        const driverId = $(this).data('driver-id');
        const lat = parseFloat($(this).data('lat'));
        const lng = parseFloat($(this).data('lng'));
        
        // Center the map on this driver
        if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
            map.setCenter({ lat, lng });
            map.setZoom(14);
            
            // Highlight this driver marker
            if (driverMarkers[driverId]) {
                driverMarkers[driverId].setAnimation(google.maps.Animation.BOUNCE);
                
                // Stop animation after 1.5 seconds
                setTimeout(() => {
                    if (driverMarkers[driverId]) {
                        driverMarkers[driverId].setAnimation(null);
                    }
                }, 1500);
            }
            
            // Toggle the active class
            $('.driver-card').removeClass('active');
            $(this).addClass('active');
        }
    });
    
    // Add click handler for the refresh button
    $('.refresh-map-btn').on('click', function() {
        refreshMap();
    });
    
    // Add click handler for the simulate button
    $('.simulate-drivers-btn').on('click', function() {
        simulateDriverLocations();
    });
}

function setupEditBookingModal() {
    // Handle edit booking button click
    $(document).on('click', '.edit-booking-btn', function() {
        const bookingId = $(this).data('id');
        loadBookingEditForm(bookingId);
    });
    
    // Handle save button click
    $('#saveBookingBtn').on('click', function() {
        saveBookingEdit();
    });
}

function loadBookingEditForm(bookingId) {
    // Show loading spinner
    $('#editBookingContent').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading booking edit form...</p>
        </div>
    `);
    
    // Load the edit form via AJAX
    $.ajax({
        url: 'pages/booking/booking_edit_form.php',
        data: { booking_id: bookingId },
        method: 'GET',
        success: function(response) {
            $('#editBookingContent').html(response);
            
            // Initialize date/time pickers and address autocomplete if needed
            initializeFormElements();
        },
        error: function(xhr, status, error) {
            console.error('Error loading booking edit form:', xhr, status, error);
            $('#editBookingContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading the edit form. Please try again.
                    <div class="mt-2 small">Error details: ${error || 'Unknown error'}</div>
                    <div class="mt-2 small">Status: ${status}</div>
                    <div class="mt-2 small">Response: ${xhr.responseText || 'No response text'}</div>
                </div>
            `);
        }
    });
}
