<?php
/**
 * Staff Profile Page
 * PrintFlow - Printing Shop PWA
 * (Code similar to customer profile but for staff members)
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
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">My Profile</h1>

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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Profile Information -->
            <div class="card">
                <h2 class="text-xl font-bold mb-4">Profile Information</h2>
                
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" name="first_name" class="input-field" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                            <input type="text" name="middle_name" class="input-field" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                        <input type="text" name="last_name" class="input-field" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" class="input-field bg-gray-100" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                        <input type="tel" name="contact_number" class="input-field" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <textarea name="address" class="input-field" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <h2 class="text-xl font-bold mb-4">Change Password</h2>
                
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Password *</label>
                        <input type="password" name="current_password" class="input-field" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                        <input type="password" name="new_password" class="input-field" required minlength="8">
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="input-field" required minlength="8">
                    </div>

                    <button type="submit" class="btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Staff Information -->
        <div class="card mt-6">
            <h2 class="text-xl font-bold mb-4">Staff Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Staff ID</p>
                    <p class="font-semibold">#<?php echo $user['user_id']; ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Role</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($user['role']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Position</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Account Status</p>
                    <p><?php echo status_badge($user['status'], 'order'); ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Joined</p>
                    <p class="font-semibold"><?php echo format_date($user['created_at']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Last Updated</p>
                    <p class="font-semibold"><?php echo format_datetime($user['updated_at']); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
