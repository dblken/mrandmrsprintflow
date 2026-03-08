<?php
/**
 * Forgot Password API
 * Sends a 6-digit reset code via email or SMS
 */

ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$type       = trim($_POST['type'] ?? '');
$identifier = trim($_POST['identifier'] ?? '');

if (empty($type) || empty($identifier)) {
    echo json_encode(['success' => false, 'message' => 'Please provide all required fields.']);
    exit;
}

if (!in_array($type, ['email', 'phone'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid reset type.']);
    exit;
}

if ($type === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if ($type === 'phone') {
    $digitsOnly = preg_replace('/[\s\-\+\(\)]/', '', $identifier);
    if (!preg_match('/^[0-9]{10,15}$/', $digitsOnly)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid phone number (digits only, 10-15 digits).']);
        exit;
    }
    $identifier = $digitsOnly;
}

// Rate limiting: one request per identifier per 2 minutes
try {
    $rate = db_query(
        "SELECT COUNT(*) as cnt FROM password_resets WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
        's', [$identifier]
    );
    if (!empty($rate) && $rate[0]['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Please wait 2 minutes before requesting another reset code.']);
        exit;
    }
} catch (Exception $e) {
    // Table doesn't exist yet - continue to create it below
}

try {
    // Ensure table exists
    global $conn;
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('User','Customer') NOT NULL,
        identifier VARCHAR(255) NOT NULL,
        reset_code VARCHAR(6) NOT NULL,
        used TINYINT(1) DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_identifier (identifier),
        KEY idx_user (user_id, user_type)
    )");

    $user_found = null;
    $user_type  = null;

    if ($type === 'email') {
        $rows = db_query(
            "SELECT user_id, CONCAT(first_name,' ',last_name) AS full_name FROM users WHERE email = ? AND status = 'Activated' LIMIT 1",
            's', [$identifier]
        );
        if (!empty($rows)) {
            $user_found = $rows[0];
            $user_type  = 'User';
        } else {
            $rows = db_query(
                "SELECT customer_id AS user_id, CONCAT(first_name,' ',last_name) AS full_name FROM customers WHERE email = ? AND status = 'Activated' LIMIT 1",
                's', [$identifier]
            );
            if (!empty($rows)) {
                $user_found = $rows[0];
                $user_type  = 'Customer';
            }
        }
    } else {
        $rows = db_query(
            "SELECT customer_id AS user_id, CONCAT(first_name,' ',last_name) AS full_name FROM customers WHERE contact_number = ? AND status = 'Activated' LIMIT 1",
            's', [$identifier]
        );
        if (!empty($rows)) {
            $user_found = $rows[0];
            $user_type  = 'Customer';
        }
    }

    // Neutral response whether account exists or not (prevents user enumeration)
    if (!$user_found) {
        echo json_encode(['success' => true, 'message' => 'If an account exists, a reset code has been sent.']);
        exit;
    }

    // Generate 6-digit reset code
    $reset_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Delete previous codes, insert fresh one
    db_execute("DELETE FROM password_resets WHERE user_id = ? AND user_type = ?", 'is', [$user_found['user_id'], $user_type]);
    db_execute(
        "INSERT INTO password_resets (user_id, user_type, identifier, reset_code, expires_at) VALUES (?,?,?,?,?)",
        'issss', [$user_found['user_id'], $user_type, $identifier, $reset_code, $expires_at]
    );

    // Send the code
    if ($type === 'email') {
        $name = htmlspecialchars($user_found['full_name']);
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
            body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin:0;padding:0;background:#f3f4f6}
            .wrap{max-width:520px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
            .hdr{background:linear-gradient(135deg,#6366f1,#7c3aed);padding:28px 24px;text-align:center;color:#fff}
            .hdr h1{margin:0;font-size:22px;font-weight:700}
            .body{padding:28px 24px;color:#374151;line-height:1.6}
            .code-box{background:#f5f3ff;border:2px dashed #6366f1;border-radius:10px;padding:20px;text-align:center;margin:20px 0}
            .code{font-size:36px;font-weight:700;color:#6366f1;letter-spacing:10px;font-family:monospace}
            .expiry{margin:6px 0 0;font-size:13px;color:#9ca3af}
            .warn{background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px;border-radius:6px;font-size:13px;color:#991b1b;margin-top:20px}
            .ftr{padding:20px;text-align:center;font-size:12px;color:#9ca3af;background:#f9fafb}
        </style></head><body>
        <div class='wrap'>
            <div class='hdr'><h1>Password Reset Code</h1></div>
            <div class='body'>
                <p>Hello <strong>{$name}</strong>,</p>
                <p>We received a password reset request for your PrintFlow account. Use the code below:</p>
                <div class='code-box'>
                    <div class='code'>{$reset_code}</div>
                    <p class='expiry'>Valid for 15 minutes &middot; Do not share this code</p>
                </div>
                <p>Enter this code in the password reset form to set a new password.</p>
                <div class='warn'><strong>Security notice:</strong> If you did not request this, please ignore this email. Your account remains secure.</div>
            </div>
            <div class='ftr'>&copy;" . date('Y') . " PrintFlow. All rights reserved.</div>
        </div></body></html>";

        send_email($identifier, 'PrintFlow - Password Reset Code', $html, true);
    } else {
        send_sms($identifier, "Your PrintFlow reset code is {$reset_code}. Valid for 15 minutes. Do not share this code.");
    }

    // Always log for development reference
    error_log("[PrintFlow] Password reset code for {$identifier}: {$reset_code}");

    $resp = ['success' => true, 'message' => 'If an account exists, a reset code has been sent.'];
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $resp['debug'] = ['reset_code' => $reset_code, 'expires_at' => $expires_at];
    }
    echo json_encode($resp);

} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
} catch (Error $e) {
    error_log("Forgot password fatal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
