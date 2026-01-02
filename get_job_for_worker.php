<?php
// server/api/get_job_for_worker.php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

try {
    $node_name = $_GET['node'] ?? '';
    
    if (empty($node_name)) {
        throw new Exception('Node name is required');
    }
    
    $db = Database::getInstance();
    
    // Get worker node info
    $worker = $db->query(
        "SELECT * FROM worker_nodes WHERE node_name = ?",
        [$node_name]
    )->fetch();
    
    if (!$worker) {
        throw new Exception('Worker node not found');
    }
    
    // Find a pending job that fits this worker's available resources
    $job = $db->query(
        "SELECT j.* FROM jobs j
         WHERE j.status = 'pending'
           AND j.cpu_requirements <= ?
           AND j.memory_requirements <= ?
         ORDER BY 
            CASE j.priority 
                WHEN 'high' THEN 1
                WHEN 'medium' THEN 2  
                WHEN 'low' THEN 3
            END,
            j.created_at
         LIMIT 1",
        [$worker['cpu_cores'] - $worker['active_jobs'], $worker['available_memory']]
    )->fetch();
    
    if ($job) {
        // Update job status to running
        $db->query(
            "UPDATE jobs SET 
                status = 'running',
                worker_node = ?,
                started_at = NOW()
             WHERE id = ?",
            [$node_name, $job['id']]
        );
        
        // Update worker stats
        $db->query(
            "UPDATE worker_nodes SET 
                active_jobs = active_jobs + 1,
                available_memory = available_memory - ?
             WHERE node_name = ?",
            [$job['memory_requirements'] ?? 0, $node_name]
        );
        
        // Add to job history
        $db->query(
            "INSERT INTO job_history (job_id, status, message) 
             VALUES (?, 'running', 'Started execution on $node_name')",
            [$job['id']]
        );
        
        echo json_encode([
            'success' => true,
            'job' => $job
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'job' => null,
            'message' => 'No jobs available'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>