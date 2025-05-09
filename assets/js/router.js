/**
 * Movers Taxi System - SPA Router
 * Handles navigation and content loading without full page refreshes
 */

const Router = {
    routes: {},
    currentPage: '',
    contentDiv: null,
    
    // Initialize router
    init: function() {
        this.contentDiv = document.getElementById('content');
        this.currentPage = this.getPageFromUrl();
        
        this.registerRoutes();
        this.setupEventListeners();
        
        // Handle initial page load
        if (history.state && history.state.page) {
            this.loadContent(history.state.page);
        }
        
        console.log('Router initialized with current page: ' + this.currentPage);
    },
    
    // Register available routes
    registerRoutes: function() {
        // Core pages
        this.routes['dashboard'] = { url: 'pages/dashboard.php', title: 'Dashboard' };
        
        // Core 1 pages
        this.routes['fleet'] = { url: 'pages/fleet.php', title: 'Fleet Management' };
        this.routes['drivers'] = { url: 'pages/drivers.php', title: 'Driver Management' };
        this.routes['dispatch'] = { url: 'pages/dispatch.php', title: 'Taxi Dispatch' };
        this.routes['customers'] = { url: 'pages/customers.php', title: 'Customer Management' };
        this.routes['fuel'] = { url: 'pages/fuel.php', title: 'Fuel Monitoring' };
        
        // Core 2 pages
        this.routes['storeroom'] = { url: 'pages/storeroom.php', title: 'Storeroom Management' };
        this.routes['booking'] = { url: 'pages/booking.php', title: 'Booking Management' };
        this.routes['gps'] = { url: 'pages/gps.php', title: 'GPS Tracking' };
        this.routes['payment'] = { url: 'pages/payment.php', title: 'Payment Management' };
        this.routes['users'] = { url: 'pages/users.php', title: 'User Maintenance' };
        this.routes['analytics'] = { url: 'pages/analytics.php', title: 'Transport Analytics' };
        
        // Common pages
        this.routes['profile'] = { url: 'pages/profile.php', title: 'My Profile' };
        this.routes['settings'] = { url: 'pages/settings.php', title: 'System Settings' };
        this.routes['logs'] = { url: 'pages/logs.php', title: 'Activity Logs' };
    },
    
    // Set up event listeners for navigation
    setupEventListeners: function() {
        // Handle link clicks for navigation
        document.addEventListener('click', (e) => {
            // Find closest anchor element
            const link = e.target.closest('a');
            
            if (link && link.href.includes('index.php?page=')) {
                e.preventDefault();
                
                const url = new URL(link.href);
                const page = url.searchParams.get('page');
                
                if (page && this.routes[page]) {
                    this.navigateTo(page);
                }
            }
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.page) {
                this.loadContent(e.state.page, false);
            } else {
                this.loadContent(this.getPageFromUrl(), false);
            }
        });
    },
    
    // Navigate to a page
    navigateTo: function(page) {
        // If already on the page, don't reload
        if (this.currentPage === page) return;
        
        // Update URL and history
        const url = 'index.php?page=' + page;
        history.pushState({ page: page }, this.routes[page].title, url);
        
        // Load the page content
        this.loadContent(page);
    },
    
    // Load content from server
    loadContent: function(page, updateHistory = true) {
        if (!this.contentDiv) return;
        
        // If page route exists
        if (this.routes[page]) {
            // Show loading indicator
            this.contentDiv.classList.add('loading');
            this.contentDiv.innerHTML = this.getLoadingHTML();
            
            // Update current page
            this.currentPage = page;
            
            // Update page title
            document.title = this.routes[page].title + ' - Movers Taxi System';
            
            // Update URL if needed
            if (updateHistory) {
                const url = 'index.php?page=' + page;
                history.pushState({ page: page }, this.routes[page].title, url);
            }
            
            // Update sidebar active state
            this.updateSidebarActiveState(page);
            
            // Load content via AJAX
            fetch(this.routes[page].url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    this.contentDiv.innerHTML = html;
                    this.contentDiv.classList.remove('loading');
                    
                    // Dispatch event for content loaded
                    const event = new CustomEvent('contentLoaded', { detail: { page: page } });
                    document.dispatchEvent(event);
                })
                .catch(error => {
                    console.error('Error loading content:', error);
                    this.contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h4 class="alert-heading">Error Loading Content</h4>
                            <p>There was a problem loading the requested page. Please try again.</p>
                            <hr>
                            <p class="mb-0">Error: ${error.message}</p>
                        </div>
                    `;
                    this.contentDiv.classList.remove('loading');
                });
        } else {
            console.error('Page not found:', page);
            this.contentDiv.innerHTML = '<div class="alert alert-warning">Page not found.</div>';
        }
    },
    
    // Get current page from URL
    getPageFromUrl: function() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('page') || 'dashboard';
    },
    
    // Update sidebar active state
    updateSidebarActiveState: function(page) {
        // Remove active class from all links
        document.querySelectorAll('#sidebarMenu .nav-link').forEach(link => {
            link.classList.remove('active');
            link.setAttribute('aria-current', 'false');
        });
        
        // Add active class to current page link
        const activeLink = document.querySelector(`#sidebarMenu .nav-link[href="index.php?page=${page}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
            activeLink.setAttribute('aria-current', 'page');
        }
    },
    
    // Get loading HTML
    getLoadingHTML: function() {
        return `
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading content...</p>
            </div>
        `;
    }
};

// Initialize router when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    Router.init();
}); 