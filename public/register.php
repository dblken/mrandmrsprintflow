<?php
/**
 * register.php
 * User (Admin/Staff) Registration Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect Admin, Manager, and Staff away from registration
redirect_admin_staff_from_public();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Clear old OTP data to prevent "sticky" email from previous attempts
        unset($_SESSION['otp_pending_email']);
        unset($_SESSION['otp_user_type']);
        
        $reg_type = $_POST['reg_type'] ?? 'user'; // 'direct' or 'legacy' are customers

        if ($reg_type === 'direct' || $reg_type === 'legacy') {
            // --- Customer Registration (Existing Flow) ---
            if ($reg_type === 'direct') {
                $identifier_type = sanitize($_POST['identifier_type'] ?? '');
                $identifier      = sanitize($_POST['identifier'] ?? '');
                $password        = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if (empty($identifier_type) || empty($identifier) || empty($password)) {
                    $error = 'Please fill in all fields.';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    $result = register_customer_direct($identifier_type, $identifier, $password);
                    if ($result['success']) {
                        $_SESSION['otp_pending_email'] = ($identifier_type === 'email') ? $identifier : ($identifier . '@phone.local');
                        $_SESSION['otp_user_type'] = 'Customer';
                        redirect('/printflow/public/verify_email.php');
                    } else {
                        $error = $result['message'];
                    }
                }
            } else {
                // ... handle legacy customer reg if needed ...
                $error = "Registration type not supported here.";
            }
        } else {
            // --- User (Admin/Staff) Registration (New Flow) ---
            // Redirect to process_register.php or handle here. 
            // For simplicity and matching the prompt, let's let process_register.php handle it 
            // BUT if it's a GET we show the form.
        }
    }
    
    // If there was an error and it's from the modal, redirect back
    if ($error && !empty($_SERVER['HTTP_REFERER'])) {
        $return_path = '/printflow/';
        $sep = (strpos($return_path, '?') !== false) ? '&' : '?';
        redirect($return_path . $sep . 'auth_modal=register&error=' . urlencode($error));
    }
} else {
    // Clear OTP session data on fresh page load to prevent "sticky" email
    unset($_SESSION['otp_pending_email']);
    unset($_SESSION['otp_user_type']);
}

$page_title = "User Registration - PrintFlow";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #00151b;
            padding: 1rem;
        }
        .auth-card {
            background: white;
            border-radius: 16px;
            padding: 40px 32px 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
        }
        .auth-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .auth-icon svg { width: 22px; height: 22px; color: white; }
        .auth-title {
            font-size: 22px; font-weight: 700; color: #111827;
            text-align: center; margin-bottom: 4px;
        }
        .auth-subtitle {
            font-size: 14px; color: #9ca3af;
            text-align: center; margin-bottom: 24px;
        }
        .alert { padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; font-weight: 500; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .form-group { margin-bottom: 14px; position: relative; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 5px; }
        input, select {
            width: 100%; padding: 11px 14px; font-size: 14px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            outline: none; transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit; color: #111827; background: #fff;
            box-sizing: border-box;
        }
        input::placeholder { color: #b0b5bf; }
        input:focus, select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        button[type="submit"] {
            width: 100%; padding: 12px; font-size: 15px; font-weight: 600;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: white; border: none; border-radius: 10px; cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            font-family: inherit; margin-top: 6px;
        }
        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99,102,241,0.35);
        }
        button[type="submit"]:active { transform: translateY(0); }
        button[type="submit"]:disabled {
            opacity: 0.5; cursor: not-allowed;
            transform: none; box-shadow: none;
        }
        .footer { text-align: center; margin-top: 20px; font-size: 13px; color: #9ca3af; }
        .footer a { color: #6366f1; font-weight: 600; text-decoration: none; }
        .footer a:hover { color: #4f46e5; }

        /* Validation Styles */
        .form-group.is-invalid input,
        .form-group.is-invalid select {
            border-color: #f87171 !important;
            box-shadow: 0 0 0 3px rgba(248,113,113,0.1) !important;
        }
        .form-group.is-valid input,
        .form-group.is-valid select {
            border-color: #4ade80 !important;
            box-shadow: 0 0 0 3px rgba(74,222,128,0.1) !important;
        }
        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 4px;
            display: none;
            font-weight: 500;
        }
        .form-group.is-invalid .error-message { display: block; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        </div>
        <h1 class="auth-title">Create Staff Account</h1>
        <p class="auth-subtitle">Register a new admin or staff member</p>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        
        <form action="process_register.php" method="POST" id="registerForm" onsubmit="return validateRegisterForm(event)">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reg_type" value="staff">
            
            <div class="form-group" id="group_first_name">
                <label>First Name *</label>
                <input type="text" name="first_name" id="first_name" required autocomplete="given-name">
                <div class="error-message" id="error_first_name">First name is required.</div>
            </div>
            <div class="form-group" id="group_last_name">
                <label>Last Name *</label>
                <input type="text" name="last_name" id="last_name" required autocomplete="family-name">
                <div class="error-message" id="error_last_name">Last name is required.</div>
            </div>
            <div class="form-group" id="group_email">
                <label>Email Address *</label>
                <input type="email" name="email" id="email" required autocomplete="email">
                <div class="error-message" id="error_email">Valid email is required.</div>
            </div>
            <div class="form-group" id="group_password">
                <label>Password *</label>
                <input type="password" name="password" id="password" required autocomplete="new-password">
                <p id="password_requirements" style="font-size:11px;color:#9ca3af;margin-top:4px;">Min. 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol</p>
                <div class="error-message" id="error_password">Invalid password format.</div>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="Staff">Staff</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit" id="btn_register">Register</button>
        </form>
        <div class="footer">
            Already have an account? <a href="/printflow/">Sign in</a>
        </div>
    </div>
    <script>
    const validators = {
        first_name: (val) => {
            if (!val) return "First name is required.";
            if (!/^[A-Za-z]+( [A-Za-z]+)*$/.test(val)) return "First name must contain only letters.";
            if (val.length < 2 || val.length > 50) return "First name must be between 2 and 50 characters.";
            return null;
        },
        last_name: (val) => {
            if (!val) return "Last name is required.";
            if (!/^[A-Za-z]+( [A-Za-z]+)*$/.test(val)) return "Last name must contain only letters.";
            if (val.length < 2 || val.length > 50) return "Last name must be between 2 and 50 characters.";
            return null;
        },
        email: (val) => {
            if (!val) return "Email is required.";
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return "Invalid email address.";
            return null;
        },
        password: (val) => {
            if (!val) return "Password is required.";
            if (val.length < 8) return "Password must be at least 8 characters.";
            if (!/[A-Z]/.test(val)) return "Password must have an uppercase letter.";
            if (!/[a-z]/.test(val)) return "Password must have a lowercase letter.";
            if (!/[0-9]/.test(val)) return "Password must have a number.";
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) return "Password must have a special character.";
            return null;
        }
    };

    function validateField(id, validator) {
        const input = document.getElementById(id);
        if (!input) return true;
        const group = document.getElementById('group_' + id);
        const error = document.getElementById('error_' + id);
        let val = input.value;

        // Auto-formatting for names
        if (id === 'first_name' || id === 'last_name') {
            if (val.startsWith(' ')) val = val.trimStart();
            if (val.length > 0) val = val.charAt(0).toUpperCase() + val.slice(1);
            input.value = val;
        }

        // Block leading spaces for all
        if (val.startsWith(' ')) {
            input.value = val.trimStart();
            val = input.value;
        }

        const errorMessage = validator(val.trim());
        if (errorMessage) {
            group.classList.add('is-invalid');
            group.classList.remove('is-valid');
            error.textContent = errorMessage;
            return false;
        } else {
            group.classList.remove('is-invalid');
            group.classList.add('is-valid');
            return true;
        }
    }

    function checkForm() {
        const fValid = validateField('first_name', validators.first_name);
        const lValid = validateField('last_name', validators.last_name);
        const eValid = validateField('email', validators.email);
        const pValid = validateField('password', validators.password);
        document.getElementById('btn_register').disabled = !(fValid && lValid && eValid && pValid);
    }

    // Listeners
    ['first_name', 'last_name', 'email', 'password'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', checkForm);
            el.addEventListener('blur', checkForm);
        }
    });

    function validateRegisterForm(e) {
        checkForm();
        if (document.getElementById('btn_register').disabled) {
            e.preventDefault();
            return false;
        }
        return true;
    }

    // Initial check
    checkForm();
    </script>
</body>
</html>
