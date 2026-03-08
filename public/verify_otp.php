<?php
/**
 * verify_otp.php
 * Validates User/Customer OTP
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$email = $_POST['email'] ?? ($_SESSION['otp_pending_email'] ?? '');
$otp = $_POST['otp'] ?? '';
$type = $_SESSION['otp_user_type'] ?? 'Customer';

if (empty($email) || empty($otp)) {
    redirect('verify_email.php?error=Please enter the code');
}

$table = ($type === 'User') ? 'users' : 'customers';
$id_col = ($type === 'User') ? 'user_id' : 'customer_id';

// 2. Query database
$sql = "SELECT $id_col, otp_code, otp_expiry FROM $table WHERE email = ?";
$result = db_query($sql, 's', [$email]);

if (empty($result)) {
    redirect('verify_email.php?error=Account not found');
}

$record = $result[0];

// 3. Check if OTP matches & 4. Not expired
if ($record['otp_code'] === $otp && strtotime($record['otp_expiry']) > time()) {
    // 5. If valid
    $update_sql = "UPDATE $table SET email_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE email = ?";
    db_execute($update_sql, 's', [$email]);

    $_SESSION['otp_success'] = "Email verified successfully. You can now log in.";
    redirect('/printflow/?auth_modal=login&success=' . urlencode('Email verified. Please log in.'));
} else {
    redirect('verify_email.php?error=Invalid or expired code');
}
