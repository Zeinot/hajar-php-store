<?php
// Home page for e-commerce website
$pageTitle = "Home";
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get featured products
$db = Database::getInstance();
$featuredProducts = [];
$featuredQuery = "SELECT p.sku, p.name, p.price, p.description, 
                  (SELECT image_path FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image 
                  FROM products p 
                  ORDER BY p.created_at DESC 
                  LIMIT 8";
$result = $db->query($featuredQuery);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $featuredProducts[] = $row;
    }
}

// Get categories
$categories = [];
$categoryQuery = "SELECT id, name, description, icon FROM categories ORDER BY name LIMIT 6";
$categoryResult = $db->query($categoryQuery);

if ($categoryResult && $categoryResult->num_rows > 0) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

include 'partials/header.php';
include 'partials/navbar.php';
?>

<div class="container main-content">
    <!-- Hero Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div id="homeCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                    <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                    <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner rounded">
                    <div class="carousel-item active">
                        <img src="https://via.placeholder.com/1200x400?text=New+Arrivals" class="d-block w-100" alt="New Arrivals">
                        <div class="carousel-caption d-none d-md-block">
                            <h2>New Arrivals</h2>
                            <p>Check out our latest products just for you!</p>
                            <a href="products.php?sort=newest" class="btn btn-primary">Shop Now</a>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="https://via.placeholder.com/1200x400?text=Special+Offers" class="d-block w-100" alt="Special Offers">
                        <div class="carousel-caption d-none d-md-block">
                            <h2>Special Offers</h2>
                            <p>Limited time discounts on selected items!</p>
                            <a href="products.php?on_sale=1" class="btn btn-primary">View Offers</a>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="https://via.placeholder.com/1200x400?text=Seasonal+Collection" class="d-block w-100" alt="Seasonal Collection">
                        <div class="carousel-caption d-none d-md-block">
                            <h2>Seasonal Collection</h2>
                            <p>Explore our curated seasonal collection!</p>
                            <a href="products.php?category=1" class="btn btn-primary">Explore</a>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#homeCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#homeCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Categories Section -->
    <section class="mb-5">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">Shop by Category</h2>
            </div>
        </div>
        <div class="row g-4">
            <?php if (count($categories) > 0): ?>
                <?php foreach ($categories as $category): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="products.php?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                            <div class="card h-100 product-card text-center">
                                <div class="card-body">
                                    <?php if (!empty($category['icon'])): ?>
                                        <img src="<?php echo SITE_URL . 'assets/uploads/categories/' . $category['icon']; ?>" class="img-fluid mb-3" alt="<?php echo $category['name']; ?>" style="max-height: 100px;">
                                    <?php else: ?>
                                        <i class="fas fa-tags fa-3x mb-3 text-primary"></i>
                                    <?php endif; ?>
                                    <h5 class="card-title"><?php echo $category['name']; ?></h5>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-center">No categories available.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="mb-5">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">Featured Products</h2>
            </div>
        </div>
        <div class="row g-4">
            <?php if (count($featuredProducts) > 0): ?>
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100 product-card">
                            <a href="product-details.php?sku=<?php echo $product['sku']; ?>" class="text-decoration-none">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo SITE_URL . 'assets/uploads/products/' . $product['image']; ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/300x300?text=No+Image" class="card-img-top" alt="No image available">
                                <?php endif; ?>
                            </a>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="product-details.php?sku=<?php echo $product['sku']; ?>" class="text-decoration-none text-dark">
                                        <?php echo $product['name']; ?>
                                    </a>
                                </h5>
                                <p class="card-text price"><?php echo formatPrice($product['price']); ?></p>
                                <p class="card-text text-muted small">
                                    <?php echo substr($product['description'], 0, 60) . (strlen($product['description']) > 60 ? '...' : ''); ?>
                                </p>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <div class="d-grid">
                                    <a href="product-details.php?sku=<?php echo $product['sku']; ?>" class="btn btn-outline-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-center">No featured products available.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="products.php" class="btn btn-primary">View All Products</a>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="mb-5">
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">Why Shop With Us</h2>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 product-card text-center">
                    <div class="card-body">
                        <i class="fas fa-truck fa-3x mb-3 text-primary"></i>
                        <h4 class="card-title">Free Shipping</h4>
                        <p class="card-text">On orders over $50. Get your items delivered right to your doorstep.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 product-card text-center">
                    <div class="card-body">
                        <i class="fas fa-undo fa-3x mb-3 text-primary"></i>
                        <h4 class="card-title">Easy Returns</h4>
                        <p class="card-text">30-day return policy. Not satisfied with your purchase? Return it hassle-free.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 product-card text-center">
                    <div class="card-body">
                        <i class="fas fa-headset fa-3x mb-3 text-primary"></i>
                        <h4 class="card-title">24/7 Support</h4>
                        <p class="card-text">Our customer service team is always ready to assist you with any questions.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="mb-5">
        <div class="card bg-light">
            <div class="card-body py-5">
                <div class="row justify-content-center">
                    <div class="col-md-8 text-center">
                        <h3>Subscribe to Our Newsletter</h3>
                        <p class="mb-4">Stay updated with our latest products and offers.</p>
                        <form class="row g-3 justify-content-center" id="newsletter-form">
                            <div class="col-md-8">
                                <input type="email" class="form-control" id="newsletter-email" placeholder="Your email address" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Subscribe</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add custom script for newsletter form -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get email value
            const email = document.getElementById('newsletter-email').value;
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            
            // Add spinner to button
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Subscribing...';
            
            // Simulate AJAX request (replace with actual implementation)
            setTimeout(function() {
                // Re-enable button and restore text
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                // Show success message
                alert('Thank you for subscribing to our newsletter!');
                
                // Reset form
                newsletterForm.reset();
            }, 1500);
        });
    }
});
</script>

<?php include 'partials/footer.php'; ?>
