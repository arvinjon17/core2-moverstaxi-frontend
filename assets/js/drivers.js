/**
 * Driver Management JavaScript
 * Handles client-side functionality for the driver management module
 */
$(document).ready(function() {
    // Fix DataTables initialization
    try {
        // Initialize DataTable for drivers - wrapped in try-catch
        if($.fn.DataTable) {
    $('#driversTable').DataTable({
        responsive: true,
        language: {
            search: "Search drivers:",
            lengthMenu: "Show _MENU_ drivers per page",
            info: "Showing _START_ to _END_ of _TOTAL_ drivers",
            emptyTable: "No drivers available"
        },
        columnDefs: [
            { orderable: false, targets: 7 } // Disable sorting on actions column
        ]
    });
            console.log("DataTable initialized successfully");
        } else {
            console.error("DataTable plugin not available. jQuery.fn.DataTable =", typeof $.fn.DataTable);
        }
    } catch(e) {
        console.error("Error initializing DataTable:", e);
    }
    
    let currentDriverId = 0;
    
    // Get the proper URL by checking the current path
    function getBasePath() {
        const path = window.location.pathname;
        
        // Check for specific paths that need adjustments
        if (path.includes('/pages/')) {
            return '../';
        } else if (path.includes('/admin/')) {
            return '../';
        } else if (path.endsWith('/') || path === '') {
            return '';
        } else if (!path.includes('.php')) {
            // If we're in a directory without trailing slash
            return '';
        }
        
        console.log('Path detected:', path, 'Using base path: ""');
        return '';
    }
    
    // Load available vehicles for dropdowns
    function loadAvailableVehicles(driverId, targetSelectId) {
        const basePath = getBasePath();
        
        // Show a loading indicator
        const select = $('#' + targetSelectId);
        select.prop('disabled', true);
        select.html('<option>Loading vehicles...</option>');
        
        console.log("Loading vehicles from:", basePath + 'ajax/driver_actions.php', "for driver ID:", driverId);
        
        $.ajax({
            url: basePath + 'ajax/driver_actions.php',
            type: 'GET',
            data: {
                action: 'get_vehicles',
                driver_id: driverId || 0
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                console.log("Vehicle loading response:", response);
                // Reset the select
                select.empty();
                select.append('<option value="">-- Select Vehicle --</option>');
                
                if (response.success) {
                    $.each(response.data, function(index, vehicle) {
                        const isAssigned = vehicle.assigned_driver_id !== null;
                        const assignedInfo = isAssigned ? 
                            (vehicle.current_driver_id == driverId ? ' (Currently Assigned)' :
                             ` (Assigned to ${vehicle.current_driver_name || 'Another Driver'})`) : '';
                             
                        const option = $('<option></option>')
                            .val(vehicle.vehicle_id)
                            .text(`${vehicle.plate_number} - ${vehicle.model} ${vehicle.year}${assignedInfo}`);
                        
                        // If this vehicle is assigned to this driver, select it
                        if (isAssigned && vehicle.current_driver_id == driverId) {
                            option.attr('selected', 'selected');
                        }
                        
                        // If the vehicle is assigned to another driver, add a class and disable it
                        if (isAssigned && vehicle.current_driver_id != driverId) {
                            option.addClass('text-warning');
                            option.prop('disabled', true);
                        }
                        
                        select.append(option);
                    });
                } else {
                    console.error('Failed to load vehicles:', response.message);
                    select.append('<option value="" disabled>Failed to load vehicles</option>');
                    
                    // Display more user-friendly error message
                    let errorMsg = response.message || 'An error occurred while loading available vehicles';
                    // Make database errors more user-friendly
                    if (errorMsg.includes('SELECT command denied')) {
                        errorMsg = 'Database permission error. Please contact the system administrator.';
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Load Vehicles',
                        text: errorMsg,
                        footer: '<a href="javascript:void(0)" onclick="location.reload()">Refresh the page</a>'
                    });
                }
                
                // Re-enable the select
                select.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response Text:', xhr.responseText);
                
                select.empty();
                select.append('<option value="">-- Error Loading Vehicles --</option>');
                select.prop('disabled', false);
                
                // Try to parse the response to get more detailed error message
                let errorMsg = 'Failed to communicate with the server. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMsg = response.message;
                    }
                } catch (e) {
                    console.error('Could not parse error response:', e);
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: errorMsg,
                    footer: '<a href="javascript:void(0)" onclick="location.reload()">Refresh the page</a>'
                });
            }
        });
    }
    
    // Add Driver - Show Modal
    $('[data-bs-target="#addDriverModal"]').on('click', function() {
        // Reset form and clear any previous preview
        $('#addDriverForm').trigger('reset');
        $('#add_image_preview').empty();
    });
    
    // Add Driver - Profile Image Preview
    $('#add_profile_image').on('change', function() {
        const file = this.files[0];
        if (file) {
            // Check file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Profile picture must be less than 2MB.'
                });
                this.value = ''; // Clear the file input
                $('#add_image_preview').empty();
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#add_image_preview').html(`
                    <img src="${e.target.result}" alt="Profile Preview" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                `);
            }
            reader.readAsDataURL(file);
        } else {
            $('#add_image_preview').empty();
        }
    });
    
    // Add Driver - Save New Driver
    $('#saveNewDriverBtn').on('click', function() {
        // Validate form
        if (!$('#addDriverForm')[0].checkValidity()) {
            $('#addDriverForm')[0].reportValidity();
            return;
        }
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Add New Driver',
            text: 'Are you sure you want to add this driver?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, add driver!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create FormData object to handle file uploads
                const formData = new FormData($('#addDriverForm')[0]);
                formData.append('action', 'add_driver');
                
                // Submit form via AJAX
                const basePath = getBasePath();
                
                $.ajax({
                    url: basePath + 'ajax/driver_actions.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    beforeSend: function() {
                        $('#saveNewDriverBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
                        
                        // Log request details for debugging
                        console.log('Add driver - Sending request with data:', {
                            firstname: formData.get('firstname'),
                            lastname: formData.get('lastname'),
                            email: formData.get('email'),
                            license_number: formData.get('license_number'),
                            vehicle_id: formData.get('vehicle_id')
                        });
                    },
                    success: function(response) {
                        console.log('Add driver - AJAX success response:', response);
                        
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Driver Added',
                                text: 'The driver has been added successfully!',
                                confirmButtonText: 'Great!'
                            }).then(() => {
                                // Close modal and reload page
                                $('#addDriverModal').modal('hide');
                                location.reload();
                            });
                            
                            // If a temporary password was generated, show it to the admin
                            if (response.data && response.data.temporary_password) {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Driver Account Created',
                                    html: `
                                        <p>Please share the following login credentials with the driver:</p>
                                        <div class="alert alert-info">
                                            <strong>Email:</strong> ${$('#add_email').val()}<br>
                                            <strong>Temporary Password:</strong> ${response.data.temporary_password}
                                        </div>
                                        <p class="text-warning">Make sure to save this information as it won't be shown again!</p>
                                    `,
                                    confirmButtonText: 'I\'ve Saved It'
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to add driver'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Add driver - AJAX Error:', {
                            status: status,
                            error: error,
                            xhr: xhr,
                            responseText: xhr.responseText
                        });
                        
                        let errorMessage = 'An error occurred while adding the driver. Please try again.';
                        let technicalDetails = '';
                        
                        // Try to parse the response if it's JSON
                        try {
                            if (xhr.responseText) {
                                const jsonResponse = JSON.parse(xhr.responseText);
                                if (jsonResponse.message) {
                                    errorMessage = jsonResponse.message;
                                }
                                technicalDetails = JSON.stringify(jsonResponse, null, 2);
                            }
                        } catch (e) {
                            technicalDetails = xhr.responseText || 'No response received';
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            html: `
                                <p>${errorMessage}</p>
                                <details>
                                    <summary>Technical Details (for IT support)</summary>
                                    <pre class="text-start text-wrap" style="max-height: 200px; overflow-y: auto;">${technicalDetails}</pre>
                                </details>
                            `
                        });
                    },
                    complete: function() {
                        $('#saveNewDriverBtn').prop('disabled', false).text('Add Driver');
                    }
                });
            }
        });
    });
    
    // Function to view driver details - separated to allow for retry
    function viewDriver(driverId, driverName) {
        // Update modal title with driver name
        $('#viewDriverModalLabel').text('Driver Details - ' + driverName);
        
        // Clear previous content and show loading spinner
        $('#driverDetails').html(`
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading driver information...</p>
            </div>
        `);
        
        // Set up the edit button to open edit modal when clicked
        $('#editFromViewBtn').off('click').on('click', function() {
            $('#viewDriverModal').modal('hide');
            $('#editDriverModal').modal('show');
            // Trigger the edit driver code
            loadDriverForEdit(driverId);
        });
        
        // AJAX call to get driver details
        const basePath = getBasePath();
        
        console.log('Fetching driver details for ID:', driverId);
        console.log('Request URL:', basePath + 'ajax/driver_actions.php');
        
        // Display request info in the modal for debugging
        $('#driverDetails').html(`
            <div class="alert alert-info">
                <h5>Sending request...</h5>
                <p>URL: ${basePath}ajax/driver_actions.php</p>
                <p>Parameters: driver_id=${driverId}</p>
                <div class="text-center mt-2">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        `);
        
        // Add a timestamp parameter to avoid caching issues
        const timestamp = new Date().getTime();
        
        $.ajax({
            url: basePath + 'ajax/driver_actions.php',
            type: 'GET',
            cache: false, // Avoid caching
            data: {
                driver_id: driverId,
                _: timestamp // Add timestamp to prevent caching
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            },
            beforeSend: function(xhr) {
                console.log('Sending request with headers:', {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                });
                console.log('Request data:', {driver_id: driverId, _: timestamp});
            },
            success: function(response) {
                console.log('Driver details response:', response);
                
                if (response.success) {
                    // Render driver details
                    renderDriverDetails(response.data);
                } else {
                    // Show detailed error message
                    let errorMessage = response.message || 'An error occurred while loading driver details.';
                    let errorDetails = '';
                    
                    // Add debug info if available
                    if (response.debug_info) {
                        console.log('Debug info:', response.debug_info);
                        errorDetails = `<div class="mt-2 text-muted small">
                            <strong>Error Code:</strong> ${response.debug_info.error_code || 'N/A'}<br>
                            <strong>Context:</strong> ${response.debug_info.context || 'N/A'}
                        </div>`;
                    }
                    
                    $('#driverDetails').html(`
                        <div class="alert alert-danger">
                            <h5>Error Loading Driver Details</h5>
                            <p>${errorMessage}</p>
                            ${errorDetails}
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-primary retry-view-driver" data-id="${driverId}" data-name="${driverName}">
                                    <i class="fas fa-sync-alt me-1"></i> Retry
                                </button>
                            </div>
                        </div>
                    `);
                    
                    // Add retry button functionality
                    $('.retry-view-driver').on('click', function() {
                        const retryId = $(this).data('id');
                        const retryName = $(this).data('name');
                        // Call the view function again
                        viewDriver(retryId, retryName);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                
                // Log the full error for debugging
                console.log({
                    status: status,
                    error: error,
                    xhr: xhr,
                    responseText: xhr.responseText
                });
                
                let errorMessage = 'An error occurred while loading driver details.';
                let errorDetails = '';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                    
                    // Display debug info if available
                    if (response.debug_info) {
                        console.log('Debug info:', response.debug_info);
                        errorDetails = `<div class="mt-2 text-muted small">
                            <strong>Error Code:</strong> ${response.debug_info.error_code || 'N/A'}<br>
                            <strong>Context:</strong> ${response.debug_info.context || 'N/A'}<br>
                            <strong>Query:</strong> ${response.debug_info.query || 'N/A'}
                        </div>`;
                    }
                } catch (e) {
                    // Parsing error - use default message
                    errorDetails = `<div class="mt-2 text-muted small">
                        <strong>Status:</strong> ${status}<br>
                        <strong>Error:</strong> ${error}<br>
                        <strong>Response:</strong> ${xhr.responseText && xhr.responseText.length > 100 ? 
                            xhr.responseText.substring(0, 100) + '...' : xhr.responseText || 'Empty response'}
                    </div>`;
                }
                
                $('#driverDetails').html(`
                    <div class="alert alert-danger">
                        <h5>Server Error</h5>
                        <p>${errorMessage}</p>
                        ${errorDetails}
                        <small>Please try again or contact system administrator.</small>
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary retry-view-driver" data-id="${driverId}" data-name="${driverName}">
                                <i class="fas fa-sync-alt me-1"></i> Retry
                            </button>
                        </div>
                    </div>
                `);
                
                // Add retry button functionality
                $('.retry-view-driver').on('click', function() {
                    const retryId = $(this).data('id');
                    const retryName = $(this).data('name');
                    // Call the view function again
                    viewDriver(retryId, retryName);
                });
            }
        });
    }
    
    // View Driver Details
    $('.view-driver').on('click', function() {
        const driverId = $(this).data('id');
        const driverName = $(this).data('name');
        currentDriverId = driverId;
        
        // Call the view driver function
        viewDriver(driverId, driverName);
    });
    
    // Helper to fetch driver status via API
    function fetchDriverStatus(userId, callback) {
        $.get('api/drivers/get_user_status.php', { user_id: userId }, function(response) {
            if (response.success) {
                callback(response.status || 'Unknown');
            } else {
                callback('Unknown');
            }
        }, 'json');
    }

    // Helper to fetch vehicle status via API
    function fetchVehicleStatus(vehicleId, callback) {
        $.get('api/drivers/get_vehicle_status.php', { vehicle_id: vehicleId }, function(response) {
            if (response.success) {
                callback(response.status || 'Unknown');
            } else {
                callback('Unknown');
            }
        }, 'json');
    }
    
    // Helper to fetch driver app status via API (from core1_movers.drivers)
    function fetchDriverAppStatus(driverId, callback) {
        $.get('api/drivers/get_driver_status.php', { driver_id: driverId }, function(response) {
            if (response.success) {
                callback(response.status || 'Unknown');
            } else {
                callback('Unknown');
            }
        }, 'json');
    }
    
    // Function to render driver details in the modal
    function renderDriverDetails(data) {
        // Use response.data.driver, response.data.assignments, response.data.performance
        if (!data || !data.driver) {
            $('#driverDetails').html('<div class="alert alert-danger">Driver details not found. Please try again or contact support.</div>');
            return;
        }
        const driver = data.driver;
        const assignments = data.assignments || [];
        const performance = data.performance || null;
        
        // Format dates
        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        };
        
        // Format time
        const formatDateTime = (dateString) => {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        };
        
        // Build status badge HTML
        const getStatusBadge = (status) => {
            if (!status) return '<span class="badge bg-secondary">Unknown</span>';
            status = status.toString().trim().toLowerCase();
            let badgeClass = '';
            switch(status) {
                case 'available': badgeClass = 'bg-success'; break;
                case 'busy': badgeClass = 'bg-warning'; break;
                case 'offline': badgeClass = 'bg-secondary'; break;
                case 'inactive': badgeClass = 'bg-danger'; break;
                case 'active': badgeClass = 'bg-primary'; break;
                case 'current': badgeClass = 'bg-info'; break;
                case 'completed': badgeClass = 'bg-secondary'; break;
                default: badgeClass = 'bg-info';
            }
            // Show the actual status text, capitalized
            return `<span class="badge ${badgeClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
        };
        
        // Build star rating HTML
        const getStarRating = (rating) => {
            rating = parseFloat(rating) || 0;
            let starsHtml = '';
            
                    for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    starsHtml += '<i class="fas fa-star text-warning"></i>';
                        } else if (i - 0.5 <= rating) {
                    starsHtml += '<i class="fas fa-star-half-alt text-warning"></i>';
                        } else {
                    starsHtml += '<i class="far fa-star text-warning"></i>';
                }
            }
            
            return `
                <div class="rating">
                    ${starsHtml}
                    <span class="ms-2">${rating.toFixed(1)}/5.0</span>
                </div>
            `;
        };
        
        // Create HTML for the driver details
        let html = `
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <img src="${driver.profile_image_url}" alt="${driver.firstname} ${driver.lastname}" 
                        class="img-fluid rounded-circle mb-3" style="width: 200px; height: 200px; object-fit: cover;">
                    <h4>${driver.firstname} ${driver.lastname}</h4>
                    <p>
                      <span id="driverAppStatusBadge"></span>
                      <span id="driverAccountStatusBadge" class="ms-2"></span>
                    </p>
                    <div class="mb-2">${getStarRating(driver.rating)}</div>
                    <p class="mb-1"><i class="fas fa-id-card me-2"></i>${driver.license_number || 'No license number'}</p>
                    <p class="text-muted small">Expires: ${formatDate(driver.license_expiry)}</p>
                </div>
                
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Driver Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                    <h6>Contact Information</h6>
                                    <p><i class="fas fa-envelope me-2"></i>${driver.email}</p>
                                    <p><i class="fas fa-phone me-2"></i>${driver.phone}</p>
                                    </div>
                                    <div class="col-md-6">
                                    <h6>Account Details</h6>
                                    <p><i class="fas fa-calendar me-2"></i>Last Login: ${formatDateTime(driver.last_login || 'Never')}</p>
                                    <p><i class="fas fa-user-plus me-2"></i>Joined: ${formatDate(driver.created_at)}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-car me-2"></i>Current Vehicle Assignment</h5>
                        </div>
                        <div class="card-body">
        `;
        
        if (driver.vehicle_id) {
            html += `
                                        <div class="row">
                                            <div class="col-md-6">
                        <p><strong>Plate Number:</strong> ${driver.plate_number}</p>
                        <p><strong>Model:</strong> ${driver.model || ''} ${driver.year || ''}</p>
                                            </div>
                                            <div class="col-md-6">
                        <p><strong>Status:</strong> <span id="vehicleStatusBadge"></span></p>
                        <p><strong>Fuel Type:</strong> ${driver.fuel_type || 'Not specified'}</p>
                        <p><strong>Capacity:</strong> ${driver.capacity || 'N/A'} passengers</p>
                                            </div>
                                        </div>
            `;
        } else {
            html += `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>No vehicle currently assigned to this driver.
                                    </div>
            `;
        }
        
        html += `
                                </div>
                    </div>
        `;
        
        // Add performance metrics if available
        if (performance) {
            html += `
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Metrics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Report Date:</strong> ${formatDate(performance.report_date)}</p>
                                <p><strong>Bookings:</strong> ${performance.total_bookings || 0} total / ${performance.completed_bookings || 0} completed</p>
                                <p><strong>Cancellations:</strong> ${performance.cancelled_bookings || 0}</p>
                                            </div>
                            <div class="col-md-6">
                                <p><strong>Average Rating:</strong> ${getStarRating(performance.avg_rating || 0)}</p>
                                <p><strong>Revenue Generated:</strong> ₱${parseFloat(performance.total_revenue || 0).toFixed(2)}</p>
                                <p><strong>Distance Covered:</strong> ${performance.total_distance || 0} km</p>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
            `;
        }
        
        html += `
                            </div>
                        </div>
                    `;
                    
        // Add vehicle assignment history if available
        if (assignments && assignments.length > 0) {
            html += `
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Vehicle Assignment History</h5>
                        </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Vehicle</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;
            
            assignments.forEach(assignment => {
                html += `
                    <tr>
                        <td>${assignment.plate_number || 'Unknown'} - ${assignment.model || ''} ${assignment.year || ''}</td>
                        <td>${formatDateTime(assignment.assignment_start)}</td>
                        <td>${assignment.assignment_end ? formatDateTime(assignment.assignment_end) : 'Current'}</td>
                        <td>${getStatusBadge(assignment.status)}</td>
                        <td>${assignment.notes || ''}</td>
                    </tr>
                `;
            });
            
            html += `
                                </tbody>
                            </table>
                    </div>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Vehicle Assignment History</h5>
                    </div>
                            <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>No vehicle assignment history found for this driver.
                            </div>
                        </div>
                    </div>
            `;
        }
        
        // Render the HTML
        $('#driverDetails').html(html);
        
        // Render driver app status badge (from drivers table)
        fetchDriverAppStatus(driver.driver_id, function(appStatus) {
            $("#driverAppStatusBadge").html(getStatusBadge(appStatus));
        });
        // Render driver account status badge (from users table)
        fetchDriverStatus(driver.user_id, function(accountStatus) {
            $("#driverAccountStatusBadge").html(getStatusBadge(accountStatus));
        });
        // Render vehicle status badge using API if vehicle assigned
        if (driver.vehicle_id) {
            fetchVehicleStatus(driver.vehicle_id, function(vehicleStatus) {
                $("#vehicleStatusBadge").html(getStatusBadge(vehicleStatus));
            });
        } else {
            $("#vehicleStatusBadge").html('<span class="badge bg-secondary">No Vehicle</span>');
        }
    }
    
    // Edit Driver - Show Modal and Load Data
    $(document).on('click', '.edit-driver', function() {
        const driverId = $(this).data('id');
        currentDriverId = driverId;
        $('#editDriverForm').trigger('reset');
        $('#edit_image_preview').empty();
        $('#editDriverModal .modal-body').html(`
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading driver information...</p>
            </div>
        `);
        const basePath = getBasePath();
        $.ajax({
            url: basePath + 'ajax/driver_actions.php',
            type: 'GET',
            data: {
                action: 'get_driver_details',
                driver_id: driverId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.driver) {
                    $('#editDriverModal .modal-body').html(`
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
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit_profile_image" class="form-label">Update Profile Image (Optional)</label>
                                <input type="file" class="form-control" id="edit_profile_image" name="profile_image" accept="image/*">
                                <div class="form-text">Leave blank to keep current image. Maximum file size: 2MB.</div>
                                <div id="edit_image_preview" class="mt-2"></div>
                            </div>
                        </form>
                    `);
                    const driver = response.data.driver;
                    $('#edit_driver_id').val(driver.driver_id);
                    $('#edit_user_id').val(driver.user_id);
                    $('#edit_firstname').val(driver.firstname);
                    $('#edit_lastname').val(driver.lastname);
                    $('#edit_email').val(driver.email);
                    $('#edit_phone').val(driver.phone);
                    $('#edit_license_number').val(driver.license_number);
                    $('#edit_license_expiry').val(driver.license_expiry);
                    $('#edit_status').val(driver.status);
                    $('#edit_rating').val(driver.rating || 0);
                    if (driver.profile_image_url) {
                        $('#edit_profile_preview').css('background-image', `url('${driver.profile_image_url}')`);
                    } else {
                        $('#edit_profile_preview').css('background-image', `url('${basePath}assets/img/avatars/default-avatar.png')`);
                    }
                    loadAvailableVehicles(driver.driver_id, 'edit_vehicle_id');
                    $('#edit_profile_image').on('change', function() {
                        const file = this.files[0];
                        if (file) {
                            if (file.size > 2 * 1024 * 1024) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'File Too Large',
                                    text: 'Profile picture must be less than 2MB.'
                                });
                                this.value = '';
                                $('#edit_image_preview').empty();
                                return;
                            }
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                $('#edit_image_preview').html(`
                                    <img src="${e.target.result}" alt="Profile Preview" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                                `);
                            }
                            reader.readAsDataURL(file);
                        } else {
                            $('#edit_image_preview').empty();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to load driver details'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'Failed to load driver details. Please try again.'
                });
            }
        });
    });
    
    // Update driver - Save changes
    $('#updateDriverBtn').on('click', function() {
        // Validate form
        if (!$('#editDriverForm')[0].checkValidity()) {
            $('#editDriverForm')[0].reportValidity();
            return;
        }
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Update Driver',
            text: 'Are you sure you want to update this driver?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, update driver!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create FormData object to handle file uploads
                const formData = new FormData($('#editDriverForm')[0]);
                formData.append('action', 'edit_driver');
                
                // Submit form via AJAX
                const basePath = getBasePath();
                
                $.ajax({
                    url: basePath + 'ajax/driver_actions.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    beforeSend: function() {
                        $('#updateDriverBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
                        
                        console.log('Update driver - Sending request with data:', {
                            driver_id: formData.get('driver_id'),
                            user_id: formData.get('user_id'),
                            firstname: formData.get('firstname'),
                            lastname: formData.get('lastname'),
                            email: formData.get('email'),
                            status: formData.get('status'),
                            rating: formData.get('rating'),
                            license_number: formData.get('license_number'),
                            license_expiry: formData.get('license_expiry'),
                            vehicle_id: formData.get('vehicle_id')
                        });
                    },
                    success: function(response) {
                        console.log('Update driver - AJAX success response:', response);
                        
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Driver Updated',
                                text: 'The driver has been updated successfully!',
                                confirmButtonText: 'Great!'
                            }).then(() => {
                                // Close modal and reload page
                                $('#editDriverModal').modal('hide');
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to update driver'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Update driver - AJAX Error:', {
                            status: status,
                            error: error,
                            xhr: xhr,
                            responseText: xhr.responseText
                        });
                        
                        let errorMessage = 'An error occurred while updating the driver. Please try again.';
                        let technicalDetails = '';
                        
                        // Try to parse the response if it's JSON
                        try {
                            if (xhr.responseText) {
                                const jsonResponse = JSON.parse(xhr.responseText);
                                if (jsonResponse.message) {
                                    errorMessage = jsonResponse.message;
                                }
                                technicalDetails = JSON.stringify(jsonResponse, null, 2);
                            }
                        } catch (e) {
                            technicalDetails = xhr.responseText || 'No response received';
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            html: `
                                <p>${errorMessage}</p>
                                <details>
                                    <summary>Technical Details (for IT support)</summary>
                                    <pre class="text-start text-wrap" style="max-height: 200px; overflow-y: auto;">${technicalDetails}</pre>
                                </details>
                            `
                        });
                    },
                    complete: function() {
                        $('#updateDriverBtn').prop('disabled', false).text('Save Changes');
                    }
                });
            }
        });
    });
    
    // Delete driver (deactivate)
    $('.delete-driver').on('click', function() {
        const driverId = $(this).data('id');
        const driverName = $(this).data('name');
        
        console.log('Delete driver - Driver ID:', driverId);
        console.log('Delete driver - Driver Name:', driverName);
        
        Swal.fire({
            title: 'Deactivate Driver',
            html: `Are you sure you want to deactivate <strong>${driverName}</strong>?<br>This will prevent the driver from logging in and mark them as inactive.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, deactivate driver',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                // Get the proper URL by checking the current path
                const basePath = getBasePath();
                console.log('Delete driver - Base path:', basePath);
                
                const requestUrl = basePath + 'ajax/driver_actions.php';
                console.log('Delete driver - Request URL:', requestUrl);
                
                return $.ajax({
                    url: requestUrl,
                    type: 'POST',
                    data: {
                        action: 'delete_driver',
                        driver_id: driverId
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    beforeSend: function(xhr) {
                        console.log('Delete driver - Sending request to:', requestUrl, 'with data:', {action: 'delete_driver', driver_id: driverId});
                    }
                })
                .done(function(response) {
                    console.log('Delete driver - AJAX success response:', response);
                    return response;
                })
                .fail(function(xhr, status, error) {
                    console.error('Delete driver - AJAX Error:', {
                        status: status,
                        error: error,
                        xhr: xhr,
                        responseText: xhr.responseText,
                        requestUrl: requestUrl,
                        driverId: driverId
                    });
                    
                    let errorMessage = 'Failed to deactivate the driver. Please try again.';
                    let technicalDetails = '';
                    
                    // Try to parse the response if it's JSON
                    try {
                        if (xhr.responseText) {
                            const jsonResponse = JSON.parse(xhr.responseText);
                            if (jsonResponse.message) {
                                errorMessage = jsonResponse.message;
                            }
                            technicalDetails = JSON.stringify(jsonResponse, null, 2);
                        }
                    } catch (e) {
                        technicalDetails = xhr.responseText || 'No response received';
                    }
                    
                    Swal.showValidationMessage(
                        `<i class="fas fa-exclamation-triangle"></i> ${errorMessage}<br>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2 debug-toggle-delete">
                            <i class="fas fa-bug me-1"></i> Show Technical Details
                        </button>`
                    );
                    
                    // Handle debug toggle click
                    setTimeout(() => {
                        $('.debug-toggle-delete').on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            Swal.fire({
                                icon: 'info',
                                title: 'Debug Information',
                                html: `
                                    <div class="text-start">
                                        <p><strong>Request URL:</strong> ${requestUrl}</p>
                                        <p><strong>Driver ID:</strong> ${driverId}</p>
                                        <p><strong>Status:</strong> ${status}</p>
                                        <p><strong>Error:</strong> ${error}</p>
                                        <p><strong>Response:</strong></p>
                                        <pre class="text-start text-wrap" style="max-height: 200px; overflow-y: auto;">${technicalDetails}</pre>
                                        <hr>
                                        <p><strong>Troubleshooting Steps:</strong></p>
                                        <ol>
                                            <li>Check if ajax/driver_actions.php exists</li>
                                            <li>Verify that your PHP error reporting is enabled</li>
                                            <li>Check database connectivity</li>
                                            <li>Verify the 'delete_driver' action is properly implemented</li>
                                        </ol>
                                    </div>
                                `,
                                width: '600px'
                            });
                        });
                    }, 100);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                const response = result.value;
                
                if (response && response.success) {
                    // Success
                    Swal.fire({
                        title: 'Deactivated!',
                        text: `${driverName} has been deactivated successfully.`,
                        icon: 'success',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        // Reload the page to update the data
                        window.location.reload();
                    });
                } else {
                    // Server returned an error
                    const errorMessage = (response && response.message) ? response.message : 'Failed to deactivate the driver due to a server error.';
                    
                    Swal.fire({
                        title: 'Error',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        footer: `<button class="btn btn-sm btn-link retry-delete" data-id="${driverId}" data-name="${driverName}">Try Again</button>`
                    });
                    
                    // Handle retry button
                    $('.retry-delete').on('click', function() {
                        const retryId = $(this).data('id');
                        const retryName = $(this).data('name');
                        Swal.close();
                        setTimeout(() => {
                            $('.delete-driver[data-id="' + retryId + '"]').trigger('click');
                        }, 300);
                    });
                }
            }
        });
    });
    
    // Activate driver
    $('.activate-driver').on('click', function() {
        const driverId = $(this).data('id');
        const driverName = $(this).data('name');
        
        console.log('Activate driver - Driver ID:', driverId);
        console.log('Activate driver - Driver Name:', driverName);
        
        Swal.fire({
            title: 'Activate Driver',
            html: `Are you sure you want to activate <strong>${driverName}</strong>?<br>This will allow the driver to log in and use the system again.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, activate driver',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                // Get the proper URL by checking the current path
                const basePath = getBasePath();
                console.log('Activate driver - Base path:', basePath);
                
                const requestUrl = basePath + 'ajax/driver_actions.php';
                console.log('Activate driver - Request URL:', requestUrl);
                
                return $.ajax({
                    url: requestUrl,
                    type: 'POST',
                    data: {
                        action: 'activate_driver',
                        driver_id: driverId
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    beforeSend: function(xhr) {
                        console.log('Activate driver - Sending request to:', requestUrl, 'with data:', {action: 'activate_driver', driver_id: driverId});
                    }
                })
                .done(function(response) {
                    console.log('Activate driver - AJAX success response:', response);
                    return response;
                })
                .fail(function(xhr, status, error) {
                    console.error('Activate driver - AJAX Error:', {
                        status: status,
                        error: error,
                        xhr: xhr,
                        responseText: xhr.responseText,
                        requestUrl: requestUrl,
                        driverId: driverId
                    });
                    
                    let errorMessage = 'Failed to activate the driver. Please try again.';
                    let technicalDetails = '';
                    
                    // Try to parse the response if it's JSON
                    try {
                        if (xhr.responseText) {
                            const jsonResponse = JSON.parse(xhr.responseText);
                            if (jsonResponse.message) {
                                errorMessage = jsonResponse.message;
                            }
                            technicalDetails = JSON.stringify(jsonResponse, null, 2);
                        }
                    } catch (e) {
                        technicalDetails = xhr.responseText || 'No response received';
                    }
                    
                    Swal.showValidationMessage(
                        `<i class="fas fa-exclamation-triangle"></i> ${errorMessage}<br>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2 debug-toggle-activate">
                            <i class="fas fa-bug me-1"></i> Show Technical Details
                        </button>`
                    );
                    
                    // Handle debug toggle click
                    setTimeout(() => {
                        $('.debug-toggle-activate').on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            Swal.fire({
                                icon: 'info',
                                title: 'Debug Information',
                                html: `
                                    <div class="text-start">
                                        <p><strong>Request URL:</strong> ${requestUrl}</p>
                                        <p><strong>Driver ID:</strong> ${driverId}</p>
                                        <p><strong>Status:</strong> ${status}</p>
                                        <p><strong>Error:</strong> ${error}</p>
                                        <p><strong>Response:</strong></p>
                                        <pre class="text-start text-wrap" style="max-height: 200px; overflow-y: auto;">${technicalDetails}</pre>
                                    </div>
                                `,
                                width: '600px'
                            });
                        });
                    }, 100);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                const response = result.value;
                
                if (response && response.success) {
                    // Success
                    Swal.fire({
                        title: 'Activated!',
                        text: `${driverName} has been activated successfully.`,
                        icon: 'success',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        // Reload the page to update the data
                        window.location.reload();
                    });
                } else {
                    // Server returned an error
                    const errorMessage = (response && response.message) ? response.message : 'Failed to activate the driver due to a server error.';
                    
                    Swal.fire({
                        title: 'Error',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        footer: `<button class="btn btn-sm btn-link retry-activate" data-id="${driverId}" data-name="${driverName}">Try Again</button>`
                    });
                    
                    // Handle retry button
                    $('.retry-activate').on('click', function() {
                        const retryId = $(this).data('id');
                        const retryName = $(this).data('name');
                        Swal.close();
                        setTimeout(() => {
                            $('.activate-driver[data-id="' + retryId + '"]').trigger('click');
                        }, 300);
                    });
                }
            }
    });
}); 
}); 