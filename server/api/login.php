<?php
require_once 'config.php';
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

$conn = connectDB();

$stmt = $conn->prepare("SELECT user_id,username, password, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $hashed_password = $user['password'];

    // Verify the password
    if (password_verify($password, $hashed_password)) {
        // Authentication successful, start session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "role" => $user['role'] // Crucial for client-side redirection
        ]);
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(["success" => false, "message" => "Invalid credentials."]);
    }
} else {
    http_response_code(404); // Not Found
    echo json_encode(["success" => false, "message" => "User not found."]);
}

$stmt->close();
$conn->close();
?>