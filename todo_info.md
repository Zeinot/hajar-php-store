# E-commerce Website Project

## Database Schema

### Users Table
- full_name (VARCHAR)
- phone (VARCHAR) - Not required for admins
- shipping_address (TEXT) - Not required for admins
- password_hash (VARCHAR)
- status (TINYINT)
- role (ENUM) - 'customer', 'admin'

### Orders Table
- id (INT) - Primary Key
- user_id (INT) - Foreign Key to Users
- date (DATETIME)
- total (DECIMAL) - Calculated via trigger
- status (ENUM) - 'confirmed', 'pending', 'canceled', 'refunded'

### Sizes Table
- name (VARCHAR) - Primary Key

### Colors Table
- name (VARCHAR) - Primary Key

### Products Table
- SKU (VARCHAR) - Primary Key
- name (VARCHAR)
- description (TEXT)
- price (DECIMAL)
- stock (INT)

### Product_Images Table
- id (INT) - Primary Key
- product_sku (VARCHAR) - Foreign Key to Products
- image_path (VARCHAR)

### Categories Table
- id (INT) - Primary Key
- name (VARCHAR)
- description (TEXT)
- icon (VARCHAR) - Path to uploaded icon

### Product_Categories Table (Junction table)
- product_sku (VARCHAR) - Foreign Key to Products
- category_id (INT) - Foreign Key to Categories

### Product_Sizes Table (Junction table)
- product_sku (VARCHAR) - Foreign Key to Products
- size_name (VARCHAR) - Foreign Key to Sizes
- stock (INT) - Stock for this specific size

### Product_Colors Table (Junction table)
- product_sku (VARCHAR) - Foreign Key to Products
- color_name (VARCHAR) - Foreign Key to Colors
- stock (INT) - Stock for this specific color

### Order_Items Table
- id (INT) - Primary Key
- order_id (INT) - Foreign Key to Orders
- product_sku (VARCHAR) - Foreign Key to Products
- size_name (VARCHAR) - Foreign Key to Sizes (nullable)
- color_name (VARCHAR) - Foreign Key to Colors (nullable)
- quantity (INT)
- price (DECIMAL) - Price at time of order

## Project Structure
```
/
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
│       ├── products/
│       └── categories/
├── includes/
│   ├── config.php
│   ├── database.php
│   ├── functions.php
│   └── auth.php
├── admin/
│   ├── index.php
│   ├── products.php
│   ├── categories.php
│   ├── orders.php
│   └── users.php
├── partials/
│   ├── header.php
│   ├── footer.php
│   └── navbar.php
├── api/
│   ├── products.php
│   ├── categories.php
│   ├── orders.php
│   └── users.php
├── index.php
├── products.php
├── product-details.php
├── cart.php
├── checkout.php
├── login.php
├── register.php
├── profile.php
└── orders.php
```

## Technical Requirements
- HTML, CSS, JS, PHP, MySQL
- Mobile-first approach (responsive design)
- Card-based UI instead of tables
- CRUD operations without page reloads (highly reactive)
- Use of Bootstrap and/or jQuery
- Form submission buttons should be disabled after click using JS
- Implement logging for debugging issues
- Add pagination for product listings
- Add search and filtering capabilities

## Features To Implement
1. User authentication (register, login, logout)
2. User profile management
3. Product browsing with filters and search
4. Shopping cart functionality
5. Checkout process
6. Order history for users
7. Admin panel for managing:
   - Products
   - Categories
   - Orders
   - Users

## TODO List
- [x] Set up database schema
- [x] Create basic project structure
- [x] Implement user authentication
- [x] Create product listing page
- [x] Implement product details page
- [x] Create shopping cart functionality
- [ ] Implement checkout process
- [ ] Create admin dashboard
- [ ] Add product management for admins
- [ ] Add category management for admins
- [ ] Add order management for admins
- [ ] Add user management for admins
- [x] Implement responsive design
- [x] Add search and filtering functionality
- [x] Implement pagination

## Completed Tasks

### Admin Panel - Products Management
Implemented complete CRUD operations for the products management in admin panel:
- Created product listing view with pagination
- Implemented create, read, update, and delete operations
- Added image upload functionality with file management
- Implemented form handling with validation and error feedback
- Added transaction handling for data integrity
- Created category assignment and management for products
- Implemented proper security with admin-only access

### User Registration System
- Created a full-featured user registration page with server-side validation
- Implemented client-side validation with Bootstrap classes
- Added real-time password matching checks
- Integrated with existing authentication functions
- Implemented flash message display for user feedback

### Database Class Enhancements
- Added several helper methods to improve database operations:
  - `fetchOne()`, `fetchAll()` for simple queries
  - `fetchOneWithParams()`, `fetchAllWithParams()` for prepared statements
  - `executeWithParams()` for executing parameterized queries safely
- Enhanced error handling and security with prepared statements

### Product Details Page
Implemented a detailed product details page that:
- Retrieves product information by SKU from the database
- Displays product images with a main image and clickable thumbnails
- Shows product categories, sizes, colors, stock status, and price
- Includes an add-to-cart form with size, color, and quantity selection
- Shows related products from the same categories
- Implements breadcrumb navigation for user-friendly navigation

### Shopping Cart Functionality
Developed a complete shopping cart system:
- Cart page with product listing, quantity controls, and order summary
- RESTful API endpoint to manage cart operations (add, update, remove, get, count, clear)
- Stock validation before adding items to cart
- Session-based cart storage
- Real-time updates using AJAX

### JavaScript Enhancements
Fixed and enhanced the main.js file with proper structuring and complete functionality:
- Implemented AJAX-based cart operations (add, update, remove)
- Added quantity controls with increment/decrement buttons
- Implemented dynamic cart count updates in the navbar
- Added toast notifications for user feedback
- Disabled form buttons after submission to prevent duplicates
- Added product image gallery functionality
- Implemented product filtering
- Added form validation

### Authentication System
Implemented user authentication:
- Login page with email/password form
- Integration with existing auth.php functions
- Session-based authentication
- Appropriate error handling and user feedback

## Next Steps
1. Create the checkout process and order management with Cash on Delivery (COD) as the only payment method
2. Implement the admin panel for product, category, order, and user management
3. Add user profile management functionality
4. Implement order history for users

**Note**: As per requirements, we will NOT implement any payment gateway integration. The only payment method will be Cash on Delivery.
