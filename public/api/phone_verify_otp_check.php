<?php
/**
 * Verify OTP — checks stored session code
 *
 * POST: { number, code }
 * Response: { valid }
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
    echo json_encode(['valid' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$number = trim($input['number'] ?? '');
$code = trim($input['code'] ?? '');

$digits = preg_replace('/\D/', '', $number);
if (preg_match('/^0(\d{10})$/', $digits, $m)) $digits = '63' . $m[1];
elseif (strlen($digits) === 10 && $digits[0] === '9') $digits = '63' . $digits;

$stored = $_SESSION['phone_otp'] ?? null;
if (!$stored || $stored['number'] !== $digits) {
    echo json_encode(['valid' => false, 'error' => 'Invalid or expired OTP. Please request a new code.']);
    exit;
}
if (time() > $stored['expires']) {
    unset($_SESSION['phone_otp']);
    echo json_encode(['valid' => false, 'error' => 'OTP expired. Please request a new code.']);
    exit;
}
if ($code !== $stored['code']) {
    echo json_encode(['valid' => false, 'error' => 'Incorrect OTP. Please try again.']);
    exit;
}

unset($_SESSION['phone_otp']);
$_SESSION['phone_verified'] = ['number' => $digits, 'verified_at' => time()];

echo json_encode(['valid' => true]);
