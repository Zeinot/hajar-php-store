<?php
/**
 * Shopping Cart Page for Elegant Drapes luxury clothing store
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Set page title
$page_title = 'Shopping Cart | Elegant Drapes';

// Calculate cart totals
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

// Check if user is logged in for checkout button status
$user_logged_in = is_logged_in();

// Get available shipping methods
$shipping_methods = [
    ['id' => 'standard', 'name' => 'Standard Shipping', 'price' => 12.99, 'days' => '3-5 business days'],
    ['id' => 'express', 'name' => 'Express Shipping', 'price' => 24.99, 'days' => '1-2 business days'],
    ['id' => 'overnight', 'name' => 'Overnight Shipping', 'price' => 39.99, 'days' => 'Next business day']
];

// Free shipping for orders over $150
if ($subtotal >= 150) {
    $shipping_methods[0]['price'] = 0;
    $shipping_methods[0]['name'] = 'Free Standard Shipping';
}

// Include header
include 'includes/header.php';
?>

<div class="container my-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
        </ol>
    </nav>
    
    <h1 class="mb-4 text-center">Your Shopping Cart</h1>
    
    <?php if (empty($_SESSION['cart'])): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart3 display-1 text-muted mb-3"></i>
            <h3 class="mb-4">Your cart is empty</h3>
            <p class="mb-4">Looks like you haven't added any items to your cart yet.</p>
            <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <!-- Cart Items -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Cart Items (<?php echo count($_SESSION['cart']); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-center" width="100">Product</th>
                                        <th>Details</th>
                                        <th class="text-center" width="120">Price</th>
                                        <th class="text-center" width="150">Quantity</th>
                                        <th class="text-center" width="120">Subtotal</th>
                                        <th width="40"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['cart'] as $cart_item_id => $item): ?>
                                        <tr class="cart-item" data-id="<?php echo htmlspecialchars($cart_item_id); ?>">
                                            <td class="text-center">
                                                <img src="<?php echo !empty($item['image']) ? htmlspecialchars($item['image']) : 'assets/images/product-placeholder.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     class="img-fluid rounded" style="max-width: 80px;">
                                            </td>
                                            <td>
                                                <h6 class="mb-1">
                                                    <a href="product.php?sku=<?php echo htmlspecialchars($item['sku']); ?>" class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </a>
                                                </h6>
                                                <div class="small text-muted">
                                                    <?php if (!empty($item['size'])): ?>
                                                        <span class="me-2">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['color'])): ?>
                                                        <span>Color: <?php echo htmlspecialchars($item['color']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center"><?php echo '$' . number_format($item['price'], 2); ?></td>
                                            <td>
                                                <div class="input-group quantity-selector">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary decrement-quantity">-</button>
                                                    <input type="number" class="form-control form-control-sm text-center quantity-input" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" max="<?php echo $item['max_quantity']; ?>"
                                                           aria-label="Quantity">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary increment-quantity">+</button>
                                                </div>
                                            </td>
                                            <td class="text-center fw-bold item-subtotal">
                                                <?php echo '$' . number_format($item['price'] * $item['quantity'], 2); ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm text-danger remove-item" 
                                                        aria-label="Remove item" data-id="<?php echo htmlspecialchars($cart_item_id); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                        <a href="shop.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Continue Shopping
                        </a>
                        <button type="button" class="btn btn-outline-danger" id="clearCartBtn">
                            <i class="bi bi-trash me-1"></i> Clear Cart
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Order Summary -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span class="fw-bold cart-subtotal"><?php echo '$' . number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span class="fw-bold cart-shipping"><?php echo $shipping > 0 ? '$' . number_format($shipping, 2) : 'Free'; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Tax (<?php echo ($tax_rate * 100) . '%'; ?>)</span>
                            <span class="fw-bold cart-tax"><?php echo '$' . number_format($tax, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Total</span>
                            <span class="fw-bold fs-5 cart-total"><?php echo '$' . number_format($total, 2); ?></span>
                        </div>
                        
                        <?php if ($subtotal < 150): ?>
                            <div class="alert alert-info small mb-3">
                                <i class="bi bi-info-circle me-1"></i> Add <?php echo '$' . number_format(150 - $subtotal, 2); ?> more to qualify for free shipping!
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success small mb-3">
                                <i class="bi bi-check-circle me-1"></i> You've qualified for free shipping!
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?php echo $user_logged_in ? 'checkout.php' : 'login.php?redirect=checkout.php'; ?>" 
                           class="btn btn-primary w-100 btn-lg">
                            <?php echo $user_logged_in ? 'Proceed to Checkout' : 'Sign In to Checkout'; ?>
                        </a>
                        
                        <?php if (!$user_logged_in): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">Don't have an account? <a href="register.php">Register now</a></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shipping Methods -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Shipping Methods</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($shipping_methods as $method): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($method['name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($method['days']); ?></div>
                                    </div>
                                    <span class="badge bg-secondary rounded-pill">
                                        <?php echo $method['price'] > 0 ? '$' . number_format($method['price'], 2) : 'FREE'; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Toast Container for Notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<!-- Clear Cart Confirmation Modal -->
<div class="modal fade" id="clearCartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear Shopping Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove all items from your shopping cart?</p>
                <p class="mb-0 text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmClearCart">Clear Cart</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cache DOM elements
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const incrementBtns = document.querySelectorAll('.increment-quantity');
    const decrementBtns = document.querySelectorAll('.decrement-quantity');
    const removeItemBtns = document.querySelectorAll('.remove-item');
    const clearCartBtn = document.getElementById('clearCartBtn');
    const confirmClearCartBtn = document.getElementById('confirmClearCart');
    const clearCartModal = new bootstrap.Modal(document.getElementById('clearCartModal'));
    
    // Initialize variables
    let updateTimeout;
    
    // Utility function to update cart item quantity
    function updateCartItemQuantity(cartItemId, quantity) {
        clearTimeout(updateTimeout);
        
        // Disable input and buttons during update
        const cartItem = document.querySelector(`.cart-item[data-id="${cartItemId}"]`);
        const quantityInput = cartItem.querySelector('.quantity-input');
        const decrementBtn = cartItem.querySelector('.decrement-quantity');
        const incrementBtn = cartItem.querySelector('.increment-quantity');
        
        decrementBtn.disabled = true;
        incrementBtn.disabled = true;
        quantityInput.disabled = true;
        
        // Update UI to show loading state
        cartItem.classList.add('opacity-50');
        
        // Delay to prevent too many rapid requests
        updateTimeout = setTimeout(() => {
            // Make AJAX request to update cart
            fetch('ajax/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update&product_sku=${encodeURIComponent(cartItemId)}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update item subtotal
                    const itemPrice = parseFloat(cartItem.querySelector('td:nth-child(3)').textContent.replace('$', ''));
                    const itemSubtotal = itemPrice * quantity;
                    cartItem.querySelector('.item-subtotal').textContent = '$' + itemSubtotal.toFixed(2);
                    
                    // Update cart totals and count (reload to get accurate totals)
                    window.location.reload();
                } else {
                    showToast('Error', data.message || 'Failed to update cart', 'danger');
                    // Reset to previous value
                    quantityInput.value = quantityInput.getAttribute('data-last-value');
                }
                
                // Re-enable inputs
                decrementBtn.disabled = false;
                incrementBtn.disabled = false;
                quantityInput.disabled = false;
                cartItem.classList.remove('opacity-50');
            })
            .catch(error => {
                console.error('Error updating cart:', error);
                showToast('Error', 'Failed to update cart', 'danger');
                
                // Re-enable inputs
                decrementBtn.disabled = false;
                incrementBtn.disabled = false;
                quantityInput.disabled = false;
                cartItem.classList.remove('opacity-50');
            });
        }, 500);
    }
    
    // Increment quantity
    incrementBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.quantity-input');
            const cartItemId = this.closest('.cart-item').dataset.id;
            const max = parseInt(input.max);
            let value = parseInt(input.value);
            
            // Store last value for potential rollback
            input.setAttribute('data-last-value', value);
            
            // Increment but don't exceed max
            if (value < max) {
                value++;
                input.value = value;
                updateCartItemQuantity(cartItemId, value);
            }
        });
    });
    
    // Decrement quantity
    decrementBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.quantity-input');
            const cartItemId = this.closest('.cart-item').dataset.id;
            let value = parseInt(input.value);
            
            // Store last value for potential rollback
            input.setAttribute('data-last-value', value);
            
            // Decrement but don't go below 1
            if (value > 1) {
                value--;
                input.value = value;
                updateCartItemQuantity(cartItemId, value);
            }
        });
    });
    
    // Handle manual input changes
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const cartItemId = this.closest('.cart-item').dataset.id;
            const min = parseInt(this.min);
            const max = parseInt(this.max);
            let value = parseInt(this.value);
            
            // Store last value for potential rollback
            const lastValue = this.getAttribute('data-last-value') || this.defaultValue;
            this.setAttribute('data-last-value', lastValue);
            
            // Validate input
            if (isNaN(value) || value < min) {
                value = min;
            } else if (value > max) {
                value = max;
            }
            
            // Update input value
            this.value = value;
            
            // Update cart if value changed
            if (value != lastValue) {
                updateCartItemQuantity(cartItemId, value);
            }
        });
        
        // Store initial value
        input.setAttribute('data-last-value', input.value);
    });
    
    // Remove item from cart
    removeItemBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const cartItemId = this.dataset.id;
            const cartItem = document.querySelector(`.cart-item[data-id="${cartItemId}"]`);
            
            // Disable button and show loading state
            this.disabled = true;
            cartItem.classList.add('opacity-50');
            
            // Make AJAX request to remove item
            fetch('ajax/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove&product_sku=${encodeURIComponent(cartItemId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showToast('Item Removed', 'The item has been removed from your cart', 'success');
                    
                    // Reload page to update cart
                    window.location.reload();
                } else {
                    showToast('Error', data.message || 'Failed to remove item', 'danger');
                    // Re-enable button
                    this.disabled = false;
                    cartItem.classList.remove('opacity-50');
                }
            })
            .catch(error => {
                console.error('Error removing item:', error);
                showToast('Error', 'Failed to remove item', 'danger');
                // Re-enable button
                this.disabled = false;
                cartItem.classList.remove('opacity-50');
            });
        });
    });
    
    // Clear cart confirmation
    clearCartBtn.addEventListener('click', function() {
        clearCartModal.show();
    });
    
    // Confirm clear cart
    confirmClearCartBtn.addEventListener('click', function() {
        // Disable button to prevent multiple clicks
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Clearing...';
        
        // Make AJAX request to clear cart
        fetch('ajax/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showToast('Cart Cleared', 'Your shopping cart has been cleared', 'success');
                
                // Hide modal and reload page
                clearCartModal.hide();
                window.location.reload();
            } else {
                showToast('Error', data.message || 'Failed to clear cart', 'danger');
                // Re-enable button
                this.disabled = false;
                this.innerHTML = 'Clear Cart';
            }
        })
        .catch(error => {
            console.error('Error clearing cart:', error);
            showToast('Error', 'Failed to clear cart', 'danger');
            // Re-enable button
            this.disabled = false;
            this.innerHTML = 'Clear Cart';
        });
    });
});

// Helper function to show toast notification
function showToast(title, message, type) {
    const toastContainer = document.getElementById('toastContainer');
    const timestamp = new Date().toLocaleTimeString();
    
    const toastHtml = `
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${type ? 'bg-' + type + ' text-white' : ''}">
                <strong class="me-auto">${title}</strong>
                <small class="text-${type === 'light' ? 'muted' : 'white'}">${timestamp}</small>
                <button type="button" class="btn-close ${type && type !== 'light' ? 'btn-close-white' : ''}" 
                       data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    // Append toast to container
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    // Initialize and show the toast
    const toastElement = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 5000 });
    toast.show();
    
    // Remove toast from DOM after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}
</script>
