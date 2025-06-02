/**
 * Main JavaScript file for E-commerce Website
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any components that need initialization
    initializeComponents();
    
    // Set up event listeners
    setupEventListeners();
});

// Initialize Bootstrap components and other features
function initializeComponents() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Set up event listeners for various interactive elements
function setupEventListeners() {
    // Product image thumbnails
    const thumbnails = document.querySelectorAll('.product-image-thumbnail');
    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            // Remove active class from all thumbnails
            thumbnails.forEach(t => t.classList.remove('active'));
            // Add active class to clicked thumbnail
            this.classList.add('active');
            // Update main image
            const mainImage = document.querySelector('.product-image-main');
            if (mainImage) {
                mainImage.src = this.src;
            }
        });
    });
    
    // Quantity buttons
    const quantityBtns = document.querySelectorAll('.quantity-btn');
    quantityBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.quantity-input');
            const currentValue = parseInt(input.value);
            const action = this.dataset.action;
            
            if (action === 'decrease' && currentValue > 1) {
                input.value = currentValue - 1;
            } else if (action === 'increase') {
                input.value = currentValue + 1;
            }
            
            // Trigger change event to update price if needed
            const event = new Event('change');
            input.dispatchEvent(event);
        });
    });
    
    // Add to cart buttons
    const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productSku = this.dataset.sku;
            const quantity = document.querySelector('.quantity-input') ? 
                document.querySelector('.quantity-input').value : 1;
            
            // Get selected size and color if available
            const sizeSelect = document.querySelector('#size-select');
            const colorSelect = document.querySelector('#color-select');
            
            const size = sizeSelect ? sizeSelect.value : null;
            const color = colorSelect ? colorSelect.value : null;
            
            // Disable button to prevent multiple clicks
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
            
            // Add to cart with AJAX
            addToCart(productSku, quantity, size, color, this);
        });
    });
    
    // Filter form in shop page
    const filterForm = document.querySelector('#filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            applyFilters();
        });
    }
    
    // Category filter checkboxes
    const categoryCheckboxes = document.querySelectorAll('.category-filter');
    categoryCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (filterForm && filterForm.dataset.ajaxFilter === 'true') {
                applyFilters();
            }
        });
    });
    
    // Price range inputs
    const priceRange = document.querySelector('#price-range');
    const priceOutput = document.querySelector('#price-output');
    if (priceRange && priceOutput) {
        priceRange.addEventListener('input', function() {
            priceOutput.textContent = '$' + this.value;
        });
        
        priceRange.addEventListener('change', function() {
            if (filterForm && filterForm.dataset.ajaxFilter === 'true') {
                applyFilters();
            }
        });
    }
    
    // Search form
    const searchForm = document.querySelector('#search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput.value.trim() !== '') {
                // Either submit form traditionally or use AJAX
                this.submit();
            }
        });
    }
}

// Add product to cart using AJAX
function addToCart(sku, quantity, size, color, button) {
    // Create form data
    const formData = new FormData();
    formData.append('sku', sku);
    formData.append('quantity', quantity);
    if (size) formData.append('size', size);
    if (color) formData.append('color', color);
    formData.append('action', 'add_to_cart');
    
    // Send AJAX request
    fetch('cart_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showToast('Success', 'Product added to cart!', 'success');
            
            // Update cart count
            updateCartCount(data.count);
            
            // Reset button
            button.disabled = false;
            button.innerHTML = 'Added to Cart <i class="fas fa-check"></i>';
            
            // Change button appearance for a short time
            setTimeout(() => {
                button.innerHTML = 'Add to Cart <i class="fas fa-shopping-cart"></i>';
            }, 2000);
        } else {
            // Show error message
            showToast('Error', data.message, 'error');
            
            // Reset button
            button.disabled = false;
            button.innerHTML = 'Add to Cart <i class="fas fa-shopping-cart"></i>';
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        
        // Show error message
        showToast('Error', 'Failed to add product to cart. Please try again.', 'error');
        
        // Reset button
        button.disabled = false;
        button.innerHTML = 'Add to Cart <i class="fas fa-shopping-cart"></i>';
    });
}

// Update cart count in the navbar
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
    });
}

// Apply filters on shop page with AJAX
function applyFilters() {
    const filterForm = document.querySelector('#filter-form');
    if (!filterForm) return;
    
    // Show loading spinner
    const productsContainer = document.querySelector('#products-container');
    if (productsContainer) {
        productsContainer.innerHTML = '<div class="loader"></div>';
    }
    
    // Get form data
    const formData = new FormData(filterForm);
    formData.append('ajax', 'true');
    
    // Create query string
    const queryString = new URLSearchParams(formData).toString();
    
    // Update URL without reloading page
    const newUrl = `${window.location.pathname}?${queryString}`;
    history.pushState({}, '', newUrl);
    
    // Fetch filtered products
    fetch(`shop_filter.php?${queryString}`)
    .then(response => response.text())
    .then(html => {
        if (productsContainer) {
            productsContainer.innerHTML = html;
            
            // Re-initialize components and listeners
            setupEventListeners();
        }
    })
    .catch(error => {
        console.error('Error applying filters:', error);
        if (productsContainer) {
            productsContainer.innerHTML = '<div class="alert alert-danger">Error loading products. Please try again.</div>';
        }
    });
}

// Show toast notification
function showToast(title, message, type) {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Create unique ID for the toast
    const toastId = 'toast-' + Date.now();
    
    // Set icon based on type
    let icon = '';
    let bgClass = '';
    
    switch (type) {
        case 'success':
            icon = '<i class="fas fa-check-circle me-2"></i>';
            bgClass = 'bg-success';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle me-2"></i>';
            bgClass = 'bg-danger';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
            bgClass = 'bg-warning';
            break;
        case 'info':
        default:
            icon = '<i class="fas fa-info-circle me-2"></i>';
            bgClass = 'bg-info';
    }
    
    // Create toast HTML
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bgClass} text-white">
                ${icon}
                <strong class="me-auto">${title}</strong>
                <small>Just now</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    // Add toast to container
    toastContainer.innerHTML += toastHtml;
    
    // Initialize and show the toast
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
