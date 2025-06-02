<?php
// Products page for e-commerce website
$pageTitle = "Products";
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get filter parameters
$category = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$size = isset($_GET['size']) ? sanitize($_GET['size']) : null;
$color = isset($_GET['color']) ? sanitize($_GET['color']) : null;

// Items per page
$itemsPerPage = 12;

// Get DB instance
$db = Database::getInstance();

// Build SQL query with placeholders
$sql = "SELECT p.sku, p.name, p.description, p.price, 
        (SELECT image_path FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image 
        FROM products p";

// Join tables if needed based on filters
if ($size || $color || $category) {
    if ($size) {
        $sql .= " JOIN product_sizes ps ON p.sku = ps.product_sku";
    }
    if ($color) {
        $sql .= " JOIN product_colors pc ON p.sku = pc.product_sku";
    }
    if ($category) {
        $sql .= " JOIN product_categories pcat ON p.sku = pcat.product_sku";
    }
}

// Add WHERE conditions
$conditions = [];
$params = [];
$paramTypes = '';

if ($search) {
    $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= 'sss';
}

if ($category) {
    $conditions[] = "pcat.category_id = ?";
    $params[] = $category;
    $paramTypes .= 'i';
}

if ($size) {
    $conditions[] = "ps.size_name = ?";
    $params[] = $size;
    $paramTypes .= 's';
}

if ($color) {
    $conditions[] = "pc.color_name = ?";
    $params[] = $color;
    $paramTypes .= 's';
}

if ($minPrice) {
    $conditions[] = "p.price >= ?";
    $params[] = $minPrice;
    $paramTypes .= 'd';
}

if ($maxPrice) {
    $conditions[] = "p.price <= ?";
    $params[] = $maxPrice;
    $paramTypes .= 'd';
}

// Combine conditions
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Add GROUP BY to handle multiple joins
if ($size || $color || $category) {
    $sql .= " GROUP BY p.sku";
}

// Add ORDER BY
switch ($sortBy) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY p.name DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY p.created_at ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

// Count total products with the same filter
$countSql = "SELECT COUNT(DISTINCT p.sku) as total FROM products p";

// Apply the same JOINs and WHERE conditions for the count query
if ($size || $color || $category) {
    if ($size) {
        $countSql .= " JOIN product_sizes ps ON p.sku = ps.product_sku";
    }
    if ($color) {
        $countSql .= " JOIN product_colors pc ON p.sku = pc.product_sku";
    }
    if ($category) {
        $countSql .= " JOIN product_categories pcat ON p.sku = pcat.product_sku";
    }
}

if (!empty($conditions)) {
    $countSql .= " WHERE " . implode(" AND ", $conditions);
}

// Prepare and execute count query
$totalProducts = 0;
$countStmt = $db->prepare($countSql);
if ($countStmt && !empty($params)) {
    $countStmt->bind_param($paramTypes, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult && $countResult->num_rows > 0) {
        $totalProducts = $countResult->fetch_assoc()['total'];
    }
} else if ($countStmt) {
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult && $countResult->num_rows > 0) {
        $totalProducts = $countResult->fetch_assoc()['total'];
    }
}

// Add pagination limits
$offset = ($page - 1) * $itemsPerPage;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $itemsPerPage;
$paramTypes .= 'ii';

// Prepare and execute main query
$products = [];
$stmt = $db->prepare($sql);
if ($stmt) {
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
}

// Get categories for filter
$categories = [];
$categoryQuery = "SELECT id, name FROM categories ORDER BY name";
$categoryResult = $db->query($categoryQuery);
if ($categoryResult && $categoryResult->num_rows > 0) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get sizes for filter
$sizes = [];
$sizeQuery = "SELECT name FROM sizes ORDER BY name";
$sizeResult = $db->query($sizeQuery);
if ($sizeResult && $sizeResult->num_rows > 0) {
    while ($row = $sizeResult->fetch_assoc()) {
        $sizes[] = $row['name'];
    }
}

// Get colors for filter
$colors = [];
$colorQuery = "SELECT name FROM colors ORDER BY name";
$colorResult = $db->query($colorQuery);
if ($colorResult && $colorResult->num_rows > 0) {
    while ($row = $colorResult->fetch_assoc()) {
        $colors[] = $row['name'];
    }
}

// Get price range for filter
$priceRange = [];
$priceQuery = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products";
$priceResult = $db->query($priceQuery);
if ($priceResult && $priceResult->num_rows > 0) {
    $priceRange = $priceResult->fetch_assoc();
}

// Generate pagination
$pagination = paginate($totalProducts, $page, $itemsPerPage, '?page=(:num)' . 
               (isset($_GET['category']) ? '&category=' . $_GET['category'] : '') . 
               (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '') .
               (isset($_GET['min_price']) ? '&min_price=' . $_GET['min_price'] : '') .
               (isset($_GET['max_price']) ? '&max_price=' . $_GET['max_price'] : '') .
               (isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : '') .
               (isset($_GET['size']) ? '&size=' . $_GET['size'] : '') .
               (isset($_GET['color']) ? '&color=' . $_GET['color'] : ''));

// Get category name if filter is active
$categoryName = '';
if ($category) {
    $catQuery = "SELECT name FROM categories WHERE id = ?";
    $catStmt = $db->prepare($catQuery);
    $catStmt->bind_param('i', $category);
    $catStmt->execute();
    $catResult = $catStmt->get_result();
    if ($catResult && $catResult->num_rows > 0) {
        $categoryName = $catResult->fetch_assoc()['name'];
    }
}

include 'partials/header.php';
include 'partials/navbar.php';
?>

<!-- Add meta tag for site URL for JavaScript -->
<meta name="site-url" content="<?php echo SITE_URL; ?>">

<div class="container main-content products-section">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <?php if ($category && $categoryName): ?>
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>products.php">Products</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo $categoryName; ?></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page">Products</li>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Filter Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form id="product-filters" method="GET" action="<?php echo SITE_URL; ?>products.php">
                        <!-- Preserve search query if exists -->
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        
                        <!-- Categories -->
                        <div class="mb-4">
                            <h6>Categories</h6>
                            <div class="form-group">
                                <select class="form-select" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo $cat['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Price Range -->
                        <div class="mb-4">
                            <h6>Price Range</h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="min-price" class="form-label">Min</label>
                                    <input type="number" class="form-control" id="min-price" name="min_price" 
                                           min="<?php echo $priceRange['min_price'] ?? 0; ?>" 
                                           max="<?php echo $priceRange['max_price'] ?? 1000; ?>" 
                                           value="<?php echo $minPrice ?? ''; ?>" placeholder="Min">
                                </div>
                                <div class="col-6">
                                    <label for="max-price" class="form-label">Max</label>
                                    <input type="number" class="form-control" id="max-price" name="max_price" 
                                           min="<?php echo $priceRange['min_price'] ?? 0; ?>" 
                                           max="<?php echo $priceRange['max_price'] ?? 1000; ?>" 
                                           value="<?php echo $maxPrice ?? ''; ?>" placeholder="Max">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sizes -->
                        <?php if (!empty($sizes)): ?>
                        <div class="mb-4">
                            <h6>Size</h6>
                            <div class="form-group">
                                <select class="form-select" name="size">
                                    <option value="">All Sizes</option>
                                    <?php foreach ($sizes as $sizeOption): ?>
                                        <option value="<?php echo $sizeOption; ?>" <?php echo $size === $sizeOption ? 'selected' : ''; ?>>
                                            <?php echo $sizeOption; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Colors -->
                        <?php if (!empty($colors)): ?>
                        <div class="mb-4">
                            <h6>Color</h6>
                            <div class="form-group">
                                <select class="form-select" name="color">
                                    <option value="">All Colors</option>
                                    <?php foreach ($colors as $colorOption): ?>
                                        <option value="<?php echo $colorOption; ?>" <?php echo $color === $colorOption ? 'selected' : ''; ?>>
                                            <?php echo $colorOption; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sort By -->
                        <div class="mb-4">
                            <h6>Sort By</h6>
                            <div class="form-group">
                                <select class="form-select" name="sort">
                                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price (Low to High)</option>
                                    <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price (High to Low)</option>
                                    <option value="name_asc" <?php echo $sortBy === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                    <option value="name_desc" <?php echo $sortBy === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Filter Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <button type="button" class="btn btn-outline-secondary filter-reset">Reset Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Products Display -->
        <div class="col-lg-9">
            <!-- Page heading -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2>
                        <?php if ($search): ?>
                            Search Results for "<?php echo htmlspecialchars($search); ?>"
                        <?php elseif ($category && $categoryName): ?>
                            <?php echo $categoryName; ?>
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                    </h2>
                    <p><?php echo $totalProducts; ?> products found</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <!-- Mobile filter toggle button -->
                    <button class="btn btn-outline-primary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                </div>
            </div>
            
            <!-- Products grid -->
            <div class="row g-4 products-container">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-6 col-md-4">
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
                                    <div class="d-grid gap-2">
                                        <a href="product-details.php?sku=<?php echo $product['sku']; ?>" class="btn btn-outline-primary">View Details</a>
                                        <form action="api/cart.php?action=add" method="POST" class="add-to-cart-form">
                                            <input type="hidden" name="product_sku" value="<?php echo $product['sku']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-shopping-cart"></i> Add to Cart
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            No products found matching your criteria. Try adjusting your filters or <a href="<?php echo SITE_URL; ?>products.php">view all products</a>.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalProducts > $itemsPerPage): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav aria-label="Product pagination" class="pagination-container">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagination['has_previous']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $pagination['links'][1]; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $pagination['links'][$pagination['previous_page']]; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php foreach ($pagination['pages'] as $pageNum): ?>
                                <li class="page-item <?php echo $pageNum === $pagination['current_page'] ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $pagination['links'][$pageNum]; ?>">
                                        <?php echo $pageNum; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            
                            <?php if ($pagination['has_next']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $pagination['links'][$pagination['next_page']]; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo $pagination['links'][$pagination['last_page']]; ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mobile Filters Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
    <div class="offcanvas-header bg-primary text-white">
        <h5 class="offcanvas-title" id="filterOffcanvasLabel">Product Filters</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <!-- The filter form will be cloned here via JavaScript -->
        <div id="mobile-filter-container"></div>
    </div>
</div>

<!-- Script to handle mobile filters -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clone filter form for mobile view
    const filterForm = document.getElementById('product-filters');
    const mobileFilterContainer = document.getElementById('mobile-filter-container');
    
    if (filterForm && mobileFilterContainer) {
        const clonedForm = filterForm.cloneNode(true);
        clonedForm.id = 'mobile-product-filters';
        mobileFilterContainer.appendChild(clonedForm);
        
        // Handle form submission for mobile filters
        clonedForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data and redirect
            const formData = new FormData(clonedForm);
            const searchParams = new URLSearchParams(formData);
            window.location.href = '<?php echo SITE_URL; ?>products.php?' + searchParams.toString();
        });
        
        // Handle reset button
        const resetButton = clonedForm.querySelector('.filter-reset');
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                clonedForm.reset();
                clonedForm.submit();
            });
        }
    }
});
</script>

<?php include 'partials/footer.php'; ?>
