<?php
/**
 * Admin Products Management
 */
$pageTitle = "Products Management";
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isAdmin()) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect(SITE_URL);
    exit;
}

// Get database instance
$db = Database::getInstance();

// Default action
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Process form submissions first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create':
            createProduct();
            break;
        case 'edit':
            updateProduct();
            break;
    }
}

// Handle AJAX delete requests
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($action === 'delete') {
        deleteProduct();
        exit;
    }
}

// Include header
include '../admin/partials/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../admin/partials/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <?php
            // Display appropriate content based on action
            switch ($action) {
                case 'view':
                    if (isset($_GET['sku'])) {
                        displayProductDetails($_GET['sku']);
                    } else {
                        redirect(SITE_URL . 'admin/products.php');
                    }
                    break;
                case 'create':
                    displayCreateForm();
                    break;
                case 'edit':
                    if (isset($_GET['sku'])) {
                        displayEditForm($_GET['sku']);
                    } else {
                        redirect(SITE_URL . 'admin/products.php');
                    }
                    break;
                case 'delete':
                    // This will be handled by the AJAX delete request
                    redirect(SITE_URL . 'admin/products.php');
                    break;
                default:
                    displayProductsList();
                    break;
            }
            ?>
        </main>
    </div>
</div>

<?php
// Function to display products list
function displayProductsList() {
    global $db;
    
    // Initialize pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Get total products count
    $totalQuery = "SELECT COUNT(*) as count FROM products";
    $totalResult = $db->fetchOne($totalQuery);
    $totalProducts = $totalResult['count'];
    $totalPages = ceil($totalProducts / $limit);
    
    // Get search parameters
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
    
    // Build query with filters
    $query = "SELECT p.SKU, p.name, p.price, p.stock, 
             (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
              FROM product_categories pc 
              JOIN categories c ON pc.category_id = c.id 
              WHERE pc.product_sku = p.SKU) as categories
             FROM products p";
    
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $whereConditions[] = "(p.name LIKE ? OR p.SKU LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    if (!empty($categoryFilter)) {
        $query .= " JOIN product_categories pc2 ON p.SKU = pc2.product_sku";
        $whereConditions[] = "pc2.category_id = ?";
        $params[] = $categoryFilter;
        $types .= 'i';
    }
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(' AND ', $whereConditions);
    }
    
    $query .= " ORDER BY p.name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Execute query
    $products = $db->fetchAllWithParams($query, $types, $params);
    
    // Get categories for filter
    $categoriesQuery = "SELECT id, name FROM categories ORDER BY name";
    $categories = $db->fetchAll($categoriesQuery);
    
    // Log the activity
    logActivity("Admin viewed products list", 'info');
    ?>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Products Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="<?= SITE_URL ?>admin/products.php?action=create" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <?php displayFlashMessages(); ?>
    
    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="<?= SITE_URL ?>admin/products.php" method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or SKU" value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $categoryFilter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products list -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($products)): ?>
                <div class="alert alert-info">No products found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="sortable">SKU</th>
                                <th class="sortable">Name</th>
                                <th class="sortable">Price</th>
                                <th>Categories</th>
                                <th class="sortable">Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= $product['SKU'] ?></td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td>$<?= number_format($product['price'], 2) ?></td>
                                    <td><?= htmlspecialchars($product['categories'] ?? 'Uncategorized') ?></td>
                                    <td>
                                        <?php if ($product['stock'] <= 0): ?>
                                            <span class="badge bg-danger">Out of stock</span>
                                        <?php elseif ($product['stock'] < 10): ?>
                                            <span class="badge bg-warning"><?= $product['stock'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $product['stock'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= SITE_URL ?>admin/products.php?action=view&sku=<?= $product['SKU'] ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= SITE_URL ?>admin/products.php?action=edit&sku=<?= $product['SKU'] ?>" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= SITE_URL ?>admin/products.php?action=delete&sku=<?= $product['SKU'] ?>" class="btn btn-danger delete-btn" data-name="<?= htmlspecialchars($product['name']) ?>" data-bs-toggle="tooltip" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= SITE_URL ?>admin/products.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= SITE_URL ?>admin/products.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= SITE_URL ?>admin/products.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Function to create a new product
function createProduct() {
    global $db;
    
    // Get form data
    $sku = isset($_POST['sku']) ? sanitize($_POST['sku']) : '';
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
    $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    // Validate data
    $errors = [];
    
    if (empty($sku)) {
        $errors[] = 'SKU is required.';
    } else {
        // Check if SKU already exists
        $checkQuery = "SELECT COUNT(*) as count FROM products WHERE SKU = ?";
        $result = $db->fetchOneWithParams($checkQuery, 's', [$sku]);
        
        if ($result && $result['count'] > 0) {
            $errors[] = 'SKU already exists. Please choose a different SKU.';
        }
    }
    
    if (empty($name)) {
        $errors[] = 'Product name is required.';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than zero.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative.';
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Insert product
            $insertQuery = "INSERT INTO products (SKU, name, description, price, stock) VALUES (?, ?, ?, ?, ?)";
            $result = $db->executeWithParams($insertQuery, 'sssdi', [$sku, $name, $description, $price, $stock]);
            
            if ($result === false) {
                throw new Exception('Failed to insert product.');
            }
            
            // Insert categories if any
            if (!empty($categories)) {
                foreach ($categories as $categoryId) {
                    $insertCatQuery = "INSERT INTO product_categories (product_sku, category_id) VALUES (?, ?)";
                    $catResult = $db->executeWithParams($insertCatQuery, 'si', [$sku, $categoryId]);
                    
                    if ($catResult === false) {
                        throw new Exception('Failed to associate product with category.');
                    }
                }
            }
            
            // Handle image upload if any
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = ROOT_PATH . '/assets/uploads/products/';
                $uploadedFile = uploadFile($_FILES['product_image'], $uploadDir);
                
                if ($uploadedFile) {
                    // Save image path to database
                    $imagePath = 'assets/uploads/products/' . $uploadedFile;
                    $imageQuery = "INSERT INTO product_images (product_sku, image_path) VALUES (?, ?)";
                    $imageResult = $db->executeWithParams($imageQuery, 'ss', [$sku, $imagePath]);
                    
                    if ($imageResult === false) {
                        throw new Exception('Failed to save product image.');
                    }
                } else {
                    throw new Exception('Failed to upload product image.');
                }
            }
            
            // Commit transaction
            $db->commit();
            
            // Set success message
            setFlashMessage('Product created successfully!', 'success');
            
            // Log activity
            logActivity("Admin created new product: $name (SKU: $sku)", 'info');
            
            // Redirect to product list
            redirect(SITE_URL . 'admin/products.php');
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            
            // Set error message
            $errors[] = 'Error: ' . $e->getMessage();
            
            // Log error
            logActivity("Product creation failed: " . $e->getMessage(), 'error');
        }
    }
    
    // If we reach here, there were errors
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
    }
}

// Function to display product create form
function displayCreateForm() {
    global $db;
    
    // Get categories for dropdown
    $categoriesQuery = "SELECT id, name FROM categories ORDER BY name";
    $categories = $db->fetchAll($categoriesQuery);
    
    // Get sizes for dropdown
    $sizesQuery = "SELECT name FROM sizes ORDER BY name";
    $sizes = $db->fetchAll($sizesQuery);
    
    // Get colors for dropdown
    $colorsQuery = "SELECT name FROM colors ORDER BY name";
    $colors = $db->fetchAll($colorsQuery);
    
    // Get form data from session if available (in case of validation errors)
    $formData = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
    $errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
    
    // Clear session data
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);
    ?>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Add New Product</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="<?= SITE_URL ?>admin/products.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <?php displayFlashMessages(); ?>
    
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Product Form -->
    <div class="card">
        <div class="card-body">
            <form action="<?= SITE_URL ?>admin/products.php?action=create" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="row">
                    <!-- Basic Product Information -->
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Product Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="sku" name="sku" value="<?= isset($formData['sku']) ? htmlspecialchars($formData['sku']) : '' ?>" required>
                                    <div class="form-text">Unique product identifier</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= isset($formData['name']) ? htmlspecialchars($formData['name']) : '' ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="5"><?= isset($formData['description']) ? htmlspecialchars($formData['description']) : '' ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="price" name="price" min="0.01" step="0.01" value="<?= isset($formData['price']) ? htmlspecialchars($formData['price']) : '' ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?= isset($formData['stock']) ? htmlspecialchars($formData['stock']) : '0' ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Categories -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Categories</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Select Categories</label>
                                    <div class="row">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="col-md-4">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="categories[]" value="<?= $category['id'] ?>" id="category_<?= $category['id'] ?>" 
                                                        <?= isset($formData['categories']) && in_array($category['id'], $formData['categories']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="category_<?= $category['id'] ?>">
                                                        <?= htmlspecialchars($category['name']) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Image and Additional Info -->
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Product Image</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="product_image" class="form-label">Upload Image</label>
                                    <input type="file" class="form-control image-upload" id="product_image" name="product_image" data-preview="image-preview" accept="image/*">
                                    <div class="form-text">Recommended size: 800x800 pixels</div>
                                </div>
                                <div class="text-center mt-3">
                                    <img id="image-preview" class="image-preview" style="display: none; max-width: 100%;" alt="Product image preview">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sizes and Colors (Advanced options) -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Advanced Options</h5>
                                <div class="form-text">You can add sizes and colors after creating the product</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="reset" class="btn btn-secondary">Reset</button>
                    <button type="submit" class="btn btn-primary">Create Product</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Function to update product
function updateProduct() {
    global $db;
    
    // Get form data
    $sku = isset($_POST['sku']) ? sanitize($_POST['sku']) : '';
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
    $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $deleteImages = isset($_POST['delete_images']) ? $_POST['delete_images'] : [];
    
    // Validate data
    $errors = [];
    
    if (empty($sku)) {
        $errors[] = 'SKU is required.';
    }
    
    if (empty($name)) {
        $errors[] = 'Product name is required.';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than zero.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative.';
    }
    
    // Check if product exists
    $checkQuery = "SELECT COUNT(*) as count FROM products WHERE SKU = ?";
    $result = $db->fetchOneWithParams($checkQuery, 's', [$sku]);
    
    if (!$result || $result['count'] == 0) {
        $errors[] = 'Product not found.';
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Update product
            $updateQuery = "UPDATE products SET name = ?, description = ?, price = ?, stock = ? WHERE SKU = ?";
            $result = $db->executeWithParams($updateQuery, 'ssdis', [$name, $description, $price, $stock, $sku]);
            
            if ($result === false) {
                throw new Exception('Failed to update product.');
            }
            
            // Update categories
            // First delete all existing categories
            $deleteCatQuery = "DELETE FROM product_categories WHERE product_sku = ?";
            $catResult = $db->executeWithParams($deleteCatQuery, 's', [$sku]);
            
            if ($catResult === false) {
                throw new Exception('Failed to update product categories.');
            }
            
            // Then insert new categories
            if (!empty($categories)) {
                foreach ($categories as $categoryId) {
                    $insertCatQuery = "INSERT INTO product_categories (product_sku, category_id) VALUES (?, ?)";
                    $catResult = $db->executeWithParams($insertCatQuery, 'si', [$sku, $categoryId]);
                    
                    if ($catResult === false) {
                        throw new Exception('Failed to associate product with category.');
                    }
                }
            }
            
            // Delete selected images
            if (!empty($deleteImages)) {
                // Get image paths first to delete files
                $imagesQuery = "SELECT * FROM product_images WHERE id IN (" . implode(',', array_fill(0, count($deleteImages), '?')) . ")";
                $types = str_repeat('i', count($deleteImages));
                $images = $db->fetchAllWithParams($imagesQuery, $types, $deleteImages);
                
                // Delete database records
                $deleteImgQuery = "DELETE FROM product_images WHERE id IN (" . implode(',', array_fill(0, count($deleteImages), '?')) . ")";
                $imgResult = $db->executeWithParams($deleteImgQuery, $types, $deleteImages);
                
                if ($imgResult === false) {
                    throw new Exception('Failed to delete product images.');
                }
                
                // Delete files
                foreach ($images as $image) {
                    $imagePath = ROOT_PATH . '/' . $image['image_path'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
            }
            
            // Handle new image upload if any
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = ROOT_PATH . '/assets/uploads/products/';
                $uploadedFile = uploadFile($_FILES['product_image'], $uploadDir);
                
                if ($uploadedFile) {
                    // Save image path to database
                    $imagePath = 'assets/uploads/products/' . $uploadedFile;
                    $imageQuery = "INSERT INTO product_images (product_sku, image_path) VALUES (?, ?)";
                    $imageResult = $db->executeWithParams($imageQuery, 'ss', [$sku, $imagePath]);
                    
                    if ($imageResult === false) {
                        throw new Exception('Failed to save product image.');
                    }
                } else {
                    throw new Exception('Failed to upload product image.');
                }
            }
            
            // Commit transaction
            $db->commit();
            
            // Set success message
            setFlashMessage('Product updated successfully!', 'success');
            
            // Log activity
            logActivity("Admin updated product: $name (SKU: $sku)", 'info');
            
            // Redirect to product view
            redirect(SITE_URL . 'admin/products.php?action=view&sku=' . urlencode($sku));
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            
            // Set error message
            $errors[] = 'Error: ' . $e->getMessage();
            
            // Log error
            logActivity("Product update failed: " . $e->getMessage(), 'error');
        }
    }
    
    // If we reach here, there were errors
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        redirect(SITE_URL . 'admin/products.php?action=edit&sku=' . urlencode($sku));
        exit;
    }
}

// Function to delete product
function deleteProduct() {
    global $db;
    
    // Get product SKU
    $sku = isset($_GET['sku']) ? sanitize($_GET['sku']) : '';
    
    // Validate SKU
    if (empty($sku)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid product SKU.']);
        exit;
    }
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Get product name for logging
        $productQuery = "SELECT name FROM products WHERE SKU = ?";
        $product = $db->fetchOneWithParams($productQuery, 's', [$sku]);
        
        if (!$product) {
            throw new Exception('Product not found.');
        }
        
        $productName = $product['name'];
        
        // Get product images to delete files
        $imagesQuery = "SELECT * FROM product_images WHERE product_sku = ?";
        $images = $db->fetchAllWithParams($imagesQuery, 's', [$sku]);
        
        // Delete product categories
        $deleteCatQuery = "DELETE FROM product_categories WHERE product_sku = ?";
        $catResult = $db->executeWithParams($deleteCatQuery, 's', [$sku]);
        
        if ($catResult === false) {
            throw new Exception('Failed to delete product categories.');
        }
        
        // Delete product images from database
        $deleteImgQuery = "DELETE FROM product_images WHERE product_sku = ?";
        $imgResult = $db->executeWithParams($deleteImgQuery, 's', [$sku]);
        
        if ($imgResult === false) {
            throw new Exception('Failed to delete product images.');
        }
        
        // Delete product
        $deleteQuery = "DELETE FROM products WHERE SKU = ?";
        $result = $db->executeWithParams($deleteQuery, 's', [$sku]);
        
        if ($result === false) {
            throw new Exception('Failed to delete product.');
        }
        
        // Commit transaction
        $db->commit();
        
        // Delete image files
        foreach ($images as $image) {
            $imagePath = ROOT_PATH . '/' . $image['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // Log activity
        logActivity("Admin deleted product: $productName (SKU: $sku)", 'info');
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        
        // Log error
        logActivity("Product deletion failed: " . $e->getMessage(), 'error');
        
        // Return error response
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Function to display product edit form
function displayEditForm($sku) {
    global $db;
    
    // Get product details
    $productQuery = "SELECT * FROM products WHERE SKU = ?";
    $product = $db->fetchOneWithParams($productQuery, 's', [$sku]);
    
    if (!$product) {
        setFlashMessage('Product not found.', 'danger');
        redirect(SITE_URL . 'admin/products.php');
        exit;
    }
    
    // Get product categories
    $categoriesQuery = "SELECT c.id, c.name, pc.product_sku IS NOT NULL as selected 
                        FROM categories c 
                        LEFT JOIN product_categories pc ON c.id = pc.category_id AND pc.product_sku = ? 
                        ORDER BY c.name";
    $categories = $db->fetchAllWithParams($categoriesQuery, 's', [$sku]);
    
    // Get product images
    $imagesQuery = "SELECT * FROM product_images WHERE product_sku = ?";
    $images = $db->fetchAllWithParams($imagesQuery, 's', [$sku]);
    
    // Get sizes for dropdown
    $sizesQuery = "SELECT name FROM sizes ORDER BY name";
    $sizes = $db->fetchAll($sizesQuery);
    
    // Get colors for dropdown
    $colorsQuery = "SELECT name FROM colors ORDER BY name";
    $colors = $db->fetchAll($colorsQuery);
    
    // Get form data from session if available (in case of validation errors)
    $formData = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : $product;
    $errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
    
    // Clear session data
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);
    ?>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Product</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="<?= SITE_URL ?>admin/products.php" class="btn btn-sm btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
            <a href="<?= SITE_URL ?>admin/products.php?action=view&sku=<?= htmlspecialchars($sku) ?>" class="btn btn-sm btn-info">
                <i class="fas fa-eye"></i> View Details
            </a>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <?php displayFlashMessages(); ?>
    
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Product Form -->
    <div class="card">
        <div class="card-body">
            <form action="<?= SITE_URL ?>admin/products.php?action=edit&sku=<?= htmlspecialchars($sku) ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="row">
                    <!-- Basic Product Information -->
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Product Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="sku" class="form-label">SKU</label>
                                    <input type="text" class="form-control" id="sku" value="<?= htmlspecialchars($sku) ?>" readonly disabled>
                                    <input type="hidden" name="sku" value="<?= htmlspecialchars($sku) ?>">
                                    <div class="form-text">SKU cannot be changed</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= isset($formData['name']) ? htmlspecialchars($formData['name']) : '' ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="5"><?= isset($formData['description']) ? htmlspecialchars($formData['description']) : '' ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="price" name="price" min="0.01" step="0.01" value="<?= isset($formData['price']) ? htmlspecialchars($formData['price']) : '' ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?= isset($formData['stock']) ? htmlspecialchars($formData['stock']) : '0' ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Categories -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Categories</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Select Categories</label>
                                    <div class="row">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="col-md-4">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="categories[]" value="<?= $category['id'] ?>" id="category_<?= $category['id'] ?>" 
                                                        <?= isset($formData['categories']) ? (in_array($category['id'], $formData['categories']) ? 'checked' : '') : ($category['selected'] ? 'checked' : '') ?>>
                                                    <label class="form-check-label" for="category_<?= $category['id'] ?>">
                                                        <?= htmlspecialchars($category['name']) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Image and Additional Info -->
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Product Images</h5>
                            </div>
                            <div class="card-body">
                                <!-- Current Images -->
                                <?php if (!empty($images)): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Current Images</label>
                                        <div class="row">
                                            <?php foreach ($images as $image): ?>
                                                <div class="col-6 mb-3">
                                                    <div class="card">
                                                        <img src="<?= SITE_URL . htmlspecialchars($image['image_path']) ?>" class="card-img-top" alt="Product image">
                                                        <div class="card-body p-2 text-center">
                                                            <div class="form-check d-inline-block">
                                                                <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?= $image['id'] ?>" id="delete_image_<?= $image['id'] ?>">
                                                                <label class="form-check-label" for="delete_image_<?= $image['id'] ?>">
                                                                    Delete
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="product_image" class="form-label">Upload New Image</label>
                                    <input type="file" class="form-control image-upload" id="product_image" name="product_image" data-preview="image-preview" accept="image/*">
                                    <div class="form-text">Recommended size: 800x800 pixels</div>
                                </div>
                                <div class="text-center mt-3">
                                    <img id="image-preview" class="image-preview" style="display: none; max-width: 100%;" alt="Product image preview">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="reset" class="btn btn-secondary">Reset</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Function to display product details view
function displayProductDetails($sku) {
    global $db;
    
    $sku = isset($_GET['sku']) ? $_GET['sku'] : '';
    
    if (empty($sku)) {
        setFlashMessage('Product SKU is required.', 'error');
        redirect(SITE_URL . 'admin/products.php');
        return;
    }
    
    // Get product details
    $query = "SELECT * FROM products WHERE SKU = ?";
    $product = $db->fetchOneWithParams($query, 's', [$sku]);
    
    if (!$product) {
        setFlashMessage('Product not found.', 'error');
        redirect(SITE_URL . 'admin/products.php');
        return;
    }
    
    // Get product categories
    $categoriesQuery = "SELECT c.id, c.name 
                      FROM product_categories pc 
                      JOIN categories c ON pc.category_id = c.id 
                      WHERE pc.product_sku = ?";
    $categories = $db->fetchAllWithParams($categoriesQuery, 's', [$sku]);
    
    // Get product images
    $imagesQuery = "SELECT * FROM product_images WHERE product_sku = ? ORDER BY id";
    $images = $db->fetchAllWithParams($imagesQuery, 's', [$sku]);
    
    // Get product sizes
    $sizesQuery = "SELECT ps.size_name, ps.stock, s.name
                  FROM product_sizes ps
                  JOIN sizes s ON ps.size_name = s.name
                  WHERE ps.product_sku = ?";
    $sizes = $db->fetchAllWithParams($sizesQuery, 's', [$sku]);
    
    // Get product colors
    $colorsQuery = "SELECT pc.color_name, pc.stock, c.name
                   FROM product_colors pc
                   JOIN colors c ON pc.color_name = c.name
                   WHERE pc.product_sku = ?";
    $colors = $db->fetchAllWithParams($colorsQuery, 's', [$sku]);
    
    // Log the activity
    logActivity("Admin viewed product details for SKU: $sku", 'info');
    ?>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Product Details</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="<?= SITE_URL ?>admin/products.php" class="btn btn-sm btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <a href="<?= SITE_URL ?>admin/products.php?action=edit&sku=<?= $sku ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-edit"></i> Edit Product
            </a>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <?php displayFlashMessages(); ?>
    
    <div class="row">
        <!-- Product Images -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Product Images</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($images)): ?>
                        <div class="alert alert-info">No images available</div>
                    <?php else: ?>
                        <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($images as $index => $image): ?>
                                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                        <img src="<?= SITE_URL . $image['image_path'] ?>" class="d-block w-100" alt="<?= htmlspecialchars($product['name']) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($images) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Thumbnails -->
                        <div class="row mt-3">
                            <?php foreach ($images as $index => $image): ?>
                                <div class="col-3 mb-2">
                                    <img src="<?= SITE_URL . $image['image_path'] ?>" class="img-thumbnail" alt="Thumbnail" 
                                         data-bs-target="#productCarousel" data-bs-slide-to="<?= $index ?>" 
                                         style="cursor: pointer; height: 60px; object-fit: cover;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Product Details -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Product Information</h5>
                </div>
                <div class="card-body">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="text-muted">SKU: <?= $product['SKU'] ?></p>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Price</h5>
                            <p class="fs-4 text-primary">$<?= number_format($product['price'], 2) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Stock</h5>
                            <?php if ($product['stock'] <= 0): ?>
                                <p class="text-danger">Out of stock</p>
                            <?php elseif ($product['stock'] < 10): ?>
                                <p class="text-warning">Low stock (<?= $product['stock'] ?> remaining)</p>
                            <?php else: ?>
                                <p class="text-success">In stock (<?= $product['stock'] ?> available)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h5>Description</h5>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    
                    <h5>Categories</h5>
                    <?php if (empty($categories)): ?>
                        <p class="text-muted">No categories assigned</p>
                    <?php else: ?>
                        <div class="mb-3">
                            <?php foreach ($categories as $category): ?>
                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($category['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- Sizes -->
                        <div class="col-md-6">
                            <h5>Sizes</h5>
                            <?php if (empty($sizes)): ?>
                                <p class="text-muted">No sizes available</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($sizes as $size): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($size['name']) ?>
                                            <?php if ($size['stock'] <= 0): ?>
                                                <span class="badge bg-danger rounded-pill">Out of stock</span>
                                            <?php elseif ($size['stock'] < 10): ?>
                                                <span class="badge bg-warning rounded-pill"><?= $size['stock'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success rounded-pill"><?= $size['stock'] ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Colors -->
                        <div class="col-md-6">
                            <h5>Colors</h5>
                            <?php if (empty($colors)): ?>
                                <p class="text-muted">No colors available</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($colors as $color): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($color['name']) ?>
                                            <?php if ($color['stock'] <= 0): ?>
                                                <span class="badge bg-danger rounded-pill">Out of stock</span>
                                            <?php elseif ($color['stock'] < 10): ?>
                                                <span class="badge bg-warning rounded-pill"><?= $color['stock'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success rounded-pill"><?= $color['stock'] ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// More functions will be added in the next file
?>
