<?php
/**
 * Admin Dashboard
 * Main entry point for the admin panel
 */
$pageTitle = "Admin Dashboard";
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    // Store intended URL
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    
    // Set error message
    setFlashMessage('You must be logged in as an admin to access this page.', 'error');
    
    // Redirect to login page
    redirect(SITE_URL . 'login.php');
    exit;
}

// Get stats for dashboard
$db = Database::getInstance();

// Get total products
$productCountQuery = "SELECT COUNT(*) as count FROM products";
$productCount = $db->fetchOne($productCountQuery)['count'] ?? 0;

// Get total users
$userCountQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
$userCount = $db->fetchOne($userCountQuery)['count'] ?? 0;

// Get total orders
$orderCountQuery = "SELECT COUNT(*) as count FROM orders";
$orderCount = $db->fetchOne($orderCountQuery)['count'] ?? 0;

// Get recent orders (limit to 5)
$recentOrdersQuery = "SELECT o.id, o.date, o.total, o.status, u.full_name as customer_name 
                      FROM orders o 
                      JOIN users u ON o.user_id = u.id 
                      ORDER BY o.date DESC 
                      LIMIT 5";
$recentOrders = $db->fetchAll($recentOrdersQuery);

// Get low stock products (limit to 5)
$lowStockQuery = "SELECT SKU, name, stock 
                  FROM products 
                  WHERE stock < 10 
                  ORDER BY stock ASC 
                  LIMIT 5";
$lowStockProducts = $db->fetchAll($lowStockQuery);

// Include admin header
include '../admin/partials/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../admin/partials/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="<?= SITE_URL ?>admin/products.php?action=create" class="btn btn-sm btn-outline-primary">Add Product</a>
                        <a href="<?= SITE_URL ?>admin/categories.php?action=create" class="btn btn-sm btn-outline-primary">Add Category</a>
                    </div>
                </div>
            </div>
            
            <!-- Flash Messages -->
            <?php displayFlashMessages(); ?>
            
            <!-- Stats cards -->
            <div class="row">
                <div class="col-12 col-sm-6 col-md-4 mb-4">
                    <div class="card text-white bg-primary h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Products</h6>
                                    <h2 class="display-4"><?= $productCount ?></h2>
                                </div>
                                <i class="fas fa-box fa-2x"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex">
                            <a href="<?= SITE_URL ?>admin/products.php" class="text-white text-decoration-none w-100">
                                View Details <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-md-4 mb-4">
                    <div class="card text-white bg-success h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Customers</h6>
                                    <h2 class="display-4"><?= $userCount ?></h2>
                                </div>
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex">
                            <a href="<?= SITE_URL ?>admin/users.php" class="text-white text-decoration-none w-100">
                                View Details <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-md-4 mb-4">
                    <div class="card text-white bg-warning h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Orders</h6>
                                    <h2 class="display-4"><?= $orderCount ?></h2>
                                </div>
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex">
                            <a href="<?= SITE_URL ?>admin/orders.php" class="text-white text-decoration-none w-100">
                                View Details <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent orders and Low stock -->
            <div class="row">
                <!-- Recent Orders -->
                <div class="col-12 col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Orders</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentOrders)): ?>
                                <div class="alert alert-info m-3">No recent orders found.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Customer</th>
                                                <th>Date</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentOrders as $order): ?>
                                                <tr>
                                                    <td><a href="<?= SITE_URL ?>admin/orders.php?action=view&id=<?= $order['id'] ?>">#<?= $order['id'] ?></a></td>
                                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                    <td><?= date('M d, Y', strtotime($order['date'])) ?></td>
                                                    <td>$<?= number_format($order['total'], 2) ?></td>
                                                    <td>
                                                        <?php
                                                        $statusClass = '';
                                                        switch ($order['status']) {
                                                            case 'confirmed':
                                                                $statusClass = 'bg-success';
                                                                break;
                                                            case 'pending':
                                                                $statusClass = 'bg-warning';
                                                                break;
                                                            case 'canceled':
                                                                $statusClass = 'bg-danger';
                                                                break;
                                                            case 'refunded':
                                                                $statusClass = 'bg-info';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($order['status']) ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-end">
                            <a href="<?= SITE_URL ?>admin/orders.php" class="btn btn-sm btn-primary">
                                View All Orders
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Products -->
                <div class="col-12 col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Low Stock Products</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($lowStockProducts)): ?>
                                <div class="alert alert-success m-3">No products with low stock.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>SKU</th>
                                                <th>Product</th>
                                                <th>Stock</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lowStockProducts as $product): ?>
                                                <tr>
                                                    <td><?= $product['SKU'] ?></td>
                                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                                    <td>
                                                        <?php if ($product['stock'] == 0): ?>
                                                            <span class="badge bg-danger">Out of stock</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning"><?= $product['stock'] ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="<?= SITE_URL ?>admin/products.php?action=edit&sku=<?= $product['SKU'] ?>" class="btn btn-sm btn-primary">
                                                            Edit
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-end">
                            <a href="<?= SITE_URL ?>admin/products.php" class="btn btn-sm btn-primary">
                                View All Products
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../admin/partials/footer.php'; ?>
