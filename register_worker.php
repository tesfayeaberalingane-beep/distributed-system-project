
<?php
// server/api/register_worker.php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

try {
    $node_name = $_POST['node_name'] ?? '';
    $cpu_cores = intval($_POST['cpu_cores'] ?? 1);
    $total_memory = intval($_POST['total_memory'] ?? 1024);
    $ip_address = $_POST['ip_address'] ?? '127.0.0.1';
    
    if (empty($node_name)) {
        throw new Exception('Node name is required');
    }
    
    $db = Database::getInstance();
    
    // Check if node already exists
    $existing = $db->query(
        "SELECT id FROM worker_nodes WHERE node_name = ?",
        [$node_name]
    )->fetch();
    
    if ($existing) {
        // Update existing node
        $db->query(
            "UPDATE worker_nodes SET 
                cpu_cores = ?, 
                total_memory = ?,
                available_memory = ?,
                ip_address = ?,
                status = 'online',
                last_heartbeat = NOW()
             WHERE node_name = ?",
            [$cpu_cores, $total_memory, $total_memory, $ip_address, $node_name]
        );
    } else {
        // Insert new node
        $db->query(
            "INSERT INTO worker_nodes 
                (node_name, ip_address, cpu_cores, total_memory, 
                 available_memory, status, last_heartbeat) 
             VALUES (?, ?, ?, ?, ?, 'online', NOW())",
            [$node_name, $ip_address, $cpu_cores, $total_memory, $total_memory]
        );
    }
    
    echo json_encode(['success' => true, 'message' => 'Worker registered']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>