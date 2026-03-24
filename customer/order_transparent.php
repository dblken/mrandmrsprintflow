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
    } elseif ((function_exists('mb_strlen') ? mb_strlen($additional_notes) : strlen($additional_notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
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
                    'source_page' => 'services',
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

                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
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
            <form method="POST" enctype="multipart/form-data" id="transForm" novalidate>
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

                <div class="mb-4" id="card-layout">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Layout *</label>
                    <div class="opt-btn-group opt-btn-compact-row">
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="layout" value="With Layout" required> <span>With Layout</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="layout" value="Without Layout"> <span>Without Layout</span></label>
                    </div>
                </div>

                <div class="mb-4" id="card-lamination">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lamination *</label>
                    <div class="opt-btn-group opt-btn-compact-row">
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="lamination" value="With Laminate" required> <span>With Laminate</span></label>
                        <label class="opt-btn-wrap opt-btn-compact"><input type="radio" name="lamination" value="Without Laminate"> <span>Without Laminate</span></label>
                    </div>
                </div>

                <div class="mb-4" id="card-upload">
                    <label class="block text-sm font-medium text-gray-700 mb-1 trans-upload-label">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <div class="file-input-wrap">
                        <input type="file" id="design_file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field input-file" required>
                    </div>
                </div>

                <div class="mb-4 need-qty-card" id="card-date-qty">
                    <div class="need-qty-row">
                        <div class="need-qty-date" id="card-date">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field input-same-height" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="need-qty-qty" id="card-qty">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="qty-control qty-control-shopee">
                                <button type="button" onclick="transDecreaseQty()" class="qty-btn">−</button>
                                <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                                <button type="button" onclick="transIncreaseQty()" class="qty-btn">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 notes-section">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="additional_notes" rows="3" class="input-field notes-textarea" placeholder="Any special instructions..." maxlength="500"><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                </div>

                <div class="tshirt-actions-row">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                    <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                    <button type="submit" name="action" value="buy_now" id="buyNowBtn" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.trans-order-container { max-width: 640px; }
.order-container { padding: 1.5rem; min-width: 0; }
.dim-feet-note { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem; }
.dim-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; display: block; margin-bottom: 0.25rem; }
.dim-sep { color: #9ca3af; font-weight: 600; align-self: center; }
.dim-others-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 0.7ch minmax(0, 1fr);
    align-items: end;
    column-gap: 0.05rem;
    row-gap: 0;
    width: 100%;
}
.dim-others-row > div {
    width: 100%;
    max-width: none;
}
.dim-sep {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: end;
    width: 0.7ch;
    min-width: 0;
    height: 44px;
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 0;
    margin-left: 0;
    margin-right: 0;
    padding: 0;
}
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
.trans-qty-item { min-width: 0; }
.need-qty-card .need-qty-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.need-qty-card .need-qty-date { flex: 1; min-width: 0; }
.need-qty-card .need-qty-qty { flex: 1; min-width: 0; }
.need-qty-card .need-qty-qty .qty-control-shopee { width: 100%; }
.qty-control-shopee { width: 110px; flex-shrink: 0; }
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

/* T-shirt page visual parity (UI only) */
#transForm {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
#transForm .mb-4 {
    margin-bottom: 0 !important;
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
#transForm label.block {
    font-size: .95rem !important;
    font-weight: 700 !important;
    color: #d9e6ef !important;
    margin-bottom: .55rem !important;
}
#transForm input[type="radio"] {
    accent-color: #53c5e0;
}
#transForm .input-field {
    min-height: 44px;
    padding: .72rem .9rem;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
    color: #eef7fb !important;
}
#transForm .input-field::placeholder { color: #a3bdca !important; }
#transForm .input-field:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
}
#transForm select.input-field option {
    background: #0a2530 !important;
    color: #f8fafc !important;
}
#transForm select.input-field option:hover,
#transForm select.input-field option:focus {
    background: #53c5e0 !important;
    color: #06232c !important;
}
#transForm select.input-field option:checked {
    background: #53c5e0 !important;
    color: #06232c !important;
}
#transForm .input-field[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) brightness(1.35);
    opacity: .95;
    cursor: pointer;
}
.dim-feet-note,
.dim-label { color: #9fc6d9 !important; }
.opt-btn, .opt-btn-wrap {
    min-height: 44px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(83, 197, 224, 0.2) !important;
    color: #d2e7f1 !important;
}
.opt-btn:hover, .opt-btn-wrap:hover {
    background: rgba(83, 197, 224, 0.12) !important;
    border-color: rgba(83, 197, 224, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.12);
}
.opt-btn.active, .opt-btn-wrap:has(input:checked) {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important;
    border-color: #53c5e0 !important;
    color: #f8fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.22), 0 8px 18px rgba(11, 42, 56, 0.35);
}
.qty-control {
    height: 44px;
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
    border-radius: 10px;
}
.qty-btn {
    height: 44px;
    background: rgba(83, 197, 224, 0.12) !important;
    color: #d8edf5 !important;
}
.qty-btn:hover { background: rgba(83, 197, 224, 0.2) !important; }
.qty-control input { color: #f8fafc !important; }
#quantity-input::-webkit-outer-spin-button,
#quantity-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
#quantity-input { -moz-appearance: textfield; appearance: textfield; }

.notes-textarea {
    overflow-y: auto;
    resize: vertical;
    min-height: 110px;
    max-height: 220px;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08);
}
.notes-textarea::-webkit-scrollbar { width: 10px; }
.notes-textarea::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px;
}
.notes-textarea::-webkit-scrollbar-thumb {
    background: rgba(83, 197, 224, 0.65);
    border-radius: 999px;
    border: 2px solid rgba(10, 37, 48, 0.55);
}
.notes-textarea::-webkit-scrollbar-thumb:hover { background: rgba(83, 197, 224, 0.85); }
.field-error {
    margin-top: .4rem;
    font-size: .75rem;
    color: #fca5a5;
    line-height: 1.3;
    display: block;
    width: 100%;
}
#transForm .mb-4.is-invalid {
    border-color: rgba(239, 68, 68, 0.35) !important;
    box-shadow: none !important;
}
#transForm .mb-4.is-invalid .input-field,
#transForm .mb-4.is-invalid .qty-control {
    border-color: rgba(239, 68, 68, 0.55) !important;
}

.tshirt-actions-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: .75rem;
    margin-top: 1.1rem;
    flex-wrap: wrap;
}
.tshirt-btn {
    height: 46px;
    min-width: 150px;
    padding: 0 1.15rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    text-decoration: none;
    font-size: .9rem;
    font-weight: 700;
    transition: all .2s;
}
.tshirt-btn-secondary {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(83, 197, 224, 0.28) !important;
    color: #d9e6ef !important;
}
.tshirt-btn-secondary:hover {
    background: rgba(83, 197, 224, 0.14) !important;
    border-color: rgba(83, 197, 224, 0.52) !important;
}
.tshirt-btn-primary {
    border: none;
    background: linear-gradient(135deg, #53C5E0, #32a1c4);
    color: #fff;
    text-transform: uppercase;
    letter-spacing: .02em;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(50,161,196,0.3);
}
.tshirt-btn:active { transform: translateY(1px) scale(0.99); }

@media (max-width: 768px) {
    .option-grid-3x2 { grid-template-columns: repeat(2, 1fr); }
    .option-grid-dim { grid-template-columns: repeat(3, 1fr); }
    .trans-option-row { grid-template-columns: 1fr; }
    .opt-btn-compact-row { flex-wrap: wrap; }
    .need-qty-card .need-qty-row { flex-direction: column; align-items: stretch; }
    .need-qty-card .need-qty-qty .qty-control-shopee { width: 100%; }
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
    .tshirt-btn { width: 100%; }
    .dim-others-row { flex-wrap: nowrap; }
}
@media (max-width: 480px) {
    .option-grid-3x2, .option-grid-dim { grid-template-columns: repeat(2, 1fr); }
    .trans-upload-label { white-space: normal; }
    .dim-others-row {
        grid-template-columns: 1fr;
        gap: 0.6rem;
    }
    .dim-sep {
        height: auto;
        justify-self: center;
    }
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
    window.__transValidationTriggered = true;
    if (!checkTransFormValid()) {
        e.preventDefault();
        return false;
    }
});

function clearFieldError(container) {
    if (!container) return;
    const err = container.querySelector('.field-error');
    container.classList.remove('is-invalid');
    if (err) {
        err.textContent = '';
        err.style.display = 'none';
    }
}

function setFieldError(container, message) {
    if (!container) return;
    let err = container.querySelector('.field-error');
    if (!err) {
        err = document.createElement('div');
        err.className = 'field-error';
        container.appendChild(err);
    }
    if (message) {
        container.classList.add('is-invalid');
        err.textContent = message;
        err.style.display = 'block';
    } else {
        container.classList.remove('is-invalid');
        err.textContent = '';
        err.style.display = 'none';
    }
}

function checkTransFormValid() {
    syncDimensions();
    const showErrors = window.__transValidationTriggered === true;

    const branch = document.querySelector('select[name="branch_id"]');
    const surface = document.querySelector('input[name="surface_application"]:checked');
    const surfaceOther = document.getElementById('surface_other');
    const dimensions = document.getElementById('dimensions_hidden');
    const layout = document.querySelector('input[name="layout"]:checked');
    const lamination = document.querySelector('input[name="lamination"]:checked');
    const qty = parseInt(document.getElementById('quantity-input').value, 10) || 0;
    const neededDate = document.getElementById('needed_date');
    const file = document.getElementById('design_file');

    const cBranch = branch?.closest('.mb-4');
    const cSurface = document.querySelector('input[name="surface_application"]')?.closest('.mb-4');
    const cDimensions = document.getElementById('dimensions_hidden')?.closest('.mb-4');
    const cLayout = document.getElementById('card-layout');
    const cLamination = document.getElementById('card-lamination');
    const cUpload = document.getElementById('card-upload');
    const cDateQty = document.getElementById('card-date-qty');

    let ok = !!branch && !!branch.value && !!surface && !!dimensions.value.trim() && !!layout && !!lamination && qty >= 1 && !!neededDate.value.trim() && !!(file && file.files.length > 0);
    if (surface && surface.value === 'Others') {
        ok = ok && !!surfaceOther.value.trim();
    }
    if (dimensionMode === 'others') {
        const w = document.getElementById('custom_width').value.trim();
        const h = document.getElementById('custom_height').value.trim();
        ok = ok && !!w && !!h;
    }

    if (showErrors) {
        setFieldError(cBranch, branch && !branch.value ? 'This field is required' : '');
        setFieldError(cSurface, !surface ? 'This field is required' : (surface && surface.value === 'Others' && !surfaceOther.value.trim() ? 'This field is required' : ''));
        setFieldError(cDimensions, !dimensions.value.trim() ? 'This field is required' : (dimensionMode === 'others' && (!document.getElementById('custom_width').value.trim() || !document.getElementById('custom_height').value.trim()) ? 'This field is required' : ''));
        setFieldError(cLayout, !layout ? 'This field is required' : '');
        setFieldError(cLamination, !lamination ? 'This field is required' : '');
        setFieldError(cUpload, !(file && file.files.length > 0) ? 'This field is required' : '');
        setFieldError(cDateQty, (!neededDate.value.trim() || qty < 1) ? 'This field is required' : '');
    } else {
        [cBranch, cSurface, cDimensions, cLayout, cLamination, cUpload, cDateQty].forEach(clearFieldError);
    }

    return ok;
}

document.getElementById('transForm').addEventListener('change', checkTransFormValid);
document.getElementById('transForm').addEventListener('input', checkTransFormValid);
document.getElementById('design_file').addEventListener('change', checkTransFormValid);
document.getElementById('quantity-input').addEventListener('input', checkTransFormValid);
document.getElementById('needed_date').addEventListener('change', checkTransFormValid);
document.getElementById('transForm').addEventListener('invalid', function(e) {
    e.preventDefault();
}, true);
checkTransFormValid();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
