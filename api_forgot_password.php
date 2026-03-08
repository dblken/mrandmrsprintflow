<?php
/**
 * Forgot Password API Endpoint
 * Handles password reset code generation and sending
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';

// Validate input
if (empty($type) || empty($identifier)) {
    echo json_encode(['success' => false, 'message' => 'Please provide all required fields']);
    exit;
}

if (!in_array($type, ['email', 'phone'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

// Validate format
if ($type === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if ($type === 'phone' && !preg_match('/^[0-9]{10,15}$/', $identifier)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
    exit;
}

// Rate limiting: Check if user has requested reset recently (within 2 minutes)
$ip_address = $_SERVER['REMOTE_ADDR'];
$rate_limit_check = db_query(
    "SELECT COUNT(*) as count FROM password_resets 
     WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
    's',
    [$identifier]
);

if (!empty($rate_limit_check) && $rate_limit_check[0]['count'] > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Please wait 2 minutes before requesting another reset code.'
    ]);
    exit;
}

try {
    $user_found = null;
    $user_type = null;
    
    if ($type === 'email') {
        // Check users table first (Admin/Manager/Staff)
        $users = db_query("SELECT user_id, email, CONCAT(first_name, ' ', last_name) as full_name, 'User' as account_type 
                          FROM users WHERE email = ? AND status = 'Activated' LIMIT 1", 's', [$identifier]);
        if (!empty($users)) {
            $user_found = $users[0];
            $user_type = 'User';
        } else {
            // Check customers table
            $customers = db_query("SELECT customer_id as user_id, email, CONCAT(first_name, ' ', last_name) as full_name, 'Customer' as account_type 
                                 FROM customers WHERE email = ? AND status = 'Activated' LIMIT 1", 's', [$identifier]);
            if (!empty($customers)) {
                $user_found = $customers[0];
                $user_type = 'Customer';
            }
        }
    } else {
        // Phone number lookup (only customers have phone numbers)
        $customers = db_query("SELECT customer_id as user_id, contact_number as phone, email, CONCAT(first_name, ' ', last_name) as full_name, 'Customer' as account_type 
                             FROM customers WHERE contact_number = ? AND status = 'Activated' LIMIT 1", 's', [$identifier]);
        if (!empty($customers)) {
            $user_found = $customers[0];
            $user_type = 'Customer';
        }
    }
    
    if (!$user_found) {
        // Don't reveal if account exists or not for security
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with this ' . $type . ', you will receive a reset code shortly.'
        ]);
        exit;
    }
    
    // Create password_resets table if it doesn't exist
    db_execute("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('User', 'Customer') NOT NULL,
        identifier_type ENUM('email', 'phone') NOT NULL,
        identifier VARCHAR(255) NOT NULL,
        reset_code VARCHAR(6) NOT NULL,
        used TINYINT(1) DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_reset (user_id, user_type, identifier)
    )");
    
    // Generate 6-digit reset code
    $reset_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Remove any existing reset codes for this user
    db_execute("DELETE FROM password_resets WHERE user_id = ? AND user_type = ?", 'is', [$user_found['user_id'], $user_type]);
    
    // Store new reset code
    db_execute("INSERT INTO password_resets (user_id, user_type, identifier_type, identifier, reset_code, expires_at) 
               VALUES (?, ?, ?, ?, ?, ?)", 'isssss', [
        $user_found['user_id'],
        $user_type,
        $type,
        $identifier,
        $reset_code,
        $expires_at
    ]);
    
    // Send reset code
    $email_sent = false;
    $sms_sent = false;
    
    if ($type === 'email') {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings (use SMTP for production)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Change to your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your-email@gmail.com'; // Change to your email
            $mail->Password   = 'your-app-password'; // Use App Password for Gmail
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // For local development, use PHP's mail() function
            // Comment out the SMTP settings above and uncomment the line below:
            // $mail->isMail();
            
            // Recipients
            $mail->setFrom('noreply@printflow.com', 'PrintFlow');
            $mail->addAddress($identifier, $user_found['full_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'PrintFlow - Password Reset Code';
            $mail->Body    = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; margin: 0; padding: 0; background: #f3f4f6; }
                    .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
                    .header { background: linear-gradient(135deg, #6366f1, #7c3aed); color: white; padding: 32px 24px; text-align: center; }
                    .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
                    .content { padding: 32px 24px; color: #374151; line-height: 1.6; }
                    .code-box { background: #f9fafb; border: 2px dashed #6366f1; border-radius: 10px; padding: 24px; text-align: center; margin: 24px 0; }
                    .code { font-size: 32px; font-weight: 700; color: #6366f1; letter-spacing: 8px; font-family: 'Courier New', monospace; }
                    .warning { background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; margin: 24px 0; border-radius: 6px; font-size: 14px; color: #991b1b; }
                    .footer { padding: 24px; text-align: center; font-size: 12px; color: #9ca3af; background: #f9fafb; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Password Reset Request</h1>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>" . htmlspecialchars($user_found['full_name']) . "</strong>,</p>
                        <p>We received a request to reset your PrintFlow account password. Use the code below to proceed:</p>
                        
                        <div class='code-box'>
                            <div class='code'>" . $reset_code . "</div>
                            <p style='margin: 8px 0 0 0; color: #6b7280; font-size: 14px;'>Valid for 15 minutes</p>
                        </div>
                        
                        <p>Enter this code on the password reset page to create your new password.</p>
                        
                        <div class='warning'>
                            <strong>⚠️ Security Notice:</strong> If you didn't request this password reset, please ignore this email and ensure your account is secure.
                        </div>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " PrintFlow. All rights reserved.</p>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            $mail->AltBody = "Hello " . $user_found['full_name'] . ",\n\nYour password reset code is: " . $reset_code . "\n\nThis code will expire in 15 minutes.\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\nPrintFlow Team";
            
            // For development: log instead of sending
            error_log("Password reset code for {$identifier}: {$reset_code}");
            
            // Uncomment for production:
            // $mail->send();
            $email_sent = true;
            
        } catch (Exception $e) {
            error_log("Failed to send reset email: " . $mail->ErrorInfo);
            $email_sent = false;
        }
    } else {
        // SMS sending (phone)
        error_log("Password reset SMS for {$identifier}: {$reset_code}");
        
        // In production, integrate with SMS service
        // Example for Semaphore SMS (Philippines):
        /*
        $apiKey = 'your-semaphore-api-key';
        $smsMessage = "Your PrintFlow reset code is " . $reset_code . ". Valid for 15 minutes. Do not share this code.";
        $result = send_sms($identifier, $smsMessage);
        $sms_sent = $result;
        */
        
        // Example for Twilio:
        /*
        require_once __DIR__ . '/vendor/autoload.php';
        $twilio = new Twilio\Rest\Client('ACCOUNT_SID', 'AUTH_TOKEN');
        $twilio->messages->create($identifier, [
            'from' => '+1234567890',
            'body' => "Your PrintFlow reset code is " . $reset_code . ". Valid for 15 minutes."
        ]);
        $sms_sent = true;
        */
        
        $sms_sent = true; // Simulated for development
    }
    
    $response = [
        'success' => true,
        'message' => 'Reset code sent successfully! Check your ' . $type . ' for the 6-digit code.'
    ];
    
    // Include debug info in development mode
    if (DEBUG_MODE) {
        $response['debug'] = [
            'reset_code' => $reset_code, // Remove in production!
            'expires_at' => $expires_at,
            'user_type' => $user_type,
            'email_sent' => $email_sent,
            'sms_sent' => $sms_sent
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}
