<?php
// Ensure this file is included through the index.php route
if (!defined('IS_INCLUDED')) {
    header('Location: ../index.php?page=playground_booking');
    exit;
}

// Include the actual playground booking page
include_once 'playground_booking.php';
?> 