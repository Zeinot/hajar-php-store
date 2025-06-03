<?php
/**
 * Utility functions for the e-commerce website
 */
require_once 'config.php';
require_once 'database.php';

/**
 * Sanitize input data
 * 
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Log activity or errors
 * 
 * @param string $message Message to log
 * @param string $type Type of log (error, info, debug)
 * @param string $file Optional specific log file
 * @return bool Success status
 */
function logActivity($message, $type = 'info', $file = null) {
    if ($file === null) {
        $file = ROOT_PATH . '/logs/' . $type . '.log';
    }
    
    $dir = dirname($file);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Write to log file
    return file_put_contents($file, $logMessage, FILE_APPEND);
}

/**
 * Redirect to a URL
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if user is logged in
 * 
 * @return bool Login status
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 * 
 * @return bool Admin status
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Generate a random string
 * 
 * @param int $length Length of string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charLength - 1)];
    }
    
    return $randomString;
}

/**
 * Format price with currency
 * 
 * @param float $price Price to format
 * @param string $currency Currency symbol
 * @return string Formatted price
 */
function formatPrice($price, $currency = '$') {
    return $currency . number_format($price, 2, '.', ',');
}

/**
 * Upload file and return file path
 * 
 * @param array $file $_FILES array element
 * @param string $destination Destination directory
 * @param array $allowedTypes Allowed file types
 * @param int $maxSize Maximum file size in bytes
 * @return string|bool File path or false on failure
 */
function uploadFile($file, $destination, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        logActivity("File upload error: " . $file['error'], 'error');
        return false;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        logActivity("File too large: " . $file['name'], 'error');
        return false;
    }
    
    // Check file type
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedTypes)) {
        logActivity("Invalid file type: " . $fileExtension, 'error');
        return false;
    }
    
    // Create destination directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }
    
    // Generate unique filename
    $filename = generateRandomString() . '_' . time() . '.' . $fileExtension;
    $targetPath = $destination . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    } else {
        logActivity("Failed to move uploaded file: " . $file['name'], 'error');
        return false;
    }
}

/**
 * Paginate results
 * 
 * @param int $totalItems Total number of items
 * @param int $currentPage Current page number
 * @param int $itemsPerPage Items per page
 * @param string $urlPattern URL pattern for pagination links
 * @return array Pagination data
 */
function paginate($totalItems, $currentPage = 1, $itemsPerPage = 10, $urlPattern = '?page=(:num)') {
    $currentPage = max(1, $currentPage);
    $totalPages = ceil($totalItems / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    $pagination = [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage - 1,
        'next_page' => $currentPage + 1,
        'first_page' => 1,
        'last_page' => $totalPages,
        'pages' => [],
        'links' => []
    ];
    
    // Generate page numbers to show
    $range = 2; // Show 2 pages before and after current page
    
    for ($i = max(1, $currentPage - $range); $i <= min($totalPages, $currentPage + $range); $i++) {
        $pagination['pages'][] = $i;
        $pagination['links'][$i] = str_replace('(:num)', $i, $urlPattern);
    }
    
    return $pagination;
}

/**
 * Display flash messages
 * 
 * @return void
 */
function displayFlashMessages() {
    $flashMessage = getFlashMessage();
    
    if ($flashMessage) {
        $alertClass = 'alert-info';
        
        switch ($flashMessage['type']) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
            case 'danger':
                $alertClass = 'alert-danger';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
        }
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo $flashMessage['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Get cart items from session
 * 
 * @return array Cart items
 */
function getCartItems() {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

/**
 * Get cart total
 * 
 * @return float Cart total
 */
function getCartTotal() {
    $total = 0;
    $cartItems = getCartItems();
    
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    return $total;
}

/**
 * Add item to cart
 * 
 * @param string $productSku Product SKU
 * @param int $quantity Quantity to add
 * @param string|null $size Product size (optional)
 * @param string|null $color Product color (optional)
 * @return bool Success status
 */
function addToCart($productSku, $quantity, $size = null, $color = null) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Initialize cart if not set
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Get product price from database
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT price FROM products WHERE sku = ?");
    $stmt->bind_param('s', $productSku);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        logActivity("Failed to add item to cart: Product not found (SKU: $productSku)", 'error', ROOT_PATH . '/logs/cart_errors.log');
        return false;
    }
    
    $product = $result->fetch_assoc();
    $price = $product['price'];
    
    // Check if item already exists in cart
    $found = false;
    $itemId = null;
    
    foreach ($_SESSION['cart'] as $index => $item) {
        if ($item['product_sku'] === $productSku && 
            $item['size'] === $size && 
            $item['color'] === $color) {
            // Update quantity if item already exists
            $_SESSION['cart'][$index]['quantity'] += $quantity;
            $found = true;
            $itemId = $index;
            break;
        }
    }
    
    // Add new item if not found
    if (!$found) {
        $itemId = uniqid('cart_');
        $_SESSION['cart'][] = [
            'id' => $itemId,
            'product_sku' => $productSku,
            'quantity' => $quantity,
            'size' => $size,
            'color' => $color,
            'price' => $price,
            'added_at' => time()
        ];
    }
    
    logActivity("Item added to cart: $productSku (Qty: $quantity, Size: $size, Color: $color)", 'info', ROOT_PATH . '/logs/cart.log');
    return true;
}

/**
 * Remove item from cart
 * 
 * @param string $itemId Item ID to remove
 * @return bool Success status
 */
function removeFromCart($itemId) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if cart exists
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return false;
    }
    
    // Find and remove the item
    foreach ($_SESSION['cart'] as $index => $item) {
        if (isset($item['id']) && $item['id'] === $itemId) {
            // Log before removing
            logActivity("Item removed from cart: {$item['product_sku']} (Qty: {$item['quantity']})", 'info', ROOT_PATH . '/logs/cart.log');
            
            // Remove item
            unset($_SESSION['cart'][$index]);
            
            // Reindex array
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            
            return true;
        }
    }
    
    return false;
}

/**
 * Display flash message
 * 
 * @param string $message Message to display
 * @param string $type Message type (success, danger, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get flash message and clear it
 * 
 * @return array|null Flash message or null if none
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}
