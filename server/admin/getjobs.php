<?php
// Note: This config path is different from the main API due to your requested folder structure
require_once '../api/config.php'; 

// 1. Authorization Check: Must be logged in AND an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(["success" => false, "message" => "Access denied."]);
    exit();
}

$conn = connectDB();

// A. Get all jobs, including the username of the submitter (JOIN)
$sql_jobs = "SELECT 
    j.job_id, j.title, j.priority, j.status, j.submit_time, j.result, u.username 
    FROM jobs j 
    JOIN users u ON j.user_id = u.user_id 
    ORDER BY j.submit_time DESC";

$result_jobs = $conn->query($sql_jobs);
$jobs = [];
if ($result_jobs) {
    while ($row = $result_jobs->fetch_assoc()) {
        $jobs[] = $row;
    }
}

// B. Get system statistics
$sql_stats = "SELECT status, COUNT(*) as count FROM jobs GROUP BY status";
$result_stats = $conn->query($sql_stats);

$stats = [
    'total' => count($jobs),
    'pending' => 0,
    'running' => 0,
    'completed' => 0,
    'failed' => 0
];

if ($result_stats) {
    while ($row = $result_stats->fetch_assoc()) {
        $stats[$row['status']] = (int)$row['count'];
    }
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "jobs" => $jobs,
    "stats" => $stats
]);

$conn->close();
?>