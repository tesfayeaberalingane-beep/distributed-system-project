<?php

require_once '../api/config.php';

// 1. Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access denied."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing job ID or status."]);
    exit();
}

$job_id = $data->job_id;
$status = $data->status;
$result = isset($data->result) ? $data->result : "Admin Manual Update to $status.";

$conn = connectDB();

// --- NEW: FETCH OLD STATUS BEFORE UPDATE ---
$old_status = 'unknown';
$check_stmt = $conn->prepare("SELECT status FROM jobs WHERE job_id = ?");
$check_stmt->bind_param("i", $job_id);
$check_stmt->execute();
$res = $check_stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $old_status = $row['status'];
}
$check_stmt->close();

// Base SQL for admin update
$sql = "UPDATE jobs SET status = ?, result = ?";
$params = "ss";
$param_values = [$status, $result];

if ($status === 'running') {
    $sql .= ", started_at = NOW()";
} elseif ($status === 'completed' || $status === 'failed' || $status === 'timeout') {
    $sql .= ", end_time = NOW()";
} elseif ($status === 'pending') {
    $sql .= ", started_at = NULL, end_time = NULL"; 
}

$sql .= " WHERE job_id = ?";
$params .= "i";
$param_values[] = $job_id;

$stmt = $conn->prepare($sql);

// Dynamic binding
$bind_names[] = $params;
for ($i=0; $i<count($param_values); $i++) {
    $bind_name = 'p'.$i;
    $$bind_name = $param_values[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array(array($stmt, 'bind_param'), $bind_names);

if ($stmt->execute()) {
    // --- NEW: TRIGGER THE LOG ENTRY ---
    if (function_exists('logJobStatusChange')) {
        $log_msg = "Admin (" . $_SESSION['username'] . ") manually changed status.";
        logJobStatusChange($job_id, $old_status, $status, $log_msg);
    }

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Admin updated Job $job_id to $status."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to update job status."]);
}

$stmt->close();
$conn->close();

/*
require_once '../api/config.php';

// 1. Authorization Check: Must be logged in AND an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(["success" => false, "message" => "Access denied."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing job ID or status."]);
    exit();
}

$job_id = $data->job_id;
$status = $data->status;
$result = isset($data->result) ? $data->result : "Admin Manual Update to $status.";

$conn = connectDB();

// Base SQL for admin update
$sql = "UPDATE jobs SET status = ?, result = ?";
$params = "ss";
$param_values = [$status, $result];

// Special time updates, similar to the scheduler
if ($status === 'running') {
    $sql .= ", start_time = NOW()";
} elseif ($status === 'completed' || $status === 'failed') {
    $sql .= ", end_time = NOW()";
} elseif ($status === 'pending') {
    // If admin sets it back to pending (e.g., re-running a failed job), clear times
    $sql .= ", start_time = NULL, end_time = NULL"; 
}

$sql .= " WHERE job_id = ?";
$params .= "i";
$param_values[] = $job_id;

$stmt = $conn->prepare($sql);

// Dynamic binding (same mechanism as update_job.php)
$bind_names[] = $params;
for ($i=0; $i<count($param_values); $i++) {
    $bind_name = 'p'.$i;
    $$bind_name = $param_values[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array(array($stmt, 'bind_param'), $bind_names);


if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Admin updated Job $job_id to $status."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to update job status."]);
}

$stmt->close();
$conn->close();
*/
?>