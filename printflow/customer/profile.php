<?php
/**
 * Customer Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();
$error = '';
$success = '';

// Get customer data
$customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $dob = sanitize($_POST['dob'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required';
        } else {
            $result = db_execute("UPDATE customers SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, dob = ?, gender = ? WHERE customer_id = ?",
                'ssssssi', [$first_name, $middle_name, $last_name, $contact_number, $dob, $gender, $customer_id]);
            
            if ($result) {
                $success = 'Profile updated successfully!';
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                // Refresh customer data
                $customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];
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
        
        if (empty($current_password) || empty($new_password)) {
            $error = 'All password fields are required';
        } elseif (!password_verify($current_password, $customer['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } else {
            $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $result = db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $customer_id]);
            
            if ($result) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password';
            }
        }
    }
}

$page_title = 'My Profile - PrintFlow';
$use_customer_css = true;
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
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="input-field" required value="<?php echo htmlspecialchars($customer['first_name']); ?>">
                        </div>
                        
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="input-field" value="<?php echo htmlspecialchars($customer['middle_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="input-field" required value="<?php echo htmlspecialchars($customer['last_name']); ?>">
                    </div>

                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="email" class="input-field bg-gray-100" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled>
                        <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                    </div>

                    <div class="mb-4">
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                        <input type="tel" id="contact_number" name="contact_number" class="input-field" value="<?php echo htmlspecialchars($customer['contact_number'] ?? ''); ?>">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="dob" class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="input-field" value="<?php echo htmlspecialchars($customer['dob'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                            <select id="gender" name="gender" class="input-field">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($customer['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($customer['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($customer['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
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
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" class="input-field" required>
                    </div>

                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                        <input type="password" id="new_password" name="new_password" class="input-field" required minlength="8">
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="input-field" required minlength="8">
                    </div>

                    <button type="submit" class="btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Account Information -->
        <div class="card mt-6">
            <h2 class="text-xl font-bold mb-4">Account Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Customer ID</p>
                    <p class="font-semibold">#<?php echo $customer['customer_id']; ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Account Status</p>
                    <p><?php echo status_badge($customer['status'], 'order'); ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Member Since</p>
                    <p class="font-semibold"><?php echo format_date($customer['created_at']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Last Updated</p>
                    <p class="font-semibold"><?php echo format_datetime($customer['updated_at']); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
