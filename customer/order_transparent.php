<?php
/**
 * Transparent Stickers - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $surface_application = trim($_POST['surface_application'] ?? '');
    $surface_other = trim($_POST['surface_other'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    $surface_display = ($surface_application === 'Others' && $surface_other) ? $surface_other : $surface_application;

    if (empty($surface_display) || empty($dimensions) || empty($layout) || empty($lamination) || $quantity < 1 || empty($needed_date)) {
        $error = 'Please fill in Branch, Surface, Dimensions, Layout, Lamination, Quantity, and Needed Date.';
    } elseif ($surface_application === 'Others' && empty($surface_other)) {
        $error = 'Please specify your surface type when Others is selected.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design file.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'trans_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'name' => 'Transparent Sticker Printing',
                    'price' => 0, // Calculated at checkout or review
                    'quantity' => $quantity,
                    'category' => 'Sticker Printing',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'surface_application' => $surface_display,
                        'dimensions' => $dimensions,
                        'layout' => $layout,
                        'lamination' => $lamination,
                        'needed_date' => $needed_date,
                        'additional_notes' => $additional_notes
                    ]
                ];

                if (isset($_POST['buy_now'])) {
                    redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
                } else {
                    redirect(BASE_URL . '/customer/cart.php');
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

$page_title = 'Order Transparent Stickers - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 trans-order-container">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Transparent Sticker Printing</h1>
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card order-container">
            <form method="POST" enctype="multipart/form-data" id="transForm">
                <?php echo csrf_field(); ?>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Where will the sticker be applied? *</label>
                    <div class="option-grid option-grid-3x2">
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Glass (Window/Door/Storefront)"> <span>Glass</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Plastic / Acrylic"> <span>Plastic/Acrylic</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Metal"> <span>Metal</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Smooth Painted Wall"> <span>Painted Wall</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Mirror"> <span>Mirror</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="surface_application" value="Others"> <span>Others</span></label>
                    </div>
                    <div id="surface-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="surface_other" id="surface_other" class="input-field" placeholder="Specify surface type">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dimensions *</label>
                    <p class="dim-feet-note">Common sizes (in feet)</p>
                    <div class="option-grid option-grid-dim">
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="2x2" onclick="selectDimPreset('2x2', event)">2×2</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="3x3" onclick="selectDimPreset('3x3', event)">3×3</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="4x4" onclick="selectDimPreset('4x4', event)">4×4</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="2x3" onclick="selectDimPreset('2x3', event)">2×3</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="3x4" onclick="selectDimPreset('3x4', event)">3×4</button>
                        <button type="button" class="opt-btn opt-btn-compact" data-dim="4x6" onclick="selectDimPreset('4x6', event)">4×6</button>
                        <button type="button" class="opt-btn opt-btn-compact dim-others-btn" id="dim-others-btn" onclick="selectDimOthers(event)">Others</button>
                    </div>
                    <input type="hidden" name="dimensions" id="dimensions_hidden">
                    <div id="dim-others-inputs" class="dim-others-row" style="display: none; margin-top: 1rem;">
                        <div>
                            <label class="dim-label">Width (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_width" class="input-field" placeholder="e.g. 5">
                        </div>
                        <div class="dim-sep">×</div>
                        <div>
                            <label class="dim-label">Height (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_height" class="input-field" placeholder="e.g. 6">
                        </div>
                    </div>
                </div>

                <div class="mb-4 trans-option-row">
                    <div class="trans-option-col">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Layout</label>
                        <div class="opt-btn-group opt-btn-compact-row">
                            <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="layout" value="With Layout" required> <span>With Layout</span></label>
                            <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="layout" value="Without Layout"> <span>Without Layout</span></label>
                        </div>
                    </div>
                    <div class="trans-option-col">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lamination</label>
                        <div class="opt-btn-group opt-btn-compact-row">
                            <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="lamination" value="With Laminate" required> <span>With Laminate</span></label>
                            <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="lamination" value="Without Laminate"> <span>Without Laminate</span></label>
                        </div>
                    </div>
                </div>

                <div class="mb-4 trans-qty-row">
                    <div class="trans-qty-item trans-qty-qty">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <div class="qty-control">
                            <button type="button" onclick="transDecreaseQty()" class="qty-btn">−</button>
                            <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                            <button type="button" onclick="transIncreaseQty()" class="qty-btn">+</button>
                        </div>
                    </div>
                    <div class="trans-qty-item trans-qty-date">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                        <input type="date" name="needed_date" class="input-field input-same-height" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="trans-qty-item trans-qty-upload">
                        <label class="block text-sm font-medium text-gray-700 mb-1 trans-upload-label">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                        <div class="file-input-wrap">
                            <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field input-file" required>
                        </div>
                    </div>
                </div>

                <div class="mb-4 notes-section">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="additional_notes" rows="3" class="input-field notes-textarea" placeholder="Any special instructions..."><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" style="height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none;">Back to Services</a>
                    <button type="submit" name="buy_now" value="1" style="height: 48px; min-width: 140px; padding: 0 1.25rem; background: #0a2530; color: #ffffff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 0.02em;">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.trans-order-container { max-width: 860px; }
.order-container { padding: 1.5rem; min-width: 0; }
.dim-feet-note { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem; }
.dim-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; display: block; margin-bottom: 0.25rem; }
.dim-sep { color: #9ca3af; font-weight: 600; align-self: center; }
.dim-others-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem; }
.option-grid { display: grid; gap: 0.4rem; }
.option-grid-3x2 { grid-template-columns: repeat(3, 1fr); }
.option-grid-dim { grid-template-columns: repeat(3, 1fr); }
.option-grid-dim .dim-others-btn { grid-column: 2; }
.opt-btn, .opt-btn-wrap { padding: 0.4rem 0.75rem; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.8rem; color: #374151; transition: all 0.25s ease; text-align: center; }
.opt-btn:hover, .opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn.active, .opt-btn-wrap:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: rgba(10,37,48,0.03); }
.opt-btn-wrap { display: inline-flex; align-items: center; justify-content: center; }
.opt-btn-wrap input { margin-right: 0.35rem; }
.opt-btn-compact { padding: 0.4rem 0.6rem; font-size: 0.8rem; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.opt-btn-compact-row { display: flex; gap: 0.4rem; flex-wrap: nowrap; }
.opt-btn-compact-row .opt-btn-wrap { flex: 1 1 0; min-width: 0; }
.trans-option-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: flex-end; }
.trans-option-col { min-width: 0; }
.trans-qty-row { display: grid; grid-template-columns: auto 1fr 1fr; gap: 1rem 1.5rem; align-items: flex-end; }
.trans-qty-item { min-width: 0; }
.trans-qty-qty { max-width: 140px; }
.trans-qty-date { min-width: 0; }
.trans-upload-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-input-wrap { height: 42px; display: flex; align-items: center; }
.input-file { height: 42px; padding: 0.4rem 0.5rem; font-size: 0.8rem; }
.qty-control { display: flex; align-items: center; height: 42px; border: 2px solid #d1d5db; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.2s ease; }
.qty-control:focus-within { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.qty-btn { flex: 0 0 38px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: background 0.2s; }
.qty-btn:hover { background: #e5e7eb; }
.qty-control input { flex: 1; min-width: 36px; border: none; text-align: center; font-weight: 700; font-size: 1rem; outline: none; background: transparent; }
.input-same-height { height: 42px; padding: 0 0.6rem; box-sizing: border-box; font-size: 0.9rem; }
.notes-section { min-width: 0; overflow: visible; }
.notes-textarea { width: 100%; max-width: 100%; box-sizing: border-box; resize: vertical; }
@media (max-width: 768px) {
    .option-grid-3x2 { grid-template-columns: repeat(2, 1fr); }
    .option-grid-dim { grid-template-columns: repeat(3, 1fr); }
    .trans-option-row { grid-template-columns: 1fr; }
    .trans-qty-row { grid-template-columns: 1fr; }
    .trans-qty-qty { max-width: none; }
    .opt-btn-compact-row { flex-wrap: wrap; }
}
@media (max-width: 480px) {
    .option-grid-3x2, .option-grid-dim { grid-template-columns: repeat(2, 1fr); }
    .trans-upload-label { white-space: normal; }
}
</style>

<script>
let dimensionMode = 'preset';

function transIncreaseQty() {
    const i = document.getElementById('quantity-input');
    i.value = Math.min(999, (parseInt(i.value) || 1) + 1);
}
function transDecreaseQty() {
    const i = document.getElementById('quantity-input');
    const v = parseInt(i.value) || 1;
    if (v > 1) i.value = v - 1;
}

function syncDimensions() {
    const h = document.getElementById('dimensions_hidden');
    if (dimensionMode === 'preset') {
        const btn = document.querySelector('.opt-btn.active');
        h.value = btn ? btn.dataset.dim.replace('x', '×') : '';
    } else {
        const w = document.getElementById('custom_width').value.trim();
        const g = document.getElementById('custom_height').value.trim();
        h.value = (w && g) ? w + '×' + g : '';
    }
}

function selectDimPreset(dim, e) {
    e.preventDefault();
    dimensionMode = 'preset';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    e.target.closest('.opt-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'none';
    document.getElementById('custom_width').value = '';
    document.getElementById('custom_height').value = '';
    syncDimensions();
}

function selectDimOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'flex';
    syncDimensions();
}

document.querySelectorAll('input[name="surface_application"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('surface-other-wrap').style.display = this.value === 'Others' ? 'block' : 'none';
        document.getElementById('surface_other').required = this.value === 'Others';
    });
});

['custom_width','custom_height'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9.]/g, '').slice(0, 6);
            syncDimensions();
        });
    }
});

document.getElementById('transForm').addEventListener('submit', function(e) {
    syncDimensions();
    const h = document.getElementById('dimensions_hidden');
    if (!h.value.trim()) {
        e.preventDefault();
        alert('Please select a dimension preset or enter custom dimensions.');
        return false;
    }
    if (dimensionMode === 'others') {
        const w = document.getElementById('custom_width').value.trim();
        const g = document.getElementById('custom_height').value.trim();
        if (!w || !g) {
            e.preventDefault();
            alert('Please enter Width and Height when Others is selected.');
            return false;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
