<?php
/**
 * AJAX handler for shopping cart operations
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
    // Start session if not already started
    start_session_if_not_started();
    
    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : 'add';
    $product_sku = isset($_POST['product_sku']) ? sanitize_input($_POST['product_sku']) : '';
    
    // Default action is 'add' if not specified
    if (empty($product_sku) && $action !== 'clear') {
        $response['message'] = 'Product SKU is required';
        json_response($response, 400);
    }
    
    try {
        $conn = getDbConnection();
        
        switch ($action) {
            case 'add':
                // Get product details
                $stmt = $conn->prepare("
                    SELECT p.*, 
                    (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image
                    FROM products p
                    WHERE p.sku = ?
                ");
                $stmt->bind_param("s", $product_sku);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $response['message'] = 'Product not found';
                    json_response($response, 404);
                }
                
                $product = $result->fetch_assoc();
                $stmt->close();
                
                // Check if product is in stock
                if ($product['stock'] <= 0) {
                    $response['message'] = 'Product is out of stock';
                    json_response($response, 400);
                }
                
                // Get quantity, size, color from POST
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
                $size = isset($_POST['size']) ? sanitize_input($_POST['size']) : '';
                $color = isset($_POST['color']) ? sanitize_input($_POST['color']) : '';
                
                // Validate quantity
                if ($quantity <= 0) {
                    $quantity = 1;
                }
                
                if ($quantity > $product['stock']) {
                    $quantity = $product['stock'];
                }
                
                // Calculate price based on size and color selections
                $base_price = !empty($product['sale_price']) ? $product['sale_price'] : $product['price'];
                $additional_price = 0;
                
                // Add price for size if selected
                if (!empty($size)) {
                    $stmt = $conn->prepare("SELECT additional_price FROM product_sizes WHERE product_sku = ? AND size_name = ?");
                    $stmt->bind_param("ss", $product_sku, $size);
                    $stmt->execute();
                    $size_result = $stmt->get_result();
                    
                    if ($size_result->num_rows > 0) {
                        $size_data = $size_result->fetch_assoc();
                        $additional_price += $size_data['additional_price'];
                    }
                    
                    $stmt->close();
                }
                
                // Add price for color if selected
                if (!empty($color)) {
                    $stmt = $conn->prepare("SELECT additional_price FROM product_colors WHERE product_sku = ? AND color_name = ?");
                    $stmt->bind_param("ss", $product_sku, $color);
                    $stmt->execute();
                    $color_result = $stmt->get_result();
                    
                    if ($color_result->num_rows > 0) {
                        $color_data = $color_result->fetch_assoc();
                        $additional_price += $color_data['additional_price'];
                    }
                    
                    $stmt->close();
                }
                
                // Final price including options
                $item_price = $base_price + $additional_price;
                
                // Create unique cart item ID based on product and options
                $cart_item_id = $product_sku;
                if (!empty($size)) $cart_item_id .= '_' . $size;
                if (!empty($color)) $cart_item_id .= '_' . $color;
                
                // Check if item already exists in cart
                if (isset($_SESSION['cart'][$cart_item_id])) {
                    // Update quantity
                    $_SESSION['cart'][$cart_item_id]['quantity'] += $quantity;
                    
                    // Make sure we don't exceed stock
                    if ($_SESSION['cart'][$cart_item_id]['quantity'] > $product['stock']) {
                        $_SESSION['cart'][$cart_item_id]['quantity'] = $product['stock'];
                    }
                } else {
                    // Add new item to cart
                    $_SESSION['cart'][$cart_item_id] = [
                        'sku' => $product_sku,
                        'name' => $product['name'],
                        'price' => $item_price,
                        'quantity' => $quantity,
                        'size' => $size,
                        'color' => $color,
                        'image' => $product['image'],
                        'max_quantity' => $product['stock']
                    ];
                }
                
                $response['success'] = true;
                $response['message'] = 'Product added to cart';
                $response['cart_count'] = count($_SESSION['cart']);
                $response['cart_item'] = $_SESSION['cart'][$cart_item_id];
                break;
                
            case 'update':
                $cart_item_id = $product_sku; // This should be the full cart_item_id in this case
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
                
                if (isset($_SESSION['cart'][$cart_item_id])) {
                    if ($quantity <= 0) {
                        // Remove item if quantity is 0 or negative
                        unset($_SESSION['cart'][$cart_item_id]);
                    } else {
                        // Make sure we don't exceed stock
                        $max_quantity = $_SESSION['cart'][$cart_item_id]['max_quantity'];
                        if ($quantity > $max_quantity) {
                            $quantity = $max_quantity;
                        }
                        
                        // Update quantity
                        $_SESSION['cart'][$cart_item_id]['quantity'] = $quantity;
                    }
                    
                    $response['success'] = true;
                    $response['message'] = 'Cart updated';
                    $response['cart_count'] = count($_SESSION['cart']);
                } else {
                    $response['message'] = 'Item not found in cart';
                }
                break;
                
            case 'remove':
                $cart_item_id = $product_sku; // This should be the full cart_item_id in this case
                
                if (isset($_SESSION['cart'][$cart_item_id])) {
                    unset($_SESSION['cart'][$cart_item_id]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Item removed from cart';
                    $response['cart_count'] = count($_SESSION['cart']);
                } else {
                    $response['message'] = 'Item not found in cart';
                }
                break;
                
            case 'clear':
                // Clear entire cart
                $_SESSION['cart'] = [];
                
                $response['success'] = true;
                $response['message'] = 'Cart cleared';
                $response['cart_count'] = 0;
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
        
        $conn->close();
    } catch (Exception $e) {
        logError("Cart operation error: " . $e->getMessage());
        $response['message'] = 'An error occurred';
    }
}

// Return JSON response
json_response($response);
