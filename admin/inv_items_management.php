<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryManager.php';

require_role(['Admin', 'Manager']);
$current_user = get_logged_in_user();
$page_title = 'Inventory Items - Admin';

// Get parameters
$cat_id   = (int)($_GET['category_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$sort     = $_GET['sort'] ?? 'name';
$dir      = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$page     = max(1, (int)($_GET['page'] ?? 1));
$track_by = isset($_GET['track_by_roll']) && $_GET['track_by_roll'] !== '' ? (int)$_GET['track_by_roll'] : null;
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
if ($track_by !== null) {
    $sql .= " AND i.track_by_roll = ?";
    $params[] = $track_by;
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

// Safe JSON for inline <script> (invalid UTF-8 or encode failure must not emit empty â†’ JS syntax error)
$items_js_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $items_js_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$items_js = json_encode($items, $items_js_flags);
if ($items_js === false) {
    $items_js = '[]';
}

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
                <td><span class="font-semibold" style="white-space:nowrap;">â‚±<?php echo number_format($item['unit_cost'], 2); ?></span></td>
                <td><span class="stock-val" style="color:<?php echo $stockColor; ?>;"><?php echo strtolower($item['unit_of_measure'] ?? '') === 'pcs' ? (int)$stock : number_format($stock, 2); ?></span></td>
                <td style="color:#6b7280;font-size:12px;"><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                <td class="no-truncate" style="text-align:right;">
                    <button type="button" class="btn-action teal" onclick="event.stopPropagation(); openAddStockModalById(<?php echo $item['id']; ?>)">+ Stock</button>
                    <button type="button" class="btn-action blue" onclick="event.stopPropagation(); editItemById(<?php echo $item['id']; ?>)">Edit</button>
                </td>
            </tr>
    <?php endforeach; ?>
    <?php endif; ?>
<?php
    $table_html = ob_get_clean();

    ob_start();
    $p = array_filter(['category_id'=>$cat_id, 'search'=>$search, 'sort'=>$sort, 'dir'=>$dir, 'track_by_roll'=>$track_by], function($v) { return $v !== null && $v !== ''; });
    echo render_pagination($page, $total_pages, $p);
    $pagination_html = ob_get_clean();

    $badge_count = count(array_filter([$cat_id ?: '', $search, $track_by], function($v) { return $v !== null && $v !== ''; }));

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
    <style>
        [x-cloak] { display: none !important; }
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
        .inv-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; color: #374151; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .no-truncate { max-width: none !important; overflow: visible !important; white-space: nowrap !important; text-overflow: clip !important; }
        .inv-table tbody tr { cursor: pointer; transition: background 0.1s; }
        .inv-table tbody tr:hover td { background: #f9fafb; }
        .inv-table tbody tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid transparent; }
        .badge-green { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .badge-red { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .badge-gray { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
        .badge-indigo { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
        
        .stock-val { font-weight: 700; font-variant-numeric: tabular-nums; font-size: 15px; }
        .locked-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: default;
        }
        .locked-select:disabled {
            color: #374151;
            opacity: 1;
        }
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
        .modal-content { background: white; border-radius: 20px; width: 90%; max-width: 800px; padding: 24px; position: relative; max-height: 90vh; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); z-index: 110001; animation: fadeIn 0.3s ease forwards; pointer-events: auto !important; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 18px; font-weight: 700; color: #111827; padding-right: 40px; overflow-wrap: break-word; word-break: break-word; -webkit-hyphens: auto; -ms-hyphens: auto; hyphens: auto; line-height: 1.4; }
        .close-btn { background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; padding: 4px; line-height: 1; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .close-btn:hover { color: #374151; }
        
        .modal input, .modal select, .modal textarea { pointer-events: auto !important; cursor: text; }
        .modal label { margin-bottom: 6px; display: block; font-weight: 700; color: #4b5563; font-size: 12px; text-transform: uppercase; letter-spacing: 0.025em; }
        
        /* New Modal Grid System */
        .modal-section { margin-bottom: 32px; }
        .modal-section:last-child { margin-bottom: 0; }
        .section-header { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #9ca3af; margin-bottom: 16px; border-bottom: 1px solid #f3f4f6; padding-bottom: 8px; }
        
        .form-row-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 20px; margin-bottom: 16px; align-items: flex-start; }
        .form-col-full { grid-column: span 2; }
        
        /* Proportional Widths */
        .w-35 { width: 35% !important; min-width: 140px; }
        .w-40 { width: 40% !important; min-width: 150px; }
        .w-50 { width: 50% !important; }
        .w-60 { width: 60% !important; }
        .w-100 { width: 100% !important; }

        @media (max-width: 600px) {
            .form-row-grid { grid-template-columns: 1fr; gap: 16px; }
            .form-col-full { grid-column: span 1; }
            .w-35, .w-40, .w-50 { width: 100% !important; }
        }

        .preview-badge { display: inline-flex; align-items: center; justify-content: center; height: 44px; padding: 0 16px; border-radius: 10px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: #374151; width: 100%; box-sizing: border-box; }

        /* Validation Styles */
        .field-error { color: #dc2626; font-size: 11px; margin-top: 4px; display: none; font-weight: 500; }
        .input-error { border-color: #dc2626 !important; background-color: #fffafb !important; }
        .input-error:focus { box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important; }
        .input-success { border-color: #10b981 !important; }
        
        #saveBtn:disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(1); }
        .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 0.8s linear infinite; margin-right: 8px; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Premium Stock Card Modal */
        .sc-cards-grid { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .sc-card { flex: 1; min-width: 140px; max-width: 160px; border-radius: 12px; padding: 14px; text-align: center; }
        @media (max-width: 600px) {
            .sc-card { flex: 1 1 calc(50% - 12px); max-width: none; }
        }
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

        /* â”€â”€ Filter Panel â”€â”€â”€ */
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

        /* â”€â”€ Sort Dropdown â”€â”€â”€ */
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
    <?php include __DIR__ . '/../includes/' . ($current_user['role'] === 'Admin' ? 'admin_sidebar.php' : 'manager_sidebar.php'); ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Inventory Master V2</h1>
            <div style="display: flex; gap: 12px;">
                <a href="inv_transactions_ledger" class="btn-secondary" style="display:inline-flex; align-items:center; gap:8px; padding: 12px 20px; border-radius: 12px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Transaction Ledger
                </a>
            </div>
        </header>

        <main>
            <!-- Items Card -->
            <div class="card">
                <div id="inv-filter-toolbar" style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;" x-data="filterPanel()">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">
                        Inventory Items List
                        <span style="font-size:13px; font-weight:400; color:#6b7280; margin-left:8px;">
                            (Showing <strong style="color:#1f2937;" id="showingCount"><?php echo $total_rows > 0 ? ($offset + 1) . 'â€“' . min($offset + $per_page, $total_rows) : '0'; ?></strong><span id="invShowingMeta"> of <?php echo number_format($total_rows); ?> items)</span>
                        </span>
                    </h3>
                    
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                        <button type="button" class="toolbar-btn" onclick="openModal('create')" style="height:38px; border-color:#3b82f6; color:#3b82f6;">Add Item</button>
                        
                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button type="button" class="toolbar-btn" :class="{active: sortOpen}" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
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
                                    A â†’ Z
                                    <svg x-show="activeSort === 'az'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="sort-option" :class="{'selected': activeSort === 'za'}" @click="applySortFilter('za')">
                                    Z â†’ A
                                    <svg x-show="activeSort === 'za'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button type="button" class="toolbar-btn" :class="{active: filterOpen || hasActiveFilters}" @click="filterOpen = !filterOpen; sortOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                Filter
                                <span id="filterBadgeContainer">
                                    <?php 
                                        $initial_badge = count(array_filter([$cat_id ?: '', $search, $track_by], function($v) { return $v !== null && $v !== ''; }));
                                        if ($initial_badge > 0): ?>
                                            <span class="filter-badge"><?php echo $initial_badge; ?></span>
                                        <?php endif; 
                                    ?>
                                </span>
                            </button>
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Filter</div>
                                
                                <!-- Category -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Category</span>
                                        <button type="button" class="filter-reset-link" onclick="resetFilterField(['category'])">Reset</button>
                                    </div>
                                    <select id="fp_category" class="filter-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Tracking Type</span>
                                        <button type="button" class="filter-reset-link" onclick="resetFilterField(['track_by_roll'])">Reset</button>
                                    </div>
                                    <select id="fp_track_by_roll" class="filter-select">
                                        <option value="">All Tracking</option>
                                        <option value="0" <?php echo ($track_by === 0) ? 'selected' : ''; ?>>Standard (Ledger)</option>
                                        <option value="1" <?php echo ($track_by === 1) ? 'selected' : ''; ?>>Roll-Based (Individual)</option>
                                    </select>
                                </div>
                                
                                <!-- Keyword -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Keyword search</span>
                                        <button type="button" class="filter-reset-link" onclick="resetFilterField(['search'])">Reset</button>
                                    </div>
                                    <input type="text" id="fp_search" class="filter-search-input" placeholder="Search by name..." value="">
                                </div>

                                <div class="filter-actions">
                                    <button type="button" class="filter-btn-reset" style="width:100%;" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto" style="border:1px solid #f3f4f6; border-radius:12px;">
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
                                        <td class="truncate" style="font-weight:500;text-transform:capitalize;" title="<?php echo htmlspecialchars($item['name']); ?>">
                                            <?php echo htmlspecialchars($item['name']); ?><?php echo $statusBadge . $inactiveBadge; ?>
                                        </td>
                                        <td class="truncate" title="<?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?>"><?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?></td>
                                        <td><?php echo $trackBadge; ?></td>
                                        <td style="white-space:nowrap;"><span class="font-semibold">â‚±<?php echo number_format($item['unit_cost'], 2); ?></span></td>
                                        <td style="white-space:nowrap;"><span class="stock-val" style="color:<?php echo $stockColor; ?>;"><?php echo strtolower($item['unit_of_measure'] ?? '') === 'pcs' ? (int)$stock : number_format($stock, 2); ?></span></td>
                                        <td class="truncate" style="color:#6b7280;font-size:12px;"><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                        <td class="no-truncate" style="text-align:right;">
                                            <button type="button" class="btn-action teal" onclick="event.stopPropagation(); openAddStockModalById(<?php echo $item['id']; ?>)">+ Stock</button>
                                            <button type="button" class="btn-action blue" onclick="event.stopPropagation(); editItemById(<?php echo $item['id']; ?>)">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="itemsPagination">
                    <?php 
                        $p = array_filter(['category_id'=>$cat_id, 'search'=>$search, 'sort'=>$sort, 'dir'=>$dir, 'track_by_roll'=>$track_by], function($v) { return $v !== null && $v !== ''; });
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
                <h3 class="modal-title" style="padding-right:30px;">Inventory: Stock Intake</h3>
                <p style="font-size:13px;color:#6b7280;margin-top:2px; padding-right:30px; overflow-wrap:break-word; word-break:break-word; hyphens:auto;" id="addStockItemName">Item Name</p>
            </div>
            <button class="close-btn" onclick="closeAddStockModal()">&times;</button>
        </div>
        <form id="addStockForm" onsubmit="saveAddStock(event)">
            <input type="hidden" id="addStockItemId">
            <input type="hidden" id="addStockIsRoll">
            <input type="hidden" id="addStockUom">
            <input type="hidden" id="addStockCurrentStock" value="0">
            <div class="form-grid" style="grid-template-columns:1fr;">
                <div class="filter-group">
                    <label for="addStockQty">Quantity to Add *</label>
                    <input type="number" step="any" min="0" max="100000" id="addStockQty" placeholder="e.g. 50" inputmode="decimal">
                    <div id="addStockQtyError" style="display:none; font-size:12px; color:#dc2626; margin-top:4px;">Please enter a valid quantity</div>
                    <div id="addStockPreview" style="font-size:13px; font-weight:600; color:#059669; margin-top:6px;">New Stock After Adding: â€”</div>
                    <div id="addStockLargeWarning" style="display:none; font-size:13px; color:#854d0e; background:#fef9c3; padding:8px 12px; border-radius:8px; margin-top:8px; border:1px solid #fde68a;">
                        âš ï¸ You are adding a large quantity. Please double check.
                    </div>
                </div>
                <div class="filter-group" id="addStockRollGroup" style="display:none; background:#f0f7ff; padding:12px; border-radius:10px; border:1px solid #dbeafe;">
                    <label for="addStockRollCode">Roll Identification</label>
                    <input type="text" id="addStockRollCode" placeholder="e.g. ROLL-001" style="margin-bottom:12px;">
                    <span id="err-addStockRollCode" class="field-error"></span>
                    
                    <label for="addStockWidth">Roll Width (ft) *</label>
                    <input type="number" step="1" min="1" max="12" id="addStockWidth" placeholder="e.g. 6 or 10">
                    <span id="err-addStockWidth" class="field-error"></span>
                    <p style="font-size:10px;color:#3b82f6;margin-top:4px;line-height:1.4;">Specify width for tarpaulin/vinyl rolls. <br>Leave code empty to auto-generate.</p>
                </div>
                <div class="filter-group">
                    <label for="addStockNotes">Notes</label>
                    <input type="text" id="addStockNotes" placeholder="e.g. Purchased from supplier XYZ">
                </div>
            </div>
            <div style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid #f3f4f6;">
                <button type="button" onclick="closeAddStockModal()" class="btn-secondary" style="height:44px;border-radius:10px;padding:0 24px;">Cancel</button>
                <button type="submit" id="addStockBtn" class="btn-primary" style="height:44px;border-radius:10px;padding:0 24px;background:#059669;" disabled>Add Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Stock Confirmation Modal -->
<div id="addStockConfirmModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Stock Addition</h3>
            <button class="close-btn" onclick="closeAddStockConfirmModal()">&times;</button>
        </div>
        <div style="padding:0 0 20px; font-size:14px; color:#374151;">
            <p style="margin-bottom:12px;">You are about to add: <strong id="addStockConfirmQty">0</strong></p>
            <p>New total will be: <strong id="addStockConfirmTotal">0</strong></p>
            <p style="margin-top:16px; color:#6b7280;">Are you sure you want to continue?</p>
        </div>
        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button type="button" onclick="closeAddStockConfirmModal()" class="btn-secondary" style="height:44px;border-radius:10px;padding:0 24px;">Cancel</button>
            <button type="button" id="addStockConfirmBtn" class="btn-primary" style="height:44px;border-radius:10px;padding:0 24px;background:#059669;">Confirm</button>
        </div>
    </div>
</div>

<!-- Stock Card View Modal (SaaS-style) -->
<div id="stockCardModal" class="modal">
    <div class="modal-content" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title" id="scName" style="padding-right:30px; word-break:break-all; overflow-wrap:anywhere;">Item Name</h3>
            <button class="close-btn" onclick="closeStockCard()">Ã—</button>
        </div>

        <!-- Summary Cards -->
        <div class="sc-cards-grid" id="scCardsContainer">
            <div class="sc-card" style="background:#f0f9ff; border:1px solid #bae6fd;">
                <div class="sc-card-label" style="font-size:11px; font-weight:700; color:#0369a1; text-transform:uppercase; letter-spacing:0.05em;">Current Stock</div>
                <div style="display:flex; align-items:baseline; justify-content:center; gap:4px;">
                    <span class="sc-card-value" id="scStock" style="font-size:20px; font-weight:800; color:#0c4a6e;">0</span>
                    <span id="scUnit" style="font-size:11px; font-weight:700; color:#0ea5e9; text-transform:uppercase;">UNIT</span>
                </div>
            </div>
            <div class="sc-card" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                <div class="sc-card-label" style="font-size:11px; font-weight:700; color:#15803d; text-transform:uppercase; letter-spacing:0.05em;">Reorder Level</div>
                <div class="sc-card-value" id="scMinStock" style="font-size:20px; font-weight:800; color:#166534;">0</div>
            </div>
            <div class="sc-card" id="scStatusCard" style="border:1px solid; display:flex; flex-direction:column; justify-content:center;">
                <div class="sc-card-label" style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">Stock Status</div>
                <div class="sc-card-value" id="scStatusText" style="font-size:16px; font-weight:700;">â€”</div>
            </div>
            <div class="sc-card" id="scRollCard" style="background:#fdf2f8; border:1px solid #fbcfe8;">
                <div class="sc-card-label" style="font-size:11px; font-weight:700; color:#be185d; text-transform:uppercase; letter-spacing:0.05em;">Available Rolls</div>
                <div style="display:flex; align-items:baseline; justify-content:center; gap:4px;">
                    <span class="sc-card-value" id="scRoll" style="font-size:20px; font-weight:800; color:#9d174d;">0</span>
                    <span style="font-size:11px; font-weight:700; color:#db2777; text-transform:uppercase;">Rolls</span>
                </div>
            </div>
        </div>

        <!-- Stock Health Progress Bar -->
        <div style="margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <span style="font-size:12px; font-weight:600; color:#4b5563;">Stock vs Reorder Level</span>
                <span id="scProgressText" style="font-size:12px; font-weight:700; color:#374151;">0 / 0</span>
            </div>
            <div id="scProgressBg" style="height:10px; background:#e5e7eb; border-radius:9999px; overflow:hidden;">
                <div id="scProgressFill" style="height:100%; border-radius:9999px; transition: width 0.3s, background 0.3s;"></div>
            </div>
        </div>

        <!-- Smart Status Message -->
        <div id="scStatusMsg" style="padding:12px 16px; border-radius:10px; font-size:14px; font-weight:600; margin-bottom:20px;"></div>

        <!-- Product Details -->
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:14px 16px; margin-bottom:20px;">
            <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Product Details</div>
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:8px 24px; font-size:13px;">
                <div><span style="color:#6b7280;">Category:</span> <span id="scCategory" style="font-weight:600;">â€”</span></div>
                <div><span style="color:#6b7280;">Unit:</span> <span id="scUnitDetail" style="font-weight:600;">â€”</span></div>
                <div><span style="color:#6b7280;">Unit Cost:</span> <span id="scUnitCost" style="font-weight:600;">â€”</span></div>
                <div><span style="color:#6b7280;">Tracking Type:</span> <span id="scTrackType" style="font-weight:600;">â€”</span></div>
                <div style="grid-column:1/-1;"><span style="color:#6b7280;">Last Updated:</span> <span id="scLastUpdated" style="font-weight:600;">â€”</span></div>
            </div>
        </div>

        <!-- Recent Activity (5 records) -->
        <div style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <span style="font-size:13px; font-weight:700; color:#374151;">Recent Activity</span>
                <a id="scSeeAllLedgerLink" href="#" style="font-size:13px; font-weight:600; color:#0d9488; text-decoration:none;">See all â†’</a>
            </div>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
                <table style="width:100%; font-size:13px; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="text-align:left; padding:12px; color:#6b7280; width:100px;">Date</th>
                            <th style="text-align:left; padding:12px; color:#6b7280; width:120px;">Action</th>
                            <th style="text-align:right; padding:12px; color:#6b7280; width:100px;">Quantity</th>
                            <th style="text-align:right; padding:12px; color:#6b7280; width:120px;">Balance After</th>
                        </tr>
                    </thead>
                    <tbody id="scLedgerBody">
                        <tr><td colspan="4" style="text-align:center; padding:24px; color:#9ca3af;">No recent activity</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:center;">
            <button onclick="closeStockCard(); if(selectedItemForStockCard) openAddStockModal(selectedItemForStockCard)" class="btn-action teal" style="flex:1; min-width:140px; height:40px; font-size:14px; border-radius:10px;">+ Add Stock</button>
            <button onclick="closeStockCard(); if(selectedItemForStockCard) openDeductStockModal(selectedItemForStockCard)" class="btn-action red" style="flex:1; min-width:140px; height:40px; font-size:14px; border-radius:10px;">âˆ’ Deduct Stock</button>
            <button onclick="editFromStockCard()" class="btn-action blue" style="flex:1; min-width:120px; height:40px; font-size:14px; border-radius:10px;">Edit Settings</button>
            <a id="scLedgerLink" href="inv_transactions_ledger.php" class="btn-action" style="flex:1; min-width:120px; height:40px; font-size:14px; border-radius:10px; border:1px solid #e5e7eb; color:#374151; display:flex; align-items:center; justify-content:center; text-decoration:none;">View Full Ledger</a>
        </div>
    </div>
</div>

<!-- Deduct Stock Modal -->
<div id="deductStockModal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" style="padding-right:30px;">Deduct Stock</h3>
                <p style="font-size:13px;color:#6b7280;margin-top:2px; padding-right:30px; overflow-wrap:break-word; word-break:break-word; hyphens:auto;" id="deductStockItemName">Item Name</p>
            </div>
            <button class="close-btn" onclick="closeDeductStockModal()">&times;</button>
        </div>
        <form id="deductStockForm" onsubmit="saveDeductStock(event)">
            <input type="hidden" id="deductStockItemId">
            <input type="hidden" id="deductStockUom">
            <div class="form-row-grid">
                <div>
                    <label for="deductStockQty">Quantity to Deduct *</label>
                    <input type="number" step="0.01" min="0.01" id="deductStockQty" required placeholder="e.g. 10" class="w-100">
                    <span id="err-deductStockQty" class="field-error"></span>
                </div>
                <div>
                    <label for="deductStockNotes">Notes</label>
                    <input type="text" id="deductStockNotes" placeholder="e.g. Used for job order" class="w-100">
                </div>
            </div>
            <div style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid #f3f4f6;">
                <button type="button" onclick="closeDeductStockModal()" class="btn-secondary" style="height:44px;border-radius:10px;padding:0 24px;">Cancel</button>
                <button type="submit" id="deductStockBtn" class="btn-primary" style="height:44px;border-radius:10px;padding:0 24px;background:#dc2626;">Deduct Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div id="itemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle" style="padding-right:30px;">Material Settings</h3>
            <button class="close-btn" onclick="closeModal()">Ã—</button>
        </div>
        <!-- Top Info (Edit mode only) -->
        <!-- Top Info Removed -->
        <form id="itemForm" onsubmit="saveItem(event)">
            <input type="hidden" id="itemId" name="id">
            <input type="hidden" id="actionType" name="action" value="create_item">

            <!-- Section: Material Information -->
            <div class="modal-section">
                <p class="section-header">Material Information</p>
                <div class="form-row-grid" style="grid-template-columns: 2fr 1fr;">
                    <div>
                        <label for="itemName">Item Name <span style="color:#ef4444">*</span></label>
                        <input type="text" id="itemName" name="name" required placeholder="name" class="w-100">
                        <span id="err-itemName" class="field-error"></span>
                    </div>
                    <div>
                        <label for="itemCategory">Category <span style="color:#ef4444">*</span></label>
                        <select id="itemCategory" name="category_id" class="w-100" onchange="handleCategoryChange()">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                        data-uom="<?php echo htmlspecialchars($cat['default_uom'] ?? ''); ?>"
                                        data-roll="<?php echo $cat['default_track_by_roll'] ?? 0; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="err-itemCategory" class="field-error"></span>
                    </div>
                </div>
                <div class="form-row-grid">
                    <div>
                        <label for="itemUnit">Unit of Measure (UOM) <span style="font-size:10px; color:#9ca3af; font-weight:normal;">(Auto-generated)</span></label>
                        <select id="itemUnit" name="unit" required onchange="handleUomChange()" class="w-100 locked-select">
                            <option value="">Select Category First</option>
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="ft">Feet (ft)</option>
                            <option value="btl">Bottles (btl)</option>
                        </select>
                        <span id="err-itemUnit" class="field-error"></span>
                    </div>
                    <div>
                        <label for="itemUnitCost">Unit Cost (â‚±) <span style="color:#ef4444">*</span></label>
                        <input type="number" step="0.01" min="0" id="itemUnitCost" name="unit_cost" value="0.00" required class="w-100">
                        <span id="err-itemUnitCost" class="field-error"></span>
                    </div>
                </div>
                <div class="form-row-grid" style="grid-template-columns: 1.2fr 1fr 1fr; gap:16px;">
                    <div>
                        <label for="itemTrackByRoll">Tracking Mode <span style="font-size:10px; color:#9ca3af; font-weight:normal;">(Auto-generated)</span></label>
                        <select id="itemTrackByRoll" name="track_by_roll" class="w-100 locked-select">
                            <option value="0">Standard (Ledger)</option>
                            <option value="1">Roll-Based (Individual)</option>
                        </select>
                    </div>
                    <div>
                        <label for="itemMinStock">Reorder Level <span style="color:#ef4444">*</span></label>
                        <input type="number" step="0.01" min="0" id="itemMinStock" name="min_stock_level" value="0.00" required class="w-100">
                        <span id="err-itemMinStock" class="field-error"></span>
                    </div>
                    <div>
                        <label>Preview Status</label>
                        <div id="editModalReorderPreview" class="preview-badge" style="height:38px; display:flex; align-items:center; justify-content:center; padding:0 12px; font-weight:600;">In Stock</div>
                    </div>
                </div>
            </div>

            <!-- Alerts Section -->
            <div id="reorderAlertsGroup" class="modal-section" style="margin-top:-24px; margin-bottom: 16px;">
                <div class="form-col-full">
                    <p style="font-size:11px; color:#6b7280; margin-bottom:4px;">You will be warned when stock reaches the Reorder Level.</p>
                    <div id="editModalReorderWarnHigh" style="display:none; font-size:11px; color:#854d0e; background:#fef9c3; padding:8px 12px; border-radius:8px; border:1px solid #fde68a;">âš ï¸ This may mark your stock as low immediately</div>
                    <div id="editModalReorderWarnLow" style="display:none; font-size:11px; color:#854d0e; background:#fef9c3; padding:8px 12px; border-radius:8px; border:1px solid #fde68a;">âš ï¸ You may run out of stock before being warned</div>
                    <div id="editModalReorderError" style="display:none; font-size:11px; color:#dc2626; margin-top:2px;">Please enter a value between 0.01 and 10,000</div>
                </div>
            </div>

            <!-- Bottom Row: Initial Balance | Roll Settings | System Status -->
            <div class="form-row" style="display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; align-items: stretch; gap: 16px; margin-bottom: 20px;">
                <!-- Column 1: Initial Stock (Create Mode Only) -->
                <div id="startingStockGroup" style="display:none; background:#fcfcfd; border:1px solid #f3f4f6; padding:16px; border-radius:12px;">
                    <p class="section-header" style="margin-top:0;">Initial Balance</p>
                    <label for="itemStartingStock">Initial Stock Quantity</label>
                    <input type="number" step="0.01" min="0" id="itemStartingStock" name="starting_stock" value="0.00" class="w-100">
                    <p style="font-size:10px; color:#9ca3af; margin-top:8px; line-height: 1.4;">Opening balance for new item record</p>
                </div>

                <!-- Column 2: Roll Settings (UOM: ft Only) -->
                <div id="rollSettingsSection" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:12px; padding:16px;">
                    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0369a1;margin-bottom:14px;border-bottom:1px solid #bae6fd;padding-bottom:8px;">Roll Settings</p>
                    <label for="itemRollLength">Roll Length (ft) <span style="color:#ef4444" id="rollLengthRequired">*</span></label>
                    <input type="number" step="0.01" min="1" max="1000" id="itemRollLength" name="roll_length_ft" placeholder="e.g. 164.00" class="w-100">
                    <span id="err-itemRollLength" class="field-error"></span>
                </div>

                <!-- Column 3: System Status -->
                <div id="statusSection" class="modal-section" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:0;">
                    <p class="section-header" style="margin-top:0;">System Status</p>
                    <div style="margin-bottom:0;">
                        <label for="itemStatus">Status</label>
                        <select id="itemStatus" name="status" class="w-100">
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                        </select>
                        <div id="statusHelperMessage" style="display:none; margin-top:8px;">
                            <p style="font-size:11px;color:#9ca3af;line-height:1.4;">Inactive materials are hidden from new orders and POS.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Change Summary (Edit mode, when changed) -->
            <div id="editModalChangeSummary" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px 16px; margin-bottom:16px;">
                <div style="font-size:12px; font-weight:700; color:#166534; margin-bottom:8px;">Changes Summary</div>
                <div id="editModalChangeSummaryContent" style="font-size:13px; color:#374151;"></div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top:16px; border-top:1px solid #f3f4f6;">
                <button type="button" onclick="closeModal()" class="btn-secondary" style="border-radius: 10px; height: 44px; padding: 0 24px;">Cancel</button>
                <button type="submit" class="btn-primary" id="saveBtn" style="border-radius: 10px; height: 44px; padding: 0 24px; background: #10b981; border:none; transition: background 0.2s;">Save Changes</button>
            </div>
        </form>
    </div>
</div>




  <script>
    /* var: Turbo re-executes this block; let/const would throw "already been declared". */
    var ADMIN_API_BASE = '/printflow/admin/';
    var currentItems = <?php echo $items_js; ?>;
    var usageChart = null;
    var selectedItemForStockCard = null;
    var editItemOriginalValues = {};
    var currentPage = <?php echo $page; ?>;
    var currentSort = '<?php echo $sort; ?>';
    var currentDir = '<?php echo $dir; ?>';
    var searchDebounceTimer = null;
    var addStockPendingSubmit = false;

    function filterPanel() {
        return {
            sortOpen: false,
            filterOpen: false,
            activeSort: '<?php echo $sort === 'name' ? ($dir === 'ASC' ? 'az' : 'za') : ($sort === 'id' ? ($dir === 'DESC' ? 'newest' : 'oldest') : 'az'); ?>',
            get hasActiveFilters() {
                return document.getElementById('fp_category')?.value ||
                       document.getElementById('fp_track_by_roll')?.value !== '' ||
                       document.getElementById('fp_search')?.value;
            }
        };
    }
    window.filterPanel = filterPanel;

    function printflowInitInvItemsBindings() {
        if (!document.getElementById('inv-filter-toolbar')) return;
        var searchInput = document.getElementById('fp_search');
        if (searchInput && !searchInput._pf_bound) {
            searchInput._pf_bound = true;
            searchInput.addEventListener('input', function () {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(function () { fetchUpdatedTable({ page: 1 }); }, 500);
            });
        }
        var catSelect = document.getElementById('fp_category');
        if (catSelect && !catSelect._pf_bound) {
            catSelect._pf_bound = true;
            catSelect.addEventListener('change', function () { fetchUpdatedTable({ page: 1 }); });
        }
        var trackBySelect = document.getElementById('fp_track_by_roll');
        if (trackBySelect && !trackBySelect._pf_bound) {
            trackBySelect._pf_bound = true;
            trackBySelect.addEventListener('change', function () { fetchUpdatedTable({ page: 1 }); });
        }
        var addStockQty = document.getElementById('addStockQty');
        if (addStockQty && !addStockQty._pf_bound) {
            addStockQty._pf_bound = true;
            addStockQty.addEventListener('input', updateAddStockUI);
            addStockQty.addEventListener('change', updateAddStockUI);
        }
        ['itemName', 'itemCategory', 'itemUnit', 'itemUnitCost', 'itemMinStock', 'itemTrackByRoll', 'itemStatus'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && !el._pf_bound) {
                el._pf_bound = true;
                var eventType = (el.tagName === 'SELECT') ? 'change' : 'input';
                el.addEventListener(eventType, function () { validateField(id); });
                if (id === 'itemName') el.addEventListener('input', handleItemNameInput);
            }
        });
    }

    function handleItemNameInput(e) {
        let val = e.target.value;
        // Block leading spaces
        if (val.startsWith(' ')) {
            val = val.trimStart();
        }
        // Auto Capitalize (Title Case)
        e.target.value = val.replace(/\b\w/g, l => l.toUpperCase());
        validateField('itemName');
    }

    function validateField(id) {
        const el = document.getElementById(id);
        const errEl = document.getElementById('err-' + id);
        let isValid = true;
        let msg = '';

        const val = el.value.trim();

        switch(id) {
            case 'itemName':
                if (!val) { msg = 'Item name is required.'; isValid = false; }
                else if (val.length < 2) { msg = 'Item name must be at least 2 characters.'; isValid = false; }
                else if (/^\d+$/.test(val)) { msg = 'Item name cannot contain only numbers.'; isValid = false; }
                break;
            case 'itemCategory':
                if (!val) { msg = 'Please select a category.'; isValid = false; }
                break;
            case 'itemUnit':
                if (!val) { msg = 'Please select a unit of measure.'; isValid = false; }
                break;
            case 'itemUnitCost':
                const cost = parseFloat(val);
                if (isNaN(cost)) { msg = 'Unit cost is required.'; isValid = false; }
                else if (cost <= 0) { msg = 'Unit cost must be greater than 0.'; isValid = false; }
                else if (cost > 1000000) { msg = 'Unit cost is too high (max 1M).'; isValid = false; }
                break;
            case 'itemMinStock':
                const min = parseFloat(val);
                if (isNaN(min)) { msg = 'Reorder level is required.'; isValid = false; }
                else if (min < 0) { msg = 'Reorder level cannot be negative.'; isValid = false; }
                break;
        }

        updateValidationUI(id, isValid, msg);
        updateSaveButtonState();
        if (id === 'itemMinStock' || id === 'itemName') updateEditModalUI();
        return isValid;
    }

    function updateValidationUI(id, isValid, msg) {
        const el = document.getElementById(id);
        const errEl = document.getElementById('err-' + id);
        if (!errEl) return;

        if (!isValid) {
            el.classList.add('input-error');
            el.classList.remove('input-success');
            errEl.textContent = msg;
            errEl.style.display = 'block';
        } else {
            el.classList.remove('input-error');
            if (el.value.trim() !== '') el.classList.add('input-success');
            else el.classList.remove('input-success');
            errEl.style.display = 'none';
        }
    }

    function updateSaveButtonState() {
        const fields = ['itemName', 'itemCategory', 'itemUnit', 'itemUnitCost', 'itemMinStock'];
        let allValid = true;
        
        fields.forEach(id => {
            const el = document.getElementById(id);
            const val = el.value.trim();
            if (!val) allValid = false;
            if (el.classList.contains('input-error')) allValid = false;
        });

        // Special check for Roll Length if UOM is ft
        const uom = document.getElementById('itemUnit').value;
        if (uom === 'ft') {
            const rl = document.getElementById('itemRollLength').value;
            const rlVal = parseFloat(rl);
            if (isNaN(rlVal) || rlVal < 1 || rlVal > 1000) allValid = false;
        }

        document.getElementById('saveBtn').disabled = !allValid;
    }

    function buildFilterURL(overrides = {}, isAjax = false) {
        const params = new URLSearchParams(window.location.search);
        
        const map = {
            'category_id': 'fp_category',
            'track_by_roll': 'fp_track_by_roll',
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

                if (tbody) {
                    tbody.innerHTML = data.table;
                    if (typeof Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
                        try {
                            Alpine.initTree(tbody);
                        } catch (e) {
                            console.error(e);
                        }
                    }
                }
                if (pagination) pagination.innerHTML = data.pagination;
                const showingMeta = document.getElementById('invShowingMeta');
                if (showingText && showingMeta) {
                    showingText.textContent = data.startIdx + 'Î“Ã‡Ã´' + data.endIdx;
                    showingMeta.textContent = ' of ' + data.total + ' items)';
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
        
        const root = document.getElementById('inv-filter-toolbar');
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

    function fmtQty(val, isPcs) {
        return isPcs ? String(Math.round(val)) : parseFloat(val).toFixed(2);
    }

    async function openStockCard(itemId) {
        if (!document.getElementById('stockCardModal')) return;
        const item = currentItems.find(i => i.id == itemId);
        if (!item) return;
        selectedItemForStockCard = item;
        
        const stock = parseFloat(item.current_stock || 0);
        const reorder = parseFloat(item.reorder_level || 0);
        const uom = (item.unit_of_measure || 'pcs').toUpperCase();
        const isPcs = (item.unit_of_measure || '').toLowerCase() === 'pcs';
        
        // Stock status (computed dynamically)
        let statusText, statusBg, statusColor, statusBorder;
        if (stock <= 0) {
            statusText = 'Out of Stock';
            statusBg = '#fef2f2'; statusColor = '#991b1b'; statusBorder = '#fecaca';
        } else if (reorder > 0 && stock <= reorder) {
            statusText = 'Low Stock';
            statusBg = '#fef9c3'; statusColor = '#854d0e'; statusBorder = '#fde68a';
        } else {
            statusText = 'In Stock';
            statusBg = '#dcfce7'; statusColor = '#166534'; statusBorder = '#bbf7d0';
        }
        
        document.getElementById('scName').textContent = item.name;
        document.getElementById('scStock').textContent = fmtQty(stock, isPcs);
        document.getElementById('scUnit').textContent = uom;
        document.getElementById('scMinStock').textContent = fmtQty(reorder, isPcs);
        document.getElementById('scStatusText').textContent = statusText;
        document.getElementById('scStatusCard').style.background = statusBg;
        document.getElementById('scStatusCard').style.borderColor = statusBorder;
        document.getElementById('scStatusCard').querySelector('.sc-card-value').style.color = statusColor;
        
        const rollCard = document.getElementById('scRollCard');
        rollCard.style.display = item.track_by_roll == 1 ? 'block' : 'none';
        document.getElementById('scRoll').textContent = '0';
        
        // Progress bar (stock vs reorder)
        const maxVal = Math.max(reorder, stock, 1);
        const pct = Math.min(100, (stock / maxVal) * 100);
        document.getElementById('scProgressFill').style.width = pct + '%';
        document.getElementById('scProgressText').textContent = fmtQty(stock, isPcs) + ' / ' + fmtQty(reorder, isPcs);
        let progColor = '#10b981';
        if (stock <= 0) progColor = '#dc2626';
        else if (reorder > 0 && stock <= reorder) progColor = '#eab308';
        document.getElementById('scProgressFill').style.background = progColor;
        
        // Status message
        const msgEl = document.getElementById('scStatusMsg');
        if (stock <= 0) {
            msgEl.textContent = 'Out of stock. Immediate restocking required';
            msgEl.style.background = '#fef2f2'; msgEl.style.color = '#991b1b'; msgEl.style.border = '1px solid #fecaca';
        } else if (reorder > 0 && stock <= reorder) {
            msgEl.textContent = 'Stock is getting low. Consider restocking';
            msgEl.style.background = '#fef9c3'; msgEl.style.color = '#854d0e'; msgEl.style.border = '1px solid #fde68a';
        } else {
            msgEl.textContent = 'Stock level is healthy';
            msgEl.style.background = '#dcfce7'; msgEl.style.color = '#166534'; msgEl.style.border = '1px solid #bbf7d0';
        }
        
        // Product details
        document.getElementById('scCategory').textContent = item.category_name || 'Uncategorized';
        document.getElementById('scUnitDetail').textContent = uom;
        document.getElementById('scUnitCost').textContent = 'Î“Ã©â–’' + parseFloat(item.unit_cost || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('scTrackType').textContent = item.track_by_roll == 1 ? 'Roll-Based' : 'Standard';
        document.getElementById('scLastUpdated').textContent = item.updated_at ? new Date(item.updated_at).toLocaleDateString() : 'Î“Ã‡Ã¶';
        
        document.getElementById('scLedgerLink').href = `inv_transactions_ledger.php?item_id=${item.id}`;
        const ledgerUrl = `inv_transactions_ledger.php?item_id=${item.id}`;
        const seeAllLink = document.getElementById('scSeeAllLedgerLink');
        if (seeAllLink) { seeAllLink.href = ledgerUrl; }
        document.getElementById('stockCardModal').style.display = 'flex';
        
        document.getElementById('scLedgerBody').innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#9ca3af;">Loading...</td></tr>';

        try {
            const res = await fetch(ADMIN_API_BASE + `inventory_stock_card_api.php?item_id=${item.id}`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('scRoll').textContent = (data.rolls || []).length;
                
                const ledger = data.ledger || [];
                let ledgerHtml = '';
                ledger.forEach(l => {
                    const isIN = l.direction === 'IN';
                    const qty = parseFloat(l.quantity);
                    const qtyDisp = (isIN ? '+' : '-') + fmtQty(qty, data.is_pcs);
                    const balDisp = fmtQty(parseFloat(l.balance_after || 0), data.is_pcs);
                    const qtyColor = isIN ? '#059669' : '#dc2626';
                    const act = l.action_display || (l.ref_type || '').replace(/_/g, ' ');
                    ledgerHtml += `<tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:10px 12px;color:#6b7280;white-space:nowrap;">${(l.transaction_date || '').split(' ')[0]}</td>
                        <td style="padding:10px 12px;text-transform:capitalize;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${act}">${act}</td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;color:${qtyColor};">${qtyDisp}</td>
                        <td style="padding:10px 12px;text-align:right;font-weight:600;color:#374151;">${balDisp}</td>
                    </tr>`;
                });
                document.getElementById('scLedgerBody').innerHTML = ledgerHtml || '<tr><td colspan="4" style="text-align:center;padding:24px;color:#9ca3af;">No recent activity</td></tr>';
            } else {
                document.getElementById('scLedgerBody').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:24px;color:#9ca3af;">No recent activity</td></tr>';
            }
        } catch (e) {
            console.error('Stock Card API error:', e);
            document.getElementById('scLedgerBody').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:24px;color:#9ca3af;">No recent activity</td></tr>';
        }
    }

    function closeStockCard() {
        document.getElementById('stockCardModal').style.display = 'none';
    }

    function handleCategoryChange() {
        const catSelect = document.getElementById('itemCategory');
        const selectedOpt = catSelect.options[catSelect.selectedIndex];
        if (!selectedOpt || !selectedOpt.value) return;

        const uom = selectedOpt.getAttribute('data-uom');
        const isRoll = selectedOpt.getAttribute('data-roll');

        // Only auto-fill if it's a NEW item (mode check)
        const isCreate = document.getElementById('actionType').value === 'create_item';
        if (isCreate) {
            if (uom) {
                document.getElementById('itemUnit').value = uom;
                handleUomChange();
                // Lock them
                document.getElementById('itemUnit').disabled = true;
                document.getElementById('itemUnit').style.background = '#fcfcfd';
                document.getElementById('itemTrackByRoll').disabled = true;
                document.getElementById('itemTrackByRoll').style.background = '#fcfcfd';
            }
            if (isRoll !== null) {
                document.getElementById('itemTrackByRoll').value = isRoll;
            }
        }
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
            rollSec.style.display = 'block';
            lengthInput.required = true;
            lengthReq.style.display = 'inline';
            document.getElementById('itemTrackByRoll').value = '1';
        } else {
            rollSec.style.display = 'none';
            lengthInput.required = false;
            lengthReq.style.display = 'none';
        }
    }

    function openModal(mode, item = null) {
        const modal = document.getElementById('itemModal');
        modal.style.display = 'flex';
        const form = document.getElementById('itemForm');
        form.reset();
        
        // Top info removed
        editItemOriginalValues = {};
        
        // Clear previous validation states
        ['itemName', 'itemCategory', 'itemUnit', 'itemUnitCost', 'itemMinStock'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('input-error', 'input-success');
                const err = document.getElementById('err-' + id);
                if (err) { err.style.display = 'none'; err.textContent = ''; }
            }
        });
        document.getElementById('saveBtn').disabled = true;
        
        handleUomChange();

        if (mode === 'create') {
            document.getElementById('modalTitle').textContent = 'Add New Material';
            document.getElementById('actionType').value = 'create_item';
            document.getElementById('itemId').value = '';
            document.getElementById('itemUnitCost').value = '0.00';
            document.getElementById('itemMinStock').value = '1';
            document.getElementById('startingStockGroup').style.display = 'block';
            document.getElementById('itemStatus').value = 'ACTIVE';
            
            // Lock them by default for new items until category is selected
            document.getElementById('itemUnit').disabled = true;
            document.getElementById('itemUnit').style.background = '#fcfcfd';
            document.getElementById('itemTrackByRoll').disabled = true;
            document.getElementById('itemTrackByRoll').style.background = '#fcfcfd';

            selectedItemForStockCard = null;
            setTimeout(updateEditModalUI, 0);
        } else {
            document.getElementById('modalTitle').textContent = 'Edit Material Settings';
            document.getElementById('actionType').value = 'update_item';
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemName').value = item.name;
            document.getElementById('itemCategory').value = item.category_id || '';
            document.getElementById('itemUnit').value = item.unit_of_measure;
            document.getElementById('itemUnitCost').value = item.unit_cost || '0.00';
            document.getElementById('itemTrackByRoll').value = item.track_by_roll;
            document.getElementById('itemMinStock').value = item.reorder_level || '1';
            document.getElementById('itemStatus').value = item.status;
            document.getElementById('startingStockGroup').style.display = 'none';

            // Lock them in Edit mode
            document.getElementById('itemUnit').disabled = true;
            document.getElementById('itemUnit').style.background = '#fcfcfd';
            document.getElementById('itemTrackByRoll').disabled = true;
            document.getElementById('itemTrackByRoll').style.background = '#fcfcfd';
            if (item.unit_of_measure === 'ft') {
                document.getElementById('itemRollLength').value = item.default_roll_length_ft || '';
                setTimeout(handleUomChange, 0);
            }
            editItemOriginalValues = {
                reorder_level: String(item.reorder_level || '0'),
                roll_length: String(item.default_roll_length_ft || '')
            };
            selectedItemForStockCard = item;
            // Stock info removed from modal top
        }
        setTimeout(updateEditModalUI, 50);
    }

    function updateEditModalUI() {
        const actionTypeEl = document.getElementById('actionType');
        if (!actionTypeEl) return;
        const isEdit = actionTypeEl.value === 'update_item';
        const minStock = document.getElementById('itemMinStock');
        const rollLength = document.getElementById('itemRollLength');
        const reorderVal = parseFloat(minStock?.value || 0);
        const rollVal = parseFloat(rollLength?.value || 0);
        const currentStock = parseFloat(selectedItemForStockCard?.current_stock || 0);
        
        const uom = document.getElementById('itemUnit')?.value || 'pcs';
        const isPcs = (uom || '').toLowerCase() === 'pcs';
        
        let reorderValid = true;
        const reorderErr = document.getElementById('err-itemMinStock');
        const reorderWarnHigh = document.getElementById('editModalReorderWarnHigh');
        const reorderWarnLow = document.getElementById('editModalReorderWarnLow');
        
        if (reorderVal <= 0 || reorderVal > 10000) {
            reorderValid = false;
            if (reorderErr) reorderErr.style.display = 'block';
            if (reorderWarnHigh) reorderWarnHigh.style.display = 'none';
            if (reorderWarnLow) reorderWarnLow.style.display = 'none';
        } else {
            if (reorderErr) reorderErr.style.display = 'none';
            if (reorderWarnHigh) reorderWarnHigh.style.display = (isEdit && currentStock > 0 && reorderVal > currentStock) ? 'block' : 'none';
            if (reorderWarnLow) reorderWarnLow.style.display = (reorderVal > 0 && reorderVal < 10) ? 'block' : 'none';
        }
        
        let statusPreview = 'Î“Ã‡Ã¶';
        let badgeStyle = 'background:#f9fafb; color:#374151;';
        if (reorderVal >= 0) {
            if (isEdit) {
                if (currentStock <= 0) {
                    statusPreview = 'Out of Stock';
                    badgeStyle = 'background:#fef2f2; color:#991b1b; border-color:#fecaca;';
                } else if (currentStock <= reorderVal) {
                    statusPreview = 'Low Stock';
                    badgeStyle = 'background:#fef9c3; color:#854d0e; border-color:#fde68a;';
                } else {
                    statusPreview = 'In Stock';
                    badgeStyle = 'background:#dcfce7; color:#166534; border-color:#bbf7d0;';
                }
            } else {
                statusPreview = 'In Stock (Initial)';
                badgeStyle = 'background:#dcfce7; color:#166534; border-color:#bbf7d0;';
            }
        }
        const previewEl = document.getElementById('editModalReorderPreview');
        if (previewEl) {
            previewEl.textContent = statusPreview;
            previewEl.style.cssText = badgeStyle;
        }

        const status = document.getElementById('itemStatus')?.value;
        const statusHelper = document.getElementById('statusHelperMessage');
        if (statusHelper) {
            statusHelper.style.display = status === 'INACTIVE' ? 'block' : 'none';
        }
        
        const rollSec = document.getElementById('rollSettingsSection');
        const rollErr = document.getElementById('err-itemRollLength');
        const rollSectionVisible = document.getElementById('itemUnit')?.value === 'ft';
        let rollValid = true;
        if (rollSectionVisible) {
            rollValid = !isNaN(rollVal) && rollVal >= 1 && rollVal <= 1000;
            if (rollErr) rollErr.style.display = (!rollValid && String(rollLength?.value || '').trim() !== '') ? 'block' : 'none';
        }
        
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) saveBtn.disabled = !reorderValid || (rollSectionVisible && !rollValid);
        
        if (isEdit && editItemOriginalValues && Object.keys(editItemOriginalValues).length) {
            const changes = [];
            const origReorder = parseFloat(editItemOriginalValues.reorder_level || 0);
            if (Math.abs(reorderVal - origReorder) > 0.001) {
                changes.push('Reorder Level: ' + (origReorder || '0') + ' Î“Ã¥Ã† ' + (isPcs ? Math.round(reorderVal) : reorderVal.toFixed(2)));
                if (statusPreview !== 'Î“Ã‡Ã¶') changes.push('This will change stock status to ' + statusPreview);
            }
            const origRoll = parseFloat(editItemOriginalValues.roll_length || 0);
            if (rollSectionVisible && Math.abs(rollVal - origRoll) > 0.001) {
                changes.push('Standard Roll Length: ' + (editItemOriginalValues.roll_length || 'Î“Ã‡Ã¶') + ' Î“Ã¥Ã† ' + rollVal);
            }
            const sumEl = document.getElementById('editModalChangeSummary');
            const sumContent = document.getElementById('editModalChangeSummaryContent');
            if (sumEl && sumContent) {
                if (changes.length > 0) {
                    sumEl.style.display = 'block';
                    sumContent.innerHTML = changes.map(c => '<div style="margin-bottom:4px;">Î“Ã‡Ã³ ' + c + '</div>').join('');
                } else {
                    sumEl.style.display = 'none';
                }
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
        const currentStock = parseFloat(item.current_stock || 0);
        document.getElementById('addStockItemName').textContent = item.name;
        document.getElementById('addStockItemId').value = item.id;
        document.getElementById('addStockIsRoll').value = item.track_by_roll;
        document.getElementById('addStockUom').value = item.unit_of_measure || 'pcs';
        document.getElementById('addStockCurrentStock').value = currentStock;
        document.getElementById('addStockQty').value = '';
        document.getElementById('addStockRollCode').value = '';
        document.getElementById('addStockWidth').value = item.default_roll_width_ft || '1'; // Default to 1 if no width specified
        document.getElementById('addStockNotes').value = '';
        document.getElementById('addStockQtyError').style.display = 'none';
        document.getElementById('addStockLargeWarning').style.display = 'none';
        
        const isRoll = item.track_by_roll == 1;
        // Hidden per user request ("you can hide them") - keeps modal clean for simple intake
        document.getElementById('addStockRollGroup').style.display = 'none';
        document.getElementById('addStockWidth').required = false; 
        
        updateAddStockUI();
        document.getElementById('addStockModal').style.display = 'flex';
        setTimeout(() => {
            const qtyInput = document.getElementById('addStockQty');
            if (qtyInput) qtyInput.focus();
        }, 150);
    }

    function updateAddStockUI() {
        const qtyInput = document.getElementById('addStockQty');
        const currentStock = parseFloat(document.getElementById('addStockCurrentStock').value || 0);
        const raw = (qtyInput && qtyInput.value) ? String(qtyInput.value).trim() : '';
        const num = parseFloat(raw);
        const uom = document.getElementById('addStockUom').value || 'pcs';
        const isPcs = (uom || '').toLowerCase() === 'pcs';
        
        let valid = false;
        const errEl = document.getElementById('addStockQtyError');
        const previewEl = document.getElementById('addStockPreview');
        const warnEl = document.getElementById('addStockLargeWarning');
        const btn = document.getElementById('addStockBtn');
        
        if (raw === '') {
            errEl.style.display = 'none';
            previewEl.textContent = 'New Stock After Adding: Î“Ã‡Ã¶';
            warnEl.style.display = 'none';
        } else if (isNaN(num) || num <= 0 || num > 100000) {
            errEl.style.display = 'block';
            previewEl.textContent = 'New Stock After Adding: Î“Ã‡Ã¶';
            warnEl.style.display = 'none';
        } else {
            valid = true;
            errEl.style.display = 'none';
            const newTotal = currentStock + num;
            const disp = isPcs ? Math.round(newTotal) : newTotal.toFixed(2);
            previewEl.textContent = 'New Stock After Adding: ' + disp;
            warnEl.style.display = (num > currentStock * 2) ? 'block' : 'none';
        }
        if (btn) btn.disabled = !valid;
    }

    function closeAddStockConfirmModal() {
        document.getElementById('addStockConfirmModal').style.display = 'none';
        addStockPendingSubmit = false;
    }

    function closeAddStockModal() {
        document.getElementById('addStockModal').style.display = 'none';
    }

    function openDeductStockModal(item) {
        document.getElementById('deductStockItemName').textContent = item.name;
        document.getElementById('deductStockItemId').value = item.id;
        document.getElementById('deductStockUom').value = item.unit_of_measure || 'pcs';
        document.getElementById('deductStockQty').value = '';
        document.getElementById('deductStockNotes').value = '';
        document.getElementById('deductStockModal').style.display = 'flex';
        setTimeout(() => document.getElementById('deductStockQty')?.focus(), 150);
    }

    function closeDeductStockModal() {
        document.getElementById('deductStockModal').style.display = 'none';
    }

    async function saveDeductStock(e) {
        if (e && e.preventDefault) e.preventDefault();
        
        // Clear previous errors
        document.querySelectorAll('.field-error').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        
        const btn = document.getElementById('deductStockBtn');
        btn.disabled = true; btn.textContent = 'Saving...';
        const fd = new FormData();
        fd.set('action', 'record_transaction');
        fd.set('item_id', document.getElementById('deductStockItemId').value);
        fd.set('transaction_type', 'adjustment_down');
        fd.set('quantity', document.getElementById('deductStockQty').value);
        fd.set('notes', document.getElementById('deductStockNotes').value || 'Manual deduction');
        try {
            const res = await fetch(ADMIN_API_BASE + 'inventory_transactions_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeDeductStockModal();
                await fetchUpdatedTable();
                if (selectedItemForStockCard) openStockCard(selectedItemForStockCard.id);
            } else { 
                if (data.errors) {
                    for (let key in data.errors) {
                        let idMap = { 'quantity': 'deductStockQty' };
                        const targetId = idMap[key] || key;
                        const errEl = document.getElementById('err-' + targetId);
                        if (errEl) {
                            errEl.textContent = data.errors[key];
                            errEl.style.display = 'block';
                        }
                    }
                } else {
                    alert('Failed: ' + (data.message || data.error || 'Unknown error')); 
                }
            }
        } catch (err) { alert('Network error.'); }
        finally { btn.disabled = false; btn.textContent = 'Deduct Stock'; }
    }

    async function saveAddStock(e) {
        if (e && e.preventDefault) e.preventDefault();
        if (addStockPendingSubmit) return;
        const qtyInput = document.getElementById('addStockQty');
        const currentStock = parseFloat(document.getElementById('addStockCurrentStock').value || 0);
        const qty = parseFloat(qtyInput?.value || 0);
        const uom = document.getElementById('addStockUom').value || 'pcs';
        const isPcs = (uom || '').toLowerCase() === 'pcs';
        
        if (isNaN(qty) || qty <= 0 || qty > 100000) return;
        
        const isLarge = qty > currentStock * 2;
        if (isLarge) {
            document.getElementById('addStockConfirmQty').textContent = isPcs ? Math.round(qty) : qty.toFixed(2);
            document.getElementById('addStockConfirmTotal').textContent = isPcs ? Math.round(currentStock + qty) : (currentStock + qty).toFixed(2);
            document.getElementById('addStockConfirmModal').style.display = 'flex';
            addStockPendingSubmit = true;
            document.getElementById('addStockConfirmBtn').onclick = async () => {
                closeAddStockConfirmModal();
                addStockPendingSubmit = false;
                await doSaveAddStock();
            };
            return;
        }
        await doSaveAddStock();
    }

    async function doSaveAddStock() {
        // Clear previous errors
        document.querySelectorAll('.field-error').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));

        const btn = document.getElementById('addStockBtn');
        btn.disabled = true; btn.textContent = 'Adding...';
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
            let w = document.getElementById('addStockWidth').value;
            fd.set('width_ft', w || '1'); // Ensure it's never empty for rolls
        }

        try {
            const res = await fetch(ADMIN_API_BASE + 'inventory_transactions_api.php', { method: 'POST', body: fd });
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch (_) {
                console.error('API returned non-JSON:', text.substring(0, 500));
                alert('Server returned an invalid response. See browser console for details.');
                return;
            }
            if (data.success) {
                closeAddStockModal();
                fetchUpdatedTable();
            } else { 
                if (data.errors) {
                    for (let key in data.errors) {
                        const errEl = document.getElementById('err-' + key) || document.getElementById('addStockQtyError');
                        if (errEl) {
                            errEl.textContent = data.errors[key];
                            errEl.style.display = 'block';
                        }
                    }
                } else {
                    alert('Operation Failed: ' + (data.error || data.message || 'Unknown error')); 
                }
            }
        } catch(err) {
            console.error('Add stock error:', err);
            alert('Network communication error. Please try again.');
        }
        finally { btn.disabled = false; btn.textContent = 'Add Stock'; updateAddStockUI(); }
    }

    function editItem(item) {
        openModal('edit', item);
    }

    async function saveItem(e) {
        e.preventDefault();
        
        // Clear previous errors
        document.querySelectorAll('.field-error').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));

        updateEditModalUI();
        if (document.getElementById('saveBtn').disabled) return;
        
        const btn = document.getElementById('saveBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner"></span>Saving...';

        const uomEl = document.getElementById('itemUnit');
        const trackEl = document.getElementById('itemTrackByRoll');
        const uomWasDisabled = uomEl.disabled;
        const trackWasDisabled = trackEl.disabled;

        uomEl.disabled = false;
        trackEl.disabled = false;
        const formData = new FormData(document.getElementById('itemForm'));
        uomEl.disabled = uomWasDisabled;
        trackEl.disabled = trackWasDisabled;
        try {
            const res = await fetch(ADMIN_API_BASE + 'inventory_items_api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                closeModal();
                fetchUpdatedTable();
            } else { 
                if (data.errors) {
                    for (let key in data.errors) {
                        // Map API field names to UI element IDs if different
                        let idMap = { 'name': 'itemName', 'category_id': 'itemCategory', 'unit': 'itemUnit', 'unit_cost': 'itemUnitCost', 'min_stock_level': 'itemMinStock', 'roll_length_ft': 'itemRollLength' };
                        const targetId = idMap[key] || key;
                        const errEl = document.getElementById('err-' + targetId);
                        const inputEl = document.getElementById(targetId);
                        if (errEl) {
                            errEl.textContent = data.errors[key];
                            errEl.style.display = 'block';
                        }
                        if (inputEl) inputEl.classList.add('input-error');
                    }
                } else {
                    alert('Error: ' + data.error); 
                }
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) { 
            alert('Request failed.'); 
            btn.disabled = false;
            btn.innerHTML = originalText;
        } 
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    if (!window._pfInvItemsModalClickBound) {
        window._pfInvItemsModalClickBound = true;
        window.addEventListener('click', function (e) {
            if (e.target.classList.contains('modal')) e.target.style.display = 'none';
        });
    }
    if (!window._pfInvItemsPopstateBound) {
        window._pfInvItemsPopstateBound = true;
        window.addEventListener('popstate', function () { location.reload(); });
    }

    /* Toolbar: turbo-init initTree(.main-content). Table rows are plain onclick; fetchUpdatedTable initTree(tbody) after AJAX. */
    function ensureInvItemsAlpineBoot() {}
    window.printflowInitInvItemsPage = ensureInvItemsAlpineBoot;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            printflowInitInvItemsBindings();
            ensureInvItemsAlpineBoot();
        });
    } else {
        printflowInitInvItemsBindings();
        ensureInvItemsAlpineBoot();
    }
    document.addEventListener('printflow:page-init', function () {
        printflowInitInvItemsBindings();
        ensureInvItemsAlpineBoot();
    });
</script>

</body>
</html>
