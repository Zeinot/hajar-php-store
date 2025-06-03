<?php
/**
 * Admin Categories Management
 */
$pageTitle = "Categories Management";
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure only admins can access this page
ensureAdminAuthenticated();

// Initialize database connection
$db = Database::getInstance();

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // Get category action type
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    try {
        switch ($action) {
            case 'create':
                // Create a new category
                $name = isset($_POST['name']) ? trim($_POST['name']) : '';
                $description = isset($_POST['description']) ? trim($_POST['description']) : '';
                
                // Validate inputs
                if (empty($name)) {
                    throw new Exception('Category name is required');
                }
                
                // Handle icon upload
                $icon_path = '';
                if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                    $filename = $_FILES['icon']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, $allowed)) {
                        throw new Exception('Invalid file format. Allowed formats: ' . implode(', ', $allowed));
                    }
                    
                    // Create upload directory if it doesn't exist
                    $upload_dir = '../assets/uploads/categories/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $new_filename = uniqid() . '.' . $ext;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['icon']['tmp_name'], $destination)) {
                        $icon_path = 'assets/uploads/categories/' . $new_filename;
                    } else {
                        throw new Exception('Failed to upload icon');
                    }
                }
                
                // Insert new category
                $query = "INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)";
                $params = [$name, $description, $icon_path];
                $result = $db->executeWithParams($query, "sss", $params);
                
                if ($result) {
                    $category_id = $db->getLastId();
                    // Log success
                    error_log("Admin created new category: ID {$category_id}, Name: {$name}");
                    echo json_encode(['success' => true, 'message' => 'Category created successfully', 'id' => $category_id]);
                } else {
                    throw new Exception('Failed to create category');
                }
                break;
                
            case 'update':
                // Update existing category
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $name = isset($_POST['name']) ? trim($_POST['name']) : '';
                $description = isset($_POST['description']) ? trim($_POST['description']) : '';
                
                // Validate inputs
                if (empty($id) || $id <= 0) {
                    throw new Exception('Invalid category ID');
                }
                
                if (empty($name)) {
                    throw new Exception('Category name is required');
                }
                
                // Get existing category to check for icon changes
                $existing = $db->fetchOneWithParams("SELECT icon FROM categories WHERE id = ?", "i", [$id]);
                $icon_path = $existing['icon'] ?? '';
                
                // Handle icon upload if a new file is provided
                if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                    $filename = $_FILES['icon']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, $allowed)) {
                        throw new Exception('Invalid file format. Allowed formats: ' . implode(', ', $allowed));
                    }
                    
                    // Create upload directory if it doesn't exist
                    $upload_dir = '../assets/uploads/categories/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $new_filename = uniqid() . '.' . $ext;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['icon']['tmp_name'], $destination)) {
                        // Delete old icon if it exists
                        if (!empty($existing['icon']) && file_exists('../' . $existing['icon'])) {
                            unlink('../' . $existing['icon']);
                        }
                        $icon_path = 'assets/uploads/categories/' . $new_filename;
                    } else {
                        throw new Exception('Failed to upload icon');
                    }
                }
                
                // Update category
                $query = "UPDATE categories SET name = ?, description = ?, icon = ? WHERE id = ?";
                $params = [$name, $description, $icon_path, $id];
                $result = $db->executeWithParams($query, "sssi", $params);
                
                if ($result) {
                    // Log success
                    error_log("Admin updated category: ID {$id}, Name: {$name}");
                    echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
                } else {
                    throw new Exception('Failed to update category');
                }
                break;
                
            case 'delete':
                // Delete category
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                
                // Validate input
                if (empty($id) || $id <= 0) {
                    throw new Exception('Invalid category ID');
                }
                
                // Check if category exists and get icon path
                $category = $db->fetchOneWithParams("SELECT * FROM categories WHERE id = ?", "i", [$id]);
                if (!$category) {
                    throw new Exception('Category not found');
                }
                
                // Check if the category is used in any products
                $product_count = $db->fetchOneWithParams(
                    "SELECT COUNT(*) as count FROM product_categories WHERE category_id = ?", 
                    "i",
                    [$id]
                );
                
                if ($product_count && $product_count['count'] > 0) {
                    throw new Exception('Cannot delete category because it is assigned to ' . $product_count['count'] . ' product(s)');
                }
                
                // Delete category
                $result = $db->executeWithParams("DELETE FROM categories WHERE id = ?", "i", [$id]);
                
                if ($result) {
                    // Delete icon file if it exists
                    if (!empty($category['icon']) && file_exists('../' . $category['icon'])) {
                        unlink('../' . $category['icon']);
                    }
                    
                    // Log success
                    error_log("Admin deleted category: ID {$id}, Name: {$category['name']}");
                    echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
                } else {
                    throw new Exception('Failed to delete category');
                }
                break;
                
            case 'get':
                // Get single category details
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                
                // Validate input
                if (empty($id) || $id <= 0) {
                    throw new Exception('Invalid category ID');
                }
                
                $category = $db->fetchOneWithParams("SELECT * FROM categories WHERE id = ?", "i", [$id]);
                
                if ($category) {
                    echo json_encode(['success' => true, 'category' => $category]);
                } else {
                    throw new Exception('Category not found');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        // Log error
        error_log("Error in category management: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit; // End script execution for AJAX requests
}

// For normal page display, get categories with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Number of categories per page
$offset = ($page - 1) * $limit;

// Get total count of categories
$total_count = $db->fetchOne("SELECT COUNT(*) as count FROM categories");
$total_pages = ceil($total_count['count'] / $limit);

// Get categories for current page
$categories = $db->fetchAllWithParams(
    "SELECT * FROM categories ORDER BY name ASC LIMIT ? OFFSET ?",
    "ii",
    [$limit, $offset]
);

// Include header
include '../admin/partials/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../admin/partials/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2">Category Management</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-plus me-2"></i>Add Category
                </button>
            </div>
    
    <!-- Flash Messages Container -->
    <div id="flashMessages"></div>
    
    <!-- Categories Grid -->
    <div class="row g-4" id="categoriesContainer">
        <?php if (empty($categories)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No categories found. Use the "Add Category" button to create one.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3" data-category-id="<?= $category['id'] ?>">
                    <div class="card h-100 shadow-sm">
                        <div class="position-relative">
                            <?php if (!empty($category['icon']) && file_exists('../' . $category['icon'])): ?>
                                <img src="../<?= $category['icon'] ?>" class="card-img-top p-3" alt="<?= htmlspecialchars($category['name']) ?>" style="height: 160px; object-fit: contain;">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($category['name']) ?></h5>
                            <p class="card-text small text-muted">
                                <?= !empty($category['description']) ? htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : '') : 'No description' ?>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-sm btn-outline-primary edit-category-btn" 
                                    data-id="<?= $category['id'] ?>">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-category-btn"
                                    data-id="<?= $category['id'] ?>" 
                                    data-name="<?= htmlspecialchars($category['name']) ?>">
                                    <i class="fas fa-trash-alt me-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="row mt-4">
            <div class="col-12">
                <nav aria-label="Category navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    <?php endif; ?>
</main>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="categoryForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="categoryId" name="id">
                    <input type="hidden" id="action" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                        <div class="invalid-feedback">Please provide a category name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryIcon" class="form-label">Category Icon</label>
                        <input type="file" class="form-control" id="categoryIcon" name="icon" accept="image/*">
                        <div class="form-text">Recommended size: 512x512px. Max size: 2MB.</div>
                    </div>
                    
                    <div id="currentIconContainer" class="mb-3 d-none">
                        <label class="form-label">Current Icon</label>
                        <div class="border p-2 text-center">
                            <img id="currentIcon" src="" alt="Current Icon" style="max-height: 100px; max-width: 100%;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveCategory">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the category "<span id="categoryToDelete"></span>"?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php require_once '../partials/footer.php'; ?>

<script>
$(document).ready(function() {
    // Show flash message
    function showFlash(message, type = 'success') {
        const flashContainer = $('#flashMessages');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        flashContainer.html(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
    
    // Reset category form
    function resetCategoryForm() {
        $('#categoryForm')[0].reset();
        $('#categoryId').val('');
        $('#action').val('create');
        $('#categoryModalLabel').text('Add Category');
        $('#currentIconContainer').addClass('d-none');
        $('#currentIcon').attr('src', '');
    }
    
    // Edit category handler
    $(document).on('click', '.edit-category-btn', function() {
        const categoryId = $(this).data('id');
        
        // Reset form first
        resetCategoryForm();
        
        // Set action to update
        $('#action').val('update');
        $('#categoryId').val(categoryId);
        $('#categoryModalLabel').text('Edit Category');
        
        // Fetch category details
        $.ajax({
            url: 'categories.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get',
                id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    const category = response.category;
                    
                    // Fill form fields
                    $('#categoryName').val(category.name);
                    $('#categoryDescription').val(category.description);
                    
                    // Show current icon if exists
                    if (category.icon) {
                        $('#currentIconContainer').removeClass('d-none');
                        $('#currentIcon').attr('src', '../' + category.icon);
                    }
                    
                    // Open modal
                    $('#categoryModal').modal('show');
                } else {
                    showFlash(response.message, 'error');
                }
            },
            error: function() {
                showFlash('Failed to load category details. Please try again.', 'error');
            }
        });
    });
    
    // Delete category handler
    $(document).on('click', '.delete-category-btn', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        
        $('#categoryToDelete').text(categoryName);
        $('#confirmDelete').data('id', categoryId);
        
        $('#deleteConfirmModal').modal('show');
    });
    
    // Confirm delete handler
    $('#confirmDelete').on('click', function() {
        const categoryId = $(this).data('id');
        const $deleteBtn = $(this);
        
        // Disable button to prevent multiple clicks
        $deleteBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
        
        $.ajax({
            url: 'categories.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete',
                id: categoryId
            },
            success: function(response) {
                // Close modal
                $('#deleteConfirmModal').modal('hide');
                
                // Re-enable button
                $deleteBtn.prop('disabled', false).html('Delete');
                
                if (response.success) {
                    // Remove category from the grid
                    $(`[data-category-id="${categoryId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        
                        // Show message if no categories left
                        if ($('#categoriesContainer').children().length === 0) {
                            $('#categoriesContainer').html(`
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        No categories found. Use the "Add Category" button to create one.
                                    </div>
                                </div>
                            `);
                        }
                    });
                    
                    showFlash(response.message);
                } else {
                    showFlash(response.message, 'error');
                }
            },
            error: function() {
                // Close modal
                $('#deleteConfirmModal').modal('hide');
                
                // Re-enable button
                $deleteBtn.prop('disabled', false).html('Delete');
                
                showFlash('Failed to delete category. Please try again.', 'error');
            }
        });
    });
    
    // Reset form when modal is closed
    $('#categoryModal').on('hidden.bs.modal', function() {
        resetCategoryForm();
    });
    
    // Form submission
    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
        
        // Form validation
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }
        
        const $submitBtn = $('#saveCategory');
        const action = $('#action').val();
        const actionText = action === 'create' ? 'Adding' : 'Updating';
        
        // Disable button to prevent multiple submissions
        $submitBtn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${actionText}...`);
        
        // Prepare form data
        const formData = new FormData(this);
        
        $.ajax({
            url: 'categories.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Close modal and show success message
                    $('#categoryModal').modal('hide');
                    showFlash(response.message);
                    
                    // Reload the page to show updated categories
                    window.location.reload();
                } else {
                    // Re-enable button
                    $submitBtn.prop('disabled', false).html('Save Category');
                    
                    // Show error message
                    showFlash(response.message, 'error');
                }
            },
            error: function() {
                // Re-enable button
                $submitBtn.prop('disabled', false).html('Save Category');
                
                // Show error message
                showFlash('Failed to save category. Please try again.', 'error');
            }
        });
    });
});
</script>

<?php include '../admin/partials/footer.php'; ?>