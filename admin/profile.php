<?php
/**
 * Admin Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);

$admin_id = get_user_id();
$error = '';
$success = '';

// Get admin data
$admin = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$admin_id])[0];

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file = $_FILES['profile_picture'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type. Please upload JPG, PNG, GIF, or WEBP.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File too large. Maximum size is 5MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/../public/assets/uploads/profiles/';
            $filepath = $upload_dir . $filename;
            
            // Delete old picture if exists
            if (!empty($admin['profile_picture'])) {
                $old_file = $upload_dir . $admin['profile_picture'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                db_execute("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?", 'si', [$filename, $admin_id]);
                $success = 'Profile picture updated successfully!';
                $admin = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$admin_id])[0];
            } else {
                $error = 'Failed to upload file. Please try again.';
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

// Handle remove picture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_picture']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (!empty($admin['profile_picture'])) {
        $upload_dir = __DIR__ . '/../public/assets/uploads/profiles/';
        $old_file = $upload_dir . $admin['profile_picture'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
        db_execute("UPDATE users SET profile_picture = NULL, updated_at = NOW() WHERE user_id = ?", 'i', [$admin_id]);
        $success = 'Profile picture removed.';
        $admin = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$admin_id])[0];
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Server-side validation
    if (empty($first_name) || empty($last_name)) {
        $error = 'First and last names are required.';
    } elseif (!preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $first_name) || !preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $last_name)) {
        $error = 'Names must contain only letters.';
    } elseif (strlen($first_name) < 2 || strlen($first_name) > 50 || strlen($last_name) < 2 || strlen($last_name) > 50) {
        $error = 'Names must be between 2 and 50 characters.';
    } elseif (!preg_match("/^09\d{9}$/", $contact_number)) {
        $error = 'Contact number must be exactly 11 digits and start with 09.';
    } elseif (strlen($address) < 5 || strlen($address) > 150) {
        $error = 'Address must be between 5 and 150 characters.';
    } else {
        // Auto-capitalize first letter
        $first_name = ucfirst($first_name);
        $last_name = ucfirst($last_name);
        
        db_execute("UPDATE users SET first_name = ?, last_name = ?, contact_number = ?, address = ?, updated_at = NOW() WHERE user_id = ?",
            'ssssi', [$first_name, $last_name, $contact_number, $address, $admin_id]);
        
        $success = 'Personal information updated successfully!';
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $admin = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$admin_id])[0];
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password)) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($current_password, $admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match("/[A-Z]/", $new_password) || !preg_match("/[a-z]/", $new_password) || !preg_match("/[0-9]/", $new_password) || !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $new_password)) {
        $error = 'Password must include uppercase, lowercase, number, and special character.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        db_execute("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?", 'si', [$password_hash, $admin_id]);
        $success = 'Password updated successfully!';
    }
}

$user_initial = strtoupper(substr($admin['first_name'], 0, 1));
$profile_pic_url = !empty($admin['profile_picture']) 
    ? '/printflow/public/assets/uploads/profiles/' . $admin['profile_picture'] 
    : '';

$page_title = 'My Profile - PrintFlow Admin';
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
        .profile-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 40px 32px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 28px;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .profile-avatar-wrapper {
            position: relative;
            flex-shrink: 0;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
            background: rgba(255,255,255,0.15);
            overflow: hidden;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-edit-btn {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            border: 2px solid #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #667eea;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .avatar-edit-btn:hover {
            background: #667eea;
            color: white;
        }
        .profile-hero-info h2 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .profile-hero-info p {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            margin: 0;
        }
        .profile-hero-info .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            margin-top: 8px;
        }
        .section-card {
            background: white;
            border: 1px solid #f3f4f6;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title svg { color: #6b7280; }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .profile-hero { flex-direction: column; text-align: center; padding: 32px 20px; }
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .form-group input:disabled {
            background: #f9fafb;
            color: #9ca3af;
        }
        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-save:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
        .btn-danger-outline {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: transparent;
            color: #ef4444;
            border: 1px solid #ef4444;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-danger-outline:hover { background: #fef2f2; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
        }
        .info-item label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            display: block;
            margin-bottom: 4px;
        }
        .info-item span {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        
        /* Mobile Header */
        .mobile-header { display: none; }
        @media (max-width: 768px) {
            .mobile-header { display: flex; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #fff; z-index: 60; padding: 0 20px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e7eb; }
            .mobile-menu-btn { font-size: 24px; background: none; border: none; cursor: pointer; color: #1f2937; }
        }

        /* Picture upload modal */
        .upload-modal-overlay {
            position: fixed; top:0; left:0; right:0; bottom:0;
            background: rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999;
        }
        .upload-modal {
            background: white;
            border-radius: 12px;
            padding: 24px;
            width: 100%;
            max-width: 400px;
            margin: 16px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }

        /* Validation Styles */
        .form-group.is-invalid input, 
        .form-group.is-invalid textarea {
            border-color: #ef4444 !important;
            background-color: #fef2f2;
        }
        .form-group.is-valid input, 
        .form-group.is-valid textarea {
            border-color: #10b981 !important;
            background-color: #f0fdf4;
        }
        .error-message {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
            display: none;
            font-weight: 500;
        }
        .form-group.is-invalid .error-message {
            display: block;
        }
        .btn-save:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(1);
            transform: none !important;
            box-shadow: none !important;
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            transition: color 0.2s;
            z-index: 10;
        }
        .password-toggle:hover {
            color: #667eea;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span style="font-weight:600;font-size:18px;">PrintFlow</span>
        </div>
    </div>

    <!-- Sidebar -->
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">My Profile</h1>
        </header>

        <main>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Profile Hero -->
            <div class="profile-hero">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar">
                        <?php if ($profile_pic_url): ?>
                            <img src="<?php echo $profile_pic_url; ?>?t=<?php echo time(); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo $user_initial; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="avatar-edit-btn" onclick="document.getElementById('pictureModal').style.display='flex'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </button>
                </div>
                <div class="profile-hero-info">
                    <h2><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h2>
                    <p><?php echo htmlspecialchars($admin['email']); ?></p>
                    <div class="role-badge">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <?php echo $admin['role']; ?>
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <!-- Profile Information -->
                <div class="section-card">
                    <div class="section-title">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Personal Information
                    </div>
                    <form method="POST" id="personalInfoForm" onsubmit="return validatePersonalInfoForm(event)">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group" id="group_first_name">
                                <label>First Name *</label>
                                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required autocomplete="given-name">
                                <div class="error-message" id="error_first_name">First name is required.</div>
                            </div>
                            <div class="form-group" id="group_last_name">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required autocomplete="family-name">
                                <div class="error-message" id="error_last_name">Last name is required.</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Email cannot be changed</p>
                        </div>
                        
                        <div class="form-group" id="group_contact_number">
                            <label>Contact Number *</label>
                            <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($admin['contact_number'] ?? ''); ?>" placeholder="e.g. 09171234567" required autocomplete="tel">
                            <div class="error-message" id="error_contact_number">Contact number is required.</div>
                        </div>
                        
                        <div class="form-group" id="group_address">
                            <label>Address *</label>
                            <textarea name="address" id="address" rows="2" placeholder="Enter your address" required><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                            <div class="error-message" id="error_address">Address is required.</div>
                        </div>
                        
                        <button type="submit" class="btn-save" id="btn_save_profile">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Save Changes
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="section-card">
                    <div class="section-title">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Change Password
                    </div>
                    <form method="POST" id="passwordForm" onsubmit="return validatePasswordForm(event)">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group" id="group_current_password">
                            <label>Current Password *</label>
                            <div class="password-wrapper">
                                <input type="password" name="current_password" id="current_password" required autocomplete="current-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="error-message" id="error_current_password">Current password is required.</div>
                        </div>
                        
                        <div class="form-group" id="group_new_password">
                            <label>New Password *</label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="new_password" required autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <p id="password_requirements" style="font-size:11px;color:#9ca3af;margin-top:4px;">Min. 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol</p>
                            <div class="error-message" id="error_new_password">Invalid password format.</div>
                        </div>
                        
                        <div class="form-group" id="group_confirm_password">
                            <label>Confirm New Password *</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div class="error-message" id="error_confirm_password">Passwords do not match.</div>
                        </div>
                        
                        <button type="submit" class="btn-save" id="btn_update_password">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Update Password
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Picture Upload Modal -->
<div id="pictureModal" style="display:none;" class="upload-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="upload-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:16px;font-weight:700;margin:0;">Profile Picture</h3>
            <button onclick="document.getElementById('pictureModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="upload_picture" value="1">
            
            <div style="border:2px dashed #e5e7eb;border-radius:8px;padding:24px;text-align:center;margin-bottom:16px;" id="dropZone">
                <svg width="40" height="40" fill="none" stroke="#9ca3af" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p style="font-size:13px;color:#6b7280;margin:0 0 8px;">Click to select or drag a photo</p>
                <p style="font-size:11px;color:#9ca3af;margin:0;">JPG, PNG, GIF, WEBP (max 5MB)</p>
                <input type="file" name="profile_picture" accept="image/*" id="fileInput" style="position:absolute;opacity:0;pointer-events:none;">
            </div>
            
            <div id="previewArea" style="display:none;text-align:center;margin-bottom:16px;">
                <img id="previewImg" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;">
                <p id="fileName" style="font-size:12px;color:#6b7280;margin-top:8px;"></p>
            </div>
            
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn-save" style="flex:1;justify-content:center;">Upload</button>
                <?php if (!empty($admin['profile_picture'])): ?>
                    <button type="submit" name="remove_picture" value="1" class="btn-danger-outline" onclick="this.form.querySelector('[name=upload_picture]').disabled=true;">Remove</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
    // File input handling
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const previewArea = document.getElementById('previewArea');
    const previewImg = document.getElementById('previewImg');
    const fileName = document.getElementById('fileName');
    
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor = '#667eea'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = '#e5e7eb'; });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#e5e7eb';
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) showPreview(e.target.files[0]);
    });
    
    function showPreview(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            fileName.textContent = file.name;
            previewArea.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    // --- Validation Logic ---

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
        contact_number: (val) => {
            if (!val) return "Contact number is required.";
            if (!/^\d+$/.test(val)) return "Contact number must contain digits only.";
            if (!val.startsWith('09')) return "Contact number must start with 09.";
            if (val.length !== 11) return "Contact number must be exactly 11 digits.";
            return null;
        },
        address: (val) => {
            if (!val) return "Address is required.";
            if (val.length < 5) return "Address must be at least 5 characters.";
            if (val.length > 150) return "Address cannot exceed 150 characters.";
            return null;
        },
        new_password: (val) => {
            if (!val) return "New password is required.";
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
        const group = document.getElementById('group_' + id);
        const error = document.getElementById('error_' + id);
        let val = input.value;

        // Auto-formatting for names
        if (id === 'first_name' || id === 'last_name') {
            // Block leading spaces
            if (val.startsWith(' ')) {
                val = val.trimStart();
            }
            // Auto capitalize
            if (val.length > 0) {
                val = val.charAt(0).toUpperCase() + val.slice(1);
            }
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

    function checkPersonalInfo() {
        const fValid = validateField('first_name', validators.first_name);
        const lValid = validateField('last_name', validators.last_name);
        const cValid = validateField('contact_number', validators.contact_number);
        const aValid = validateField('address', validators.address);
        document.getElementById('btn_save_profile').disabled = !(fValid && lValid && cValid && aValid);
    }

    function checkPassword() {
        const nValid = validateField('new_password', validators.new_password);
        const confirm = document.getElementById('confirm_password');
        const current = document.getElementById('current_password');
        
        // Confirm check
        const cGroup = document.getElementById('group_confirm_password');
        const cError = document.getElementById('error_confirm_password');
        const match = confirm.value === document.getElementById('new_password').value;
        
        if (confirm.value && !match) {
            cGroup.classList.add('is-invalid');
            cError.textContent = "Passwords do not match.";
        } else if (confirm.value) {
            cGroup.classList.remove('is-invalid');
            cGroup.classList.add('is-valid');
        }

        document.getElementById('btn_update_password').disabled = !(nValid && match && current.value);
    }

    // Listeners
    ['first_name', 'last_name', 'contact_number', 'address'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener('input', checkPersonalInfo);
        el.addEventListener('blur', checkPersonalInfo);
    });

    ['current_password', 'new_password', 'confirm_password'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener('input', checkPassword);
        el.addEventListener('blur', checkPassword);
    });

    // Initial check
    checkPersonalInfo();
    checkPassword();

    function validatePersonalInfoForm(e) {
        checkPersonalInfo();
        if (document.getElementById('btn_save_profile').disabled) {
            e.preventDefault();
            return false;
        }
        return true;
    }

    function validatePasswordForm(e) {
        checkPassword();
        if (document.getElementById('btn_update_password').disabled) {
            e.preventDefault();
            return false;
        }
        return true;
    }

    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('svg');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        }
    }
</script>

</body>
</html>
