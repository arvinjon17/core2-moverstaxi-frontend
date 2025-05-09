/**
 * Customer Details Modal Handler
 * Displays detailed customer information in a modal view
 */

document.addEventListener('DOMContentLoaded', function() {
    // Customer Details Modal Functionality
    const customerDetailsBtns = document.querySelectorAll('.view-customer-btn');
    
    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Add click listeners to all view buttons
    customerDetailsBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const customerId = this.getAttribute('data-id');
            loadCustomerDetails(customerId);
        });
    });

    /**
     * Load customer details via AJAX
     * @param {number} customerId - The ID of the customer to load
     */
    function loadCustomerDetails(customerId) {
        const modal = document.getElementById('customerDetailsModal');
        const modalBody = modal.querySelector('.modal-body');
        const modalTitle = modal.querySelector('.modal-title');
        const progressBar = modal.querySelector('.progress-bar');
        
        // Reset modal content and show loader
        modalBody.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading customer data...</p></div>';
        modalTitle.innerHTML = '<i class="fas fa-user me-2"></i> Customer Details';
        
        // Animate progress bar
        progressBar.style.width = '0%';
        progressBar.classList.add('progress-bar-animated');
        
        // Simulate progress
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += 10;
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
            
            if (progress >= 90) {
                clearInterval(progressInterval);
            }
        }, 150);
        
        console.log(`Fetching customer details for ID: ${customerId}`);
        
        // Fetch real customer details (removed debug parameter to ensure we get only real data)
        fetch(`api/get_customer_details.php?id=${customerId}`)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Complete progress bar animation
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                progressBar.classList.remove('progress-bar-animated');
                
                if (data.success) {
                    console.log('Customer data received successfully');
                    console.log('User ID:', data.customer.id);
                    console.log('Customer ID (core1):', data.customer.customer_id_core1);
                    
                    // Debug booking history data
                    if (data.customer.booking_history && Array.isArray(data.customer.booking_history)) {
                        console.log(`Booking history array length: ${data.customer.booking_history.length}`);
                        if (data.customer.booking_history.length > 0) {
                            console.log('First booking:', data.customer.booking_history[0]);
                        } else {
                            console.warn('Booking history array is empty');
                        }
                    } else {
                        console.warn('No booking history found or it is not an array!', data.customer.booking_history);
                        // Ensure booking_history is at least an empty array to prevent errors
                        data.customer.booking_history = [];
                    }
                    
                    // Log booking statistics for debugging
                    console.log('Booking statistics:', {
                        totalRides: data.customer.total_rides,
                        pendingRides: data.customer.pending_rides,
                        completedRides: data.customer.completed_rides,
                        cancelledRides: data.customer.cancelled_rides,
                        totalSpending: data.customer.total_spending
                    });
                    
                    // Render customer details after a small delay to show completed progress
                    setTimeout(() => {
                        renderCustomerDetails(data.customer, modalBody);
                        // Update modal title with customer name
                        modalTitle.innerHTML = `<i class="fas fa-user me-2"></i> ${data.customer.full_name || 'Customer Details'}`;
                        
                        // Update tooltips after rendering
                        initializeTooltips();
                    }, 300);
                } else {
                    throw new Error(data.message || 'Failed to load customer data');
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.remove('bg-info');
                progressBar.classList.add('bg-danger');
                
                console.error('Error loading customer details:', error);
                
                modalBody.innerHTML = `
                    <div class="text-center p-5">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-circle fa-3x"></i>
                        </div>
                        <h5>Error Loading Customer Data</h5>
                        <p>There was a problem loading the customer information. Please try again later.</p>
                        <div class="alert alert-danger mt-3">
                            ${error.message || 'Unknown error'}
                        </div>
                        <button class="btn btn-outline-primary mt-3" id="retryCustomerLoadBtn">
                            <i class="fas fa-sync-alt me-2"></i> Retry
                        </button>
                    </div>
                `;
                
                // Add retry button functionality
                document.getElementById('retryCustomerLoadBtn')?.addEventListener('click', function() {
                    loadCustomerDetails(customerId);
                });
            });
    }
    
    /**
     * Initialize tooltips on dynamically loaded content
     */
    function initializeTooltips() {
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
            const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltips.forEach(el => new bootstrap.Tooltip(el));
        }
    }

    /**
     * Render customer details in the modal body
     * @param {Object} customer - The customer data object
     * @param {HTMLElement} container - The container element to render into
     */
    function renderCustomerDetails(customer, container) {
        // Helper function to format account status badge
        function getAccountStatusBadge(status) {
            const statusMap = {
                'active': { color: 'success', icon: 'check-circle' },
                'inactive': { color: 'secondary', icon: 'times-circle' },
                'suspended': { color: 'danger', icon: 'ban' }
            };
            const statusInfo = statusMap[status] || { color: 'secondary', icon: 'question-circle' };
            
            return `<span class="badge bg-${statusInfo.color} px-3 py-2">
                <i class="fas fa-${statusInfo.icon} me-1"></i> ${status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown'}
            </span>`;
        }
        
        // Helper function to format activity status badge
        function getActivityStatusBadge(status) {
            const statusMap = {
                'online': { color: 'success', icon: 'circle' },
                'busy': { color: 'warning', icon: 'hourglass-half' },
                'offline': { color: 'secondary', icon: 'power-off' }
            };
            const statusInfo = statusMap[status] || { color: 'secondary', icon: 'question-circle' };
            
            return `<span class="badge bg-${statusInfo.color} px-3 py-2">
                <i class="fas fa-${statusInfo.icon} me-1"></i> ${status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Offline'}
            </span>`;
        }
        
        // Helper function to format loyalty status badge
        function getLoyaltyStatusBadge(status) {
            const statusMap = {
                'Gold': { color: 'warning', icon: 'crown' },
                'Silver': { color: 'secondary', icon: 'medal' },
                'Bronze': { color: 'bronze', icon: 'award' },
                'Regular': { color: 'info', icon: 'user' }
            };
            const statusInfo = statusMap[status] || { color: 'info', icon: 'user' };
            
            return `<span class="badge bg-${statusInfo.color} px-3 py-2">
                <i class="fas fa-${statusInfo.icon} me-1"></i> ${status}
            </span>`;
        }
        
        // Build customer booking history table
        function buildBookingHistoryTable(bookings) {
            console.log('Building booking history table with data:', bookings);
            
            if (!bookings || !Array.isArray(bookings) || bookings.length === 0) {
                console.log('No booking history or invalid data format');
                
                // Check if booking statistics indicate there should be bookings
                if (customer.total_rides > 0) {
                    return `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> Unable to display booking history.
                            <div class="small mt-2">Statistics show ${customer.total_rides} bookings but they could not be retrieved. This may indicate a database mapping issue.</div>
                            <button class="btn btn-sm btn-outline-secondary mt-2" id="bookingDebugBtn">Show Technical Details</button>
                            <div id="bookingDebugInfo" class="mt-2 d-none small p-2 bg-light rounded">
                                <div>User ID: ${customer.id}</div>
                                <div>Customer ID: ${customer.customer_id_core1 || 'Not Available'}</div>
                                <div>Booking Stats: ${customer.total_rides} total, ${customer.completed_rides} completed</div>
                            </div>
                        </div>
                    `;
                }
                
                return `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No booking history available for this customer.
                        <div class="small mt-2">This customer has not made any bookings yet.</div>
                    </div>
                `;
            }
            
            // Filter out any sample data that might have been included
            bookings = bookings.filter(booking => {
                // Skip bookings that look like sample data (ID 999 or having "Sample" in text fields)
                const isSampleData = 
                    booking.booking_id == 999 || 
                    (booking.pickup && booking.pickup.includes('Sample')) ||
                    (booking.driver_name && booking.driver_name === 'Sample Driver');
                    
                if (isSampleData) {
                    console.log('Filtered out sample booking data:', booking);
                    return false;
                }
                return true;
            });
            
            // Re-check after filtering
            if (bookings.length === 0) {
                console.log('No real booking data after filtering out sample data');
                return `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No real booking history available for this customer.
                        <div class="small mt-2">Only sample data was found which has been filtered out.</div>
                    </div>
                `;
            }
            
            console.log(`Found ${bookings.length} real bookings to display`);
            
            let tableHTML = `
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>From/To</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Driver/Vehicle</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            bookings.forEach(booking => {
                console.log('Processing booking ID:', booking.booking_id);
                
                // Determine status badge class
                let statusClass, statusIcon;
                switch (booking.status ? booking.status.toLowerCase() : '') {
                    case 'completed':
                        statusClass = 'success';
                        statusIcon = 'check-circle';
                        break;
                    case 'cancelled':
                        statusClass = 'danger';
                        statusIcon = 'times-circle';
                        break;
                    case 'in_progress':
                        statusClass = 'primary';
                        statusIcon = 'spinner';
                        break;
                    case 'confirmed':
                        statusClass = 'info';
                        statusIcon = 'calendar-check';
                        break;
                    case 'pending':
                        statusClass = 'warning';
                        statusIcon = 'clock';
                        break;
                    default:
                        statusClass = 'secondary';
                        statusIcon = 'question-circle';
                }
                
                // Determine payment status badge
                let paymentClass, paymentIcon, paymentStatus = booking.payment_status || 'unknown';
                switch (paymentStatus.toLowerCase()) {
                    case 'completed':
                    case 'paid':
                        paymentClass = 'success';
                        paymentIcon = 'check-circle';
                        break;
                    case 'pending':
                        paymentClass = 'warning';
                        paymentIcon = 'clock';
                        break;
                    case 'failed':
                        paymentClass = 'danger';
                        paymentIcon = 'times-circle';
                        break;
                    default:
                        paymentClass = 'secondary';
                        paymentIcon = 'question-circle';
                }
                
                // Format fare amount and ensure it exists
                let fare = '0.00';
                if (booking.fare) {
                    // Handle different formats (string vs number)
                    try {
                        fare = parseFloat(booking.fare).toFixed(2);
                    } catch (e) {
                        console.warn('Error parsing fare amount:', e);
                    }
                }
                
                // Ensure we have valid data for all fields
                const bookingId = booking.booking_id || 'N/A';
                const bookingDate = booking.date || 'N/A';
                const pickup = booking.pickup || 'N/A';
                const dropoff = booking.dropoff || 'N/A';
                const statusText = booking.status ? 
                    booking.status.charAt(0).toUpperCase() + booking.status.slice(1).replace('_', ' ') : 
                    'Unknown';
                const paymentText = paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1);
                const driverName = booking.driver_name || 'Not assigned';
                const vehicle = booking.vehicle || 'Not assigned';
                
                // Format payment method (convert from database format to user-friendly text)
                let paymentMethod = booking.payment_method || 'unknown';
                switch (paymentMethod.toLowerCase()) {
                    case 'credit_card':
                        paymentMethod = 'Credit Card';
                        break;
                    case 'debit_card':
                        paymentMethod = 'Debit Card'; 
                        break;
                    case 'cash':
                        paymentMethod = 'Cash';
                        break;
                    case 'mobile_payment':
                        paymentMethod = 'Mobile Payment';
                        break;
                    case 'online_transfer':
                        paymentMethod = 'Online Transfer';
                        break;
                }
                
                // Format payment details for tooltip
                const paymentDetails = [];
                if (booking.transaction_id) {
                    paymentDetails.push(`Transaction: ${booking.transaction_id}`);
                }
                if (booking.payment_date) {
                    paymentDetails.push(`Date: ${booking.payment_date}`);
                }
                if (paymentMethod !== 'unknown') {
                    paymentDetails.push(`Method: ${paymentMethod}`);
                }
                
                const paymentDetailsText = paymentDetails.length > 0 ? 
                    paymentDetails.join(' | ') : 'No payment details available';
                
                tableHTML += `
                <tr>
                    <td><span class="fw-bold">#${bookingId}</span></td>
                    <td>${bookingDate}</td>
                    <td>
                        <small class="d-block text-truncate" style="max-width: 150px;" data-bs-toggle="tooltip" title="${pickup}">
                            <i class="fas fa-map-marker-alt text-danger"></i> ${pickup}
                        </small>
                        <small class="d-block text-truncate" style="max-width: 150px;" data-bs-toggle="tooltip" title="${dropoff}">
                            <i class="fas fa-map-marker-alt text-success"></i> ${dropoff}
                        </small>
                    </td>
                    <td>
                        <span class="badge bg-${statusClass}">
                            <i class="fas fa-${statusIcon} me-1"></i>
                            ${statusText}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-bold">₱${fare}</span>
                            <span class="badge bg-${paymentClass} mt-1">
                                <i class="fas fa-${paymentIcon} me-1"></i>
                                ${paymentText}
                            </span>
                            <small class="text-muted mt-1">${paymentMethod !== 'unknown' ? paymentMethod : ''}</small>
                        </div>
                    </td>
                    <td>
                        <small class="d-block text-truncate" style="max-width: 150px;" data-bs-toggle="tooltip" title="Driver: ${driverName}">
                            <i class="fas fa-user text-primary"></i> ${driverName}
                        </small>
                        <small class="d-block text-truncate" style="max-width: 150px;" data-bs-toggle="tooltip" title="Vehicle: ${vehicle}">
                            <i class="fas fa-car text-secondary"></i> ${vehicle}
                        </small>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="${paymentDetailsText}">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </td>
                </tr>`;
            });
            
            tableHTML += `
                    </tbody>
                </table>
            </div>`;
            
            return tableHTML;
        }
        
        // Build customer documents list
        function buildDocumentsList(documents) {
            if (!documents || documents.length === 0) {
                return '<div class="alert alert-info">No documents available for this customer.</div>';
            }
            
            let listHTML = '<div class="list-group">';
            
            documents.forEach(doc => {
                let iconClass;
                switch (doc.document_type) {
                    case 'id':
                        iconClass = 'id-card';
                        break;
                    case 'license':
                        iconClass = 'id-badge';
                        break;
                    case 'vehicle':
                        iconClass = 'car';
                        break;
                    case 'insurance':
                        iconClass = 'file-contract';
                        break;
                    default:
                        iconClass = 'file-alt';
                }
                
                listHTML += `
                <a href="#" class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><i class="fas fa-${iconClass} me-2"></i> ${doc.document_name}</h6>
                        <small>${new Date(doc.uploaded_at).toLocaleDateString()}</small>
                    </div>
                    <small class="text-muted">Type: ${doc.document_type.charAt(0).toUpperCase() + doc.document_type.slice(1)}</small>
                </a>`;
            });
            
            listHTML += '</div>';
            return listHTML;
        }
        
        // Build driver feedback list
        function buildDriverFeedbackList(feedback) {
            if (!feedback || feedback.length === 0) {
                return '<div class="alert alert-info">No driver feedback available for this customer.</div>';
            }
            
            let listHTML = '';
            
            feedback.forEach(item => {
                // Generate stars based on rating
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= item.rating) {
                        stars += '<i class="fas fa-star text-warning"></i>';
                    } else {
                        stars += '<i class="far fa-star text-muted"></i>';
                    }
                }
                
                listHTML += `
                <div class="card mb-2 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-subtitle mb-0 text-muted">
                                <i class="fas fa-user-circle me-2"></i> ${item.driver_name}
                            </h6>
                            <small class="text-muted">${item.date}</small>
                        </div>
                        <div class="mb-2">
                            ${stars} <span class="ms-2 text-muted">(${item.rating}/5)</span>
                        </div>
                        <p class="card-text">${item.comment || 'No comment provided.'}</p>
                    </div>
                </div>`;
            });
            
            return listHTML;
        }

        // Main HTML content
        const html = `
        <div class="customer-profile-modal">
            <div class="row mb-4">
                <div class="col-lg-4 col-md-5 text-center mb-3">
                    <img src="${customer.avatar}" alt="Profile Image" class="img-fluid rounded-circle customer-profile-img" style="max-width: 160px; border: 4px solid #f8f9fa; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <h4 class="mt-3 mb-1">${customer.full_name}</h4>
                    <p class="text-muted">Customer #${customer.id}</p>
                    <div class="mt-2">
                        ${getLoyaltyStatusBadge(customer.loyalty_status)}
                    </div>
                </div>
                <div class="col-lg-8 col-md-7">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0"><i class="fas fa-user-circle me-2"></i>Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-envelope me-1"></i> Email</div>
                                        <div class="info-value fw-medium">${customer.email}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-phone me-1"></i> Phone</div>
                                        <div class="info-value fw-medium">${customer.phone || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-group p-2 bg-light rounded mb-3">
                                <div class="info-label text-muted mb-1"><i class="fas fa-map-marker-alt me-1"></i> Address</div>
                                <div class="info-value fw-medium">${customer.address}</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-calendar-plus me-1"></i> Registered Date</div>
                                        <div class="info-value fw-medium">${customer.registered_date}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-clock me-1"></i> Last Login</div>
                                        <div class="info-value fw-medium">${customer.last_login}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-user-shield me-1"></i> Account Status</div>
                                        <div class="info-value">
                                            ${getAccountStatusBadge(customer.account_status)}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-signal me-1"></i> Activity Status</div>
                                        <div class="info-value">
                                            ${getActivityStatusBadge(customer.activity_status)}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-credit-card me-1"></i> Payment Method</div>
                                        <div class="info-value fw-medium">${customer.preferred_payment_method}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-group p-2 bg-light rounded">
                                        <div class="info-label text-muted mb-1"><i class="fas fa-wallet me-1"></i> Total Spending</div>
                                        <div class="info-value fw-medium">₱${customer.total_spending}</div>
                                    </div>
                                </div>
                            </div>
                            
                            ${customer.notes ? `
                            <div class="info-group p-2 bg-light rounded">
                                <div class="info-label text-muted mb-1"><i class="fas fa-sticky-note me-1"></i> Notes</div>
                                <div class="info-value fw-medium">${customer.notes}</div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Booking Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h5 class="mb-0">${customer.total_rides}</h5>
                                        <small class="text-muted">Total Rides</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h5 class="mb-0">${customer.completed_rides}</h5>
                                        <small class="text-muted">Completed</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h5 class="mb-0">${customer.pending_rides}</h5>
                                        <small class="text-muted">Pending</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <h5 class="mb-0">${customer.cancelled_rides}</h5>
                                        <small class="text-muted">Cancelled</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            ${customer.assigned_driver || customer.assigned_vehicle ? `
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="fas fa-user-friends me-2"></i>Assigned Resources</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        ${customer.assigned_driver ? `
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-id-badge me-2"></i>Assigned Driver</h6>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">${customer.assigned_driver.full_name}</h6>
                                            <small class="text-muted">${customer.assigned_driver.phone}</small>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        <small class="text-muted">License: ${customer.assigned_driver.license_number}</small><br>
                                        <small class="text-muted">Rating: ${customer.assigned_driver.rating || 'N/A'}/5</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${customer.assigned_vehicle ? `
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-car me-2"></i>Assigned Vehicle</h6>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                            <i class="fas fa-car"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">${customer.assigned_vehicle.model} (${customer.assigned_vehicle.year})</h6>
                                            <small class="text-muted">Plate: ${customer.assigned_vehicle.plate_number}</small>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        <small class="text-muted">VIN: ${customer.assigned_vehicle.vin || 'N/A'}</small><br>
                                        <small class="text-muted">Status: ${customer.assigned_vehicle.status || 'Unknown'}</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            ` : ''}
            
            <div class="row">
                <div class="col-12 mb-4">
                    <!-- Navigation tabs for customer details sections -->
                    <ul class="nav nav-pills nav-justified mb-3" id="customerDetailsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="booking-history-tab" data-bs-toggle="pill" data-bs-target="#booking-history" type="button" role="tab" aria-controls="booking-history" aria-selected="true">
                                <i class="fas fa-history me-2"></i>Booking History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payment-history-tab" data-bs-toggle="pill" data-bs-target="#payment-history" type="button" role="tab" aria-controls="payment-history" aria-selected="false">
                                <i class="fas fa-money-bill-wave me-2"></i>Payment History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="documents-tab" data-bs-toggle="pill" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                                <i class="fas fa-file-alt me-2"></i>Documents
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="feedback-tab" data-bs-toggle="pill" data-bs-target="#feedback" type="button" role="tab" aria-controls="feedback" aria-selected="false">
                                <i class="fas fa-comment-alt me-2"></i>Driver Feedback
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab content -->
                    <div class="tab-content" id="customerDetailsTabContent">
                        <!-- Booking History Tab -->
                        <div class="tab-pane fade show active" id="booking-history" role="tabpanel" aria-labelledby="booking-history-tab">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Booking History</h5>
                                    <span class="badge bg-primary">${customer.total_rides} Total</span>
                        </div>
                        <div class="card-body">
                            ${buildBookingHistoryTable(customer.booking_history)}
                        </div>
                    </div>
                </div>
                
                        <!-- Payment History Tab -->
                        <div class="tab-pane fade" id="payment-history" role="tabpanel" aria-labelledby="payment-history-tab">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payment History</h5>
                                </div>
                                <div class="card-body">
                                    ${buildPaymentHistoryTable(customer.booking_history)}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                            <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0"><i class="fas fa-file-alt me-2"></i>Documents</h5>
                        </div>
                        <div class="card-body">
                            ${buildDocumentsList(customer.documents)}
                                </div>
                        </div>
                    </div>
                    
                        <!-- Driver Feedback Tab -->
                        <div class="tab-pane fade" id="feedback" role="tabpanel" aria-labelledby="feedback-tab">
                            <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0"><i class="fas fa-comment-alt me-2"></i>Driver Feedback</h5>
                        </div>
                        <div class="card-body">
                            ${buildDriverFeedbackList(customer.driver_feedback)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
        
        // Update modal content
        container.innerHTML = html;
        
        // Add event listener for the booking debug button
        const bookingDebugBtn = container.querySelector('#bookingDebugBtn');
        if (bookingDebugBtn) {
            bookingDebugBtn.addEventListener('click', function() {
                const debugInfo = container.querySelector('#bookingDebugInfo');
                if (debugInfo) {
                    debugInfo.classList.toggle('d-none');
                    this.textContent = debugInfo.classList.contains('d-none') ? 
                        'Show Technical Details' : 'Hide Technical Details';
                }
            });
        }
        
        // Reinitialize tooltips on new content
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
            const tooltips = [].slice.call(container.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltips.forEach(el => new bootstrap.Tooltip(el));
        }
    }
    
    /**
     * Build payment history table based on booking history
     * @param {Array} bookings - Array of booking objects
     * @returns {string} HTML for payment history table
     */
    function buildPaymentHistoryTable(bookings) {
        if (!bookings || bookings.length === 0) {
            return '<div class="alert alert-info">No payment history available for this customer.</div>';
        }
        
        // Filter out bookings without payment information
        const bookingsWithPayment = bookings.filter(booking => 
            booking.payment_status && booking.payment_status.toLowerCase() !== 'unknown');
        
        if (bookingsWithPayment.length === 0) {
            return '<div class="alert alert-info">No payment records found for this customer\'s bookings.</div>';
        }
        
        let tableHTML = `
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Booking ID</th>
                        <th>Payment Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                    </tr>
                </thead>
                <tbody>`;
        
        bookingsWithPayment.forEach(booking => {
            // Format payment method
            let paymentMethod = booking.payment_method || 'Unknown';
            switch (paymentMethod.toLowerCase()) {
                case 'credit_card':
                    paymentMethod = 'Credit Card';
                    break;
                case 'debit_card':
                    paymentMethod = 'Debit Card';
                    break;
                case 'cash':
                    paymentMethod = 'Cash';
                    break;
                case 'mobile_payment':
                    paymentMethod = 'Mobile Payment';
                    break;
                case 'online_transfer':
                    paymentMethod = 'Online Transfer';
                    break;
            }
            
            // Determine payment status badge
            let paymentClass, paymentIcon;
            switch (booking.payment_status ? booking.payment_status.toLowerCase() : '') {
                case 'completed':
                case 'paid':
                    paymentClass = 'success';
                    paymentIcon = 'check-circle';
                    break;
                case 'pending':
                    paymentClass = 'warning';
                    paymentIcon = 'clock';
                    break;
                case 'failed':
                case 'refunded':
                    paymentClass = 'danger';
                    paymentIcon = 'times-circle';
                    break;
                default:
                    paymentClass = 'secondary';
                    paymentIcon = 'question-circle';
            }
            
            // Format payment date - use payment_date if available, otherwise booking date
            const paymentDate = booking.payment_date || booking.date || 'N/A';
            
            // Format fare
            let fare = '0.00';
            if (booking.fare) {
                try {
                    fare = parseFloat(booking.fare).toFixed(2);
                } catch (e) {
                    console.warn('Error parsing fare amount:', e);
                }
            }
            
            // Format transaction ID
            const transactionId = booking.transaction_id || 'N/A';
            
            tableHTML += `
            <tr>
                <td><span class="fw-bold">#${booking.booking_id}</span></td>
                <td>${paymentDate}</td>
                <td class="fw-bold">₱${fare}</td>
                <td>
                    <span class="badge bg-${paymentClass}">
                        <i class="fas fa-${paymentIcon} me-1"></i>
                        ${booking.payment_status ? booking.payment_status.charAt(0).toUpperCase() + booking.payment_status.slice(1) : 'Unknown'}
                    </span>
                </td>
                <td>${paymentMethod}</td>
                <td>
                    <span class="small text-monospace">${transactionId}</span>
                </td>
            </tr>`;
        });
        
        tableHTML += `
                </tbody>
            </table>
        </div>`;
        
        return tableHTML;
    }

    // New Edit Customer Modal logic
    $('.edit-customer-new-btn').on('click', function() {
        const customerId = $(this).data('id');
        $('#editCustomerNewContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading customer edit form...</p>
            </div>
        `);
        $.ajax({
            url: '/pages/customer/customer_edit_modal.php',
            data: { user_id: customerId },
            method: 'GET',
            success: function(response) {
                $('#editCustomerNewContent').html(response);
            },
            error: function(xhr, status, error) {
                $('#editCustomerNewContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading the edit form. Please try again.<br>
                        <div class="mt-2 small">Error details: ${error || 'Unknown error'}</div>
                    </div>
                `);
            }
        });
    });

    // Save handler for new edit customer modal
    $(document).on('click', '#saveCustomerNewBtn', function() {
        const $form = $('#editCustomerNewForm');
        if ($form.length === 0) {
            Swal.fire({
                title: 'Error',
                text: 'Edit form not found.',
                icon: 'error'
            });
            return;
        }
        // Client-side validation for PH phone
        const phone = $form.find('#phone').val();
        const phRegex = /^(\+639|09)\d{9}$/;
        if (!phRegex.test(phone)) {
            $form.find('#phone').addClass('is-invalid');
            Swal.fire({
                title: 'Validation Error',
                text: 'Please enter a valid PH number (+639XXXXXXXXX or 09XXXXXXXXX)',
                icon: 'error'
            });
            return;
        }
        // Validate required fields
        let missing = [];
        $form.find('[required]').each(function() {
            if (!$(this).val().trim()) missing.push($(this).attr('name'));
        });
        if (missing.length > 0) {
            Swal.fire({
                title: 'Validation Error',
                html: 'Please fill all required fields:<br>' + missing.join(', '),
                icon: 'error'
            });
            return;
        }
        // Submit via AJAX
        $.ajax({
            url: 'api/customers/update_new.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#editCustomerNewModal').modal('hide');
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.message,
                        icon: 'error'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while saving. Please try again.',
                    icon: 'error'
                });
            }
        });
    });
}); 