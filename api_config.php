<?php
/**
 * API Configuration Template
 * 
 * Copy this file to your frontend repository and rename it to 'api_config.php'
 * Update the values according to your backend API deployment
 */

// Backend API URL - update this to your backend API endpoint
define('API_BASE_URL', 'YOUR_BACKEND_API_URL');

// API Authentication settings
define('API_KEY', 'YOUR_API_KEY'); // Set this to your secure API key
define('API_TIMEOUT', 30); // Request timeout in seconds

// Enable/disable debug mode
define('API_DEBUG', false);

/**
 * Example usage:
 * 
 * require_once 'api_config.php';
 * 
 * $apiUrl = API_BASE_URL . '/endpoint';
 * // Use in your AJAX calls or fetch requests
 */
?> 