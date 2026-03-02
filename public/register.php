<?php
/**
 * Customer Registration Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user_type = get_user_type();
    if ($user_type === 'Admin') {
        redirect('/printflow/admin/dashboard.php');
    } elseif ($user_type === 'Staff') {
        redirect('/printflow/staff/dashboard.php');
    } else {
        redirect('/printflow/customer/dashboard.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $reg_type = $_POST['reg_type'] ?? '';

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
                    redirect('/printflow/customer/dashboard.php');
                } else {
                    $error = $result['message'];
                }
            }
        } else {
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name  = sanitize($_POST['last_name'] ?? '');
            $email      = sanitize($_POST['email'] ?? '');
            $password   = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
                $error = 'Please fill in all required fields';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                $data = [
                    'first_name' => $first_name,
                    'middle_name' => sanitize($_POST['middle_name'] ?? ''),
                    'last_name' => $last_name,
                    'email' => $email,
                    'contact_number' => sanitize($_POST['contact_number'] ?? ''),
                    'password' => $password,
                    'gender' => sanitize($_POST['gender'] ?? ''),
                    'dob' => sanitize($_POST['dob'] ?? ''),
                ];
                $result = register_customer($data);
                if ($result['success']) {
                    redirect('/printflow/customer/dashboard.php');
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
    // Redirect back with error for modal flow
    if ($error) {
        $return_path = '/printflow/';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $ref = $_SERVER['HTTP_REFERER'];
            if (strpos($ref, '/printflow/') !== false) {
                $parsed = parse_url($ref);
                $return_path = isset($parsed['path']) ? $parsed['path'] : $return_path;
            }
        }
        $sep = (strpos($return_path, '?') !== false) ? '&' : '?';
        redirect($return_path . $sep . 'auth_modal=register&error=' . urlencode($error));
    }
}

$page_title = 'Register - PrintFlow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            padding: 1rem;
        }
        .auth-card {
            background: white;
            border-radius: 16px;
            padding: 40px 32px 32px;
            width: 100%;
            max-width: 420px;
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
            text-align: center; margin-bottom: 28px;
        }
        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
            padding: 10px 14px; border-radius: 10px; font-size: 13px;
            margin-bottom: 20px; font-weight: 500;
        }
        .form-group { margin-bottom: 16px; position: relative; }
        .form-row { display: flex; gap: 12px; }
        .form-row .form-group { flex: 1; }
        .form-input {
            width: 100%; padding: 12px 14px; font-size: 14px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            outline: none; transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit; color: #111827; background: #fff;
        }
        .form-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .form-input::placeholder { color: #b0b5bf; }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af;
            display: flex; align-items: center; padding: 2px;
        }
        .password-toggle:hover { color: #6b7280; }
        .btn-submit {
            width: 100%; padding: 12px; font-size: 15px; font-weight: 600;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: white; border: none; border-radius: 10px; cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            font-family: inherit; margin-top: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99,102,241,0.35);
        }
        .btn-submit:active { transform: translateY(0); }
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }
        .divider span {
            font-size: 11px; color: #9ca3af; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;
        }
        .social-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 11px; font-size: 14px; font-weight: 500;
            border: 1.5px solid #e5e7eb; border-radius: 10px; background: white;
            cursor: pointer; transition: all 0.2s; font-family: inherit; color: #374151;
        }
        .social-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .social-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
        .footer-text {
            text-align: center; margin-top: 24px; font-size: 13px; color: #9ca3af;
        }
        .footer-text a {
            color: #6366f1; font-weight: 600; text-decoration: none;
        }
        .footer-text a:hover { color: #4f46e5; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
    </div>
    <h1 class="auth-title">Create your account</h1>
    <p class="auth-subtitle">Join PrintFlow to start ordering prints</p>

    <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="reg_type" value="legacy">

        <div class="form-row">
            <div class="form-group">
                <input type="text" name="first_name" class="form-input" placeholder="First name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <input type="text" name="last_name" class="form-input" placeholder="Last name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <input type="email" name="email" class="form-input" placeholder="Email address" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <input type="tel" name="contact_number" class="form-input" placeholder="Contact number (optional)" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <div class="password-wrapper">
                <input type="password" id="password" name="password" class="form-input" placeholder="Password (min 8 characters)" required>
                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <div class="password-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit">Create account</button>
    </form>

    <div class="divider"><span>or continue with</span></div>

    <button type="button" class="social-btn" onclick="signUpWithGoogle()">
        <svg viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Google
    </button>

    <p class="footer-text">
        Already have an account? <a href="<?php echo $url_login ?? '/printflow/login/'; ?>">Sign in</a>
    </p>
</div>

<script>
function togglePassword(id) {
    const pw = document.getElementById(id);
    pw.type = pw.type === 'password' ? 'text' : 'password';
}

function signUpWithGoogle() {
    window.location.href = '/printflow/public/google_auth.php?action=register';
}
</script>
</body>
</html>
