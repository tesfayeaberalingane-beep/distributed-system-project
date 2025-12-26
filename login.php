<?php
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        json_response(false, null, 'Username and password required', 400);
    }
    
    $auth = new Auth();
    $user = $auth->login($data['username'], $data['password']);
    
    if ($user) {
        json_response(true, [
            'user' => $user,
            'redirect' => $user['role'] === 'admin' ? 'admin.html' : 'dashboard.html'
        ], 'Login successful');
    } else {
        json_response(false, null, 'Invalid credentials', 401);
    }
} else {
    json_response(false, null, 'Method not allowed', 405);
}
?>