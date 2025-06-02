<?php
/**
 * Login page for Elegant Drapes luxury clothing store
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if user is already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$email = '';
$remember = false;

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            $conn = getDbConnection();
            
            // Get user by email
            $stmt = $conn->prepare("SELECT id, full_name, email, password_hash, role, status FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $errors['account'] = 'Your account is not active. Please contact support.';
                    logActivity('login_failed', "Inactive account: {$email}");
                } 
                // Verify password
                elseif (password_verify($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Set remember me cookie if checked
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        // Store token in database
                        $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expiry) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expiry = ?");
                        $stmt->bind_param("issss", $user['id'], $token, date('Y-m-d H:i:s', $expiry), $token, date('Y-m-d H:i:s', $expiry));
                        $stmt->execute();
                        
                        // Set secure cookie
                        setcookie('remember_token', $token, $expiry, '/', '', true, true);
                    }
                    
                    // Log successful login
                    logActivity('login_success', "User logged in: {$email}");
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        // Redirect to intended page if set, otherwise to home
                        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
                        unset($_SESSION['redirect_after_login']);
                        redirect($redirect);
                    }
                } else {
                    $errors['password'] = 'Incorrect password';
                    logActivity('login_failed', "Invalid password for: {$email}");
                }
            } else {
                $errors['email'] = 'No account found with this email';
                logActivity('login_failed', "Unknown email: {$email}");
            }
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $errors['system'] = 'System error occurred. Please try again later.';
            logError('Login error: ' . $e->getMessage());
        }
    }
}

// Set page title
$page_title = 'Login | Elegant Drapes';

// Include header
include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="text-center mb-4 fw-bold">Welcome Back</h2>
                    
                    <?php if (!empty($errors['system'])): ?>
                        <div class="alert alert-danger"><?php echo $errors['system']; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors['account'])): ?>
                        <div class="alert alert-warning"><?php echo $errors['account']; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?>">
                            <?php 
                                echo $_SESSION['flash_message']; 
                                unset($_SESSION['flash_message']);
                                unset($_SESSION['flash_type']);
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                   id="password" name="password" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me" 
                                   <?php echo $remember ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p>Don't have an account? <a href="register.php" class="fw-bold text-decoration-none">Sign Up</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
