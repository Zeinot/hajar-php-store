<?php
/**
 * Admin Authentication Helper for Elegant Drapes
 * This file provides functions for admin authentication and authorization
 */

/**
 * Check if admin user is logged in
 * @return bool True if admin is logged in, false otherwise
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

/**
 * Ensure admin is logged in, redirect to login if not
 * @return void
 */
function require_admin_login() {
    if (!is_admin_logged_in()) {
        // Save current URL to redirect back after login
        $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
        exit;
    }
}

/**
 * Check if admin has required role
 * @param string|array $required_roles Required role or array of roles
 * @return bool True if admin has required role, false otherwise
 */
function has_admin_role($required_roles) {
    if (!is_admin_logged_in()) {
        return false;
    }
    
    $admin_role = $_SESSION['admin_role'];
    
    // If no specific role is required
    if (empty($required_roles)) {
        return true;
    }
    
    // Convert single role to array for consistent handling
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    // Admin role 'superadmin' has access to everything
    if ($admin_role === 'superadmin') {
        return true;
    }
    
    return in_array($admin_role, $required_roles);
}

/**
 * Ensure admin has required role, redirect or show error if not
 * @param string|array $required_roles Required role or array of roles
 * @param bool $redirect Whether to redirect (true) or show error (false)
 * @return void
 */
function require_admin_role($required_roles, $redirect = true) {
    require_admin_login();
    
    if (!has_admin_role($required_roles)) {
        log_message("Access denied: Admin ID {$_SESSION['admin_id']} attempted to access restricted area requiring role(s): " . 
            (is_array($required_roles) ? implode(', ', $required_roles) : $required_roles), 'warning');
        
        if ($redirect) {
            $_SESSION['flash_message'] = 'You do not have permission to access that area.';
            $_SESSION['flash_type'] = 'danger';
            redirect('index.php');
        } else {
            http_response_code(403);
            include_once 'templates/header.php';
            echo '<div class="container mt-5">';
            echo '<div class="alert alert-danger">You do not have permission to access this area.</div>';
            echo '<a href="index.php" class="btn btn-primary">Return to Dashboard</a>';
            echo '</div>';
            include_once 'templates/footer.php';
            exit;
        }
    }
}

/**
 * Log out admin user
 * @return void
 */
function admin_logout() {
    // Log the logout event
    if (isset($_SESSION['admin_id'])) {
        log_message("Admin logout: ID {$_SESSION['admin_id']}, Email: {$_SESSION['admin_email']}", 'info');
    }
    
    // Unset all admin session variables
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_role']);
    
    // Optional: regenerate session ID for security
    session_regenerate_id(true);
}
