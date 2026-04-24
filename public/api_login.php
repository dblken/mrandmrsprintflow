<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'code' => 'csrf_mismatch',
        'message' => 'Your session was refreshed. Please try again.',
        'csrf_token' => generate_csrf_token(),
    ]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required',
        'field_errors' => [
            'email' => empty($email) ? 'Email is required' : '',
            'password' => empty($password) ? 'Password is required' : ''
        ]
    ]);
    exit;
}

$result = login($email, $password, false);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'redirect' => $result['redirect'] ?? '/printflow/'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Login failed',
        'code' => $result['code'] ?? null,
        'lockout_remaining_seconds' => isset($result['lockout_remaining_seconds']) ? (int)$result['lockout_remaining_seconds'] : null,
        'lockout_level' => isset($result['lockout_level']) ? (int)$result['lockout_level'] : null,
        'field_errors' => [
            'password' => $result['message'] ?? 'Invalid credentials'
        ]
    ]);
}
