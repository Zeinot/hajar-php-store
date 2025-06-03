<?php
/**
 * Cart API for handling cart operations (add, update, remove)
 * Returns JSON responses for AJAX requests
 */
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
// Comment these out in production
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in the output

// Initialize the response array
$response = [
    'status' => 'error',
    'message' => 'Invalid request',
    'data' => null
];

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;

// Log the request for debugging
logActivity('Cart API Request: ' . $method . ' ' . $action . ' - ' . json_encode($_POST), 'cart_api.log');

// Get database instance
$db = Database::getInstance();

// Initialize or get cart items
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

try {
    // Handle different request methods and actions
    switch ($method) {
        case 'GET':
            // Get cart items
            if ($action === 'get') {
                $cartItems = getCartItems();
                $cartTotal = getCartTotal();
                
                // Enhanced cart items with product details
                $enhancedCartItems = [];
                
                // If cart has items, get product details
                if (!empty($cartItems)) {
                    foreach ($cartItems as $index => $item) {
                        // Get product details
                        $stmt = $db->prepare("SELECT name, price, stock FROM products WHERE sku = ?");
                        $stmt->bind_param('s', $item['product_sku']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result && $result->num_rows > 0) {
                            $product = $result->fetch_assoc();
                            
                            // Get product image
                            $imgStmt = $db->prepare("SELECT image_path FROM product_images WHERE product_sku = ? AND is_primary = 1 LIMIT 1");
                            $imgStmt->bind_param('s', $item['product_sku']);
                            $imgStmt->execute();
                            $imgResult = $imgStmt->get_result();
                            
                            $imagePath = null;
                            if ($imgResult && $imgResult->num_rows > 0) {
                                $image = $imgResult->fetch_assoc();
                                $imagePath = $image['image_path'];
                            }
                            
                            // Combine item and product data
                            $enhancedCartItems[] = array_merge($item, [
                                'name' => $product['name'],
                                'price' => $product['price'],
                                'current_stock' => $product['stock'],
                                'image' => $imagePath,
                                'subtotal' => $product['price'] * $item['quantity']
                            ]);
                        }
                    }
                }
                
                $response = [
                    'status' => 'success',
                    'message' => 'Cart retrieved successfully',
                    'data' => [
                        'items' => $enhancedCartItems,
                        'total' => $cartTotal,
                        'itemCount' => count($cartItems),
                        'shipping' => $cartTotal >= 50 ? 0 : 5.99,
                        'orderTotal' => $cartTotal >= 50 ? $cartTotal : $cartTotal + 5.99
                    ]
                ];
            } elseif ($action === 'count') {
                // Get cart count only
                $cartItems = getCartItems();
                $cartCount = count($cartItems);
                
                $response = [
                    'status' => 'success',
                    'message' => 'Cart count retrieved successfully',
                    'data' => [
                        'count' => $cartCount
                    ]
                ];
            } elseif ($action === 'clear') {
                // Clear the cart
                $_SESSION['cart'] = [];
                
                $response = [
                    'status' => 'success',
                    'message' => 'Cart cleared successfully',
                    'data' => [
                        'count' => 0,
                        'total' => 0
                    ]
                ];
            }
            break;
            
        case 'POST':
            // Add item to cart
            if ($action === 'add') {
                // Required parameters
                $productSku = isset($_POST['product_sku']) ? sanitize($_POST['product_sku']) : null;
                $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
                
                // Optional parameters
                $size = isset($_POST['size']) ? sanitize($_POST['size']) : null;
                $color = isset($_POST['color']) ? sanitize($_POST['color']) : null;
                
                // Validate parameters
                if (!$productSku) {
                    throw new Exception('Product SKU is required');
                }
                
                if ($quantity <= 0) {
                    throw new Exception('Quantity must be greater than zero');
                }
                
                // Check if product exists and has enough stock
                $stmt = $db->prepare("SELECT stock FROM products WHERE sku = ?");
                $stmt->bind_param('s', $productSku);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if (!$result || $result->num_rows === 0) {
                    throw new Exception('Product not found');
                }
                
                $product = $result->fetch_assoc();
                
                if ($product['stock'] < $quantity) {
                    throw new Exception('Not enough stock available');
                }
                
                // Check stock for size if specified
                if ($size) {
                    $sizeStmt = $db->prepare("SELECT stock FROM product_sizes WHERE product_sku = ? AND size_name = ?");
                    $sizeStmt->bind_param('ss', $productSku, $size);
                    $sizeStmt->execute();
                    $sizeResult = $sizeStmt->get_result();
                    
                    if ($sizeResult && $sizeResult->num_rows > 0) {
                        $sizeStock = $sizeResult->fetch_assoc();
                        if ($sizeStock['stock'] < $quantity) {
                            throw new Exception('Not enough stock available for selected size');
                        }
                    }
                }
                
                // Check stock for color if specified
                if ($color) {
                    $colorStmt = $db->prepare("SELECT stock FROM product_colors WHERE product_sku = ? AND color_name = ?");
                    $colorStmt->bind_param('ss', $productSku, $color);
                    $colorStmt->execute();
                    $colorResult = $colorStmt->get_result();
                    
                    if ($colorResult && $colorResult->num_rows > 0) {
                        $colorStock = $colorResult->fetch_assoc();
                        if ($colorStock['stock'] < $quantity) {
                            throw new Exception('Not enough stock available for selected color');
                        }
                    }
                }
                
                // Add to cart (either add new item or update existing)
                $added = addToCart($productSku, $quantity, $size, $color);
                
                if ($added) {
                    $cartItems = getCartItems();
                    $cartTotal = getCartTotal();
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Item added to cart successfully',
                        'data' => [
                            'itemCount' => count($cartItems),
                            'total' => $cartTotal
                        ]
                    ];
                } else {
                    throw new Exception('Failed to add item to cart');
                }
            }
            break;
            
        case 'PUT':
            // Update cart item
            if ($action === 'update') {
                // Get PUT data
                parse_str(file_get_contents("php://input"), $putData);
                
                $itemId = isset($putData['id']) ? (int)$putData['id'] : null;
                $quantity = isset($putData['quantity']) ? (int)$putData['quantity'] : null;
                
                // Validate parameters
                if (!$itemId) {
                    throw new Exception('Item ID is required');
                }
                
                if (!$quantity || $quantity <= 0) {
                    throw new Exception('Quantity must be greater than zero');
                }
                
                // Find the item in cart
                $cartItems = &$_SESSION['cart'];
                $found = false;
                
                foreach ($cartItems as &$item) {
                    if ($item['id'] == $itemId) {
                        // Get product to check stock
                        $stmt = $db->prepare("SELECT stock FROM products WHERE sku = ?");
                        $stmt->bind_param('s', $item['product_sku']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if (!$result || $result->num_rows === 0) {
                            throw new Exception('Product not found');
                        }
                        
                        $product = $result->fetch_assoc();
                        
                        if ($product['stock'] < $quantity) {
                            throw new Exception('Not enough stock available');
                        }
                        
                        // Check stock for size if specified
                        if (isset($item['size']) && $item['size']) {
                            $sizeStmt = $db->prepare("SELECT stock FROM product_sizes WHERE product_sku = ? AND size_name = ?");
                            $sizeStmt->bind_param('ss', $item['product_sku'], $item['size']);
                            $sizeStmt->execute();
                            $sizeResult = $sizeStmt->get_result();
                            
                            if ($sizeResult && $sizeResult->num_rows > 0) {
                                $sizeStock = $sizeResult->fetch_assoc();
                                if ($sizeStock['stock'] < $quantity) {
                                    throw new Exception('Not enough stock available for selected size');
                                }
                            }
                        }
                        
                        // Check stock for color if specified
                        if (isset($item['color']) && $item['color']) {
                            $colorStmt = $db->prepare("SELECT stock FROM product_colors WHERE product_sku = ? AND color_name = ?");
                            $colorStmt->bind_param('ss', $item['product_sku'], $item['color']);
                            $colorStmt->execute();
                            $colorResult = $colorStmt->get_result();
                            
                            if ($colorResult && $colorResult->num_rows > 0) {
                                $colorStock = $colorResult->fetch_assoc();
                                if ($colorStock['stock'] < $quantity) {
                                    throw new Exception('Not enough stock available for selected color');
                                }
                            }
                        }
                        
                        // Update quantity
                        $item['quantity'] = $quantity;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    throw new Exception('Item not found in cart');
                }
                
                $cartTotal = getCartTotal();
                
                $response = [
                    'status' => 'success',
                    'message' => 'Cart item updated successfully',
                    'data' => [
                        'itemCount' => count($cartItems),
                        'total' => $cartTotal,
                        'shipping' => $cartTotal >= 50 ? 0 : 5.99,
                        'orderTotal' => $cartTotal >= 50 ? $cartTotal : $cartTotal + 5.99
                    ]
                ];
            }
            break;
            
        case 'DELETE':
            // Remove item from cart
            if ($action === 'remove') {
                // Get DELETE data
                parse_str(file_get_contents("php://input"), $deleteData);
                
                $itemId = isset($deleteData['id']) ? (int)$deleteData['id'] : null;
                
                // Validate parameters
                if (!$itemId) {
                    throw new Exception('Item ID is required');
                }
                
                // Remove from cart
                $removed = removeFromCart($itemId);
                
                if ($removed) {
                    $cartItems = getCartItems();
                    $cartTotal = getCartTotal();
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Item removed from cart successfully',
                        'data' => [
                            'itemCount' => count($cartItems),
                            'total' => $cartTotal,
                            'shipping' => $cartTotal >= 50 ? 0 : 5.99,
                            'orderTotal' => $cartTotal >= 50 ? $cartTotal : $cartTotal + 5.99
                        ]
                    ];
                } else {
                    throw new Exception('Failed to remove item from cart');
                }
            }
            break;
            
        default:
            throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    // Log error
    logActivity('Cart API Error: ' . $e->getMessage(), 'error', ROOT_PATH . '/logs/cart_api_errors.log');
}

// Return JSON response
echo json_encode($response);
exit;
?>
