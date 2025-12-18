<?php
require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->username) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please provide a username and password."]);
    exit();
}

$username = $data->username;
$password = $data->password;
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = 'user'; // Default registration role is 'user'

$conn = connectDB();

// Check if username already exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(["success" => false, "message" => "Username already exists."]);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt->close();

// Insert the new user
$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $hashed_password, $role);

if ($stmt->execute()) {
    http_response_code(201); // Created
    echo json_encode(["success" => true, "message" => "Registration successful. Please log in."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Registration failed."]);
}

$stmt->close();
$conn->close();
?>