<?php
/**
 * Database connection class
 */
require_once 'config.php';

class Database {
    private $conn;
    private static $instance = null;
    
    /**
     * Constructor - connects to the database
     */
    private function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                $this->logError("Connection failed: " . $this->conn->connect_error);
                throw new Exception("Database connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            $this->logError("Connection exception: " . $e->getMessage());
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get database instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Execute query
     */
    public function query($sql) {
        try {
            $result = $this->conn->query($sql);
            if (!$result) {
                $this->logError("Query failed: " . $this->conn->error . " SQL: " . $sql);
            }
            return $result;
        } catch (Exception $e) {
            $this->logError("Query exception: " . $e->getMessage() . " SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * Execute prepared statement
     */
    public function prepare($sql) {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                $this->logError("Prepare failed: " . $this->conn->error . " SQL: " . $sql);
            }
            return $stmt;
        } catch (Exception $e) {
            $this->logError("Prepare exception: " . $e->getMessage() . " SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * Get last inserted ID
     */
    public function getLastId() {
        return $this->conn->insert_id;
    }
    
    /**
     * Escape string
     */
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->conn->rollback();
    }
    
    /**
     * Log errors to file
     */
    private function logError($message) {
        $logFile = ROOT_PATH . '/logs/db_errors.log';
        $dir = dirname($logFile);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        // Write to log file
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Close connection
     */
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * Fetch single row from query
     * 
     * @param string $sql SQL query
     * @return array|null Single row as associative array or null if not found
     */
    public function fetchOne($sql) {
        try {
            $result = $this->query($sql);
            if (!$result) {
                return null;
            }
            
            $row = $result->fetch_assoc();
            $result->free();
            return $row;
        } catch (Exception $e) {
            $this->logError("FetchOne exception: " . $e->getMessage() . " SQL: " . $sql);
            return null;
        }
    }
    
    /**
     * Fetch all rows from query
     * 
     * @param string $sql SQL query
     * @return array Array of rows as associative arrays
     */
    public function fetchAll($sql) {
        try {
            $result = $this->query($sql);
            if (!$result) {
                return [];
            }
            
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            
            $result->free();
            return $rows;
        } catch (Exception $e) {
            $this->logError("FetchAll exception: " . $e->getMessage() . " SQL: " . $sql);
            return [];
        }
    }
    
    /**
     * Fetch single row using prepared statement
     * 
     * @param string $sql SQL query with placeholders
     * @param string $types Types of parameters (s: string, i: integer, d: double, b: blob)
     * @param array $params Array of parameters
     * @return array|null Single row as associative array or null if not found
     */
    public function fetchOneWithParams($sql, $types, $params) {
        try {
            $stmt = $this->prepare($sql);
            if (!$stmt) {
                return null;
            }
            
            // Bind parameters
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result) {
                $stmt->close();
                return null;
            }
            
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        } catch (Exception $e) {
            $this->logError("FetchOneWithParams exception: " . $e->getMessage() . " SQL: " . $sql);
            return null;
        }
    }
    
    /**
     * Fetch all rows using prepared statement
     * 
     * @param string $sql SQL query with placeholders
     * @param string $types Types of parameters (s: string, i: integer, d: double, b: blob)
     * @param array $params Array of parameters
     * @return array Array of rows as associative arrays
     */
    public function fetchAllWithParams($sql, $types, $params) {
        try {
            $stmt = $this->prepare($sql);
            if (!$stmt) {
                return [];
            }
            
            // Bind parameters
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result) {
                $stmt->close();
                return [];
            }
            
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            
            $stmt->close();
            return $rows;
        } catch (Exception $e) {
            $this->logError("FetchAllWithParams exception: " . $e->getMessage() . " SQL: " . $sql);
            return [];
        }
    }
    
    /**
     * Execute statement with parameters and return affected rows
     * 
     * @param string $sql SQL query with placeholders
     * @param string $types Types of parameters (s: string, i: integer, d: double, b: blob)
     * @param array $params Array of parameters
     * @return int|bool Number of affected rows or false on failure
     */
    public function executeWithParams($sql, $types, $params) {
        try {
            $stmt = $this->prepare($sql);
            if (!$stmt) {
                return false;
            }
            
            // Bind parameters
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            return $affectedRows;
        } catch (Exception $e) {
            $this->logError("ExecuteWithParams exception: " . $e->getMessage() . " SQL: " . $sql);
            return false;
        }
    }
}
