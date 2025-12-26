<?php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth();
    $user = $auth->requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $required = ['title', 'description', 'priority', 'job_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            json_response(false, null, "Missing required field: $field", 400);
        }
    }
    
    // Validate priority
    $validPriorities = ['low', 'medium', 'high'];
    if (!in_array($data['priority'], $validPriorities)) {
        $data['priority'] = 'medium';
    }
    
    // Validate job type
    $validTypes = ['simulation', 'data_process', 'report', 'batch'];
    if (!in_array($data['job_type'], $validTypes)) {
        $data['job_type'] = 'simulation';
    }
    
    // Insert job
    $db = Database::getInstance();
    $sql = "INSERT INTO jobs (user_id, title, description, priority, status, job_type, max_retries) 
            VALUES (?, ?, ?, ?, 'pending', ?, ?)";
    
    $maxRetries = isset($data['max_retries']) ? (int)$data['max_retries'] : 3;
    
    $result = $db->query($sql, [
        $user['user_id'],
        trim($data['title']),
        trim($data['description']),
        $data['priority'],
        $data['job_type'],
        $maxRetries
    ]);
    
    if ($result) {
        $jobId = $db->getLastInsertId();
        
        // Log job creation
        $logSql = "INSERT INTO job_logs (job_id, status, message, executed_by) 
                   VALUES (?, 'pending', 'Job submitted', ?)";
        $db->query($logSql, [$jobId, $user['user_id']]);
        
        json_response(true, [
            'job_id' => $jobId,
            'message' => 'Job submitted successfully'
        ], 'Job created');
    } else {
        json_response(false, null, 'Failed to submit job', 500);
    }
} else {
    json_response(false, null, 'Method not allowed', 405);
}
?>