<?php
/**
 * Database connection configuration for Elegant Drapes luxury clothing store
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'elegant_drapes');

// Attempt to connect to MySQL database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Create logs directory if it doesn't exist
    if (!is_dir(__DIR__ . "/../logs")) {
        mkdir(__DIR__ . "/../logs", 0755, true);
    }
    
    // Log the error
    error_log("Connection failed: " . $conn->connect_error, 3, __DIR__ . "/../logs/db_errors.log");
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
