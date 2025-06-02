<?php
// Product details page for e-commerce website
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get product SKU from URL
$sku = isset($_GET['sku']) ? sanitize($_GET['sku']) : null;

// If SKU is not provided, redirect to products page
if (!$sku) {
    setFlashMessage('Product not found.', 'danger');
    redirect(SITE_URL . 'products.php');
}

// Get DB instance
$db = Database::getInstance();

// Get product details
$product = null;
$productQuery = "SELECT p.sku, p.name, p.description, p.price, p.stock 
                FROM products p 
                WHERE p.sku = ?";
$stmt = $db->prepare($productQuery);
$stmt->bind_param('s', $sku);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $product = $result->fetch_assoc();
} else {
    setFlashMessage('Product not found.', 'danger');
    redirect(SITE_URL . 'products.php');
}

// Get product images
$images = [];
$imagesQuery = "SELECT id, image_path, is_primary FROM product_images WHERE product_sku = ? ORDER BY is_primary DESC, id ASC";
$imagesStmt = $db->prepare($imagesQuery);
$imagesStmt->bind_param('s', $sku);
$imagesStmt->execute();
$imagesResult = $imagesStmt->get_result();

if ($imagesResult && $imagesResult->num_rows > 0) {
    while ($row = $imagesResult->fetch_assoc()) {
        $images[] = $row;
    }
}

// Get product categories
$categories = [];
$categoriesQuery = "SELECT c.id, c.name 
                    FROM categories c 
                    JOIN product_categories pc ON c.id = pc.category_id 
                    WHERE pc.product_sku = ?";
$categoriesStmt = $db->prepare($categoriesQuery);
$categoriesStmt->bind_param('s', $sku);
$categoriesStmt->execute();
$categoriesResult = $categoriesStmt->get_result();

if ($categoriesResult && $categoriesResult->num_rows > 0) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get product sizes
$sizes = [];
$sizesQuery = "SELECT ps.size_name, ps.stock 
              FROM product_sizes ps 
              WHERE ps.product_sku = ? 
              ORDER BY ps.size_name";
$sizesStmt = $db->prepare($sizesQuery);
$sizesStmt->bind_param('s', $sku);
$sizesStmt->execute();
$sizesResult = $sizesStmt->get_result();

if ($sizesResult && $sizesResult->num_rows > 0) {
    while ($row = $sizesResult->fetch_assoc()) {
        $sizes[] = $row;
    }
}

// Get product colors
$colors = [];
$colorsQuery = "SELECT pc.color_name, pc.stock 
               FROM product_colors pc 
               WHERE pc.product_sku = ? 
               ORDER BY pc.color_name";
$colorsStmt = $db->prepare($colorsQuery);
$colorsStmt->bind_param('s', $sku);
$colorsStmt->execute();
$colorsResult = $colorsStmt->get_result();

if ($colorsResult && $colorsResult->num_rows > 0) {
    while ($row = $colorsResult->fetch_assoc()) {
        $colors[] = $row;
    }
}

// Get related products (from same categories)
$relatedProducts = [];
if (!empty($categories)) {
    $categoryIds = array_column($categories, 'id');
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    
    // Build query with dynamic placeholders
    $relatedQuery = "SELECT DISTINCT p.sku, p.name, p.price,
                     (SELECT image_path FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image
                     FROM products p
                     JOIN product_categories pc ON p.sku = pc.product_sku
                     WHERE pc.category_id IN ($placeholders)
                     AND p.sku != ?
                     ORDER BY RAND()
                     LIMIT 4";
    
    // Create parameter types string (all 'i' for category IDs plus one 's' for SKU)
    $paramTypes = str_repeat('i', count($categoryIds)) . 's';
    
    // Prepare parameters array
    $params = $categoryIds;
    $params[] = $sku;
    
    // Prepare and execute query
    $relatedStmt = $db->prepare($relatedQuery);
    if ($relatedStmt) {
        $relatedStmt->bind_param($paramTypes, ...$params);
        $relatedStmt->execute();
        $relatedResult = $relatedStmt->get_result();
        
        if ($relatedResult && $relatedResult->num_rows > 0) {
            while ($row = $relatedResult->fetch_assoc()) {
                $relatedProducts[] = $row;
            }
        }
    }
}

// Set page title
$pageTitle = $product['name'];

include 'partials/header.php';
include 'partials/navbar.php';
?>

<!-- Add meta tag for site URL for JavaScript -->
<meta name="site-url" content="<?php echo SITE_URL; ?>">

<div class="container main-content">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>products.php">Products</a></li>
                    <?php if (!empty($categories)): ?>
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>products.php?category=<?php echo $categories[0]['id']; ?>"><?php echo $categories[0]['name']; ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $product['name']; ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-5">
        <!-- Product Images -->
        <div class="col-md-6 mb-4">
            <div class="product-image-container text-center mb-3">
                <?php if (!empty($images)): ?>
                    <img src="<?php echo SITE_URL . 'assets/uploads/products/' . $images[0]['image_path']; ?>" alt="<?php echo $product['name']; ?>" class="img-fluid product-main-image">
                <?php else: ?>
                    <img src="https://via.placeholder.com/600x600?text=No+Image" alt="No image available" class="img-fluid product-main-image">
                <?php endif; ?>
            </div>
            
            <?php if (count($images) > 1): ?>
                <div class="row product-image-gallery g-2">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="col-3">
                            <img src="<?php echo SITE_URL . 'assets/uploads/products/' . $image['image_path']; ?>" 
                                 alt="<?php echo $product['name'] . ' image ' . ($index + 1); ?>" 
                                 class="img-fluid img-thumbnail <?php echo $index === 0 ? 'border border-primary' : ''; ?>"
                                 onclick="changeMainImage(this.src)">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Details -->
        <div class="col-md-6">
            <h1 class="h2 mb-3"><?php echo $product['name']; ?></h1>
            
            <div class="mb-3">
                <h2 class="h4 text-primary mb-0 price"><?php echo formatPrice($product['price']); ?></h2>
                
                <?php if ($product['stock'] > 0): ?>
                    <span class="badge bg-success">In Stock</span>
                <?php else: ?>
                    <span class="badge bg-danger">Out of Stock</span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($categories)): ?>
                <div class="mb-3">
                    <?php foreach ($categories as $category): ?>
                        <a href="<?php echo SITE_URL; ?>products.php?category=<?php echo $category['id']; ?>" class="category-badge text-decoration-none">
                            <?php echo $category['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <p><?php echo nl2br($product['description']); ?></p>
            </div>
            
            <form action="<?php echo SITE_URL; ?>api/cart.php?action=add" method="POST" class="add-to-cart-form">
                <input type="hidden" name="product_sku" value="<?php echo $product['sku']; ?>">
                
                <?php if (!empty($sizes)): ?>
                <div class="mb-3">
                    <label for="size" class="form-label">Size</label>
                    <select class="form-select" id="size" name="size" required>
                        <option value="">Select Size</option>
                        <?php foreach ($sizes as $size): ?>
                            <option value="<?php echo $size['size_name']; ?>" <?php echo $size['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <?php echo $size['size_name']; ?> <?php echo $size['stock'] <= 0 ? '(Out of Stock)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($colors)): ?>
                <div class="mb-3">
                    <label for="color" class="form-label">Color</label>
                    <select class="form-select" id="color" name="color" required>
                        <option value="">Select Color</option>
                        <?php foreach ($colors as $color): ?>
                            <option value="<?php echo $color['color_name']; ?>" <?php echo $color['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <?php echo $color['color_name']; ?> <?php echo $color['stock'] <= 0 ? '(Out of Stock)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <label for="quantity" class="form-label">Quantity</label>
                    <div class="quantity-control">
                        <button type="button" class="btn btn-outline-secondary quantity-minus">-</button>
                        <input type="number" class="form-control mx-2" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" required>
                        <button type="button" class="btn btn-outline-secondary quantity-plus">+</button>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                    <a href="<?php echo SITE_URL; ?>products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </form>
            
            <div class="mt-4">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-truck text-muted me-2"></i>
                    <span>Free shipping on orders over $50</span>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-undo text-muted me-2"></i>
                    <span>30-day return policy</span>
                </div>
                <div class="d-flex align-items-center">
                    <i class="fas fa-shield-alt text-muted me-2"></i>
                    <span>Secure payment</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
    <section class="mb-5">
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4">Related Products</h3>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ($relatedProducts as $related): ?>
                <div class="col-6 col-md-3">
                    <div class="card h-100 product-card">
                        <a href="product-details.php?sku=<?php echo $related['sku']; ?>" class="text-decoration-none">
                            <?php if (!empty($related['image'])): ?>
                                <img src="<?php echo SITE_URL . 'assets/uploads/products/' . $related['image']; ?>" class="card-img-top" alt="<?php echo $related['name']; ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x300?text=No+Image" class="card-img-top" alt="No image available">
                            <?php endif; ?>
                        </a>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="product-details.php?sku=<?php echo $related['sku']; ?>" class="text-decoration-none text-dark">
                                    <?php echo $related['name']; ?>
                                </a>
                            </h5>
                            <p class="card-text price"><?php echo formatPrice($related['price']); ?></p>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="product-details.php?sku=<?php echo $related['sku']; ?>" class="btn btn-outline-primary w-100">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Custom script for product details -->
<script>
function changeMainImage(src) {
    document.querySelector('.product-main-image').src = src;
    
    // Update active thumbnail
    document.querySelectorAll('.product-image-gallery img').forEach(img => {
        if (img.src === src) {
            img.classList.add('border', 'border-primary');
        } else {
            img.classList.remove('border', 'border-primary');
        }
    });
}
</script>

<?php include 'partials/footer.php'; ?>
