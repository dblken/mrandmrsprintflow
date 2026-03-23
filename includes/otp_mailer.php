<?php
/**
 * OTP Mailer — sends OTP verification emails via PHPMailer + SMTP.
 * Requires PHPMailer (installed via composer).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Load PHPMailer from vendor (do not `return` here — that would skip defining send_otp_email)
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendor_autoload)) {
    require_once $vendor_autoload;
} else {
    error_log('OTP Mailer: vendor/autoload.php not found. Run: composer require phpmailer/phpmailer');
}

/**
 * Send OTP email to a customer.
 *
 * @param string $to_email   Recipient email
 * @param string $otp_code   6-digit OTP
 * @return array ['success' => bool, 'message' => string]
 */
function send_otp_email(string $to_email, string $otp_code): array
{
    if (!class_exists(PHPMailer::class)) {
        return ['success' => false, 'message' => 'PHPMailer is not installed. Run: composer require phpmailer/phpmailer'];
    }

    $cfgPath = __DIR__ . '/smtp_config.php';
    if (!is_file($cfgPath)) {
        return ['success' => false, 'message' => 'Missing includes/smtp_config.php. Copy smtp_config.example.php and add your SMTP credentials.'];
    }
    $cfg = require $cfgPath;

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
        $mail->isHTML(true);
        $mail->Subject = 'Verify your email address';

        $logo_path = dirname(__DIR__) . '/public/images/printflow.jpg';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'printflow_logo', 'printflow.jpg');
        }
        $logo_html = file_exists($logo_path)
            ? '<img src="cid:printflow_logo" alt="PrintFlow" style="width:88px;height:88px;object-fit:cover;border-radius:50%;display:block;margin:0 auto 12px auto;">'
            : '';

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;color:#111827;line-height:1.5;">
                <div style="max-width:520px;margin:0 auto;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">
                    ' . $logo_html . '
                    <h2 style="margin:0 0 8px 0;font-size:18px;text-align:center;">Email Verification Code</h2>
                    <p style="margin:0 0 12px 0;">Hello,</p>
                    <p style="margin:0 0 12px 0;">Your PrintFlow verification code is:</p>
                    <p style="margin:0 0 14px 0;font-size:28px;font-weight:700;letter-spacing:4px;text-align:center;">' . htmlspecialchars($otp_code) . '</p>
                    <p style="margin:0 0 12px 0;">This code will expire in ' . (int)$expiry . ' minutes.</p>
                    <p style="margin:0;">If you did not request this verification, you may ignore this email.</p>
                    <p style="margin:14px 0 0 0;">PrintFlow Security Team</p>
                </div>
            </div>';
        $mail->AltBody = implode("\n", [
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
