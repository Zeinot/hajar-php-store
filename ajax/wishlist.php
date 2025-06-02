<?php
/**
 * AJAX handler for wishlist operations
 */
require_once '../config/database.php';
require_once '../includes/functions.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';
    $product_sku = isset($_POST['product_sku']) ? sanitize_input($_POST['product_sku']) : '';
    
    if (empty($product_sku)) {
        $response['message'] = 'Product SKU is required';
        json_response($response, 400);
    }
    
    // Check if product exists
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT sku FROM products WHERE sku = ?");
        $stmt->bind_param("s", $product_sku);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'Product not found';
            json_response($response, 404);
        }
        
        $stmt->close();
        
        // Handle different actions
        switch ($action) {
            case 'add':
                if (!isLoggedIn()) {
                    // Store in session for adding after login
                    if ($action === 'remember') {
                        $_SESSION['wishlist_after_login'] = $product_sku;
                        $response['success'] = true;
                        $response['message'] = 'Product will be added to wishlist after login';
                        json_response($response);
                    }
                    
                    $response['success'] = false;
                    $response['message'] = 'login_required';
                    json_response($response, 401);
                }
                
                $user_id = $_SESSION['user_id'];
                
                // Check if already in wishlist
                $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_sku = ?");
                $stmt->bind_param("is", $user_id, $product_sku);
                $stmt->execute();
                $check_result = $stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $response['message'] = 'already_in_wishlist';
                    json_response($response);
                }
                
                $stmt->close();
                
                // Add to wishlist
                $stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_sku, added_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("is", $user_id, $product_sku);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Product added to wishlist';
                } else {
                    logError("Failed to add product to wishlist: " . $stmt->error);
                    $response['message'] = 'Database error';
                }
                
                $stmt->close();
                break;
                
            case 'remove':
                if (!isLoggedIn()) {
                    $response['message'] = 'login_required';
                    json_response($response, 401);
                }
                
                $user_id = $_SESSION['user_id'];
                
                // Remove from wishlist
                $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_sku = ?");
                $stmt->bind_param("is", $user_id, $product_sku);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Product removed from wishlist';
                } else {
                    logError("Failed to remove product from wishlist: " . $stmt->error);
                    $response['message'] = 'Database error';
                }
                
                $stmt->close();
                break;
                
            case 'remember':
                // Just store the product SKU in session for adding after login
                $_SESSION['wishlist_after_login'] = $product_sku;
                $response['success'] = true;
                $response['message'] = 'Product will be added to wishlist after login';
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
        
        $conn->close();
    } catch (Exception $e) {
        logError("Wishlist operation error: " . $e->getMessage());
        $response['message'] = 'An error occurred';
    }
}

// Return JSON response
json_response($response);
