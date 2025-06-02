<?php
/**
 * Helper functions for Elegant Drapes luxury clothing store
 */

// Start session if not already started
function start_session_if_not_started() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Function to log messages
function log_message($message, $type = 'info') {
    $log_file = __DIR__ . "/../logs/" . date('Y-m-d') . ".log";
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$type]: $message" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!is_dir(__DIR__ . "/../logs")) {
        mkdir(__DIR__ . "/../logs", 0755, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Function to log errors (alias to log_message with type=error)
function logError($message) {
    log_message($message, 'error');
}

// Check if user is logged in
function is_logged_in() {
    start_session_if_not_started();
    return isset($_SESSION['user_id']);
}

// Alias for is_logged_in
function isLoggedIn() {
    return is_logged_in();
}

// Check if user is admin
function is_admin() {
    start_session_if_not_started();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Redirect to a URL
function redirect($url) {
    header("Location: $url");
    exit;
}

// Sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Alias for sanitize_input
function sanitizeInput($data) {
    return sanitize_input($data);
}

// Generate a random string (for creating SKUs, etc.)
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }
    
    return $randomString;
}

// Format price
function format_price($price) {
    return '$' . number_format($price, 2);
}

// Function to upload an image
function upload_image($file, $destination_folder) {
    // Create the folder if it doesn't exist
    if (!is_dir($destination_folder)) {
        mkdir($destination_folder, 0755, true);
    }
    
    $target_file = $destination_folder . basename($file["name"]);
    $upload_ok = 1;
    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ["success" => false, "message" => "File is not an image."];
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "File is too large. Maximum size is 5MB."];
    }
    
    // Allow certain file formats
    if ($image_file_type != "jpg" && $image_file_type != "png" && $image_file_type != "jpeg" && $image_file_type != "gif") {
        return ["success" => false, "message" => "Only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    // Generate a unique filename to prevent overwriting
    $new_filename = uniqid() . '.' . $image_file_type;
    $target_file = $destination_folder . $new_filename;
    
    // Try to upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "file_path" => $target_file, "filename" => $new_filename];
    } else {
        return ["success" => false, "message" => "Error uploading file."];
    }
}

// Get page title based on current page
function get_page_title($default = "E-commerce Store") {
    $page = basename($_SERVER['PHP_SELF'], '.php');
    
    $titles = [
        'index' => 'Home',
        'shop' => 'Shop',
        'cart' => 'Shopping Cart',
        'checkout' => 'Checkout',
        'login' => 'Login',
        'register' => 'Register',
        'account' => 'My Account',
        'product' => 'Product Details',
        'admin' => 'Admin Dashboard',
        'admin_products' => 'Manage Products',
        'admin_categories' => 'Manage Categories',
        'admin_orders' => 'Manage Orders',
        'admin_users' => 'Manage Users',
    ];
    
    return isset($titles[$page]) ? $titles[$page] . ' - ' . $default : $default;
}

// Check if the request is an AJAX request
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Function to return JSON response
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Get database connection
function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        require_once __DIR__ . '/../config/database.php';
        
        // Create connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            log_message("Database connection failed: " . $conn->connect_error, 'error');
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

// Get all categories
function get_all_categories($conn) {
    $sql = "SELECT * FROM categories ORDER BY name";
    $result = $conn->query($sql);
    
    $categories = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    return $categories;
}

// Get all sizes
function get_all_sizes($conn) {
    $sql = "SELECT * FROM sizes ORDER BY name";
    $result = $conn->query($sql);
    
    $sizes = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sizes[] = $row;
        }
    }
    
    return $sizes;
}

// Get all colors
function get_all_colors($conn) {
    $sql = "SELECT * FROM colors ORDER BY name";
    $result = $conn->query($sql);
    
    $colors = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $colors[] = $row;
        }
    }
    
    return $colors;
}

// Get product details by SKU
function get_product_by_sku($conn, $sku) {
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.sku = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Get product images
        $sql = "SELECT * FROM product_images WHERE product_sku = ? ORDER BY is_primary DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $images_result = $stmt->get_result();
        
        $product['images'] = [];
        while ($image = $images_result->fetch_assoc()) {
            $product['images'][] = $image;
        }
        
        // Get product sizes
        $sql = "SELECT ps.*, s.name FROM product_sizes ps 
                JOIN sizes s ON ps.size_name = s.name 
                WHERE ps.product_sku = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $sizes_result = $stmt->get_result();
        
        $product['sizes'] = [];
        while ($size = $sizes_result->fetch_assoc()) {
            $product['sizes'][] = $size;
        }
        
        // Get product colors
        $sql = "SELECT pc.*, c.name FROM product_colors pc 
                JOIN colors c ON pc.color_name = c.name 
                WHERE pc.product_sku = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $sku);
        $stmt->execute();
        $colors_result = $stmt->get_result();
        
        $product['colors'] = [];
        while ($color = $colors_result->fetch_assoc()) {
            $product['colors'][] = $color;
        }
        
        return $product;
    }
    
    return null;
}

// Function to generate pagination
function generate_pagination($current_page, $total_pages, $url_pattern) {
    $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    $prev_disabled = ($current_page <= 1) ? 'disabled' : '';
    $prev_url = sprintf($url_pattern, $current_page - 1);
    $pagination .= '<li class="page-item ' . $prev_disabled . '"><a class="page-link" href="' . $prev_url . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $page_url = sprintf($url_pattern, $i);
        $pagination .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
    }
    
    // Next button
    $next_disabled = ($current_page >= $total_pages) ? 'disabled' : '';
    $next_url = sprintf($url_pattern, $current_page + 1);
    $pagination .= '<li class="page-item ' . $next_disabled . '"><a class="page-link" href="' . $next_url . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    
    $pagination .= '</ul></nav>';
    
    return $pagination;
}
