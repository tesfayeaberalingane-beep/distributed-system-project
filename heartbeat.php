<?php
// server/api/heartbeat.php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

try {
    $node_name = $_POST['node_name'] ?? '';
    
    if (empty($node_name)) {
        throw new Exception('Node name is required');
    }
    
    $db = Database::getInstance();
    
    // Update heartbeat timestamp
    $db->query(
        "UPDATE worker_nodes SET 
            last_heartbeat = NOW(),
            status = 'online'
         WHERE node_name = ?",
        [$node_name]
    );
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>