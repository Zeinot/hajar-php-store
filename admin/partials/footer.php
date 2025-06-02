    <!-- Toast container for notifications -->
    <div class="toast-container"></div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Admin JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get site URL from meta tag
        const siteUrl = document.querySelector('meta[name="site-url"]').getAttribute('content');
        
        // Store active AJAX requests
        const ajaxRequests = {};
        
        // Initialize tooltip
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Show toast message
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container');
            const toastEl = document.createElement('div');
            toastEl.classList.add('toast');
            toastEl.classList.add('align-items-center');
            toastEl.classList.add('border-0');
            
            // Set color based on type
            if (type === 'success') {
                toastEl.classList.add('text-white');
                toastEl.classList.add('bg-success');
            } else if (type === 'error') {
                toastEl.classList.add('text-white');
                toastEl.classList.add('bg-danger');
            } else if (type === 'warning') {
                toastEl.classList.add('text-dark');
                toastEl.classList.add('bg-warning');
            } else if (type === 'info') {
                toastEl.classList.add('text-white');
                toastEl.classList.add('bg-info');
            }
            
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            
            const toastBody = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastEl.innerHTML = toastBody;
            toastContainer.appendChild(toastEl);
            
            const toast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 5000
            });
            
            toast.show();
            
            // Remove toast from DOM after it's hidden
            toastEl.addEventListener('hidden.bs.toast', function() {
                toastContainer.removeChild(toastEl);
            });
        }
        
        // Check for flash messages on page load
        const flashMessages = document.querySelectorAll('.alert-dismissible');
        if (flashMessages.length > 0) {
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                flashMessages.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        }
        
        // Handle form submissions
        const forms = document.querySelectorAll('.ajax-form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Client-side validation
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    showToast('Please fix the validation errors.', 'error');
                    return;
                }
                
                // Get submit button and disable it
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                // Get form data
                const formData = new FormData(this);
                
                // Send AJAX request
                fetch(this.action, {
                    method: this.method,
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Operation completed successfully.', 'success');
                        
                        // Handle redirect if provided
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1000);
                        }
                        
                        // Handle form reset if no redirect
                        if (!data.redirect && data.reset) {
                            this.reset();
                            this.classList.remove('was-validated');
                            
                            // Reset any image previews
                            const imagePreviews = this.querySelectorAll('.image-preview');
                            imagePreviews.forEach(preview => {
                                preview.src = '';
                                preview.style.display = 'none';
                            });
                        }
                        
                        // Handle content update if provided
                        if (data.updateElement && data.content) {
                            const element = document.querySelector(data.updateElement);
                            if (element) {
                                element.innerHTML = data.content;
                            }
                        }
                    } else {
                        showToast(data.message || 'An error occurred.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('A network error occurred. Please try again.', 'error');
                })
                .finally(() => {
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            });
        });
        
        // Handle delete confirmation
        const deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const url = this.getAttribute('href') || this.getAttribute('data-url');
                const itemName = this.getAttribute('data-name') || 'this item';
                
                if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                    // Disable button to prevent multiple clicks
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                    
                    // Send AJAX delete request
                    fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message || 'Item deleted successfully.', 'success');
                            
                            // Remove item from DOM if parent element is specified
                            const parentSelector = this.getAttribute('data-parent');
                            if (parentSelector) {
                                const parent = document.querySelector(parentSelector);
                                if (parent) {
                                    parent.remove();
                                }
                            }
                            
                            // Redirect if URL provided
                            if (data.redirect) {
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 1000);
                            }
                            
                            // Reload page if specified and no redirect
                            if (data.reload && !data.redirect) {
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            }
                        } else {
                            showToast(data.message || 'Failed to delete item.', 'error');
                            this.disabled = false;
                            this.innerHTML = 'Delete';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('A network error occurred. Please try again.', 'error');
                        this.disabled = false;
                        this.innerHTML = 'Delete';
                    });
                }
            });
        });
        
        // Handle image upload preview
        const imageInputs = document.querySelectorAll('.image-upload');
        imageInputs.forEach(input => {
            input.addEventListener('change', function() {
                const previewId = this.getAttribute('data-preview');
                const preview = document.getElementById(previewId);
                
                if (preview && this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
        
        // Initialize any datepickers
        const datepickers = document.querySelectorAll('.datepicker');
        if (datepickers.length > 0 && $.fn.datepicker) {
            datepickers.forEach(datepicker => {
                $(datepicker).datepicker({
                    format: 'yyyy-mm-dd',
                    autoclose: true,
                    todayHighlight: true
                });
            });
        }
        
        // Make any tables sortable
        const sortableColumns = document.querySelectorAll('.sortable');
        sortableColumns.forEach(column => {
            column.addEventListener('click', function() {
                const table = this.closest('table');
                const index = Array.from(this.parentNode.children).indexOf(this);
                const direction = this.classList.contains('asc') ? -1 : 1;
                
                // Update UI for sort direction
                this.closest('tr').querySelectorAll('.sortable').forEach(col => {
                    col.classList.remove('asc', 'desc');
                    col.querySelector('i.fas')?.remove();
                });
                
                this.classList.add(direction === 1 ? 'asc' : 'desc');
                this.innerHTML += direction === 1 ? 
                    ' <i class="fas fa-sort-up"></i>' : 
                    ' <i class="fas fa-sort-down"></i>';
                
                // Sort table rows
                const rows = Array.from(table.querySelectorAll('tbody tr'));
                rows.sort((a, b) => {
                    const aValue = a.children[index].textContent.trim();
                    const bValue = b.children[index].textContent.trim();
                    
                    // Check if values are numbers
                    if (!isNaN(aValue) && !isNaN(bValue)) {
                        return direction * (Number(aValue) - Number(bValue));
                    }
                    
                    // Sort as strings
                    return direction * aValue.localeCompare(bValue);
                });
                
                // Reorder the rows
                const tbody = table.querySelector('tbody');
                rows.forEach(row => tbody.appendChild(row));
            });
        });
        
        // Expose functions to window
        window.adminUI = {
            showToast
        };
    });
    </script>
</body>
</html>
