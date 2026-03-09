<?php
/**
 * Update Password API
 * Finalizes the reset process after token verification.
 */

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token    = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($token) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

// 1. Verify token one last time
$rows = db_query(
    "SELECT * FROM password_resets WHERE reset_token = ? AND used = 0 AND expires_at > NOW() LIMIT 1",
    's', [$token]
);

if (empty($rows)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
    exit;
}

$reset_data = $rows[0];
$user_id    = $reset_data['user_id'];
$user_type  = $reset_data['user_type'];

try {
    // 2. Hash new password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // 3. Update the correct user table
    if ($user_type === 'Customer') {
        $ok = db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $user_id]);
    } else {
        $ok = db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", 'si', [$password_hash, $user_id]);
    }

    if (!$ok) {
        throw new Exception("Failed to update password in database.");
    }

    // 4. Invalidate the token
    db_execute("UPDATE password_resets SET used = 1 WHERE id = ?", 'i', [$reset_data['id']]);

    echo json_encode(['success' => true, 'message' => 'Password updated successfully! Redirecting to login...']);

} catch (Exception $e) {
    error_log("Update password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during password update. Please try again.']);
}
