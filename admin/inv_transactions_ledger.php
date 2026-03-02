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

        .header-actions { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; flex-wrap: wrap; gap: 20px; padding: 24px; background: var(--glass-bg); backdrop-filter: blur(12px); border-radius: 16px; border: 1px solid var(--glass-border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
        .filter-group input, .filter-group select { padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; background: #fff; transition: all 0.2s; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        
        .inv-table { width: 100%; border-collapse: separate; border-spacing: 0; background: transparent; }
        .inv-table th { background: #f9fafb; padding: 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #4b5563; text-align: left; border-bottom: 2px solid #e5e7eb; letter-spacing: 0.05em; }
        .inv-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #1f2937; }
        .inv-table tr:hover td { background: #f8fafc; }
        
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em; }
        .badge-in { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-out { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-neutral { background: #f9fafb; color: #4b5563; border: 1px solid #e5e7eb; }
        
        .qty-val { font-weight: 800; font-variant-numeric: tabular-nums; font-size: 15px; }
        .qty-val.positive { color: var(--in-color); }
        .qty-val.negative { color: var(--out-color); }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(17, 24, 39, 0.6); z-index: 9999; align-items: flex-start; justify-content: center; padding: 40px 20px; overflow-y: auto; backdrop-filter: blur(8px); animation: fadeIn 0.3s ease; }
        .modal-content { background: #fff; border-radius: 20px; width: 100%; max-width: 550px; padding: 32px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2); position: relative; z-index: 10001; pointer-events: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 20px; font-weight: 800; color: #111827; }
        .close-btn { background: #f3f4f6; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; padding: 6px; line-height: 1; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .close-btn:hover { background: #fee2e2; color: #ef4444; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .form-group.full { grid-column: span 2; }
        
        /* Ensure select elements in modal have consistent height and style */
        .modal select, .modal input { height: 44px; width: 100% !important; display: block; }
        .modal label { margin-bottom: 8px; display: block; font-weight: 700; color: #374151; font-size: 13px; }

        .btn-entry { height: 44px; display: inline-flex; align-items: center; gap: 8px; padding: 0 20px; border-radius: 12px; font-weight: 700; font-size: 13px; transition: all 0.2s; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 0.025em; }
        .btn-in { background: #10b981; color: #fff; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-in:hover { background: #059669; transform: translateY(-1px); }
        .btn-out { background: #ef4444; color: #fff; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .btn-out:hover { background: #dc2626; transform: translateY(-1px); }

        @keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
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
            <!-- Filters & Actions -->
            <div class="header-actions">
                <div style="display: flex; gap: 20px; flex-wrap: wrap; flex: 1;">
                    <div class="filter-group">
                        <label>Date Period</label>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="date" id="filterStart" value="<?php echo date('Y-m-01'); ?>" onchange="loadTransactions()">
                            <span style="color:#d1d5db; font-weight: 800;">&rarr;</span>
                            <input type="date" id="filterEnd" value="<?php echo date('Y-m-t'); ?>" onchange="loadTransactions()">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Filter by Item</label>
                        <select id="filterItem" style="width:240px;" onchange="loadTransactions()">
                            <option value="">All Materials</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>" <?php echo (isset($_GET['item_id']) && $_GET['item_id'] == $item['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Type</label>
                        <select id="filterType" style="min-width: 140px;" onchange="loadTransactions()">
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
                <div style="display:flex; gap:12px;">
                    <button onclick="openModal('issue')" class="btn-entry btn-out">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Issue OUT
                    </button>
                    <button onclick="openModal('purchase')" class="btn-entry btn-in">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Receive IN
                    </button>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="card" style="padding: 0; overflow-x: auto; border-radius: 16px; border: 1px solid #e5e7eb;">
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
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="ledgerTableBody">
                        <tr><td colspan="8" style="text-align:center; padding: 60px; color:#9ca3af; font-size:15px;">Retrieving audit logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Transaction View Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width:440px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Transaction Detail</h3>
                <p style="font-size:12px; color:#6b7280; font-family:monospace; margin-top:2px;" id="viewModalRef"></p>
            </div>
            <button class="close-btn" onclick="document.getElementById('viewModal').style.display='none'">×</button>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <div><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">Date</div><div style="font-weight:600;" id="viewModalDate"></div></div>
            <div><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">Direction</div><div style="font-weight:700;" id="viewModalDir"></div></div>
            <div style="grid-column:span 2;"><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">Material</div><div style="font-weight:700;color:#111827;font-size:15px;" id="viewModalItem"></div></div>
            <div><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">Transaction Type</div><div id="viewModalType"></div></div>
            <div><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">Quantity</div><div style="font-weight:800;font-size:18px;" id="viewModalQty"></div></div>
        </div>
        <div style="background:#f9fafb;border-radius:10px;padding:12px;margin-bottom:16px;">
            <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Notes</div>
            <div style="font-size:13px;color:#374151;" id="viewModalNotes"></div>
        </div>
        <div style="font-size:11px;color:#6b7280;">Recorded by: <span style="font-weight:600;color:#374151;" id="viewModalAdmin"></span></div>
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
                        <option value="JobOrder">Job Order</option>
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
    async function loadTransactions() {
        const item_id = document.getElementById('filterItem').value;
        const type = document.getElementById('filterType').value;
        const start = document.getElementById('filterStart').value;
        const end = document.getElementById('filterEnd').value;
        
        try {
            const res = await fetch(`inventory_transactions_api.php?action=get_transactions&item_id=${item_id}&type=${type}&start_date=${start}&end_date=${end}`);
            const data = await res.json();
            
            if (data.success) {
                renderTransactions(data.data);
            }
        } catch (e) {
            console.error(e);
            document.getElementById('ledgerTableBody').innerHTML = '<tr><td colspan="8" style="color:red; text-align:center;">Network error.</td></tr>';
        }
    }

    function renderTransactions(transactions) {
        const tbody = document.getElementById('ledgerTableBody');
        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 60px; color:#6b7280; font-size: 15px;">No logs found for this period.</td></tr>';
            return;
        }

        let html = '';
        transactions.forEach(t => {
            const qty = parseFloat(t.quantity);
            const isIN = (t.direction === 'IN');
            const displayQty = isIN ? '+' + qty.toFixed(2) : '-' + qty.toFixed(2);
            const qtyClass = isIN ? 'qty-val positive' : 'qty-val negative';
            const badgeClass = isIN ? 'badge-in' : 'badge-out';
            
            let displayType = (t.ref_type || t.direction || 'MOVEMENT').replace('_', ' ').toUpperCase();
            
            html += `<tr style="cursor:pointer;" onclick="viewTransaction(${JSON.stringify(t).replace(/"/g,'&quot;')})">
                <td style="color:#9ca3af; font-family:monospace; font-size:11px;">#TX-${t.id}</td>
                <td style="font-weight:600; color:#4b5563;">${t.transaction_date}</td>
                <td style="font-weight:700; color:#111827;">${escapeHtml(t.item_name)}</td>
                <td><span class="badge ${badgeClass}">${displayType}</span></td>
                <td style="text-align:right;">
                    <span class="${qtyClass}">${displayQty}</span>
                    <span style="font-size:11px; color:#6b7280; font-weight: 600;">${t.unit}</span>
                </td>
                <td style="color:#6b7280; font-size:12px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(t.notes)}">${escapeHtml(t.notes || '-')}</td>
                <td style="font-size:12px; color:#1f2937; font-weight: 600;">${escapeHtml(t.created_by_name || 'System')}</td>
                <td style="text-align:center;">
                    <button onclick="event.stopPropagation(); viewTransaction(${JSON.stringify(t).replace(/"/g,'&quot;')})" style="padding:5px 12px; background:#eef2ff; color:#4f46e5; border:1px solid #e0e7ff; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">View</button>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    function viewTransaction(t) {
        const isIN = (t.direction === 'IN');
        const qty = parseFloat(t.quantity);
        const displayQty = isIN ? '+' + qty.toFixed(2) : '-' + qty.toFixed(2);
        document.getElementById('viewModalRef').textContent = '#TX-' + t.id;
        document.getElementById('viewModalDate').textContent = t.transaction_date;
        document.getElementById('viewModalItem').textContent = t.item_name;
        document.getElementById('viewModalType').textContent = (t.ref_type || t.direction || 'MOVEMENT').replace('_',' ').toUpperCase();
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
            document.getElementById('txRefType').value = 'JobOrder';
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
