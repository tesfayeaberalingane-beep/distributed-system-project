<?php
require_once 'config.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// 1. Check if job_id is provided
if (!isset($_GET['job_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Job ID is required."]);
    exit();
}

$job_id = (int)$_GET['job_id'];
$conn = connectDB();

// 2. Fetch logs for this specific job ordered by newest first
$sql = "SELECT old_status, new_status, message, timestamp 
        FROM job_log 
        WHERE job_id = ? 
        ORDER BY timestamp DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $logs = [];
    
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    echo json_encode([
        "success" => true, 
        "logs" => $logs
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => "Database error: " . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>