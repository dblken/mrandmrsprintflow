<?php
/**
 * resend_otp.php — JSON endpoint to resend OTP (rate-limited).
 * PrintFlow
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/otp_mailer.php';

header('Content-Type: application/json');

function json_out(bool $success, string $message): never {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(false, 'Invalid request method.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    json_out(false, 'Invalid security token. Please refresh the page.');
}

$email = sanitize($_POST['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(false, 'Invalid email address.');
}

$smtp_cfg = require __DIR__ . '/../includes/smtp_config.php';
// Base cooldown is 5 minutes (300 seconds)
$base_cooldown = 300; 

// Track attempts to multiply the cooldown (1st resend = 5m, 2nd = 10m, etc.)
if (!isset($_SESSION['otp_resend_attempts'])) {
    $_SESSION['otp_resend_attempts'] = 1;
}

$cooldown = $base_cooldown * $_SESSION['otp_resend_attempts'];

$type  = $_SESSION['otp_user_type'] ?? 'Customer';
$table = ($type === 'User') ? 'users' : 'customers';
$id_col = ($type === 'User') ? 'user_id' : 'customer_id';

// Fetch record
$rows = db_query("SELECT $id_col, otp_last_sent, email_verified FROM $table WHERE email = ?", 's', [$email]);
if (empty($rows)) {
    json_out(false, 'Account not found.');
}
$customer = $rows[0];

if ($customer['email_verified']) {
    json_out(false, 'This email is already verified. Please log in.');
}

// Rate limit
if (!empty($customer['otp_last_sent'])) {
    $seconds_since = time() - strtotime($customer['otp_last_sent']);
    if ($seconds_since < $cooldown) {
        $wait = $cooldown - $seconds_since;
        json_out(false, "Please wait {$wait} seconds before requesting another code.");
    }
}

// Generate new OTP
$otp_code   = (string) rand(100000, 999999);
$otp_expiry = date('Y-m-d H:i:s', time() + (($smtp_cfg['otp_expiry_minutes'] ?? 5) * 60));
$now        = date('Y-m-d H:i:s');

// Increment attempts for the NEXT resend
$_SESSION['otp_resend_attempts']++;

db_execute(
    "UPDATE $table SET otp_code = ?, otp_expiry = ?, otp_last_sent = ? WHERE email = ?",
    'ssss', [$otp_code, $otp_expiry, $now, $email]
);

// Send email
require_once __DIR__ . '/../includes/mail_helper.php';
if (send_otp_email($email, $otp_code)) {
    echo json_encode(['success' => true, 'message' => 'A new verification code has been sent to your email.', 'new_cooldown' => $base_cooldown * $_SESSION['otp_resend_attempts']]);
    exit;
} else {
    json_out(false, 'Failed to send email. Please try again later.');
}
