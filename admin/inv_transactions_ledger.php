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
        
        .inv-table { width: 100%; border-collapse: collapse; }
        .inv-table th { background: #f9fafb; padding: 12px 16px; font-size: 11px; font-weight: 700; text-transform: capitalize; color: #4b5563; text-align: left; border-bottom: 2px solid #e5e7eb; letter-spacing: 0.05em; }
        .inv-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #1f2937; vertical-align: middle; }
        .inv-table tr:hover td { background: #f9fafb; }
        
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
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;">
                    <span style="font-size:13px; color:#6b7280; white-space:nowrap;">Showing <strong style="color:#1f2937;" id="showingCount">0</strong> transactions</span>
                    
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                        <button onclick="openModal('purchase')" class="btn-entry btn-in" style="font-size:13px; font-weight:600; padding:0 12px; height:36px; display:inline-flex; align-items:center; gap:6px; border:none; background:#ecfdf5; color:#059669; border-radius:8px; cursor:pointer; transition:all 0.2s ease;">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Receive IN
                        </button>
                        <button onclick="openModal('issue')" class="btn-entry btn-out" style="font-size:13px; font-weight:600; padding:0 12px; height:36px; display:inline-flex; align-items:center; gap:6px; border:none; background:#fef2f2; color:#dc2626; border-radius:8px; cursor:pointer; transition:all 0.2s ease;">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Issue OUT
                        </button>
                        
                        <div style="position:relative; flex-shrink:0; margin-left:8px;">
                            <input type="date" id="filterStart" value="<?php echo date('Y-m-01'); ?>" onchange="loadTransactions()" style="width:125px; height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; padding-left:10px;">
                        </div>
                        <span style="color:#d1d5db;">&rarr;</span>
                        <div style="position:relative; flex-shrink:0;">
                            <input type="date" id="filterEnd" value="<?php echo date('Y-m-t'); ?>" onchange="loadTransactions()" style="width:125px; height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; padding-left:10px;">
                        </div>

                        <select id="filterItem" style="height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; padding:0 10px; color:#374151; width:auto;" onchange="loadTransactions()">
                            <option value="">All Materials</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>" <?php echo (isset($_GET['item_id']) && $_GET['item_id'] == $item['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filterType" style="height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; padding:0 10px; color:#374151; width:auto;" onchange="loadTransactions()">
                            <option value="">All Types</option>
                            <option value="opening_balance">Opening Balance</option>
                            <option value="purchase">Purchase (IN)</option>
                            <option value="issue">Issue (OUT)</option>
                            <option value="adjustment_up">Adj. Up (IN)</option>
                            <option value="adjustment_down">Adj. Down (OUT)</option>
                            <option value="return">Return (IN)</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3 cursor-pointer" onclick="handleSort('id')">Ref # <span id="sort-id"></span></th>
                                <th class="text-left py-3 cursor-pointer" onclick="handleSort('transaction_date')">Date <span id="sort-transaction_date"></span></th>
                                <th class="text-left py-3 cursor-pointer" onclick="handleSort('item_name')">Item Name <span id="sort-item_name"></span></th>
                                <th class="text-left py-3">Transaction Type</th>
                                <th class="text-right py-3 cursor-pointer" onclick="handleSort('quantity')">Quantity <span id="sort-quantity"></span></th>
                                <th class="text-left py-3">Notes</th>
                                <th class="text-left py-3">Admin</th>
                                <th class="text-right py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerTableBody">
                            <tr><td colspan="8" class="py-8 text-center text-gray-500">Retrieving audit logs...</td></tr>
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

    async function loadTransactions() {
        const item_id = document.getElementById('filterItem').value;
        const type = document.getElementById('filterType').value;
        const start = document.getElementById('filterStart').value;
        const end = document.getElementById('filterEnd').value;
        
        try {
            const res = await fetch(`inventory_transactions_api.php?action=get_transactions&item_id=${item_id}&type=${type}&start_date=${start}&end_date=${end}&sort=${currentSort}&dir=${currentDir}`);
            const data = await res.json();
            
            if (data.success) {
                allTransactions = data.data;
                renderTransactions(allTransactions);
                updateSortIcons();
            }
        } catch (e) {
            console.error(e);
            document.getElementById('ledgerTableBody').innerHTML = '<tr><td colspan="8" style="color:red; text-align:center;">Network error.</td></tr>';
        }
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
            
            html += `<tr class="border-b hover:bg-gray-50" style="cursor:pointer;" onclick="viewTransaction(${JSON.stringify(t).replace(/"/g,'&quot;')})">
                <td class="py-3 font-mono text-xs text-gray-400">#TX-${t.id}</td>
                <td class="py-3 font-medium text-gray-600">${t.transaction_date}</td>
                <td class="py-3 font-medium text-gray-900" style="text-transform: capitalize;">${escapeHtml(t.item_name)}${rollBadge}</td>
                <td class="py-3"><span class="${typeBadgeClass}" style="text-transform: capitalize; pointer-events:none; ${typeBadgeStyle}">${displayType}</span></td>
                <td class="py-3 text-right">
                    <span class="${qtyClass}">${displayQty}</span>
                    <span style="font-size:11px; color:#6b7280; font-weight: 600; margin-left:4px;">${t.unit}</span>
                </td>
                <td class="py-3 text-gray-500" style="font-size:12px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(t.notes)}">${escapeHtml(t.notes || '-')}</td>
                <td class="py-3 font-medium text-gray-800" style="font-size:12px;">${escapeHtml(t.created_by_name || 'System')}</td>
                <td class="py-3 text-right" style="white-space:nowrap;" onclick="event.stopPropagation()">
                    <button onclick="event.stopPropagation(); viewTransaction(${JSON.stringify(t).replace(/"/g,'&quot;')})" class="btn-action blue">View</button>
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

    function handleSort(col) {
        if (currentSort === col) {
            currentDir = currentDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            currentSort = col;
            currentDir = 'DESC';
        }
        ledgerPage = 1;
        loadTransactions();
    }

    function updateSortIcons() {
        const cols = ['id', 'transaction_date', 'item_name', 'quantity'];
        cols.forEach(c => {
            const el = document.getElementById('sort-' + c);
            if (!el) return;
            if (currentSort === c) {
                el.innerHTML = currentDir === 'ASC' ? ' ▲' : ' ▼';
                el.style.color = '#3b82f6';
            } else {
                el.innerHTML = '';
            }
        });
    }

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
    
    window.addEventListener('click', e => {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
</script>
</body>
</html>
