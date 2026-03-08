<?php
/**
 * OTP Mailer — sends OTP verification emails via PHPMailer + SMTP.
 * Requires PHPMailer (installed via composer).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Load PHPMailer from vendor
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    error_log('OTP Mailer: vendor/autoload.php not found. Run: composer require phpmailer/phpmailer');
    return false;
}
require_once $vendor_autoload;

/**
 * Send OTP email to a customer.
 *
 * @param string $to_email   Recipient email
 * @param string $otp_code   6-digit OTP
 * @return array ['success' => bool, 'message' => string]
 */
function send_otp_email(string $to_email, string $otp_code): array
{
    $cfg = require __DIR__ . '/smtp_config.php';

    $mail = new PHPMailer(true);

    try {
        // ── Server settings ───────────────────────────────
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->Port       = (int) $cfg['smtp_port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->SMTPSecure = $cfg['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        // Disable verbose debugging in production; set to SMTP::DEBUG_SERVER for local testing
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        // ── Sender / recipient ────────────────────────────
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to_email);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        // ── Content ───────────────────────────────────────
        $expiry = $cfg['otp_expiry_minutes'] ?? 5;
        $mail->isHTML(false); // Plain text — better deliverability
        $mail->Subject = 'Verify your email address';
        $mail->Body    = implode("\n", [
            "Hello,",
            "",
            "Your PrintFlow verification code is:",
            "",
            "  {$otp_code}",
            "",
            "This code will expire in {$expiry} minutes.",
            "",
            "If you did not request this verification, you may ignore this email.",
            "",
            "PrintFlow Security Team",
        ]);

        $mail->send();
        return ['success' => true, 'message' => 'OTP sent successfully'];

    } catch (PHPMailerException $e) {
        error_log('OTP Mailer error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
    }
}
