<?php
/**
 * Order Details Page for Elegant Drapes luxury clothing store
 * Displays details of a specific order, including status, tracking, and items
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    // Save current URL to redirect back after login
    $_SESSION['redirect_after_login'] = 'profile.php?tab=orders';
    redirect('login.php');
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    redirect('profile.php?tab=orders');
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$order = null;
$order_items = [];
$order_status_history = [];
$shipping_statuses = [
    'pending' => [
        'icon' => 'bi-clock-history',
        'color' => 'warning',
        'label' => 'Order Pending',
        'description' => 'Your order has been received and is awaiting processing.'
    ],
    'processing' => [
        'icon' => 'bi-gear',
        'color' => 'primary',
        'label' => 'Processing',
        'description' => 'Your order is being processed and prepared for shipping.'
    ],
    'shipped' => [
        'icon' => 'bi-truck',
        'color' => 'info',
        'label' => 'Shipped',
        'description' => 'Your order has been shipped and is on its way to you.'
    ],
    'delivered' => [
        'icon' => 'bi-check-circle',
        'color' => 'success',
        'label' => 'Delivered',
        'description' => 'Your order has been delivered successfully.'
    ],
    'cancelled' => [
        'icon' => 'bi-x-circle',
        'color' => 'danger',
        'label' => 'Cancelled',
        'description' => 'Your order has been cancelled.'
    ],
    'refunded' => [
        'icon' => 'bi-arrow-counterclockwise',
        'color' => 'secondary',
        'label' => 'Refunded',
        'description' => 'Your order has been refunded.'
    ]
];

try {
    $conn = getDbConnection();
    
    // Fetch order details
    $stmt = $conn->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Order not found or doesn't belong to this user
        redirect('profile.php?tab=orders');
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Format dates
    $order['order_date_formatted'] = date('F j, Y g:i A', strtotime($order['order_date']));
    
    // Fetch order items
    $stmt = $conn->prepare("
        SELECT oi.*, p.name, p.sku,
        (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image
        FROM order_items oi
        JOIN products p ON oi.product_sku = p.sku
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $order_items[] = $item;
    }
    
    $stmt->close();
    
    // Fetch order status history
    $stmt = $conn->prepare("
        SELECT * FROM order_status_history
        WHERE order_id = ?
        ORDER BY date_created DESC
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $history_result = $stmt->get_result();
    
    while ($history = $history_result->fetch_assoc()) {
        $history['date_formatted'] = date('F j, Y g:i A', strtotime($history['date_created']));
        $order_status_history[] = $history;
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    log_message('Error retrieving order: ' . $e->getMessage(), 'error');
    redirect('profile.php?tab=orders');
}

// Set page title
$page_title = 'Order #' . sprintf('%06d', $order_id) . ' | Elegant Drapes';

// Include header
include 'includes/header.php';
?>

<div class="container my-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="profile.php?tab=orders" class="text-decoration-none">My Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">Order #<?php echo sprintf('%06d', $order_id); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order #<?php echo sprintf('%06d', $order_id); ?></h5>
                    <span class="badge bg-<?php echo $shipping_statuses[$order['status']]['color']; ?> px-3 py-2">
                        <i class="bi <?php echo $shipping_statuses[$order['status']]['icon']; ?> me-1"></i>
                        <?php echo $shipping_statuses[$order['status']]['label']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="alert alert-<?php echo $shipping_statuses[$order['status']]['color']; ?> d-flex align-items-center mb-4">
                        <i class="bi <?php echo $shipping_statuses[$order['status']]['icon']; ?> fs-4 me-3"></i>
                        <div>
                            <strong><?php echo $shipping_statuses[$order['status']]['label']; ?>:</strong> 
                            <?php echo $shipping_statuses[$order['status']]['description']; ?>
                        </div>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Order Information</h6>
                            <p class="mb-1"><strong>Order Date:</strong> <?php echo $order['order_date_formatted']; ?></p>
                            <p class="mb-1"><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                            <?php if (!empty($order['tracking_number'])): ?>
                                <p class="mb-1"><strong>Tracking Number:</strong> <?php echo htmlspecialchars($order['tracking_number']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Customer Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['full_name']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                            <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Shipping Address</h6>
                            <address class="mb-0">
                                <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                                <?php echo htmlspecialchars($order['shipping_city']); ?>, 
                                <?php echo htmlspecialchars($order['shipping_state']); ?> 
                                <?php echo htmlspecialchars($order['shipping_zip']); ?><br>
                                <?php echo htmlspecialchars($order['shipping_country']); ?><br>
                                Phone: <?php echo htmlspecialchars($order['shipping_phone']); ?>
                            </address>
                        </div>
                        
                        <?php if (!empty($order['customer_notes'])): ?>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Order Notes</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($order['status'] === 'shipped' && !empty($order['tracking_number'])): ?>
                        <div class="mt-4 text-center">
                            <a href="https://track-package.com/<?php echo htmlspecialchars($order['tracking_number']); ?>" 
                               target="_blank" class="btn btn-outline-primary">
                                <i class="bi bi-box-seam me-1"></i> Track Your Package
                            </a>
                            <p class="small text-muted mt-2">
                                Note: Tracking information may take 24-48 hours to become available after shipment.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Status Timeline -->
            <?php if (!empty($order_status_history)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($order_status_history as $index => $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-item-marker">
                                        <div class="timeline-item-marker-indicator bg-<?php echo $shipping_statuses[$history['status']]['color']; ?>">
                                            <i class="bi <?php echo $shipping_statuses[$history['status']]['icon']; ?> text-white"></i>
                                        </div>
                                    </div>
                                    <div class="timeline-item-content pt-0">
                                        <div class="timeline-item-title">
                                            <?php echo $shipping_statuses[$history['status']]['label']; ?>
                                        </div>
                                        <div class="timeline-item-subtitle"><?php echo $history['date_formatted']; ?></div>
                                        <div class="timeline-item-description"><?php echo htmlspecialchars($history['notes']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Add order placed as first item if no history exists -->
                            <?php if (count($order_status_history) === 0): ?>
                                <div class="timeline-item">
                                    <div class="timeline-item-marker">
                                        <div class="timeline-item-marker-indicator bg-success">
                                            <i class="bi bi-check-circle text-white"></i>
                                        </div>
                                    </div>
                                    <div class="timeline-item-content pt-0">
                                        <div class="timeline-item-title">Order Placed</div>
                                        <div class="timeline-item-subtitle"><?php echo $order['order_date_formatted']; ?></div>
                                        <div class="timeline-item-description">Your order has been successfully placed and is awaiting processing.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Order Items -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center" width="80">Item</th>
                                    <th>Product</th>
                                    <th class="text-center">Price</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td class="text-center">
                                            <img src="<?php echo !empty($item['image']) ? htmlspecialchars($item['image']) : 'assets/images/product-placeholder.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="img-fluid rounded" style="max-width: 60px;">
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
                                        <td class="text-center">$<?php echo number_format($item['price'], 2); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Order Summary -->
            <div class="card shadow-sm mb-4 sticky-md-top" style="top: 20px; z-index: 1;">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($order['total_amount'] - $order['shipping_amount'] - $order['tax_amount'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping</span>
                        <span><?php echo $order['shipping_amount'] > 0 ? '$' . number_format($order['shipping_amount'], 2) : 'Free'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Tax</span>
                        <span>$<?php echo number_format($order['tax_amount'], 2); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-0">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold fs-5">$<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-grid gap-2">
                        <a href="profile.php?tab=orders" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to My Orders
                        </a>
                        <?php if (in_array($order['status'], ['delivered', 'cancelled', 'refunded'])): ?>
                            <a href="shop.php" class="btn btn-primary">
                                <i class="bi bi-bag me-1"></i> Shop Again
                            </a>
                        <?php else: ?>
                            <a href="contact.php?subject=Order%20%23<?php echo $order_id; ?>" class="btn btn-primary">
                                <i class="bi bi-envelope me-1"></i> Contact Support
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Need Help? -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Need Help?</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">If you have any questions or concerns about your order, our customer support team is here to help.</p>
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-envelope text-primary me-2"></i>
                            <a href="mailto:support@elegantdrapes.com" class="text-decoration-none">support@elegantdrapes.com</a>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-telephone text-primary me-2"></i>
                            <a href="tel:+1-800-123-4567" class="text-decoration-none">+1-800-123-4567</a>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-chat-dots text-primary me-2"></i>
                            <a href="#" class="text-decoration-none">Live Chat (9 AM - 6 PM EST)</a>
                        </div>
                    </div>
                    <div class="alert alert-light small mb-0">
                        <strong>About Cash on Delivery:</strong> For Cash on Delivery orders, please ensure someone is available to receive the package and make the payment. The delivery person may request ID verification.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline styling */
.timeline {
    position: relative;
    padding-left: 1.5rem;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0.75rem;
    height: 100%;
    width: 1px;
    background-color: #e0e0e0;
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item-marker {
    position: absolute;
    left: -1.5rem;
    width: 1.5rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-item-marker-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 2rem;
    width: 2rem;
    border-radius: 100%;
    background-color: #fff;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.timeline-item-marker-indicator i {
    font-size: 1rem;
}

.timeline-item-content {
    padding-left: 0.75rem;
    padding-top: 0.25rem;
}

.timeline-item-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.timeline-item-subtitle {
    font-size: 0.875rem;
    font-weight: 400;
    margin-bottom: 0.5rem;
    color: #6c757d;
}

.timeline-item-description {
    font-size: 0.875rem;
}
</style>

<?php include 'includes/footer.php'; ?>
