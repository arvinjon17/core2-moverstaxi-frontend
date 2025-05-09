/**
 * Movers Taxi System - Main Application JavaScript
 */

// Global application state
const appState = {
    isFirebaseAvailable: false,
    isOnline: navigator.onLine,
    notificationCount: 0,
    currentPage: '',
    user: null
};

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Movers Taxi System initialized');
    
    // Set current page from URL
    const urlParams = new URLSearchParams(window.location.search);
    appState.currentPage = urlParams.get('page') || 'dashboard';
    
    // Initialize components
    initializeNotifications();
    setupEventListeners();
    checkOnlineStatus();
    
    // Setup AJAX fallback if Firebase is not available
    if (!appState.isFirebaseAvailable) {
        setupAjaxFallback();
    }
});

/**
 * Initialize notification system
 */
function initializeNotifications() {
    // This will be integrated with Firebase when configured
    console.log('Notification system initialized');
    
    // For demo purposes, update notification count
    document.querySelectorAll('.notification-count').forEach(badge => {
        badge.textContent = appState.notificationCount;
    });
}

/**
 * Set up event listeners for application
 */
function setupEventListeners() {
    // Online/offline status
    window.addEventListener('online', () => {
        appState.isOnline = true;
        console.log('Application is online');
        // Show online status notification
        showToast('You are back online', 'success');
    });
    
    window.addEventListener('offline', () => {
        appState.isOnline = false;
        console.log('Application is offline');
        // Show offline status notification
        showToast('You are offline. Some features may be limited.', 'warning', 0); // 0 means no auto-hide
    });
    
    // Notification dropdown toggle
    const notificationDropdown = document.getElementById('notificationsDropdown');
    if (notificationDropdown) {
        notificationDropdown.addEventListener('click', function(e) {
            // Mark notifications as read when dropdown is opened
            appState.notificationCount = 0;
            document.querySelectorAll('.notification-count').forEach(badge => {
                badge.textContent = '0';
            });
        });
    }
    
    // Logout confirmation
    const logoutLinks = document.querySelectorAll('a[href="logout.php"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = 'logout.php';
            }
        });
    });
}

/**
 * Check if application is online
 */
function checkOnlineStatus() {
    if (!navigator.onLine) {
        appState.isOnline = false;
        console.log('Application started in offline mode');
        showToast('You are offline. Some features may be limited.', 'warning', 0);
    }
}

/**
 * Set up AJAX fallback for real-time updates
 */
function setupAjaxFallback() {
    console.log('Setting up AJAX fallback for real-time updates');
    
    // Check for notifications
    setInterval(checkNotifications, 30000); // Every 30 seconds
    
    // Check for updates on current page
    setInterval(checkPageUpdates, 60000); // Every minute
}

/**
 * Check for new notifications via AJAX
 */
function checkNotifications() {
    if (!appState.isOnline) return;
    
    // This will be implemented to fetch notifications via AJAX
    // For demo purposes, simulate a new notification sometimes
    if (Math.random() > 0.7) {
        appState.notificationCount++;
        document.querySelectorAll('.notification-count').forEach(badge => {
            badge.textContent = appState.notificationCount;
        });
        
        // Update notification list
        const notificationsList = document.getElementById('notificationsList');
        if (notificationsList && notificationsList.innerHTML.includes('No notifications')) {
            notificationsList.innerHTML = '<li><a class="dropdown-item notification-item unread" href="#">New notification</a></li>';
        }
    }
}

/**
 * Check for updates on current page via AJAX
 */
function checkPageUpdates() {
    if (!appState.isOnline) return;
    
    // This will be implemented to fetch page-specific updates via AJAX
    console.log('Checking for updates on ' + appState.currentPage);
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info', autohideDelay = 5000) {
    // Create toast container if not exists
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toastHTML = `
    <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="${autohideDelay !== 0}">
        <div class="toast-header bg-${type} text-white">
            <strong class="me-auto">Movers Taxi System</strong>
            <small>Just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    </div>
    `;
    
    // Add toast to container
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    // Initialize and show toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        delay: autohideDelay
    });
    toast.show();
    
    // Remove toast element after it's hidden
    if (autohideDelay !== 0) {
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
} 