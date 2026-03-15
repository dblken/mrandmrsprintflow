<?php
/**
 * New Inventory - Transactions Ledger
 * Professional Transaction-Based Inventory UI
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryManager.php';

require_role('Admin');
$page_title = 'Inventory Ledger - Admin';

// Get items for filters/forms
$items = db_query("SELECT id, name, unit_of_measure as unit FROM inv_items ORDER BY name ASC") ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.4);
            --in-color: #059669;
            --out-color: #dc2626;
        }

        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 700; text-transform: capitalize; color: #6b7280; letter-spacing: 0.025em; }
        .filter-group input, .filter-group select { height: 36px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; padding: 0 10px; color: #374151; width: auto; background: #fff; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        
        .inv-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .inv-table th { padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        .inv-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; color: #374151; }
        .inv-table tbody tr { cursor: pointer; transition: background 0.1s; }
        .inv-table tbody tr:hover td { background: #f9fafb; }
        .inv-table tbody tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid transparent; }
        .badge-in { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .badge-out { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .badge-neutral { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
        
        .qty-val { font-weight: 700; font-variant-numeric: tabular-nums; font-size: 15px; }
        .qty-val.positive { color: #059669; }
        .qty-val.negative { color: #dc2626; }
        
        /* Modals */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; padding: 16px; overflow-y: auto; animation: fadeIn 0.3s ease; }
        .modal-content { background: #fff; border-radius: 12px; width: 95%; max-width: 640px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #e5e7eb; position: relative; z-index: 1001; pointer-events: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 18px; font-weight: 700; color: #111827; }
        .close-btn { background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; padding: 4px; line-height: 1; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .close-btn:hover { color: #374151; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .form-group.full { grid-column: span 2; }
        
        /* Ensure select elements in modal have consistent height and style */
        .modal select, .modal input { height: 40px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0 12px; font-size: 14px; background: #fff; color: #1f2937; }
        .modal label { margin-bottom: 6px; display: block; font-weight: 600; color: #374151; font-size: 13px; }

        .btn-action {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 5px 12px; min-width: 80px; border: 1px solid transparent;
            background: transparent; border-radius: 6px; font-size: 12px;
            font-weight: 500; transition: all 0.2s; cursor: pointer;
            text-decoration: none; white-space: nowrap; height: 32px;
        }
        .btn-action.blue { color: #3b82f6; border-color: #3b82f6; }
        .btn-action.blue:hover { background: #3b82f6; color: white; }
        .btn-action.teal { color: #0d9488; border-color: #0d9488; }
        .btn-action.teal:hover { background: #0d9488; color: white; }
        .btn-action.red { color: #dc2626; border-color: #dc2626; }
        .btn-action.red:hover { background: #dc2626; color: white; }

        .btn-entry { height: 36px; display: inline-flex; align-items: center; gap: 8px; padding: 0 16px; border-radius: 8px; font-weight: 600; font-size: 13px; transition: all 0.2s; border: 1px solid transparent; cursor: pointer; }
        .btn-in { border-color: #10b981; color: #10b981; background: transparent; }
        .btn-in:hover { background: #10b981; color: #fff; }
        .btn-out { border-color: #ef4444; color: #ef4444; background: transparent; }
        .btn-out:hover { background: #ef4444; color: #fff; }

        @keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }

        /* Standardized Toolbar Styles */
        .toolbar-btn { display: inline-flex; align-items: center; gap: 8px; padding: 0 16px; height: 38px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .toolbar-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .toolbar-btn.active { border-color: #0d9488; background: #f0fdfa; color: #0d9488; }
        .sort-dropdown { position: absolute; top: calc(100% + 8px); right: 0; width: 220px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 8px; z-index: 100002; }
        .sort-option { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-radius: 8px; font-size: 13px; color: #4b5563; cursor: pointer; transition: all 0.2s; }
        .sort-option:hover { background: #f3f4f6; color: #111827; }
        .sort-option.selected { background: #f0fdfa; color: #0d9488; font-weight: 600; }
        .filter-panel { position: absolute; top: calc(100% + 8px); right: 0; width: 340px; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); z-index: 100002; overflow: hidden; }
        .filter-panel-header { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 700; color: #111827; }
        .filter-section { padding: 16px; border-bottom: 1px solid #f3f4f6; }
        .filter-section:last-child { border-bottom: none; }
        .filter-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .filter-section-label { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.025em; }
        .filter-reset-link { font-size: 11px; font-weight: 600; color: #0d9488; background: none; border: none; cursor: pointer; padding: 0; }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-select, .filter-search-input, .filter-input { width: 100%; height: 40px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; padding: 0 12px; transition: all 0.2s; }
        .filter-select:focus, .filter-search-input:focus, .filter-input:focus { outline: none; border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
        .filter-date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 700; }
        .filter-actions { display: flex; gap: 8px; padding: 14px 18px; border-top: 1px solid #f3f4f6; }
        .filter-btn-reset { flex: 1; height: 36px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; transition: all 0.2s; }
        .filter-btn-reset:hover { background: #f9fafb; }
        .filter-badge { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background: #0d9488; color: white; font-size: 10px; font-weight: 700; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title" style="margin-bottom: 4px;">Stock Movement Ledger</h1>
                <p style="font-size: 14px; color: #6b7280;">Audit trail of every material transaction in the system.</p>
            </div>
            <a href="inv_items_management" class="btn-secondary" style="display:inline-flex; align-items:center; gap:10px; padding: 12px 20px; border-radius: 12px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                Manage Items
            </a>
        </header>

        <main>
            <!-- Ledger Card -->
            <div class="card">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;" x-data="filterPanel()">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Ledger List</h3>
                    
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                        <button onclick="openModal('purchase')" class="toolbar-btn" style="height:38px; border-color:#059669; color:#059669; background:#ecfdf5; gap:6px;">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Receive IN
                        </button>
                        <button onclick="openModal('issue')" class="toolbar-btn" style="height:38px; border-color:#dc2626; color:#dc2626; background:#fef2f2; gap:6px;">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Issue OUT
                        </button>

                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button class="toolbar-btn" :class="{active: sortOpen}" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <div class="sort-option" :class="{'selected': activeSort === 'newest'}" @click="applySortFilter('newest')">Newest to Oldest <svg x-show="activeSort === 'newest'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <div class="sort-option" :class="{'selected': activeSort === 'oldest'}" @click="applySortFilter('oldest')">Oldest to Newest <svg x-show="activeSort === 'oldest'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <div class="sort-option" :class="{'selected': activeSort === 'az'}" @click="applySortFilter('az')">Material A → Z <svg x-show="activeSort === 'az'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <div class="sort-option" :class="{'selected': activeSort === 'za'}" @click="applySortFilter('za')">Material Z → A <svg x-show="activeSort === 'za'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
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
                                
                                <!-- Date Range -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Date range</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['start_date','end_date'])">Reset</button>
                                    </div>
                                    <div class="filter-date-row">
                                        <div><div class="filter-date-label">From:</div><input type="date" id="fp_start_date" class="filter-input" value="<?php echo date('Y-m-01'); ?>"></div>
                                        <div><div class="filter-date-label">To:</div><input type="date" id="fp_end_date" class="filter-input" value="<?php echo date('Y-m-t'); ?>"></div>
                                    </div>
                                </div>

                                <!-- Material -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Material</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['item_id'])">Reset</button>
                                    </div>
                                    <select id="fp_item_id" class="filter-select">
                                        <option value="">All Materials</option>
                                        <?php foreach ($items as $item): ?>
                                            <option value="<?php echo $item['id']; ?>" <?php echo (isset($_GET['item_id']) && $_GET['item_id'] == $item['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Trans. Type -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Trans. Type</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['type'])">Reset</button>
                                    </div>
                                    <select id="fp_type" class="filter-select">
                                        <option value="">All Types</option>
                                        <option value="opening_balance">Opening Balance</option>
                                        <option value="purchase">Purchase (IN)</option>
                                        <option value="issue">Issue (OUT)</option>
                                        <option value="adjustment_up">Adj. Up (IN)</option>
                                        <option value="adjustment_down">Adj. Down (OUT)</option>
                                        <option value="return">Return (IN)</option>
                                    </select>
                                </div>

                                <div class="filter-actions">
                                    <button class="filter-btn-reset" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Date</th>
                                <th>Item Name</th>
                                <th>Transaction Type</th>
                                <th style="text-align:right;">Quantity</th>
                                <th>Notes</th>
                                <th>Admin</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerTableBody">
                            <tr><td colspan="8" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">Retrieving audit logs...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="ledgerPagination"></div>
            </div>
        </main>
    </div>
</div>

<!-- Transaction View Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Transaction Details</h3>
                <p style="font-size:13px; color:#6b7280; font-family:monospace; margin-top:2px;" id="viewModalRef"></p>
            </div>
            <button class="close-btn" onclick="document.getElementById('viewModal').style.display='none'">×</button>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
            <div style="grid-column:span 2; background:#f9fafb; padding:16px; border-radius:12px; border:1px solid #f3f4f6;">
                <div style="font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Material</div>
                <div style="font-weight:700; color:#111827; font-size:16px;" id="viewModalItem"></div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Date</div>
                <div style="font-weight:600; color:#374151;" id="viewModalDate"></div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Direction</div>
                <div style="font-weight:700;" id="viewModalDir"></div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Trans. Type</div>
                <div id="viewModalType"></div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Quantity</div>
                <div style="font-weight:800; font-size:20px;" id="viewModalQty"></div>
            </div>
        </div>
        <div style="margin-bottom:24px;">
            <div style="font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:8px;">Internal Notes</div>
            <div style="background:#f3f4f6; border-radius:10px; padding:12px; font-size:13px; color:#4b5563; min-height:60px;" id="viewModalNotes"></div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:20px; border-top:1px solid #f3f4f6;">
            <div style="font-size:11px; color:#6b7280;">Recorded by: <span style="font-weight:600; color:#374151;" id="viewModalAdmin"></span></div>
            <button onclick="document.getElementById('viewModal').style.display='none'" class="btn-action blue">Close</button>
        </div>
    </div>
</div>

<!-- Transaction Modal -->
<div id="txModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Record Transaction</h3>
            <button class="close-btn" onclick="closeModal()">×</button>
        </div>
        <form id="txForm" onsubmit="saveTransaction(event)">
            <input type="hidden" name="action" value="record_transaction">
            <input type="hidden" id="txType" name="transaction_type" value="">
            
            <div class="form-grid">
                <div class="form-group full">
                    <label for="txItem">Resource / Material *</label>
                    <select id="txItem" name="item_id" required style="width: 100%;">
                        <option value="">Search for an item...</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?> (SOH: <?php echo (float)InventoryManager::getStockOnHand($item['id']); ?> <?php echo $item['unit']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="txDate">Transaction Date *</label>
                    <input type="date" id="txDate" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="filter-group">
                    <label for="txQty">Quantity *</label>
                    <input type="number" step="0.01" id="txQty" name="quantity" min="0.01" required placeholder="0.00">
                </div>
                
                <div class="filter-group">
                    <label for="txRefType">Ref Category</label>
                    <select id="txRefType" name="reference_type">
                        <option value="">General Adjustment</option>
                        <option value="Customization">Customization</option>
                        <option value="PurchaseOrder">Purchase Order</option>
                        <option value="InventoryReturn">Return to Stock</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="txRefId">Ref Number / Code</label>
                    <input type="text" id="txRefId" name="reference_id" placeholder="e.g. JO-4022">
                </div>
                
                <div class="form-group full">
                    <label for="txNotes">Internal Memo / Notes</label>
                    <input type="text" id="txNotes" name="notes" placeholder="Reason for this movement...">
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 24px; border-top: 1px solid #f3f4f6;">
                <button type="button" onclick="closeModal()" class="btn-secondary" style="height: 44px; border-radius: 10px; padding: 0 24px;">Cancel</button>
                <button type="submit" class="btn-primary" id="saveBtn" style="height: 44px; border-radius: 10px; padding: 0 24px; background: #6366f1;">Submit Entry</button>
            </div>
        </form>
    </div>
</div>

<script>
    let allTransactions = [];
    let ledgerPage = 1;
    const ledgerPerPage = 10;
    let currentSort = 'transaction_date';
    let currentDir = 'DESC';
    let activeSort = 'newest';

    function filterPanel() {
        return {
            sortOpen: false,
            filterOpen: false,
            activeSort: activeSort,
            get hasActiveFilters() {
                // Check if filters differ from defaults
                const start = document.getElementById('fp_start_date')?.value;
                const end = document.getElementById('fp_end_date')?.value;
                const item = document.getElementById('fp_item_id')?.value;
                const type = document.getElementById('fp_type')?.value;
                
                const defaultStart = '<?php echo date('Y-m-01'); ?>';
                const defaultEnd = '<?php echo date('Y-m-t'); ?>';
                
                return item || type || start !== defaultStart || end !== defaultEnd;
            }
        };
    }

    function applySortFilter(sortKey) {
        activeSort = sortKey;
        if (sortKey === 'newest') { currentSort = 'transaction_date'; currentDir = 'DESC'; }
        else if (sortKey === 'oldest') { currentSort = 'transaction_date'; currentDir = 'ASC'; }
        else if (sortKey === 'az') { currentSort = 'item_name'; currentDir = 'ASC'; }
        else if (sortKey === 'za') { currentSort = 'item_name'; currentDir = 'DESC'; }
        
        ledgerPage = 1;
        loadTransactions();
        
        const alpineEl = document.querySelector('[x-data="filterPanel()"]');
        if (alpineEl && alpineEl._x_dataStack) {
            alpineEl._x_dataStack[0].activeSort = sortKey;
            alpineEl._x_dataStack[0].sortOpen = false;
        }
    }

    function resetFilterField(fields) {
        fields.forEach(f => {
            const el = document.getElementById('fp_' + f);
            if (el) {
                if (f === 'start_date') el.value = '<?php echo date('Y-m-01'); ?>';
                else if (f === 'end_date') el.value = '<?php echo date('Y-m-t'); ?>';
                else el.value = '';
            }
        });
        ledgerPage = 1;
        loadTransactions();
    }

    function applyFilters(reset = false) {
        if (reset) {
            ['fp_item_id', 'fp_type'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('fp_start_date').value = '<?php echo date('Y-m-01'); ?>';
            document.getElementById('fp_end_date').value = '<?php echo date('Y-m-t'); ?>';
            activeSort = 'newest';
            currentSort = 'transaction_date';
            currentDir = 'DESC';
        }
        ledgerPage = 1;
        loadTransactions();
    }

    async function loadTransactions() {
        const itemId = document.getElementById('fp_item_id')?.value || '';
        const type = document.getElementById('fp_type')?.value || '';
        const start = document.getElementById('fp_start_date')?.value || '';
        const end = document.getElementById('fp_end_date')?.value || '';
        
        try {
            const res = await fetch(`inventory_transactions_api.php?action=get_transactions&item_id=${itemId}&type=${type}&start_date=${start}&end_date=${end}&sort=${currentSort}&dir=${currentDir}`);
            const data = await res.json();
            
            if (data.success) {
                allTransactions = data.data;
                renderTransactions(allTransactions);
                updateBadgeCount();
            }
        } catch (e) {
            console.error(e);
            document.getElementById('ledgerTableBody').innerHTML = '<tr><td colspan="8" style="color:red; text-align:center;">Network error.</td></tr>';
        }
    }

    function updateBadgeCount() {
        const item = document.getElementById('fp_item_id')?.value;
        const type = document.getElementById('fp_type')?.value;
        const start = document.getElementById('fp_start_date')?.value;
        const end = document.getElementById('fp_end_date')?.value;
        
        const defaultStart = '<?php echo date('Y-m-01'); ?>';
        const defaultEnd = '<?php echo date('Y-m-t'); ?>';
        
        let count = 0;
        if (item) count++;
        if (type) count++;
        if (start !== defaultStart) count++;
        if (end !== defaultEnd) count++;
        
        const cont = document.getElementById('filterBadgeContainer');
        if (cont) cont.innerHTML = count > 0 ? `<span class="filter-badge">${count}</span>` : '';
    }

    function renderTransactions(transactions) {
        const tbody = document.getElementById('ledgerTableBody');
        const total = transactions.length;
        const totalPages = Math.max(1, Math.ceil(total / ledgerPerPage));
        if (ledgerPage > totalPages) ledgerPage = totalPages;

        const startIdx = (ledgerPage - 1) * ledgerPerPage;
        const endIdx = Math.min(startIdx + ledgerPerPage, total);
        const pageData = transactions.slice(startIdx, endIdx);

        // Update showing text
        const sc = document.getElementById('showingCount');
        if (sc) {
            if (total === 0) {
                sc.parentNode.innerHTML = `Showing <strong style="color:#1f2937;" id="showingCount">0</strong> transactions`;
            } else {
                sc.parentNode.innerHTML = `Showing <strong style="color:#1f2937;" id="showingCount">${startIdx + 1}\u2013${endIdx}</strong> of ${total} transactions`;
            }
        }

        if (total === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 60px; color:#6b7280; font-size: 15px;">No logs found for this period.</td></tr>';
            document.getElementById('ledgerPagination').innerHTML = '';
            return;
        }

        let html = '';
        pageData.forEach(t => {
            const qty = parseFloat(t.quantity);
            const isIN = (t.direction === 'IN');
            const displayQty = isIN ? '+' + qty.toFixed(2) : '-' + qty.toFixed(2);
            const qtyClass = isIN ? 'qty-val positive' : 'qty-val negative';
            const badgeClass = isIN ? 'badge-in' : 'badge-out';
            
            let displayType = (t.ref_type || t.direction || 'MOVEMENT').replace('_', ' ').toLowerCase();
            
            // Map job order to customization and apply specific styling requested by user
            let typeBadgeClass = `badge ${badgeClass}`;
            let typeBadgeStyle = '';
            if (displayType === 'joborder' || displayType === 'job order') {
                displayType = 'customization';
                typeBadgeClass = '';
                typeBadgeStyle = 'display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#eef2ff;color:#4338ca;';
            }
            
            let rollBadge = t.roll_code ? `<span style="display:block;font-size:10px;color:#7c3aed;font-weight:600;margin-top:2px;text-transform:uppercase;">Roll: ${escapeHtml(t.roll_code)}</span>` : '';
            
            html += `<tr style="cursor:pointer;" onclick="viewTransaction(${JSON.stringify(t).replace(/"/g,'&quot;')})">
                <td style="font-family:monospace;font-size:12px;color:#9ca3af;">#TX-${t.id}</td>
                <td style="color:#6b7280;">${t.transaction_date}</td>
                <td style="font-weight:500;color:#111827;text-transform:capitalize;">${escapeHtml(t.item_name)}${rollBadge}</td>
                <td><span class="${typeBadgeClass}" style="text-transform:capitalize;pointer-events:none;${typeBadgeStyle}">${displayType}</span></td>
                <td style="text-align:right;">
                    <span class="${qtyClass}">${displayQty}</span>
                    <span style="font-size:11px;color:#6b7280;font-weight:600;margin-left:4px;">${t.unit}</span>
                </td>
                <td style="font-size:12px;color:#6b7280;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHtml(t.notes)}">${escapeHtml(t.notes || '—')}</td>
                <td style="font-size:12px;color:#374151;">${escapeHtml(t.created_by_name || 'System')}</td>
                <td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation()">
                    <button onclick="event.stopPropagation();viewTransaction(${JSON.stringify(t).replace(/"/g,'&quot;')})" class="btn-action blue">View</button>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
        renderLedgerPagination(totalPages);
    }

    function renderLedgerPagination(totalPages) {
        const container = document.getElementById('ledgerPagination');
        if (totalPages <= 1) { container.innerHTML = ''; return; }

        let html = '<div style="display:flex; align-items:center; justify-content:center; gap:4px; margin-top:20px; padding-top:16px; border-top:1px solid #f3f4f6;">';

        if (ledgerPage > 1) {
            html += `<a href="#" onclick="event.preventDefault(); goToLedgerPage(${ledgerPage - 1})" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid #e5e7eb;color:#374151;text-decoration:none;font-size:13px;transition:all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>`;
        }

        for (let i = 1; i <= totalPages; i++) {
            const isActive = (i === ledgerPage);
            const bg = isActive ? 'background:#1f2937;color:white;border-color:#1f2937;' : 'background:white;color:#374151;border:1px solid #e5e7eb;';
            const fw = isActive ? '600' : '500';
            html += `<a href="#" onclick="event.preventDefault(); goToLedgerPage(${i})" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:${fw};transition:all 0.2s;${bg}"`;
            if (!isActive) html += ` onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'"`;
            html += `>${i}</a>`;
        }

        if (ledgerPage < totalPages) {
            html += `<a href="#" onclick="event.preventDefault(); goToLedgerPage(${ledgerPage + 1})" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid #e5e7eb;color:#374151;text-decoration:none;font-size:13px;transition:all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>`;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    function goToLedgerPage(page) {
        ledgerPage = page;
        renderTransactions(allTransactions);
    }

    // Removed handleSort and updateSortIcons as sorting is now handled by applySortFilter and Alpine.js

    function viewTransaction(t) {
        const isIN = (t.direction === 'IN');
        const qty = parseFloat(t.quantity);
        const displayQty = isIN ? '+' + qty.toFixed(2) : '-' + qty.toFixed(2);
        document.getElementById('viewModalRef').textContent = '#TX-' + t.id;
        document.getElementById('viewModalDate').textContent = t.transaction_date;
        document.getElementById('viewModalItem').textContent = t.item_name;
        document.getElementById('viewModalItem').style.textTransform = 'capitalize';
        
        let typeStr = (t.ref_type || t.direction || 'MOVEMENT').replace('_',' ').toLowerCase();
        if (typeStr === 'joborder' || typeStr === 'job order') typeStr = 'customization';
        
        document.getElementById('viewModalType').textContent = typeStr;
        document.getElementById('viewModalType').style.textTransform = 'capitalize';
        document.getElementById('viewModalDir').textContent = t.direction;
        document.getElementById('viewModalDir').style.color = isIN ? '#059669' : '#dc2626';
        document.getElementById('viewModalQty').textContent = displayQty + ' ' + t.unit;
        document.getElementById('viewModalQty').style.color = isIN ? '#059669' : '#dc2626';
        document.getElementById('viewModalNotes').textContent = t.notes || 'No notes.';
        document.getElementById('viewModalAdmin').textContent = t.created_by_name || 'System';
        document.getElementById('viewModal').style.display = 'flex';
    }

    function openModal(mode) {
        document.getElementById('txModal').style.display = 'flex';
        const form = document.getElementById('txForm');
        form.reset();
        document.getElementById('txDate').value = new Date().toISOString().split('T')[0];
        
        if (mode === 'issue') {
            document.getElementById('modalTitle').textContent = 'Issue Material (STOCK-OUT)';
            document.getElementById('txType').value = 'issue';
            document.getElementById('txRefType').value = 'Customization';
        } else if (mode === 'purchase') {
            document.getElementById('modalTitle').textContent = 'Receive Stock (STOCK-IN)';
            document.getElementById('txType').value = 'purchase';
            document.getElementById('txRefType').value = 'PurchaseOrder';
        }
    }

    function closeModal() {
        document.getElementById('txModal').style.display = 'none';
    }

    async function saveTransaction(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.textContent = 'Recording...';

        const formData = new FormData(document.getElementById('txForm'));
        try {
            const res = await fetch('inventory_transactions_api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                closeModal();
                loadTransactions();
                
                // Show FIFO deduction summary if roll-based
                if (data.fifo_deductions && data.fifo_deductions.length > 0) {
                    let summary = 'FIFO Stock-Out Summary:\n\n';
                    data.fifo_deductions.forEach(d => {
                        summary += `Roll: ${d.roll_code}\n`;
                        summary += `  Deducted: ${parseFloat(d.deducted).toFixed(2)} ft\n`;
                        summary += `  Was: ${parseFloat(d.was).toFixed(2)} ft → Now: ${parseFloat(d.now).toFixed(2)} ft`;
                        if (d.status === 'FINISHED') summary += ' (FINISHED)';
                        summary += '\n\n';
                    });
                    alert(summary);
                }
            } else { alert('Error: ' + data.error); }
        } catch (err) { alert('Network failure.'); } 
        finally { btn.disabled = false; btn.textContent = 'Submit Entry'; }
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    document.addEventListener('DOMContentLoaded', loadTransactions);
    
    document.addEventListener('DOMContentLoaded', () => {
        ['fp_item_id', 'fp_type', 'fp_start_date', 'fp_end_date'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', () => { ledgerPage = 1; loadTransactions(); });
        });
    });

    window.addEventListener('click', e => {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
</script>
</body>
</html>
