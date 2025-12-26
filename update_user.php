<?php
require_once __DIR__ . '/../config/config.php';
require_once 'db.php';

// Check authentication and admin role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - Admin access required']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

class UserUpdater {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function updateUser($userId, $updates, $adminId) {
        // Validate user exists
        $user = $this->getUser($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Cannot modify self (except certain fields)
        if ($userId == $adminId && isset($updates['role']) && $updates['role'] !== $user['role']) {
            return ['success' => false, 'message' => 'Cannot change your own role'];
        }
        
        if ($userId == $adminId && isset($updates['is_active']) && !$updates['is_active']) {
            return ['success' => false, 'message' => 'Cannot deactivate your own account'];
        }
        
        // Validate updates
        $validation = $this->validateUpdates($updates, $user);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            $updateFields = [];
            $updateValues = [];
            
            // Build update query
            foreach ($updates as $field => $value) {
                if ($this->isAllowedField($field)) {
                    // Handle password update specially
                    if ($field === 'password') {
                        if (!empty($value)) {
                            $hashedPassword = password_hash($value, PASSWORD_DEFAULT);
                            $updateFields[] = "password = ?";
                            $updateValues[] = $hashedPassword;
                        }
                        continue;
                    }
                    
                    $updateFields[] = "{$field} = ?";
                    $updateValues[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }
            
            // Add updated timestamp
            $updateFields[] = 'updated_at = NOW()';
            
            // Execute update
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
            $updateValues[] = $userId;
            
            $result = $this->db->query($sql, $updateValues);
            
            if (!$result) {
                throw new Exception("Failed to update user: " . $conn->error);
            }
            
            // Log the update
            $this->logUserUpdate($userId, $updates, $adminId, $user);
            
            // Handle special cases
            if (isset($updates['is_active']) && !$updates['is_active']) {
                $this->handleUserDeactivation($userId, $adminId);
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Get updated user data
            $updatedUser = $this->getUser($userId);
            unset($updatedUser['password']);
            unset($updatedUser['api_key']);
            
            return [
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $updatedUser
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("User update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    private function getUser($userId) {
        $sql = "SELECT * FROM users WHERE user_id = ?";
        $result = $this->db->query($sql, [$userId]);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    private function validateUpdates($updates, $currentUser) {
        $errors = [];
        
        foreach ($updates as $field => $value) {
            switch ($field) {
                case 'username':
                    if (strlen($value) < 3 || strlen($value) > 50) {
                        $errors[] = 'Username must be 3-50 characters';
                    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                        $errors[] = 'Username can only contain letters, numbers, and underscores';
                    } elseif ($value !== $currentUser['username']) {
                        // Check if username is taken
                        $checkSql = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
                        $checkResult = $this->db->query($checkSql, [$value, $currentUser['user_id']]);
                        if ($checkResult && $checkResult->num_rows > 0) {
                            $errors[] = 'Username already taken';
                        }
                    }
                    break;
                    
                case 'email':
                    if (!empty($value)) {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = 'Invalid email format';
                        } elseif (strlen($value) > 100) {
                            $errors[] = 'Email cannot exceed 100 characters';
                        } elseif ($value !== $currentUser['email']) {
                            // Check if email is taken
                            $checkSql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
                            $checkResult = $this->db->query($checkSql, [$value, $currentUser['user_id']]);
                            if ($checkResult && $checkResult->num_rows > 0) {
                                $errors[] = 'Email already registered';
                            }
                        }
                    }
                    break;
                    
                case 'password':
                    if (!empty($value) && strlen($value) < 8) {
                        $errors[] = 'Password must be at least 8 characters';
                    }
                    break;
                    
                case 'role':
                    if (!in_array($value, ['user', 'admin'])) {
                        $errors[] = 'Invalid role';
                    }
                    break;
                    
                case 'is_active':
                    if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false])) {
                        $errors[] = 'Invalid active status';
                    }
                    break;
                    
                case 'full_name':
                    if (!empty($value) && strlen($value) > 100) {
                        $errors[] = 'Full name cannot exceed 100 characters';
                    }
                    break;
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'message' => implode(', ', $errors)];
        }
        
        return ['valid' => true];
    }
    
    private function isAllowedField($field) {
        $allowedFields = [
            'username', 'email', 'password', 'full_name', 
            'role', 'is_active', 'api_key'
        ];
        
        return in_array($field, $allowedFields);
    }
    
    private function logUserUpdate($userId, $updates, $adminId, $oldUser) {
        $updateLog = [
            'user_id' => $userId,
            'updated_by' => $adminId,
            'updates' => $updates,
            'old_values' => array_intersect_key($oldUser, $updates),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Log to audit table if exists
        $auditSql = "INSERT INTO audit_log (table_name, record_id, action, old_values, new_values, changed_by) 
                    VALUES ('users', ?, 'UPDATE', ?, ?, ?)";
        
        $oldValuesJson = json_encode($updateLog['old_values']);
        $newValuesJson = json_encode($updates);
        
        $this->db->query($auditSql, [
            $userId,
            $oldValuesJson,
            $newValuesJson,
            $adminId
        ]);
    }
    
    private function handleUserDeactivation($userId, $adminId) {
        // Cancel all pending jobs for the user
        $cancelSql = "UPDATE jobs SET status = 'cancelled', end_time = NOW() 
                     WHERE user_id = ? AND status IN ('pending', 'running')";
        $this->db->query($cancelSql, [$userId]);
        
        // Log the cancellation
        $logSql = "INSERT INTO job_logs (job_id, status, message) 
                  SELECT job_id, 'cancelled', 'Job cancelled due to user deactivation' 
                  FROM jobs WHERE user_id = ? AND status IN ('pending', 'running')";
        $this->db->query($logSql, [$userId]);
        
        // Generate new API key to invalidate existing tokens
        $newApiKey = bin2hex(random_bytes(32));
        $apiKeySql = "UPDATE users SET api_key = ? WHERE user_id = ?";
        $this->db->query($apiKeySql, [$newApiKey, $userId]);
        
        // Log deactivation
        $deactivationLog = [
            'user_id' => $userId,
            'deactivated_by' => $adminId,
            'timestamp' => date('Y-m-d H:i:s'),
            'pending_jobs_cancelled' => $conn->affected_rows
        ];
        
        error_log("User deactivation: " . json_encode($deactivationLog));
    }
    
    public function resetPassword($userId, $newPassword, $adminId) {
        // Validate password
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        // Hash password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Generate new API key
        $newApiKey = bin2hex(random_bytes(32));
        
        $sql = "UPDATE users SET password = ?, api_key = ?, updated_at = NOW() WHERE user_id = ?";
        $result = $this->db->query($sql, [$hashedPassword, $newApiKey, $userId]);
        
        if ($result) {
            // Log the password reset
            $this->logPasswordReset($userId, $adminId);
            
            return [
                'success' => true,
                'message' => 'Password reset successfully',
                'user_id' => $userId
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to reset password'];
    }
    
    private function logPasswordReset($userId, $adminId) {
        $logData = [
            'user_id' => $userId,
            'reset_by' => $adminId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $auditSql = "INSERT INTO audit_log (table_name, record_id, action, new_values, changed_by) 
                    VALUES ('users', ?, 'PASSWORD_RESET', ?, ?)";
        
        $this->db->query($auditSql, [
            $userId,
            json_encode(['action' => 'password_reset']),
            $adminId
        ]);
    }
    
    public function generateApiKey($userId) {
        $newApiKey = bin2hex(random_bytes(32));
        
        $sql = "UPDATE users SET api_key = ?, updated_at = NOW() WHERE user_id = ?";
        $result = $this->db->query($sql, [$newApiKey, $userId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'API key generated successfully',
                'api_key' => $newApiKey,
                'user_id' => $userId
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to generate API key'];
    }
}

// API Endpoint Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'update';
    $userId = $input['user_id'] ?? 0;
    $adminId = $_SESSION['user_id'];
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit();
    }
    
    $updater = new UserUpdater();
    
    switch ($action) {
        case 'update':
            $updates = $input['updates'] ?? [];
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No updates provided']);
                exit();
            }
            
            $response = $updater->updateUser($userId, $updates, $adminId);
            echo json_encode($response);
            break;
            
        case 'reset_password':
            $newPassword = $input['new_password'] ?? '';
            if (!$newPassword) {
                http_response_code(400);
                echo json_encode(['error' => 'New password is required']);
                exit();
            }
            
            $response = $updater->resetPassword($userId, $newPassword, $adminId);
            echo json_encode($response);
            break;
            
        case 'generate_api_key':
            $response = $updater->generateApiKey($userId);
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