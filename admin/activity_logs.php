<?php
/**
 * Admin Activity Logs Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

// Pagination & Sorting defaults
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$sort_by = sanitize($_GET['sort_by'] ?? 'created_at');
$dir = strtoupper(sanitize($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

$search = sanitize($_GET['search'] ?? '');
$role = sanitize($_GET['role'] ?? '');
$date_from = sanitize($_GET['date_from'] ?? '');
$date_to = sanitize($_GET['date_to'] ?? '');

$sql_base = "SELECT al.log_id, al.user_id, al.action AS action_type, al.details AS description, al.created_at, 
           CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role 
           FROM activity_logs al 
           LEFT JOIN users u ON al.user_id = u.user_id 
           WHERE 1=1";

$params = [];
$types = '';

if ($search) {
    $sql_base .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR al.action LIKE ? OR al.details LIKE ?)";
    $p = "%$search%";
    $params = array_merge($params, [$p, $p, $p, $p]);
    $types .= 'ssss';
}
if ($role) {
    $sql_base .= " AND u.role = ?";
    $params[] = $role;
    $types .= 's';
}
if ($date_from && $date_to) {
    $sql_base .= " AND DATE(al.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= 'ss';
}

$sort_col_sql = match($sort_by) {
    'user_name' => 'user_name',
    'action_type' => 'al.action',
    default => 'al.created_at'
};

$total_sql = "SELECT COUNT(*) as total FROM ($sql_base) as t";
$total_res = db_query($total_sql, $types ?: null, $params ?: null);
$total_records = $total_res[0]['total'] ?? 0;
$total_pages = ceil($total_records / $per_page);

$query_sql = $sql_base . " ORDER BY $sort_col_sql $dir LIMIT $per_page OFFSET $offset";
$logs = db_query($query_sql, $types ?: null, $params ?: null) ?: [];

// AJAX Handler
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    ob_start();
    ?>
    <table class="logs-table">
        <thead>
            <tr>
                <th class="col-timestamp">Timestamp</th>
                <th class="col-user">User</th>
                <th class="col-role">Role</th>
                <th class="col-action">Action</th>
                <th class="col-desc">Description</th>
            </tr>
        </thead>
        <tbody id="logsTableBody">
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">No activity logs found matching the filters.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log):
                    $roleLower = strtolower($log['role'] ?? '');
                    $roleBadgeClass = $roleLower === 'admin' ? 'admin' : ($roleLower === 'manager' ? 'manager' : 'staff');
                ?>
                    <tr>
                        <td style="color:#6b7280;font-size:12px;"><?php echo format_datetime($log['created_at']); ?></td>
                        <td style="font-weight:500;color:#111827;"><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></td>
                        <td><span class="role-badge <?php echo $roleBadgeClass; ?>"><?php echo $log['role'] ?? 'N/A'; ?></span></td>
                        <td style="font-weight:600;color:#374151;"><?php echo htmlspecialchars($log['action_type']); ?></td>
                        <td title="<?php echo htmlspecialchars($log['description']); ?>" style="color:#6b7280;"><?php echo htmlspecialchars($log['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    $table_html = ob_get_clean();

    ob_start();
    if ($total_pages > 1) {
        echo '<div class="pagination-container" style="display:flex; align-items:center; justify-content:center; gap:8px; margin-top:20px; padding-top:16px; border-top:1px solid #f3f4f6;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $is_active = ($i == $page);
            $style = $is_active ? 'background:#111827; color:white; border-color:#111827;' : 'background:white; color:#374151; border:1px solid #e5e7eb;';
            echo '<button onclick="goToPage('.$i.')" style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; '.$style.'">'.$i.'</button>';
        }
        echo '</div>';
    }
    $pagination_html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'table' => $table_html,
        'pagination' => $pagination_html,
        'count' => $total_records,
        'showing' => count($logs),
        'offset_start' => $offset + 1,
        'offset_end' => $offset + count($logs)
    ]);
    exit;
}

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
    <script src="/printflow/public/assets/js/alpine.min.js" defer></script>
    <style>
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

        /* Standardized Toolbar Styles */
        /* Standardized Toolbar Styles */
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
            z-index: 200;
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
        .filter-date-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
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
            min-width: 204px;
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
        .sort-option svg.check { margin-left: auto; color: #0d9488; }

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
        [x-cloak] { display: none !important; }

        /* Logs Table */
        .logs-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
        .logs-table th { padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        .logs-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; color: #374151; word-break: break-word; }
        .logs-table tbody tr:hover td { background: #f9fafb; }
        .logs-table tbody tr:last-child td { border-bottom: none; }
        .logs-table .col-timestamp { width: 15%; }
        .logs-table .col-user { width: 14%; }
        .logs-table .col-role { width: 9%; }
        .logs-table .col-action { width: 16%; }
        .logs-table .col-desc { width: 46%; }
        .role-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-badge.admin { background: #fee2e2; color: #991b1b; }
        .role-badge.staff { background: #dbeafe; color: #1e40af; }
        .role-badge.manager { background: #ede9fe; color: #5b21b6; }
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
            <!-- Activity Logs Card -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;" x-data="filterPanel()">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Activity Logs List</h3>
                    
                    <div style="display:flex; align-items:center; gap:8px;">
                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{active: sortOpen}" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <div class="sort-option" :class="{'selected': activeSort === 'newest'}" @click="applySortFilter('newest')">
                                    Newest to Oldest
                                    <svg x-show="activeSort === 'newest'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="{'selected': activeSort === 'oldest'}" @click="applySortFilter('oldest')">
                                    Oldest to Newest
                                    <svg x-show="activeSort === 'oldest'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="{'selected': activeSort === 'az'}" @click="applySortFilter('az')">
                                    User A → Z
                                    <svg x-show="activeSort === 'az'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="{'selected': activeSort === 'za'}" @click="applySortFilter('za')">
                                    User Z → A
                                    <svg x-show="activeSort === 'za'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{active: filterOpen || hasActiveFilters}" @click="filterOpen = !filterOpen; sortOpen = false">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filter
                                <span id="filterBadgeContainer"></span>
                            </button>
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Filter</div>
                                
                                <!-- Role -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Role</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['role'])">Reset</button>
                                    </div>
                                    <select id="fp_role" class="filter-select">
                                        <option value="">All Roles</option>
                                        <option value="Admin">Admin</option>
                                        <option value="Staff">Staff</option>
                                    </select>
                                </div>

                                <!-- Keyword -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Keyword search</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['search'])">Reset</button>
                                    </div>
                                    <input type="text" id="fp_search" class="filter-search-input" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>

                                <!-- Date Range -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Date range</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['date_from', 'date_to'])">Reset</button>
                                    </div>
                                    <div class="filter-date-row">
                                        <div><div class="filter-date-label">From:</div><input type="date" id="fp_date_from" class="filter-input" value="<?php echo $date_from; ?>"></div>
                                        <div><div class="filter-date-label">To:</div><input type="date" id="fp_date_to" class="filter-input" value="<?php echo $date_to; ?>"></div>
                                    </div>
                                </div>

                                <div class="filter-actions">
                                    <button class="filter-btn-reset" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="logsTableContainer" class="overflow-x-auto">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th class="col-timestamp">Timestamp</th>
                                <th class="col-user">User</th>
                                <th class="col-role">Role</th>
                                <th class="col-action">Action</th>
                                <th class="col-desc">Description</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">No activity logs found</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): 
                                    $roleLower = strtolower($log['role'] ?? '');
                                    $roleBadgeClass = $roleLower === 'admin' ? 'admin' : ($roleLower === 'manager' ? 'manager' : 'staff');
                                ?>
                                    <tr>
                                        <td style="color:#6b7280;font-size:12px;"><?php echo format_datetime($log['created_at']); ?></td>
                                        <td style="font-weight:500;color:#111827;"><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></td>
                                        <td><span class="role-badge <?php echo $roleBadgeClass; ?>"><?php echo $log['role'] ?? 'N/A'; ?></span></td>
                                        <td style="font-weight:600;color:#374151;"><?php echo htmlspecialchars($log['action_type']); ?></td>
                                        <td title="<?php echo htmlspecialchars($log['description']); ?>" style="color:#6b7280;"><?php echo htmlspecialchars($log['description']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="logsPagination">
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container" style="display:flex; align-items:center; justify-content:center; gap:8px; margin-top:20px; padding-top:16px; border-top:1px solid #f3f4f6;">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php 
                                    $is_active = ($i == $page);
                                    $style = $is_active ? 'background:#111827; color:white; border-color:#111827;' : 'background:white; color:#374151; border:1px solid #e5e7eb;';
                                ?>
                                <button onclick="goToPage(<?php echo $i; ?>)" style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; <?php echo $style; ?>"><?php echo $i; ?></button>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    let currentPage = <?php echo $page; ?>;
    let currentSort = '<?php echo $sort_by; ?>';
    let currentDir = '<?php echo $dir; ?>';
    let activeSort = 'newest';
    let searchDebounceTimer = null;

    function filterPanel() {
        return {
            sortOpen: false,
            filterOpen: false,
            activeSort: activeSort,
            get hasActiveFilters() {
                return document.getElementById('fp_role')?.value || 
                       document.getElementById('fp_search')?.value ||
                       document.getElementById('fp_date_from')?.value ||
                       document.getElementById('fp_date_to')?.value;
            }
        };
    }

    function applySortFilter(sortKey) {
        activeSort = sortKey;
        if (sortKey === 'newest') { currentSort = 'created_at'; currentDir = 'DESC'; }
        else if (sortKey === 'oldest') { currentSort = 'created_at'; currentDir = 'ASC'; }
        else if (sortKey === 'az') { currentSort = 'user_name'; currentDir = 'ASC'; }
        else if (sortKey === 'za') { currentSort = 'user_name'; currentDir = 'DESC'; }
        
        currentPage = 1;
        fetchUpdatedTable();
        
        const alpineEl = document.querySelector('[x-data="filterPanel()"]');
        if (alpineEl && alpineEl._x_dataStack) {
            alpineEl._x_dataStack[0].activeSort = sortKey;
            alpineEl._x_dataStack[0].sortOpen = false;
        }
    }

    function resetFilterField(fields) {
        fields.forEach(f => {
            const el = document.getElementById('fp_' + f);
            if (el) el.value = '';
        });
        currentPage = 1;
        fetchUpdatedTable();
    }

    function applyFilters(reset = false) {
        if (reset) {
            ['fp_role', 'fp_search', 'fp_date_from', 'fp_date_to'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            activeSort = 'newest';
            currentSort = 'created_at';
            currentDir = 'DESC';
        }
        currentPage = 1;
        fetchUpdatedTable();
    }

    function buildFilterURL() {
        const role = document.getElementById('fp_role')?.value || '';
        const search = document.getElementById('fp_search')?.value || '';
        const from = document.getElementById('fp_date_from')?.value || '';
        const to = document.getElementById('fp_date_to')?.value || '';
        
        return `activity_logs.php?ajax=1&page=${currentPage}&sort_by=${currentSort}&dir=${currentDir}&role=${role}&search=${encodeURIComponent(search)}&date_from=${from}&date_to=${to}`;
    }

    async function fetchUpdatedTable() {
        try {
            const res = await fetch(buildFilterURL());
            const data = await res.json();
            if (data.success) {
                document.getElementById('logsTableContainer').innerHTML = data.table;
                document.getElementById('logsPagination').innerHTML = data.pagination;
                // showingCount element replaced with heading - no update needed
                updateBadgeCount();
            }
        } catch (e) { console.error(e); }
    }

    function updateBadgeCount() {
        const role = document.getElementById('fp_role')?.value;
        const s = document.getElementById('fp_search')?.value;
        const from = document.getElementById('fp_date_from')?.value;
        const to = document.getElementById('fp_date_to')?.value;
        let count = 0;
        if (role) count++;
        if (s) count++;
        if (from) count++;
        if (to) count++;
        const cont = document.getElementById('filterBadgeContainer');
        if (cont) cont.innerHTML = count > 0 ? `<span class="filter-badge">${count}</span>` : '';
    }

    function goToPage(p) {
        currentPage = p;
        fetchUpdatedTable();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('fp_search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => { currentPage = 1; fetchUpdatedTable(); }, 500);
            });
        }
        ['fp_role', 'fp_date_from', 'fp_date_to'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', () => { currentPage = 1; fetchUpdatedTable(); });
        });
    });
</script>

</body>
</html>
