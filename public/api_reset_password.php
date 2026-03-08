<?php
/**
 * Reset Password API
 * Verifies the 6-digit code and updates user password in-modal
 */

ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$reset_code = trim($_POST['reset_code'] ?? '');
$password   = $_POST['password'] ?? '';
$confirm    = $_POST['confirm_password'] ?? '';

// Validate all required fields
if (empty($identifier) || empty($reset_code) || empty($password) || empty($confirm)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Validate code: exactly 6 digits
if (strlen($reset_code) !== 6 || !ctype_digit($reset_code)) {
    echo json_encode(['success' => false, 'message' => 'Reset code must be exactly 6 digits.']);
    exit;
}

// Validate password length
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

// Validate password match
if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

try {
    // Check for a valid, unexpired, unused code
    $reset = db_query(
        "SELECT * FROM password_resets WHERE identifier = ? AND reset_code = ? AND used = 0 AND expires_at > NOW() LIMIT 1",
        'ss', [$identifier, $reset_code]
    );

    if (empty($reset)) {
        // Distinguish expired vs invalid for better UX
        $expired = db_query(
            "SELECT id FROM password_resets WHERE identifier = ? AND reset_code = ? AND used = 0 AND expires_at <= NOW() LIMIT 1",
            'ss', [$identifier, $reset_code]
        );

        echo json_encode([
            'success' => false,
            'expired' => !empty($expired),
            'message' => !empty($expired)
                ? 'Your reset code has expired. Please request a new one.'
                : 'Invalid reset code. Please check and try again.',
        ]);
        exit;
    }

    $data          = $reset[0];
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Update the password in the correct table
    if ($data['user_type'] === 'Customer') {
        db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $data['user_id']]);
    } else {
        db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", 'si', [$password_hash, $data['user_id']]);
    }

    // Invalidate all reset codes for this user (one-time use)
    db_execute(
        "UPDATE password_resets SET used = 1 WHERE user_id = ? AND user_type = ?",
        'is', [$data['user_id'], $data['user_type']]
    );

    echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);

} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
} catch (Error $e) {
    error_log("Reset password fatal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
