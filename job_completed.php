<?php
// server/api/job_completed.php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

try {
    $job_id = intval($_POST['job_id'] ?? 0);
    $exit_code = intval($_POST['exit_code'] ?? 0);
    $output = $_POST['output'] ?? '';
    $worker_node = $_POST['worker_node'] ?? '';
    
    if (!$job_id) {
        throw new Exception('Job ID is required');
    }
    
    $db = Database::getInstance();
    
    // Determine job status based on exit code
    $status = ($exit_code == 0) ? 'completed' : 'failed';
    
    // Update job
    $db->query(
        "UPDATE jobs SET 
            status = ?,
            completed_at = NOW(),
            exit_code = ?,
            output_log = ?
         WHERE id = ?",
        [$status, $exit_code, $output, $job_id]
    );
    
    // Free up worker resources
    if (!empty($worker_node)) {
        // Get job memory requirements first
        $job = $db->query(
            "SELECT memory_requirements FROM jobs WHERE id = ?",
            [$job_id]
        )->fetch();
        
        $memory = $job['memory_requirements'] ?? 0;
        
        $db->query(
            "UPDATE worker_nodes SET 
                active_jobs = GREATEST(0, active_jobs - 1),
                available_memory = available_memory + ?
             WHERE node_name = ?",
            [$memory, $worker_node]
        );
    }
    
    // Add to job history
    $status_message = ($exit_code == 0) ? 'Job completed successfully' : "Job failed with exit code $exit_code";
    $db->query(
        "INSERT INTO job_history (job_id, status, message) 
         VALUES (?, ?, ?)",
        [$job_id, $status, $status_message]
    );
    
    echo json_encode([
        'success' => true,
        'message' => "Job $job_id marked as $status"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>