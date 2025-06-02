<?php
/**
 * Admin Orders Management for Elegant Drapes
 * Lists all orders with filtering and sorting options
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/auth.php';

// Ensure admin is logged in
require_admin_login();

// Set page title
$page_title = 'Orders Management';

// Initialize variables
$orders = [];
$total_orders = 0;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

// Filter variables
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';
$sort_by = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'newest';

// Status options for filter
$status_options = [
    'pending' => 'Pending',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded'
];

// Status badges with colors and icons
$status_badges = [
    'pending' => [
        'color' => 'warning',
        'icon' => 'clock-history'
    ],
    'processing' => [
        'color' => 'primary',
        'icon' => 'gear'
    ],
    'shipped' => [
        'color' => 'info',
        'icon' => 'truck'
    ],
    'delivered' => [
        'color' => 'success',
        'icon' => 'check-circle'
    ],
    'cancelled' => [
        'color' => 'danger',
        'icon' => 'x-circle'
    ],
    'refunded' => [
        'color' => 'secondary',
        'icon' => 'arrow-counterclockwise'
    ]
];

// Bulk action response message
$bulk_action_message = '';
$bulk_action_type = '';

// Process bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = sanitize_input($_POST['bulk_action']);
    $selected_orders = isset($_POST['selected_orders']) ? $_POST['selected_orders'] : [];
    
    if (!empty($selected_orders) && in_array($action, array_keys($status_options))) {
        try {
            $conn = getDbConnection();
            $updated_count = 0;
            
            foreach ($selected_orders as $order_id) {
                $order_id = intval($order_id);
                
                // Get current status
                $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $current_status = $result->fetch_assoc()['status'];
                    
                    // Only update if status is different
                    if ($current_status !== $action) {
                        // Update order status
                        $update_stmt = $conn->prepare("
                            UPDATE orders 
                            SET status = ?, updated_by = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $admin_id = $_SESSION['admin_id'];
                        $update_stmt->bind_param("sii", $action, $admin_id, $order_id);
                        
                        if ($update_stmt->execute()) {
                            $updated_count++;
                            
                            // Add history record (the trigger in the database will handle this)
                            $notes = "Status updated by admin from $current_status to $action";
                            $history_stmt = $conn->prepare("
                                INSERT INTO order_status_history (order_id, status, notes, created_by)
                                VALUES (?, ?, ?, ?)
                            ");
                            $history_stmt->bind_param("issi", $order_id, $action, $notes, $admin_id);
                            $history_stmt->execute();
                            $history_stmt->close();
                        }
                        
                        $update_stmt->close();
                    }
                }
                
                $stmt->close();
            }
            
            if ($updated_count > 0) {
                $bulk_action_message = "Successfully updated status of $updated_count orders to '" . ucfirst($action) . "'";
                $bulk_action_type = 'success';
                
                // Log action
                log_message("Admin ID {$_SESSION['admin_id']} bulk updated $updated_count orders to status: $action", 'info');
            } else {
                $bulk_action_message = "No orders were updated. They may already have the selected status.";
                $bulk_action_type = 'info';
            }
            
            $conn->close();
        } catch (Exception $e) {
            $bulk_action_message = "Error updating orders: " . $e->getMessage();
            $bulk_action_type = 'danger';
            log_message('Error in bulk updating orders: ' . $e->getMessage(), 'error');
        }
    } elseif (empty($selected_orders)) {
        $bulk_action_message = "No orders were selected for the action.";
        $bulk_action_type = 'warning';
    }
}

// Fetch orders with filtering and pagination
try {
    $conn = getDbConnection();
    
    // Build WHERE clause based on filters
    $where_clauses = [];
    $params = [];
    $param_types = '';
    
    if (!empty($status_filter)) {
        $where_clauses[] = "o.status = ?";
        $params[] = $status_filter;
        $param_types .= 's';
    }
    
    if (!empty($search_term)) {
        $search_param = "%{$search_term}%";
        $where_clauses[] = "(o.id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR o.tracking_number LIKE ?)";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'ssss';
    }
    
    if (!empty($date_from)) {
        $where_clauses[] = "o.order_date >= ?";
        $params[] = $date_from . ' 00:00:00';
        $param_types .= 's';
    }
    
    if (!empty($date_to)) {
        $where_clauses[] = "o.order_date <= ?";
        $params[] = $date_to . ' 23:59:59';
        $param_types .= 's';
    }
    
    $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);
    
    // Determine sorting
    $sort_sql = match($sort_by) {
        'oldest' => 'ORDER BY o.order_date ASC',
        'total_asc' => 'ORDER BY o.total_amount ASC',
        'total_desc' => 'ORDER BY o.total_amount DESC',
        default => 'ORDER BY o.order_date DESC' // newest first
    };
    
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        $where_sql
    ";
    
    if (!empty($params)) {
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_orders = $count_result->fetch_assoc()['count'];
        $count_stmt->close();
    } else {
        $count_result = $conn->query($count_sql);
        $total_orders = $count_result->fetch_assoc()['count'];
    }
    
    // Get paginated orders
    $sql = "
        SELECT o.*, u.full_name, u.email,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        $where_sql
        $sort_sql
        LIMIT ?, ?
    ";
    
    $stmt = $conn->prepare($sql);
    $param_types .= 'ii';
    $params[] = $offset;
    $params[] = $items_per_page;
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($order = $result->fetch_assoc()) {
        $orders[] = $order;
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    log_message('Error fetching orders: ' . $e->getMessage(), 'error');
}

// Calculate pagination variables
$total_pages = ceil($total_orders / $items_per_page);
$prev_page = max(1, $current_page - 1);
$next_page = min($total_pages, $current_page + 1);

// Build pagination URL
function build_pagination_url($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'orders.php?' . http_build_query($params);
}

// Build filter URL
function build_filter_url($params_to_update) {
    $params = $_GET;
    
    // Remove page param when filters change
    if (isset($params['page'])) {
        unset($params['page']);
    }
    
    // Update/add new params
    foreach ($params_to_update as $key => $value) {
        if ($value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    
    return 'orders.php?' . http_build_query($params);
}

// Include header
include 'includes/templates/header.php';
?>

<!-- Orders Management -->
<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="h3 mb-2">Orders Management</h1>
        <p class="text-muted">Manage and update order statuses</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="exports/export-orders.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Export
        </a>
    </div>
</div>

<?php if (!empty($bulk_action_message)): ?>
    <div class="alert alert-<?php echo $bulk_action_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $bulk_action_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Filters</h5>
    </div>
    <div class="card-body">
        <form action="orders.php" method="get" class="row g-3">
            <!-- Search -->
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Order #, name, email, tracking..." 
                       value="<?php echo htmlspecialchars($search_term); ?>">
            </div>
            
            <!-- Status -->
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($status_options as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Date Range -->
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            
            <!-- Sort -->
            <div class="col-md-2">
                <label for="sort" class="form-label">Sort By</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="total_desc" <?php echo $sort_by === 'total_desc' ? 'selected' : ''; ?>>Highest Total</option>
                    <option value="total_asc" <?php echo $sort_by === 'total_asc' ? 'selected' : ''; ?>>Lowest Total</option>
                </select>
            </div>
            
            <!-- Submit and Reset -->
            <div class="col-md-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-filter me-1"></i> Apply Filters
                </button>
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Orders List -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            Orders
            <?php if ($total_orders > 0): ?>
                <span class="badge bg-secondary ms-2"><?php echo number_format($total_orders); ?></span>
            <?php endif; ?>
        </h5>
        
        <?php if (!empty($orders)): ?>
            <form id="bulk-action-form" method="post" class="d-flex align-items-center">
                <select class="form-select form-select-sm me-2" name="bulk_action" id="bulk-action" style="width: auto;">
                    <option value="">Bulk Actions</option>
                    <optgroup label="Change Status">
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>">Update to <?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary" id="apply-bulk-action" disabled>Apply</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="mt-3 mb-0">No orders found</p>
                <?php if (!empty($status_filter) || !empty($search_term) || !empty($date_from) || !empty($date_to)): ?>
                    <p class="text-muted">Try adjusting your filters</p>
                    <a href="orders.php" class="btn btn-outline-secondary mt-2">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40">
                                <div class="form-check">
                                    <input class="form-check-input select-all-orders" type="checkbox" id="select-all">
                                </div>
                            </th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input order-checkbox" type="checkbox" 
                                               name="selected_orders[]" value="<?php echo $order['id']; ?>"
                                               form="bulk-action-form">
                                    </div>
                                </td>
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
                                        <i class="bi bi-<?php echo $status_badges[$order['status']]['icon']; ?> me-1"></i>
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><h6 class="dropdown-header">Change Status</h6></li>
                                                <?php foreach ($status_options as $value => $label): ?>
                                                    <?php if ($value !== $order['status']): ?>
                                                        <li>
                                                            <a class="dropdown-item status-change-link" href="#" 
                                                               data-order-id="<?php echo $order['id']; ?>"
                                                               data-status="<?php echo $value; ?>"
                                                               data-current-status="<?php echo $order['status']; ?>">
                                                                <i class="bi bi-<?php echo $status_badges[$value]['icon']; ?> me-1"></i>
                                                                <?php echo $label; ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="print-invoice.php?id=<?php echo $order['id']; ?>" target="_blank">
                                                        <i class="bi bi-printer me-1"></i> Print Invoice
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="print-shipping.php?id=<?php echo $order['id']; ?>" target="_blank">
                                                        <i class="bi bi-tag me-1"></i> Print Shipping Label
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center border-top p-3">
                    <div>
                        <span class="text-muted">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_orders); ?> 
                            of <?php echo number_format($total_orders); ?> orders
                        </span>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $current_page === 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo build_pagination_url($prev_page); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php 
                            // Show limited page numbers with ellipsis
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="' . build_pagination_url(1) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i === $current_page ? 'active' : '') . '">';
                                echo '<a class="page-link" href="' . build_pagination_url($i) . '">' . $i . '</a>';
                                echo '</li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="' . build_pagination_url($total_pages) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo build_pagination_url($next_page); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to change the status of order #<span id="modal-order-id"></span> from 
                    <span id="modal-current-status" class="fw-bold"></span> to 
                    <span id="modal-new-status" class="fw-bold"></span>?</p>
                
                <form id="status-change-form" method="post" action="update-order-status.php">
                    <input type="hidden" name="order_id" id="status-order-id">
                    <input type="hidden" name="status" id="status-new-value">
                    <div class="mb-3">
                        <label for="status-notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="status-notes" name="notes" rows="2" 
                                  placeholder="Add any notes about this status change"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="notify-customer" name="notify_customer" checked>
                        <label class="form-check-label" for="notify-customer">Notify customer via email</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="status-change-form" class="btn btn-primary">Update Status</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select all orders checkbox
        const selectAll = document.getElementById('select-all');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');
        const bulkActionButton = document.getElementById('apply-bulk-action');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const isChecked = this.checked;
                
                orderCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                
                updateBulkActionButton();
            });
        }
        
        // Individual checkboxes
        orderCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Update "select all" checkbox
                if (selectAll) {
                    selectAll.checked = [...orderCheckboxes].every(cb => cb.checked);
                    selectAll.indeterminate = !selectAll.checked && [...orderCheckboxes].some(cb => cb.checked);
                }
                
                updateBulkActionButton();
            });
        });
        
        // Update bulk action button state
        function updateBulkActionButton() {
            if (bulkActionButton) {
                const anyChecked = [...orderCheckboxes].some(cb => cb.checked);
                bulkActionButton.disabled = !anyChecked;
            }
        }
        
        // Status change links
        const statusChangeLinks = document.querySelectorAll('.status-change-link');
        const statusChangeModal = new bootstrap.Modal(document.getElementById('statusChangeModal'));
        
        statusChangeLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const orderId = this.getAttribute('data-order-id');
                const status = this.getAttribute('data-status');
                const currentStatus = this.getAttribute('data-current-status');
                
                // Set modal values
                document.getElementById('modal-order-id').textContent = orderId.padStart(6, '0');
                document.getElementById('modal-current-status').textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
                document.getElementById('modal-new-status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
                
                // Set form values
                document.getElementById('status-order-id').value = orderId;
                document.getElementById('status-new-value').value = status;
                
                // Show modal
                statusChangeModal.show();
            });
        });
        
        // Confirm bulk action
        const bulkActionForm = document.getElementById('bulk-action-form');
        
        if (bulkActionForm) {
            bulkActionForm.addEventListener('submit', function(e) {
                const bulkAction = document.getElementById('bulk-action').value;
                const selectedOrders = [...document.querySelectorAll('.order-checkbox:checked')];
                
                if (bulkAction === '' || selectedOrders.length === 0) {
                    e.preventDefault();
                    showToast('Please select both an action and at least one order', 'warning');
                    return false;
                }
                
                // Allow form submission
                return true;
            });
        }
    });
</script>

<?php include 'includes/templates/footer.php'; ?>
