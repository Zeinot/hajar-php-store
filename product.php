<?php
/**
 * Product Details Page for Elegant Drapes luxury clothing store
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get product SKU from URL
$sku = isset($_GET['sku']) ? sanitize_input($_GET['sku']) : '';

// Redirect if SKU is empty
if (empty($sku)) {
    redirect('shop.php');
}

// Get product details
$product = null;
$product_images = [];
$product_sizes = [];
$product_colors = [];
$product_features = [];
$related_products = [];

try {
    $conn = getDbConnection();
    
    // Get product details
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name, m.name as material_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN materials m ON p.material_id = m.id
        WHERE p.sku = ?
    ");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();
    } else {
        // Product not found
        redirect('shop.php');
    }
    
    $stmt->close();
    
    // Get product images
    $stmt = $conn->prepare("SELECT id, image_url, is_primary FROM product_images WHERE product_sku = ? ORDER BY is_primary DESC");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $images_result = $stmt->get_result();
    
    while ($image = $images_result->fetch_assoc()) {
        $product_images[] = $image;
    }
    
    $stmt->close();
    
    // Get product sizes
    $stmt = $conn->prepare("SELECT size_name, additional_price FROM product_sizes WHERE product_sku = ?");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $sizes_result = $stmt->get_result();
    
    while ($size = $sizes_result->fetch_assoc()) {
        $product_sizes[] = $size;
    }
    
    $stmt->close();
    
    // Get product colors
    $stmt = $conn->prepare("SELECT color_name, additional_price FROM product_colors WHERE product_sku = ?");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $colors_result = $stmt->get_result();
    
    while ($color = $colors_result->fetch_assoc()) {
        $product_colors[] = $color;
    }
    
    $stmt->close();
    
    // Get product features
    $stmt = $conn->prepare("
        SELECT f.name, f.description 
        FROM product_has_features phf
        JOIN product_features f ON phf.feature_id = f.id
        WHERE phf.product_sku = ?
    ");
    $stmt->bind_param("s", $sku);
    $stmt->execute();
    $features_result = $stmt->get_result();
    
    while ($feature = $features_result->fetch_assoc()) {
        $product_features[] = $feature;
    }
    
    $stmt->close();
    
    // Get related products from same category
    $stmt = $conn->prepare("
        SELECT p.sku, p.name, p.price, p.sale_price, 
        (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image
        FROM products p
        WHERE p.category_id = ? AND p.sku != ?
        ORDER BY RAND()
        LIMIT 4
    ");
    $stmt->bind_param("is", $product['category_id'], $sku);
    $stmt->execute();
    $related_result = $stmt->get_result();
    
    while ($related = $related_result->fetch_assoc()) {
        $related_products[] = $related;
    }
    
    $stmt->close();
    
    // Check if product is in user's wishlist
    $in_wishlist = false;
    if (is_logged_in()) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_sku = ?");
        $stmt->bind_param("is", $user_id, $sku);
        $stmt->execute();
        $wishlist_result = $stmt->get_result();
        $in_wishlist = ($wishlist_result->num_rows > 0);
        $stmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    log_message('Product details error: ' . $e->getMessage(), 'error');
    redirect('shop.php');
}

// Set page title
$page_title = htmlspecialchars($product['name']) . ' | Elegant Drapes';

// Include header
include 'includes/header.php';
?>

<div class="container my-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none">Shop</a></li>
            <li class="breadcrumb-item"><a href="shop.php?category=<?php echo $product['category_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Product Images -->
        <div class="col-lg-6 mb-4">
            <div class="product-images">
                <?php if (empty($product_images)): ?>
                    <img src="assets/img/product-placeholder.jpg" class="img-fluid rounded main-product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <?php else: ?>
                    <div class="main-image-container mb-3">
                        <img src="uploads/products/<?php echo $product_images[0]['image_url']; ?>" class="img-fluid rounded main-product-image" id="mainProductImage" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    
                    <?php if (count($product_images) > 1): ?>
                        <div class="row product-thumbnails">
                            <?php foreach ($product_images as $index => $image): ?>
                                <div class="col-3 mb-3">
                                    <img src="uploads/products/<?php echo $image['image_url']; ?>" 
                                         class="img-thumbnail product-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         data-image="uploads/products/<?php echo $image['image_url']; ?>" 
                                         alt="Product thumbnail">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Product Info -->
        <div class="col-lg-6">
            <div class="product-details">
                <h1 class="product-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-meta mb-3">
                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <?php if (!empty($product['material_name'])): ?>
                        <span class="badge bg-info text-dark me-2"><?php echo htmlspecialchars($product['material_name']); ?></span>
                    <?php endif; ?>
                    <span class="badge bg-<?php echo $product['gender'] === 'Men' ? 'primary' : ($product['gender'] === 'Women' ? 'danger' : 'dark'); ?>">
                        <?php echo $product['gender']; ?>
                    </span>
                </div>
                
                <div class="product-price mb-4">
                    <?php if (!empty($product['sale_price'])): ?>
                        <span class="text-decoration-line-through text-muted me-2 fs-5">
                            $<?php echo number_format($product['price'], 2); ?>
                        </span>
                        <span class="fw-bold text-danger fs-3">
                            $<?php echo number_format($product['sale_price'], 2); ?>
                        </span>
                    <?php else: ?>
                        <span class="fw-bold fs-3">
                            $<?php echo number_format($product['price'], 2); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="product-description mb-4">
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                
                <form id="addToCartForm" class="mb-4">
                    <input type="hidden" name="product_sku" value="<?php echo $sku; ?>">
                    
                    <?php if (!empty($product_sizes)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Size</label>
                            <div class="size-options">
                                <?php foreach ($product_sizes as $index => $size): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="size" 
                                               id="size<?php echo $index; ?>" value="<?php echo htmlspecialchars($size['size_name']); ?>"
                                               data-additional-price="<?php echo $size['additional_price']; ?>"
                                               <?php echo $index === 0 ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="size<?php echo $index; ?>">
                                            <?php echo htmlspecialchars($size['size_name']); ?>
                                            <?php if ($size['additional_price'] > 0): ?>
                                                <small class="text-muted">(+$<?php echo number_format($size['additional_price'], 2); ?>)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($product_colors)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Color</label>
                            <div class="color-options">
                                <?php foreach ($product_colors as $index => $color): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="color" 
                                               id="color<?php echo $index; ?>" value="<?php echo htmlspecialchars($color['color_name']); ?>"
                                               data-additional-price="<?php echo $color['additional_price']; ?>"
                                               <?php echo $index === 0 ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="color<?php echo $index; ?>">
                                            <?php echo htmlspecialchars($color['color_name']); ?>
                                            <?php if ($color['additional_price'] > 0): ?>
                                                <small class="text-muted">(+$<?php echo number_format($color['additional_price'], 2); ?>)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Quantity</label>
                        <div class="quantity-control d-flex align-items-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="decreaseQuantity">
                                <i class="bi bi-dash"></i>
                            </button>
                            <input type="number" class="form-control mx-2" id="quantity" name="quantity" 
                                   value="1" min="1" max="<?php echo min(10, $product['stock']); ?>" style="width: 60px;">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="increaseQuantity">
                                <i class="bi bi-plus"></i>
                            </button>
                            <span class="ms-3 text-muted"><?php echo $product['stock']; ?> in stock</span>
                        </div>
                    </div>
                    
                    <div class="product-actions d-flex">
                        <button type="button" id="addToCartBtn" class="btn btn-primary me-2" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <i class="bi bi-cart-plus me-1"></i> Add to Cart
                        </button>
                        
                        <button type="button" id="addToWishlistBtn" class="btn btn-outline-danger" data-sku="<?php echo $sku; ?>">
                            <i class="bi bi-heart<?php echo $in_wishlist ? '-fill' : ''; ?> me-1"></i> 
                            <?php echo $in_wishlist ? 'In Wishlist' : 'Add to Wishlist'; ?>
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($product_features)): ?>
                    <div class="product-features mt-4">
                        <h5 class="mb-3">Features</h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($product_features as $feature): ?>
                                <li class="list-group-item d-flex align-items-center border-0 ps-0">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <span>
                                        <strong><?php echo htmlspecialchars($feature['name']); ?>:</strong> 
                                        <?php echo htmlspecialchars($feature['description']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="product-sharing mt-4">
                    <h5 class="mb-3">Share</h5>
                    <div class="social-sharing">
                        <a href="https://facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                           target="_blank" class="btn btn-outline-primary btn-sm me-2">
                            <i class="bi bi-facebook me-1"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode('Check out this ' . $product['name'] . ' from Elegant Drapes!'); ?>" 
                           target="_blank" class="btn btn-outline-info btn-sm me-2">
                            <i class="bi bi-twitter me-1"></i> Twitter
                        </a>
                        <a href="https://pinterest.com/pin/create/button/?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&media=<?php echo urlencode(!empty($product_images) ? 'https://' . $_SERVER['HTTP_HOST'] . '/uploads/products/' . $product_images[0]['image_url'] : ''); ?>&description=<?php echo urlencode($product['name']); ?>" 
                           target="_blank" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-pinterest me-1"></i> Pinterest
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="related-products mt-5">
            <h3 class="mb-4">You May Also Like</h3>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach ($related_products as $related): ?>
                    <div class="col">
                        <div class="card h-100 product-card">
                            <?php if (!empty($related['sale_price'])): ?>
                                <div class="badge bg-danger position-absolute top-0 start-0 m-2">SALE</div>
                            <?php endif; ?>
                            
                            <a href="product.php?sku=<?php echo $related['sku']; ?>">
                                <img src="<?php echo !empty($related['image']) ? 'uploads/products/' . $related['image'] : 'assets/img/product-placeholder.jpg'; ?>" 
                                     class="card-img-top product-img" alt="<?php echo htmlspecialchars($related['name']); ?>">
                            </a>
                            
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="product.php?sku=<?php echo $related['sku']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($related['name']); ?>
                                    </a>
                                </h5>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <?php if (!empty($related['sale_price'])): ?>
                                            <span class="text-decoration-line-through text-muted me-2">
                                                $<?php echo number_format($related['price'], 2); ?>
                                            </span>
                                            <span class="fw-bold text-danger">
                                                $<?php echo number_format($related['sale_price'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="fw-bold">
                                                $<?php echo number_format($related['price'], 2); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <a href="product.php?sku=<?php echo $related['sku']; ?>" class="btn btn-outline-primary btn-sm">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add to Cart Success Modal -->
<div class="modal fade" id="addToCartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Added to Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-cart-check text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3 mb-3"><?php echo htmlspecialchars($product['name']); ?> has been added to your cart</h5>
                <p>You now have <span id="cartItemCount">0</span> items in your cart.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <a href="shop.php" class="btn btn-outline-secondary">Continue Shopping</a>
                <a href="cart.php" class="btn btn-primary">View Cart</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Product image thumbnails
    const thumbnails = document.querySelectorAll('.product-thumbnail');
    const mainImage = document.getElementById('mainProductImage');
    
    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            // Update main image
            mainImage.src = this.getAttribute('data-image');
            
            // Update active state
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Quantity controls
    const quantityInput = document.getElementById('quantity');
    const decreaseBtn = document.getElementById('decreaseQuantity');
    const increaseBtn = document.getElementById('increaseQuantity');
    
    decreaseBtn.addEventListener('click', function() {
        let value = parseInt(quantityInput.value);
        if (value > 1) {
            quantityInput.value = value - 1;
        }
    });
    
    increaseBtn.addEventListener('click', function() {
        let value = parseInt(quantityInput.value);
        let max = parseInt(quantityInput.getAttribute('max'));
        if (value < max) {
            quantityInput.value = value + 1;
        }
    });
    
    // Add to cart
    const addToCartBtn = document.getElementById('addToCartBtn');
    const addToCartForm = document.getElementById('addToCartForm');
    
    addToCartBtn.addEventListener('click', function() {
        // Disable button to prevent multiple clicks
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
        
        // Get form data
        const formData = new FormData(addToCartForm);
        
        // Add to cart via AJAX
        fetch('ajax/cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count in header
                const cartCountElement = document.getElementById('cartCount');
                if (cartCountElement) {
                    cartCountElement.textContent = data.cart_count;
                    cartCountElement.classList.remove('d-none');
                }
                
                // Update modal count
                const cartItemCountElement = document.getElementById('cartItemCount');
                if (cartItemCountElement) {
                    cartItemCountElement.textContent = data.cart_count;
                }
                
                // Show success modal
                const modal = new bootstrap.Modal(document.getElementById('addToCartModal'));
                modal.show();
            } else {
                showToast('Error', data.message || 'Failed to add item to cart', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', 'An error occurred. Please try again.', 'danger');
        })
        .finally(() => {
            // Re-enable button
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Add to Cart';
        });
    });
    
    // Add to wishlist
    const addToWishlistBtn = document.getElementById('addToWishlistBtn');
    
    addToWishlistBtn.addEventListener('click', function() {
        const sku = this.getAttribute('data-sku');
        const isInWishlist = this.querySelector('i').classList.contains('bi-heart-fill');
        
        // Use AJAX to add/remove from wishlist
        fetch('ajax/wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${isInWishlist ? 'remove' : 'add'}&product_sku=${sku}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update button appearance
                if (isInWishlist) {
                    this.querySelector('i').classList.replace('bi-heart-fill', 'bi-heart');
                    this.innerHTML = this.innerHTML.replace('In Wishlist', 'Add to Wishlist');
                    showToast('Success', 'Item removed from your wishlist', 'success');
                } else {
                    this.querySelector('i').classList.replace('bi-heart', 'bi-heart-fill');
                    this.innerHTML = this.innerHTML.replace('Add to Wishlist', 'In Wishlist');
                    showToast('Success', 'Item added to your wishlist!', 'success');
                }
            } else {
                if (data.message === 'login_required') {
                    showToast('Login Required', 'Please login to add items to your wishlist', 'warning');
                } else {
                    showToast('Error', 'Failed to update wishlist', 'danger');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', 'An error occurred. Please try again.', 'danger');
        });
    });
});

// Helper function to show toast notification
function showToast(title, message, type) {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}:</strong> ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    document.getElementById('toastContainer').appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}
</script>

<style>
/* Product Images */
.main-product-image {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: contain;
}

.product-thumbnail {
    cursor: pointer;
    transition: all 0.2s;
    height: 80px;
    object-fit: cover;
}

.product-thumbnail.active {
    border-color: #1a2456;
}

/* Product Info */
.product-title {
    font-size: 2rem;
    font-weight: 600;
    color: #1a2456;
}

/* Related Products */
.product-card {
    transition: transform 0.3s, box-shadow 0.3s;
    border: none;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.product-img {
    height: 200px;
    object-fit: cover;
}
</style>

<?php include 'includes/footer.php'; ?>