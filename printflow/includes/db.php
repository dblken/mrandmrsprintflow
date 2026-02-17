<?php
/**
 * Database Connection
 * PrintFlow - Printing Shop PWA
 */

// Database configuration - UPDATE THESE VALUES
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Change to your MySQL username
define('DB_PASS', '1234');               // Change to your MySQL password
define('DB_NAME', 'printflow_1');      // Database name

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

/**
 * Prepare and execute a SQL statement safely
 * @param string $sql SQL query with placeholders
 * @param string $types Parameter types (e.g., 'ssi' for string, string, integer)
 * @param array $params Array of parameters
 * @return mysqli_stmt|false
 */
function db_prepare($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Database prepare error: " . $conn->error);
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    return $stmt;
}

/**
 * Execute a query and return results as associative array
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameters
 * @return array|false
 */
function db_query($sql, $types = '', $params = []) {
    $stmt = db_prepare($sql, $types, $params);
    if (!$stmt) return false;
    
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        return false;
    }
    
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    $stmt->close();
    return $rows;
}

/**
 * Execute an INSERT/UPDATE/DELETE query
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameters
 * @return bool|int Returns true for success, false for failure, or last insert ID
 */
function db_execute($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = db_prepare($sql, $types, $params);
    if (!$stmt) return false;
    
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return false;
    }
    
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $insert_id > 0 ? $insert_id : true;
}

/**
 * Escape string for SQL queries (use prepared statements instead when possible)
 * @param string $str
 * @return string
 */
function db_escape($str) {
    global $conn;
    return $conn->real_escape_string($str);
}

/**
 * Close database connection
 */
function db_close() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}
