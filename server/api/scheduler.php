<?php
// Note: This script is intended to be run via CLI/Cron, 
// so it does not rely on browser sessions.
require_once 'config.php';

// Simple function to simulate execution time and result
function simulateJobExecution($job_data) {
    // Determine complexity based on priority
    switch ($job_data['priority']) {
        case 'high':
            $duration = rand(1, 3); // Quick execution
            break;
        case 'medium':
            $duration = rand(3, 7);
            break;
        case 'low':
            $duration = rand(5, 10); // Longer execution
            break;
        default:
            $duration = 5;
    }
    
    // Simulate working
    sleep($duration); 
    
    // Simulate random failure/success (e.g., 80% success rate)
    $is_successful = rand(1, 100) <= 80;

    if ($is_successful) {
        $status = 'completed';
        $result = "Job '{$job_data['title']}' completed successfully in {$duration} seconds. Output: Processed {$job_data['description']}.";
    } else {
        $status = 'failed';
        $result = "Job '{$job_data['title']}' failed due to a simulated internal error. Time elapsed: {$duration} seconds.";
    }

    return ['status' => $status, 'result' => $result];
}

function runScheduler() {
    $conn = connectDB();
    
    // --- 1. SCHEDULING LOGIC: Find the next job ---
    // Priority-based scheduling: Select the oldest PENDING job with the highest priority.
    // Order by: 
    // 1. Priority (High > Medium > Low) 
    // 2. submit_time (FIFO for jobs with the same priority)
    $sql = "SELECT job_id, title, description, priority 
            FROM jobs 
            WHERE status = 'pending'
            ORDER BY FIELD(priority, 'high', 'medium', 'low'), submit_time ASC 
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result === false) {
        // Handle SQL error
        echo "Error retrieving job: " . $conn->error . "\n";
        $conn->close();
        return;
    }

    if ($result->num_rows === 0) {
        echo "No pending jobs found. Queue is empty.\n";
        $conn->close();
        return;
    }

    $job_to_run = $result->fetch_assoc();
    $job_id = $job_to_run['job_id'];

    echo "--- Selected Job ID: $job_id (Priority: {$job_to_run['priority']}) ---\n";

    // --- 2. EXECUTION PREP: Mark as 'running' ---
    // Update status to 'running' *before* execution to prevent multiple workers from grabbing it.
    // Note: We use a separate file/API to simulate a real-world scenario where the scheduler 
    // communicates status updates to a dedicated Job Manager.
    
    $update_url = 'http://localhost/distributed-jobs-scheduler/server/api/update_job.php'; // Update this if your path is different
    
    $client = curl_init($update_url);
    curl_setopt($client, CURLOPT_POST, 1);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    
    // Set status to RUNNING
    $data_running = json_encode(['job_id' => $job_id, 'status' => 'running']);
    curl_setopt($client, CURLOPT_POSTFIELDS, $data_running);
    $response_running = curl_exec($client);
    echo "API Response: " . $response_running . "\n"; 
    $update_success = json_decode($response_running, true)['success'] ?? false;
    //Added code logging for audit trail (Requirement F14 from previous step)
    if (function_exists('logJobStatusChange')) {
        logJobStatusChange($job_id, 'pending', 'running', 'Worker started execution via CLI.');
    }
    //addedgi
    $update_success = json_decode($response_running, true)['success'] ?? false;
    
    if (!$update_success) {
        echo "Failed to mark job $job_id as running. Aborting execution.\n";
        curl_close($client);
        $conn->close();
        return;
    }
    echo "Job $job_id marked as RUNNING.\n";

    // --- 3. EXECUTION: Simulate Work ---
    echo "Simulating execution for Job $job_id. Please wait...\n";
    $execution_result = simulateJobExecution($job_to_run);
    
    // --- 4. EXECUTION COMPLETION: Update final status and result ---
    $final_status = $execution_result['status'];
    $final_result = $execution_result['result'];

    // Set final status (COMPLETED or FAILED)
    $data_final = json_encode([
        'job_id' => $job_id, 
        'status' => $final_status, 
        'result' => $final_result
    ]);
    curl_setopt($client, CURLOPT_POSTFIELDS, $data_final);
    $response_final = curl_exec($client);

    echo "Job $job_id completed with status: $final_status.\n";
    // added F14 IMPLEMENTATION: Log final status to Audit Trail
    if (function_exists('logJobStatusChange')) {
        logJobStatusChange($job_id, 'running', $final_status, $final_result);
    }
    //added
    echo "Result: $final_result\n";

    curl_close($client);
    $conn->close();
}

// Ensure the script is called from CLI for continuous/cron execution
if (php_sapi_name() === 'cli') {
    runScheduler();
} else {
    // If accessed via web browser, output a message
    echo json_encode(["message" => "Scheduler worker script. Should be run via CLI/Cron."]);
}
?>