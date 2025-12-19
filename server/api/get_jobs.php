<?php

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. Please log in."]);
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = connectDB();

// Select all jobs submitted by the current user, ordered by submission time (newest first)
$sql = "SELECT job_id, title, priority, status, submit_time, result, max_runtime, started_at FROM jobs WHERE user_id = ? ORDER BY submit_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "jobs" => $jobs
]);

$stmt->close();
$conn->close();
?>