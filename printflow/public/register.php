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
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $gender = sanitize($_POST['gender'] ?? '');
        $dob = sanitize($_POST['dob'] ?? '');
        
        // Validation
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
                'middle_name' => $middle_name,
                'last_name' => $last_name,
                'email' => $email,
                'contact_number' => $contact_number,
                'password' => $password,
                'gender' => $gender,
                'dob' => $dob
            ];
            
            $result = register_customer($data);
            
            if ($result['success']) {
                redirect('/printflow/customer/dashboard.php');
            } else {
                $error = $result['message'];
            }
        }
    }
    // Redirect back with modal params so modal can open with error (for modal flow)
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
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-2xl mx-auto">
        <!-- Registration Card -->
        <div class="card">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Create Account</h1>
                <p class="text-gray-600">Join PrintFlow and start ordering custom prints</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- First Name -->
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="input-field" 
                            required 
                            value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                        >
                    </div>

                    <!-- Middle Name -->
                    <div>
                        <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Middle Name
                        </label>
                        <input 
                            type="text" 
                            id="middle_name" 
                            name="middle_name" 
                            class="input-field"
                            value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>"
                        >
                    </div>

                    <!-- Last Name -->
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="input-field" 
                            required
                            value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                        >
                    </div>

                    <!-- Gender -->
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                            Gender
                        </label>
                        <select id="gender" name="gender" class="input-field">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (($_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Date of Birth -->
                    <div>
                        <label for="dob" class="block text-sm font-medium text-gray-700 mb-2">
                            Date of Birth
                        </label>
                        <input 
                            type="date" 
                            id="dob" 
                            name="dob" 
                            class="input-field"
                            value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>"
                        >
                    </div>

                    <!-- Contact Number -->
                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Contact Number
                        </label>
                        <input 
                            type="tel" 
                            id="contact_number" 
                            name="contact_number" 
                            class="input-field" 
                            placeholder="+63 123 456 7890"
                            value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>"
                        >
                    </div>
                </div>

                <!-- Email -->
                <div class="mt-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="input-field" 
                        placeholder="you@example.com" 
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="input-field" 
                            placeholder="••••••••" 
                            required
                            minlength="8"
                        >
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="input-field" 
                            placeholder="••••••••" 
                            required
                            minlength="8"
                        >
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full btn-primary mt-6">
                    Create Account
                </button>
            </form>

            <!-- Login Link -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Already have an account? 
                    <a href="<?php echo $url_login ?? '/printflow/login/'; ?>" class="text-primary-600 font-medium hover:text-primary-700">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
