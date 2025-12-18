<?php
// Include the configuration file which defines connection details and the connectDB function
require_once 'config.php';

// Set headers for standard web output (if run via browser)
header("Content-Type: text/plain");

echo "--- Distributed Job Scheduler: Database Connection Test ---\n";

// Attempt to connect to the database using the defined function
$conn = connectDB();

// The connectDB function already handles a fatal error and JSON output if the connection fails,
// but we'll add a clear success message here.

if ($conn && $conn->ping()) {
    echo "\n[SUCCESS] Successfully connected to the MySQL database.\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Host: " . DB_SERVER . "\n";
    
    // Test a simple query to ensure tables are present
    $test_query = "SELECT COUNT(*) AS user_count FROM users";
    $result = $conn->query($test_query);
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Users table check: Found " . $row['user_count'] . " user(s).\n";
        $result->free();

        $test_query_2 = "SELECT COUNT(*) AS job_count FROM jobs";
        $result_2 = $conn->query($test_query_2);
        
        if ($result_2) {
            $row_2 = $result_2->fetch_assoc();
            echo "Jobs table check: Found " . $row_2['job_count'] . " job(s).\n";
            $result_2->free();
        } else {
             echo "\n[WARNING] Could not query 'jobs' table. Table may not exist or check database permissions.\n";
        }
    } else {
        echo "\n[ERROR] Failed to query 'users' table. Check if tables were imported correctly.\n";
    }

    // Close the connection
    $conn->close();
    echo "\nConnection closed.\n";

} else {
    // If we reach here, the script didn't exit in connectDB(), but the connection failed.
    echo "\n[FATAL ERROR] Failed to connect to the MySQL database. Check XAMPP MySQL status and config.php settings.\n";
}

echo "\n--------------------------------------------------------------\n";
?>