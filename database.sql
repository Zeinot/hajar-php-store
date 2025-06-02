-- E-commerce Website Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS ecommerce_db;
USE ecommerce_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    shipping_address TEXT,
    status TINYINT NOT NULL DEFAULT 1,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sizes table
CREATE TABLE IF NOT EXISTS sizes (
    name VARCHAR(50) PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Colors table
CREATE TABLE IF NOT EXISTS colors (
    name VARCHAR(50) PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    sku VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Product images table
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_sku VARCHAR(50) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE
);

-- Product categories junction table
CREATE TABLE IF NOT EXISTS product_categories (
    product_sku VARCHAR(50) NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_sku, category_id),
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Product sizes junction table
CREATE TABLE IF NOT EXISTS product_sizes (
    product_sku VARCHAR(50) NOT NULL,
    size_name VARCHAR(50) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    PRIMARY KEY (product_sku, size_name),
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (size_name) REFERENCES sizes(name) ON DELETE CASCADE
);

-- Product colors junction table
CREATE TABLE IF NOT EXISTS product_colors (
    product_sku VARCHAR(50) NOT NULL,
    color_name VARCHAR(50) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    PRIMARY KEY (product_sku, color_name),
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    FOREIGN KEY (color_name) REFERENCES colors(name) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'confirmed', 'canceled', 'refunded') NOT NULL DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_sku VARCHAR(50) NOT NULL,
    size_name VARCHAR(50),
    color_name VARCHAR(50),
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE RESTRICT,
    FOREIGN KEY (size_name) REFERENCES sizes(name) ON DELETE RESTRICT,
    FOREIGN KEY (color_name) REFERENCES colors(name) ON DELETE RESTRICT
);

-- Trigger to update order total based on order items
DELIMITER //
CREATE TRIGGER calculate_order_total
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders
    SET total = (
        SELECT SUM(price * quantity)
        FROM order_items
        WHERE order_id = NEW.order_id
    )
    WHERE id = NEW.order_id;
END//

CREATE TRIGGER update_order_total
AFTER UPDATE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders
    SET total = (
        SELECT SUM(price * quantity)
        FROM order_items
        WHERE order_id = NEW.order_id
    )
    WHERE id = NEW.order_id;
END//

CREATE TRIGGER delete_order_total
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders
    SET total = (
        SELECT SUM(price * quantity)
        FROM order_items
        WHERE order_id = OLD.order_id
    )
    WHERE id = OLD.order_id;
END//
DELIMITER ;

-- Insert default admin user (password: admin123)
INSERT INTO users (full_name, email, password_hash, status, role)
VALUES ('Admin User', 'admin@example.com', '$2y$10$1qAz2wSx3eDc4rFv5tDaFud3Qm4JBbiP9RaC5bInLVJUZXG5fNTim', 1, 'admin');

-- Insert some sample categories
INSERT INTO categories (name, description) VALUES 
('Clothing', 'All types of clothing items'),
('Footwear', 'Shoes, sandals, and boots'),
('Accessories', 'Belts, hats, and jewelry');

-- Insert some sample sizes
INSERT INTO sizes (name) VALUES 
('XS'), ('S'), ('M'), ('L'), ('XL'), ('XXL'),
('35'), ('36'), ('37'), ('38'), ('39'), ('40'), ('41'), ('42'), ('43'), ('44'), ('45');

-- Insert some sample colors
INSERT INTO colors (name) VALUES 
('Red'), ('Green'), ('Blue'), ('Black'), ('White'), ('Yellow'), ('Purple'), ('Orange'), ('Gray'), ('Brown');
