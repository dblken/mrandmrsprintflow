<?php
/**
 * register.php
 * User (Admin/Staff) Registration Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; text-align: center; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        input, select { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #32a1c4; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; margin-top: 1rem; }
        .alert { padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-error { background: #fee2e2; color: #b91c1c; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .footer { text-align: center; margin-top: 1rem; font-size: 0.875rem; }
        .footer a { color: #32a1c4; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Create Staff Account</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        
        <form action="process_register.php" method="POST">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="Staff">Staff</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit">Register</button>
        </form>
        <div class="footer">
            Already have an account? <a href="/printflow/">Sign in</a>
        </div>
    </div>
</body>
</html>
