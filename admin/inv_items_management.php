<?php
/**
 * Inventory - Items Management (v2)
 * Professional Transaction-Based Inventory UI
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);
$page_title = 'Inventory Items - Admin';

// Get categories for filters/forms
$categories = db_query("SELECT * FROM inv_categories ORDER BY sort_order ASC, name ASC") ?: [];
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
        .toolbar-btn { display: inline-flex; align-items: center; gap: 8px; padding: 0 16px; height: 38px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .toolbar-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .toolbar-btn.active { border-color: #0d9488; background: #f0fdfa; color: #0d9488; }
        .sort-dropdown { position: absolute; top: calc(100% + 8px); right: 0; width: 220px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 8px; z-index: 100; }
        .sort-option { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-radius: 8px; font-size: 13px; color: #4b5563; cursor: pointer; transition: all 0.2s; }
        .sort-option:hover { background: #f3f4f6; color: #111827; }
        .sort-option.selected { background: #f0fdfa; color: #0d9488; font-weight: 600; }
        .filter-panel { position: absolute; top: calc(100% + 8px); right: 0; width: 320px; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); z-index: 100; overflow: hidden; }
        .filter-panel-header { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 700; color: #111827; }
        .filter-section { padding: 16px; border-bottom: 1px solid #f3f4f6; }
        .filter-section:last-child { border-bottom: none; }
        .filter-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .filter-section-label { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.025em; }
        .filter-reset-link { font-size: 11px; font-weight: 600; color: #0d9488; background: none; border: none; cursor: pointer; padding: 0; }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-select, .filter-search-input { width: 100%; height: 40px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; padding: 0 12px; transition: all 0.2s; }
        .filter-select:focus, .filter-search-input:focus { outline: none; border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
        .filter-actions { padding: 16px; background: #f9fafb; display: flex; gap: 12px; }
        .filter-btn-reset { flex: 1; height: 40px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; font-weight: 600; color: #ef4444; cursor: pointer; transition: all 0.2s; }
        .filter-btn-reset:hover { background: #fef2f2; border-color: #fecaca; }
        .filter-badge { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background: #0d9488; color: #fff; font-size: 10px; font-weight: 700; }
        [x-cloak] { display: none !important; }
        .filter-actions { display: flex; gap: 8px; padding: 14px 18px; border-top: 1px solid #f3f4f6; }
        .filter-btn-reset { flex: 1; height: 36px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; }
        .filter-btn-reset:hover { background: #f9fafb; }

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
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Inventory Items List</h3>
                    
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                        <button class="toolbar-btn" onclick="openModal('create')" style="height:38px; border-color:#3b82f6; color:#3b82f6;">Add Item</button>
                        
                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{active: sortOpen}" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <div class="sort-option" :class="{'selected': activeSort === 'newest'}" @click="applySortFilter('newest')">Newest to Oldest <svg x-show="activeSort === 'newest'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <div class="sort-option" :class="{'selected': activeSort === 'oldest'}" @click="applySortFilter('oldest')">Oldest to Newest <svg x-show="activeSort === 'oldest'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <div class="sort-option" :class="{'selected': activeSort === 'az'}" @click="applySortFilter('az')">A → Z <svg x-show="activeSort === 'az'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <div class="sort-option" :class="{'selected': activeSort === 'za'}" @click="applySortFilter('za')">Z → A <svg x-show="activeSort === 'za'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
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
                            <tr><td colspan="7" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">Scanning inventory...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="itemsPagination"></div>
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
        </form>
    </div>
</div>

<script>
    let currentItems = [];
    let usageChart = null;
    let selectedItemForStockCard = null;
    let currentPage = 1;
    const itemsPerPage = 10;
    let currentSort = 'name';
    let currentDir = 'ASC';
    let activeSort = 'az'; // default to A-Z
    let searchDebounceTimer = null;

    function filterPanel() {
        return {
            sortOpen: false,
            filterOpen: false,
            activeSort: activeSort,
            get hasActiveFilters() {
                return document.getElementById('fp_category')?.value ||
                       document.getElementById('fp_search')?.value;
            }
        };
    }

    function applySortFilter(sortKey) {
        activeSort = sortKey;
        if (sortKey === 'newest') { currentSort = 'id'; currentDir = 'DESC'; }
        else if (sortKey === 'oldest') { currentSort = 'id'; currentDir = 'ASC'; }
        else if (sortKey === 'az') { currentSort = 'name'; currentDir = 'ASC'; }
        else if (sortKey === 'za') { currentSort = 'name'; currentDir = 'DESC'; }
        
        currentPage = 1;
        loadItems();
        
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
        loadItems();
    }

    function applyFilters(reset = false) {
        if (reset) {
            ['fp_category', 'fp_search'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            activeSort = 'az';
            currentSort = 'name';
            currentDir = 'ASC';
        }
        currentPage = 1;
        loadItems();
    }

    async function loadItems() {
        const catId = document.getElementById('fp_category')?.value || '';
        const search = document.getElementById('fp_search')?.value || '';
        
        try {
            const res = await fetch(`inventory_items_api.php?action=get_items&category_id=${catId}&search=${encodeURIComponent(search)}&sort=${currentSort}&dir=${currentDir}`);
            const data = await res.json();
            
            if (data.success) {
                currentItems = data.data;
                renderItems(currentItems);
                updateBadgeCount();
            }
        } catch (e) {
            console.error(e);
            document.getElementById('itemsTableBody').innerHTML = '<tr><td colspan="7" style="color:red; text-align:center;">Network error.</td></tr>';
        }
    }

    function updateBadgeCount() {
        const cat = document.getElementById('fp_category')?.value;
        const s = document.getElementById('fp_search')?.value;
        let count = 0;
        if (cat) count++;
        if (s) count++;
        const cont = document.getElementById('filterBadgeContainer');
        if (cont) cont.innerHTML = count > 0 ? `<span class="filter-badge">${count}</span>` : '';
    }

    function renderItems(items) {
        const tbody = document.getElementById('itemsTableBody');
        const totalItems = items.length;

        if (totalItems === 0) {
            const showingCountEl = document.getElementById('showingCount');
            if (showingCountEl) showingCountEl.parentNode.innerHTML = `Showing <strong style="color:#1f2937;" id="showingCount">0</strong> items`;
            tbody.innerHTML = '<tr id="emptyItemsRow"><td colspan="7" class="py-8 text-center text-gray-500">No inventory items matching the filter.</td></tr>';
            document.getElementById('itemsPagination').innerHTML = '';
            return;
        }

        // Sort by severity: Critical (SOH=0) first, then Low Stock, then Normal
        const sortedItems = [...items].sort((a, b) => {
            const getSeverity = (it) => {
                const s = parseFloat(it.current_stock);
                const r = parseFloat(it.reorder_level);
                if (s <= 0) return 0;
                if (s <= r) return 1;
                return 2;
            };
            return getSeverity(a) - getSeverity(b);
        });

        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        const startIdx = (currentPage - 1) * itemsPerPage;
        const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
        const pageItems = sortedItems.slice(startIdx, endIdx);

        // Update showing text now that startIdx/endIdx are defined
        const showingCountEl = document.getElementById('showingCount');
        if (showingCountEl) {
            showingCountEl.parentNode.innerHTML = `Showing <strong style="color:#1f2937;" id="showingCount">${startIdx + 1}–${endIdx}</strong> of ${totalItems} items`;
        }

        let html = '';
        pageItems.forEach(item => {
            const stock = parseFloat(item.current_stock);
            const minStock = parseFloat(item.reorder_level);
            const unitCost = parseFloat(item.unit_cost || 0);
            const isOut = stock <= 0;
            const isLow = !isOut && stock <= minStock;

            let stockColor = '#1f2937';
            if (isOut) stockColor = '#991b1b';
            else if (isLow) stockColor = '#d97706';

            let trackBadge = item.track_by_roll == 1
                ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#eef2ff;color:#4338ca;">Roll-Based</span>'
                : '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f3f4f6;color:#4b5563;">Standard</span>';

            let costDisplay = unitCost > 0
                ? `<span class="font-semibold">₱${unitCost.toLocaleString(undefined, {minimumFractionDigits:2})}</span>`
                : '<span style="color:#d97706;font-weight:600;">₱0.00</span>';

            let statusBadge = '';
            if (isLow && !isOut) statusBadge = '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:8px;">Low Stock</span>';

            // Show inactive badge
            const inactiveBadge = item.status === 'INACTIVE' ? '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#f3f4f6;color:#6b7280;margin-left:6px;">Inactive</span>' : '';

            html += `<tr class="${(isOut || isLow) ? 'low-stock-row' : ''}" style="cursor:pointer;" onclick="openStockCard(${item.id})">
                <td style="font-weight:500;text-transform:capitalize;">${escapeHtml(item.name)}${statusBadge}${inactiveBadge}</td>
                <td>${escapeHtml(item.category_name || 'Uncategorized')}</td>
                <td>${trackBadge}</td>
                <td>${costDisplay}</td>
                <td>
                    <span class="stock-val" style="color:${stockColor};">${stock.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </td>
                <td style="color:#6b7280;font-size:12px;">${escapeHtml(item.unit_of_measure || '')}</td>
                <td style="text-align:right;white-space:nowrap;">
                    <button class="btn-action teal" onclick="event.stopPropagation(); openAddStockModalById(${item.id})">+ Stock</button>
                    <button class="btn-action blue" onclick="event.stopPropagation(); editItemById(${item.id})">Edit</button>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
        renderItemsPagination(totalPages);
    }

    function renderItemsPagination(totalPages) {
        const container = document.getElementById('itemsPagination');
        if (totalPages <= 1) { container.innerHTML = ''; return; }

        let html = '<div style="display:flex; align-items:center; justify-content:center; gap:4px; margin-top:20px; padding-top:16px; border-top:1px solid #f3f4f6;">';

        // Previous button
        if (currentPage > 1) {
            html += `<a href="#" onclick="event.preventDefault(); goToItemsPage(${currentPage - 1})" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid #e5e7eb;color:#374151;text-decoration:none;font-size:13px;transition:all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>`;
        }

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            const isActive = (i === currentPage);
            const bg = isActive ? 'background:#1f2937;color:white;border-color:#1f2937;' : 'background:white;color:#374151;border:1px solid #e5e7eb;';
            const fw = isActive ? '600' : '500';
            html += `<a href="#" onclick="event.preventDefault(); goToItemsPage(${i})" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:${fw};transition:all 0.2s;${bg}"`;
            if (!isActive) html += ` onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'"`;
            html += `>${i}</a>`;
        }

        // Next button
        if (currentPage < totalPages) {
            html += `<a href="#" onclick="event.preventDefault(); goToItemsPage(${currentPage + 1})" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid #e5e7eb;color:#374151;text-decoration:none;font-size:13px;transition:all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>`;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    function goToItemsPage(page) {
        currentPage = page;
        renderItems(currentItems);
    }

    function handleSort(col) {
        if (currentSort === col) {
            currentDir = currentDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            currentSort = col;
            currentDir = 'ASC';
        }
        currentPage = 1;
        loadItems();
    }

    function updateSortIcons() {
        const cols = ['name', 'category_name', 'track_by_roll', 'unit_cost'];
        cols.forEach(c => {
            const el = document.getElementById('sort-' + c);
            if (!el) return;
            if (currentSort === c) {
                el.innerHTML = currentDir === 'ASC' ? ' ▲' : ' ▼';
                el.style.color = '#6366f1';
            } else {
                el.innerHTML = '';
            }
        });
    }

    async function openStockCard(itemId) {
        const item = currentItems.find(i => i.id == itemId);
        if (!item) return;

        selectedItemForStockCard = item;
        document.getElementById('scName').textContent = item.name;
        document.getElementById('scStock').textContent = parseFloat(item.current_stock).toLocaleString();
        document.getElementById('scUnit').textContent = item.unit_of_measure.toUpperCase();
        document.getElementById('scMinStock').textContent = parseFloat(item.reorder_level).toLocaleString();
        document.getElementById('scLedgerLink').href = `inv_transactions_ledger?item_id=${item.id}`;

        loadRecentLedger(item.id);

        if (item.track_by_roll == 1) {
            document.getElementById('rollKpi').style.display = 'block';
            document.getElementById('rollDetailsSection').style.display = 'block';
            loadRollsForItem(item.id);
        } else {
            document.getElementById('rollKpi').style.display = 'none';
            document.getElementById('rollDetailsSection').style.display = 'none';
        }

        document.getElementById('stockCardModal').style.display = 'flex';
        renderMiniChart(item.id);
    }

    async function loadRecentLedger(itemId) {
        const body = document.getElementById('scLedgerBody');
        body.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#9ca3af;">Loading history...</td></tr>';
        
        try {
            const res = await fetch(`inventory_transactions_api.php?action=get_transactions&item_id=${itemId}`);
            const data = await res.json();
            
            if (data.success && data.data.length > 0) {
                let html = '';
                // Only show last 5
                data.data.slice(0, 5).forEach(t => {
                    const isOut = t.direction === 'OUT';
                    const color = isOut ? '#ef4444' : '#10b981';
                    const prefix = isOut ? '-' : '+';
                    const typeLabel = t.ref_type ? t.ref_type.replace(/_/g, ' ').toUpperCase() : 'ADJUSTMENT';
                    
                    html += `<tr>
                        <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6; color:#6b7280; white-space:nowrap;">${new Date(t.transaction_date).toLocaleDateString()}</td>
                        <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6;">
                            <span style="font-weight:700; color:#4b5563; font-size:10px;">${typeLabel}</span>
                        </td>
                        <td style="padding:10px 12px; border-bottom:1px solid #f3f4f6; text-align:right; font-weight:700; color:${color};">
                            ${prefix}${parseFloat(t.quantity).toLocaleString()}
                        </td>
                    </tr>`;
                });
                body.innerHTML = html;
            } else {
                body.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#9ca3af;">No recent activities found.</td></tr>';
            }
        } catch (err) {
            body.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#ef4444;">Failed to load history.</td></tr>';
        }
    }

    async function loadRollsForItem(itemId) {
        try {
            const res = await fetch(`inventory_rolls_api.php?action=list_rolls&item_id=${itemId}`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('scRoll').textContent = data.data.length;
                const tbody = document.getElementById('scRollBody');
                tbody.innerHTML = '';
                data.data.forEach(roll => {
                    const pct = (roll.remaining_length_ft / roll.total_length_ft) * 100;
                    tbody.innerHTML += `
                        <tr>
                            <td><span style="font-family:monospace; font-weight:700;">${roll.roll_code || '#'+roll.id}</span></td>
                            <td>${parseFloat(roll.remaining_length_ft).toFixed(2)} / ${parseFloat(roll.total_length_ft).toFixed(0)} ft</td>
                            <td>
                                <div style="width:100%; height:6px; background:#f3f4f6; border-radius:10px; overflow:hidden;">
                                    <div style="width:${pct}%; height:100%; background:${pct < 20 ? '#ef4444' : '#10b981'};"></div>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
        } catch (e) {}
    }

    function renderMiniChart(itemId) {
        if (usageChart) usageChart.destroy();
        const ctx = document.getElementById('usageChart').getContext('2d');
        usageChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Movement',
                    data: [12, 19, 3, 5, 2, 3, 7],
                    borderColor: '#6366f1',
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(99, 102, 241, 0.05)',
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { display:false }, y: { display:false } }
            }
        });
    }

    function closeStockCard() {
        document.getElementById('stockCardModal').style.display = 'none';
    }

    function editFromStockCard() {
        if (selectedItemForStockCard) {
            editItem(selectedItemForStockCard);
            closeStockCard();
        }
    }

    function handleUomChange() {
        const uom = document.getElementById('itemUnit').value;
        const section = document.getElementById('rollSettingsSection');
        const rollInput = document.getElementById('itemRollLength');
        if (uom === 'ft') {
            section.style.maxHeight = '200px';
            section.style.opacity = '1';
            rollInput.required = true;
        } else {
            section.style.maxHeight = '0';
            section.style.opacity = '0';
            rollInput.required = false;
            rollInput.value = '';
        }
    }

    function openModal(mode = 'create', item = null) {
        document.getElementById('itemModal').style.display = 'flex';
        const form = document.getElementById('itemForm');
        form.reset();
        // Reset roll section visibility
        document.getElementById('rollSettingsSection').style.maxHeight = '0';
        document.getElementById('rollSettingsSection').style.opacity = '0';
        document.getElementById('itemRollLength').required = false;
        
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
            qtyInput.focus();
            qtyInput.select(); 
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
        
        // Call the inventory transactions API to receive stock
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
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch(e) { 
                console.error("Non-JSON API response:", text);
                alert('Server returned an unexpected response. Please refresh the page and try again (Check Console for details).');
                return;
            }
            
            if (data.success) {
                closeAddStockModal();
                loadItems();
            } else { alert('Operation Failed: ' + data.error); }
        } catch(err) { 
            console.error(err);
            alert('Network communication error. Please try again.'); 
        }
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
                loadItems();
            } else { alert('Error: ' + data.error); }
        } catch (err) { alert('Request failed.'); } 
        finally { btn.disabled = false; btn.textContent = 'Save Changes'; }
    }

    function suggestSku(name) {
        // SKU feature removed as per user request
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    document.addEventListener('DOMContentLoaded', loadItems);
    
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('fp_search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => { currentPage = 1; loadItems(); }, 500);
            });
        }
        const catSelect = document.getElementById('fp_category');
        if (catSelect) {
            catSelect.addEventListener('change', () => { currentPage = 1; loadItems(); });
        }
    });

    window.addEventListener('click', e => {
        if (e.target.classList.contains('modal')) e.target.style.display = 'none';
    });
</script>
</body>
</html>
