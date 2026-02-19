<?php
/**
 * Forgot Password Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if email exists in customers or users table
            $customer = db_query("SELECT customer_id, first_name FROM customers WHERE email = ?", 's', [$email]);
            $user = db_query("SELECT user_id, first_name FROM users WHERE email = ?", 's', [$email]);
            
            if (!empty($customer)) {
                $user_id = $customer[0]['customer_id'];
                $user_type = 'Customer';
                $name = $customer[0]['first_name'];
            } elseif (!empty($user)) {
                $user_id = $user[0]['user_id'];
                $user_type = 'User';
                $name = $user[0]['first_name'];
            } else {
                // Don't reveal if email doesn't exist (security)
                $success = 'If that email exists in our system, you will receive a password reset link shortly.';
                goto show_page;
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save reset token
            db_execute("INSERT INTO password_resets (user_type, user_id, token, expires_at) VALUES (?, ?, ?, ?)", 
                'siss', [$user_type, $user_id, $token, $expires_at]);
            
            // Send reset email
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/printflow/reset-password/?token=" . $token;
            $message = "
                <p>Hi {$name},</p>
                <p>You requested to reset your password. Click the link below to proceed:</p>
                <p><a href='{$reset_link}' style='background: #4F46E5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
            ";
            
            send_email($email, 'Password Reset Request - PrintFlow', $message);
            
            $success = 'If that email exists in our system, you will receive a password reset link shortly.';
        }
    }
}

show_page:
$page_title = 'Forgot Password - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-md mx-auto">
        <div class="card">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Forgot Password?</h1>
                <p class="text-gray-600">Enter your email and we'll send you a reset link</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                
                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="input-field" 
                        placeholder="you@example.com" 
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>

                <button type="submit" class="w-full btn-primary mb-4">
                    Send Reset Link
                </button>

                <div class="text-center">
                    <a href="<?php echo $url_login ?? '/printflow/login/'; ?>" class="text-sm text-indigo-600 hover:text-indigo-700">← Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
