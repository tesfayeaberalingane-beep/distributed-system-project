
<?php

require_once 'config.php';

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
//$status = $data->status;
$status = strtolower(trim($data->status));
$result = isset($data->result) ? $data->result : null;

$conn = connectDB();

// --- 1. Fetch Old Status for Logging ---
$old_status = 'unknown';
$check = $conn->prepare("SELECT status FROM jobs WHERE job_id = ?");
$check->bind_param("i", $job_id);
$check->execute();
$res = $check->get_result();
if($row = $res->fetch_assoc()) {
    $old_status = $row['status'];
}
$check->close();

// --- 2. Build Dynamic Update Query ---
$sql = "UPDATE jobs SET status = ?";
$params = "s";
$param_values = [$status];
// F10 FIX: Set started_at when job starts running
if ($status === 'running') {
    $sql .= ", started_at = NOW()";
}
// F10 IMPLEMENTATION: Use 'started_at' to track execution start
if (in_array($status, ['completed', 'failed', 'timeout'])) {
    $sql .= ", end_time = NOW()";
}

if ($result !== null) {
    $sql .= ", result = ?";
    $params .= "s";
    $param_values[] = $result;
}

$sql .= " WHERE job_id = ?";
$params .= "i";
$param_values[] = $job_id;

$stmt = $conn->prepare($sql);

// Dynamically bind parameters
$bind_names = [];
$bind_names[] = $params;
for ($i=0; $i<count($param_values); $i++) {
    $bind_name = 'p'.$i;
    $$bind_name = $param_values[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array(array($stmt, 'bind_param'), $bind_names);

if ($stmt->execute()) {
    // F14 IMPLEMENTATION: Log the transition
    if (function_exists('logJobStatusChange')) {
        logJobStatusChange($job_id, $old_status, $status, $result ?? "Updated via internal API.");
    }

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Job $job_id updated to $status."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to update job status."]);
}

$stmt->close();
$conn->close();

/*
require_once 'config.php';

// This API endpoint is primarily for internal scheduler use or admin actions, 
// so we'll check for a specific authorization header (a simple key) 
// or ensure it's called from a trusted internal source (like the scheduler script).
// For simplicity here, we will only check method and data existence.

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

// Optional fields for completion/failure
$result = isset($data->result) ? $data->result : null;

$conn = connectDB();

$sql = "UPDATE jobs SET status = ?";
$params = "s";
$param_values = [$status];

// Add start_time if status is 'running'
if ($status === 'running') {
    $sql .= ", start_time = NOW()";
}
// Add end_time and result if status is 'completed' or 'failed'
if ($status === 'completed' || $status === 'failed') {
    $sql .= ", end_time = NOW(), result = ?";
    $params .= "s";
    $param_values[] = $result;
}

$sql .= " WHERE job_id = ?";
$params .= "i";
$param_values[] = $job_id;

$stmt = $conn->prepare($sql);

// Dynamically bind parameters
$bind_names[] = $params;
for ($i=0; $i<count($param_values); $i++) {
    $bind_name = 'p'.$i;
    $$bind_name = $param_values[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array(array($stmt, 'bind_param'), $bind_names);


if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Job $job_id updated to $status."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to update job status."]);
}

$stmt->close();
$conn->close();*/

?>
