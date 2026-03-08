<?php
/**
 * Admin Product Variants Management
 * PrintFlow - Printing Shop PWA
 *
 * URL: /admin/product_variants.php?product_id=X
 * Manages variants and their BOM (material assignments) for a given product.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/variant_functions.php';

require_role(['Admin', 'Manager']);

$current_user = get_logged_in_user();

// -------------------------------------------------------------------
// Require product_id
// -------------------------------------------------------------------
$product_id = (int)($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
    redirect('/printflow/admin/products_management.php');
}

$product_rows = db_query("SELECT * FROM products WHERE product_id = ?", 'i', [$product_id]);
if (empty($product_rows)) {
    redirect('/printflow/admin/products_management.php');
}
$product = $product_rows[0];

// All materials for the BOM dropdown
$all_materials = db_query(
    "SELECT m.material_id, m.material_name, m.unit, mc.category_name
     FROM materials m
     JOIN material_categories mc ON mc.category_id = m.category_id
     ORDER BY mc.category_name, m.material_name"
) ?: [];

$error   = '';
$success = '';

// -------------------------------------------------------------------
// POST handlers
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {

    // --- Add Variant ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_variant') {
        $variant_name = trim($_POST['variant_name'] ?? '');
        $price        = (float)($_POST['price'] ?? 0);
        $status       = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';

        if ($variant_name === '') {
            $error = 'Variant name is required.';
        } else {
            $new_id = db_execute(
                "INSERT INTO product_variants (product_id, variant_name, price, status) VALUES (?, ?, ?, ?)",
                'isds', [$product_id, $variant_name, $price, $status]
            );
            if ($new_id) {
                // Save BOM
                $mat_ids = $_POST['material_id'] ?? [];
                $mat_qtys = $_POST['quantity_required'] ?? [];
                $bom = [];
                foreach ($mat_ids as $k => $mid) {
                    $bom[] = ['material_id' => (int)$mid, 'quantity_required' => (float)($mat_qtys[$k] ?? 0)];
                }
                save_variant_materials((int)$new_id, $bom);
                log_activity($current_user['user_id'], 'Added variant', "Variant \"{$variant_name}\" added to product ID {$product_id}");
                $success = "Variant \"{$variant_name}\" added successfully!";
            } else {
                $error = 'Failed to save variant.';
            }
        }
    }

    // --- Edit Variant ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'edit_variant') {
        $variant_id   = (int)($_POST['variant_id'] ?? 0);
        $variant_name = trim($_POST['variant_name'] ?? '');
        $price        = (float)($_POST['price'] ?? 0);
        $status       = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';

        if ($variant_id <= 0 || $variant_name === '') {
            $error = 'Invalid variant data.';
        } else {
            $ok = db_execute(
                "UPDATE product_variants SET variant_name=?, price=?, status=? WHERE variant_id=? AND product_id=?",
                'sdsii', [$variant_name, $price, $status, $variant_id, $product_id]
            );
            if ($ok !== false) {
                // Save BOM
                $mat_ids  = $_POST['material_id'] ?? [];
                $mat_qtys = $_POST['quantity_required'] ?? [];
                $bom = [];
                foreach ($mat_ids as $k => $mid) {
                    $bom[] = ['material_id' => (int)$mid, 'quantity_required' => (float)($mat_qtys[$k] ?? 0)];
                }
                save_variant_materials($variant_id, $bom);
                log_activity($current_user['user_id'], 'Edited variant', "Variant ID {$variant_id} updated");
                $success = "Variant \"{$variant_name}\" updated successfully!";
            } else {
                $error = 'Failed to update variant.';
            }
        }
    }

    // --- Toggle Status ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'Inactive';
        if (!in_array($new_status, ['Active','Inactive'])) $new_status = 'Inactive';

        db_execute(
            "UPDATE product_variants SET status=? WHERE variant_id=? AND product_id=?",
            'sii', [$new_status, $variant_id, $product_id]
        );
        $success = "Variant status changed to {$new_status}.";
    }

    // --- Delete Variant ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_variant') {
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        db_execute("DELETE FROM product_variants WHERE variant_id=? AND product_id=?", 'ii', [$variant_id, $product_id]);
        $success = 'Variant deleted.';
    }
}

// Reload variants after POST
$variants = get_variants_by_product($product_id);

$page_title = 'Manage Variants — ' . htmlspecialchars($product['name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .btn-action {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 5px 11px; border: 1px solid transparent; background: transparent;
            border-radius: 6px; font-size: 12px; font-weight: 500;
            transition: all .2s; cursor: pointer; text-decoration: none;
        }
        .btn-action.blue  { color:#3b82f6; border-color:#3b82f6; }
        .btn-action.blue:hover  { background:#3b82f6; color:#fff; }
        .btn-action.green { color:#10b981; border-color:#10b981; }
        .btn-action.green:hover { background:#10b981; color:#fff; }
        .btn-action.amber { color:#f59e0b; border-color:#f59e0b; }
        .btn-action.amber:hover { background:#f59e0b; color:#fff; }
        .btn-action.red   { color:#ef4444; border-color:#ef4444; }
        .btn-action.red:hover   { background:#ef4444; color:#fff; }

        .bom-row { display:grid; grid-template-columns:1fr 140px 36px; gap:8px; align-items:center; margin-bottom:8px; }
        .modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:50; }
        .modal-box { background:#fff;border-radius:12px;padding:28px;max-width:680px;width:95%;max-height:90vh;overflow-y:auto; }
        .section-divider { border-top:1px dashed #e5e7eb; margin: 20px 0; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <div>
                <a href="/printflow/admin/products_management.php" class="text-sm text-gray-500 hover:text-gray-700 mb-1 inline-flex items-center gap-1">
                    ← Back to Products
                </a>
                <h1 class="page-title" style="margin-top:4px;">
                    Variants: <?php echo htmlspecialchars($product['name']); ?>
                </h1>
                <p class="text-sm text-gray-500">
                    <?php echo htmlspecialchars($product['category']); ?>
                    &nbsp;·&nbsp; Base price: <?php echo format_currency($product['price']); ?>
                    &nbsp;·&nbsp; Status: <?php echo status_badge($product['status'], 'order'); ?>
                </p>
            </div>
            <button
                x-data
                @click="$dispatch('open-variant-modal', { mode: 'create' })"
                class="btn-primary">
                + Add Variant
            </button>
        </header>

        <main>
            <?php if ($success): ?>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Variants Table -->
            <div class="card">
                <?php if (empty($variants)): ?>
                    <div style="text-align:center;padding:48px;color:#9ca3af;">
                        <div style="font-size:40px;margin-bottom:12px;">📦</div>
                        <p style="font-size:15px;font-weight:500;">No variants yet.</p>
                        <p style="font-size:13px;margin-top:4px;">Click "+ Add Variant" to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b-2">
                                    <th class="text-left py-3">ID</th>
                                    <th class="text-left py-3">Variant Name</th>
                                    <th class="text-left py-3">Price</th>
                                    <th class="text-left py-3">Materials</th>
                                    <th class="text-left py-3">Status</th>
                                    <th class="text-left py-3">Created</th>
                                    <th class="text-right py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($variants as $v): ?>
                                    <?php $vm = get_variant_materials((int)$v['variant_id']); ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 text-gray-400">#<?php echo $v['variant_id']; ?></td>
                                        <td class="py-3 font-medium"><?php echo htmlspecialchars($v['variant_name']); ?></td>
                                        <td class="py-3 font-semibold"><?php echo format_currency($v['price']); ?></td>
                                        <td class="py-3">
                                            <?php if (empty($vm)): ?>
                                                <span class="text-gray-400 text-xs">None assigned</span>
                                            <?php else: ?>
                                                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                                <?php foreach ($vm as $m): ?>
                                                    <span style="background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:500;">
                                                        <?php echo htmlspecialchars($m['material_name']); ?>
                                                        (×<?php echo $m['quantity_required']; ?> <?php echo htmlspecialchars($m['unit']); ?>)
                                                    </span>
                                                <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3">
                                            <?php
                                            $sc = $v['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500';
                                            echo "<span class='px-2 py-1 text-xs font-semibold rounded-full {$sc}'>" . htmlspecialchars($v['status']) . "</span>";
                                            ?>
                                        </td>
                                        <td class="py-3 text-gray-400 text-xs"><?php echo format_date($v['created_at']); ?></td>
                                        <td class="py-3 text-right" style="white-space:nowrap;">
                                            <!-- Edit button triggers Alpine modal -->
                                            <button
                                                x-data
                                                @click="$dispatch('open-variant-modal', {
                                                    mode: 'edit',
                                                    variant_id: <?php echo $v['variant_id']; ?>,
                                                    variant_name: <?php echo json_encode($v['variant_name']); ?>,
                                                    price: <?php echo $v['price']; ?>,
                                                    status: <?php echo json_encode($v['status']); ?>,
                                                    materials: <?php echo json_encode(array_map(fn($m) => [
                                                        'material_id' => $m['material_id'],
                                                        'material_name' => $m['material_name'],
                                                        'unit' => $m['unit'],
                                                        'quantity_required' => $m['quantity_required']
                                                    ], $vm)); ?>
                                                })"
                                                class="btn-action blue">Edit</button>

                                            <!-- Toggle Status -->
                                            <form method="POST" class="inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="variant_id" value="<?php echo $v['variant_id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $v['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                                                <button type="submit" class="btn-action <?php echo $v['status'] === 'Active' ? 'amber' : 'green'; ?>">
                                                    <?php echo $v['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>

                                            <!-- Delete -->
                                            <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this variant and its materials?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_variant">
                                                <input type="hidden" name="variant_id" value="<?php echo $v['variant_id']; ?>">
                                                <button type="submit" class="btn-action red">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- ================================================================
     Add / Edit Variant Modal (Alpine.js)
================================================================ -->
<div
    x-data="{
        showModal: false,
        mode: 'create',
        variant_id: 0,
        variant_name: '',
        price: '',
        status: 'Active',
        materials: [],

        openModal(ev) {
            this.mode         = ev.mode;
            this.variant_id   = ev.variant_id   ?? 0;
            this.variant_name = ev.variant_name ?? '';
            this.price        = ev.price        ?? '';
            this.status       = ev.status       ?? 'Active';
            this.materials    = (ev.materials ?? []).map(m => ({
                material_id: m.material_id,
                material_name: m.material_name,
                unit: m.unit,
                quantity_required: m.quantity_required
            }));
            this.showModal = true;
        },

        addMaterialRow() {
            this.materials.push({ material_id: '', material_name: '', unit: '', quantity_required: '' });
        },

        removeMaterialRow(idx) {
            this.materials.splice(idx, 1);
        }
    }"
    @open-variant-modal.window="openModal($event.detail)"
    x-show="showModal"
    class="modal-overlay"
    style="display:none;">

    <div class="modal-box" @click.away="showModal = false">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="font-size:18px;font-weight:700;" x-text="mode === 'create' ? 'Add Variant' : 'Edit Variant'"></h3>
            <button @click="showModal = false" style="color:#9ca3af;font-size:20px;cursor:pointer;">✕</button>
        </div>

        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" :value="mode === 'create' ? 'add_variant' : 'edit_variant'">
            <input type="hidden" name="variant_id" :value="variant_id">

            <!-- Basic Fields -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px;">Variant Name *</label>
                    <input type="text" name="variant_name" class="input-field" x-model="variant_name" required placeholder="e.g. 2x3ft Standard">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px;">Price (PHP) *</label>
                    <input type="number" step="0.01" min="0" name="price" class="input-field" x-model="price" required placeholder="0.00">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px;">Status</label>
                <select name="status" class="input-field" x-model="status">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <!-- BOM: Materials Used -->
            <div class="section-divider"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div>
                    <p style="font-size:14px;font-weight:600;">Materials Used <span style="color:#9ca3af;font-weight:400;">(Bill of Materials)</span></p>
                    <p style="font-size:12px;color:#9ca3af;margin-top:2px;">Select material and quantity needed per unit of this variant.</p>
                </div>
                <button type="button" @click="addMaterialRow()" class="btn-action green" style="white-space:nowrap;">+ Add Row</button>
            </div>

            <!-- Header row -->
            <div class="bom-row" style="font-size:12px;font-weight:500;color:#6b7280;margin-bottom:4px;">
                <span>Material</span>
                <span>Qty Required</span>
                <span></span>
            </div>

            <!-- Material rows -->
            <template x-for="(row, idx) in materials" :key="idx">
                <div class="bom-row">
                    <select :name="'material_id[]'" x-model="row.material_id" class="input-field" required>
                        <option value="">— Select material —</option>
                        <?php foreach ($all_materials as $mat): ?>
                            <option value="<?php echo $mat['material_id']; ?>">
                                <?php echo htmlspecialchars($mat['category_name'] . ' › ' . $mat['material_name'] . ' (' . $mat['unit'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" min="0.01" :name="'quantity_required[]'"
                           x-model="row.quantity_required" class="input-field" placeholder="0.00" required>
                    <button type="button" @click="removeMaterialRow(idx)"
                            style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;border:1px solid #fca5a5;color:#ef4444;background:transparent;cursor:pointer;font-size:16px;"
                            title="Remove row">✕</button>
                </div>
            </template>

            <div x-show="materials.length === 0" style="text-align:center;padding:16px;color:#9ca3af;font-size:13px;background:#f9fafb;border-radius:8px;margin-bottom:8px;">
                No materials assigned. Click "+ Add Row" to add one.
            </div>

            <!-- Footer Buttons -->
            <div class="section-divider"></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" @click="showModal = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" x-text="mode === 'create' ? 'Create Variant' : 'Save Changes'"></button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
