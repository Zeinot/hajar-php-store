<?php
/**
 * Order Details page for Elegant Drapes luxury clothing store
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if user is not logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'order-details.php?id=' . ($_GET['id'] ?? '');
    $_SESSION['flash_message'] = 'Please log in to view your order details';
    $_SESSION['flash_type'] = 'warning';
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];

// Validate order ID
if ($order_id <= 0) {
    redirect('profile.php?tab=orders');
}

// Get order details
try {
    $conn = getDbConnection();
    
    // Check if order belongs to the logged-in user (security check)
    $stmt = $conn->prepare("
        SELECT o.*, u.full_name, u.email, u.phone 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        // Order not found or doesn't belong to user
        $_SESSION['flash_message'] = 'Order not found';
        $_SESSION['flash_type'] = 'danger';
        redirect('profile.php?tab=orders');
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    $items = [];
    $stmt = $conn->prepare("
        SELECT oi.*, p.name as product_name, p.sku,
        (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image
        FROM order_items oi
        JOIN products p ON oi.product_sku = p.sku
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $errors['system'] = 'System error occurred. Please try again later.';
    logError('Order details fetch error: ' . $e->getMessage());
}

// Set page title
$page_title = 'Order #' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . ' | Elegant Drapes';

// Include header
include 'includes/header.php';
?>

<div class="container my-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="profile.php?tab=orders" class="text-decoration-none">My Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></li>
        </ol>
    </nav>

    <?php if (!empty($errors['system'])): ?>
        <div class="alert alert-danger"><?php echo $errors['system']; ?></div>
    <?php endif; ?>
    
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="h4 mb-0">Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></h1>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="badge bg-<?php 
                        echo match($order['status']) {
                            'pending' => 'warning',
                            'confirmed' => 'info',
                            'shipped' => 'primary',
                            'delivered' => 'success',
                            'canceled' => 'danger',
                            'refunded' => 'secondary',
                            default => 'secondary'
                        };
                    ?> fs-6">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                    
                    <?php if ($order['status'] === 'shipped' && !empty($order['tracking_number'])): ?>
                        <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#trackingModal">
                            Track Order
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0">
                    <h5>Order Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="ps-0 text-muted">Order Date:</td>
                            <td><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-0 text-muted">Order Status:</td>
                            <td><?php echo ucfirst($order['status']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-0 text-muted">Shipping Method:</td>
                            <td><?php echo ucfirst($order['shipping_method']); ?></td>
                        </tr>
                        <?php if (!empty($order['tracking_number'])): ?>
                        <tr>
                            <td class="ps-0 text-muted">Tracking Number:</td>
                            <td><?php echo htmlspecialchars($order['tracking_number']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5>Customer Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="ps-0 text-muted">Name:</td>
                            <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="ps-0 text-muted">Email:</td>
                            <td><?php echo htmlspecialchars($order['email']); ?></td>
                        </tr>
                        <?php if (!empty($order['phone'])): ?>
                        <tr>
                            <td class="ps-0 text-muted">Phone:</td>
                            <td><?php echo htmlspecialchars($order['phone']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="ps-0 text-muted">Shipping Address:</td>
                            <td><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <hr class="my-4">
            
            <h5>Order Items</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Image</th>
                            <th>Product</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th class="text-end">Price</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo !empty($item['image']) ? 'uploads/products/' . $item['image'] : 'assets/img/product-placeholder.jpg'; ?>" 
                                         class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                </td>
                                <td>
                                    <a href="product.php?sku=<?php echo $item['sku']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo !empty($item['size_name']) ? htmlspecialchars($item['size_name']) : 'N/A'; ?></td>
                                <td><?php echo !empty($item['color_name']) ? htmlspecialchars($item['color_name']) : 'N/A'; ?></td>
                                <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end fw-bold">$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-group-divider">
                        <tr>
                            <td colspan="6" class="text-end fw-bold">Order Total:</td>
                            <td class="text-end fw-bold fs-5">$<?php echo number_format($order['total'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                <div class="alert alert-info mt-4">
                    <h6 class="alert-heading fw-bold">Order Processing</h6>
                    <p class="mb-0">Your order is being processed. You will receive an email notification when your order has been shipped.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <a href="profile.php?tab=orders" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Orders
                </a>
                <a href="#" class="btn btn-outline-primary" onclick="window.print(); return false;">
                    <i class="bi bi-printer me-1"></i> Print Order
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($order['status'] === 'shipped' && !empty($order['tracking_number'])): ?>
<!-- Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Track Your Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <h6>Order Information</h6>
                    <p class="mb-1"><strong>Order #:</strong> <?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
                    <p class="mb-1"><strong>Shipping Method:</strong> <?php echo ucfirst($order['shipping_method']); ?></p>
                    <p class="mb-1"><strong>Tracking Number:</strong> <?php echo $order['tracking_number']; ?></p>
                </div>
                
                <div class="mb-3">
                    <h6>Shipment Status</h6>
                    <div class="shipping-timeline">
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="timeline-icon bg-success rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-check text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0 fw-bold">Order Processed</p>
                                <p class="text-muted small"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="timeline-icon bg-success rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-check text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0 fw-bold">Order Shipped</p>
                                <p class="text-muted small"><?php echo date('M d, Y', strtotime($order['updated_at'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="timeline-icon bg-primary rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-truck text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0 fw-bold">In Transit</p>
                                <p class="text-muted small">Your package is on its way</p>
                            </div>
                        </div>
                        
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="timeline-icon bg-secondary rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-house text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0 fw-bold">Delivered</p>
                                <p class="text-muted small">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mb-0">
                    <p class="mb-0">For real-time tracking updates, please visit the carrier's website and enter your tracking number.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.timeline-icon {
    width: 30px;
    height: 30px;
}
@media print {
    .navbar, .footer, .breadcrumb, .card-footer, .alert {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
