<?php
session_start();
require_once 'functions/auth.php';

// If user is not logged in, redirect to landing page instead of login page
if (!isLoggedIn()) {
    header('Location: landing.php');
    exit;
}

// Get user role and default page
$userRole = $_SESSION['user_role'] ?? '';
$page = $_GET['page'] ?? 'dashboard';

// Load the appropriate dashboard based on role
if ($page === 'dashboard') {
    switch ($userRole) {
        case 'super_admin':
            $includePage = 'pages/dashboard_super_admin.php';
            break;
        case 'dispatch':
            $includePage = 'pages/dashboard_dispatch.php';
            break;
        case 'finance':
            $includePage = 'pages/dashboard_finance.php';
            break;
        case 'driver':
            $includePage = 'pages/dashboard_driver.php';
            break;
        case 'customer':
            $includePage = 'pages/dashboard_customer.php';
            break;
        default:
            // Default dashboard if role doesn't match any specific dashboard
            $includePage = 'pages/dashboard.php';
    }
} else {
    // For non-dashboard pages, check if the page exists
    $includePage = 'pages/' . $page . '.php';
    
    // Special handling for activity logs page
    if ($page === 'activity_logs') {
        // Check for activity logs permission
        if (!hasPermission('view_activity_logs') && $userRole !== 'super_admin') {
            $_SESSION['error'] = "You don't have permission to access the activity logs page.";
            $includePage = 'pages/access_denied.php';
        }
    } else {
        // Check if user has permission to access this page
        $requiredPermission = 'access_' . $page;
        
        // Debug log - comment out in production
        // error_log("Checking permission: $requiredPermission for user role: $userRole");
        
        // Super admin bypasses permission checks
        if ($userRole !== 'super_admin' && !hasPermission($requiredPermission)) {
            $_SESSION['error'] = "You don't have permission to access the {$page} page.";
            $includePage = 'pages/access_denied.php';
        }
    }
}

// Check if the file exists
if (!file_exists($includePage)) {
    $includePage = 'pages/not_found.php';
}

// Define a constant to indicate pages are included via index.php
if (!defined('IS_INCLUDED')) {
    define('IS_INCLUDED', true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movers Taxi System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php include $includePage; ?>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/js/app.js"></script>

</body>
</html> 