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

class JobUpdater {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function updateJob($jobId, $updates, $userId, $isAdmin) {
        // Verify job exists and user has permission
        if (!$isAdmin) {
            $verifySql = "SELECT job_id, status FROM jobs WHERE job_id = ? AND user_id = ?";
            $verifyResult = $this->db->query($verifySql, [$jobId, $userId]);
            
            if (!$verifyResult || $verifyResult->num_rows === 0) {
                return ['success' => false, 'message' => 'Job not found or access denied'];
            }
        } else {
            $verifySql = "SELECT job_id, status FROM jobs WHERE job_id = ?";
            $verifyResult = $this->db->query($verifySql, [$jobId]);
            
            if (!$verifyResult || $verifyResult->num_rows === 0) {
                return ['success' => false, 'message' => 'Job not found'];
            }
        }
        
        $job = $verifyResult->fetch_assoc();
        $currentStatus = $job['status'];
        
        // Validate update based on current status and user role
        $validation = $this->validateUpdate($currentStatus, $updates, $isAdmin);
        
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            $updateFields = [];
            $updateValues = [];
            $updateTypes = '';
            
            // Build update query
            foreach ($updates as $field => $value) {
                if (in_array($field, ['priority', 'title', 'description', 'status'])) {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $value;
                    $updateTypes .= $this->getParamType($value);
                }
            }
            
            // Add updated timestamp
            $updateFields[] = 'last_updated = NOW()';
            
            // For status changes, add specific handling
            if (isset($updates['status'])) {
                $this->handleStatusChange($jobId, $currentStatus, $updates['status'], $updates['message'] ?? null);
            }
            
            // Execute update
            $sql = "UPDATE jobs SET " . implode(', ', $updateFields) . " WHERE job_id = ?";
            $updateValues[] = $jobId;
            $updateTypes .= 'i';
            
            $result = $this->db->query($sql, $updateValues);
            
            if (!$result) {
                throw new Exception("Failed to update job");
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Log the update
            $this->logUpdate($jobId, $updates, $userId);
            
            // Trigger scheduler if needed
            if (isset($updates['status']) && $updates['status'] === 'pending') {
                $this->triggerScheduler();
            }
            
            return [
                'success' => true,
                'message' => 'Job updated successfully',
                'job_id' => $jobId,
                'updates' => $updates
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Job update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    private function validateUpdate($currentStatus, $updates, $isAdmin) {
        // Non-admin users have limited update capabilities
        if (!$isAdmin) {
            $allowedFields = ['title', 'description'];
            $updatingFields = array_keys($updates);
            
            foreach ($updatingFields as $field) {
                if (!in_array($field, $allowedFields)) {
                    return [
                        'valid' => false,
                        'message' => 'You can only update title and description'
                    ];
                }
            }
            
            // Users can only update pending jobs
            if ($currentStatus !== 'pending') {
                return [
                    'valid' => false,
                    'message' => 'Only pending jobs can be modified'
                ];
            }
            
            return ['valid' => true];
        }
        
        // Admin validation
        if (isset($updates['status'])) {
            $newStatus = $updates['status'];
            $allowedTransitions = [
                'pending' => ['running', 'cancelled'],
                'running' => ['completed', 'failed', 'cancelled'],
                'failed' => ['pending', 'cancelled'],
                'cancelled' => []  // Cannot change from cancelled
            ];
            
            if (!isset($allowedTransitions[$currentStatus]) || 
                !in_array($newStatus, $allowedTransitions[$currentStatus])) {
                return [
                    'valid' => false,
                    'message' => "Invalid status transition from $currentStatus to $newStatus"
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    private function handleStatusChange($jobId, $oldStatus, $newStatus, $message = null) {
        $actions = [];
        
        switch ($newStatus) {
            case 'running':
                $actions[] = "start_time = NOW()";
                $logMessage = $message ?: 'Job execution started manually';
                break;
                
            case 'completed':
                $actions[] = "end_time = NOW()";
                $actions[] = "execution_time = TIMESTAMPDIFF(SECOND, start_time, NOW())";
                $logMessage = $message ?: 'Job marked as completed manually';
                break;
                
            case 'failed':
                $actions[] = "end_time = NOW()";
                $logMessage = $message ?: 'Job marked as failed manually';
                break;
                
            case 'cancelled':
                $actions[] = "end_time = NOW()";
                $logMessage = $message ?: 'Job cancelled by administrator';
                break;
                
            case 'pending':
                // Reset timestamps for retry
                $actions[] = "start_time = NULL";
                $actions[] = "end_time = NULL";
                $actions[] = "retry_count = retry_count + 1";
                $logMessage = $message ?: 'Job queued for retry';
                break;
                
            default:
                $logMessage = $message ?: "Status changed from $oldStatus to $newStatus";
        }
        
        // Execute actions if any
        if (!empty($actions)) {
            $sql = "UPDATE jobs SET " . implode(', ', $actions) . " WHERE job_id = ?";
            $this->db->query($sql, [$jobId]);
        }
        
        // Add to job logs
        $logSql = "INSERT INTO job_logs (job_id, status, message) VALUES (?, ?, ?)";
        $this->db->query($logSql, [$jobId, $newStatus, $logMessage]);
    }
    
    private function getParamType($value) {
        if (is_int($value)) return 'i';
        if (is_float($value)) return 'd';
        if (is_string($value)) return 's';
        return 'b';
    }
    
    private function logUpdate($jobId, $updates, $userId) {
        $updateLog = [
            'job_id' => $jobId,
            'updated_by' => $userId,
            'updates' => $updates,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // In a production system, you might want to log to a separate audit table
        error_log("Job update: " . json_encode($updateLog));
    }
    
    private function triggerScheduler() {
        // Trigger scheduler asynchronously
        $url = 'http://localhost/distributed-job-scheduler/server/api/scheduler.php';
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1
            ]
        ]);
        
        @file_get_contents($url, false, $context);
    }
    
    public function cancelJob($jobId, $userId, $isAdmin) {
        // Verify job exists and is running
        $sql = "SELECT job_id, status, user_id FROM jobs WHERE job_id = ?";
        $result = $this->db->query($sql, [$jobId]);
        
        if (!$result || $result->num_rows === 0) {
            return ['success' => false, 'message' => 'Job not found'];
        }
        
        $job = $result->fetch_assoc();
        
        // Check permissions
        if (!$isAdmin && $job['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Access denied'];
        }
        
        // Check if job can be cancelled
        if (!in_array($job['status'], ['pending', 'running'])) {
            return ['success' => false, 'message' => "Job cannot be cancelled in {$job['status']} status"];
        }
        
        // Update job status
        $updateSql = "UPDATE jobs SET status = 'cancelled', end_time = NOW() WHERE job_id = ?";
        $this->db->query($updateSql, [$jobId]);
        
        // Add log entry
        $logSql = "INSERT INTO job_logs (job_id, status, message) VALUES (?, 'cancelled', 'Job cancelled by user')";
        $this->db->query($logSql, [$jobId]);
        
        return [
            'success' => true,
            'message' => 'Job cancelled successfully',
            'job_id' => $jobId
        ];
    }
    
    public function retryJob($jobId, $userId, $isAdmin) {
        // Verify job exists and has failed
        $sql = "SELECT job_id, status, user_id, retry_count, max_retries 
                FROM jobs WHERE job_id = ?";
        $result = $this->db->query($sql, [$jobId]);
        
        if (!$result || $result->num_rows === 0) {
            return ['success' => false, 'message' => 'Job not found'];
        }
        
        $job = $result->fetch_assoc();
        
        // Check permissions
        if (!$isAdmin && $job['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Access denied'];
        }
        
        // Check if job can be retried
        if ($job['status'] !== 'failed') {
            return ['success' => false, 'message' => 'Only failed jobs can be retried'];
        }
        
        if ($job['retry_count'] >= $job['max_retries']) {
            return ['success' => false, 'message' => 'Maximum retry limit reached'];
        }
        
        // Reset job for retry
        $updateSql = "UPDATE jobs 
                      SET status = 'pending', 
                          start_time = NULL, 
                          end_time = NULL,
                          retry_count = retry_count + 1
                      WHERE job_id = ?";
        $this->db->query($updateSql, [$jobId]);
        
        // Add log entry
        $logSql = "INSERT INTO job_logs (job_id, status, message) VALUES (?, 'pending', 'Job queued for retry')";
        $this->db->query($logSql, [$jobId]);
        
        // Trigger scheduler
        $this->triggerScheduler();
        
        return [
            'success' => true,
            'message' => 'Job queued for retry',
            'job_id' => $jobId,
            'retry_count' => $job['retry_count'] + 1
        ];
    }
    
    public function updatePriority($jobId, $priority, $userId, $isAdmin) {
        // Validate priority
        if (!in_array($priority, ['low', 'medium', 'high'])) {
            return ['success' => false, 'message' => 'Invalid priority level'];
        }
        
        // Verify job exists and user has permission
        if (!$isAdmin) {
            $verifySql = "SELECT job_id, status FROM jobs WHERE job_id = ? AND user_id = ?";
            $verifyResult = $this->db->query($verifySql, [$jobId, $userId]);
            
            if (!$verifyResult || $verifyResult->num_rows === 0) {
                return ['success' => false, 'message' => 'Job not found or access denied'];
            }
        } else {
            $verifySql = "SELECT job_id, status FROM jobs WHERE job_id = ?";
            $verifyResult = $this->db->query($verifySql, [$jobId]);
            
            if (!$verifyResult || $verifyResult->num_rows === 0) {
                return ['success' => false, 'message' => 'Job not found'];
            }
        }
        
        $job = $verifyResult->fetch_assoc();
        
        // Only allow priority changes for pending jobs
        if ($job['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Priority can only be changed for pending jobs'];
        }
        
        // Update priority
        $updateSql = "UPDATE jobs SET priority = ? WHERE job_id = ?";
        $this->db->query($updateSql, [$priority, $jobId]);
        
        // Add log entry
        $logSql = "INSERT INTO job_logs (job_id, status, message) VALUES (?, 'priority_changed', 'Priority changed to $priority')";
        $this->db->query($logSql, [$jobId]);
        
        return [
            'success' => true,
            'message' => 'Job priority updated',
            'job_id' => $jobId,
            'new_priority' => $priority
        ];
    }
}

// API Endpoint Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $jobId = $input['job_id'] ?? 0;
    $action = $input['action'] ?? 'update';
    
    if (!$jobId) {
        http_response_code(400);
        echo json_encode(['error' => 'Job ID is required']);
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    $isAdmin = $_SESSION['role'] === 'admin';
    $updater = new JobUpdater();
    
    switch ($action) {
        case 'update':
            $updates = $input['updates'] ?? [];
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No updates provided']);
                exit();
            }
            
            $response = $updater->updateJob($jobId, $updates, $userId, $isAdmin);
            echo json_encode($response);
            break;
            
        case 'cancel':
            $response = $updater->cancelJob($jobId, $userId, $isAdmin);
            echo json_encode($response);
            break;
            
        case 'retry':
            $response = $updater->retryJob($jobId, $userId, $isAdmin);
            echo json_encode($response);
            break;
            
        case 'priority':
            $priority = $input['priority'] ?? '';
            if (!$priority) {
                http_response_code(400);
                echo json_encode(['error' => 'Priority is required']);
                exit();
            }
            
            $response = $updater->updatePriority($jobId, $priority, $userId, $isAdmin);
            echo json_encode($response);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>