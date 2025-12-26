<?php
require_once __DIR__ . '/../config/config.php';
require_once 'db.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $errors = [];
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $jobType = $input['job_type'] ?? 'other';
    $priority = $input['priority'] ?? 'medium';
    
    if (empty($title)) {
        $errors[] = 'Job title is required';
    }
    
    if (strlen($title) > 100) {
        $errors[] = 'Job title must be less than 100 characters';
    }
    
    if (!in_array($jobType, ['data_processing', 'report_generation', 'file_conversion', 'simulation', 'other'])) {
        $errors[] = 'Invalid job type';
    }
    
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        $errors[] = 'Invalid priority';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
        exit();
    }
    
    // Insert job
    $userId = $_SESSION['user_id'];
    $sql = "INSERT INTO jobs (user_id, title, description, job_type, priority, status, submit_time) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $userId, $title, $description, $jobType, $priority);
    
    if ($stmt->execute()) {
        $jobId = $conn->insert_id;
        
        // Insert job log
        $logSql = "INSERT INTO job_logs (job_id, status, message) VALUES (?, 'pending', 'Job submitted')";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("i", $jobId);
        $logStmt->execute();
        
        // Trigger scheduler
        file_get_contents('http://localhost/distributed-job-scheduler/server/api/scheduler.php?trigger=1');
        
        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'message' => 'Job submitted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit job']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>