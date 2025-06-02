<?php
/**
 * Shop page with advanced filters for Elegant Drapes luxury clothing store
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize filter variables
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$material_id = isset($_GET['material']) ? intval($_GET['material']) : 0;
$gender = isset($_GET['gender']) ? sanitizeInput($_GET['gender']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 1000;
$sort_by = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 12;

// Prepare the base query
$base_query = "
    SELECT p.*, c.name as category_name, m.name as material_name,
    (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN materials m ON p.material_id = m.id
    WHERE 1=1
";

// Add filters to query
$params = [];
$param_types = "";

if ($category_id > 0) {
    // Get all subcategories of the selected category
    $subcategories = [];
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id FROM categories WHERE parent_id = ? OR id = ?");
        $stmt->bind_param("ii", $category_id, $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = $row['id'];
        }
        
        $stmt->close();
    } catch (Exception $e) {
        logError("Error fetching subcategories: " . $e->getMessage());
    }
    
    if (!empty($subcategories)) {
        $category_placeholders = str_repeat('?,', count($subcategories) - 1) . '?';
        $base_query .= " AND p.category_id IN ($category_placeholders)";
        
        foreach ($subcategories as $subcat) {
            $params[] = $subcat;
            $param_types .= "i";
        }
    }
}

if ($material_id > 0) {
    $base_query .= " AND p.material_id = ?";
    $params[] = $material_id;
    $param_types .= "i";
}

if (!empty($gender) && in_array($gender, ['Men', 'Women', 'Unisex'])) {
    $base_query .= " AND (p.gender = ? OR p.gender = 'Unisex')";
    $params[] = $gender;
    $param_types .= "s";
}

$base_query .= " AND (p.sale_price IS NOT NULL AND p.sale_price BETWEEN ? AND ?) OR (p.sale_price IS NULL AND p.price BETWEEN ? AND ?)";
$params[] = $min_price;
$params[] = $max_price;
$params[] = $min_price;
$params[] = $max_price;
$param_types .= "dddd";

// Add sorting
switch ($sort_by) {
    case 'price_low':
        $base_query .= " ORDER BY COALESCE(p.sale_price, p.price) ASC";
        break;
    case 'price_high':
        $base_query .= " ORDER BY COALESCE(p.sale_price, p.price) DESC";
        break;
    case 'name_asc':
        $base_query .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $base_query .= " ORDER BY p.name DESC";
        break;
    case 'newest':
    default:
        $base_query .= " ORDER BY p.created_at DESC";
}

// Count total results for pagination
$count_query = str_replace("p.*, c.name as category_name, m.name as material_name, (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image", "COUNT(*) as total", $base_query);
$count_query = preg_replace("/ORDER BY.*/", "", $count_query);

// Fetch products with pagination
$products = [];
$total_items = 0;
$total_pages = 0;

try {
    $conn = getDbConnection();
    
    // Get total count
    $stmt = $conn->prepare($count_query);
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $count_result = $stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_items = $count_row['total'];
    $total_pages = ceil($total_items / $items_per_page);
    $stmt->close();
    
    // Adjust page if out of bounds
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    
    // Calculate offset
    $offset = ($page - 1) * $items_per_page;
    
    // Get products for current page
    $base_query .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $items_per_page;
    $param_types .= "ii";
    
    $stmt = $conn->prepare($base_query);
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($product = $result->fetch_assoc()) {
        $products[] = $product;
    }
    
    $stmt->close();
    
    // Get all categories for filter
    $categories = [];
    $stmt = $conn->prepare("SELECT id, name, parent_id FROM categories ORDER BY name");
    $stmt->execute();
    $categories_result = $stmt->get_result();
    
    while ($category = $categories_result->fetch_assoc()) {
        $categories[] = $category;
    }
    
    $stmt->close();
    
    // Get all materials for filter
    $materials = [];
    $stmt = $conn->prepare("SELECT id, name FROM materials ORDER BY name");
    $stmt->execute();
    $materials_result = $stmt->get_result();
    
    while ($material = $materials_result->fetch_assoc()) {
        $materials[] = $material;
    }
    
    $stmt->close();
    
    // Get min and max prices from database for price slider
    $stmt = $conn->prepare("
        SELECT 
            MIN(CASE WHEN sale_price IS NOT NULL THEN sale_price ELSE price END) as min_price,
            MAX(price) as max_price
        FROM products
    ");
    $stmt->execute();
    $price_result = $stmt->get_result();
    $price_range = $price_result->fetch_assoc();
    
    $db_min_price = floor($price_range['min_price']);
    $db_max_price = ceil($price_range['max_price']);
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    logError('Shop page error: ' . $e->getMessage());
}

// Set page title
$page_title = 'Shop | Elegant Drapes';

// Include header
include 'includes/header.php';
?>

<div class="container-fluid my-4">
    <div class="row">
        <!-- Filter Sidebar - Mobile Collapse -->
        <div class="col-lg-3 mb-4">
            <div class="d-lg-none mb-3">
                <button class="btn btn-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterSidebar">
                    <i class="bi bi-funnel me-2"></i> Show Filters
                </button>
            </div>
            
            <div class="collapse d-lg-block" id="filterSidebar">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Filters</h5>
                    </div>
                    <div class="card-body">
                        <form id="filterForm" method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <!-- Categories Filter -->
                            <div class="mb-4">
                                <h6 class="fw-bold">Categories</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="category" id="cat0" value="0" <?php echo $category_id === 0 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="cat0">All Categories</label>
                                </div>
                                
                                <?php 
                                // Display parent categories first
                                foreach ($categories as $category) {
                                    if ($category['parent_id'] === null) {
                                        echo '<div class="form-check">';
                                        echo '<input class="form-check-input" type="radio" name="category" id="cat' . $category['id'] . '" value="' . $category['id'] . '" ' . ($category_id === $category['id'] ? 'checked' : '') . '>';
                                        echo '<label class="form-check-label" for="cat' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</label>';
                                        echo '</div>';
                                        
                                        // Display subcategories indented
                                        foreach ($categories as $subcategory) {
                                            if ($subcategory['parent_id'] === $category['id']) {
                                                echo '<div class="form-check ms-3">';
                                                echo '<input class="form-check-input" type="radio" name="category" id="cat' . $subcategory['id'] . '" value="' . $subcategory['id'] . '" ' . ($category_id === $subcategory['id'] ? 'checked' : '') . '>';
                                                echo '<label class="form-check-label" for="cat' . $subcategory['id'] . '">' . htmlspecialchars($subcategory['name']) . '</label>';
                                                echo '</div>';
                                            }
                                        }
                                    }
                                }
                                ?>
                            </div>
                            
                            <!-- Materials Filter -->
                            <div class="mb-4">
                                <h6 class="fw-bold">Materials</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="material" id="mat0" value="0" <?php echo $material_id === 0 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mat0">All Materials</label>
                                </div>
                                
                                <?php foreach ($materials as $material): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="material" id="mat<?php echo $material['id']; ?>" 
                                           value="<?php echo $material['id']; ?>" <?php echo $material_id === $material['id'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mat<?php echo $material['id']; ?>">
                                        <?php echo htmlspecialchars($material['name']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Gender Filter -->
                            <div class="mb-4">
                                <h6 class="fw-bold">Gender</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" id="genderAll" value="" <?php echo empty($gender) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="genderAll">All</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" id="genderMen" value="Men" <?php echo $gender === 'Men' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="genderMen">Men</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" id="genderWomen" value="Women" <?php echo $gender === 'Women' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="genderWomen">Women</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" id="genderUnisex" value="Unisex" <?php echo $gender === 'Unisex' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="genderUnisex">Unisex</label>
                                </div>
                            </div>
                            
                            <!-- Price Range Filter -->
                            <div class="mb-4">
                                <h6 class="fw-bold">Price Range</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="minPrice" name="min_price" 
                                                   value="<?php echo $min_price; ?>" min="<?php echo $db_min_price; ?>" max="<?php echo $db_max_price; ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="maxPrice" name="max_price" 
                                                   value="<?php echo $max_price; ?>" min="<?php echo $db_min_price; ?>" max="<?php echo $db_max_price; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <input type="range" class="form-range" id="priceSlider" 
                                           min="<?php echo $db_min_price; ?>" max="<?php echo $db_max_price; ?>" step="10">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="shop.php" class="btn btn-outline-secondary">Clear All</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Grid -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h4 mb-0">Our Collections</h2>
                    <p class="text-muted mb-0"><?php echo $total_items; ?> products found</p>
                </div>
                
                <div class="d-flex align-items-center">
                    <label for="sortSelect" class="me-2 text-nowrap">Sort by:</label>
                    <select id="sortSelect" class="form-select form-select-sm" name="sort" onchange="updateSort(this.value)">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Name: A-Z</option>
                        <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Name: Z-A</option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    No products found matching your criteria. Try adjusting your filters.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($products as $product): ?>
                        <div class="col">
                            <div class="card h-100 product-card">
                                <?php if (!empty($product['sale_price'])): ?>
                                    <div class="badge bg-danger position-absolute top-0 start-0 m-2">SALE</div>
                                <?php endif; ?>
                                
                                <div class="position-absolute top-0 end-0 m-2">
                                    <button type="button" class="btn btn-sm btn-light rounded-circle wishlist-btn"
                                            data-sku="<?php echo $product['sku']; ?>" title="Add to wishlist">
                                        <i class="bi bi-heart"></i>
                                    </button>
                                </div>
                                
                                <a href="product.php?sku=<?php echo $product['sku']; ?>">
                                    <img src="<?php echo !empty($product['image']) ? 'uploads/products/' . $product['image'] : 'assets/img/product-placeholder.jpg'; ?>" 
                                         class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </a>
                                
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                        <?php if (!empty($product['material_name'])): ?>
                                            <span class="badge bg-info text-dark"><?php echo htmlspecialchars($product['material_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h5 class="card-title">
                                        <a href="product.php?sku=<?php echo $product['sku']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h5>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <?php if (!empty($product['sale_price'])): ?>
                                                <span class="text-decoration-line-through text-muted me-2">
                                                    $<?php echo number_format($product['price'], 2); ?>
                                                </span>
                                                <span class="fw-bold text-danger">
                                                    $<?php echo number_format($product['sale_price'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="fw-bold">
                                                    $<?php echo number_format($product['price'], 2); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="product.php?sku=<?php echo $product['sku']; ?>" class="btn btn-outline-primary btn-sm">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Product pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo getPageUrl($page - 1); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="' . getPageUrl(1) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="' . getPageUrl($i) . '">' . $i . '</a></li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="' . getPageUrl($total_pages) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo getPageUrl($page + 1); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Price slider functionality
    const minPriceInput = document.getElementById('minPrice');
    const maxPriceInput = document.getElementById('maxPrice');
    const priceSlider = document.getElementById('priceSlider');
    
    if (priceSlider) {
        noUiSlider.create(priceSlider, {
            start: [<?php echo $min_price; ?>, <?php echo $max_price; ?>],
            connect: true,
            step: 10,
            range: {
                'min': <?php echo $db_min_price; ?>,
                'max': <?php echo $db_max_price; ?>
            },
            format: {
                to: function(value) {
                    return Math.round(value);
                },
                from: function(value) {
                    return Number(value);
                }
            }
        });
        
        priceSlider.noUiSlider.on('update', function(values, handle) {
            if (handle === 0) {
                minPriceInput.value = values[0];
            } else {
                maxPriceInput.value = values[1];
            }
        });
        
        minPriceInput.addEventListener('change', function() {
            priceSlider.noUiSlider.set([this.value, null]);
        });
        
        maxPriceInput.addEventListener('change', function() {
            priceSlider.noUiSlider.set([null, this.value]);
        });
    }
    
    // Wishlist buttons
    const wishlistButtons = document.querySelectorAll('.wishlist-btn');
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function() {
            const sku = this.getAttribute('data-sku');
            addToWishlist(sku, this);
        });
    });
});

// Function to update sort and submit form
function updateSort(sortValue) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('sort', sortValue);
    window.location.href = currentUrl.toString();
}

// Function to add product to wishlist
function addToWishlist(sku, button) {
    // Check if user is logged in
    <?php if (isLoggedIn()): ?>
        // Use AJAX to add to wishlist
        fetch('ajax/wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=add&product_sku=' + sku
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update button appearance
                button.innerHTML = '<i class="bi bi-heart-fill text-danger"></i>';
                showToast('Success', 'Item added to your wishlist!', 'success');
            } else {
                if (data.message === 'already_in_wishlist') {
                    showToast('Info', 'This item is already in your wishlist', 'info');
                } else {
                    showToast('Error', 'Failed to add item to wishlist', 'danger');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', 'An error occurred. Please try again.', 'danger');
        });
    <?php else: ?>
        // Prompt user to login
        showToast('Login Required', 'Please login to add items to your wishlist', 'warning');
        // Store the product in session to add after login
        fetch('ajax/wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=remember&product_sku=' + sku
        });
    <?php endif; ?>
}

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

// Helper function to generate pagination URLs
function getPageUrl(page) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('page', page);
    return currentUrl.toString();
}
</script>

<?php
// Helper function to generate pagination URLs
function getPageUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<!-- Add noUiSlider for price range -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>

<style>
.product-card {
    transition: transform 0.3s, box-shadow 0.3s;
    border: none;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.product-img {
    height: 250px;
    object-fit: cover;
}

/* Price Range Slider */
.noUi-connect {
    background: #1a2456;
}

.noUi-handle {
    border-radius: 50%;
    width: 20px !important;
    height: 20px !important;
    top: -8px !important;
    background: #d4af37;
    box-shadow: none;
}

.noUi-handle:before, .noUi-handle:after {
    display: none;
}

@media (max-width: 991.98px) {
    #filterSidebar {
        margin-bottom: 1.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
