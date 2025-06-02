/**
 * Main JavaScript file for E-commerce Website
 * Handles AJAX requests, form validations, and UI interactions
 */

// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get site URL from meta tag
    const siteUrl = document.querySelector('meta[name="site-url"]')?.content || '/';
    
    // Initialize components
    initializeQuantityControls();
    initializeFormValidation();
    initializeImageGallery();
    initializeFilters();
    initializeCart();
    updateCartCount();
    
    // Disable form submit buttons after click
    disableSubmitButtons();
    
    // Handle newsletter subscription
    handleNewsletterSubscription();
    
    // Log that the page is ready for debugging
    console.log('E-commerce JS initialized');
});

/**
 * Initialize quantity control buttons (+/-)
 */
function initializeQuantityControls() {
    document.querySelectorAll('.quantity-control').forEach(control => {
        const minusBtn = control.querySelector('.quantity-minus');
        const plusBtn = control.querySelector('.quantity-plus');
        const input = control.querySelector('input');
        
        if (!input) return;
        
        const minValue = parseInt(input.getAttribute('min') || '1');
        const maxValue = parseInt(input.getAttribute('max') || '99');
        
        minusBtn?.addEventListener('click', function() {
            const currentValue = parseInt(input.value);
            if (currentValue > minValue) {
                input.value = currentValue - 1;
                // Trigger change event
                input.dispatchEvent(new Event('change'));
            }
        });
        
        plusBtn?.addEventListener('click', function() {
            const currentValue = parseInt(input.value);
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
                // Trigger change event
                input.dispatchEvent(new Event('change'));
            }
        });
        
        // Validate input on change
        input.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < minValue) {
                this.value = minValue;
            } else if (value > maxValue) {
                this.value = maxValue;
            }
            
            // If this is in a cart, trigger cart update
            if (control.closest('.cart-item')) {
                const itemId = input.dataset.id;
                if (itemId) {
                    const updateBtn = document.getElementById('update-cart');
                    if (updateBtn) {
                        // Highlight update button
                        updateBtn.classList.add('btn-primary');
                        updateBtn.classList.remove('btn-outline-secondary');
                    }
                }
            }
        });
    });
}

/**
 * Disable submit buttons after form submit to prevent multiple submissions
 */
function disableSubmitButtons() {
    document.querySelectorAll('form:not(.ajax-form)').forEach(form => {
        form.addEventListener('submit', function() {
            // Skip forms with file inputs
            if (this.querySelector('input[type="file"]')) return;
            
            const submitButtons = this.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(button => {
                button.disabled = true;
                
                // Save original text and show loading spinner
                const originalText = button.innerHTML;
                button.dataset.originalText = originalText;
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
            });
        });
    });
}

/**
 * Handle newsletter subscription form
 */
function handleNewsletterSubscription() {
    const form = document.getElementById('newsletter-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const emailInput = this.querySelector('input[type="email"]');
        const email = emailInput.value.trim();
        
        if (!email) {
            showToast('Error', 'Please enter your email address', 'error');
            return;
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Subscribing...';
        
        // Simulate AJAX request (replace with actual implementation)
        setTimeout(() => {
            showToast('Success', 'Thank you for subscribing to our newsletter!', 'success');
            form.reset();
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Subscribe';
        }, 1500);
    });
}

/**
 * Show loading spinner when processing AJAX requests
 */
function showSpinner() {
    // Create spinner if it doesn't exist
    if (!document.getElementById('loadingSpinner')) {
        const spinnerHtml = `
            <div id="loadingSpinner" class="spinner-overlay">
                <div class="spinner-container">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2">Loading...</p>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', spinnerHtml);
    } else {
        document.getElementById('loadingSpinner').style.display = 'flex';
    }
}

/**
 * Hide loading spinner
 */
function hideSpinner() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.style.display = 'none';
    }
}

/**
 * Initialize product image gallery
 */
function initializeImageGallery() {
    // Handle main image change on thumbnail click
    const mainImage = document.querySelector('.product-main-image');
    const thumbnails = document.querySelectorAll('.product-image-gallery img');
    
    if (!mainImage || thumbnails.length === 0) return;
    
    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            // Update main image
            mainImage.src = this.src;
            
            // Update active thumbnail
            thumbnails.forEach(t => t.classList.remove('border', 'border-primary'));
            this.classList.add('border', 'border-primary');
        });
    });
}

/**
 * Initialize product filters
 */
function initializeFilters() {
    const filterForm = document.getElementById('filter-form');
    if (!filterForm) return;
    
    // Get all filter inputs
    const filterInputs = filterForm.querySelectorAll('input[type="checkbox"], input[type="radio"], select, input[type="range"]');
    
    // Add change event listeners
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            applyFilters(filterForm);
        });
    });
    
    // Handle clear filters button
    const clearBtn = document.getElementById('clear-filters');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Reset all inputs
            filterInputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                } else if (input.type === 'range') {
                    input.value = input.max;
                    // Update range display if exists
                    const display = document.getElementById(input.id + '-value');
                    if (display) display.textContent = input.value;
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });
            
            // Apply filters
            applyFilters(filterForm);
        });
    }
}

/**
 * Apply filters and update products list
 */
function applyFilters(filterForm) {
    // Show loading state
    const productsContainer = document.querySelector('.products-container');
    if (productsContainer) {
        productsContainer.classList.add('loading');
    }
    
    // Get form data and convert to URL parameters
    const formData = new FormData(filterForm);
    const params = new URLSearchParams(formData);
    
    // Update URL without reloading page
    const url = window.location.pathname + '?' + params.toString();
    window.history.pushState({ path: url }, '', url);
    
    // Reload the page (for now) - can be replaced with AJAX
    setTimeout(() => window.location.reload(), 500);
}

/**
 * Initialize cart functionality
 */
function initializeCart() {
    // Get site URL from meta tag
    const siteUrl = document.querySelector('meta[name="site-url"]')?.content || '/';
    
    // Initialize add to cart forms
    initializeAddToCart(siteUrl);
    
    // Initialize update cart button
    initializeUpdateCart(siteUrl);
    
    // Initialize remove from cart buttons
    initializeRemoveFromCart(siteUrl);
}

/**
 * Initialize add to cart functionality
 */
function initializeAddToCart(siteUrl) {
    // Add to cart forms
    const addToCartForms = document.querySelectorAll('.add-to-cart-form');
    addToCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Disable submit button and show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
            
            // Get form data
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch(`${siteUrl}api/cart.php?action=add`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (data.status === 'success') {
                    // Show success message
                    showToast('Success', data.message || 'Item added to cart successfully', 'success');
                    
                    // Update cart count
                    updateCartCount(data.data?.itemCount);
                    
                    // Reset quantity if it's a product details page
                    const quantityInput = this.querySelector('input[name="quantity"]');
                    if (quantityInput && document.querySelector('.product-main-image')) {
                        quantityInput.value = 1;
                    }
                } else {
                    // Show error message
                    showToast('Error', data.message || 'Failed to add item to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showToast('Error', 'Failed to add item to cart', 'error');
            });
        });
    });
}

/**
 * Initialize update cart button
 */
function initializeUpdateCart(siteUrl) {
    // Cart update button
    const updateCartBtn = document.getElementById('update-cart');
    if (!updateCartBtn) return;
    
    updateCartBtn.addEventListener('click', function() {
        // Disable button and show loading state
        const originalBtnText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
        
        // Get all quantity inputs
        const quantityInputs = document.querySelectorAll('input[id^="quantity-"]');
        const updatePromises = [];
        
        // Process each input one by one
        quantityInputs.forEach(input => {
            const itemId = input.dataset.id;
            const quantity = input.value;
            
            if (itemId && quantity) {
                // Create update promise
                const updatePromise = fetch(`${siteUrl}api/cart.php?action=update`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${itemId}&quantity=${quantity}`
                })
                .then(response => response.json());
                
                updatePromises.push(updatePromise);
            }
        });
        
        // Wait for all updates to complete
        Promise.all(updatePromises)
            .then(results => {
                // Get the last result for final state
                const lastResult = results[results.length - 1] || { status: 'error' };
                
                if (lastResult.status === 'success') {
                    // Show success message
                    showToast('Success', 'Cart updated successfully', 'success');
                    
                    // Update totals
                    document.getElementById('cart-subtotal').textContent = formatPrice(lastResult.data.total);
                    document.getElementById('cart-shipping').textContent = formatPrice(lastResult.data.shipping);
                    document.getElementById('cart-total').textContent = formatPrice(lastResult.data.orderTotal);
                    
                    // Update cart count
                    updateCartCount(lastResult.data.itemCount);
                    
                    // Reset button style
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-outline-secondary');
                } else {
                    showToast('Error', 'Failed to update some items', 'error');
                }
                
                // Re-enable button
                this.disabled = false;
                this.innerHTML = originalBtnText;
            })
            .catch(error => {
                console.error('Error updating cart:', error);
                showToast('Error', 'Failed to update cart', 'error');
                this.disabled = false;
                this.innerHTML = originalBtnText;
            });
    });
}

/**
 * Initialize remove from cart buttons
 */
function initializeRemoveFromCart(siteUrl) {
    // Remove from cart buttons
    const removeButtons = document.querySelectorAll('.remove-from-cart');
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.id;
            if (!itemId) return;
            
            // Confirm removal
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            // Disable button and show loading state
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            
            // Send delete request
            fetch(`${siteUrl}api/cart.php?action=remove`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${itemId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Show success message
                    showToast('Success', 'Item removed from cart successfully', 'success');
                    
                    // Remove item from DOM
                    const cartItem = this.closest('.cart-item');
                    if (cartItem) cartItem.remove();
                    
                    // Update cart count
                    updateCartCount(data.data.itemCount);
                    
                    // Update totals
                    document.getElementById('cart-subtotal').textContent = formatPrice(data.data.total);
                    document.getElementById('cart-shipping').textContent = formatPrice(data.data.shipping);
                    document.getElementById('cart-total').textContent = formatPrice(data.data.orderTotal);
                    
                    // Show empty cart message if no items left
                    if (data.data.itemCount === 0) {
                        const cartContainer = document.querySelector('.cart-container');
                        if (cartContainer) {
                            cartContainer.innerHTML = `
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        Your cart is empty. <a href="${siteUrl}products.php">Continue shopping</a>.
                                    </div>
                                </div>
                            `;
                        }
                    }
                } else {
                    // Show error message
                    showToast('Error', data.message, 'error');
                    
                    // Reset button
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-trash"></i>';
                }
            })
            .catch(error => {
                console.error('Error removing item:', error);
                showToast('Error', 'Failed to remove item from cart', 'error');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-trash"></i>';
            });
        });
    });
}

/**
 * Update cart count in navbar
 */
function updateCartCount(count) {
    // Get site URL from meta tag
    const siteUrl = document.querySelector('meta[name="site-url"]')?.content || '/';
    
    // If count is provided, just update the DOM
    if (count !== undefined) {
        updateCartBadge(count);
        return;
    }
    
    // Otherwise, fetch count from API
    fetch(`${siteUrl}api/cart.php?action=count`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                updateCartBadge(data.data.count);
            }
        })
        .catch(error => {
            console.error('Error fetching cart count:', error);
        });
        
    function updateCartBadge(count) {
        const cartBadge = document.getElementById('cart-count');
        if (cartBadge) {
            cartBadge.textContent = count;
            cartBadge.classList.toggle('d-none', count === 0);
        }
    }
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Show first error message
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    showToast('Error', 'Please check the form for errors', 'error');
                }
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Show toast notification
 */
function showToast(title, message, type = 'info') {
    // Map type to Bootstrap color
    const typeClass = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info'
    }[type] || 'bg-info';
    
    // Create toast container if it doesn't exist
    if (!document.querySelector('.toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1080';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${typeClass} text-white">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    // Add toast to container
    document.querySelector('.toast-container').insertAdjacentHTML('beforeend', toastHtml);
    
    // Initialize and show toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();
    
    // Remove toast from DOM after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

/**
 * Format price for display
 */
function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2);
}

/**
 * Update cart item quantity
 * @param {HTMLInputElement} input Quantity input element
 */
function updateCartItemQuantity(input) {
    const cartItemId = input.getAttribute('data-id');
    const quantity = input.value;
    
    // Cancel previous request for this item if exists
    if (ajaxRequests[cartItemId]) {
        ajaxRequests[cartItemId].abort();
    }
    
    // Create new AJAX request
    const controller = new AbortController();
    ajaxRequests[cartItemId] = controller;
    
    // Send AJAX request to update cart
    fetch(siteUrl + 'api/cart.php?action=update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `cart_item_id=${cartItemId}&quantity=${quantity}`,
        credentials: 'same-origin',
        signal: controller.signal
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update item subtotal
            const subtotalElement = input.closest('.cart-item').querySelector('.item-subtotal');
            if (subtotalElement) {
                subtotalElement.textContent = data.itemSubtotal;
            }
            
            // Update cart total
            if (document.getElementById('cart-total')) {
                document.getElementById('cart-total').textContent = data.cartTotal;
            }
        } else {
            // Show error message
            showToast('Error!', data.message, 'error');
            
            // Revert to previous quantity
            input.value = data.originalQuantity;
        }
    })
    .catch(error => {
        if (error.name !== 'AbortError') {
            console.error('Error updating cart:', error);
            showToast('Error!', 'Failed to update cart. Please try again.', 'error');
        }
    })
    .finally(() => {
        // Remove request from tracking
        delete ajaxRequests[cartItemId];
    });
}

/**
 * Update cart count in navbar
 * @param {number} count New cart count
 */
function updateCartCount(count) {
    const cartBadge = document.querySelector('.navbar .fa-shopping-cart + .badge');
    
    if (count > 0) {
        if (cartBadge) {
            cartBadge.textContent = count;
        } else {
            const cartIcon = document.querySelector('.navbar .fa-shopping-cart');
            if (cartIcon) {
                cartIcon.insertAdjacentHTML('afterend', `<span class="badge bg-danger">${count}</span>`);
            }
        }
    } else {
        if (cartBadge) {
            cartBadge.remove();
        }
    }
}

/**
 * Initialize product image gallery
 */
function initializeImageGallery() {
    const mainImage = document.querySelector('.product-main-image');
    if (!mainImage) return;
    
    document.querySelectorAll('.product-image-gallery img').forEach(img => {
        img.addEventListener('click', function() {
            // Update main image src
            mainImage.src = this.src;
            
            // Update active thumbnail
            document.querySelectorAll('.product-image-gallery img').forEach(thumb => {
                thumb.classList.remove('border', 'border-primary');
            });
            this.classList.add('border', 'border-primary');
        });
    });
}

/**
 * Initialize product filters with AJAX
 */
function initializeFilters() {
    // Product filtering without page reload
    const filterForm = document.getElementById('product-filters');
    if (!filterForm) return;
    
    // Handle filter form changes
    filterForm.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', function() {
            applyFilters();
        });
    });
    
    // Handle filter form submission
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        applyFilters();
    });
    
    // Handle filter reset
    const resetButton = filterForm.querySelector('.filter-reset');
    if (resetButton) {
        resetButton.addEventListener('click', function(e) {
            e.preventDefault();
            filterForm.reset();
            applyFilters();
        });
    }
}

/**
 * Apply product filters via AJAX
 */
function applyFilters() {
    const filterForm = document.getElementById('product-filters');
    if (!filterForm) return;
    
    const formData = new FormData(filterForm);
    const searchParams = new URLSearchParams(formData);
    
    // Show loading spinner
    showSpinner();
    
    // Update URL without reloading the page
    const newUrl = `${window.location.pathname}?${searchParams.toString()}`;
    window.history.pushState({ path: newUrl }, '', newUrl);
    
    // Send AJAX request to get filtered products
    fetch(siteUrl + 'api/products.php?' + searchParams.toString(), {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update products container
            const productsContainer = document.querySelector('.products-container');
            if (productsContainer) {
                productsContainer.innerHTML = data.html;
            }
            
            // Update pagination
            const paginationContainer = document.querySelector('.pagination-container');
            if (paginationContainer) {
                paginationContainer.innerHTML = data.pagination;
            }
            
            // Reinitialize add to cart buttons
            initializeAddToCart();
        } else {
            showToast('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error applying filters:', error);
        showToast('Error!', 'Failed to apply filters. Please try again.', 'error');
    })
    .finally(() => {
        // Hide loading spinner
        hideSpinner();
    });
}

/**
 * Initialize AJAX pagination
 */
function initializeAjaxPagination() {
    // Delegate event handler for pagination links
    document.addEventListener('click', function(e) {
        const paginationLink = e.target.closest('.pagination-container a');
        if (paginationLink) {
            e.preventDefault();
            
            const url = paginationLink.href;
            
            // Show loading spinner
            showSpinner();
            
            // Update URL without reloading the page
            window.history.pushState({ path: url }, '', url);
            
            // Send AJAX request to get paginated products
            fetch(url.replace('products.php', 'api/products.php'), {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update products container
                    const productsContainer = document.querySelector('.products-container');
                    if (productsContainer) {
                        productsContainer.innerHTML = data.html;
                    }
                    
                    // Update pagination
                    const paginationContainer = document.querySelector('.pagination-container');
                    if (paginationContainer) {
                        paginationContainer.innerHTML = data.pagination;
                    }
                    
                    // Scroll to top of products section
                    const productsSection = document.querySelector('.products-section');
                    if (productsSection) {
                        productsSection.scrollIntoView({ behavior: 'smooth' });
                    }
                    
                    // Reinitialize add to cart buttons
                    initializeAddToCart();
                } else {
                    showToast('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error loading page:', error);
                showToast('Error!', 'Failed to load page. Please try again.', 'error');
            })
            .finally(() => {
                // Hide loading spinner
                hideSpinner();
            });
        }
    });
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    // Form validation for all forms with 'needs-validation' class
    document.querySelectorAll('form.needs-validation').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
}

/**
 * Show toast notification
 * @param {string} title Toast title
 * @param {string} message Toast message
 * @param {string} type Toast type (success, error, warning, info)
 */
function showToast(title, message, type = 'info') {
    // Map type to Bootstrap color
    const typeClass = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info'
    }[type] || 'bg-info';
    
    // Create toast container if it doesn't exist
    if (!document.querySelector('.toast-container')) {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1080';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${typeClass} text-white">
                <strong class="me-auto">${title}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    // Add toast to container
    document.querySelector('.toast-container').insertAdjacentHTML('beforeend', toastHtml);
    
    // Initialize and show toast
    const toast = new bootstrap.Toast(document.getElementById(toastId), {
        autohide: true,
        delay: 5000
    });
    toast.show();
    
    // Remove toast from DOM after it's hidden
    document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}
