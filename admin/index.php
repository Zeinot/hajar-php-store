<?php
/**
 * Admin Dashboard for Elegant Drapes
 * Displays key metrics, recent orders, and other important information
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/auth.php';

// Ensure admin is logged in
require_admin_login();

// Set page title
$page_title = 'Dashboard';

// Get statistics
$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'total_sales' => 0,
    'average_order' => 0,
    'total_customers' => 0,
    'total_products' => 0,
    'low_stock_products' => 0
];

// Get recent orders
$recent_orders = [];

try {
    $conn = getDbConnection();
    
    // Total orders
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $stats['total_orders'] = $result->fetch_assoc()['count'];
    
    // Pending orders
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = $result->fetch_assoc()['count'];
    
    // Total sales
    $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
    $row = $result->fetch_assoc();
    $stats['total_sales'] = $row['total'] ?? 0;
    
    // Average order value
    if ($stats['total_orders'] > 0) {
        $stats['average_order'] = $stats['total_sales'] / $stats['total_orders'];
    }
    
    // Total customers
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_customers'] = $result->fetch_assoc()['count'];
    
    // Total products
    $result = $conn->query("SELECT COUNT(*) as count FROM products");
    $stats['total_products'] = $result->fetch_assoc()['count'];
    
    // Low stock products
    $result = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity < 10");
    $stats['low_stock_products'] = $result->fetch_assoc()['count'];
    
    // Recent orders
    $stmt = $conn->prepare("
        SELECT o.*, u.full_name, u.email,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($order = $result->fetch_assoc()) {
        $recent_orders[] = $order;
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    log_message('Error fetching dashboard data: ' . $e->getMessage(), 'error');
}

// Include header
include 'includes/templates/header.php';
?>

<!-- Dashboard Content -->
<div class="row g-4 mb-4">
    <!-- Total Orders -->
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-bag text-primary fs-3"></i>
                        </div>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Total Orders</h5>
                        <h2 class="mt-2 mb-0"><?php echo number_format($stats['total_orders']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <small class="text-muted">
                    <a href="orders.php" class="text-decoration-none">View all orders <i class="bi bi-arrow-right"></i></a>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Pending Orders -->
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-hourglass-split text-warning fs-3"></i>
                        </div>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Pending Orders</h5>
                        <h2 class="mt-2 mb-0"><?php echo number_format($stats['pending_orders']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <small class="text-muted">
                    <a href="orders.php?status=pending" class="text-decoration-none">View pending orders <i class="bi bi-arrow-right"></i></a>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Total Sales -->
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-currency-dollar text-success fs-3"></i>
                        </div>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Total Sales</h5>
                        <h2 class="mt-2 mb-0">$<?php echo number_format($stats['total_sales'], 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <small class="text-muted">
                    <a href="reports.php" class="text-decoration-none">View sales reports <i class="bi bi-arrow-right"></i></a>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Customers -->
    <div class="col-xl-3 col-md-6">
        <div class="card dashboard-card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="bi bi-people text-info fs-3"></i>
                        </div>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Customers</h5>
                        <h2 class="mt-2 mb-0"><?php echo number_format($stats['total_customers']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <small class="text-muted">
                    <a href="customers.php" class="text-decoration-none">View all customers <i class="bi bi-arrow-right"></i></a>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders and Quick Stats -->
<div class="row g-4">
    <!-- Recent Orders -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Orders</h5>
                <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_orders)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="mt-3 mb-0">No orders found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($order['full_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                        </td>
                                        <td><?php echo $order['item_count']; ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-muted">Average Order Value</div>
                        <div class="fw-bold">$<?php echo number_format($stats['average_order'], 2); ?></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-muted">Total Products</div>
                        <div class="fw-bold"><?php echo number_format($stats['total_products']); ?></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">Low Stock Products</div>
                        <div class="fw-bold">
                            <?php echo number_format($stats['low_stock_products']); ?>
                            <?php if ($stats['low_stock_products'] > 0): ?>
                                <a href="products.php?filter=low_stock" class="btn btn-sm btn-warning ms-2">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <!-- Orders by Status -->
                <h6 class="card-subtitle mb-3">Orders by Status</h6>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-status-pending me-2">Pending</span>
                            <div>Pending</div>
                        </div>
                        <div class="fw-bold"><?php echo $stats['pending_orders']; ?></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-status-processing me-2">Processing</span>
                            <div>Processing</div>
                        </div>
                        <div class="fw-bold">
                            <?php 
                                // Get processing orders count
                                $processing_count = 0;
                                foreach ($recent_orders as $order) {
                                    if ($order['status'] === 'processing') {
                                        $processing_count++;
                                    }
                                }
                                echo $processing_count;
                            ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-status-shipped me-2">Shipped</span>
                            <div>Shipped</div>
                        </div>
                        <div class="fw-bold">
                            <?php 
                                // Get shipped orders count
                                $shipped_count = 0;
                                foreach ($recent_orders as $order) {
                                    if ($order['status'] === 'shipped') {
                                        $shipped_count++;
                                    }
                                }
                                echo $shipped_count;
                            ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-status-delivered me-2">Delivered</span>
                            <div>Delivered</div>
                        </div>
                        <div class="fw-bold">
                            <?php 
                                // Get delivered orders count
                                $delivered_count = 0;
                                foreach ($recent_orders as $order) {
                                    if ($order['status'] === 'delivered') {
                                        $delivered_count++;
                                    }
                                }
                                echo $delivered_count;
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="reports.php" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up me-1"></i> View Detailed Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/templates/footer.php'; ?>
