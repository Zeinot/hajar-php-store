<?php
/**
 * Configuration file for e-commerce website
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_db');

// Site configuration
define('SITE_URL', 'http://localhost/');
define('SITE_NAME', 'E-Shop');
define('ADMIN_EMAIL', 'admin@example.com');

// Paths
define('ROOT_PATH', realpath(dirname(__FILE__) . '/../'));
define('UPLOAD_PRODUCT_PATH', ROOT_PATH . '/assets/uploads/products/');
define('UPLOAD_CATEGORY_PATH', ROOT_PATH . '/assets/uploads/categories/');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');
