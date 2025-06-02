<?php
/**
 * Logout page for e-commerce website
 */
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the activity
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $userEmail = $_SESSION['user_email'];
    logActivity("User logged out: $userEmail (ID: $userId)", 'info');
}

// Log out the user
logoutUser();

// Set success message
setFlashMessage('You have been logged out successfully!', 'success');

// Redirect to home page
redirect(SITE_URL);
?>
