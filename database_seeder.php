<?php
/**
 * Database Seeder Script
 * 
 * This script populates the database with sample data for development and testing purposes.
 * It creates sample users, categories, products, orders, and related data.
 */

// Start timer to measure execution time
$start_time = microtime(true);

// Load required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Create log directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0777, true);
}

// Initialize logger
$log_file = 'logs/seeder_' . date('Y-m-d') . '.log';
function log_message($message, $type = 'INFO') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

// Connect to database
$db = Database::getInstance();
log_message("Connected to database", "INFO");

// Truncate tables (optional) - uncomment if you want to clear existing data
// Comment out the tables you don't want to truncate
function truncate_tables() {
    global $db;
    
    // Disable foreign key checks temporarily
    $db->execute("SET FOREIGN_KEY_CHECKS = 0");
    
    // Tables to truncate (in reverse order of dependencies)
    $tables = [
        'order_items',
        'orders',
        'product_colors',
        'product_sizes',
        'product_categories',
        'product_images',
        'products',
        'colors',
        'sizes',
        'categories',
        'users'
    ];
    
    foreach ($tables as $table) {
        try {
            $db->execute("TRUNCATE TABLE $table");
            log_message("Truncated table: $table", "INFO");
        } catch (Exception $e) {
            log_message("Error truncating table $table: " . $e->getMessage(), "ERROR");
        }
    }
    
    // Re-enable foreign key checks
    $db->execute("SET FOREIGN_KEY_CHECKS = 1");
}

// Uncomment the following line if you want to clear existing data before seeding
// truncate_tables();

// ============================
// ======= SEED USERS =========
// ============================
function seed_users($count = 10) {
    global $db;
    log_message("Starting to seed users...", "INFO");
    
    // First, add admin user if not exists
    $admin_exists = $db->fetchOneWithParams(
        "SELECT COUNT(*) as count FROM users WHERE email = ?",
        "s", 
        ["admin@example.com"]
    );
    
    if ($admin_exists['count'] == 0) {
        // Password: admin123
        $admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->executeWithParams(
            "INSERT INTO users (full_name, email, password_hash, phone, shipping_address, status, role) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            "sssssss", 
            ["Admin User", 
             "admin@example.com",
             $admin_hash,
             "555-123-4567",
             "123 Admin Street, Admin City, AC 12345",
             "1",
             "admin"
            ]
        );
        log_message("Added admin user", "INFO");
    }
    
    // Then add regular users
    $addresses = [
        "123 Main St, Anytown, AT 12345",
        "456 Oak Ave, Somewhere, SW 23456",
        "789 Pine Rd, Nowhere, NW 34567",
        "321 Elm Blvd, Everywhere, EV 45678",
        "654 Maple Dr, Anywhere, AW 56789"
    ];
    
    $created = 0;
    for ($i = 1; $i <= $count; $i++) {
        $full_name = "User " . $i;
        $email = "user" . $i . "@example.com";
        
        // Check if user already exists
        $user_exists = $db->fetchOneWithParams(
            "SELECT COUNT(*) as count FROM users WHERE email = ?",
            "s",
            [$email]
        );
        
        if ($user_exists['count'] == 0) {
            $password_hash = password_hash('password' . $i, PASSWORD_DEFAULT);
            $phone = "555-" . rand(100, 999) . "-" . rand(1000, 9999);
            $address = $addresses[array_rand($addresses)];
            
            $db->executeWithParams(
                "INSERT INTO users (full_name, email, password_hash, phone, shipping_address, status, role) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                "sssssss", 
                [$full_name, 
                 $email,
                 $password_hash,
                 $phone,
                 $address,
                 "1",
                 "customer"
                ]
            );
            $created++;
        }
    }
    
    log_message("Created $created new users", "INFO");
    return $created + ($admin_exists['count'] == 0 ? 1 : 0);
}

// ============================
// ===== SEED CATEGORIES ======
// ============================
function seed_categories() {
    global $db;
    log_message("Starting to seed categories...", "INFO");
    
    $categories = [
        ['Clothing', 'All types of clothing items', 'clothing.png'],
        ['Footwear', 'Shoes, sandals, and boots', 'footwear.png'],
        ['Accessories', 'Belts, hats, and jewelry', 'accessories.png'],
        ['Electronics', 'Gadgets and electronic devices', 'electronics.png'],
        ['Home & Kitchen', 'Items for your home', 'home.png'],
        ['Beauty & Personal Care', 'Beauty products and personal care items', 'beauty.png'],
        ['Sports & Outdoors', 'Sports equipment and outdoor gear', 'sports.png'],
        ['Books', 'Books of all genres', 'books.png'],
        ['Toys & Games', 'Fun for all ages', 'toys.png'],
        ['Pet Supplies', 'Everything for your pets', 'pets.png']
    ];
    
    $created = 0;
    foreach ($categories as $category) {
        $category_exists = $db->fetchOneWithParams(
            "SELECT COUNT(*) as count FROM categories WHERE name = ?",
            "s",
            [$category[0]]
        );
        
        if ($category_exists['count'] == 0) {
            $db->executeWithParams(
                "INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)",
                "sss", 
                [$category[0], $category[1], $category[2]]
            );
            $created++;
        }
    }
    
    log_message("Created $created new categories", "INFO");
    return $created;
}

// ============================
// ======= SEED SIZES =========
// ============================
function seed_sizes() {
    global $db;
    log_message("Starting to seed sizes...", "INFO");
    
    $sizes = [
        'XS', 'S', 'M', 'L', 'XL', 'XXL',
        '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45',
        'One Size', '4', '6', '8', '10', '12', '14', '16'
    ];
    
    $created = 0;
    foreach ($sizes as $size) {
        $size_exists = $db->fetchOneWithParams(
            "SELECT COUNT(*) as count FROM sizes WHERE name = ?",
            "s",
            [$size]
        );
        
        if ($size_exists['count'] == 0) {
            $db->executeWithParams(
                "INSERT INTO sizes (name) VALUES (?)",
                "s", 
                [$size]
            );
            $created++;
        }
    }
    
    log_message("Created $created new sizes", "INFO");
    return $created;
}

// ============================
// ======= SEED COLORS ========
// ============================
function seed_colors() {
    global $db;
    log_message("Starting to seed colors...", "INFO");
    
    $colors = [
        'Red', 'Green', 'Blue', 'Black', 'White', 
        'Yellow', 'Purple', 'Orange', 'Gray', 'Brown',
        'Pink', 'Navy', 'Teal', 'Maroon', 'Olive',
        'Silver', 'Gold', 'Beige', 'Turquoise', 'Lavender'
    ];
    
    $created = 0;
    foreach ($colors as $color) {
        $color_exists = $db->fetchOneWithParams(
            "SELECT COUNT(*) as count FROM colors WHERE name = ?",
            "s",
            [$color]
        );
        
        if ($color_exists['count'] == 0) {
            $db->executeWithParams(
                "INSERT INTO colors (name) VALUES (?)",
                "s", 
                [$color]
            );
            $created++;
        }
    }
    
    log_message("Created $created new colors", "INFO");
    return $created;
}

// ============================
// ====== SEED PRODUCTS =======
// ============================
function seed_products($count = 30) {
    global $db;
    log_message("Starting to seed products...", "INFO");
    
    // Get categories, sizes, and colors for associations
    $categories = $db->fetchAll("SELECT id, name FROM categories");
    $sizes = $db->fetchAll("SELECT name FROM sizes");
    $colors = $db->fetchAll("SELECT name FROM colors");
    
    if (empty($categories) || empty($sizes) || empty($colors)) {
        log_message("Missing categories, sizes, or colors. Please seed those first.", "ERROR");
        return 0;
    }
    
    // Product types for different categories
    $product_types = [
        'Clothing' => ['T-Shirt', 'Jeans', 'Dress', 'Sweater', 'Jacket', 'Hoodie', 'Blouse', 'Skirt', 'Shorts', 'Pants'],
        'Footwear' => ['Sneakers', 'Boots', 'Sandals', 'Loafers', 'Heels', 'Slippers', 'Running Shoes', 'Flip Flops'],
        'Accessories' => ['Watch', 'Sunglasses', 'Hat', 'Scarf', 'Gloves', 'Belt', 'Bag', 'Wallet', 'Necklace', 'Earrings'],
        'Electronics' => ['Headphones', 'Smartphone', 'Tablet', 'Laptop', 'Camera', 'Speaker', 'Smartwatch', 'Keyboard'],
        'Home & Kitchen' => ['Blender', 'Coffee Maker', 'Toaster', 'Plate Set', 'Cutlery Set', 'Towels', 'Bedding', 'Pillow'],
        'Beauty & Personal Care' => ['Shampoo', 'Conditioner', 'Face Cream', 'Perfume', 'Makeup Kit', 'Hair Dryer', 'Razor'],
        'Sports & Outdoors' => ['Yoga Mat', 'Dumbbells', 'Tennis Racket', 'Soccer Ball', 'Bicycle', 'Camping Tent', 'Hiking Boots'],
        'Books' => ['Novel', 'Cookbook', 'Biography', 'Self-Help Book', 'Children\'s Book', 'Reference Book', 'Textbook'],
        'Toys & Games' => ['Action Figure', 'Board Game', 'Puzzle', 'Doll', 'LEGO Set', 'Remote Control Car', 'Card Game'],
        'Pet Supplies' => ['Dog Food', 'Cat Litter', 'Pet Bed', 'Dog Toy', 'Cat Toy', 'Pet Carrier', 'Food Bowl']
    ];
    
    // Brands for different categories
    $brands = [
        'Clothing' => ['Nike', 'Adidas', 'H&M', 'Zara', 'Levi\'s', 'Gap', 'Ralph Lauren', 'Tommy Hilfiger'],
        'Footwear' => ['Nike', 'Adidas', 'Puma', 'Converse', 'Vans', 'New Balance', 'Timberland', 'Dr. Martens'],
        'Accessories' => ['Fossil', 'Ray-Ban', 'Coach', 'Michael Kors', 'Swatch', 'Herschel', 'Casio', 'Gucci'],
        'Electronics' => ['Apple', 'Samsung', 'Sony', 'Bose', 'JBL', 'Logitech', 'Dell', 'HP'],
        'Home & Kitchen' => ['KitchenAid', 'Cuisinart', 'Ikea', 'Crate & Barrel', 'Ninja', 'Keurig', 'OXO', 'Pyrex'],
        'Beauty & Personal Care' => ['L\'Oreal', 'Dove', 'Maybelline', 'Neutrogena', 'Olay', 'Nivea', 'Revlon', 'Garnier'],
        'Sports & Outdoors' => ['Nike', 'Adidas', 'Under Armour', 'Coleman', 'Wilson', 'The North Face', 'Patagonia', 'REI'],
        'Books' => ['Penguin Random House', 'HarperCollins', 'Simon & Schuster', 'Macmillan', 'Hachette', 'Scholastic'],
        'Toys & Games' => ['LEGO', 'Hasbro', 'Mattel', 'Fisher-Price', 'Playmobil', 'Melissa & Doug', 'Nintendo'],
        'Pet Supplies' => ['Purina', 'Pedigree', 'Blue Buffalo', 'Kong', 'PetSafe', 'Friskies', 'Hill\'s Science Diet']
    ];
    
    // Associate specific categories with appropriate sizes
    $category_sizes = [
        'Clothing' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'Footwear' => ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'],
        'Accessories' => ['One Size', 'S', 'M', 'L']
    ];
    
    // Create products
    $created = 0;
    for ($i = 1; $i <= $count; $i++) {
        // Randomly select a category
        $category = $categories[array_rand($categories)];
        $category_name = $category['name'];
        
        // Get product types for this category, or use generic types
        $types = isset($product_types[$category_name]) ? $product_types[$category_name] : ['Product'];
        $type = $types[array_rand($types)];
        
        // Get brands for this category, or use generic brands
        $category_brands = isset($brands[$category_name]) ? $brands[$category_name] : ['Generic Brand'];
        $brand = $category_brands[array_rand($category_brands)];
        
        // Generate product details
        $sku = strtoupper(substr($category_name, 0, 3)) . "-" . rand(10000, 99999);
        $name = $brand . " " . $type . " " . rand(100, 999);
        $description = "This is a high-quality $type from $brand. Perfect for any occasion, featuring modern design and excellent craftsmanship.";
        $price = rand(999, 19999) / 100; // Random price between 9.99 and 199.99
        $stock = rand(0, 100);
        
        // Check if product already exists
        $product_exists = $db->fetchOneWithParams(
            "SELECT COUNT(*) as count FROM products WHERE sku = ?",
            "s",
            [$sku]
        );
        
        if ($product_exists['count'] == 0) {
            // Insert product
            $db->executeWithParams(
                "INSERT INTO products (sku, name, description, price, stock) VALUES (?, ?, ?, ?, ?)",
                "sssdi", 
                [$sku, $name, $description, $price, $stock]
            );
            
            // Link product to category
            $db->executeWithParams(
                "INSERT INTO product_categories (product_sku, category_id) VALUES (?, ?)",
                "si", 
                [$sku, $category['id']]
            );
            
            // Add product images (placeholders)
            $db->executeWithParams(
                "INSERT INTO product_images (product_sku, image_path, is_primary) VALUES (?, ?, ?)",
                "ssi", 
                [$sku, "placeholder.jpg", 1]
            );
            
            // Randomly add additional images
            $additional_images = rand(0, 3);
            for ($j = 1; $j <= $additional_images; $j++) {
                $db->executeWithParams(
                    "INSERT INTO product_images (product_sku, image_path, is_primary) VALUES (?, ?, ?)",
                    "ssi", 
                    [$sku, "placeholder$j.jpg", 0]
                );
            }
            
            // Add sizes and colors based on category
            if (isset($category_sizes[$category_name])) {
                $applicable_sizes = [];
                foreach ($sizes as $size) {
                    if (in_array($size['name'], $category_sizes[$category_name])) {
                        $applicable_sizes[] = $size;
                    }
                }
                
                // If no applicable sizes found, use all sizes
                if (empty($applicable_sizes)) {
                    $applicable_sizes = $sizes;
                }
                
                // Add 2-4 random sizes
                $size_count = min(rand(2, 4), count($applicable_sizes));
                $selected_sizes = array_rand(array_flip(array_column($applicable_sizes, 'name')), $size_count);
                
                // Convert to array if only one size selected
                if (!is_array($selected_sizes)) {
                    $selected_sizes = [$selected_sizes];
                }
                
                foreach ($selected_sizes as $size_name) {
                    $size_stock = rand(0, 20);
                    $db->executeWithParams(
                        "INSERT INTO product_sizes (product_sku, size_name, stock) VALUES (?, ?, ?)",
                        "ssi", 
                        [$sku, $size_name, $size_stock]
                    );
                }
                
                // Add 2-4 random colors
                $color_count = min(rand(2, 4), count($colors));
                $selected_colors = array_rand(array_flip(array_column($colors, 'name')), $color_count);
                
                // Convert to array if only one color selected
                if (!is_array($selected_colors)) {
                    $selected_colors = [$selected_colors];
                }
                
                foreach ($selected_colors as $color_name) {
                    $color_stock = rand(0, 20);
                    $db->executeWithParams(
                        "INSERT INTO product_colors (product_sku, color_name, stock) VALUES (?, ?, ?)",
                        "ssi", 
                        [$sku, $color_name, $color_stock]
                    );
                }
            }
            
            $created++;
        }
    }
    
    log_message("Created $created new products", "INFO");
    return $created;
}

// ============================
// ====== SEED ORDERS =========
// ============================
function seed_orders($count = 20) {
    global $db;
    log_message("Starting to seed orders...", "INFO");
    
    // Get users and products
    $users = $db->fetchAll("SELECT id, shipping_address FROM users WHERE role = 'customer'");
    $products = $db->fetchAll("SELECT sku, price FROM products");
    
    if (empty($users) || empty($products)) {
        log_message("Missing users or products. Please seed those first.", "ERROR");
        return 0;
    }
    
    // Order statuses
    $statuses = ['pending', 'confirmed', 'canceled', 'refunded'];
    
    // Create orders
    $created = 0;
    for ($i = 1; $i <= $count; $i++) {
        // Randomly select a user
        $user = $users[array_rand($users)];
        $user_id = $user['id'];
        $shipping_address = $user['shipping_address'];
        
        // Generate random date within the last 30 days
        $days_ago = rand(0, 30);
        $date = date('Y-m-d H:i:s', strtotime("-$days_ago days"));
        
        // Random status with higher probability for 'confirmed'
        $status_rand = rand(1, 10);
        if ($status_rand <= 6) {
            $status = 'confirmed';
        } else if ($status_rand <= 8) {
            $status = 'pending';
        } else if ($status_rand <= 9) {
            $status = 'canceled';
        } else {
            $status = 'refunded';
        }
        
        // Insert order
        $db->executeWithParams(
            "INSERT INTO orders (user_id, date, status, shipping_address) VALUES (?, ?, ?, ?)",
            "isss", 
            [$user_id, $date, $status, $shipping_address]
        );
        
        $order_id = $db->getLastId();
        
        // Add 1-5 random products to the order
        $item_count = rand(1, 5);
        $selected_products = array_rand($products, min($item_count, count($products)));
        
        // Convert to array if only one product selected
        if (!is_array($selected_products)) {
            $selected_products = [$selected_products];
        }
        
        foreach ($selected_products as $product_index) {
            $product = $products[$product_index];
            $product_sku = $product['sku'];
            $price = $product['price'];
            $quantity = rand(1, 3);
            
            // Get random size and color if available
            $size_result = $db->fetchOneWithParams(
                "SELECT size_name FROM product_sizes WHERE product_sku = ? ORDER BY RAND() LIMIT 1",
                "s",
                [$product_sku]
            );
            $size_name = $size_result ? $size_result['size_name'] : null;
            
            $color_result = $db->fetchOneWithParams(
                "SELECT color_name FROM product_colors WHERE product_sku = ? ORDER BY RAND() LIMIT 1",
                "s",
                [$product_sku]
            );
            $color_name = $color_result ? $color_result['color_name'] : null;
            
            // Insert order item
            $db->executeWithParams(
                "INSERT INTO order_items (order_id, product_sku, size_name, color_name, quantity, price) VALUES (?, ?, ?, ?, ?, ?)",
                "isssid", 
                [$order_id, $product_sku, $size_name, $color_name, $quantity, $price]
            );
        }
        
        $created++;
    }
    
    log_message("Created $created new orders", "INFO");
    return $created;
}

// Run the seeders
try {
    $user_count = seed_users(20);
    $category_count = seed_categories();
    $size_count = seed_sizes();
    $color_count = seed_colors();
    $product_count = seed_products(50);
    $order_count = seed_orders(30);
    
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    log_message("Database seeding completed successfully in $execution_time seconds", "SUCCESS");
    log_message("Created: $user_count users, $category_count categories, $size_count sizes, $color_count colors, $product_count products, $order_count orders", "SUCCESS");
    
    echo "<h1>Database Seeding Completed</h1>";
    echo "<p>Execution time: $execution_time seconds</p>";
    echo "<p>Created: $user_count users, $category_count categories, $size_count sizes, $color_count colors, $product_count products, $order_count orders</p>";
    echo "<p>See log file for details: $log_file</p>";
    echo "<p><a href='admin/'>Go to Admin Panel</a></p>";
    
} catch (Exception $e) {
    log_message("Error during database seeding: " . $e->getMessage(), "ERROR");
    echo "<h1>Error During Database Seeding</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
