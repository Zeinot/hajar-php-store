<?php
// Shopping cart page for e-commerce website
$pageTitle = "Shopping Cart";
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get cart items
$cartItems = getCartItems();
$cartTotal = getCartTotal();

// Get DB instance for product details
$db = Database::getInstance();

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

include 'partials/header.php';
include 'partials/navbar.php';
?>

<!-- Add meta tag for site URL for JavaScript -->
<meta name="site-url" content="<?php echo SITE_URL; ?>">

<div class="container main-content">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2">Shopping Cart</h1>
        </div>
    </div>

    <?php if (empty($enhancedCartItems)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info cart-container">
                Your cart is empty. <a href="<?php echo SITE_URL; ?>products.php">Continue shopping</a>.
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row cart-container">
        <div class="col-lg-8">
            <!-- Cart Items -->
            <?php foreach ($enhancedCartItems as $item): ?>
            <div class="card mb-3 cart-item">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 col-sm-3 text-center mb-3 mb-md-0">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?php echo SITE_URL . 'assets/uploads/products/' . $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="img-fluid rounded">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/80x80?text=No+Image" alt="No image available" class="img-fluid rounded">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 col-sm-9 mb-3 mb-md-0">
                            <h5 class="card-title mb-1">
                                <a href="<?php echo SITE_URL; ?>product-details.php?sku=<?php echo $item['product_sku']; ?>" class="text-decoration-none text-dark">
                                    <?php echo $item['name']; ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted mb-1">SKU: <?php echo $item['product_sku']; ?></p>
                            
                            <?php if (!empty($item['size'])): ?>
                            <p class="card-text text-muted small mb-1">Size: <?php echo $item['size']; ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['color'])): ?>
                            <p class="card-text text-muted small mb-1">Color: <?php echo $item['color']; ?></p>
                            <?php endif; ?>
                            
                            <p class="card-text price mb-0"><?php echo formatPrice($item['price']); ?></p>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <label for="quantity-<?php echo $item['id']; ?>" class="form-label">Quantity</label>
                            <div class="quantity-control">
                                <button type="button" class="btn btn-sm btn-outline-secondary quantity-minus">-</button>
                                <input type="number" class="form-control mx-2" 
                                       id="quantity-<?php echo $item['id']; ?>" 
                                       data-id="<?php echo $item['id']; ?>" 
                                       value="<?php echo $item['quantity']; ?>" 
                                       min="1" 
                                       max="<?php echo $item['current_stock']; ?>">
                                <button type="button" class="btn btn-sm btn-outline-secondary quantity-plus">+</button>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-3 text-end">
                            <p class="card-text fw-bold item-subtotal"><?php echo formatPrice($item['subtotal']); ?></p>
                        </div>
                        <div class="col-md-1 col-sm-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-from-cart" data-id="<?php echo $item['id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Continue Shopping Button -->
            <div class="d-flex justify-content-between mb-4">
                <a href="<?php echo SITE_URL; ?>products.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                </a>
                <button type="button" id="update-cart" class="btn btn-outline-secondary">
                    <i class="fas fa-sync-alt me-2"></i>Update Cart
                </button>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Cart Summary -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Subtotal</span>
                        <span id="cart-subtotal"><?php echo formatPrice($cartTotal); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Shipping</span>
                        <span id="cart-shipping"><?php echo formatPrice($cartTotal >= 50 ? 0 : 5.99); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total</strong>
                        <strong id="cart-total"><?php echo formatPrice($cartTotal >= 50 ? $cartTotal : $cartTotal + 5.99); ?></strong>
                    </div>
                    
                    <!-- Proceed to Checkout Button -->
                    <div class="d-grid gap-2">
                        <a href="<?php echo SITE_URL; ?>checkout.php" class="btn btn-success">
                            <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Promo Code Form -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Promo Code</h5>
                    <form id="promo-code-form">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Enter promo code" aria-label="Promo code">
                            <button class="btn btn-outline-primary" type="submit">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Script for promo code form (demo only) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const promoForm = document.getElementById('promo-code-form');
    if (promoForm) {
        promoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get input value
            const promoCode = this.querySelector('input').value.trim();
            
            // Check if input is empty
            if (!promoCode) {
                showToast('Error!', 'Please enter a promo code.', 'error');
                return;
            }
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Applying...';
            
            // Simulate AJAX request (replace with actual implementation)
            setTimeout(function() {
                // Re-enable button and restore text
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                // Show error message (demo assumes all codes are invalid)
                showToast('Error!', 'Invalid promo code. Please try again.', 'error');
                
                // Reset form
                promoForm.reset();
            }, 1500);
        });
    }
    
    // Update cart button
    const updateCartBtn = document.getElementById('update-cart');
    if (updateCartBtn) {
        updateCartBtn.addEventListener('click', function() {
            showToast('Success!', 'Cart updated successfully.', 'success');
        });
    }
    
    // Function to show toast messages
    function showToast(title, message, type = 'info') {
        // Map type to Bootstrap color
        const typeClass = {
            'success': 'bg-success',
            'error': 'bg-danger',
            'warning': 'bg-warning',
            'info': 'bg-info'
        }[type] || 'bg-info';
        
        // Create toast container if it doesn't exist
        if (!document.querySelector('.toast-container')) {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '1080';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header ${typeClass} text-white">
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;
        
        // Add toast to container
        document.querySelector('.toast-container').insertAdjacentHTML('beforeend', toastHtml);
        
        // Initialize and show toast
        const toast = new bootstrap.Toast(document.getElementById(toastId), {
            autohide: true,
            delay: 5000
        });
        toast.show();
        
        // Remove toast from DOM after it's hidden
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }
});
</script>

<?php include 'partials/footer.php'; ?>
