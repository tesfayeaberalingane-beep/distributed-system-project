<?php

// 1. Check if session is already started before calling it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Adjust the path to config.php (Ensure this points to the right file)
require_once 'config.php'; 

header("Content-Type: application/json");

// 3. Authorization Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized: Please log in."]);
    exit();
}

// 4. Get the JSON data
// Note: true as the second argument makes it an associative ARRAY
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data)) {
    echo json_encode(["success" => false, "message" => "No data received."]);
    exit();
}

// 5. FIX: Accessing the data correctly as an Array
$user_id     = $_SESSION['user_id'];
$title       = $data['title'] ?? 'Untitled Job';
$description = $data['description'] ?? '';
$priority    = $data['priority'] ?? 'medium';

// F9 Implementation: Get max_runtime from data or default to 3600
$max_runtime = isset($data['max_runtime']) ? (int)$data['max_runtime'] : 3600;

$conn = connectDB();

// 6. Updated Query to include max_runtime
$sql = "INSERT INTO jobs (user_id, title, description, priority, status, max_runtime) VALUES (?, ?, ?, ?, 'pending', ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isssi", $user_id, $title, $description, $priority, $max_runtime);

if ($stmt->execute()) {
    $job_id = $conn->insert_id;
    
    // Log initial status (F14)
    if (function_exists('logJobStatusChange')) {
        logJobStatusChange($job_id, 'N/A', 'pending', 'Job submitted successfully.');
    }

    echo json_encode(["success" => true, "message" => "Job #$job_id submitted!"]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB Error: " . $conn->error]);
}

$stmt->close();
$conn->close();

/*
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. Please log in."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->title) || empty($data->description) || empty($data->priority)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing job details."]);
    exit();
}

$user_id = $_SESSION['user_id'];
$title = $data->title;
$description = $data->description;
$priority = $data->priority; // Priority is validated by ENUM in DB
$max_runtime = isset($data['max_runtime']) ? (int)$data['max_runtime'] : 3600;
$conn = connectDB();
// Update the query to include max_runtim
$stmt = $conn->prepare("INSERT INTO jobs (user_id, title, description, priority, max_runtime) VALUES (?, ?, ?, 'pending', ?)");
$stmt->bind_param("issi",$user_id, $title, $description, $priority, $max_runtime);
// Insert the new job into the database with 'pending' status
//$stmt = $conn->prepare("INSERT INTO jobs (user_id, title, description, priority) VALUES (?, ?, ?, ?, ?)");
//$stmt->bind_param("isss", $user_id, $title, $description, $priority);

if ($stmt->execute()) {
    http_response_code(201); // Created
    $job_id = $conn->insert_id;
    logJobStatusChange($new_job_id, 'N/A', 'PENDING', 'Job submitted by user and queued.');
    echo json_encode(["success" => true, "message" => "Job submitted successfully!", "job_id" => $job_id]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Job submission failed."]);
}

$stmt->close();
$conn->close();
*/
?>