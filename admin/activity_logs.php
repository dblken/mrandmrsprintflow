<?php
/**
 * Admin Activity Logs Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

// Fetch all logs (up to 200), filtering is done client-side in realtime
$sql = "SELECT al.log_id, al.user_id, al.action AS action_type, al.details AS description, al.created_at, 
        CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        ORDER BY al.created_at DESC LIMIT 200";
$logs = db_query($sql);

$page_title = 'Activity Logs - Admin';
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
        /* Search Box */
        .search-box { position:relative; flex:1; min-width:180px; }
        .search-box input { padding-left:36px; width:100%; height:38px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:#fff; transition: border-color 0.2s; }
        .search-box input:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
        .search-box .search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }
        /* Role Filter Dropdown */
        .filter-select { height:38px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; padding:0 12px; background:#fff; flex:1; min-width:180px; transition: border-color 0.2s; }
        .filter-select:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Activity Logs</h1>
            <button class="btn-secondary" onclick="window.print()">
                Print Logs
            </button>
        </header>

        <main>
            <!-- Activity Logs Table -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                    <h3 style="font-size:16px; font-weight:700; color:#1f2937; margin:0;">Activity Logs <span id="logCount" style="font-size:13px; font-weight:400; color:#9ca3af;">(<?php echo count($logs); ?>)</span></h3>
                    <div style="display:flex; align-items:center; gap:10px; flex:1; max-width:480px; min-width:280px;">
                        <div class="search-box">
                            <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" id="searchInput" placeholder="Search logs...">
                        </div>
                        <select id="roleFilter" class="filter-select">
                            <option value="">All Roles</option>
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3">Timestamp</th>
                                <th class="text-left py-3">User</th>
                                <th class="text-left py-3">Role</th>
                                <th class="text-left py-3">Action</th>
                                <th class="text-left py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-gray-500">No activity logs found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="border-b hover:bg-gray-50 log-row"
                                        data-user="<?php echo htmlspecialchars($log['user_name'] ?? ''); ?>"
                                        data-role="<?php echo htmlspecialchars($log['role'] ?? ''); ?>"
                                        data-action="<?php echo htmlspecialchars($log['action_type']); ?>"
                                        data-description="<?php echo htmlspecialchars($log['description']); ?>">
                                        <td class="py-3 text-xs"><?php echo format_datetime($log['created_at']); ?></td>
                                        <td class="py-3 font-medium user-cell"><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></td>
                                        <td class="py-3">
                                            <span class="badge <?php echo $log['role'] === 'Admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo $log['role'] ?? 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 font-semibold action-cell"><?php echo htmlspecialchars($log['action_type']); ?></td>
                                        <td class="py-3 desc-cell"><?php echo htmlspecialchars($log['description']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-sm text-gray-600">
                    Showing latest 200 activities
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // Realtime filtering
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const logCount = document.getElementById('logCount');
    const rows = document.querySelectorAll('.log-row');

    function filterLogs() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedRole = roleFilter.value;
        let visibleCount = 0;

        rows.forEach(row => {
            const role = row.getAttribute('data-role');
            const user = row.getAttribute('data-user').toLowerCase();
            const action = row.getAttribute('data-action').toLowerCase();
            const description = row.getAttribute('data-description').toLowerCase();

            // Check role filter
            const roleMatch = !selectedRole || role === selectedRole;

            // Check search filter (searches user, action, description)
            const searchMatch = !searchTerm || 
                user.includes(searchTerm) || 
                action.includes(searchTerm) || 
                description.includes(searchTerm);

            if (roleMatch && searchMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        logCount.textContent = '(' + visibleCount + ')';
    }

    searchInput.addEventListener('input', filterLogs);
    roleFilter.addEventListener('change', filterLogs);
</script>

</body>
</html>
