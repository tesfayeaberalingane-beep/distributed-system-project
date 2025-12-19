<?php

require_once 'config.php';
$conn = connectDB();

echo "--- Watchdog Monitor Started: " . date('Y-m-d H:i:s') . " ---\n";

// F12: Find jobs where current time > started_at + max_runtime
$sql = "SELECT job_id, started_at, max_runtime FROM jobs 
        WHERE status = 'running' 
        AND started_at IS NOT NULL 
        AND TIMESTAMPDIFF(SECOND, started_at, NOW()) > max_runtime";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($job = $result->fetch_assoc()) {
        $id = $job['job_id'];
        
        // Mark as TIMEOUT
        $update = $conn->prepare("UPDATE jobs SET status = 'timeout', result = 'Exceeded maximum allowed runtime.' WHERE job_id = ?");
        $update->bind_param("i", $id);
        
        if ($update->execute()) {
            echo "[TIMEOUT] Job #$id has been killed.\n";
            // Log to Audit Trail
            if (function_exists('logJobStatusChange')) {
                logJobStatusChange($id, 'running', 'timeout', 'Killed by Watchdog Monitor.');
            }
        }
    }
} else {
    echo "No timed-out jobs found.\n";
}

$conn->close();

/*
require_once 'config.php';

$conn = connectDB();

echo "--- Watchdog Monitor Started: " . date('Y-m-d H:i:s') . " ---\n";

// Find jobs that have exceeded their runtime
$query = "SELECT job_id, started_at, max_runtime FROM jobs 
          WHERE status = 'running' 
          AND started_at IS NOT NULL";

$result = $conn->query($query);

while ($job = $result->fetch_assoc()) {
    $job_id = $job['job_id'];
    $start_time = strtotime($job['started_at']);
    $max_seconds = (int)$job['max_runtime'];
    $current_time = time();
    
    $elapsed = $current_time - $start_time;

    if ($elapsed > $max_seconds) {
        echo "Alert: Job #$job_id exceeded limit ($elapsed/{$max_seconds}s). Timing out...\n";
        
        // Update job status to TIMEOUT
        $stmt = $conn->prepare("UPDATE jobs SET status = 'timeout', result = 'Execution exceeded max_runtime limit.' WHERE job_id = ?");
        $stmt->bind_param("i", $job_id);
        
        if ($stmt->execute()) {
            // Log the event in our audit trail
            logJobStatusChange($job_id, 'RUNNING', 'TIMEOUT', "Job killed automatically after $elapsed seconds.");
        }
        $stmt->close();
    }
}

$conn->close();
echo "--- Monitor Cycle Complete ---\n";*/
?>