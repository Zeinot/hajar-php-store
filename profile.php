<?php
/**
 * User Profile page for Elegant Drapes luxury clothing store
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if user is not logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'profile.php';
    $_SESSION['flash_message'] = 'Please log in to view your profile';
    $_SESSION['flash_type'] = 'warning';
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'profile';
$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Update profile information
    if ($action === 'update_profile') {
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $shipping_address = sanitizeInput($_POST['shipping_address'] ?? '');
        $email_subscription = isset($_POST['email_subscription']) ? 1 : 0;
        
        // Validate inputs
        if (empty($full_name)) {
            $errors['full_name'] = 'Full name is required';
        }
        
        if (empty($errors)) {
            try {
                $conn = getDbConnection();
                
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, shipping_address = ?, email_subscription = ? WHERE id = ?");
                $stmt->bind_param("sssii", $full_name, $phone, $shipping_address, $email_subscription, $user_id);
                
                if ($stmt->execute()) {
                    // Update session variable
                    $_SESSION['user_name'] = $full_name;
                    $success = 'Profile updated successfully';
                    logActivity('profile_update', "User ID: {$user_id} updated profile");
                } else {
                    $errors['system'] = 'Failed to update profile. Please try again.';
                    logError('Profile update failed: ' . $stmt->error);
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $errors['system'] = 'System error occurred. Please try again later.';
                logError('Profile update error: ' . $e->getMessage());
            }
        }
        $active_tab = 'profile';
    }
    // Change password
    else if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password is required';
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = 'New password is required';
        } elseif (strlen($new_password) < 8) {
            $errors['new_password'] = 'Password must be at least 8 characters long';
        }
        
        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        
        if (empty($errors)) {
            try {
                $conn = getDbConnection();
                
                // Get current password hash
                $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    
                    // Verify current password
                    if (password_verify($current_password, $user['password_hash'])) {
                        // Hash new password
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Update password
                        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $update_stmt->bind_param("si", $new_password_hash, $user_id);
                        
                        if ($update_stmt->execute()) {
                            $success = 'Password changed successfully';
                            logActivity('password_change', "User ID: {$user_id} changed password");
                        } else {
                            $errors['system'] = 'Failed to change password. Please try again.';
                            logError('Password change failed: ' . $update_stmt->error);
                        }
                        
                        $update_stmt->close();
                    } else {
                        $errors['current_password'] = 'Current password is incorrect';
                    }
                } else {
                    $errors['system'] = 'User not found';
                    logError('Password change - User not found: ' . $user_id);
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $errors['system'] = 'System error occurred. Please try again later.';
                logError('Password change error: ' . $e->getMessage());
            }
        }
        $active_tab = 'security';
    }
    // Remove item from wishlist
    else if ($action === 'remove_wishlist') {
        $product_sku = sanitizeInput($_POST['product_sku'] ?? '');
        
        if (!empty($product_sku)) {
            try {
                $conn = getDbConnection();
                
                $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_sku = ?");
                $stmt->bind_param("is", $user_id, $product_sku);
                
                if ($stmt->execute()) {
                    $success = 'Item removed from wishlist';
                } else {
                    $errors['system'] = 'Failed to remove item from wishlist';
                    logError('Wishlist remove failed: ' . $stmt->error);
                }
                
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $errors['system'] = 'System error occurred. Please try again later.';
                logError('Wishlist remove error: ' . $e->getMessage());
            }
        }
        $active_tab = 'wishlist';
    }
}

// Get user information
try {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT full_name, email, phone, shipping_address, email_subscription, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        $errors['system'] = 'User not found';
        logError('User profile fetch - User not found: ' . $user_id);
    }
    
    $stmt->close();
} catch (Exception $e) {
    $errors['system'] = 'System error occurred. Please try again later.';
    logError('User profile fetch error: ' . $e->getMessage());
}

// Get user orders
$orders = [];
try {
    $stmt = $conn->prepare("
        SELECT o.id, o.total, o.status, o.order_date, o.shipping_method, o.tracking_number,
        COUNT(oi.id) as item_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.order_date DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();
    
    while ($order = $orders_result->fetch_assoc()) {
        $orders[] = $order;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $errors['system'] = 'Failed to load order history';
    logError('Order history fetch error: ' . $e->getMessage());
}

// Get user wishlist
$wishlist = [];
try {
    $stmt = $conn->prepare("
        SELECT w.product_sku, p.name, p.price, p.sale_price, 
        (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as image,
        c.name as category_name
        FROM wishlist w
        JOIN products p ON w.product_sku = p.sku
        JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ?
        ORDER BY w.added_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wishlist_result = $stmt->get_result();
    
    while ($item = $wishlist_result->fetch_assoc()) {
        $wishlist[] = $item;
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $errors['system'] = 'Failed to load wishlist';
    logError('Wishlist fetch error: ' . $e->getMessage());
}

// Set page title
$page_title = 'My Profile | Elegant Drapes';

// Include header
include 'includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4">My Account</h1>
    
    <?php if (!empty($errors['system'])): ?>
        <div class="alert alert-danger"><?php echo $errors['system']; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-placeholder bg-primary rounded-circle text-white d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 20px;">
                            <?php echo strtoupper(substr($user['full_name'] ?? 'User', 0, 1)); ?>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></small>
                        </div>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <a href="?tab=profile" class="list-group-item list-group-item-action <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                            <i class="bi bi-person me-2"></i> Profile Information
                        </a>
                        <a href="?tab=orders" class="list-group-item list-group-item-action <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">
                            <i class="bi bi-bag me-2"></i> Order History
                        </a>
                        <a href="?tab=wishlist" class="list-group-item list-group-item-action <?php echo $active_tab === 'wishlist' ? 'active' : ''; ?>">
                            <i class="bi bi-heart me-2"></i> Wishlist
                        </a>
                        <a href="?tab=security" class="list-group-item list-group-item-action <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                            <i class="bi bi-shield-lock me-2"></i> Security
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <?php if ($active_tab === 'profile'): ?>
                        <h3 class="card-title mb-4">Profile Information</h3>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                       id="fullName" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                                <div class="form-text">Email cannot be changed. Contact support if needed.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="shippingAddress" class="form-label">Shipping Address</label>
                                <textarea class="form-control" id="shippingAddress" name="shipping_address" 
                                          rows="3"><?php echo htmlspecialchars($user['shipping_address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="emailSubscription" name="email_subscription" 
                                       <?php echo ($user['email_subscription'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="emailSubscription">
                                    Subscribe to our newsletter for exclusive offers and updates
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    
                    <?php elseif ($active_tab === 'orders'): ?>
                        <h3 class="card-title mb-4">Order History</h3>
                        
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bag-x" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">No Orders Yet</h5>
                                <p class="text-muted">You haven't placed any orders yet.</p>
                                <a href="shop.php" class="btn btn-primary mt-2">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                                <td><?php echo $order['item_count']; ?> item(s)</td>
                                                <td>$<?php echo number_format($order['total'], 2); ?></td>
                                                <td>
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
                                                    ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
                                                    <?php if ($order['status'] === 'shipped' && !empty($order['tracking_number'])): ?>
                                                        <a href="#" class="btn btn-sm btn-outline-secondary mt-1" data-bs-toggle="modal" data-bs-target="#trackingModal<?php echo $order['id']; ?>">
                                                            Track Order
                                                        </a>
                                                        
                                                        <!-- Tracking Modal -->
                                                        <div class="modal fade" id="trackingModal<?php echo $order['id']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Tracking Information</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p><strong>Order #:</strong> <?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                                                        <p><strong>Shipping Method:</strong> <?php echo ucfirst($order['shipping_method']); ?></p>
                                                                        <p><strong>Tracking Number:</strong> <?php echo $order['tracking_number']; ?></p>
                                                                        <hr>
                                                                        <p>To track your package, please visit the carrier's website and enter your tracking number.</p>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    
                    <?php elseif ($active_tab === 'wishlist'): ?>
                        <h3 class="card-title mb-4">My Wishlist</h3>
                        
                        <?php if (empty($wishlist)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-heart" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">Your Wishlist is Empty</h5>
                                <p class="text-muted">Add items to your wishlist while shopping.</p>
                                <a href="shop.php" class="btn btn-primary mt-2">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-4">
                                <?php foreach ($wishlist as $item): ?>
                                    <div class="col">
                                        <div class="card h-100 product-card position-relative">
                                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=wishlist" class="position-absolute top-0 end-0 m-2">
                                                <input type="hidden" name="action" value="remove_wishlist">
                                                <input type="hidden" name="product_sku" value="<?php echo $item['product_sku']; ?>">
                                                <button type="submit" class="btn btn-sm btn-light rounded-circle" title="Remove from wishlist">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                            
                                            <a href="product.php?sku=<?php echo $item['product_sku']; ?>">
                                                <img src="<?php echo !empty($item['image']) ? 'uploads/products/' . $item['image'] : 'assets/img/product-placeholder.jpg'; ?>" 
                                                     class="card-img-top" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            </a>
                                            
                                            <div class="card-body">
                                                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                <h5 class="card-title">
                                                    <a href="product.php?sku=<?php echo $item['product_sku']; ?>" class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </a>
                                                </h5>
                                                
                                                <div class="d-flex justify-content-between align-items-center mt-3">
                                                    <div>
                                                        <?php if (!empty($item['sale_price'])): ?>
                                                            <span class="text-decoration-line-through text-muted me-2">
                                                                $<?php echo number_format($item['price'], 2); ?>
                                                            </span>
                                                            <span class="fw-bold text-danger">
                                                                $<?php echo number_format($item['sale_price'], 2); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="fw-bold">
                                                                $<?php echo number_format($item['price'], 2); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <a href="product.php?sku=<?php echo $item['product_sku']; ?>" class="btn btn-primary btn-sm">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    
                    <?php elseif ($active_tab === 'security'): ?>
                        <h3 class="card-title mb-4">Security Settings</h3>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=security">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Current Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                       id="currentPassword" name="current_password" required>
                                <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">New Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                       id="newPassword" name="new_password" required>
                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                <?php else: ?>
                                    <div class="form-text">Password must be at least 8 characters long</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirmPassword" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h4>Account Activity</h4>
                        <p class="text-muted">Your account was created on <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                        <p>If you notice any suspicious activity, please change your password immediately and contact customer support.</p>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
