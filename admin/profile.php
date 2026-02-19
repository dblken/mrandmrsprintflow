<?php
/**
 * Admin Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

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
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $contact_number = sanitize($_POST['contact_number'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } else {
        db_execute("UPDATE users SET first_name = ?, last_name = ?, contact_number = ?, address = ?, updated_at = NOW() WHERE user_id = ?",
            'ssssi', [$first_name, $last_name, $contact_number, $address, $admin_id]);
        
        $success = 'Profile updated successfully!';
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $admin = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$admin_id])[0];
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        db_execute("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?", 'si', [$password_hash, $admin_id]);
        $success = 'Password changed successfully!';
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
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

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
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Email cannot be changed</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?php echo htmlspecialchars($admin['contact_number'] ?? ''); ?>" placeholder="e.g. 09171234567">
                        </div>
                        
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="2" placeholder="Enter your address"><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-save">
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
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label>Current Password *</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" name="new_password" minlength="8" required>
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Minimum 8 characters</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password *</label>
                            <input type="password" name="confirm_password" minlength="8" required>
                        </div>
                        
                        <button type="submit" class="btn-save">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="section-card">
                <div class="section-title">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Account Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <label>User ID</label>
                        <span>#<?php echo $admin['user_id']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Role</label>
                        <span style="color:#667eea;"><?php echo $admin['role']; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <span><?php echo status_badge($admin['status'], 'order'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Created</label>
                        <span><?php echo format_date($admin['created_at']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Last Updated</label>
                        <span><?php echo format_datetime($admin['updated_at']); ?></span>
                    </div>
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
</script>

</body>
</html>
