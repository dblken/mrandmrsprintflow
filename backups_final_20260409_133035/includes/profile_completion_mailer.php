<?php
/**
 * Profile Completion Mailer — sends email with link for new staff to complete their profile.
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    error_log('Profile Completion Mailer: vendor/autoload.php not found.');
    return false;
}
require_once $vendor_autoload;

/**
 * Send profile completion email to a new user.
 *
 * @param string $to_email   Recipient email
 * @param string $first_name First name
 * @param string $link       Full URL to complete profile (with token)
 * @return array ['success' => bool, 'message' => string]
 */
function send_profile_completion_email(string $to_email, string $first_name, string $link): array
{
    $cfg = require __DIR__ . '/smtp_config.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Timeout    = isset($cfg['smtp_timeout']) ? (int) $cfg['smtp_timeout'] : 45;
        $mail->Host       = $cfg['smtp_host'];
        $mail->Port       = (int) $cfg['smtp_port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->SMTPSecure = $cfg['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to_email);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Complete Your PrintFlow Profile';

        $logo_path = dirname(__DIR__) . '/public/images/printflow.jpg';
        $logo_html = '';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'printflow_logo', 'printflow.jpg');
            $logo_html = '<img src="cid:printflow_logo" alt="PrintFlow" style="width:88px;height:88px;object-fit:cover;border-radius:50%;display:block;margin:0 auto 12px auto;">';
        }

        $name = htmlspecialchars($first_name);
        $link_safe = htmlspecialchars($link);

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;color:#111827;line-height:1.5;">
                <div style="max-width:520px;margin:0 auto;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">
                    ' . $logo_html . '
                    <h2 style="margin:0 0 8px 0;font-size:18px;text-align:center;">Welcome to PrintFlow!</h2>
                    <p style="margin:0 0 12px 0;">Hello ' . $name . ',</p>
                    <p style="margin:0 0 12px 0;">Your staff account has been created. Please complete your profile by clicking the link below:</p>
                    <p style="margin:0 0 14px 0;text-align:center;">
                        <a href="' . $link_safe . '" style="display:inline-block;padding:12px 24px;background:#0d9488;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Complete My Profile</a>
                    </p>
                    <p style="margin:0 0 12px 0;font-size:13px;color:#6b7280;">This link expires in 7 days. You will need to add your contact details, address, and upload a valid ID. Use your email and default password (email + birthday MMDDYYYY) to log in after completing.</p>
                    <p style="margin:0;">If you did not expect this email, please contact your administrator.</p>
                    <p style="margin:14px 0 0 0;">PrintFlow Team</p>
                </div>
            </div>';
        $mail->AltBody = "Hello {$name},\n\nYour staff account has been created. Complete your profile:\n{$link}\n\nThis link expires in 7 days. Use your email and default password (email + birthday MMDDYYYY) to log in after completing.\n\nPrintFlow Team";

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (PHPMailerException $e) {
        error_log('Profile Completion Mailer error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
    }
}

/**
 * Send profile completion resend email with admin notes.
 *
 * @param string $to_email   Recipient email
 * @param string $first_name First name
 * @param string $link       Full URL to complete profile (with token)
 * @param array  $admin_notes Array of issue labels, e.g. ['Name', 'Address', 'ID Image', 'Other: ...']
 * @return array ['success' => bool, 'message' => string]
 */
function send_profile_completion_resend_email(string $to_email, string $first_name, string $link, array $admin_notes = []): array
{
    $cfg = require __DIR__ . '/smtp_config.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->Port       = (int) $cfg['smtp_port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->SMTPSecure = $cfg['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to_email);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = 'PrintFlow – Please Review Your Profile';

        $logo_path = dirname(__DIR__) . '/public/images/printflow.jpg';
        $logo_html = '';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'printflow_logo', 'printflow.jpg');
            $logo_html = '<img src="cid:printflow_logo" alt="PrintFlow" style="width:88px;height:88px;object-fit:cover;border-radius:50%;display:block;margin:0 auto 12px auto;">';
        }

        $name = htmlspecialchars($first_name);
        $link_safe = htmlspecialchars($link);

        $notes_html = '';
        if (!empty($admin_notes)) {
            $notes_html = '
                    <div style="margin:16px 0;padding:14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;">
                        <p style="margin:0 0 8px 0;font-weight:600;color:#92400e;">Admin feedback – please fix the following:</p>
                        <ul style="margin:0;padding-left:20px;color:#78350f;">' .
                implode('', array_map(fn($n) => '<li>' . htmlspecialchars($n) . '</li>', $admin_notes)) . '
                        </ul>
                    </div>';
        }

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;color:#111827;line-height:1.5;">
                <div style="max-width:520px;margin:0 auto;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">
                    ' . $logo_html . '
                    <h2 style="margin:0 0 8px 0;font-size:18px;text-align:center;">Profile Review Required</h2>
                    <p style="margin:0 0 12px 0;">Hello ' . $name . ',</p>
                    <p style="margin:0 0 12px 0;">An administrator has requested that you review and update your profile. Please use the link below to complete or correct your information.</p>
                    ' . $notes_html . '
                    <p style="margin:0 0 14px 0;text-align:center;">
                        <a href="' . $link_safe . '" style="display:inline-block;padding:12px 24px;background:#0d9488;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Complete My Profile</a>
                    </p>
                    <p style="margin:0 0 12px 0;font-size:13px;color:#6b7280;">This link expires in 7 days.</p>
                    <p style="margin:0;">If you did not expect this email, please contact your administrator.</p>
                    <p style="margin:14px 0 0 0;">PrintFlow Team</p>
                </div>
            </div>';
        $notes_plain = !empty($admin_notes) ? "\n\nAdmin feedback – please fix:\n- " . implode("\n- ", $admin_notes) : '';
        $mail->AltBody = "Hello {$name},\n\nAn administrator has requested that you review and update your profile.{$notes_plain}\n\nComplete your profile: {$link}\n\nThis link expires in 7 days.\n\nPrintFlow Team";

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (PHPMailerException $e) {
        error_log('Profile Completion Resend Mailer error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
    }
}

/**
 * Send account activation email to staff with login button.
 *
 * @param string $to_email   Recipient email
 * @param string $first_name First name
 * @return array ['success' => bool, 'message' => string]
 */
function send_account_activated_email(string $to_email, string $first_name): array
{
    $cfg = require __DIR__ . '/smtp_config.php';

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $login_url = $protocol . '://' . $host . '/printflow/?auth_modal=login';
    $login_safe = htmlspecialchars($login_url);

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->Port       = (int) $cfg['smtp_port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->SMTPSecure = $cfg['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to_email);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Your PrintFlow Account is Activated';

        $logo_path = dirname(__DIR__) . '/public/images/printflow.jpg';
        $logo_html = '';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'printflow_logo', 'printflow.jpg');
            $logo_html = '<img src="cid:printflow_logo" alt="PrintFlow" style="width:88px;height:88px;object-fit:cover;border-radius:50%;display:block;margin:0 auto 12px auto;">';
        }

        $name = htmlspecialchars($first_name);

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;color:#111827;line-height:1.5;">
                <div style="max-width:520px;margin:0 auto;padding:16px;border:1px solid #e5e7eb;border-radius:8px;">
                    ' . $logo_html . '
                    <h2 style="margin:0 0 8px 0;font-size:18px;text-align:center;">Your Account is Activated</h2>
                    <p style="margin:0 0 12px 0;">Hello ' . $name . ',</p>
                    <p style="margin:0 0 12px 0;">Your PrintFlow staff account has been approved. You can now log in and access the system.</p>
                    <p style="margin:0 0 14px 0;text-align:center;">
                        <a href="' . $login_safe . '" style="display:inline-block;padding:12px 24px;background:#0d9488;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Go to Login</a>
                    </p>
                    <p style="margin:0 0 12px 0;font-size:13px;color:#6b7280;">Use your email and password to sign in.</p>
                    <p style="margin:0;">PrintFlow Team</p>
                </div>
            </div>';
        $mail->AltBody = "Hello {$name},\n\nYour PrintFlow staff account has been activated. Log in at: {$login_url}\n\nUse your email and password to sign in.\n\nPrintFlow Team";

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (PHPMailerException $e) {
        error_log('Account Activated Mailer error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
    }
}
