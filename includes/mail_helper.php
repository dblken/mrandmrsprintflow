<?php
/**
 * mail_helper.php
 * PrintFlow - PHPMailer Helper
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Send OTP Verification Email
 * @param string $email
 * @param string $otp
 * @return bool
 */
function send_otp_email($email, $otp) {
    // Load SMTP config (leveraging previous config if available, or using prompt defaults)
    $smtp_cfg = file_exists(__DIR__ . '/smtp_config.php') ? require __DIR__ . '/smtp_config.php' : [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => 'your_email@gmail.com',
        'smtp_pass' => 'your_app_password',
        'smtp_secure' => 'tls',
        'from_email' => 'noreply@printflow.com',
        'from_name' => 'PrintFlow Security Team'
    ];

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_cfg['smtp_user'];
        $mail->Password   = $smtp_cfg['smtp_pass'];
        $mail->SMTPSecure = $smtp_cfg['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_cfg['smtp_port'];

        // Recipients
        $mail->setFrom($smtp_cfg['from_email'], $smtp_cfg['from_name']);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(false); // Plain text
        $mail->Subject = 'PrintFlow Email Verification Code';
        $mail->Body    = "Hello,\n\nYour PrintFlow verification code is:\n\n{$otp}\n\nThis code expires in 5 minutes.\n\nIf you did not request this verification, ignore this email.\n\nPrintFlow Security Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send Password Reset Email
 * @param string $email
 * @param string $reset_code
 * @return bool
 */
function send_password_reset_email($email, $reset_code) {
    $smtp_cfg = file_exists(__DIR__ . '/smtp_config.php') ? require __DIR__ . '/smtp_config.php' : [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => 'your_email@gmail.com',
        'smtp_pass' => 'your_app_password',
        'smtp_secure' => 'tls',
        'from_email' => 'noreply@printflow.com',
        'from_name' => 'PrintFlow Security Team'
    ];

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_cfg['smtp_user'];
        $mail->Password   = $smtp_cfg['smtp_pass'];
        $mail->SMTPSecure = $smtp_cfg['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_cfg['smtp_port'];

        // Recipients
        $mail->setFrom($smtp_cfg['from_email'], $smtp_cfg['from_name']);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(false); // Plain text
        $mail->Subject = 'PrintFlow Password Reset Code';
        $mail->Body    = "Hello,\n\nYour PrintFlow password reset code is:\n\n{$reset_code}\n\nThis code expires in 15 minutes.\n\nIf you did not request a password reset, please ignore this email and your password will remain unchanged.\n\nPrintFlow Security Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return false;
    }
}
