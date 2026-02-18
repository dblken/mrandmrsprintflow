<?php
/**
 * Staff Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

$user_id = get_user_id();
$error = '';
$success = '';

$user = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$user_id])[0];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required';
        } else {
            $result = db_execute("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, address = ? WHERE user_id = ?",
                'sssssi', [$first_name, $middle_name, $last_name, $contact_number, $address, $user_id]);
            
            if ($result) {
                $success = 'Profile updated successfully!';
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $user = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$user_id])[0];
            } else {
                $error = 'Failed to update profile';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } else {
            $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $result = db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", 'si', [$password_hash, $user_id]);
            
            if ($result !== false) {
                $success = 'Password changed successfully!';
                log_activity($user_id, 'Password Change', 'Staff member changed password');
            } else {
                $error = 'Failed to change password';
            }
        }
    }
}

$page_title = 'My Profile - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        @media (max-width: 900px) { .profile-grid { grid-template-columns:1fr; } }
        .info-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; }
        @media (max-width: 768px) { .info-grid { grid-template-columns:1fr; } }
        .info-item p:first-child { font-size:12px; color:#9ca3af; margin-bottom:4px; }
        .info-item p:last-child { font-size:14px; font-weight:600; color:#1f2937; }
        textarea.input-field { resize: vertical; min-height: 80px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">My Profile</h1>
        </header>

        <main>
            <?php if (isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'Pending'): ?>
                <div style="background:linear-gradient(135deg, #fef3c7, #fde68a); border:1px solid #f59e0b; border-radius:12px; padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px;">
                    <div style="font-size:32px;">⏳</div>
                    <div>
                        <h3 style="font-weight:700; color:#92400e; margin-bottom:4px; font-size:16px;">Account Pending Approval</h3>
                        <p style="font-size:13px; color:#92400e; line-height:1.5;">Please complete your profile information below. Once submitted, an administrator will review and approve your account. You'll then have full access to the staff panel.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Profile Information -->
                <div class="card">
                    <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Profile Information</h2>
                    
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px;">
                            <div>
                                <label>First Name *</label>
                                <input type="text" name="first_name" class="input-field" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            </div>
                            <div>
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="input-field" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" class="input-field" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>Email</label>
                            <input type="email" class="input-field" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#f3f4f6; cursor:not-allowed;">
                            <p style="font-size:11px; color:#9ca3af; margin-top:4px;">Email cannot be changed</p>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>Contact Number</label>
                            <input type="tel" name="contact_number" class="input-field" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                        </div>

                        <div style="margin-bottom:20px;">
                            <label>Address</label>
                            <textarea name="address" class="input-field"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn-primary">Update Profile</button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Change Password</h2>
                    
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">
                        
                        <div style="margin-bottom:16px;">
                            <label>Current Password *</label>
                            <input type="password" name="current_password" class="input-field" required>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>New Password *</label>
                            <input type="password" name="new_password" class="input-field" required minlength="8">
                            <p style="font-size:11px; color:#9ca3af; margin-top:4px;">Minimum 8 characters</p>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label>Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="input-field" required minlength="8">
                        </div>

                        <button type="submit" class="btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Staff Information -->
            <div class="card" style="margin-top:24px;">
                <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Staff Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <p>Staff ID</p>
                        <p>#<?php echo $user['user_id']; ?></p>
                    </div>
                    <div class="info-item">
                        <p>Role</p>
                        <p><?php echo htmlspecialchars($user['role']); ?></p>
                    </div>
                    <div class="info-item">
                        <p>Position</p>
                        <p><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="info-item">
                        <p>Account Status</p>
                        <p><?php echo status_badge($user['status'], 'order'); ?></p>
                    </div>
                    <div class="info-item">
                        <p>Joined</p>
                        <p><?php echo format_date($user['created_at']); ?></p>
                    </div>
                    <div class="info-item">
                        <p>Last Updated</p>
                        <p><?php echo format_datetime($user['updated_at']); ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
