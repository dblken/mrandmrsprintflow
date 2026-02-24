<?php
/**
 * Verify OTP Code API
 * POST: { type: 'email'|'phone', identifier: '...', code: '123456', purpose: 'register'|'reset' }
 * Returns JSON: { success, message, verified_token }
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$type       = $input['type'] ?? '';
$identifier = trim($input['identifier'] ?? '');
$code       = trim($input['code'] ?? '');
$purpose    = $input['purpose'] ?? 'register';

// Basic validation
if (!in_array($type, ['email', 'phone'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid type.']);
    exit;
}
if (empty($identifier) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Identifier and code are required.']);
    exit;
}
if (!preg_match('/^\d{6}$/', $code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid code format. Must be 6 digits.']);
    exit;
}

// Find matching, unexpired, unused code
$result = db_query(
    "SELECT id, code FROM verification_codes 
     WHERE identifier = ? AND type = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()
     ORDER BY created_at DESC LIMIT 1",
    'sss', [$identifier, $type, $purpose]
);

if (empty($result)) {
    echo json_encode(['success' => false, 'message' => 'Code expired or not found. Please request a new one.']);
    exit;
}

$record = $result[0];

// Constant-time comparison to prevent timing attacks
if (!hash_equals($record['code'], $code)) {
    echo json_encode(['success' => false, 'message' => 'Incorrect verification code.']);
    exit;
}

// Mark as used
db_execute("UPDATE verification_codes SET is_used = 1 WHERE id = ?", 'i', [$record['id']]);

// Generate a short-lived verified token (stored in session for the registration step)
if (session_status() === PHP_SESSION_NONE) session_start();
$verified_token = bin2hex(random_bytes(16));
$_SESSION['otp_verified'] = [
    'type'       => $type,
    'identifier' => $identifier,
    'token'      => $verified_token,
    'expires'    => time() + 900, // 15 minutes to complete registration
];

echo json_encode([
    'success' => true,
    'message' => 'Verification successful!',
    'verified_token' => $verified_token,
]);
