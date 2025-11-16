<?php
require_once("cors-headers.php");
require_once("env-loader.php"); // Load environment variables
header('Content-Type: application/json');

// Load environment variables with proper defaults for local development
// These will be overridden by .env file if it exists
$servername = $_ENV['DB_HOST'] ?? 'localhost';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? ''; // Will be loaded from .env file
$database = $_ENV['DB_NAME'] ?? 'abc_medicos';

// Debug: Check if .env file was loaded
$envFileExists = file_exists(dirname(__DIR__) . '/.env');
error_log(".env file exists: " . ($envFileExists ? 'YES' : 'NO'));
error_log(".env file path: " . dirname(__DIR__) . '/.env');
if (isset($_ENV['DB_PASSWORD'])) {
    error_log("DB_PASSWORD loaded from .env: " . ($_ENV['DB_PASSWORD'] ? 'SET (value hidden)' : 'EMPTY'));
} else {
    error_log("DB_PASSWORD NOT in \$_ENV array - using default empty string");
}

// Log connection attempt (for debugging - remove in production)
error_log("Attempting DB connection: host=$servername, user=$username, database=$database, password=" . ($password ? 'SET' : 'EMPTY'));

try {
    $conn = new mysqli($servername, $username, $password, $database);
    
    if ($conn->connect_error) {
        $errorMsg = "Database connection failed: " . $conn->connect_error;
        error_log($errorMsg);
        
        // Provide helpful error message
        if (strpos($conn->connect_error, "Access denied") !== false) {
            $errorMsg .= "\n\nHint: Check your database credentials in .env file or config/db_connect.php";
            $errorMsg .= "\nCurrent settings: user='$username', password=" . ($password ? "'***'" : "'(empty)'");
        }
        
        die(json_encode([
            "status" => "error", 
            "message" => $errorMsg,
            "db_config" => [
                "host" => $servername,
                "username" => $username,
                "database" => $database,
                "password_set" => !empty($password)
            ]
        ]));
    }
    
    // Set charset to utf8mb4 for proper encoding
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    $conn->set_charset($charset);
    
    error_log("Database connection successful");
    
} catch (Exception $e) {
    error_log("Database connection exception: " . $e->getMessage());
    die(json_encode([
        "status" => "error",
        "message" => "Database connection exception: " . $e->getMessage()
    ]));
}
?>