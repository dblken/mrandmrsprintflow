<?php
/**
 * mail_helper.php
 * PrintFlow — delegates OTP mail to otp_mailer.php (single SMTP source: smtp_config.php).
 */

if (!function_exists('send_otp_email')) {
    require_once __DIR__ . '/otp_mailer.php';
}

/**
 * Send Password Reset Email (plain text code) — uses smtp_config.php when present.
 *
 * @return array{success:bool,message?:string}
 */
function send_password_reset_email($email, $reset_code) {
    if (!function_exists('send_email')) {
        require_once __DIR__ . '/functions.php';
    }
    $body = "Hello,\n\nYour PrintFlow password reset code is:\n\n{$reset_code}\n\nThis code expires in 15 minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nPrintFlow Security Team";
    $ok = send_email($email, 'PrintFlow Password Reset Code', $body, false);
    return $ok ? ['success' => true] : ['success' => false, 'message' => 'Failed to send email'];
}
