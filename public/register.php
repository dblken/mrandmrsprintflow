<?php
/**
 * Customer Registration Page (handles both modal AJAX and direct form POST)
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
        $reg_type = $_POST['reg_type'] ?? ''; // 'direct' or 'legacy'
        
        if ($reg_type === 'direct') {
            // New Direct registration (no validation)
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
            // Legacy registration (keep for backward compat)
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

// If accessed directly (GET), show simple register page
$page_title = 'Register - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="min-height:100vh; background: linear-gradient(135deg, #00151b 0%, #00232b 60%, #003a47 100%); display:flex; align-items:center; justify-content:center; padding:2rem;">
    <div style="background:white; border-radius:1.25rem; padding:2.5rem 2rem; max-width:400px; width:100%; text-align:center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="width:60px;height:60px;background:#53C5E0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
            <svg style="width:28px;height:28px;color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#111827;margin-bottom:0.5rem;">Create Account</h1>
        <p style="color:#6b7280;margin-bottom:1.5rem;font-size:0.95rem;">Click below to open the registration form.</p>
        <a href="/printflow/?auth_modal=register" style="display:inline-block;padding:0.75rem 2rem;background:#53C5E0;color:white;border-radius:0.625rem;font-weight:600;font-size:0.95rem;text-decoration:none;transition:background 0.2s;">Register Now</a>
        <p style="margin-top:1.25rem;font-size:0.875rem;color:#9ca3af;">Already have an account? <a href="/printflow/?auth_modal=login" style="color:#53C5E0;font-weight:600;">Login</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
