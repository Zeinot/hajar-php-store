-- Elegant Drapes - Luxury Clothing Store Database Schema

-- Create the database
CREATE DATABASE IF NOT EXISTS elegant_drapes;
USE elegant_drapes;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    shipping_address TEXT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    role ENUM('customer', 'admin') DEFAULT 'customer',
    email_subscription BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(255) NULL,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Materials table (new table for clothing materials)
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    sku VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2) NULL,
    stock INT NOT NULL DEFAULT 0,
    category_id INT NOT NULL,
    material_id INT NULL,
    gender ENUM('Men', 'Women', 'Unisex') DEFAULT 'Unisex',
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE SET NULL
);

-- Product Images table
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_sku VARCHAR(50) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE
);

-- Sizes table
CREATE TABLE IF NOT EXISTS sizes (
    name VARCHAR(20) PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Colors table
CREATE TABLE IF NOT EXISTS colors (
    name VARCHAR(30) PRIMARY KEY,
    hex_code VARCHAR(7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Product sizes (many-to-many relationship)
CREATE TABLE IF NOT EXISTS product_sizes (
    product_sku VARCHAR(50) NOT NULL,
    size_name VARCHAR(20) NOT NULL,
    additional_price DECIMAL(10, 2) DEFAULT 0.00,
    PRIMARY KEY (product_sku, size_name),
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (size_name) REFERENCES sizes(name) ON DELETE CASCADE
);

-- Product colors (many-to-many relationship)
CREATE TABLE IF NOT EXISTS product_colors (
    product_sku VARCHAR(50) NOT NULL,
    color_name VARCHAR(30) NOT NULL,
    additional_price DECIMAL(10, 2) DEFAULT 0.00,
    PRIMARY KEY (product_sku, color_name),
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (color_name) REFERENCES colors(name) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'canceled', 'refunded') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    shipping_address TEXT NOT NULL,
    shipping_method ENUM('standard', 'express', 'overnight') DEFAULT 'standard',
    tracking_number VARCHAR(100) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_sku VARCHAR(50) NOT NULL,
    size_name VARCHAR(20) NULL,
    color_name VARCHAR(30) NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (size_name) REFERENCES sizes(name) ON DELETE SET NULL,
    FOREIGN KEY (color_name) REFERENCES colors(name) ON DELETE SET NULL
);

-- Trigger to calculate order total
DELIMITER //
CREATE TRIGGER calculate_order_total
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders
    SET total = (SELECT SUM(subtotal) FROM order_items WHERE order_id = NEW.order_id)
    WHERE id = NEW.order_id;
END//
DELIMITER ;

-- Trigger to update order total when order items are updated
DELIMITER //
CREATE TRIGGER update_order_total
AFTER UPDATE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders
    SET total = (SELECT SUM(subtotal) FROM order_items WHERE order_id = NEW.order_id)
    WHERE id = NEW.order_id;
END//
DELIMITER ;

-- Trigger to update order total when order items are deleted
DELIMITER //
CREATE TRIGGER delete_order_total
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders
    SET total = (SELECT SUM(subtotal) FROM order_items WHERE order_id = OLD.order_id)
    WHERE id = OLD.order_id;
END//
DELIMITER ;

-- Insert default admin user (password: admin123)
INSERT INTO users (full_name, email, password_hash, role) 
VALUES ('Admin User', 'admin@example.com', '$2y$10$YL.fBKFPz2YB5ZCFWrT2sO6oXpOC1S0VVDSbF9iMNDfPPKL1F3Bbu', 'admin');

-- Wishlist table
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_sku VARCHAR(50) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    UNIQUE (user_id, product_sku)
);

-- Product Features table
CREATE TABLE IF NOT EXISTS product_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Product Features mapping (many-to-many relationship)
CREATE TABLE IF NOT EXISTS product_has_features (
    product_sku VARCHAR(50) NOT NULL,
    feature_id INT NOT NULL,
    PRIMARY KEY (product_sku, feature_id),
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES product_features(id) ON DELETE CASCADE
);

-- Insert some sample categories
INSERT INTO categories (name, description, parent_id) VALUES 
('Robes', 'Luxury robes for ultimate comfort', NULL),
('Foulards', 'Designer scarves and foulards', NULL),
('Accessories', 'Fashion accessories to complete your look', NULL),
('Silk Robes', 'Premium silk robes for luxurious comfort', 1),
('Cotton Robes', 'Soft cotton robes for everyday luxury', 1),
('Cashmere Robes', 'Warm and cozy cashmere robes', 1),
('Silk Scarves', 'Elegant silk scarves and foulards', 2),
('Wool Scarves', 'Warm and stylish wool scarves', 2),
('Ties', 'Elegant neckties and bow ties', 3),
('Pocket Squares', 'Stylish pocket squares to complement any outfit', 3),
('Hair Accessories', 'Elegant hair accessories', 3);

-- Insert some sample materials
INSERT INTO materials (name, description) VALUES
('Silk', 'Luxurious and smooth natural fabric'),
('Cashmere', 'Exceptionally soft and warm natural fiber'),
('Cotton', 'Breathable and comfortable natural fabric'),
('Satin', 'Smooth, glossy fabric with a luxurious feel'),
('Velvet', 'Soft fabric with a plush feel and elegant appearance'),
('Wool', 'Natural, warm fiber with excellent insulation properties'),
('Linen', 'Light, breathable fabric perfect for warm weather');

-- Insert some sample features
INSERT INTO product_features (name, description) VALUES
('Handcrafted', 'Meticulously made by skilled artisans'),
('Eco-friendly', 'Made with sustainable materials and processes'),
('Limited Edition', 'Exclusive designs with limited availability'),
('Reversible', 'Can be worn on both sides'),
('Monogram Option', 'Can be personalized with custom monogram'),
('Gift Wrapped', 'Comes in our signature gift packaging'),
('Hypoallergenic', 'Suitable for sensitive skin');

-- Insert some sample sizes
INSERT INTO sizes (name) VALUES 
('XS'), ('S'), ('M'), ('L'), ('XL'), ('XXL'), ('One Size');

-- Insert some sample colors with hex codes
INSERT INTO colors (name, hex_code) VALUES 
('Navy Blue', '#0A1747'),
('Burgundy', '#800020'),
('Gold', '#D4AF37'),
('Champagne', '#F7E7CE'),
('Ivory', '#FFFFF0'),
('Charcoal', '#36454F'),
('Silver', '#C0C0C0'),
('Emerald', '#50C878'),
('Sapphire', '#0F52BA'),
('Black', '#000000'),
('White', '#FFFFFF'),
('Cream', '#FFFDD0'),
('Taupe', '#483C32');

-- Product Reviews table
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_sku VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT NULL,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (product_sku, user_id)
);

-- Newsletter table
CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    subscription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Newsletter Campaign table
CREATE TABLE IF NOT EXISTS newsletter_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    sent_date TIMESTAMP NULL,
    status ENUM('draft', 'scheduled', 'sent') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
