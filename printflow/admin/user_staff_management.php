<?php
/**
 * Admin User & Staff Management
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();

$error = '';
$success = '';

// Handle staff creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role']; // 'Admin' or 'Staff'
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        // Check if email exists
        $existing = db_query("SELECT user_id FROM users WHERE email = ?", 's', [$email]);
        
        if (!empty($existing)) {
            $error = 'Email already exists';
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            db_execute("INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'Activated', NOW(), NOW())",
                'sssss', [$first_name, $last_name, $email, $password_hash, $role]);
            
            $success = ucfirst($role) . ' account created successfully!';
        }
    }
}

// Get all users
$users = db_query("SELECT * FROM users ORDER BY created_at DESC");

$page_title = 'User & Staff Management - Admin';
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
        /* Modal Popup Styles */
        #user-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            padding: 16px;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        #user-modal-backdrop.is-open {
            display: flex;
            opacity: 1;
        }
        #user-modal-box {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255,255,255,0.05);
            max-width: 500px;
            width: 100%;
            padding: 32px;
            transform: scale(0.95) translateY(10px);
            transition: transform 0.25s ease, opacity 0.25s ease;
            opacity: 0;
            max-height: 90vh;
            overflow-y: auto;
        }
        #user-modal-backdrop.is-open #user-modal-box {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
        #user-modal-box .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        #user-modal-box .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        #user-modal-box .modal-close-x {
            width: 32px;
            height: 32px;
            border: none;
            background: #f3f4f6;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #6b7280;
            transition: all 0.15s;
            line-height: 1;
        }
        #user-modal-box .modal-close-x:hover {
            background: #e5e7eb;
            color: #1f2937;
        }
        #user-modal-box .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        #user-modal-box .form-group {
            margin-bottom: 18px;
        }
        #user-modal-box .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        #user-modal-box .form-group input,
        #user-modal-box .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            color: #1f2937;
            background: #f9fafb;
            transition: all 0.2s;
            outline: none;
        }
        #user-modal-box .form-group input:focus,
        #user-modal-box .form-group select:focus {
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        #user-modal-box .form-group input::placeholder {
            color: #9ca3af;
        }
        #user-modal-box .form-hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }
        #user-modal-box .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 26px;
        }
        #user-modal-box .modal-btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-align: center;
        }
        #user-modal-box .modal-btn-cancel {
            background: #f3f4f6;
            color: #4b5563;
            border: 1.5px solid #e5e7eb;
        }
        #user-modal-box .modal-btn-cancel:hover {
            background: #e5e7eb;
        }
        #user-modal-box .modal-btn-submit {
            background: #1f2937;
            color: #ffffff;
        }
        #user-modal-box .modal-btn-submit:hover {
            background: #111827;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        @media (max-width: 540px) {
            #user-modal-box { padding: 24px 20px; }
            #user-modal-box .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">User & Staff Management</h1>
            <button type="button" id="btn-open-user-modal" class="btn-primary">
                + Add New User/Staff
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

            <!-- Users Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3">ID</th>
                                <th class="text-left py-3">Name</th>
                                <th class="text-left py-3">Email</th>
                                <th class="text-left py-3">Role</th>
                                <th class="text-left py-3">Status</th>
                                <th class="text-left py-3">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3"><?php echo $user['user_id']; ?></td>
                                    <td class="py-3 font-medium">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </td>
                                    <td class="py-3"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="py-3">
                                        <span class="badge <?php echo $user['role'] === 'Admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3"><?php echo status_badge($user['status'], 'order'); ?></td>
                                    <td class="py-3"><?php echo format_date($user['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add User/Staff Modal Popup -->
<div id="user-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
    <div id="user-modal-box">
        <div class="modal-header">
            <h3 class="modal-title" id="user-modal-title">Create New User / Staff</h3>
            <button type="button" class="modal-close-x" id="btn-close-user-modal-x" aria-label="Close">✕</button>
        </div>
        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="create_staff" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label>First Name <span style="color:#ef4444">*</span></label>
                    <input type="text" name="first_name" required placeholder="e.g. Juan">
                </div>
                <div class="form-group">
                    <label>Last Name <span style="color:#ef4444">*</span></label>
                    <input type="text" name="last_name" required placeholder="e.g. Dela Cruz">
                </div>
            </div>

            <div class="form-group">
                <label>Email Address <span style="color:#ef4444">*</span></label>
                <input type="email" name="email" required placeholder="staff@printflow.com">
            </div>

            <div class="form-group">
                <label>Password <span style="color:#ef4444">*</span></label>
                <input type="password" name="password" minlength="8" required placeholder="Enter password">
                <p class="form-hint">Must be at least 8 characters</p>
            </div>

            <div class="form-group">
                <label>Role <span style="color:#ef4444">*</span></label>
                <select name="role" required>
                    <option value="Staff">Staff</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" id="btn-close-user-modal">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-submit">Create Account</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var backdrop = document.getElementById('user-modal-backdrop');
    var btnOpen = document.getElementById('btn-open-user-modal');
    var btnClose = document.getElementById('btn-close-user-modal');
    var btnCloseX = document.getElementById('btn-close-user-modal-x');
    if (!backdrop || !btnOpen) return;

    function openModal() {
        backdrop.style.display = 'flex';
        // Trigger reflow then add class for animation
        void backdrop.offsetWidth;
        backdrop.classList.add('is-open');
        var firstInput = backdrop.querySelector('input[type="text"]');
        if (firstInput) setTimeout(function() { firstInput.focus(); }, 150);
    }

    function closeModal() {
        backdrop.classList.remove('is-open');
        setTimeout(function() {
            if (!backdrop.classList.contains('is-open')) {
                backdrop.style.display = 'none';
            }
        }, 260);
    }

    btnOpen.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCloseX) btnCloseX.addEventListener('click', closeModal);

    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && backdrop.classList.contains('is-open')) closeModal();
    });

    // Auto-open modal if there was a validation error
    <?php if ($error): ?>
    openModal();
    <?php endif; ?>
})();
</script>

</body>
</html>
