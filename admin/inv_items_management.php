<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryManager.php';

require_role(['Admin', 'Manager']);
$page_title = 'Inventory Items - Admin';

// Get parameters
$cat_id   = (int)($_GET['category_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$sort     = $_GET['sort'] ?? 'name';
$dir      = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

// Build Query
$sql = "SELECT i.*, c.name as category_name 
        FROM inv_items i 
        LEFT JOIN inv_categories c ON i.category_id = c.id 
        WHERE 1=1";
$params = [];
$types = '';

if ($cat_id) {
    $sql .= " AND i.category_id = ?";
    $params[] = $cat_id;
    $types .= 'i';
}
if ($search) {
    $st = '%' . $search . '%';
    $sql .= " AND (i.name LIKE ? OR i.sku LIKE ?)";
    $params[] = $st; $params[] = $st;
    $types .= 'ss';
}

// Count total for pagination
$count_sql = "SELECT COUNT(*) as total FROM ({$sql}) as wrap";
$total_rows = db_query($count_sql, $types ?: null, $params ?: null)[0]['total'] ?? 0;
$total_pages = max(1, ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sort_map = [
    'name' => 'i.name',
    'id'   => 'i.id'
];
$orderBy = $sort_map[$sort] ?? 'i.name';
$sql .= " ORDER BY $orderBy $dir LIMIT $per_page OFFSET $offset";

$items = db_query($sql, $types ?: null, $params ?: null) ?: [];

// Add stock info
foreach ($items as &$item) {
    $item['current_stock'] = InventoryManager::getStockOnHand($item['id']);
}
unset($item);

// Get categories for filters
$categories = db_query("SELECT * FROM inv_categories ORDER BY sort_order ASC, name ASC") ?: [];

// AJAX Partial Response
if (isset($_GET['ajax'])) {
    ob_start();
    if (empty($items)): ?>
        <tr id="emptyItemsRow"><td colspan="7" class="py-12 text-center text-gray-500">No inventory items matching the filter.</td></tr>
    <?php else: 
        foreach ($items as $item): 
            $stock = (float)$item['current_stock'];
            $minStock = (float)$item['reorder_level'];
            $isOut = $stock <= 0;
            $isLow = !$isOut && $stock <= $minStock;
            
            $stockColor = '#1f2937';
            if ($isOut) $stockColor = '#991b1b';
            else if ($isLow) $stockColor = '#d97706';
            
            $trackBadge = $item['track_by_roll'] == 1
                ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#eef2ff;color:#4338ca;">Roll-Based</span>'
                : '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f3f4f6;color:#4b5563;">Standard</span>';
            
            $statusBadge = ($isLow && !$isOut) ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:8px;">Low Stock</span>' : '';
            $inactiveBadge = $item['status'] === 'INACTIVE' ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f3f4f6;color:#6b7280;margin-left:6px;">Inactive</span>' : '';
    ?>
            <tr class="<?php echo ($isOut || $isLow) ? 'low-stock-row' : ''; ?>" style="cursor:pointer;" onclick="openStockCard(<?php echo $item['id']; ?>)">
                <td style="font-weight:500;text-transform:capitalize;"><?php echo htmlspecialchars($item['name']); ?><?php echo $statusBadge . $inactiveBadge; ?></td>
                <td><?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?></td>
                <td><?php echo $trackBadge; ?></td>
                <td><span class="font-semibold">₱<?php echo number_format($item['unit_cost'], 2); ?></span></td>
                <td><span class="stock-val" style="color:<?php echo $stockColor; ?>;"><?php echo number_format($stock, 2); ?></span></td>
                <td style="color:#6b7280;font-size:12px;"><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                <td style="text-align:right;white-space:nowrap;">
                    <button class="btn-action teal" onclick="event.stopPropagation(); openAddStockModalById(<?php echo $item['id']; ?>)">+ Stock</button>
                    <button class="btn-action blue" onclick="event.stopPropagation(); editItemById(<?php echo $item['id']; ?>)">Edit</button>
                </td>
            </tr>
    <?php endforeach; ?>
    <?php endif; ?>
<?php
    $table_html = ob_get_clean();

    ob_start();
    $p = array_filter(['category_id'=>$cat_id, 'search'=>$search, 'sort'=>$sort, 'dir'=>$dir]);
    echo render_pagination($page, $total_pages, $p);
    $pagination_html = ob_get_clean();

    $badge_count = count(array_filter([$cat_id, $search]));

    echo json_encode([
        'success'    => true,
        'table'      => $table_html,
        'pagination' => $pagination_html,
        'count'      => number_format($total_rows),
        'badge'      => $badge_count,
        'startIdx'   => $total_rows > 0 ? $offset + 1 : 0,
        'endIdx'     => min($offset + $per_page, $total_rows),
        'total'      => $total_rows,
        'items'      => $items // Keep returning items for Alpine functions like find()
    ]);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(255, 255, 255, 0.3);
        }

        .header-actions { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; padding: 24px; background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 16px; border: 1px solid var(--glass-border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 700; text-transform: capitalize; color: #6b7280; letter-spacing: 0.025em; }
        .filter-group input, .filter-group select { padding: 10px 16px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; transition: all 0.2s; background: #fff; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        
        .inv-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .inv-table th { padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        .inv-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; color: #374151; }
        .inv-table tbody tr { cursor: pointer; transition: background 0.1s; }
        .inv-table tbody tr:hover td { background: #f9fafb; }
        .inv-table tbody tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid transparent; }
        .badge-green { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .badge-red { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .badge-gray { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
        .badge-indigo { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
        
        .stock-val { font-weight: 700; font-variant-numeric: tabular-nums; font-size: 15px; }
        .btn-action {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 5px 12px; min-width: 80px; border: 1px solid transparent;
            background: transparent; border-radius: 6px; font-size: 12px;
            font-weight: 500; transition: all 0.2s; cursor: pointer;
            text-decoration: none; white-space: nowrap; height: 32px;
        }
        .btn-action.teal { color: #14b8a6; border-color: #14b8a6; }
        .btn-action.teal:hover { background: #14b8a6; color: white; }
        .btn-action.blue { color: #3b82f6; border-color: #3b82f6; }
        .btn-action.blue:hover { background: #3b82f6; color: white; }
        .btn-action.red { color: #ef4444; border-color: #ef4444; }
        .btn-action.red:hover { background: #ef4444; color: white; }

        /* Legacy support for page-specific coloring if needed */
        .btn-edit { color: #4f46e5; background: #eef2ff; border: 1px solid #e0e7ff; }
        .btn-edit:hover { background: #6366f1; color: #fff; transform: translateY(-1px); }
        
        /* Modals */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 110000; align-items: center; justify-content: center; padding: 16px; overflow: hidden; pointer-events: auto !important; }
        .modal-content { background: #fff; border-radius: 12px; width: 95%; max-width: 640px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid var(--border-color); position: relative; z-index: 110001; animation: fadeIn 0.3s ease forwards; pointer-events: auto !important; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 18px; font-weight: 700; color: #111827; }
        .close-btn { background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; padding: 4px; line-height: 1; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .close-btn:hover { color: #374151; }
        
        .modal input, .modal select, .modal textarea { pointer-events: auto !important; cursor: text; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 32px; }
        .form-group.full { grid-column: span 2; }
        
        /* Ensure select elements in modal have consistent height and style */
        .modal select, .modal input:not([type="checkbox"]) { height: 44px; width: 100% !important; display: block; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0 14px; font-size: 14px; background: #fff; color: #1f2937; }
        .modal label { margin-bottom: 8px; display: block; font-weight: 700; color: #374151; font-size: 13px; }

        /* Premium Stock Card Modal */
        #stockCardModal .modal-content, #itemModal .modal-content { max-width: 850px; }
        .stock-card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; }
        .kpi-mini-card { padding: 16px; border-radius: 16px; background: #f9fafb; border: 1px solid #e5e7eb; }
        .kpi-mini-card .label { font-size: 10px; font-weight: 700; color: #6b7280; text-transform: capitalize; margin-bottom: 2px; }
        .kpi-mini-card .value { font-size: 20px; font-weight: 800; color: #1f2937; }
        
        .roll-list-table { width: 100%; margin-top: 24px; font-size: 13px; }
        .roll-list-table th { text-align: left; padding: 8px; font-weight: 700; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        .roll-list-table td { padding: 12px 8px; border-bottom: 1px solid #f3f4f6; }

        .chart-container { height: 200px; margin-top: 24px; padding: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; }

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
            transition: border-color 0.15s;
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

        /* Low stock row highlight */
        .low-stock-row td { background-color: #fff5f5 !important; color: #1f2937 !important; }
        .low-stock-row:hover td { background-color: #fee2e2 !important; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title" style="margin-bottom: 4px;">Inventory Master V2</h1>
                <p style="font-size: 14px; color: #6b7280;">Centralized material tracking with individual roll management.</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="inv_rolls_management" class="btn-secondary" style="display:inline-flex; align-items:center; gap:8px; padding: 12px 20px; border-radius: 12px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Roll Tracking
                </a>
                <a href="inv_transactions_ledger" class="btn-secondary" style="display:inline-flex; align-items:center; gap:8px; padding: 12px 20px; border-radius: 12px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Transaction Ledger
                </a>
            </div>
        </header>

        <main>
            <!-- Items Card -->
            <div class="card">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;" x-data="filterPanel()">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">
                        Inventory Items List
                        <span style="font-size:13px; font-weight:400; color:#6b7280; margin-left:8px;">
                            (Showing <strong style="color:#1f2937;" id="showingCount"><?php echo $total_rows > 0 ? ($offset + 1) . '–' . min($offset + $per_page, $total_rows) : '0'; ?></strong> of <?php echo number_format($total_rows); ?> items)
                        </span>
                    </h3>
                    
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                        <button class="toolbar-btn" onclick="openModal('create')" style="height:38px; border-color:#3b82f6; color:#3b82f6;">Add Item</button>
                        
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
                                    A → Z
                                    <svg x-show="activeSort === 'az'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="{'selected': activeSort === 'za'}" @click="applySortFilter('za')">
                                    Z → A
                                    <svg x-show="activeSort === 'za'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{active: filterOpen || hasActiveFilters}" @click="filterOpen = !filterOpen; sortOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filter
                                <span id="filterBadgeContainer"></span>
                            </button>
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Filter</div>
                                
                                <!-- Category -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Category</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['category'])">Reset</button>
                                    </div>
                                    <select id="fp_category" class="filter-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Keyword -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Keyword search</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['search'])">Reset</button>
                                    </div>
                                    <input type="text" id="fp_search" class="filter-search-input" placeholder="Search by name..." value="">
                                </div>

                                <div class="filter-actions">
                                    <button class="filter-btn-reset" style="width:100%;" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="inv-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr>
                                <th>Material Name</th>
                                <th>Category</th>
                                <th>Tracking</th>
                                <th>Unit Cost</th>
                                <th>Stock Level</th>
                                <th>UOM</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <?php if (empty($items)): ?>
                                <tr id="emptyItemsRow"><td colspan="7" class="py-12 text-center text-gray-500">No inventory items matching the filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): 
                                    $stock = (float)$item['current_stock'];
                                    $minStock = (float)$item['reorder_level'];
                                    $isOut = $stock <= 0;
                                    $isLow = !$isOut && $stock <= $minStock;
                                    
                                    $stockColor = '#1f2937';
                                    if ($isOut) $stockColor = '#991b1b';
                                    else if ($isLow) $stockColor = '#d97706';
                                    
                                    $trackBadge = $item['track_by_roll'] == 1
                                        ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#eef2ff;color:#4338ca;">Roll-Based</span>'
                                        : '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f3f4f6;color:#4b5563;">Standard</span>';
                                    
                                    $statusBadge = ($isLow && !$isOut) ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:8px;">Low Stock</span>' : '';
                                    $inactiveBadge = $item['status'] === 'INACTIVE' ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f3f4f6;color:#6b7280;margin-left:6px;">Inactive</span>' : '';
                                ?>
                                    <tr class="<?php echo ($isOut || $isLow) ? 'low-stock-row' : ''; ?>" style="cursor:pointer;" onclick="openStockCard(<?php echo $item['id']; ?>)">
                                        <td style="font-weight:500;text-transform:capitalize;"><?php echo htmlspecialchars($item['name']); ?><?php echo $statusBadge . $inactiveBadge; ?></td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?></td>
                                        <td><?php echo $trackBadge; ?></td>
                                        <td><span class="font-semibold">₱<?php echo number_format($item['unit_cost'], 2); ?></span></td>
                                        <td><span class="stock-val" style="color:<?php echo $stockColor; ?>;"><?php echo number_format($stock, 2); ?></span></td>
                                        <td style="color:#6b7280;font-size:12px;"><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                        <td style="text-align:right;white-space:nowrap;">
                                            <button class="btn-action teal" onclick="event.stopPropagation(); openAddStockModalById(<?php echo $item['id']; ?>)">+ Stock</button>
                                            <button class="btn-action blue" onclick="event.stopPropagation(); editItemById(<?php echo $item['id']; ?>)">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="itemsPagination">
                    <?php 
                        $p = array_filter(['category_id'=>$cat_id, 'search'=>$search, 'sort'=>$sort, 'dir'=>$dir]);
                        echo render_pagination($page, $total_pages, $p); 
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Stock Modal -->
<div id="addStockModal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Inventory: Stock Intake</h3>
                <p style="font-size:13px;color:#6b7280;margin-top:2px;" id="addStockItemName">Item Name</p>
            </div>
            <button class="close-btn" onclick="closeAddStockModal()">&times;</button>
        </div>
        <form id="addStockForm" onsubmit="saveAddStock(event)">
            <input type="hidden" id="addStockItemId">
            <input type="hidden" id="addStockIsRoll">
            <input type="hidden" id="addStockUom">
            <div class="form-grid" style="grid-template-columns:1fr;">
                <div class="filter-group">
                    <label for="addStockQty">Quantity to Add *</label>
                    <input type="number" step="0.01" min="0.01" id="addStockQty" required placeholder="e.g. 164.00" autofocus>
                </div>
                <div class="filter-group" id="addStockRollGroup" style="display:none; background:#f0f7ff; padding:12px; border-radius:10px; border:1px solid #dbeafe;">
                    <label for="addStockRollCode">Roll Identification</label>
                    <input type="text" id="addStockRollCode" placeholder="e.g. ROLL-001" style="margin-bottom:12px;">
                    
                    <label for="addStockWidth">Roll Width (ft) *</label>
                    <input type="number" step="1" min="1" max="12" id="addStockWidth" placeholder="e.g. 6 or 10">
                    <p style="font-size:10px;color:#3b82f6;margin-top:4px;line-height:1.4;">Specify width for tarpaulin/vinyl rolls. <br>Leave code empty to auto-generate.</p>
                </div>
                <div class="filter-group">
                    <label for="addStockNotes">Notes</label>
                    <input type="text" id="addStockNotes" placeholder="e.g. Purchased from supplier XYZ">
                </div>
            </div>
            <div style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid #f3f4f6;">
                <button type="button" onclick="closeAddStockModal()" class="btn-secondary" style="height:44px;border-radius:10px;padding:0 24px;">Cancel</button>
                <button type="submit" id="addStockBtn" class="btn-primary" style="height:44px;border-radius:10px;padding:0 24px;background:#059669;">Add Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Card View Modal (PREMIUM) -->
<div id="stockCardModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="scName">Item Name</h3>
            </div>
            <button class="close-btn" onclick="closeStockCard()">×</button>
        </div>
        
        <div class="stock-card-grid">
            <div class="kpi-mini-card" style="background: #f0f9ff; border-color: #bae6fd;">
                <div class="label">Stock Level</div>
                <div class="value" style="color: #0369a1;" id="scStock">0.00</div>
                <div style="font-size: 11px; font-weight: 600; color: #0ea5e9; margin-top: 4px;" id="scUnit">UNIT</div>
            </div>
            <div class="kpi-mini-card" id="rollKpi" style="background: #fdf2f8; border-color: #fbcfe8;">
                <div class="label">Roll Count</div>
                <div class="value" style="color: #be185d;" id="scRoll">0</div>
                <div style="font-size: 11px; font-weight: 600; color: #db2777; margin-top: 4px;">active rolls</div>
            </div>
            <div class="kpi-mini-card" style="background: #f0fdf4; border-color: #bbf7d0;">
                <div class="label">Reorder At</div>
                <div class="value" style="color: #15803d;" id="scMinStock">0.00</div>
                <div style="font-size: 11px; font-weight: 600; color: #16a34a; margin-top: 4px;">minimum</div>
            </div>
        </div>

        <div id="rollDetailsSection" style="display: none;">
            <h4 style="margin-top: 24px; font-size: 13px; font-weight: 700; text-transform: uppercase; color: #6b7280;">Active Rolls</h4>
            <div style="max-height: 200px; overflow-y: auto;">
                <table class="roll-list-table">
                    <thead>
                        <tr>
                            <th>Roll Code</th>
                            <th>Remaining</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody id="scRollBody"></tbody>
                </table>
            </div>
        </div>

        <div class="chart-container">
            <canvas id="usageChart"></canvas>
        </div>

        <div id="recentTransactionsSection" style="margin-top: 24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #6b7280;">Recent Ledger Activities</h4>
            </div>
            <div style="background:#f9fafb; border-radius:12px; border:1px solid #e5e7eb; overflow:hidden;">
                <table style="width:100%; font-size:12px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f3f4f6; text-align:left;">
                            <th style="padding:8px 12px; font-weight:700; color:#4b5563;">Date</th>
                            <th style="padding:8px 12px; font-weight:700; color:#4b5563;">Type</th>
                            <th style="padding:8px 12px; font-weight:700; color:#4b5563; text-align:right;">Qty</th>
                        </tr>
                    </thead>
                    <tbody id="scLedgerBody">
                        <tr><td colspan="3" style="text-align:center; padding:20px; color:#9ca3af;">Loading history...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; gap: 12px;">
            <button onclick="editFromStockCard()" class="btn-edit" style="flex: 1; height: 44px; border-radius: 10px;">Edit Settings</button>
            <a id="scLedgerLink" href="inv_transactions_ledger" class="btn-secondary" style="flex: 1; height: 44px; border-radius: 10px; display: flex; align-items:center; justify-content:center; text-decoration:none;">View Ledger</a>
        </div>
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div id="itemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Material Settings</h3>
            <button class="close-btn" onclick="closeModal()">×</button>
        </div>
        <form id="itemForm" onsubmit="saveItem(event)">
            <input type="hidden" id="itemId" name="id">
            <input type="hidden" id="actionType" name="action" value="create_item">

            <!-- Section: Material Information -->
            <div style="margin-bottom:24px;">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:14px;border-bottom:1px solid #f3f4f6;padding-bottom:8px;">Material Information</p>
                <div class="form-grid" style="margin-bottom:0;">
                    <div class="form-group full">
                        <label for="itemName">Item Name <span style="color:#ef4444">*</span></label>
                        <input type="text" id="itemName" name="name" required placeholder="e.g. 3FT Tarpaulin Gloss">
                    </div>
                    <div class="filter-group">
                        <label for="itemCategory">Category</label>
                        <select id="itemCategory" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="itemUnit">Unit of Measure (UOM) <span style="color:#ef4444">*</span></label>
                        <select id="itemUnit" name="unit" required onchange="handleUomChange()">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="ft">Feet (ft)</option>
                            <option value="btl">Bottles (btl)</option>
                            <option value="set">Sets (set)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="itemUnitCost">Unit Cost (₱) <span style="color:#ef4444">*</span></label>
                        <input type="number" step="0.01" min="0" id="itemUnitCost" name="unit_cost" value="0.00" required>
                    </div>
                </div>
            </div>

            <!-- Section: Inventory Control -->
            <div style="margin-bottom:24px;">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:14px;border-bottom:1px solid #f3f4f6;padding-bottom:8px;">Inventory Control</p>
                <div class="form-grid" style="margin-bottom:0;">
                    <div class="filter-group">
                        <label for="itemTrackByRoll">Tracking Mode</label>
                        <select id="itemTrackByRoll" name="track_by_roll">
                            <option value="0">Standard (Ledger)</option>
                            <option value="1">Roll-Based (Individual)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="itemMinStock">Reorder Level <span style="color:#ef4444">*</span></label>
                        <input type="number" step="0.01" min="0" id="itemMinStock" name="min_stock_level" value="0.00" required>
                    </div>
                    <div class="filter-group" id="startingStockGroup">
                        <label for="itemStartingStock">Initial Stock</label>
                        <input type="number" step="0.01" min="0" id="itemStartingStock" name="starting_stock" value="0.00">
                    </div>
                </div>
            </div>

            <!-- Section: Roll Material Settings (conditional) -->
            <div id="rollSettingsSection" style="margin-bottom:24px; overflow:hidden; transition: max-height 0.35s ease, opacity 0.3s ease; max-height:0; opacity:0;">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6366f1;margin-bottom:14px;border-bottom:1px solid #e0e7ff;padding-bottom:8px; background:#eef2ff; padding:8px 12px; border-radius:8px;">🪄 Roll Material Settings</p>
                <div class="form-grid" style="margin-bottom:0;">
                    <div class="filter-group form-group full">
                        <label for="itemRollLength">Standard Roll Length (ft) <span style="color:#ef4444" id="rollLengthRequired">*</span></label>
                        <input type="number" step="0.01" min="1" max="1000" id="itemRollLength" name="roll_length_ft" placeholder="e.g. 164.00">
                        <p style="font-size:11px;color:#6b7280;margin-top:6px;">Used for roll-based materials like tarpaulin or vinyl. Must be between 1 and 1000 ft.</p>
                    </div>
                </div>
            </div>

            <!-- Section: System Settings -->
            <div style="margin-bottom:24px;">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:14px;border-bottom:1px solid #f3f4f6;padding-bottom:8px;">System Settings</p>
                <div class="form-grid" style="margin-bottom:0;">
                    <div class="filter-group">
                        <label for="itemStatus">Status</label>
                        <select id="itemStatus" name="status">
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                        </select>
                    </div>
                </div>
                <p style="font-size:11px;color:#9ca3af;margin-top:6px;">Inactive materials are hidden from new orders and POS. Historical records are preserved.</p>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top:16px; border-top:1px solid #f3f4f6;">
                <button type="button" onclick="closeModal()" class="btn-secondary" style="border-radius: 10px; height: 44px; padding: 0 24px;">Cancel</button>
                <button type="submit" class="btn-primary" id="saveBtn" style="border-radius: 10px; height: 44px; padding: 0 24px; background: var(--primary-gradient);">Save Changes</button>
            </div>
  <script>
    let currentItems = <?php echo json_encode($items); ?>;
    let usageChart = null;
    let selectedItemForStockCard = null;
    let currentPage = <?php echo $page; ?>;
    let currentSort = '<?php echo $sort; ?>';
    let currentDir = '<?php echo $dir; ?>';
    let searchDebounceTimer = null;

    function filterPanel() {
        return {
            sortOpen: false,
            filterOpen: false,
            activeSort: '<?php echo $sort === 'name' ? ($dir === 'ASC' ? 'az' : 'za') : ($sort === 'id' ? ($dir === 'DESC' ? 'newest' : 'oldest') : 'az'); ?>',
            get hasActiveFilters() {
                return document.getElementById('fp_category')?.value ||
                       document.getElementById('fp_search')?.value;
            }
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('fp_search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => { 
                    fetchUpdatedTable({ page: 1 }); 
                }, 500); 
            });
        }
        
        const catSelect = document.getElementById('fp_category');
        if (catSelect) {
            catSelect.addEventListener('change', () => { 
                fetchUpdatedTable({ page: 1 }); 
            });
        }
    });

    function buildFilterURL(overrides = {}, isAjax = false) {
        const params = new URLSearchParams(window.location.search);
        
        const map = {
            'category_id': 'fp_category',
            'search': 'fp_search'
        };

        for (const [param, id] of Object.entries(map)) {
            const val = document.getElementById(id)?.value;
            if (val) params.set(param, val);
            else params.delete(param);
        }

        if (overrides.page !== undefined) params.set('page', overrides.page);
        else if (currentPage > 1) params.set('page', currentPage);

        if (overrides.sort !== undefined) {
            params.set('sort', overrides.sort);
            currentSort = overrides.sort;
        } else {
            params.set('sort', currentSort);
        }

        if (overrides.dir !== undefined) {
            params.set('dir', overrides.dir);
            currentDir = overrides.dir;
        } else {
            params.set('dir', currentDir);
        }

        if (isAjax) params.set('ajax', '1');
        else params.delete('ajax');

        return window.location.pathname + '?' + params.toString();
    }

    async function fetchUpdatedTable(overrides = {}) {
        const url = buildFilterURL(overrides, true);
        try {
            const resp = await fetch(url);
            const data = await resp.json();
            if (data.success) {
                const tbody = document.getElementById('itemsTableBody');
                const pagination = document.getElementById('itemsPagination');
                const showingText = document.getElementById('showingCount');
                const badgeCont = document.getElementById('filterBadgeContainer');

                if (tbody) tbody.innerHTML = data.table;
                if (pagination) pagination.innerHTML = data.pagination;
                if (showingText) {
                    showingText.textContent = data.startIdx + '–' + data.endIdx;
                    showingText.nextSibling.textContent = ' of ' + data.total + ' items)';
                }
                
                if (badgeCont) {
                    badgeCont.innerHTML = data.badge > 0 ? `<span class="filter-badge">${data.badge}</span>` : '';
                }

                if (data.items) currentItems = data.items;
                if (overrides.page !== undefined) currentPage = overrides.page;

                const displayUrl = buildFilterURL(overrides, false);
                window.history.replaceState({ path: displayUrl }, '', displayUrl);
            }
        } catch (e) {
            console.error('Error updating table:', e);
        }
    }

    function applyFilters(reset = false) {
        if (reset) {
            window.location.href = window.location.pathname;
        } else {
            fetchUpdatedTable({ page: 1 });
        }
    }

    function applySortFilter(sortKey) {
        let sort = 'name';
        let dir = 'ASC';

        if (sortKey === 'newest') { sort = 'id'; dir = 'DESC'; }
        else if (sortKey === 'oldest') { sort = 'id'; dir = 'ASC'; }
        else if (sortKey === 'az') { sort = 'name'; dir = 'ASC'; }
        else if (sortKey === 'za') { sort = 'name'; dir = 'DESC'; }
        
        const root = document.querySelector('[x-data]');
        if (root && root._x_dataStack) {
            const data = root._x_dataStack[0];
            data.activeSort = sortKey;
            data.sortOpen = false;
        }

        fetchUpdatedTable({ sort: sort, dir: dir, page: 1 });
    }

    function resetFilterField(fields) {
        fields.forEach(f => {
            const id = 'fp_' + (f === 'category' ? 'category' : f);
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        fetchUpdatedTable({ page: 1 });
    }

    function goToItemsPage(page) {
        fetchUpdatedTable({ page: page });
    }

    async function openStockCard(itemId) {
        const item = currentItems.find(i => i.id == itemId);
        if (!item) return;
        selectedItemForStockCard = item;
        
        document.getElementById('scName').textContent = item.name;
        document.getElementById('scStock').textContent = parseFloat(item.current_stock).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('scUnit').textContent = item.unit_of_measure.toUpperCase();
        document.getElementById('scMinStock').textContent = parseFloat(item.reorder_level).toLocaleString(undefined, {minimumFractionDigits: 2});
        
        // Handle roll kpi
        const rollKpi = document.getElementById('rollKpi');
        if (item.track_by_roll == 1) {
            rollKpi.style.display = 'block';
            document.getElementById('scRoll').textContent = '...'; // Placeholder
        } else {
            rollKpi.style.display = 'none';
        }

        document.getElementById('scLedgerLink').href = `inv_transactions_ledger.php?item_id=${item.id}`;
        document.getElementById('stockCardModal').style.display = 'flex';

        // Load Stock Card Details (Rolls & Ledger)
        try {
            const res = await fetch(`inventory_stock_card_api.php?item_id=${item.id}`);
            const data = await res.json();
            if (data.success) {
                // Rolls
                if (item.track_by_roll == 1) {
                    document.getElementById('rollDetailsSection').style.display = 'block';
                    document.getElementById('scRoll').textContent = data.rolls.length;
                    let rollHtml = '';
                    data.rolls.forEach(r => {
                        const prog = Math.min(100, Math.max(0, (r.current_length / r.original_length) * 100));
                        rollHtml += `<tr>
                            <td><span style="font-family:monospace;font-weight:700;">${r.roll_code}</span></td>
                            <td><span style="font-weight:700;">${parseFloat(r.current_length).toFixed(2)}</span> / ${parseFloat(r.original_length).toFixed(2)} ft</td>
                            <td>
                                <div style="width:100%; height:6px; background:#e5e7eb; border-radius:10px; overflow:hidden;">
                                    <div style="width:${prog}%; height:100%; background:#10b981; border-radius:10px;"></div>
                                </div>
                            </td>
                        </tr>`;
                    });
                    document.getElementById('scRollBody').innerHTML = rollHtml || '<tr><td colspan="3" style="text-align:center;padding:12px;color:#9ca3af;">No active rolls.</td></tr>';
                } else {
                    document.getElementById('rollDetailsSection').style.display = 'none';
                }

                // Ledger
                let ledgerHtml = '';
                data.ledger.forEach(l => {
                    const isIN = l.direction === 'IN';
                    const qty = parseFloat(l.quantity);
                    const qtyDisp = (isIN ? '+' : '-') + qty.toFixed(2);
                    const qtyColor = isIN ? '#059669' : '#dc2626';
                    ledgerHtml += `<tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding:8px 12px;color:#6b7280;">${l.transaction_date.split(' ')[0]}</td>
                        <td style="padding:8px 12px;text-transform:capitalize;">${l.ref_type.replace('_',' ')}</td>
                        <td style="padding:8px 12px;text-align:right;font-weight:700;color:${qtyColor};">${qtyDisp}</td>
                    </tr>`;
                });
                document.getElementById('scLedgerBody').innerHTML = ledgerHtml || '<tr><td colspan="3" style="text-align:center;padding:20px;color:#9ca3af;">No history.</td></tr>';

                // Chart
                renderUsageChart(data.usage_stats);
            }
        } catch(e) { console.error("Stock Card API error:", e); }
    }

    function renderUsageChart(stats) {
        const ctx = document.getElementById('usageChart').getContext('2d');
        if (usageChart) usageChart.destroy();
        
        usageChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: stats.labels,
                datasets: [{
                    label: 'Daily Stock-Out Usage',
                    data: stats.values,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 } } }
                }
            }
        });
    }

    function closeStockCard() {
        document.getElementById('stockCardModal').style.display = 'none';
    }

    function editFromStockCard() {
        if (selectedItemForStockCard) {
            closeStockCard();
            editItem(selectedItemForStockCard);
        }
    }

    function handleUomChange() {
        const uom = document.getElementById('itemUnit').value;
        const rollSec = document.getElementById('rollSettingsSection');
        const lengthInput = document.getElementById('itemRollLength');
        const lengthReq = document.getElementById('rollLengthRequired');
        
        if (uom === 'ft') {
            rollSec.style.maxHeight = '200px';
            rollSec.style.opacity = '1';
            lengthInput.required = true;
            lengthReq.style.display = 'inline';
            document.getElementById('itemTrackByRoll').value = '1';
        } else {
            rollSec.style.maxHeight = '0';
            rollSec.style.opacity = '0';
            lengthInput.required = false;
            lengthReq.style.display = 'none';
        }
    }

    function openModal(mode, item = null) {
        const modal = document.getElementById('itemModal');
        modal.style.display = 'flex';
        const form = document.getElementById('itemForm');
        form.reset();
        
        // Default UOM behavior
        handleUomChange();

        if (mode === 'create') {
            document.getElementById('modalTitle').textContent = 'Add New Material';
            document.getElementById('actionType').value = 'create_item';
            document.getElementById('itemId').value = '';
            document.getElementById('itemUnitCost').value = '0.00';
            document.getElementById('itemMinStock').value = '0.00';
            document.getElementById('startingStockGroup').style.display = 'block';
            document.getElementById('itemStatus').value = 'ACTIVE';
        } else {
            document.getElementById('modalTitle').textContent = 'Edit Material Settings';
            document.getElementById('actionType').value = 'update_item';
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemName').value = item.name;
            document.getElementById('itemCategory').value = item.category_id || '';
            document.getElementById('itemUnit').value = item.unit_of_measure;
            document.getElementById('itemUnitCost').value = item.unit_cost || '0.00';
            document.getElementById('itemTrackByRoll').value = item.track_by_roll;
            document.getElementById('itemMinStock').value = item.reorder_level;
            document.getElementById('itemStatus').value = item.status;
            document.getElementById('startingStockGroup').style.display = 'none';
            // Set roll length if UOM is ft
            if (item.unit_of_measure === 'ft') {
                document.getElementById('itemRollLength').value = item.default_roll_length_ft || '';
                setTimeout(handleUomChange, 0);
            }
        }
    }

    function closeModal() {
        document.getElementById('itemModal').style.display = 'none';
    }

    function editItemById(itemId) {
        const item = currentItems.find(i => i.id == itemId);
        if (item) editItem(item);
    }
    
    function openAddStockModalById(itemId) {
        const item = currentItems.find(i => i.id == itemId);
        if (item) openAddStockModal(item);
    }

    function openAddStockModal(item) {
        document.getElementById('addStockItemName').textContent = item.name;
        document.getElementById('addStockItemId').value = item.id;
        document.getElementById('addStockIsRoll').value = item.track_by_roll;
        document.getElementById('addStockUom').value = item.unit_of_measure || 'pcs';
        document.getElementById('addStockQty').value = '';
        document.getElementById('addStockRollCode').value = '';
        document.getElementById('addStockWidth').value = item.roll_length_ft || ''; // Default to item width if available
        document.getElementById('addStockNotes').value = '';
        
        // Show roll group only for roll-based items
        const isRoll = item.track_by_roll == 1;
        document.getElementById('addStockRollGroup').style.display = isRoll ? 'block' : 'none';
        document.getElementById('addStockWidth').required = isRoll;
        
        document.getElementById('addStockModal').style.display = 'flex';
        // Force focus after display
        setTimeout(() => {
            const qtyInput = document.getElementById('addStockQty');
            if (qtyInput) {
                qtyInput.focus();
                qtyInput.select();
            }
        }, 150);
    }

    function closeAddStockModal() {
        document.getElementById('addStockModal').style.display = 'none';
    }

    async function saveAddStock(e) {
        e.preventDefault();
        const btn = document.getElementById('addStockBtn');
        btn.disabled = true; btn.textContent = 'Saving...';
        const itemId = document.getElementById('addStockItemId').value;
        const isRoll = document.getElementById('addStockIsRoll').value == 1;
        const uom = document.getElementById('addStockUom').value;
        const qty = document.getElementById('addStockQty').value;
        const rollCode = document.getElementById('addStockRollCode').value;
        const notes = document.getElementById('addStockNotes').value;
        
        const fd = new FormData();
        fd.set('action', 'record_transaction');
        fd.set('item_id', itemId);
        fd.set('transaction_type', 'purchase');
        fd.set('quantity', qty);
        fd.set('uom', uom);
        fd.set('notes', notes || 'Manual stock entry');
        if (isRoll) {
            fd.set('roll_code', rollCode);
            fd.set('width_ft', document.getElementById('addStockWidth').value);
        }

        try {
            const res = await fetch('inventory_transactions_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeAddStockModal();
                fetchUpdatedTable();
            } else { alert('Operation Failed: ' + data.error); }
        } catch(err) { alert('Network communication error. Please try again.'); }
        finally { btn.disabled = false; btn.textContent = 'Add Stock'; }
    }

    function editItem(item) {
        openModal('edit', item);
    }

    async function saveItem(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';

        const formData = new FormData(document.getElementById('itemForm'));
        try {
            const res = await fetch('inventory_items_api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                closeModal();
                fetchUpdatedTable();
            } else { alert('Error: ' + data.error); }
        } catch (err) { alert('Request failed.'); } 
        finally { btn.disabled = false; btn.textContent = 'Save Changes'; }
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    window.addEventListener('click', e => {
        if (e.target.classList.contains('modal')) e.target.style.display = 'none';
    });

    window.addEventListener('popstate', (event) => {
        location.reload(); 
    });
</script>
</body>
</html>
