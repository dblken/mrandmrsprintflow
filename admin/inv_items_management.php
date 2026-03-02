<?php
/**
 * Inventory - Items Management (v2)
 * Professional Transaction-Based Inventory UI
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');
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
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(255, 255, 255, 0.3);
        }

        .header-actions { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; padding: 24px; background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 16px; border: 1px solid var(--glass-border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.025em; }
        .filter-group input, .filter-group select { padding: 10px 16px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; transition: all 0.2s; background: #fff; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        
        .inv-table { width: 100%; border-collapse: separate; border-spacing: 0; background: transparent; }
        .inv-table th { background: #f9fafb; padding: 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #4b5563; text-align: left; border-bottom: 2px solid #e5e7eb; letter-spacing: 0.05em; }
        .inv-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #1f2937; transition: all 0.2s; }
        .inv-table tr { cursor: pointer; transition: all 0.2s; }
        .inv-table tr:hover td { background: #f5f7ff; }
        .inv-table tr:last-child td:first-child { border-bottom-left-radius: 12px; }
        .inv-table tr:last-child td:last-child { border-bottom-right-radius: 12px; }
        
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em; }
        .badge-green { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-red { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-gray { background: #f9fafb; color: #4b5563; border: 1px solid #e5e7eb; }
        .badge-indigo { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
        
        .stock-val { font-weight: 800; font-variant-numeric: tabular-nums; font-size: 16px; }
        .btn-action { padding: 8px 14px; font-size: 12px; font-weight: 700; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.025em; }
        .btn-edit { color: #4f46e5; background: #eef2ff; border: 1px solid #e0e7ff; }
        .btn-edit:hover { background: #6366f1; color: #fff; transform: translateY(-1px); }
        
        /* Modals */
        .modal { display: none; position: fixed; inset: 0; background: rgba(17, 24, 39, 0.7); z-index: 100000; align-items: flex-start; justify-content: center; padding: 40px 20px; overflow-y: auto; backdrop-filter: blur(8px); animation: fadeIn 0.3s ease; }
        .modal-content { background: #fff; border-radius: 20px; width: 100%; max-width: 550px; padding: 32px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid var(--border-color); position: relative; z-index: 100001; pointer-events: auto; transform: translateZ(0); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 20px; font-weight: 800; color: #111827; letter-spacing: -0.01em; }
        .close-btn { background: #f3f4f6; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; padding: 6px; line-height: 1; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .close-btn:hover { background: #fee2e2; color: #ef4444; }
        
        .modal input, .modal select, .modal textarea { pointer-events: auto !important; position: relative; z-index: 100002; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 32px; }
        .form-group.full { grid-column: span 2; }
        
        /* Ensure select elements in modal have consistent height and style */
        .modal select, .modal input:not([type="checkbox"]) { height: 44px; width: 100% !important; display: block; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0 14px; font-size: 14px; background: #fff; color: #1f2937; }
        .modal label { margin-bottom: 8px; display: block; font-weight: 700; color: #374151; font-size: 13px; }

        /* Premium Stock Card Modal */
        #stockCardModal .modal-content { max-width: 800px; }
        .stock-card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; }
        .kpi-mini-card { padding: 16px; border-radius: 16px; background: #f9fafb; border: 1px solid #e5e7eb; }
        .kpi-mini-card .label { font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
        .kpi-mini-card .value { font-size: 20px; font-weight: 800; color: #1f2937; }
        
        .roll-list-table { width: 100%; margin-top: 24px; font-size: 13px; }
        .roll-list-table th { text-align: left; padding: 8px; font-weight: 700; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        .roll-list-table td { padding: 12px 8px; border-bottom: 1px solid #f3f4f6; }

        .chart-container { height: 200px; margin-top: 24px; padding: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; }

        @keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
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
            <!-- Filters & Actions -->
            <div class="header-actions">
                <div style="display: flex; gap: 20px; flex-wrap: wrap; flex: 1;">
                    <div class="filter-group">
                        <label>Quick Search</label>
                        <input type="text" id="filterSearch" placeholder="Name, SKU, or Keyword..." style="width: 300px;">
                    </div>
                    <div class="filter-group">
                        <label>Category</label>
                        <select id="filterCategory" style="min-width: 180px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <button onclick="openModal('create')" class="btn-primary" style="height: 48px; border-radius: 12px; padding: 0 24px; display:inline-flex; align-items:center; gap:8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add New Item
                    </button>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card" style="padding: 0; overflow: hidden; border-radius: 16px; border: 1px solid #e5e7eb;">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
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
                        <tr><td colspan="9" style="text-align:center; padding: 60px; color:#9ca3af; font-size: 15px;">Scanning inventory...</td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Add Stock Modal -->
<div id="addStockModal" class="modal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Add Stock Entry</h3>
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
                <div class="filter-group" id="addStockRollGroup" style="display:none;">
                    <label for="addStockRollCode">Roll Code (optional)</label>
                    <input type="text" id="addStockRollCode" placeholder="e.g. ROLL-001">
                    <p style="font-size:10px;color:#9ca3af;margin-top:4px;">Leaving this empty will auto-generate a roll code.</p>
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
                <p style="font-size:13px; color:#6b7280; font-family:monospace;" id="scSku">#SKU-000</p>
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
            <h3 class="modal-title" id="modalTitle">Settings</h3>
            <button class="close-btn" onclick="closeModal()">×</button>
        </div>
        <form id="itemForm" onsubmit="saveItem(event)">
            <input type="hidden" id="itemId" name="id">
            <input type="hidden" id="actionType" name="action" value="create_item">
            
            <div class="form-grid">
                <div class="form-group full">
                    <label for="itemName">Item Name *</label>
                    <input type="text" id="itemName" name="name" required placeholder="e.g. 3FT Tarpaulin Gloss" oninput="suggestSku(this.value)">
                </div>
                
                <div class="filter-group">
                    <label for="itemSku">SKU / Code</label>
                    <input type="text" id="itemSku" name="sku" placeholder="AUTO-GEN">
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
                    <label for="itemUnit">UOM *</label>
                    <select id="itemUnit" name="unit" required>
                        <option value="pcs">Pieces (pcs)</option>
                        <option value="ft">Feet (ft)</option>
                        <option value="btl">Bottles (btl)</option>
                        <option value="set">Sets (set)</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="itemUnitCost">Unit Cost (₱) *</label>
                    <input type="number" step="0.01" id="itemUnitCost" name="unit_cost" value="0.00" required>
                </div>
                
                <div class="filter-group">
                    <label for="itemTrackByRoll">Tracking Mode</label>
                    <select id="itemTrackByRoll" name="track_by_roll">
                        <option value="0">Standard (Ledger)</option>
                        <option value="1">Roll-Based (Individual)</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="itemRollLength">Std Roll Length (ft)</label>
                    <input type="number" step="0.01" id="itemRollLength" name="roll_length_ft" placeholder="e.g. 164.00">
                </div>
                
                <div class="filter-group">
                    <label for="itemMinStock">Reorder Level</label>
                    <input type="number" step="0.01" id="itemMinStock" name="min_stock_level" value="0.00" required>
                </div>
                
                <div class="filter-group" id="startingStockGroup">
                    <label for="itemStartingStock">Initial Stock</label>
                    <input type="number" step="0.01" id="itemStartingStock" name="starting_stock" value="0.00">
                </div>

                <div class="filter-group">
                    <label for="itemStatus">Status</label>
                    <select id="itemStatus" name="status">
                        <option value="ACTIVE">Active</option>
                        <option value="INACTIVE">Inactive</option>
                    </select>
                </div>
                
                <div class="form-group full" style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                    <input type="checkbox" id="itemAllowNegative" name="allow_negative_stock" value="1">
                    <label for="itemAllowNegative" style="cursor:pointer; text-transform:none; font-weight: 500; color: #4b5563; margin-bottom:0;">Allow Negative Stock</label>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
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

    async function loadItems() {
        const catId = document.getElementById('filterCategory').value;
        const search = document.getElementById('filterSearch').value;
        
        try {
            const res = await fetch(`inventory_items_api.php?action=get_items&category_id=${catId}&search=${encodeURIComponent(search)}`);
            const data = await res.json();
            
            if (data.success) {
                currentItems = data.data;
                renderItems(currentItems);
            }
        } catch (e) {
            console.error(e);
            document.getElementById('itemsTableBody').innerHTML = '<tr><td colspan="7" style="color:red; text-align:center;">Network error.</td></tr>';
        }
    }

    function renderItems(items) {
        const tbody = document.getElementById('itemsTableBody');
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 60px; color:#6b7280; font-size: 15px;">No inventory matching the filter.</td></tr>';
            return;
        }

        let html = '';
        items.forEach(item => {
            const stock = parseFloat(item.current_stock);
            const minStock = parseFloat(item.reorder_level);
            const unitCost = parseFloat(item.unit_cost || 0);
            const isOut = stock <= 0;
            const isLow = !isOut && stock <= minStock;

            let stockColor = 'color: #1f2937;';
            if (isOut) stockColor = 'color: #991b1b;';
            else if (isLow) stockColor = 'color: #d97706;';

            let rowStyle = '';
            if (isOut) rowStyle = 'background: #fff5f5;';
            else if (isLow) rowStyle = 'background: #fffbeb;';

            let trackBadge = item.track_by_roll == 1 ? '<span class="badge badge-indigo">Roll-Based</span>' : '<span class="badge badge-gray">Standard</span>';

            let costDisplay = unitCost > 0
                ? `₱${unitCost.toFixed(2)}`
                : `<span style="display:inline-flex;align-items:center;gap:4px;color:#d97706;font-weight:700;"><svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>₱0</span>`;

            let statusAlert = '';
            if (isOut) statusAlert = '<span style="font-size:10px;font-weight:700;color:#991b1b;background:#fee2e2;border-radius:4px;padding:2px 6px;margin-left:6px;">OUT</span>';
            else if (isLow) statusAlert = '<span style="font-size:10px;font-weight:700;color:#d97706;background:#fef3c7;border-radius:4px;padding:2px 6px;margin-left:6px;">LOW</span>';

            html += `<tr class="row-selectable" onclick="openStockCard(${item.id})" style="${rowStyle}">
                <td style="color:#6b7280; font-family:monospace; font-size:12px;">${item.sku || '-'}</td>
                <td style="font-weight:700; color:#111827; font-size: 15px;">${escapeHtml(item.name)}${statusAlert}</td>
                <td><span class="badge badge-gray">${escapeHtml(item.category_name || 'Uncategorized')}</span></td>
                <td>${trackBadge}</td>
                <td>${costDisplay}</td>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="stock-val" style="${stockColor}">${stock.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                        ${isLow || isOut ? '<svg width="14" height="14" fill="currentColor" color="' + (isOut ? '#991b1b' : '#d97706') + '" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>' : ''}
                    </div>
                </td>
                <td><span style="font-weight:600; font-size:12px; color:#4b5563; text-transform:uppercase;">${item.unit_of_measure}</span></td>
                <td style="text-align:right;">
                    <div style="display:flex; gap:6px; justify-content: flex-end;" onclick="event.stopPropagation()">
                        <button class="btn-action" style="color:#059669;background:#ecfdf5;border:1px solid #a7f3d0;" onclick='event.stopPropagation(); openAddStockModal(${JSON.stringify(item)})'>+ Stock</button>
                        <button class="btn-action btn-edit" onclick='event.stopPropagation(); editItem(${JSON.stringify(item)})'>Edit</button>
                    </div>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    async function openStockCard(itemId) {
        const item = currentItems.find(i => i.id == itemId);
        if (!item) return;

        selectedItemForStockCard = item;
        document.getElementById('scName').textContent = item.name;
        document.getElementById('scSku').textContent = '#' + (item.sku || 'SKU-NONE');
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

    function openModal(mode = 'create', item = null) {
        document.getElementById('itemModal').style.display = 'flex';
        const form = document.getElementById('itemForm');
        form.reset();
        
        if (mode === 'create') {
            document.getElementById('modalTitle').textContent = 'Add New Material';
            document.getElementById('actionType').value = 'create_item';
            document.getElementById('itemId').value = '';
            document.getElementById('itemUnitCost').value = '0.00';
            document.getElementById('startingStockGroup').style.display = 'block';
            document.getElementById('itemStatus').value = 'ACTIVE';
        } else {
            document.getElementById('modalTitle').textContent = 'Edit Material Settings';
            document.getElementById('actionType').value = 'update_item';
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemName').value = item.name;
            document.getElementById('itemSku').value = item.sku || '';
            document.getElementById('itemCategory').value = item.category_id || '';
            document.getElementById('itemUnit').value = item.unit_of_measure;
            document.getElementById('itemUnitCost').value = item.unit_cost || '0.00';
            document.getElementById('itemTrackByRoll').value = item.track_by_roll;
            document.getElementById('itemRollLength').value = item.default_roll_length_ft || '';
            document.getElementById('itemMinStock').value = item.reorder_level;
            document.getElementById('itemAllowNegative').checked = !!parseInt(item.allow_negative_stock || 0);
            document.getElementById('itemStatus').value = item.status;
            document.getElementById('startingStockGroup').style.display = 'none';
        }
    }

    function closeModal() {
        document.getElementById('itemModal').style.display = 'none';
    }

    function openAddStockModal(item) {
        document.getElementById('addStockItemName').textContent = item.name;
        document.getElementById('addStockItemId').value = item.id;
        document.getElementById('addStockIsRoll').value = item.track_by_roll;
        document.getElementById('addStockUom').value = item.unit_of_measure || 'pcs';
        document.getElementById('addStockQty').value = '';
        document.getElementById('addStockRollCode').value = '';
        document.getElementById('addStockNotes').value = '';
        // Show roll code field only for roll-based items
        document.getElementById('addStockRollGroup').style.display = item.track_by_roll == 1 ? 'block' : 'none';
        document.getElementById('addStockModal').style.display = 'flex';
        // Force focus after display
        setTimeout(() => document.getElementById('addStockQty').focus(), 100);
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
        fd.append('action', 'record_transaction');
        fd.append('item_id', itemId);
        fd.append('transaction_type', 'purchase');
        fd.append('quantity', qty);
        fd.append('uom', uom);
        fd.append('notes', notes || 'Manual stock entry');
        if (isRoll && rollCode) fd.append('roll_code', rollCode);

        try {
            const res = await fetch('inventory_transactions_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeAddStockModal();
                loadItems();
            } else { alert('Error: ' + data.error); }
        } catch(err) { alert('Request failed.'); }
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
        const skuInput = document.getElementById('itemSku');
        if (skuInput.value.trim() === '' || skuInput.getAttribute('data-auto') === 'true') {
            if (name.length > 2) {
                const prefix = name.substring(0, 3).toUpperCase().replace(/[^A-Z0-9]/g, '');
                const random = Math.floor(1000 + Math.random() * 9000);
                skuInput.value = prefix + '-' + random;
                skuInput.setAttribute('data-auto', 'true');
            } else {
                skuInput.value = '';
            }
        }
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    document.addEventListener('DOMContentLoaded', loadItems);
    document.getElementById('filterSearch').addEventListener('input', () => {
        clearTimeout(window.searchT);
        window.searchT = setTimeout(loadItems, 300);
    });
    document.getElementById('filterCategory').addEventListener('change', loadItems);

    window.addEventListener('click', e => {
        if (e.target.classList.contains('modal')) e.target.style.display = 'none';
    });
</script>
</body>
</html>
