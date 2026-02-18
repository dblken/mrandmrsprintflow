<?php
/**
 * Admin Inventory Management Page
 * PrintFlow - Dynamic Inventory Module
 * CRUD for Material Categories and Materials
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();
$error = '';
$success = '';

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {

    // Category CRUD
    if (isset($_POST['create_category'])) {
        $name = sanitize($_POST['category_name'] ?? '');
        if (!empty($name)) {
            db_execute("INSERT INTO material_categories (category_name) VALUES (?)", 's', [$name]);
            $success = "Category \"$name\" created!";
        } else { $error = 'Category name is required.'; }

    } elseif (isset($_POST['update_category'])) {
        $cid  = (int)$_POST['category_id'];
        $name = sanitize($_POST['category_name'] ?? '');
        if ($cid && !empty($name)) {
            db_execute("UPDATE material_categories SET category_name = ? WHERE category_id = ?", 'si', [$name, $cid]);
            $success = 'Category updated!';
        }

    } elseif (isset($_POST['delete_category'])) {
        $cid = (int)$_POST['category_id'];
        if ($cid) {
            db_execute("DELETE FROM material_categories WHERE category_id = ?", 'i', [$cid]);
            $success = 'Category and all its materials deleted.';
        }

    // Material CRUD
    } elseif (isset($_POST['create_material'])) {
        $cat_id  = (int)($_POST['category_id'] ?? 0);
        $name    = sanitize($_POST['material_name'] ?? '');
        $opening = (float)($_POST['opening_stock'] ?? 0);
        $unit    = sanitize($_POST['unit'] ?? 'ft');
        if ($cat_id && !empty($name)) {
            db_execute(
                "INSERT INTO materials (category_id, material_name, opening_stock, current_stock, unit) VALUES (?, ?, ?, ?, ?)",
                'isdds', [$cat_id, $name, $opening, $opening, $unit]
            );
            $success = "Material \"$name\" added!";
        } else { $error = 'Category and material name are required.'; }

    } elseif (isset($_POST['update_material'])) {
        $mid     = (int)$_POST['material_id'];
        $name    = sanitize($_POST['material_name'] ?? '');
        $opening = (float)($_POST['opening_stock'] ?? 0);
        $unit    = sanitize($_POST['unit'] ?? 'ft');
        if ($mid && !empty($name)) {
            db_execute("UPDATE materials SET material_name = ?, opening_stock = ?, unit = ? WHERE material_id = ?", 'sdsi', [$name, $opening, $unit, $mid]);
            $success = 'Material updated!';
        }

    } elseif (isset($_POST['delete_material'])) {
        $mid = (int)$_POST['material_id'];
        if ($mid) {
            db_execute("DELETE FROM materials WHERE material_id = ?", 'i', [$mid]);
            $success = 'Material deleted.';
        }
    }
}

// ── Fetch data ────────────────────────────────────────────
$categories = db_query("SELECT * FROM material_categories ORDER BY category_name ASC") ?: [];
$selected_cat = (int)($_GET['category_id'] ?? ($categories[0]['category_id'] ?? 0));
$materials = $selected_cat
    ? (db_query("SELECT * FROM materials WHERE category_id = ? ORDER BY material_name ASC", 'i', [$selected_cat]) ?: [])
    : [];

$page_title = 'Inventory Management - Admin';
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
        .tab-bar { display:inline-flex; gap:0; background:#e0e7ff; border-radius:50px; padding:4px; border:2px solid #6366f1; }
        .tab-btn { padding:10px 28px; border:none; background:transparent; border-radius:50px; font-weight:600; font-size:13px; cursor:pointer; color:#6366f1; transition:all .25s ease; display:inline-flex; align-items:center; gap:8px; white-space:nowrap; }
        .tab-btn:hover { background:rgba(99,102,241,.1); }
        .tab-btn.active { background:#6366f1; color:#fff; box-shadow:0 2px 8px rgba(99,102,241,.35); }
        .tab-btn .tab-icon { width:16px; height:16px; flex-shrink:0; }
        .tab-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .pill-link-dark { display:inline-flex; align-items:center; gap:8px; padding:12px 28px; background:#1f2937; color:#fff; border-radius:50px; font-weight:600; font-size:13px; text-decoration:none; border:2px solid #1f2937; transition:all .25s ease; white-space:nowrap; }
        .pill-link-dark:hover { background:#374151; border-color:#374151; }
        .pill-link-dark .tab-icon { width:16px; height:16px; flex-shrink:0; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        .inv-table { width:100%; border-collapse:separate; border-spacing:0; }
        .inv-table th { padding:12px 16px; font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; background:#f9fafb; text-align:left; border-bottom:1px solid #e5e7eb; }
        .inv-table td { padding:12px 16px; border-bottom:1px solid #f3f4f6; font-size:14px; }
        .inv-table tr:hover td { background:#f9fafb; }
        .badge-unit { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#ede9fe; color:#7c3aed; }
        .mini-form { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
        .mini-form .field { display:flex; flex-direction:column; gap:4px; }
        .mini-form .field label { font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
        .mini-form .field input, .mini-form .field select { padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:#fff; }
        .mini-form .field input:focus, .mini-form .field select:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.15); }
        .action-btns { display:flex; gap:6px; }
        .btn-sm { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; border:none; cursor:pointer; transition:all .15s; }
        .btn-edit { background:#ede9fe; color:#7c3aed; }
        .btn-edit:hover { background:#ddd6fe; }
        .btn-del { background:#fef2f2; color:#ef4444; }
        .btn-del:hover { background:#fee2e2; }
        .empty-state { text-align:center; padding:48px 20px; color:#9ca3af; }
        .empty-state svg { width:48px; height:48px; margin:0 auto 12px; opacity:.4; }
        .cat-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; background:#f3f4f6; border-radius:20px; font-size:13px; font-weight:500; border:2px solid transparent; text-decoration:none; color:#374151; transition:all .2s; }
        .cat-chip.active { background:#eef2ff; border-color:#6366f1; color:#4f46e5; }
        .cat-chip:hover { background:#eef2ff; }
        .stock-val { font-weight:700; font-variant-numeric:tabular-nums; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Inventory Management</h1>
        </header>

        <main>
            <?php if ($success): ?>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; font-weight:500;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; font-weight:500;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Tab Row: pill toggle left, monthly view link right -->
            <div class="tab-row">
                <div class="tab-bar">
                    <button class="tab-btn active" onclick="switchTab('categories')">
                        <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        Categories
                    </button>
                    <button class="tab-btn" onclick="switchTab('materials')">
                        <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Materials
                    </button>
                </div>
                <a href="inventory_monthly" class="pill-link-dark">
                    <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Monthly View
                </a>
            </div>

            <!-- ═══════ CATEGORIES TAB ═══════ -->
            <div id="tab-categories" class="tab-content active">
                <div class="card">
                    <h2 style="margin-bottom:16px; font-size:16px; font-weight:700;">Material Categories</h2>

                    <!-- Add Category Form -->
                    <form method="POST" class="mini-form" style="margin-bottom:24px; padding:16px; background:#f9fafb; border-radius:10px;">
                        <?php echo csrf_field(); ?>
                        <div class="field" style="width:260px;">
                            <label>Category Name</label>
                            <input type="text" name="category_name" placeholder="e.g. Tarpaulin, Vinyl, Sticker…" required>
                        </div>
                        <button type="submit" name="create_category" class="btn-primary" style="height:38px; font-size:13px;">+ Add Category</button>
                    </form>

                    <?php if (empty($categories)): ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <p style="font-weight:600;">No categories yet</p>
                            <p style="font-size:13px;">Create your first material category above.</p>
                        </div>
                    <?php else: ?>
                        <table class="inv-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Materials</th>
                                    <th>Created</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat):
                                    $mat_count = db_query("SELECT COUNT(*) as cnt FROM materials WHERE category_id = ?", 'i', [$cat['category_id']]);
                                    $cnt = $mat_count[0]['cnt'] ?? 0;
                                ?>
                                <tr>
                                    <td style="font-weight:600; color:#6b7280;">#<?php echo $cat['category_id']; ?></td>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                    <td><span class="badge-unit"><?php echo $cnt; ?> types</span></td>
                                    <td style="font-size:12px; color:#9ca3af;"><?php echo date('M j, Y', strtotime($cat['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns" style="justify-content:flex-end;">
                                            <button class="btn-sm btn-edit" onclick="editCategory(<?php echo $cat['category_id']; ?>, '<?php echo addslashes($cat['category_name']); ?>')">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category and ALL its materials?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                                <button type="submit" name="delete_category" class="btn-sm btn-del">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══════ MATERIALS TAB ═══════ -->
            <div id="tab-materials" class="tab-content">
                <div class="card">
                    <h2 style="margin-bottom:16px; font-size:16px; font-weight:700;">Materials</h2>

                    <!-- Category Selector -->
                    <?php if (!empty($categories)): ?>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px;">
                        <?php foreach ($categories as $cat): ?>
                            <a href="?category_id=<?php echo $cat['category_id']; ?>#materials"
                               class="cat-chip <?php echo $selected_cat == $cat['category_id'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Add Material Form -->
                    <form method="POST" class="mini-form" style="margin-bottom:24px; padding:16px; background:#f9fafb; border-radius:10px;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="category_id" value="<?php echo $selected_cat; ?>">
                        <div class="field" style="width:220px;">
                            <label>Material Name</label>
                            <input type="text" name="material_name" placeholder="e.g. 3FT, BLUE, AC MC, MATTE…" required>
                        </div>
                        <div class="field" style="width:130px;">
                            <label>Opening Stock</label>
                            <input type="number" step="0.01" name="opening_stock" value="0" required>
                        </div>
                        <div class="field" style="width:120px;">
                            <label>Unit of Measure</label>
                            <input type="text" name="unit" list="unit-options" value="pcs" placeholder="e.g. ft, pcs" required>
                            <datalist id="unit-options">
                                <option value="ft">
                                <option value="roll">
                                <option value="pcs">
                                <option value="sheets">
                                <option value="meters">
                                <option value="bottles">
                                <option value="liters">
                                <option value="ml">
                                <option value="sets">
                            </datalist>
                        </div>
                        <button type="submit" name="create_material" class="btn-primary" style="height:38px; font-size:13px;">+ Add Material</button>
                    </form>

                    <?php if (empty($materials)): ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <p style="font-weight:600;">No materials in this category</p>
                            <p style="font-size:13px;">Add materials above to start tracking inventory.</p>
                        </div>
                    <?php else: ?>
                        <table class="inv-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Material Name</th>
                                    <th>Opening Stock</th>
                                    <th>Current Stock</th>
                                    <th>Unit</th>
                                    <th>Created</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $mat): ?>
                                <tr>
                                    <td style="font-weight:600; color:#6b7280;">#<?php echo $mat['material_id']; ?></td>
                                    <td style="font-weight:700;"><?php echo htmlspecialchars($mat['material_name']); ?></td>
                                    <td class="stock-val"><?php echo number_format((float)$mat['opening_stock'], 2); ?></td>
                                    <td>
                                        <span class="stock-val" style="color:<?php echo (float)$mat['current_stock'] <= 0 ? '#ef4444' : '#059669'; ?>;">
                                            <?php echo number_format((float)$mat['current_stock'], 2); ?>
                                        </span>
                                    </td>
                                    <td><span class="badge-unit"><?php echo htmlspecialchars($mat['unit']); ?></span></td>
                                    <td style="font-size:12px; color:#9ca3af;"><?php echo date('M j, Y', strtotime($mat['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns" style="justify-content:flex-end;">
                                            <button class="btn-sm btn-edit" onclick="editMaterial(<?php echo $mat['material_id']; ?>, '<?php echo addslashes($mat['material_name']); ?>', <?php echo $mat['opening_stock']; ?>, '<?php echo addslashes($mat['unit']); ?>')">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this material and all its stock history?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="material_id" value="<?php echo $mat['material_id']; ?>">
                                                <button type="submit" name="delete_material" class="btn-sm btn-del">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p style="font-weight:600;">Create a category first</p>
                            <p style="font-size:13px;">Go to the Categories tab to add one.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCatModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; display:none; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; padding:28px; max-width:400px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-weight:700; margin-bottom:16px;">Edit Category</h3>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="category_id" id="editCatId">
            <div class="field" style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:6px;">CATEGORY NAME</label>
                <input type="text" name="category_name" id="editCatName" class="input-field" required>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" onclick="closeModal('editCatModal')" class="btn-secondary" style="flex:1;">Cancel</button>
                <button type="submit" name="update_category" class="btn-primary" style="flex:1;">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Material Modal -->
<div id="editMatModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; display:none; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; padding:28px; max-width:450px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-weight:700; margin-bottom:16px;">Edit Material</h3>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="material_id" id="editMatId">
            <div class="field" style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:6px;">MATERIAL NAME</label>
                <input type="text" name="material_name" id="editMatName" class="input-field" required>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="field">
                    <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:6px;">OPENING STOCK</label>
                    <input type="number" step="0.01" name="opening_stock" id="editMatStock" class="input-field" required>
                </div>
                <div class="field">
                    <label style="display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:6px;">UNIT OF MEASURE</label>
                    <input type="text" name="unit" id="editMatUnit" class="input-field" list="unit-options-edit" placeholder="e.g. ft, pcs, bottles" required>
                    <datalist id="unit-options-edit">
                        <option value="ft">
                        <option value="roll">
                        <option value="pcs">
                        <option value="sheets">
                        <option value="meters">
                        <option value="bottles">
                        <option value="liters">
                        <option value="ml">
                        <option value="sets">
                    </datalist>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" onclick="closeModal('editMatModal')" class="btn-secondary" style="flex:1;">Cancel</button>
                <button type="submit" name="update_material" class="btn-primary" style="flex:1;">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
    if (tab === 'materials') history.replaceState(null, '', location.pathname + location.search + '#materials');
}

// Auto-switch to materials tab if hash
if (location.hash === '#materials') {
    switchTab('materials');
    document.querySelectorAll('.tab-btn')[1].classList.add('active');
    document.querySelectorAll('.tab-btn')[0].classList.remove('active');
}

// Modals
function editCategory(id, name) {
    document.getElementById('editCatId').value = id;
    document.getElementById('editCatName').value = name;
    document.getElementById('editCatModal').style.display = 'flex';
}

function editMaterial(id, name, stock, unit) {
    document.getElementById('editMatId').value = id;
    document.getElementById('editMatName').value = name;
    document.getElementById('editMatStock').value = stock;
    document.getElementById('editMatUnit').value = unit;
    document.getElementById('editMatModal').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Close modal on backdrop click
document.querySelectorAll('#editCatModal, #editMatModal').forEach(modal => {
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(modal.id); });
});
</script>

</body>
</html>
