<?php
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['username', 'password', 'email'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            json_response(false, null, "Missing required field: $field", 400);
        }
    }
    
    $username = trim($data['username']);
    $password = trim($data['password']);
    $email = trim($data['email']);
    $role = isset($data['role']) && $data['role'] === 'admin' ? 'admin' : 'user';
    
    // Validate inputs
    if (strlen($username) < 3 || strlen($username) > 50) {
        json_response(false, null, 'Username must be between 3 and 50 characters', 400);
    }
    
    if (strlen($password) < 6) {
        json_response(false, null, 'Password must be at least 6 characters', 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(false, null, 'Invalid email address', 400);
    }
    
    // Additional password strength check
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        json_response(false, null, 'Password must contain at least one uppercase letter and one number', 400);
    }
    
    // Check if registration requires admin approval (for admin roles)
    if ($role === 'admin') {
        // In production, you might want to restrict admin registration
        // or require approval from existing admin
        json_response(false, null, 'Admin registration requires approval', 403);
    }
    
    $auth = new Auth();
    $result = $auth->register($username, $password, $email, $role);
    
    if ($result['success']) {
        json_response(true, [
            'user_id' => $result['user_id'],
            'username' => $username,
            'email' => $email,
            'role' => $role
        ], $result['message']);
    } else {
        json_response(false, null, $result['message'], 400);
    }
} else {
    json_response(false, null, 'Method not allowed', 405);
}
?>