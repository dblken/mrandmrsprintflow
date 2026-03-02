<?php
/**
 * Global Roll Management
 * View and manage all inventory rolls.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');
$page_title = 'Roll Management - Admin';

// Get items that are roll-tracked
$rollItems = db_query("SELECT id, name FROM inv_items WHERE track_by_roll = 1 AND status = 'ACTIVE' ORDER BY name ASC") ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .roll-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .roll-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .roll-card:hover { transform: translateY(-4px); border-color: #6366f1; }
        .roll-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .roll-item-name { font-size: 14px; font-weight: 800; color: #111827; }
        .roll-code { font-family: monospace; font-size: 11px; color: #6b7280; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
        .progress-bar { height: 10px; background: #e5e7eb; border-radius: 10px; overflow: hidden; margin: 12px 0; }
        .progress-fill { height: 100%; border-radius: 10px; transition: width 0.5s ease-out; }
        .roll-stats { display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: #4b5563; }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-content { background: #fff; padding: 32px; border-radius: 20px; width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .btn-void { background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; cursor: pointer; }
        .btn-void:hover { background: #ef4444; color: #fff; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <div class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <div>
                <h1 class="page-title">Roll Tracking Dashboard</h1>
                <p style="color:#6b7280; font-size:14px;">Monitor real-time remaining length for all stock rolls.</p>
            </div>
            <button onclick="document.getElementById('addRollModal').style.display='flex'" class="btn-primary">Add New Roll</button>
        </header>

        <div style="margin-bottom: 24px; display: flex; gap: 12px;">
            <select id="filterItem" onchange="loadRolls()" class="p-2 border rounded-lg text-sm bg-white">
                <option value="">All Materials</option>
                <?php foreach ($rollItems as $item): ?>
                    <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterStatus" onchange="loadRolls()" class="p-2 border rounded-lg text-sm bg-white">
                <option value="OPEN">Open Only</option>
                <option value="FINISHED">Finished Only</option>
                <option value="VOID">Void Only</option>
                <option value="">All Statuses</option>
            </select>
        </div>

        <div id="rollGrid" class="roll-grid">
            <!-- Dynamic rolls -->
        </div>
    </div>
</div>

<div id="addRollModal" class="modal">
    <div class="modal-content">
        <h3 class="font-bold text-xl mb-6">Add New Physical Roll</h3>
        <form id="addRollForm" onsubmit="saveRoll(event)">
            <input type="hidden" name="action" value="add_roll">
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Item / Material</label>
                <select name="item_id" required class="w-full p-3 border rounded-xl bg-gray-50">
                    <?php foreach ($rollItems as $item): ?>
                        <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Total Length (FT)</label>
                <input type="number" step="0.01" name="total_length" value="164" required class="w-full p-3 border rounded-xl">
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Roll Code / Label</label>
                <input type="text" name="roll_code" placeholder="e.g. BATCH-001" class="w-full p-3 border rounded-xl">
            </div>
            <div class="flex gap-4">
                <button type="button" onclick="this.closest('.modal').style.display='none'" class="flex-1 p-3 bg-gray-100 rounded-xl font-bold">Cancel</button>
                <button type="submit" class="flex-1 p-3 bg-indigo-600 text-white rounded-xl font-bold">Save Roll</button>
            </div>
        </form>
    </div>
</div>

<script>
    async function loadRolls() {
        const itemId = document.getElementById('filterItem').value;
        const status = document.getElementById('filterStatus').value;
        const grid = document.getElementById('rollGrid');
        
        try {
            const res = await fetch(`inventory_rolls_api.php?action=list_rolls&item_id=${itemId}&status=${status}`);
            const data = await res.json();
            if(!data.success) throw new Exception();

            grid.innerHTML = '';
            data.data.forEach(roll => {
                const pct = (roll.remaining_length_ft / roll.total_length_ft) * 100;
                let color = '#10b981';
                if(pct < 20) color = '#f59e0b';
                if(pct < 10) color = '#ef4444';

                grid.innerHTML += `
                    <div class="roll-card">
                        <div class="roll-header">
                            <div>
                                <div class="roll-item-name">${roll.item_name || 'Material'}</div>
                                <div class="roll-code">${roll.roll_code || '#'+roll.id}</div>
                            </div>
                            <button onclick="voidRoll(${roll.id})" class="btn-void">VOID</button>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${pct}%; background: ${color};"></div>
                        </div>
                        <div class="roll-stats">
                            <span>${parseFloat(roll.remaining_length_ft).toFixed(2)} FT</span>
                            <span style="color:#9ca3af; font-weight:400;">OF ${parseFloat(roll.total_length_ft).toFixed(0)}</span>
                            <span style="color: ${color};">${Math.round(pct)}%</span>
                        </div>
                    </div>
                `;
            });
        } catch(e) { grid.innerHTML = 'Error loading rolls.'; }
    }

    async function saveRoll(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const res = await fetch('inventory_rolls_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) {
            e.target.closest('.modal').style.display = 'none';
            loadRolls();
        } else alert(data.error);
    }

    async function voidRoll(id) {
        if(!confirm('Are you sure you want to VOID this roll? It will be removed from active stock.')) return;
        const fd = new FormData();
        fd.append('action', 'void_roll');
        fd.append('roll_id', id);
        const res = await fetch('inventory_rolls_api.php', { method: 'POST', body: fd });
        if((await res.json()).success) loadRolls();
    }

    document.addEventListener('DOMContentLoaded', loadRolls);
</script>
</body>
</html>
