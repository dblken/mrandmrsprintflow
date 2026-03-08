<?php
/**
 * process_register.php
 * Handles User (Admin/Staff) Registration
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any existing OTP session data to prevent "stickiness"
    unset($_SESSION['otp_pending_email']);
    unset($_SESSION['otp_user_type']);
    unset($_SESSION['otp_error']);
    unset($_SESSION['otp_success']);

    // 1. Validate form inputs
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name  = sanitize($_POST['last_name'] ?? '');
    $email      = sanitize($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = $_POST['role'] ?? 'Staff'; // Default to Staff

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        redirect('register.php?error=All fields are required');
    }

    // 2. Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Check if email exists
    $existing = db_query("SELECT user_id, email_verified FROM users WHERE email = ?", 's', [$email]);
    if (!empty($existing)) {
        if (isset($existing[0]['email_verified']) && $existing[0]['email_verified'] == 0) {
            // Delete incomplete registration to allow re-registration
            db_execute("DELETE FROM users WHERE user_id = ?", 'i', [$existing[0]['user_id']]);
        } else {
            redirect('register.php?error=Email already exists');
        }
    }

    // 3. Insert user record with email_verified = 0
    $sql = "INSERT INTO users (first_name, last_name, email, password_hash, role, status, email_verified) VALUES (?, ?, ?, ?, ?, 'Pending', 0)";
    $user_id = db_execute($sql, 'sssss', [$first_name, $last_name, $email, $password_hash, $role]);

    if ($user_id) {
        // 4. Generate OTP
        $otp = (string)rand(100000, 999999);

        // 5. Set expiration time (5 minutes)
        $now    = date('Y-m-d H:i:s');
        $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

        // 6. Save OTP and expiry in users table
        db_execute("UPDATE users SET otp_code = ?, otp_expiry = ?, otp_last_sent = ? WHERE user_id = ?", 'sssi', [$otp, $expiry, $now, $user_id]);

        // 7. Send OTP email
        if (send_otp_email($email, $otp)) {
            $_SESSION['otp_pending_email'] = $email;
            $_SESSION['otp_user_type'] = 'User';
            // 8. Redirect to verify_email.php
            redirect('verify_email.php?success=Verification code sent to your email');
        } else {
            redirect('register.php?error=Failed to send verification email. Please try again.');
        }
    } else {
        redirect('register.php?error=Registration failed');
    }
} else {
    redirect('register.php');
}
