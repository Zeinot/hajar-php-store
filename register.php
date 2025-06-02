<?php
// Registration page for e-commerce website
$pageTitle = "Register";
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isLoggedIn()) {
    redirect(SITE_URL);
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $fullName = isset($_POST['full_name']) ? sanitize($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
    $address = isset($_POST['shipping_address']) ? sanitize($_POST['shipping_address']) : '';
    
    // Validate form data
    $errors = [];
    
    if (empty($fullName)) {
        $errors[] = 'Full name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check if phone is valid (optional field)
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]*$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }
    
    // Attempt registration if no validation errors
    if (empty($errors)) {
        $registerResult = registerUser($fullName, $email, $password, $phone, $address);
        
        if ($registerResult) {
            // Set success message
            setFlashMessage('Registration successful! You can now log in.', 'success');
            
            // Redirect to login page
            redirect(SITE_URL . 'login.php');
        } else {
            // Add error message if registration failed
            $errors[] = 'Registration failed. Email may already be in use.';
        }
    }
}

// Include header
include 'partials/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">Create an Account</h3>
                </div>
                <div class="card-body">
                    <!-- Flash Messages -->
                    <?php displayFlashMessages(); ?>
                    
                    <!-- Error Messages -->
                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Registration Form -->
                    <form action="<?= SITE_URL ?>register.php" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= isset($fullName) ? htmlspecialchars($fullName) : '' ?>" required>
                            <div class="invalid-feedback">
                                Please enter your full name.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <div class="invalid-feedback">
                                Password must be at least 6 characters long.
                            </div>
                            <div class="form-text">
                                Password must be at least 6 characters long.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">
                                Passwords do not match.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= isset($phone) ? htmlspecialchars($phone) : '' ?>">
                            <div class="form-text">
                                Optional, but recommended for order updates.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Shipping Address</label>
                            <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3"><?= isset($address) ? htmlspecialchars($address) : '' ?></textarea>
                            <div class="form-text">
                                Optional. You can also add this later.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="registerBtn">Create Account</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    Already have an account? <a href="<?= SITE_URL ?>login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom JavaScript for form validation and button disabling -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.querySelector('.needs-validation');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const registerBtn = document.getElementById('registerBtn');
    
    // Check if passwords match
    function checkPasswordsMatch() {
        if (passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
    
    // Add event listeners for password validation
    passwordInput.addEventListener('change', checkPasswordsMatch);
    confirmPasswordInput.addEventListener('keyup', checkPasswordsMatch);
    
    // Form submission
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        } else {
            // Disable submit button to prevent multiple submissions
            registerBtn.disabled = true;
            registerBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
        }
        
        form.classList.add('was-validated');
    });
});
</script>

<?php include 'partials/footer.php'; ?>
