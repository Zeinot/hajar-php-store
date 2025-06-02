        </div><!-- End of main content -->
    </div><!-- End of content -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Mobile sidebar toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const isClickInside = sidebar.contains(event.target) || sidebarToggle.contains(event.target);
                
                if (!isClickInside && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            });
        });
        
        // Toast notifications
        function showToast(message, type = 'success') {
            const bgColors = {
                success: 'linear-gradient(to right, #00b09b, #96c93d)',
                error: 'linear-gradient(to right, #ff5f6d, #ffc371)',
                warning: 'linear-gradient(to right, #f7971e, #ffd200)',
                info: 'linear-gradient(to right, #2193b0, #6dd5ed)'
            };
            
            Toastify({
                text: message,
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: bgColors[type],
                stopOnFocus: true
            }).showToast();
        }
        
        // Disable submit buttons after click to prevent multiple submissions
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form:not(.no-disable-on-submit)');
            
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    
                    submitButtons.forEach(button => {
                        const originalText = button.innerHTML;
                        button.disabled = true;
                        
                        if (button.tagName === 'BUTTON') {
                            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                        }
                        
                        // If form validation fails, re-enable the button
                        setTimeout(() => {
                            if (!form.checkValidity()) {
                                button.disabled = false;
                                if (button.tagName === 'BUTTON') {
                                    button.innerHTML = originalText;
                                }
                            }
                        }, 100);
                    });
                });
            });
        });
    </script>
</body>
</html>
