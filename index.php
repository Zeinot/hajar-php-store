<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get featured products (latest 8 products)
$featured_sql = "SELECT p.*, c.name as category_name, 
                (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image 
                FROM products p
                JOIN categories c ON p.category_id = c.id
                ORDER BY p.created_at DESC
                LIMIT 8";
$featured_result = $conn->query($featured_sql);
$featured_products = [];

if ($featured_result && $featured_result->num_rows > 0) {
    while ($row = $featured_result->fetch_assoc()) {
        $featured_products[] = $row;
    }
}

// Get all categories
$categories = get_all_categories($conn);

// Include header
include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="hero" style="background-image: url('assets/images/hero-bg.jpg');">
    <div class="container hero-content">
        <h1>Welcome to ShopSmart</h1>
        <p>Your one-stop destination for quality products at affordable prices</p>
        <a href="shop.php" class="btn btn-primary btn-lg">Shop Now</a>
    </div>
</div>

<!-- Categories Section -->
<div class="container my-5">
    <h2 class="text-center mb-4">Shop by Category</h2>
    <div class="row">
        <?php foreach ($categories as $category): ?>
        <div class="col-6 col-md-3 mb-4">
            <a href="shop.php?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                <div class="category-card">
                    <?php if ($category['icon']): ?>
                    <img src="<?php echo $category['icon']; ?>" alt="<?php echo $category['name']; ?>">
                    <?php else: ?>
                    <img src="assets/images/category-placeholder.jpg" alt="<?php echo $category['name']; ?>">
                    <?php endif; ?>
                    <div class="category-overlay">
                        <span><?php echo $category['name']; ?></span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Featured Products Section -->
<div class="container my-5">
    <h2 class="text-center mb-4">Featured Products</h2>
    <div class="row">
        <?php if (count($featured_products) > 0): ?>
            <?php foreach ($featured_products as $product): ?>
            <div class="col-12 col-md-6 col-lg-3 mb-4">
                <div class="card product-card h-100">
                    <a href="product.php?sku=<?php echo $product['sku']; ?>">
                        <?php if ($product['image']): ?>
                        <img src="<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                        <?php else: ?>
                        <img src="assets/images/product-placeholder.jpg" class="card-img-top" alt="<?php echo $product['name']; ?>">
                        <?php endif; ?>
                    </a>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <a href="product.php?sku=<?php echo $product['sku']; ?>" class="text-dark text-decoration-none">
                                <?php echo $product['name']; ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted small"><?php echo $product['category_name']; ?></p>
                        <div class="product-price mt-auto">
                            <?php echo format_price($product['price']); ?>
                        </div>
                        <button class="btn btn-primary add-to-cart-btn" data-sku="<?php echo $product['sku']; ?>">
                            Add to Cart <i class="fas fa-shopping-cart"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">No featured products available at the moment.</div>
            </div>
        <?php endif; ?>
    </div>
    <div class="text-center mt-4">
        <a href="shop.php" class="btn btn-outline-primary">View All Products</a>
    </div>
</div>

<!-- Promo Banner -->
<div class="container-fluid bg-light py-5 my-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 mb-4 mb-md-0">
                <h2>Special Offer</h2>
                <p class="lead">Get 20% off on your first purchase when you sign up for our newsletter!</p>
                <form class="row g-3" id="newsletter-form">
                    <div class="col-md-8">
                        <input type="email" class="form-control" id="email" placeholder="Your email address" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-accent w-100">Subscribe</button>
                    </div>
                </form>
            </div>
            <div class="col-md-6 text-center">
                <img src="assets/images/special-offer.png" alt="Special Offer" class="img-fluid" style="max-height: 300px;">
            </div>
        </div>
    </div>
</div>

<!-- Why Choose Us Section -->
<div class="container my-5">
    <h2 class="text-center mb-4">Why Choose Us</h2>
    <div class="row text-center">
        <div class="col-md-4 mb-4">
            <div class="p-4 bg-light rounded-3 h-100">
                <i class="fas fa-shipping-fast fa-3x mb-3 text-primary"></i>
                <h4>Fast Shipping</h4>
                <p>Free shipping on orders over $50. Quick delivery to your doorstep.</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="p-4 bg-light rounded-3 h-100">
                <i class="fas fa-undo fa-3x mb-3 text-primary"></i>
                <h4>Easy Returns</h4>
                <p>30-day hassle-free return policy. No questions asked.</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="p-4 bg-light rounded-3 h-100">
                <i class="fas fa-headset fa-3x mb-3 text-primary"></i>
                <h4>24/7 Support</h4>
                <p>Our customer support team is available around the clock to assist you.</p>
            </div>
        </div>
    </div>
</div>

<!-- Testimonials Section -->
<div class="container my-5">
    <h2 class="text-center mb-4">What Our Customers Say</h2>
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                    </div>
                    <p class="card-text">"Great products and fast shipping! I love shopping at ShopSmart and will definitely be back for more."</p>
                    <div class="d-flex align-items-center mt-3">
                        <div class="flex-shrink-0">
                            <img src="assets/images/avatar-1.jpg" alt="Customer" class="rounded-circle" width="50" height="50">
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0">John Doe</h6>
                            <small class="text-muted">Loyal Customer</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star-half-alt text-warning"></i>
                    </div>
                    <p class="card-text">"The customer service is exceptional. I had an issue with my order and they resolved it immediately. Highly recommend!"</p>
                    <div class="d-flex align-items-center mt-3">
                        <div class="flex-shrink-0">
                            <img src="assets/images/avatar-2.jpg" alt="Customer" class="rounded-circle" width="50" height="50">
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0">Jane Smith</h6>
                            <small class="text-muted">Happy Shopper</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                        <i class="fas fa-star text-warning"></i>
                    </div>
                    <p class="card-text">"Quality products at reasonable prices. The website is easy to navigate and checkout is a breeze. Will be shopping here again!"</p>
                    <div class="d-flex align-items-center mt-3">
                        <div class="flex-shrink-0">
                            <img src="assets/images/avatar-3.jpg" alt="Customer" class="rounded-circle" width="50" height="50">
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0">Michael Johnson</h6>
                            <small class="text-muted">New Customer</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Newsletter Form Processing -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const emailInput = document.getElementById('email');
            const email = emailInput.value.trim();
            
            if (email === '') {
                showToast('Error', 'Please enter your email address.', 'error');
                return;
            }
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Subscribing...';
            
            // Simulate subscription (would be replaced with actual AJAX)
            setTimeout(() => {
                // Success
                showToast('Success', 'Thank you for subscribing to our newsletter!', 'success');
                
                // Reset form
                emailInput.value = '';
                
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Subscribe';
            }, 1500);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
