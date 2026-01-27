<?php
/**
 * Oracle Database Connection Configuration
 * Database: serena_sactuary
 * Service Name: FREEPDB1
 */

// Database connection parameters
define('DB_USERNAME', 'homestay');
define('DB_PASSWORD', 'password');
define('DB_HOST', '85.211.253.234');  // Database host (use IP or hostname if remote)
define('DB_PORT', '1623');       // Oracle database port (default: 1521)
define('DB_SERVICE_NAME', 'FREEPDB1');

/**
 * Get Oracle database connection
 * @return resource|false Returns connection resource on success, false on failure
 */
function getDBConnection() {
    // Use Easy Connect format: //host:port/service_name
    // This format works with Oracle Instant Client without requiring tnsnames.ora
    $connection_string = '//' . DB_HOST . ':' . DB_PORT . '/' . DB_SERVICE_NAME;
    
    // Attempt to connect to Oracle database using Easy Connect format
    $conn = oci_connect(DB_USERNAME, DB_PASSWORD, $connection_string);
    
    if (!$conn) {
        $error = oci_error();
        error_log("Oracle Connection Error: " . $error['message']);
        return false;
    }
    
    return $conn;
}

/**
 * Close database connection
 * @param resource $conn Oracle connection resource
 */
function closeDBConnection($conn) {
    if ($conn) {
        oci_close($conn);
    }
}