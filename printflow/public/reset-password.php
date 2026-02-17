<?php
/**
 * Reset Password Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$valid_token = false;

// Verify token
if (!empty($token)) {
    $reset = db_query("SELECT * FROM password_resets WHERE token = ? AND is_used = 0 AND expires_at > NOW()", 's', [$token]);
    
    if (!empty($reset)) {
        $valid_token = true;
        $reset_data = $reset[0];
    } else {
        $error = 'Invalid or expired reset link';
    }
} else {
    $error = 'No reset token provided';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            // Update password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            if ($reset_data['user_type'] === 'Customer') {
                db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $reset_data['user_id']]);
            } else {
                db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", 'si', [$password_hash, $reset_data['user_id']]);
            }
            
            // Mark token as used
            db_execute("UPDATE password_resets SET is_used = 1 WHERE reset_id = ?", 'i', [$reset_data['reset_id']]);
            
            $success = 'Password reset successfully! You can now login with your new password.';
            $valid_token = false; // Hide form
        }
    }
}

$page_title = 'Reset Password - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-md mx-auto">
        <div class="card">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Reset Password</h1>
                <p class="text-gray-600">Enter your new password</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-4">
                        <a href="<?php echo $url_login ?? '/printflow/login/'; ?>" class="btn-primary block text-center">Go to Login</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($valid_token && !$success): ?>
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="input-field" 
                            placeholder="••••••••" 
                            required
                            minlength="8"
                        >
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>

                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="input-field" 
                            placeholder="••••••••" 
                            required
                            minlength="8"
                        >
                    </div>

                    <button type="submit" class="w-full btn-primary mb-4">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$valid_token && !$success): ?>
                <div class="text-center">
                    <a href="<?php echo $url_forgot_password ?? '/printflow/forgot-password/'; ?>" class="text-indigo-600 hover:text-indigo-700">Request a new reset link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
