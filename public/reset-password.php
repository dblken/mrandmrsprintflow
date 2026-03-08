<?php
/**
 * Reset Password Page
 * PrintFlow - Printing Shop PWA
 * Supports both token-based (legacy) and code-based (new) reset methods
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';
$valid = false;

// Check if code-based reset (new method)
$type = $_GET['type'] ?? '';
$identifier = $_GET['identifier'] ?? '';
$reset_code = $_POST['reset_code'] ?? '';

// Check if token-based reset (legacy method)
$token = $_GET['token'] ?? '';

// Determine which method
$is_code_based = !empty($identifier) && !empty($type);
$is_token_based = !empty($token);

if ($is_code_based) {
    // New code-based method
    $valid = true;
    $reset_method = 'code';
} elseif ($is_token_based) {
    // Legacy token-based method
    $reset = db_query("SELECT * FROM password_resets WHERE token = ? AND is_used = 0 AND expires_at > NOW()", 's', [$token]);
    
    if (!empty($reset)) {
        $valid = true;
        $reset_data = $reset[0];
        $reset_method = 'token';
    } else {
        $error = 'Invalid or expired reset link';
    }
} else {
    $error = 'Invalid reset request';
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
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
            if ($reset_method === 'code') {
                // Verify code and update password (code-based method)
                $reset_code = $_POST['reset_code'] ?? '';
                
                if (empty($reset_code)) {
                    $error = 'Please enter the reset code';
                } else {
                    // Check in password_resets table (works for both users and customers)
                    $reset_query = db_query("
                        SELECT pr.* 
                        FROM password_resets pr
                        WHERE pr.identifier = ? 
                        AND pr.reset_code = ? 
                        AND pr.used = 0 
                        AND pr.expires_at > NOW()
                        LIMIT 1
                    ", 'ss', [$identifier, $reset_code]);
                    
                    if (empty($reset_query)) {
                        $error = 'Invalid or expired reset code';
                    } else {
                        $reset_data = $reset_query[0];
                        $password_hash = password_hash($password, PASSWORD_BCRYPT);
                        
                        // Update password based on user type
                        if ($reset_data['user_type'] === 'Customer') {
                            db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $reset_data['user_id']]);
                        } else {
                            db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", 'si', [$password_hash, $reset_data['user_id']]);
                        }
                        
                        // Mark code as used
                        db_execute("UPDATE password_resets SET used = 1 WHERE id = ?", 'i', [$reset_data['id']]);
                        
                        // Invalidate any other active reset codes for this user
                        db_execute("UPDATE password_resets SET used = 1 WHERE user_id = ? AND user_type = ? AND used = 0", 'is', [$reset_data['user_id'], $reset_data['user_type']]);
                        
                        $success = 'Password reset successfully! You can now login with your new password.';
                        $valid = false;
                    }
                }
            } else {
                // Token-based method (legacy)
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                if ($reset_data['user_type'] === 'Customer') {
                    db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $reset_data['user_id']]);
                } else {
                    db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", 'si', [$password_hash, $reset_data['user_id']]);
                }
                
                // Mark token as used
                db_execute("UPDATE password_resets SET is_used = 1 WHERE reset_id = ?", 'i', [$reset_data['reset_id']]);
                
                $success = 'Password reset successfully! You can now login with your new password.';
                $valid = false;
            }
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

            <?php if ($valid && !$success): ?>
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    
                    <?php if ($reset_method === 'code'): ?>
                        <div class="mb-4">
                            <label for="reset_code" class="block text-sm font-medium text-gray-700 mb-2">
                                Reset Code <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="reset_code" 
                                name="reset_code" 
                                class="input-field text-center text-lg tracking-widest" 
                                placeholder="000000" 
                                required
                                maxlength="6"
                                pattern="[0-9]{6}"
                            >
                            <p class="text-xs text-gray-500 mt-1">Enter the 6-digit code sent to your <?php echo $type === 'email' ? 'email' : 'phone'; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="input-field pr-12" 
                                placeholder="••••••••" 
                                required
                                minlength="8"
                                oninput="checkPasswordStrength()"
                            >
                            <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="togglePassword('password')">
                                <svg id="eye-icon-password" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Password Strength Indicator -->
                        <div class="mt-2">
                            <div class="flex items-center gap-1 mb-1">
                                <div id="strength-bar-1" class="h-1 flex-1 rounded bg-gray-200 transition-all duration-200"></div>
                                <div id="strength-bar-2" class="h-1 flex-1 rounded bg-gray-200 transition-all duration-200"></div>
                                <div id="strength-bar-3" class="h-1 flex-1 rounded bg-gray-200 transition-all duration-200"></div>
                                <div id="strength-bar-4" class="h-1 flex-1 rounded bg-gray-200 transition-all duration-200"></div>
                            </div>
                            <p id="strength-text" class="text-xs text-gray-500">At least 8 characters</p>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="mt-2 space-y-1 text-xs">
                            <div id="req-length" class="flex items-center gap-2 text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>At least 8 characters</span>
                            </div>
                            <div id="req-uppercase" class="flex items-center gap-2 text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>One uppercase letter</span>
                            </div>
                            <div id="req-number" class="flex items-center gap-2 text-gray-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>One number</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="input-field pr-12" 
                                placeholder="••••••••" 
                                required
                                minlength="8"
                                oninput="checkPasswordMatch()"
                            >
                            <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="togglePassword('confirm_password')">
                                <svg id="eye-icon-confirm_password" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        <p id="match-text" class="text-xs text-gray-500 mt-1"></p>
                    </div>

                    <button type="submit" class="w-full btn-primary mb-4">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$valid && !$success): ?>
                <div class="text-center">
                    <a href="<?php echo $url_forgot_password ?? '/printflow/forgot-password/'; ?>" class="text-indigo-600 hover:text-indigo-700">Request a new reset link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById('eye-icon-' + fieldId);
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    } else {
        field.type = 'password';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    }
}

// Check password strength
function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthBars = [
        document.getElementById('strength-bar-1'),
        document.getElementById('strength-bar-2'),
        document.getElementById('strength-bar-3'),
        document.getElementById('strength-bar-4')
    ];
    const strengthText = document.getElementById('strength-text');
    
    // Requirements
    const hasLength = password.length >= 8;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?\":{}|<>]/.test(password);
    
    // Update requirement indicators
    updateRequirement('req-length', hasLength);
    updateRequirement('req-uppercase', hasUppercase);
    updateRequirement('req-number', hasNumber);
    
    // Calculate strength
    let strength = 0;
    if (hasLength) strength++;
    if (hasUppercase && hasLowercase) strength++;
    if (hasNumber) strength++;
    if (hasSpecial) strength++;
    
    // Reset all bars
    strengthBars.forEach(bar => {
        bar.style.backgroundColor = '#e5e7eb';
    });
    
    // Update bars based on strength
    if (strength === 0) {
        strengthText.textContent = 'Too weak';
        strengthText.className = 'text-xs text-gray-500';
    } else if (strength === 1) {
        strengthBars[0].style.backgroundColor = '#ef4444';
        strengthText.textContent = 'Weak';
        strengthText.className = 'text-xs text-red-600 font-medium';
    } else if (strength === 2) {
        strengthBars[0].style.backgroundColor = '#f59e0b';
        strengthBars[1].style.backgroundColor = '#f59e0b';
        strengthText.textContent = 'Fair';
        strengthText.className = 'text-xs text-orange-600 font-medium';
    } else if (strength === 3) {
        strengthBars[0].style.backgroundColor = '#3b82f6';
        strengthBars[1].style.backgroundColor = '#3b82f6';
        strengthBars[2].style.backgroundColor = '#3b82f6';
        strengthText.textContent = 'Good';
        strengthText.className = 'text-xs text-blue-600 font-medium';
    } else {
        strengthBars.forEach(bar => {
            bar.style.backgroundColor = '#10b981';
        });
        strengthText.textContent = 'Strong';
        strengthText.className = 'text-xs text-green-600 font-medium';
    }
    
    // Also check if passwords match
    checkPasswordMatch();
}

// Update requirement indicator
function updateRequirement(reqId, met) {
    const req = document.getElementById(reqId);
    if (!req) return;
    
    const icon = req.querySelector('svg');
    if (met) {
        req.className = 'flex items-center gap-2 text-green-600';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
    } else {
        req.className = 'flex items-center gap-2 text-gray-500';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
    }
}

// Check if passwords match
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchText = document.getElementById('match-text');
    
    if (confirmPassword.length === 0) {
        matchText.textContent = '';
        return;
    }
    
    if (password === confirmPassword) {
        matchText.textContent = '\u2713 Passwords match';
        matchText.className = 'text-xs text-green-600 font-medium mt-1';
    } else {
        matchText.textContent = '\u2717 Passwords do not match';
        matchText.className = 'text-xs text-red-600 font-medium mt-1';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
