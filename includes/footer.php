    </div>
    <!-- End Main Content Container -->

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>ShopSmart</h5>
                    <p>Your one-stop shop for quality products at affordable prices.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="shop.php" class="text-white">Shop</a></li>
                        <li><a href="about.php" class="text-white">About Us</a></li>
                        <li><a href="contact.php" class="text-white">Contact</a></li>
                        <li><a href="privacy.php" class="text-white">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <address>
                        <p><i class="fas fa-map-marker-alt me-2"></i> 123 Shopping St, Retail City</p>
                        <p><i class="fas fa-phone me-2"></i> +1 (555) 123-4567</p>
                        <p><i class="fas fa-envelope me-2"></i> info@shopsmart.com</p>
                    </address>
                </div>
            </div>
            <hr class="my-3 bg-secondary">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> ShopSmart. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">
                        <i class="fab fa-cc-visa me-2 fs-5"></i>
                        <i class="fab fa-cc-mastercard me-2 fs-5"></i>
                        <i class="fab fa-cc-paypal me-2 fs-5"></i>
                        <i class="fab fa-cc-amex fs-5"></i>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="assets/js/main.js"></script>
    <script>
    // Disable form submit buttons after click
    $(document).ready(function() {
        $('form').on('submit', function() {
            var form = $(this);
            var submitBtn = form.find('button[type="submit"]');
            
            // Disable the button and change text
            submitBtn.prop('disabled', true);
            if (submitBtn.html().indexOf('Loading') === -1) {
                var originalText = submitBtn.html();
                submitBtn.data('original-text', originalText);
                submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
            }
            
            // If the form is submitted via AJAX, we need to handle re-enabling the button there
            if (!form.hasClass('ajax-form')) {
                return true; // Allow normal form submission
            }
            
            // If it's an AJAX form, prevent default submission
            return false;
        });
        
        // Update cart count (you would implement this with AJAX to get the actual count)
        function updateCartCount() {
            $.ajax({
                url: 'get_cart_count.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('.cart-count').text(response.count);
                    }
                }
            });
        }
        
        // Call the function when page loads
        updateCartCount();
    });
    </script>
</body>
</html>
