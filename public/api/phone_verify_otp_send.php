<?php
/**
 * Send OTP (simulation) — stores 6-digit code in session
 * Requires phone to be validated first (call phone_verify.php).
 *
 * POST: number=639XXXXXXXXX (validated PH mobile)
 * Response: { success, expires_in, debug_code? }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/email_sms_config.php';
session_start();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$number = trim($input['number'] ?? '');

if ($number === '') {
    echo json_encode(['success' => false, 'error' => 'Phone number required']);
    exit;
}

$digits = preg_replace('/\D/', '', $number);
if (preg_match('/^0(\d{10})$/', $digits, $m)) $digits = '63' . $m[1];
elseif (strlen($digits) === 10 && $digits[0] === '9') $digits = '63' . $digits;

if (!preg_match('/^63[89]\d{9}$/', $digits)) {
    echo json_encode(['success' => false, 'error' => 'Invalid Philippine mobile number']);
    exit;
}

$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = time() + 300; // 5 min

$_SESSION['phone_otp'] = [
    'code' => $otp,
    'number' => $digits,
    'expires' => $expires,
];

// Send real SMS when Semaphore is configured
$sms_sent = false;
if (defined('SMS_ENABLED') && SMS_ENABLED && function_exists('send_sms')) {
    require_once __DIR__ . '/../../includes/functions.php';
    $sms_sent = send_sms('+' . $digits, "PrintFlow: Your verification code is {$otp}. Valid for 5 minutes.");
}

$debug = defined('DEBUG_MODE') && DEBUG_MODE;
echo json_encode([
    'success' => true,
    'expires_in' => 300,
    'debug_code' => ($debug && !$sms_sent) ? $otp : null,
]);
