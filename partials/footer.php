    </div><!-- End of container from header -->
    
    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">Your one-stop shop for all your needs.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>" class="text-decoration-none text-muted">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>products.php" class="text-decoration-none text-muted">Products</a></li>
                        <li><a href="<?php echo SITE_URL; ?>cart.php" class="text-decoration-none text-muted">Cart</a></li>
                        <?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo SITE_URL; ?>profile.php" class="text-decoration-none text-muted">My Account</a></li>
                        <li><a href="<?php echo SITE_URL; ?>orders.php" class="text-decoration-none text-muted">My Orders</a></li>
                        <?php else: ?>
                        <li><a href="<?php echo SITE_URL; ?>login.php" class="text-decoration-none text-muted">Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>register.php" class="text-decoration-none text-muted">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <address class="text-muted">
                        <i class="fas fa-map-marker-alt me-2"></i> 123 E-Commerce Street, Shopping City<br>
                        <i class="fas fa-phone me-2"></i> +1 (555) 123-4567<br>
                        <i class="fas fa-envelope me-2"></i> <a href="mailto:info@example.com" class="text-decoration-none text-muted">info@example.com</a>
                    </address>
                </div>
            </div>
            <hr class="my-4 bg-secondary">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="#" class="text-decoration-none text-muted">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="#" class="text-decoration-none text-muted">Terms of Use</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo SITE_URL; ?>assets/js/main.js"></script>
    
    <!-- Form Submission Prevention Script -->
    <script>
    $(document).ready(function() {
        // Disable form submit buttons after click to prevent double submission
        $('form').on('submit', function() {
            var form = $(this);
            var submitButtons = form.find('button[type="submit"], input[type="submit"]');
            
            // Disable all submit buttons in the form
            submitButtons.prop('disabled', true);
            
            // Add spinner icon to indicate loading
            submitButtons.each(function() {
                var btn = $(this);
                var originalHtml = btn.html();
                btn.data('original-html', originalHtml);
                
                if (btn.is('button')) {
                    btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + 
                             (btn.text().trim() !== '' ? btn.text() : 'Processing...'));
                }
            });
            
            // If form submission takes too long, re-enable the buttons after 10 seconds
            setTimeout(function() {
                submitButtons.each(function() {
                    var btn = $(this);
                    btn.prop('disabled', false);
                    
                    if (btn.is('button') && btn.data('original-html')) {
                        btn.html(btn.data('original-html'));
                    }
                });
            }, 10000);
            
            // Continue with form submission
            return true;
        });
    });
    </script>
</body>
</html>
