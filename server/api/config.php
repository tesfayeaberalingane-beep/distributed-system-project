<?php
date_default_timezone_set('UTC'); // Or your local timezone
// Configuration file for Database connection and other settings
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Default XAMPP username
define('DB_PASSWORD', '');     // Default XAMPP password (often blank)
define('DB_NAME', 'distributed_job');

// Function to establish database connection
function connectDB() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        die(json_encode(["success" => false, "message" => "Database connection failed: " . $conn->connect_error]));
    }
    return $conn;
}

// Ensure all API endpoints allow CORS for local development
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Start session management for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ... (Keep existing connectDB() and constants)

// New function to log job status changes
function logJobStatusChange($job_id, $old_status, $new_status, $message = NULL) {
    // Note: We create a new connection instance to avoid conflicts with main script's connection
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        // Log to server error log instead of dying
        error_log("Logging failed: Database connection failed for job_id " . $job_id);
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO job_log (job_id, old_status, new_status, message) VALUES (?, ?, ?, ?)"
    );

    // Use $message if provided, otherwise use a default message
    $log_message = $message ?? "Status changed from " . $old_status . " to " . $new_status;

    $stmt->bind_param("isss", $job_id, $old_status, $new_status, $log_message);

    $success = $stmt->execute();

    $stmt->close();
    $conn->close();
    return $success;
}

// ... (Rest of config.php, including session_start())
// ... (inside the success block after job insertion)
?>