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

// Ensure columns exist (safe migration)
try {
    db_execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS middle_name VARCHAR(100) NULL AFTER first_name");
    db_execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday DATE NULL AFTER last_name");
} catch (Throwable $e) { /* already exists or unsupported – ignore */ }

// Handle staff creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $first_name  = sanitize($_POST['first_name']);
    $middle_name = sanitize($_POST['middle_name'] ?? '');
    $last_name   = sanitize($_POST['last_name']);
    $email      = sanitize($_POST['email']);
    $birthday   = sanitize($_POST['birthday'] ?? '');
    $password   = $_POST['password'];
    $role       = $_POST['role']; // 'Admin', 'Manager', or 'Staff'

    // Default password: email + birthday (MMDDYYYY) when not supplied by client
    if (empty($password) && !empty($birthday)) {
        $d = DateTime::createFromFormat('Y-m-d', $birthday);
        $password = $d ? ($email . $d->format('mdY')) : $email;
    }

    // Parse branch_id safely
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    if ($role === 'Admin') $branch_id = null; // Admins have global access

    $valid_roles = ['Admin', 'Manager', 'Staff'];
    if (!in_array($role, $valid_roles)) $role = 'Staff';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'All required fields must be filled in';
    } elseif (!empty($birthday)) {
        $bday_date = new DateTime($birthday);
        $today = new DateTime();
        $age = $today->diff($bday_date)->y;
        if ($bday_date > $today) {
            $error = 'Birthday cannot be a future date';
        } elseif ($age < 13) {
            $error = 'User must be at least 13 years old';
        }
    }

    if (!$error && strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($role !== 'Admin' && empty($branch_id)) {
        $error = 'Please assign a branch for this role';
    } else {
        // Check if email exists
        $existing = db_query("SELECT user_id FROM users WHERE email = ?", 's', [$email]);

        if (!empty($existing)) {
            $error = 'Email already exists';
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $bday_val = !empty($birthday) ? $birthday : null;

            db_execute(
                "INSERT INTO users (first_name, middle_name, last_name, birthday, email, password_hash, role, status, branch_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Activated', ?, NOW(), NOW())",
                'sssssssi',
                [$first_name, $middle_name, $last_name, $bday_val, $email, $password_hash, $role, $branch_id]
            );

            $success = $role . ' account created successfully!';
        }
    }
}

// Get all users with filters/sort
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 10;
$search        = trim($_GET['search'] ?? '');
$role_filter   = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort          = $_GET['sort'] ?? 'newest';
$dir           = $_GET['dir'] ?? 'DESC';
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';

$sort_col_sql = match($sort) {
    'oldest' => 'u.created_at ASC',
    'az'     => 'u.first_name ASC',
    'za'     => 'u.first_name DESC',
    default  => 'u.created_at DESC',
};

$sql_base = "FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE 1=1";
$params = []; $types = '';

if (!empty($search)) {
    $like = '%'.$search.'%';
    $sql_base .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if (!empty($role_filter)) {
    $sql_base .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}
if (!empty($status_filter)) {
    $sql_base .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($date_from)) {
    $sql_base .= " AND DATE(u.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if (!empty($date_to)) {
    $sql_base .= " AND DATE(u.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$total_users = db_query("SELECT COUNT(*) as total $sql_base", $types ?: null, $params ?: null)[0]['total'] ?? 0;
$total_pages = max(1, ceil($total_users / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$users = db_query("SELECT u.*, b.branch_name $sql_base ORDER BY $sort_col_sql LIMIT $per_page OFFSET $offset", $types ?: null, $params ?: null) ?: [];

// Fetch available branches for the creation dropdown
$branches = db_query("SELECT id, branch_name FROM branches ORDER BY id ASC");

// Summary statistics
$stat_total    = db_query("SELECT COUNT(*) as c FROM users")[0]['c'];
$stat_admins   = db_query("SELECT COUNT(*) as c FROM users WHERE role = 'Admin'")[0]['c'];
$stat_managers = db_query("SELECT COUNT(*) as c FROM users WHERE role = 'Manager'")[0]['c'];
$stat_staff    = db_query("SELECT COUNT(*) as c FROM users WHERE role = 'Staff'")[0]['c'];
$stat_active   = db_query("SELECT COUNT(*) as c FROM users WHERE status = 'Activated'")[0]['c'];

// Sort helpers
$build_sort_url = function(string $col) use ($sort, $dir, $search, $role_filter, $status_filter): string {
    $p = array_filter(['sort'=>$col,'dir'=>($sort===$col&&$dir==='ASC')?'DESC':'ASC','search'=>$search,'role'=>$role_filter,'status'=>$status_filter]);
    return '?'.http_build_query($p);
};
$sort_icon = fn(string $col): string => $sort===$col?($dir==='ASC'?' ▲':' ▼'):'';

// Flash message from process_create_manager.php
$manager_created = $_SESSION['cm_success'] ?? '';
unset($_SESSION['cm_success']);

$max_birthday = date('Y-m-d', strtotime('-13 years'));

$page_title = 'User & Staff Management - Admin';

// ── AJAX handler
if (isset($_GET['ajax'])) {
    ob_start();
    ?>
    <table class="w-full text-sm">
        <thead><tr class="border-b-2">
            <th class="text-left py-3">ID</th>
            <th class="text-left py-3">Name</th>
            <th class="text-left py-3">Email</th>
            <th class="text-left py-3">Role</th>
            <th class="text-left py-3">Branch</th>
            <th class="text-left py-3">Status</th>
            <th class="text-right py-3">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr class="border-b hover:bg-gray-50">
                <td class="py-3"><?php echo $user['user_id']; ?></td>
                <td class="py-3 font-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                <td class="py-3"><?php echo htmlspecialchars($user['email']); ?></td>
                <td class="py-3"><?php
                    $rs = match($user['role']) { 'Admin' => 'background:#fee2e2;color:#991b1b;', 'Manager' => 'background:#ede9fe;color:#5b21b6;', default => 'background:#dbeafe;color:#1e40af;' };
                    ?><span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $rs; ?>"><?php echo $user['role']; ?></span></td>
                <td class="py-3"><?php echo $user['role']==='Admin' ? '<span class="text-gray-500 italic">All Branches</span>' : htmlspecialchars($user['branch_name'] ?? 'Unassigned'); ?></td>
                <td class="py-3"><?php
                    $sc = match($user['status']) { 'Activated' => 'background:#dcfce7;color:#166534;', 'Deactivated' => 'background:#fee2e2;color:#991b1b;', default => 'background:#fef9c3;color:#854d0e;' };
                    ?><span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $sc; ?>"><?php echo $user['status']; ?></span></td>
                <td class="py-3 text-right">
                    <button class="btn-action" onclick="window._viewUser && _viewUser(<?php echo $user['user_id']; ?>)">View / Edit</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
            <tr><td colspan="7" class="py-8 text-center text-gray-500">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
    $table_html = ob_get_clean();
    ob_start();
    $pp = array_filter(['search'=>$search,'role'=>$role_filter,'status'=>$status_filter,'sort'=>$sort,'dir'=>$dir,'date_from'=>$_GET['date_from']??'','date_to'=>$_GET['date_to']??'']);
    echo render_pagination($page, $total_pages, $pp);
    $pagination_html = ob_get_clean();
    echo json_encode(['success'=>true,'table'=>$table_html,'pagination'=>$pagination_html,'count'=>number_format($total_users),'badge'=>count(array_filter([$search,$role_filter,$status_filter,$_GET['date_from']??'',$_GET['date_to']??'']))]);
    exit;
}
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
        .modal-box { background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.15); width:100%; max-width:680px; max-height:92vh; overflow-y:auto; }
        .modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid #f3f4f6; }
        .modal-hdr h2 { font-size:16px; font-weight:700; color:#111827; margin:0; }
        .modal-hdr button { background:none; border:none; font-size:20px; color:#9ca3af; cursor:pointer; line-height:1; padding:2px 6px; }
        .modal-hdr button:hover { color:#374151; }
        .modal-bdy { padding:20px 24px; }
        .mf-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
        .mf-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:14px; }
        .mf-full { grid-column:1/-1; }
        .mf-group { display: flex; flex-direction: column; }
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
        
        /* Validation States */
        .mf-group.is-invalid input, .mf-group.is-invalid select, .mf-group.is-invalid textarea {
            border-color: #ef4444 !important;
            background-color: #fff9f9 !important;
        }
        .mf-group.is-valid input, .mf-group.is-valid select, .mf-group.is-valid textarea {
            border-color: #10b981 !important;
        }
        .error-message {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
            display: none;
            font-weight: 500;
        }
        .mf-group.is-invalid .error-message {
            display: block;
        }
        @media(max-width:520px) { .mf-row { grid-template-columns:1fr; } }

        /* ─── Standardized Toolbar Styles ─── */
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
        .toolbar-btn.active { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
        .toolbar-btn svg { flex-shrink: 0; }

        .toolbar-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            height: 38px;
            border: 1px solid #3b82f6;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #3b82f6;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
            box-sizing: border-box;
        }
        .toolbar-btn-primary:hover {
            background: #eff6ff;
            border-color: #2563eb;
            color: #2563eb;
        }

        /* ── Filter Panel ─── */
        .filter-panel {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 320px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 100;
            overflow: hidden;
        }
        .filter-panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .filter-section {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
        }
        .filter-section:last-of-type { border-bottom: none; }
        .filter-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .filter-section-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .filter-reset-link {
            font-size: 12px;
            font-weight: 600;
            color: #0d9488;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            box-sizing: border-box;
            transition: border-color 0.15s;
        }
        .filter-input:focus { outline: none; border-color: #0d9488; }
        .filter-select {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            background: #fff;
            box-sizing: border-box;
            cursor: pointer;
        }
        .filter-select:focus { outline: none; border-color: #0d9488; }
        .filter-search-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 12px;
            color: #1f2937;
            box-sizing: border-box;
        }
        .filter-search-input:focus { outline: none; border-color: #0d9488; }
        .filter-actions {
            display: flex;
            gap: 8px;
            padding: 14px 18px;
            border-top: 1px solid #f3f4f6;
        }
        .filter-btn-reset {
            flex: 1;
            height: 36px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }
        .filter-btn-reset:hover { background: #f9fafb; }

        /* ── Sort Dropdown ─── */
        .sort-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            padding: 6px 0;
            overflow: hidden;
        }
        .sort-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            font-size: 13px;
            color: #374151;
            cursor: pointer;
            transition: background 0.1s;
        }
        .sort-option:hover { background: #f9fafb; }
        .sort-option.selected { color: #0d9488; font-weight: 600; background: #f0fdfa; }
        .sort-option .check { margin-left: auto; color: #0d9488; }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #0d9488;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
        }

        .filter-date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }

        /* Create-user modal */
        #user-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9800; justify-content:center; align-items:center; padding:16px; }
        #user-modal-backdrop.is-open { display:flex; }
        #user-modal-box { background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.15); max-width:680px; width:100%; max-height:90vh; overflow-y:auto; }
        #user-modal-box .modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid #f3f4f6; }
        #user-modal-box .modal-title { font-size:16px; font-weight:700; color:#111827; margin:0; }
        #user-modal-box .modal-close-x { background:none; border:none; font-size:20px; color:#9ca3af; cursor:pointer; }
        #user-modal-box .modal-close-x:hover { color:#374151; }
        #user-modal-box .modal-body { padding: 20px 24px; }
        #user-modal-box .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        #user-modal-box .form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
        #user-modal-box .form-group { margin-bottom:14px; }
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
                    <div class="kpi-label">Managers</div>
                    <div class="kpi-value"><?php echo $stat_managers; ?></div>
                    <div class="kpi-sub">Branch managers</div>
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

            <?php if ($success || $manager_created): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success ?: $manager_created); ?>
                </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="card">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;" x-data="filterPanel()">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Users & Staff List</h3>

                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                        <!-- Add User Button -->
                        <button type="button" id="btn-open-user-modal" class="toolbar-btn-primary" style="height:38px;">
                            Add User
                        </button>

                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: sortOpen }" @click="sortOpen = !sortOpen; filterOpen = false" id="sortBtn" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/>
                                </svg>
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <?php
                                $sorts = [
                                    'newest' => 'Newest to Oldest',
                                    'oldest' => 'Oldest to Newest',
                                    'az'     => 'A → Z',
                                    'za'     => 'Z → A',
                                ];
                                foreach ($sorts as $key => $label): ?>
                                <div class="sort-option" 
                                     :class="{ 'selected': activeSort === '<?php echo $key; ?>' }"
                                     @click="applySortFilter('<?php echo $key; ?>')">
                                    <?php echo htmlspecialchars($label); ?>
                                    <svg x-show="activeSort === '<?php echo $key; ?>'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{ active: filterOpen || hasActiveFilters }" @click="filterOpen = !filterOpen; sortOpen = false" id="filterBtn" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                </svg>
                                Filter
                                <span id="filterBadgeContainer">
                                    <?php
                                    $active_filters = array_filter([$role_filter, $status_filter, $search, $date_from, $date_to]);
                                    if (count($active_filters) > 0): ?>
                                    <span class="filter-badge"><?php echo count($active_filters); ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>

                            <!-- Filter Panel -->
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false" id="filterPanel">
                                <div class="filter-panel-header">Filter</div>

                                <!-- Date Range -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Date range</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['date_from','date_to'])">Reset</button>
                                    </div>
                                    <div class="filter-date-row">
                                        <div>
                                            <div class="filter-date-label">From:</div>
                                            <input type="date" id="fp_date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                                        </div>
                                        <div>
                                            <div class="filter-date-label">To:</div>
                                            <input type="date" id="fp_date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Role -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Role</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['role'])">Reset</button>
                                    </div>
                                    <select id="fp_role" class="filter-select">
                                        <option value="">All roles</option>
                                        <option value="Admin"   <?php echo $role_filter === 'Admin'   ? 'selected' : ''; ?>>Admin</option>
                                        <option value="Manager" <?php echo $role_filter === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="Staff"   <?php echo $role_filter === 'Staff'   ? 'selected' : ''; ?>>Staff</option>
                                    </select>
                                </div>

                                <!-- Status -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Status</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['status'])">Reset</button>
                                    </div>
                                    <select id="fp_status" class="filter-select">
                                        <option value="">All statuses</option>
                                        <option value="Activated"   <?php echo $status_filter === 'Activated'   ? 'selected' : ''; ?>>Activated</option>
                                        <option value="Deactivated" <?php echo $status_filter === 'Deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                    </select>
                                </div>

                                <!-- Keyword Search -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Keyword search</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['search'])">Reset</button>
                                    </div>
                                    <div class="filter-search-wrap">
                                        <input type="text" id="fp_search" class="filter-search-input" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="filter-actions">
                                    <button class="filter-btn-reset" style="width: 100%;" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="usersTableContainer">
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
                                <th class="text-right py-3">Actions</th>
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
                                        <?php
                                            $role_style = match($user['role']) {
                                                'Admin'   => 'background:#fee2e2;color:#991b1b;',
                                                'Manager' => 'background:#ede9fe;color:#5b21b6;',
                                                default   => 'background:#dbeafe;color:#1e40af;'
                                            };
                                        ?>
                                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $role_style; ?>">
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
                <div id="usersPagination">
                    <?php
                    $pagination_params = array_filter(['search'=>$search,'role'=>$role_filter,'status'=>$status_filter,'sort'=>$sort,'dir'=>$dir,'date_from'=>$date_from,'date_to'=>$date_to]);
                    echo render_pagination($page, $total_pages, $pagination_params);
                    ?>
                </div>
                </div><!-- /usersTableContainer -->
            </div><!-- /card -->
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
            <div class="modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="create_staff" value="1">
                
                <div class="form-row-3">
                <div class="form-group">
                    <label>First Name <span style="color:#ef4444">*</span></label>
                    <input type="text" name="first_name" required placeholder="e.g. Juan">
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" placeholder="e.g. Santos">
                </div>
                <div class="form-group">
                    <label>Last Name <span style="color:#ef4444">*</span></label>
                    <input type="text" name="last_name" required placeholder="e.g. Dela Cruz">
                </div>
            </div>

            <div class="form-group">
                <label>Email Address <span style="color:#ef4444">*</span></label>
                <input type="email" name="email" id="um-email" required placeholder="staff@printflow.com">
            </div>

            <div class="form-group">
                <label>Birthday <span style="color:#ef4444">*</span></label>
                <input type="date" name="birthday" id="um-birthday" required max="<?php echo $max_birthday; ?>">
                <div id="um-birthday-error" class="error-message" style="display:none; color:#ef4444; font-size:11px; margin-top:4px; font-weight:500;"></div>
                <p class="form-hint">Used to generate the default password.</p>
            </div>

            <div class="form-group">
                <label>Default Password</label>
                <div style="position:relative;">
                    <input type="text" name="password" id="um-password" minlength="8" readonly
                           placeholder="Auto-filled from email + birthday"
                           style="padding-right:80px;background:#f9fafb;color:#374151;">
                    <span id="um-pw-label"
                          style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;color:#9ca3af;font-weight:600;pointer-events:none;">AUTO</span>
                </div>
                <p class="form-hint">Format: <em>email</em> + <em>MMDDYYYY</em> &mdash; e.g. <code>juan@store.com01151990</code></p>
            </div>

            <div class="form-group">
                <label>Role <span style="color:#ef4444">*</span></label>
                <select name="role" id="user-role-select" required>
                    <option value="Staff">Staff</option>
                    <option value="Manager">Manager</option>
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
    var roleSelect  = document.getElementById('user-role-select');
    var branchGroup = document.getElementById('branch-select-group');
    if (roleSelect && branchGroup) {
        roleSelect.addEventListener('change', function() {
            var needsBranch = (this.value !== 'Admin');
            branchGroup.style.display = needsBranch ? 'block' : 'none';
            document.getElementById('user-branch-select').required = needsBranch;
        });
        roleSelect.dispatchEvent(new Event('change'));
    }

    // Auto-fill default password from email + birthday (MMDDYYYY)
    var emailInput = document.getElementById('um-email');
    var bdayInput  = document.getElementById('um-birthday');
    var pwInput    = document.getElementById('um-password');

    function buildDefaultPassword() {
        var em = emailInput ? emailInput.value.trim() : '';
        var bd = bdayInput  ? bdayInput.value : '';
        if (em && bd) {
            var parts = bd.split('-'); // [YYYY, MM, DD]
            if (parts.length === 3) {
                pwInput.value = em + parts[1] + parts[2] + parts[0];
            }
        } else {
            pwInput.value = '';
        }
    }

    if (emailInput) emailInput.addEventListener('input', buildDefaultPassword);
    if (bdayInput)  bdayInput.addEventListener('change', buildDefaultPassword);

    // Birthday validation for creation
    if (bdayInput) {
        bdayInput.addEventListener('change', function() {
            var val = this.value;
            var errDiv = document.getElementById('um-birthday-error');
            var submitBtn = backdrop.querySelector('.modal-btn-submit');
            if (!val) return;
            
            var bday = new Date(val);
            var today = new Date();
            var age = today.getFullYear() - bday.getFullYear();
            var m = today.getMonth() - bday.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < bday.getDate())) age--;
            
            if (bday > today) {
                errDiv.textContent = "Cannot be a future date.";
                errDiv.style.display = 'block';
                submitBtn.disabled = true;
                this.classList.add('is-invalid');
            } else if (age < 13) {
                errDiv.textContent = "Must be at least 13 years old.";
                errDiv.style.display = 'block';
                submitBtn.disabled = true;
                this.classList.add('is-invalid');
            } else {
                errDiv.style.display = 'none';
                submitBtn.disabled = false;
                this.classList.remove('is-invalid');
            }
        });
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

        <template x-if="viewModal.user">
            <div class="modal-bdy">
                <!-- Alerts -->
                <div x-show="viewModal.error" class="mf-alert err" x-text="viewModal.error"></div>
                <div x-show="viewModal.success" class="mf-alert ok" x-text="viewModal.success"></div>

                <form @submit.prevent="saveUserChanges">
                    <!-- Row 1: First, Middle, Last Name -->
                    <div class="mf-row-3">
                        <div class="mf-group" :class="{'is-invalid': errors.first_name, 'is-valid': viewModal.user.first_name && !errors.first_name}">
                            <label>First Name *</label>
                            <input type="text" x-model="viewModal.user.first_name" @input="validateField('first_name')" required>
                            <div class="error-message" x-text="errors.first_name"></div>
                        </div>
                        <div class="mf-group" :class="{'is-valid': viewModal.user.middle_name}">
                            <label>Middle Name</label>
                            <input type="text" x-model="viewModal.user.middle_name">
                        </div>
                        <div class="mf-group" :class="{'is-invalid': errors.last_name, 'is-valid': viewModal.user.last_name && !errors.last_name}">
                            <label>Last Name *</label>
                            <input type="text" x-model="viewModal.user.last_name" @input="validateField('last_name')" required>
                            <div class="error-message" x-text="errors.last_name"></div>
                        </div>
                    </div>

                    <!-- Row 2: Email (read-only) + Contact -->
                    <div class="mf-row">
                        <div class="mf-group">
                            <label>Email Address</label>
                            <input type="email" :value="viewModal.user.email" disabled>
                        </div>
                        <div class="mf-group" :class="{'is-invalid': errors.contact_number, 'is-valid': viewModal.user.contact_number && !errors.contact_number}">
                            <label>Contact Number *</label>
                            <input type="text" x-model="viewModal.user.contact_number" @input="validateField('contact_number')" placeholder="e.g. 09171234567" required>
                            <div class="error-message" x-text="errors.contact_number"></div>
                        </div>
                    </div>

                    <!-- Row 3: DOB + Gender -->
                    <div class="mf-row">
                        <div class="mf-group" :class="{'is-invalid': errors.dob, 'is-valid': viewModal.user.dob && !errors.dob}">
                            <label>Date of Birth *</label>
                            <input type="date" x-model="viewModal.user.dob" @change="validateField('dob')" required max="<?php echo $max_birthday; ?>">
                            <div class="error-message" x-text="errors.dob"></div>
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
                        <div class="mf-group mf-full" :class="{'is-invalid': errors.address, 'is-valid': viewModal.user.address && !errors.address}">
                            <label>Address *</label>
                            <textarea x-model="viewModal.user.address" @input="validateField('address')" rows="2" placeholder="Street, City, Province" required></textarea>
                            <div class="error-message" x-text="errors.address"></div>
                        </div>
                    </div>

                    <hr class="mf-divider">

                    <p style="font-size: 11px; color: #6b7280; font-style: italic; margin-bottom: 12px;">* Please fill out all required fields marked with an asterisk.</p>

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
                        <button type="submit" class="mf-btn-save" :disabled="viewModal.saving || !isFormValid" x-text="viewModal.saving ? 'Saving...' : 'Save Changes'"></button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</div>

<script>
// ── Filter & Sort JS (user_staff_management.php) ────────────────────────────
let activeSort = '<?php echo $sort ?? "newest"; ?>';
let searchDebounceTimer = null;

function filterPanel() {
    return {
        sortOpen: false,
        filterOpen: false,
        activeSort: activeSort,
        hasActiveFilters: <?php echo count(array_filter([$role_filter, $status_filter, $search, $date_from, $date_to])) > 0 ? 'true' : 'false'; ?>,
    };
}

function buildFilterURL(overrides = {}, includeAjax = false) {
    const params = new URLSearchParams(window.location.search);
    
    // Default current values
    const current = {
        role:      document.getElementById('fp_role')?.value || '',
        status:    document.getElementById('fp_status')?.value || '',
        date_from: document.getElementById('fp_date_from')?.value || '',
        date_to:   document.getElementById('fp_date_to')?.value || '',
        search:    document.getElementById('fp_search')?.value || '',
        sort:      activeSort
    };

    const combined = { ...current, ...overrides };

    const finalParams = new URLSearchParams();
    if (combined.page)      finalParams.set('page', combined.page);
    if (combined.role)      finalParams.set('role', combined.role);
    if (combined.status)    finalParams.set('status', combined.status);
    if (combined.date_from) finalParams.set('date_from', combined.date_from);
    if (combined.date_to)   finalParams.set('date_to', combined.date_to);
    if (combined.search)    finalParams.set('search', combined.search);
    if (combined.sort && combined.sort !== 'newest') finalParams.set('sort', combined.sort);
    
    if (includeAjax) finalParams.set('ajax', '1');
    
    return '?' + finalParams.toString();
}

async function fetchUpdatedTable(overrides = {}) {
    try {
        const url = buildFilterURL(overrides, true);
        const resp = await fetch(url);
        const data = await resp.json();
        
        if (data.success) {
            const container = document.getElementById('usersTableContainer');
            if (container) {
                container.innerHTML = data.table + '<div id="usersPagination">' + data.pagination + '</div>';
            }
            
            // Update badge
            const badgeCont = document.getElementById('filterBadgeContainer');
            if (badgeCont) {
                badgeCont.innerHTML = data.badge > 0 ? `<span class="filter-badge">${data.badge}</span>` : '';
            }
            
            // Update Alpine hasActiveFilters
            const alpineEl = document.querySelector('[x-data="filterPanel()"]');
            if (alpineEl && alpineEl._x_dataStack) {
                alpineEl._x_dataStack[0].hasActiveFilters = data.badge > 0;
            }

            // Update URL bar
            const displayUrl = buildFilterURL(overrides, false);
            window.history.replaceState({ path: displayUrl }, '', displayUrl);
        }
    } catch (e) {
        console.error('Error updating table:', e);
    }
}

function applyFilters(resetAll = false) {
    if (resetAll) {
        const base = window.location.pathname;
        window.location.href = base;
    } else {
        fetchUpdatedTable();
    }
}

function applySortFilter(sortKey) {
    activeSort = sortKey;
    // Update Alpine state
    const alpineEl = document.querySelector('[x-data="filterPanel()"]');
    if (alpineEl && alpineEl._x_dataStack) {
        const data = alpineEl._x_dataStack[0];
        data.activeSort = sortKey;
        data.sortOpen = false;
    }
    
    fetchUpdatedTable({ sort: sortKey });
}

function resetFilterField(fields) {
    fields.forEach(f => {
        const el = document.getElementById('fp_' + f);
        if (el) el.value = '';
    });
    fetchUpdatedTable();
}

document.addEventListener('DOMContentLoaded', () => {
    const inputs = ['fp_role', 'fp_status', 'fp_date_from', 'fp_date_to'];
    inputs.forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => fetchUpdatedTable());
    });

    const searchInput = document.getElementById('fp_search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                fetchUpdatedTable();
            }, 500);
        });
    }
});

function userManagement() {
    return {
        viewModal: {
            isOpen: false,
            error: '',
            success: '',
            user: null
        },
        errors: {
            first_name: '',
            last_name: '',
            contact_number: '',
            address: '',
            dob: ''
        },

        get isFormValid() {
            if (!this.viewModal.user) return false;
            return this.viewModal.user.first_name && 
                   this.viewModal.user.last_name && 
                   this.viewModal.user.contact_number && 
                   this.viewModal.user.address && 
                   this.viewModal.user.dob &&
                   !this.errors.first_name && 
                   !this.errors.last_name && 
                   !this.errors.contact_number && 
                   !this.errors.address &&
                   !this.errors.dob;
        },

        validateField(id) {
            if (!this.viewModal.user) return;
            let val = this.viewModal.user[id] || '';
            
            // Auto formatting for names
            if (id === 'first_name' || id === 'last_name') {
                if (val.startsWith(' ')) val = val.trimStart();
                if (val.length > 0) val = val.charAt(0).toUpperCase() + val.slice(1);
                this.viewModal.user[id] = val;
            }

            if (val.startsWith(' ')) {
                this.viewModal.user[id] = val.trimStart();
                val = this.viewModal.user[id];
            }

            const trimVal = val.trim();

            if (id === 'first_name' || id === 'last_name') {
                if (!trimVal) this.errors[id] = "Required.";
                else if (!/^[A-Za-z]+( [A-Za-z]+)*$/.test(trimVal)) this.errors[id] = "Letters only.";
                else if (trimVal.length < 2 || trimVal.length > 50) this.errors[id] = "2-50 chars.";
                else this.errors[id] = '';
            }
            else if (id === 'contact_number') {
                if (!trimVal) this.errors[id] = "Required.";
                else if (!/^\d+$/.test(trimVal)) this.errors[id] = "Digits only.";
                else if (!trimVal.startsWith('09')) this.errors[id] = "Must start with 09.";
                else if (trimVal.length !== 11) this.errors[id] = "Must be 11 digits.";
                else this.errors[id] = '';
            }
            else if (id === 'address') {
                if (!trimVal) this.errors[id] = "Required.";
                else if (trimVal.length < 5) this.errors[id] = "Min 5 chars.";
                else if (trimVal.length > 150) this.errors[id] = "Max 150 chars.";
                else this.errors[id] = '';
            }
            else if (id === 'dob') {
                if (!val) {
                    this.errors.dob = "Required.";
                    return;
                }
                const bday = new Date(val);
                const today = new Date();
                let age = today.getFullYear() - bday.getFullYear();
                const m = today.getMonth() - bday.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < bday.getDate())) age--;
                
                if (bday > today) this.errors.dob = "Cannot be future.";
                else if (age < 13) this.errors.dob = "Min 13 years old.";
                else this.errors.dob = '';
            }
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

// Global expose to bridge AJAX table clicks to userManagement Alpine component
document.addEventListener('alpine:init', () => {
    window._viewUser = (id) => {
        const el = document.querySelector('[x-data="userManagement()"]');
        if (el && el.__x && el.__x.$data) {
            el.__x.$data.viewUser(id);
        } else if (el && el._x_dataStack) {
             el._x_dataStack[0].viewUser(id);
        }
    };
});
</script>
</body>
</html>
