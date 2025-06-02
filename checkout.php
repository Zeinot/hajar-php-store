<?php
/**
 * Checkout Page for Elegant Drapes luxury clothing store
 * Implements Cash on Delivery (COD) payment method
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect to cart if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    redirect('cart.php');
}

// Check if user is logged in
if (!is_logged_in()) {
    // Save current URL to redirect back after login
    $_SESSION['redirect_after_login'] = 'checkout.php';
    redirect('login.php');
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;
$order_id = null;

// Get user details for pre-filling the form
try {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        log_message("User not found: $user_id", 'error');
        redirect('cart.php');
    }
    
    $stmt->close();
} catch (Exception $e) {
    log_message('Error retrieving user: ' . $e->getMessage(), 'error');
    redirect('cart.php');
}

// Calculate order totals
$subtotal = 0;
$shipping = 0;
$tax_rate = 0.07; // 7% tax rate
$tax = 0;
$total = 0;

// Calculate subtotal from cart items
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // Calculate shipping (free over $150)
    $shipping = ($subtotal >= 150) ? 0 : 12.99;
    
    // Calculate tax and total
    $tax = $subtotal * $tax_rate;
    $total = $subtotal + $shipping + $tax;
}

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Validate form inputs
    $full_name = isset($_POST['full_name']) ? sanitize_input($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
    $address = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';
    $city = isset($_POST['city']) ? sanitize_input($_POST['city']) : '';
    $state = isset($_POST['state']) ? sanitize_input($_POST['state']) : '';
    $zip = isset($_POST['zip']) ? sanitize_input($_POST['zip']) : '';
    $country = isset($_POST['country']) ? sanitize_input($_POST['country']) : '';
    $notes = isset($_POST['notes']) ? sanitize_input($_POST['notes']) : '';
    
    // Form validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    if (empty($address)) {
        $errors[] = 'Address is required';
    }
    
    if (empty($city)) {
        $errors[] = 'City is required';
    }
    
    if (empty($state)) {
        $errors[] = 'State/Province is required';
    }
    
    if (empty($zip)) {
        $errors[] = 'ZIP/Postal code is required';
    }
    
    if (empty($country)) {
        $errors[] = 'Country is required';
    }
    
    // If no errors, process the order
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Create order in database
            $stmt = $conn->prepare("
                INSERT INTO orders 
                (user_id, order_date, total_amount, shipping_amount, tax_amount, status, payment_method, 
                shipping_address, shipping_city, shipping_state, shipping_zip, shipping_country, 
                shipping_phone, customer_notes) 
                VALUES (?, NOW(), ?, ?, ?, 'pending', 'Cash on Delivery', ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "idddssssss",
                $user_id,
                $total,
                $shipping,
                $tax,
                $address,
                $city,
                $state,
                $zip,
                $country,
                $phone,
                $notes
            );
            
            if ($stmt->execute()) {
                $order_id = $conn->insert_id;
                
                // Add order items
                $stmt = $conn->prepare("
                    INSERT INTO order_items 
                    (order_id, product_sku, quantity, price, size, color) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($_SESSION['cart'] as $item) {
                    $stmt->bind_param(
                        "isidss",
                        $order_id,
                        $item['sku'],
                        $item['quantity'],
                        $item['price'],
                        $item['size'],
                        $item['color']
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to add order item: ' . $stmt->error);
                    }
                    
                    // Update product stock
                    $update_stock = $conn->prepare("
                        UPDATE products 
                        SET stock = stock - ? 
                        WHERE sku = ? AND stock >= ?
                    ");
                    
                    $update_stock->bind_param("isi", $item['quantity'], $item['sku'], $item['quantity']);
                    
                    if (!$update_stock->execute() || $update_stock->affected_rows === 0) {
                        throw new Exception('Failed to update product stock: ' . $update_stock->error);
                    }
                    
                    $update_stock->close();
                }
                
                // Generate order tracking number
                $tracking_number = strtoupper(substr(md5($order_id . time()), 0, 10));
                $update_tracking = $conn->prepare("UPDATE orders SET tracking_number = ? WHERE id = ?");
                $update_tracking->bind_param("si", $tracking_number, $order_id);
                
                if (!$update_tracking->execute()) {
                    throw new Exception('Failed to update tracking number: ' . $update_tracking->error);
                }
                
                $update_tracking->close();
                
                // Commit transaction
                $conn->commit();
                
                // Clear the cart
                $_SESSION['cart'] = [];
                
                // Set success flag
                $success = true;
                
                // Log successful order
                log_message("Order #$order_id created successfully for user #$user_id", 'info');
            } else {
                throw new Exception('Failed to create order: ' . $stmt->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            log_message('Order creation error: ' . $e->getMessage(), 'error');
            $errors[] = 'An error occurred while processing your order. Please try again.';
        }
    }
}

// Set page title
$page_title = 'Checkout | Elegant Drapes';

// Include header
include 'includes/header.php';
?>

<div class="container my-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="cart.php" class="text-decoration-none">Shopping Cart</a></li>
            <li class="breadcrumb-item active" aria-current="page">Checkout</li>
        </ol>
    </nav>
    
    <h1 class="mb-4 text-center">Checkout</h1>
    
    <?php if ($success && $order_id): ?>
        <!-- Order Success -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-success mb-4 shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i> Order Placed Successfully</h5>
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="bi bi-bag-check display-1 text-success mb-4"></i>
                        <h3 class="mb-3">Thank You for Your Order!</h3>
                        <p class="lead mb-3">Your order has been received and is now being processed.</p>
                        <p class="mb-4">Order #: <strong><?php echo sprintf('%06d', $order_id); ?></strong></p>
                        <p class="mb-4">
                            We've sent a confirmation email to <strong><?php echo htmlspecialchars($email); ?></strong> 
                            with your order details.
                        </p>
                        <div class="d-grid gap-2 col-md-6 mx-auto">
                            <a href="profile.php?tab=orders" class="btn btn-primary">View Your Orders</a>
                            <a href="shop.php" class="btn btn-outline-secondary">Continue Shopping</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Checkout Form -->
        <div class="row">
            <div class="col-lg-8">
                <!-- Display errors if any -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-4">
                        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i> There were problems with your submission</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form id="checkoutForm" method="post" action="checkout.php">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required
                                           value="<?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required
                                           value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Shipping Address</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="address" class="form-label">Street Address *</label>
                                    <input type="text" class="form-control" id="address" name="address" required
                                           value="<?php echo isset($user['shipping_address']) ? htmlspecialchars($user['shipping_address']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" required
                                           value="<?php echo isset($user['city']) ? htmlspecialchars($user['city']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="state" class="form-label">State/Province *</label>
                                    <input type="text" class="form-control" id="state" name="state" required
                                           value="<?php echo isset($user['state']) ? htmlspecialchars($user['state']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="zip" class="form-label">ZIP/Postal Code *</label>
                                    <input type="text" class="form-control" id="zip" name="zip" required
                                           value="<?php echo isset($user['zip']) ? htmlspecialchars($user['zip']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="country" class="form-label">Country *</label>
                                    <select class="form-select" id="country" name="country" required>
                                        <option value="">Select Country</option>
                                        <option value="United States" <?php echo (isset($user['country']) && $user['country'] == 'United States') ? 'selected' : ''; ?>>United States</option>
                                        <option value="Canada" <?php echo (isset($user['country']) && $user['country'] == 'Canada') ? 'selected' : ''; ?>>Canada</option>
                                        <option value="United Kingdom" <?php echo (isset($user['country']) && $user['country'] == 'United Kingdom') ? 'selected' : ''; ?>>United Kingdom</option>
                                        <option value="Australia" <?php echo (isset($user['country']) && $user['country'] == 'Australia') ? 'selected' : ''; ?>>Australia</option>
                                        <option value="France" <?php echo (isset($user['country']) && $user['country'] == 'France') ? 'selected' : ''; ?>>France</option>
                                        <option value="Germany" <?php echo (isset($user['country']) && $user['country'] == 'Germany') ? 'selected' : ''; ?>>Germany</option>
                                        <option value="Italy" <?php echo (isset($user['country']) && $user['country'] == 'Italy') ? 'selected' : ''; ?>>Italy</option>
                                        <option value="Spain" <?php echo (isset($user['country']) && $user['country'] == 'Spain') ? 'selected' : ''; ?>>Spain</option>
                                        <option value="Japan" <?php echo (isset($user['country']) && $user['country'] == 'Japan') ? 'selected' : ''; ?>>Japan</option>
                                        <option value="China" <?php echo (isset($user['country']) && $user['country'] == 'China') ? 'selected' : ''; ?>>China</option>
                                        <option value="India" <?php echo (isset($user['country']) && $user['country'] == 'India') ? 'selected' : ''; ?>>India</option>
                                        <!-- Add more countries as needed -->
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Order Notes (Optional)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Special instructions for delivery or any other notes"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_cod" value="cod" checked>
                                <label class="form-check-label" for="payment_cod">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-cash me-2 fs-4"></i>
                                        <div>
                                            <span class="fw-bold">Cash on Delivery</span>
                                            <p class="text-muted mb-0 small">Pay with cash when your order is delivered</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="alert alert-info mt-3 mb-0">
                                <div class="d-flex">
                                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                                    <div>
                                        <p class="mb-0"><strong>How Cash on Delivery works:</strong></p>
                                        <ol class="mb-0 ps-3 small">
                                            <li>Your order will be processed and prepared for shipping</li>
                                            <li>Our delivery partner will bring your order to your address</li>
                                            <li>You'll inspect the item(s) and pay in cash upon delivery</li>
                                            <li>Please ensure you have the exact amount ready for a smooth delivery experience</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-4">
                <!-- Order Summary -->
                <div class="card shadow-sm mb-4 sticky-lg-top" style="top: 20px; z-index: 1;">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <li class="list-group-item d-flex py-3">
                                    <div class="flex-shrink-0 me-3">
                                        <img src="<?php echo !empty($item['image']) ? htmlspecialchars($item['image']) : 'assets/images/product-placeholder.jpg'; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="img-fluid rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="my-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <small class="text-muted">
                                            Qty: <?php echo $item['quantity']; ?>
                                            <?php if (!empty($item['size'])): ?> 
                                                | Size: <?php echo htmlspecialchars($item['size']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($item['color'])): ?> 
                                                | Color: <?php echo htmlspecialchars($item['color']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-muted">
                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="card-body border-top">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span><?php echo $shipping > 0 ? '$' . number_format($shipping, 2) : 'Free'; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Tax (<?php echo ($tax_rate * 100) . '%'; ?>)</span>
                                <span>$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold fs-5">$<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <button type="submit" form="checkoutForm" name="place_order" id="placeOrderBtn" class="btn btn-primary w-100 btn-lg">
                                Place Order
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="cart.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i> Return to Cart
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the checkout form and submit button
    const checkoutForm = document.getElementById('checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    
    // Add submit event listener to the form
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Disable the submit button to prevent multiple submissions
            if (placeOrderBtn) {
                placeOrderBtn.disabled = true;
                placeOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
            }
        });
    }
});
</script>
