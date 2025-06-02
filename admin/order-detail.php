<?php
/**
 * Admin Order Detail Page for Elegant Drapes
 * Displays order information and allows status updates
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/auth.php';

// Ensure admin is logged in
require_admin_login();

// Initialize variables
$order = null;
$order_items = [];
$status_history = [];
$customer = null;
$error_message = '';
$success_message = '';

// Status options with badges and icons
$status_options = [
    'pending' => ['label' => 'Pending', 'color' => 'warning', 'icon' => 'clock-history'],
    'processing' => ['label' => 'Processing', 'color' => 'primary', 'icon' => 'gear'],
    'shipped' => ['label' => 'Shipped', 'color' => 'info', 'icon' => 'truck'],
    'delivered' => ['label' => 'Delivered', 'color' => 'success', 'icon' => 'check-circle'],
    'cancelled' => ['label' => 'Cancelled', 'color' => 'danger', 'icon' => 'x-circle'],
    'refunded' => ['label' => 'Refunded', 'color' => 'secondary', 'icon' => 'arrow-counterclockwise']
];

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = 'Invalid order ID';
} else {
    $order_id = intval($_GET['id']);
    
    try {
        $conn = getDbConnection();
        
        // Get order details
        $stmt = $conn->prepare("
            SELECT o.*, u.full_name, u.email, u.phone
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = 'Order not found';
        } else {
            $order = $result->fetch_assoc();
            
            // Get order items
            $items_stmt = $conn->prepare("
                SELECT oi.*, p.name as product_name, p.sku, 
                (SELECT image_url FROM product_images WHERE product_sku = p.sku AND is_primary = 1 LIMIT 1) as product_image
                FROM order_items oi
                JOIN products p ON oi.product_sku = p.sku
                WHERE oi.order_id = ?
            ");
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $order_items[] = $item;
            }
            
            // Get status history
            $history_stmt = $conn->prepare("
                SELECT osh.*, IFNULL(a.full_name, u.full_name) as updated_by_name
                FROM order_status_history osh
                LEFT JOIN admin a ON osh.created_by = a.id
                LEFT JOIN users u ON osh.created_by = u.id
                WHERE osh.order_id = ?
                ORDER BY osh.created_at DESC
            ");
            $history_stmt->bind_param("i", $order_id);
            $history_stmt->execute();
            $history_result = $history_stmt->get_result();
            
            while ($history = $history_result->fetch_assoc()) {
                $status_history[] = $history;
            }
            
            // Get customer details with order count
            $customer_stmt = $conn->prepare("
                SELECT u.*, 
                (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
                (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status != 'cancelled') as total_spent
                FROM users u
                WHERE u.id = ?
            ");
            $customer_stmt->bind_param("i", $order['user_id']);
            $customer_stmt->execute();
            $customer_result = $customer_stmt->get_result();
            $customer = $customer_result->fetch_assoc();
            
            $customer_stmt->close();
            $history_stmt->close();
            $items_stmt->close();
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $error_message = 'Error retrieving order details: ' . $e->getMessage();
        log_message('Error in order-detail.php: ' . $e->getMessage(), 'error');
    }
}

// Process status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $new_status = sanitize_input($_POST['status']);
        $notes = sanitize_input($_POST['notes']);
        $notify_customer = isset($_POST['notify_customer']) ? 1 : 0;
        
        if (!array_key_exists($new_status, $status_options)) {
            throw new Exception('Invalid status selected');
        }
        
        $conn = getDbConnection();
        
        // Get current status
        $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_status = $result->fetch_assoc()['status'];
        
        // Only update if status is different
        if ($current_status !== $new_status) {
            // Update order status
            $update_stmt = $conn->prepare("
                UPDATE orders 
                SET status = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $admin_id = $_SESSION['admin_id'];
            $update_stmt->bind_param("sii", $new_status, $admin_id, $order_id);
            
            if ($update_stmt->execute()) {
                // Add history record
                $history_stmt = $conn->prepare("
                    INSERT INTO order_status_history (order_id, status, notes, created_by)
                    VALUES (?, ?, ?, ?)
                ");
                $history_stmt->bind_param("issi", $order_id, $new_status, $notes, $admin_id);
                $history_stmt->execute();
                
                $success_message = "Order status successfully updated to " . ucfirst($new_status);
                
                // Send email notification if requested
                if ($notify_customer) {
                    // Email code would go here
                    // For now, just log it
                    log_message("Email notification would be sent to customer for order #$order_id status change", 'info');
                }
                
                // Log the action
                log_message("Admin ID {$_SESSION['admin_id']} updated order #$order_id status from $current_status to $new_status", 'info');
                
                $history_stmt->close();
            }
            
            $update_stmt->close();
        } else {
            $success_message = "Order status remains " . ucfirst($current_status) . " (no change needed)";
        }
        
        $stmt->close();
        $conn->close();
        
        // Refresh the page to show updated data
        header("Location: order-detail.php?id=$order_id&success=" . urlencode($success_message));
        exit();
    } catch (Exception $e) {
        $error_message = 'Error updating order status: ' . $e->getMessage();
        log_message('Error updating order status: ' . $e->getMessage(), 'error');
    }
}

// Set page title
$page_title = 'Order Details';
if ($order) {
    $page_title .= ' - #' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
}

// Get success message from URL if available
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Include header
include 'includes/templates/header.php';
?>