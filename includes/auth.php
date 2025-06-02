<?php
/**
 * Authentication functions for the e-commerce website
 */
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

/**
 * Register a new user
 * 
 * @param string $fullName User's full name
 * @param string $email User's email
 * @param string $password User's password
 * @param string $phone User's phone (optional)
 * @param string $address User's address (optional)
 * @return int|bool User ID on success, false on failure
 */
function registerUser($fullName, $email, $password, $phone = null, $address = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Check if email already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            logActivity("Registration failed: Email already exists - $email", 'error');
            return false;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare insert statement
        $stmt = $db->prepare("INSERT INTO users (full_name, email, password_hash, phone, shipping_address, status, role) VALUES (?, ?, ?, ?, ?, 1, 'customer')");
        $stmt->bind_param("sssss", $fullName, $email, $passwordHash, $phone, $address);
        
        if ($stmt->execute()) {
            $userId = $db->getLastId();
            logActivity("New user registered: $email (ID: $userId)", 'info');
            return $userId;
        } else {
            logActivity("Registration failed: Database error", 'error');
            return false;
        }
    } catch (Exception $e) {
        logActivity("Registration exception: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Login a user
 * 
 * @param string $email User's email
 * @param string $password User's password
 * @return bool Success status
 */
function loginUser($email, $password) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Get user by email
        $stmt = $db->prepare("SELECT id, full_name, email, password_hash, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("Login failed: Email not found - $email", 'error');
            return false;
        }
        
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] != 1) {
            logActivity("Login failed: Account inactive - $email", 'error');
            return false;
        }
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Start session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            logActivity("User logged in: $email", 'info');
            return true;
        } else {
            logActivity("Login failed: Invalid password - $email", 'error');
            return false;
        }
    } catch (Exception $e) {
        logActivity("Login exception: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Logout a user
 */
function logoutUser() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log activity if user was logged in
    if (isset($_SESSION['user_email'])) {
        logActivity("User logged out: " . $_SESSION['user_email'], 'info');
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Get current user data
 * 
 * @return array|bool User data or false if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userId = $_SESSION['user_id'];
    $db = Database::getInstance();
    
    try {
        $stmt = $db->prepare("SELECT id, full_name, email, phone, shipping_address, role, status FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    } catch (Exception $e) {
        logActivity("Get current user exception: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Change user password
 * 
 * @param int $userId User ID
 * @param string $currentPassword Current password
 * @param string $newPassword New password
 * @return bool Success status
 */
function changePassword($userId, $currentPassword, $newPassword) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Get current password hash
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logActivity("Change password failed: User not found - ID: $userId", 'error');
            return false;
        }
        
        $user = $result->fetch_assoc();
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            logActivity("Change password failed: Incorrect current password - ID: $userId", 'error');
            return false;
        }
        
        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newPasswordHash, $userId);
        
        if ($updateStmt->execute()) {
            logActivity("Password changed for user ID: $userId", 'info');
            return true;
        } else {
            logActivity("Change password failed: Database error", 'error');
            return false;
        }
    } catch (Exception $e) {
        logActivity("Change password exception: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Update user profile
 * 
 * @param int $userId User ID
 * @param string $fullName User's full name
 * @param string $phone User's phone (optional)
 * @param string $address User's address (optional)
 * @return bool Success status
 */
function updateProfile($userId, $fullName, $phone = null, $address = null) {
    $db = Database::getInstance();
    
    try {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, shipping_address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $fullName, $phone, $address, $userId);
        
        if ($stmt->execute()) {
            logActivity("Profile updated for user ID: $userId", 'info');
            return true;
        } else {
            logActivity("Profile update failed: Database error", 'error');
            return false;
        }
    } catch (Exception $e) {
        logActivity("Profile update exception: " . $e->getMessage(), 'error');
        return false;
    }
}
