<?php
/**
 * Admin Login Page for Elegant Drapes
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if admin is already logged in
if (isset($_SESSION['admin_id'])) {
    redirect('index.php');
}

$errors = [];
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            $conn = getDbConnection();
            
            // Get admin with matching email
            $stmt = $conn->prepare("SELECT id, email, password_hash, full_name, role FROM admin_users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $admin['password_hash'])) {
                    // Set session variables
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    
                    // Update last login timestamp
                    $update_stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->bind_param("i", $admin['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Log successful login
                    log_message("Admin login successful: {$admin['email']} (ID: {$admin['id']})", 'info');
                    
                    // Redirect to admin dashboard
                    redirect('index.php');
                } else {
                    $errors['login'] = 'Invalid email or password';
                    log_message("Failed admin login attempt: {$email} (Invalid password)", 'warning');
                }
            } else {
                $errors['login'] = 'Invalid email or password';
                log_message("Failed admin login attempt: {$email} (User not found or inactive)", 'warning');
            }
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $errors['system'] = 'System error occurred. Please try again later.';
            log_message('Admin login error: ' . $e->getMessage(), 'error');
        }
    }
}

// Page title
$page_title = 'Admin Login | Elegant Drapes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand h1 {
            font-size: 1.8rem;
            margin-bottom: 0;
            color: #333;
        }
        .brand p {
            color: #777;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card mx-auto">
            <div class="brand">
                <h1>Elegant Drapes</h1>
                <p>Admin Dashboard</p>
            </div>
            
            <?php if (!empty($errors['system']) || !empty($errors['login'])): ?>
                <div class="alert alert-danger">
                    <?php echo !empty($errors['system']) ? $errors['system'] : $errors['login']; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" 
                           id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (!empty($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" 
                           id="password" name="password" required>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Log In</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <a href="../index.php" class="text-decoration-none">Return to Website</a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Disable the submit button after click to prevent multiple submissions
        document.querySelector('form').addEventListener('submit', function(e) {
            // Get the submit button
            const submitBtn = document.querySelector('button[type="submit"]');
            
            // Disable the button and change text
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...';
        });
    </script>
</body>
</html>
