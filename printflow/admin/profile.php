wwwww<?php
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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required';
    } else {
        db_execute("UPDATE users SET first_name = ?, last_name = ?, updated_at = NOW() WHERE user_id = ?",
            'ssi', [$first_name, $last_name, $admin_id]);
        
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
        $error = 'Current password is incorrect';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        db_execute("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?", 'si', [$password_hash, $admin_id]);
        $success = 'Password changed successfully!';
    }
}

$page_title = 'Admin Profile - PrintFlow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">My Profile</h1>
            <button form="profile-form" type="submit" class="btn-primary">
                Update Profile
            </button>
        </header>

        <main>
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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Profile Information -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Profile Information</h3>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">First Name *</label>
                            <input type="text" name="first_name" class="input-field" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Last Name *</label>
                            <input type="text" name="last_name" class="input-field" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Email</label>
                            <input type="email" class="input-field bg-gray-100" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                            <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">Update Profile</button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Change Password</h3>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Current Password *</label>
                            <input type="password" name="current_password" class="input-field" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">New Password *</label>
                            <input type="password" name="new_password" class="input-field" minlength="8" required>
                            <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="input-field" minlength="8" required>
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Account Info -->
            <div class="card mt-6">
                <h3 class="text-lg font-bold mb-4">Account Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-600">User ID</p>
                        <p class="font-semibold">#<?php echo $admin['user_id']; ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Role</p>
                        <p class="font-semibold text-red-600"><?php echo $admin['role']; ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Status</p>
                        <p><?php echo status_badge($admin['status'], 'order'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Created</p>
                        <p class="font-semibold"><?php echo format_date($admin['created_at']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Last Updated</p>
                        <p class="font-semibold"><?php echo format_datetime($admin['updated_at']); ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
