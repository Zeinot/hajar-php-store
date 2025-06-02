<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session
start_session_if_not_started();

// Check if user is already logged in
if (is_logged_in()) {
    redirect('index.php');
}

// Initialize variables
$full_name = '';
$email = '';
$phone = '';
$shipping_address = '';
$error_message = '';
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log registration attempt
    log_message("Registration attempt with email: " . $_POST['email']);
    
    // Get form data
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $shipping_address = sanitize_input($_POST['shipping_address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate form data
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    $check_email_sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_email_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists. Please use a different email or try logging in";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database
        $insert_sql = "INSERT INTO users (full_name, email, phone, shipping_address, password_hash, role) VALUES (?, ?, ?, ?, ?, 'customer')";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssss", $full_name, $email, $phone, $shipping_address, $password_hash);
        
        if ($stmt->execute()) {
            // Log successful registration
            log_message("User registered successfully: " . $email, "success");
            
            // Set success message
            $success_message = "Registration successful! You can now login.";
            
            // Clear form data
            $full_name = '';
            $email = '';
            $phone = '';
            $shipping_address = '';
        } else {
            // Log registration error
            log_message("Registration failed for email: " . $email . " - Error: " . $stmt->error, "error");
            
            $error_message = "Registration failed. Please try again later.";
        }
    } else {
        // Combine all errors into a single message
        $error_message = implode("<br>", $errors);
        
        // Log validation errors
        log_message("Registration validation errors for email: " . $email . " - Errors: " . $error_message, "error");
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="form-container my-5">
                <h2 class="form-title">Create an Account</h2>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Login Now</a>
                    </div>
                <?php else: ?>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name*</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $full_name; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address*</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $phone; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Shipping Address</label>
                            <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3"><?php echo $shipping_address; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password*</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password*</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation with JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const fullName = document.getElementById('full_name');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Reset previous error messages
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
            
            // Validate full name
            if (fullName.value.trim() === '') {
                isValid = false;
                addError(fullName, 'Full name is required');
            }
            
            // Validate email
            if (email.value.trim() === '') {
                isValid = false;
                addError(email, 'Email is required');
            } else if (!isValidEmail(email.value)) {
                isValid = false;
                addError(email, 'Please enter a valid email address');
            }
            
            // Validate password
            if (password.value === '') {
                isValid = false;
                addError(password, 'Password is required');
            } else if (password.value.length < 6) {
                isValid = false;
                addError(password, 'Password must be at least 6 characters long');
            }
            
            // Validate confirm password
            if (confirmPassword.value === '') {
                isValid = false;
                addError(confirmPassword, 'Please confirm your password');
            } else if (password.value !== confirmPassword.value) {
                isValid = false;
                addError(confirmPassword, 'Passwords do not match');
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Helper function to add error message
    function addError(input, message) {
        input.classList.add('is-invalid');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        input.parentNode.appendChild(errorDiv);
    }
    
    // Helper function to validate email
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
