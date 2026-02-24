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
    
    // Parse branch_id safely
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    if ($role === 'Admin') $branch_id = null; // Admins have global access
    
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
            
            db_execute("INSERT INTO users (first_name, last_name, email, password_hash, role, status, branch_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'Activated', ?, NOW(), NOW())",
                'sssssi', [$first_name, $last_name, $email, $password_hash, $role, $branch_id]);
            
            $success = ucfirst($role) . ' account created successfully!';
        }
    }
}

// Get all users
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$total_users = db_query("SELECT COUNT(*) as total FROM users")[0]['total'];
$total_pages = max(1, ceil($total_users / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$users = db_query("
    SELECT u.*, b.branch_name 
    FROM users u 
    LEFT JOIN branches b ON u.branch_id = b.id 
    ORDER BY u.created_at DESC 
    LIMIT $per_page OFFSET $offset
");

// Fetch available branches for the creation dropdown
$branches = db_query("SELECT id, branch_name FROM branches ORDER BY id ASC");

// Summary statistics
$stat_total   = db_query("SELECT COUNT(*) as c FROM users")[0]['c'];
$stat_admins  = db_query("SELECT COUNT(*) as c FROM users WHERE role = 'Admin'")[0]['c'];
$stat_staff   = db_query("SELECT COUNT(*) as c FROM users WHERE role = 'Staff'")[0]['c'];
$stat_active  = db_query("SELECT COUNT(*) as c FROM users WHERE status = 'Activated'")[0]['c'];

$page_title = 'User & Staff Management - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        [x-cloak] { display: none !important; }

        /* KPI Cards – matching dashboard */
        .kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        @media(max-width:900px) { .kpi-row { grid-template-columns:repeat(2,1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-card.rose::before { background:linear-gradient(90deg,#e11d48,#fb7185); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-value { font-size:26px; font-weight:800; color:#1f2937; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }

        /* Action Button */
        .btn-action { display:inline-flex; align-items:center; gap:5px; padding:5px 14px; border:1.5px solid #6366f1; color:#6366f1; background:transparent; border-radius:20px; font-size:12px; font-weight:600; transition:all 0.18s; cursor:pointer; }
        .btn-action:hover { background:#6366f1; color:white; }

        /* ===== MINIMALISTIC VIEW MODAL ===== */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9900; align-items:center; justify-content:center; padding:16px; }
        .modal-overlay.is-open { display:flex; }
        .modal-box { background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.15); width:100%; max-width:580px; max-height:92vh; overflow-y:auto; }
        .modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid #f3f4f6; }
        .modal-hdr h2 { font-size:16px; font-weight:700; color:#111827; margin:0; }
        .modal-hdr button { background:none; border:none; font-size:20px; color:#9ca3af; cursor:pointer; line-height:1; padding:2px 6px; }
        .modal-hdr button:hover { color:#374151; }
        .modal-bdy { padding:20px 24px; }
        .mf-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
        .mf-full { grid-column:1/-1; }
        .mf-group label { display:block; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        .mf-group input, .mf-group select, .mf-group textarea { width:100%; padding:9px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; color:#111827; background:#fafafa; outline:none; transition:border-color .15s; box-sizing:border-box; }
        .mf-group input:focus, .mf-group select:focus, .mf-group textarea:focus { border-color:#6366f1; background:#fff; }
        .mf-group input:disabled { background:#f3f4f6; color:#9ca3af; cursor:not-allowed; }
        .mf-group textarea { resize:none; }
        .mf-divider { border:none; border-top:1px solid #f3f4f6; margin:16px 0; }
        .mf-footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px; border-top:1px solid #f3f4f6; }
        .mf-btn-cancel { padding:9px 18px; border:1px solid #e5e7eb; background:#fff; border-radius:8px; font-size:14px; font-weight:600; color:#374151; cursor:pointer; }
        .mf-btn-save { padding:9px 22px; border:none; border-radius:8px; background:#4f46e5; color:#fff; font-size:14px; font-weight:600; cursor:pointer; }
        .mf-btn-save:disabled { opacity:.5; cursor:not-allowed; }
        .mf-alert { padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:14px; }
        .mf-alert.ok { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
        .mf-alert.err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
        @media(max-width:520px) { .mf-row { grid-template-columns:1fr; } }

        /* Create-user modal */
        #user-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9800; justify-content:center; align-items:center; padding:16px; }
        #user-modal-backdrop.is-open { display:flex; }
        #user-modal-box { background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.15); max-width:500px; width:100%; max-height:90vh; overflow-y:auto; }
        #user-modal-box .modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid #f3f4f6; }
        #user-modal-box .modal-title { font-size:16px; font-weight:700; color:#111827; margin:0; }
        #user-modal-box .modal-close-x { background:none; border:none; font-size:20px; color:#9ca3af; cursor:pointer; }
        #user-modal-box .modal-close-x:hover { color:#374151; }
        #user-modal-box .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        #user-modal-box .form-group { margin-bottom:14px; padding:0 24px; }
        #user-modal-box .form-group:first-of-type { margin-top:20px; }
        #user-modal-box .form-group label { display:block; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        #user-modal-box .form-group input, #user-modal-box .form-group select { width:100%; padding:9px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; color:#1f2937; background:#fafafa; outline:none; box-sizing:border-box; transition:border-color .15s; }
        #user-modal-box .form-group input:focus, #user-modal-box .form-group select:focus { border-color:#4f46e5; background:#fff; }
        #user-modal-box .form-hint { font-size:11px; color:#9ca3af; margin-top:4px; }
        #user-modal-box .modal-actions { display:flex; gap:10px; padding:16px 24px; border-top:1px solid #f3f4f6; }
        #user-modal-box .modal-btn { flex:1; padding:10px 16px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; border:none; }
        #user-modal-box .modal-btn-cancel { background:#f3f4f6; color:#4b5563; border:1px solid #e5e7eb; }
        #user-modal-box .modal-btn-submit { background:#4f46e5; color:#fff; }
        @media(max-width:520px) { #user-modal-box .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body x-data="userManagement()">

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
            <!-- KPI Summary Cards (matching dashboard) -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Users</div>
                    <div class="kpi-value"><?php echo $stat_total; ?></div>
                    <div class="kpi-sub">All accounts</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Admins</div>
                    <div class="kpi-value"><?php echo $stat_admins; ?></div>
                    <div class="kpi-sub">Administrator roles</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Staff</div>
                    <div class="kpi-value"><?php echo $stat_staff; ?></div>
                    <div class="kpi-sub">Staff roles</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Active Accounts</div>
                    <div class="kpi-value"><?php echo $stat_active; ?></div>
                    <div class="kpi-sub">Activated users</div>
                </div>
            </div>

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
                                <th class="text-left py-3">Branch</th>
                                <th class="text-left py-3">Status</th>
                                <th class="text-left py-3 text-right">Actions</th>
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
                                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $user['role'] === 'Admin' ? 'background:#fee2e2;color:#991b1b;' : 'background:#dbeafe;color:#1e40af;'; ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($user['role'] === 'Admin'): ?>
                                            <span class="text-gray-500 italic">All Branches</span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($user['branch_name'] ?? 'Unassigned'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php
                                            $sc = match($user['status']) {
                                                'Activated'   => 'background:#dcfce7;color:#166534;',
                                                'Deactivated' => 'background:#fee2e2;color:#991b1b;',
                                                default       => 'background:#fef9c3;color:#854d0e;'
                                            };
                                        ?>
                                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $sc; ?>">
                                            <?php echo $user['status']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <button @click="viewUser(<?php echo $user['user_id']; ?>)" class="btn-action">View / Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php echo render_pagination($page, $total_pages); ?>
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
                <select name="role" id="user-role-select" required>
                    <option value="Staff">Staff</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <div class="form-group" id="branch-select-group">
                <label>Branch Assignment <span style="color:#ef4444">*</span></label>
                <select name="branch_id" id="user-branch-select">
                    <option value="">-- Select Branch --</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Staff members only see data for their assigned branch.</p>
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

    // Toggle Branch Select based on Role
    var roleSelect = document.getElementById('user-role-select');
    var branchGroup = document.getElementById('branch-select-group');
    if (roleSelect && branchGroup) {
        roleSelect.addEventListener('change', function() {
            if (this.value === 'Admin') {
                branchGroup.style.display = 'none';
                document.getElementById('user-branch-select').required = false;
            } else {
                branchGroup.style.display = 'block';
                document.getElementById('user-branch-select').required = true;
            }
        });
        // Trigger on load
        roleSelect.dispatchEvent(new Event('change'));
    }
})();
</script>

<!-- User Profile View/Edit Modal (Minimalistic) -->
<div x-show="viewModal.isOpen" x-cloak class="modal-overlay is-open" @click.self="viewModal.isOpen = false">
    <div class="modal-box" @click.stop>
        <!-- Header -->
        <div class="modal-hdr">
            <h2>User Details</h2>
            <button @click="viewModal.isOpen = false">&times;</button>
        </div>

        <!-- Loading -->
        <div x-show="viewModal.loading" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">
            Loading...
        </div>

        <div x-show="!viewModal.loading && viewModal.user">
            <div class="modal-bdy">
                <!-- Alerts -->
                <div x-show="viewModal.error" class="mf-alert err" x-text="viewModal.error"></div>
                <div x-show="viewModal.success" class="mf-alert ok" x-text="viewModal.success"></div>

                <form @submit.prevent="saveUserChanges">
                    <!-- Row 1: First, Middle, Last Name -->
                    <div class="mf-row" style="grid-template-columns:1fr 1fr 1fr;">
                        <div class="mf-group">
                            <label>First Name *</label>
                            <input type="text" x-model="viewModal.user.first_name" required>
                        </div>
                        <div class="mf-group">
                            <label>Middle Name</label>
                            <input type="text" x-model="viewModal.user.middle_name">
                        </div>
                        <div class="mf-group">
                            <label>Last Name *</label>
                            <input type="text" x-model="viewModal.user.last_name" required>
                        </div>
                    </div>

                    <!-- Row 2: Email (read-only) + Contact -->
                    <div class="mf-row">
                        <div class="mf-group">
                            <label>Email Address</label>
                            <input type="email" :value="viewModal.user.email" disabled>
                        </div>
                        <div class="mf-group">
                            <label>Contact Number</label>
                            <input type="text" x-model="viewModal.user.contact_number" placeholder="e.g. 09XX-XXX-XXXX">
                        </div>
                    </div>

                    <!-- Row 3: DOB + Gender -->
                    <div class="mf-row">
                        <div class="mf-group">
                            <label>Date of Birth</label>
                            <input type="date" x-model="viewModal.user.dob">
                        </div>
                        <div class="mf-group">
                            <label>Gender</label>
                            <select x-model="viewModal.user.gender">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 4: Address (full width) -->
                    <div class="mf-row">
                        <div class="mf-group mf-full">
                            <label>Address</label>
                            <textarea x-model="viewModal.user.address" rows="2" placeholder="Street, City, Province"></textarea>
                        </div>
                    </div>

                    <hr class="mf-divider">

                    <!-- Row 5: Role + Branch -->
                    <div class="mf-row">
                        <div class="mf-group">
                            <label>Role *</label>
                            <select x-model="viewModal.user.role" required>
                                <option value="Staff">Staff</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <div class="mf-group" x-show="viewModal.user.role === 'Staff'">
                            <label>Branch Assignment</label>
                            <select x-model="viewModal.user.branch_id">
                                <option value="">-- No Branch --</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mf-group" x-show="viewModal.user.role === 'Admin'">
                            <label>Branch</label>
                            <input type="text" value="All Branches" disabled>
                        </div>
                    </div>

                    <!-- Row 6: Account Status (dropdown) + Member Since -->
                    <div class="mf-row">
                        <div class="mf-group">
                            <label>Account Status</label>
                            <select x-model="viewModal.user.status">
                                <option value="Activated">Activated</option>
                                <option value="Pending">Pending</option>
                                <option value="Deactivated">Deactivated</option>
                            </select>
                        </div>
                        <div class="mf-group">
                            <label>Member Since</label>
                            <input type="text" :value="viewModal.user ? new Date(viewModal.user.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : ''" disabled>
                        </div>
                    </div>

                    <div class="mf-footer">
                        <button type="button" @click="viewModal.isOpen = false" class="mf-btn-cancel">Cancel</button>
                        <button type="submit" class="mf-btn-save" :disabled="viewModal.saving" x-text="viewModal.saving ? 'Saving...' : 'Save Changes'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function userManagement() {
    return {
        viewModal: {
            isOpen: false,
            loading: false,
            saving: false,
            error: '',
            success: '',
            user: null
        },
        
        async viewUser(userId) {
            this.viewModal.isOpen = true;
            this.viewModal.loading = true;
            this.viewModal.error = '';
            this.viewModal.success = '';
            this.viewModal.user = null;

            try {
                const res = await fetch('/printflow/admin/api_user_details.php?id=' + userId);
                const data = await res.json();
                if (data.success) {
                    this.viewModal.user = data.user;
                } else {
                    this.viewModal.error = data.error || 'Failed to load user.';
                }
            } catch (e) {
                this.viewModal.error = 'Network error.';
            } finally {
                this.viewModal.loading = false;
            }
        },

        async saveUserChanges() {
            if (!this.viewModal.user) return;
            this.viewModal.saving = true;
            this.viewModal.error = '';
            this.viewModal.success = '';

            try {
                const payload = {
                    action: 'update_info',
                    user_id: this.viewModal.user.user_id,
                    first_name: this.viewModal.user.first_name,
                    middle_name: this.viewModal.user.middle_name || '',
                    last_name: this.viewModal.user.last_name,
                    contact_number: this.viewModal.user.contact_number || '',
                    address: this.viewModal.user.address || '',
                    gender: this.viewModal.user.gender || '',
                    dob: this.viewModal.user.dob || '',
                    role: this.viewModal.user.role,
                    branch_id: this.viewModal.user.branch_id || '',
                    status: this.viewModal.user.status,
                    csrf_token: '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'
                };

                const res = await fetch('/printflow/admin/api_update_user_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if (data.success) {
                    this.viewModal.success = data.message;
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.viewModal.error = data.error || 'Update failed.';
                }
            } catch (e) {
                this.viewModal.error = 'Network error.';
            } finally {
                this.viewModal.saving = false;
            }
        },

        async toggleStatus() {
            if (!this.viewModal.user) return;
            if (!confirm(`Are you sure you want to ${this.viewModal.user.status === 'Activated' ? 'deactivate' : 'activate'} this user?`)) return;
            
            this.viewModal.error = '';
            this.viewModal.success = '';
            
            try {
                const payload = {
                    action: 'toggle_status',
                    user_id: this.viewModal.user.user_id,
                    current_status: this.viewModal.user.status,
                    csrf_token: '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'
                };

                const res = await fetch('/printflow/admin/api_update_user_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if (data.success) {
                    this.viewModal.user.status = data.new_status;
                    this.viewModal.success = data.message;
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.viewModal.error = data.error || 'Status toggle failed.';
                }
            } catch (e) {
                this.viewModal.error = 'Network error.';
            }
        }
    };
}
</script>
</body>
</html>
