<?php
/**
 * Reflectorized (Subdivision Stickers / Signages) - Service Order Form
 * PrintFlow - Conditional layout for Custom Reflectorized Sign
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$page_title = 'Order Reflectorized Signage - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");

// Standard reflectorized sizes (inches)
$dimension_presets = [
    '6 x 12' => ['w' => 6, 'h' => 12],
    '9 x 12' => ['w' => 9, 'h' => 12],
    '12 x 18' => ['w' => 12, 'h' => 18],
    '18 x 24' => ['w' => 18, 'h' => 24],
    '24 x 36' => ['w' => 24, 'h' => 36],
];
?>

<div class="min-h-screen py-8">
    <div class="refl-container">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Reflectorized Signage</h1>

        <form id="reflectorizedForm" class="refl-form" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="service_type" value="Reflectorized Signage">

            <div class="refl-main">
                <!-- Branch (on top) -->
                <div class="refl-field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach($branches as $b): ?><option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>

                <!-- Product Type -->
                <div class="refl-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type of Reflectorized Product *</label>
                    <div class="refl-options-list">
                        <?php 
                        $types = [
                            'Subdivision / Gate Pass (Vehicle Sticker)', 
                            'Plate Number / Temporary Plate',
                            'Custom Reflectorized Sign'
                        ];
                        foreach($types as $type): 
                            $isTempPlate = ($type === 'Plate Number / Temporary Plate');
                            $isGatePass = ($type === 'Subdivision / Gate Pass (Vehicle Sticker)');
                        ?>
                        <div class="refl-option-block">
                            <label class="opt-btn-wrap refl-opt-card">
                                <input type="radio" name="product_type" value="<?php echo $type; ?>" class="refl-radio" required onchange="reflUpdateOptionVisuals(this); reflToggleProductFields();">
                                <span><?php echo $type; ?></span>
                            </label>
                            <?php if($isTempPlate): ?>
                            <div class="refl-expand refl-tempPlateFields" style="display: none;">
                                <div class="refl-subsection">
                                    <p class="refl-note mb-2">Please choose the material before proceeding.</p>
                                    <div class="opt-btn-group">
                                        <label class="opt-btn-wrap"><input type="radio" name="temp_plate_material" value="Acrylic" class="refl-radio" onclick="event.stopPropagation()"> <span>Acrylic</span></label>
                                        <label class="opt-btn-wrap"><input type="radio" name="temp_plate_material" value="Aluminum Sheet" class="refl-radio" onclick="event.stopPropagation()"> <span>Aluminum Sheet</span></label>
                                        <label class="opt-btn-wrap"><input type="radio" name="temp_plate_material" value="Aluminum Coated (Steel Plate)" class="refl-radio" onclick="event.stopPropagation()"> <span>Aluminum Coated (Steel)</span></label>
                                    </div>
                                </div>
                                <div class="refl-grid-2 mt-3">
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Plate Number *</label><input type="text" name="temp_plate_number" id="temp_plate_number" class="input-field" placeholder="Must match OR/CR" onclick="event.stopPropagation()"></div>
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">TEMPORARY PLATE text</label><input type="text" name="temp_plate_text" class="input-field" value="TEMPORARY PLATE" readonly onclick="event.stopPropagation()"><p class="refl-hint">Appears on design.</p></div>
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">MV File Number</label><input type="text" name="mv_file_number" class="input-field" placeholder="Optional" onclick="event.stopPropagation()"></div>
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Dealer Name</label><input type="text" name="dealer_name" class="input-field" placeholder="Optional" onclick="event.stopPropagation()"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if($isGatePass): ?>
                            <div class="refl-expand refl-gatePassFields" style="display: none;">
                                <div class="refl-grid-2">
                                    <div class="refl-col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Subdivision / Company Name *</label><input type="text" name="gate_pass_subdivision" id="gate_pass_subdivision" class="input-field" placeholder="e.g. GREEN VALLEY SUBDIVISION" onclick="event.stopPropagation()"></div>
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Gate Pass Number *</label><input type="text" name="gate_pass_number" id="gate_pass_number" class="input-field" placeholder="e.g. GP-0215" onclick="event.stopPropagation()"></div>
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Plate Number *</label><input type="text" name="gate_pass_plate" id="gate_pass_plate" class="input-field" placeholder="e.g. ABC 1234" onclick="event.stopPropagation()"></div>
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Year / Validity *</label><input type="text" name="gate_pass_year" id="gate_pass_year" class="input-field" placeholder="e.g. VALID UNTIL: 2026" onclick="event.stopPropagation()"></div>
                                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Type</label><select name="gate_pass_vehicle_type" class="input-field" onclick="event.stopPropagation()"><option value="">Select</option><option value="Car">Car</option><option value="Motorcycle">Motorcycle</option></select></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Needed Date | Exact Size | Unit (same row) -->
                <div class="refl-row refl-top-row">
                    <div class="refl-field">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date * (dd/mm/yyyy)</label>
                        <input type="date" name="needed_date" id="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>">
                        <p class="refl-hint">When you need the order ready.</p>
                    </div>
                    <div class="refl-field refl-gatepass-only" id="reflSizeSection">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Exact Size (Width × Height) *</label>
                        <input type="text" id="dimensions_gatepass" class="input-field" placeholder="e.g. 12 x 18">
                    </div>
                    <div class="refl-field refl-gatepass-only">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit *</label>
                        <select id="unit_gatepass" class="input-field">
                            <option value="ft">Feet (ft)</option>
                            <option value="in">Inches (in)</option>
                        </select>
                    </div>
                </div>

                <!-- Gate Pass: Logo & Quantity (same row) -->
                <div id="reflUploadQtyRow" class="refl-row">
                    <div id="reflLogoSection" class="refl-field refl-gatepass-only" style="display: none; flex: 1; min-width: 200px;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                        <div class="refl-file-wrap">
                            <input type="file" name="gate_pass_logo" id="gate_pass_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field refl-file-input">
                        </div>
                    </div>
                    <div id="reflSharedSection" class="refl-field" style="flex: 0 0 auto;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Required *</label>
                        <div class="refl-qty-stepper">
                            <button type="button" onclick="reflQtyDown()" class="refl-qty-btn">−</button>
                            <input type="number" id="quantity" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClamp()">
                            <button type="button" onclick="reflQtyUp()" class="refl-qty-btn">+</button>
                        </div>
                    </div>
                </div>

                <div id="reflNotesSection" class="refl-field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes_shared" rows="2" class="input-field" placeholder="Any special requests?"></textarea>
                </div>

                <!-- Custom Reflectorized Sign Section (hidden by default) -->
                <div id="reflCustomSection" class="refl-custom-block" style="display: none;">
                    <div class="refl-custom-inner">
                        <!-- Dimensions -->
                        <div class="refl-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions *</label>
                            <p class="refl-hint mb-2">Select a standard size or enter custom dimensions.</p>
                            <div class="opt-btn-group refl-dim-presets" id="reflDimPresets">
                                <?php foreach($dimension_presets as $label => $d): ?>
                                <label class="opt-btn-wrap refl-dim-btn" data-w="<?php echo $d['w']; ?>" data-h="<?php echo $d['h']; ?>">
                                    <input type="radio" name="dimension_preset" value="<?php echo $label; ?>" class="refl-radio" onchange="reflSelectDimension('<?php echo $label; ?>', <?php echo $d['w']; ?>, <?php echo $d['h']; ?>)">
                                    <span><?php echo $label; ?> in</span>
                                </label>
                                <?php endforeach; ?>
                                <label class="opt-btn-wrap refl-dim-btn" data-others="1">
                                    <input type="radio" name="dimension_preset" value="Others" class="refl-radio" onchange="reflSelectDimensionOthers()">
                                    <span>Others</span>
                                </label>
                            </div>
                            <input type="hidden" id="reflDimensionsHidden">
                            <div id="reflDimOthersWrap" class="refl-dim-others mt-3" style="display: none;">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Custom Size (Width × Height, inches)</label>
                                <input type="text" id="reflDimOthersInput" class="input-field" placeholder="e.g. 10 x 14" oninput="reflSyncDimOthers()">
                            </div>
                        </div>

                        <div class="refl-row">
                            <div class="refl-field">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Lamination</label>
                                <div class="opt-btn-group">
                                    <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="With Laminate" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>With Lamination</span></label>
                                    <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="Without Laminate" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>Without Lamination</span></label>
                                </div>
                            </div>
                            <div class="refl-field">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Layout</label>
                                <div class="opt-btn-group">
                                    <label class="opt-btn-wrap"><input type="radio" name="layout" value="With Layout" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>With Layout</span></label>
                                    <label class="opt-btn-wrap"><input type="radio" name="layout" value="Without Layout" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>Without Layout</span></label>
                                </div>
                            </div>
                        </div>

                        <div class="refl-field">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Material Brand</label>
                            <div class="opt-btn-group">
                                <label class="opt-btn-wrap"><input type="radio" name="material_type" value="Kiwalite (Japan Brand)" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>Kiwalite (Japan Brand)</span></label>
                                <label class="opt-btn-wrap"><input type="radio" name="material_type" value="3M Brand" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>3M Brand</span></label>
                            </div>
                        </div>

                        <div class="refl-row">
                            <div class="refl-field" style="flex: 1; min-width: 200px;">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                                <div class="refl-file-wrap">
                                    <input type="file" name="signage_logo" id="signage_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field refl-file-input">
                                </div>
                            </div>
                            <div class="refl-field" style="flex: 0 0 auto;">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Required *</label>
                                <div class="refl-qty-stepper">
                                    <button type="button" onclick="reflQtyDownCustom()" class="refl-qty-btn">−</button>
                                    <input type="number" id="quantity_custom" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClampCustom()">
                                    <button type="button" onclick="reflQtyUpCustom()" class="refl-qty-btn">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="refl-field">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea id="notes_custom" rows="2" class="input-field" placeholder="Any special requests?"></textarea>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="quantity_gatepass" id="quantity_gatepass">
                <input type="hidden" name="quantity_signage" id="quantity_signage">
                <input type="hidden" name="quantity" id="quantity_hidden">
                <input type="hidden" name="other_instructions" id="notes_hidden">
                <input type="hidden" name="dimensions" id="dimensions_submit">
                <input type="hidden" name="unit" id="unit_submit" value="in">

                <div class="refl-actions">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="refl-btn-secondary">Back to Services</a>
                    <button type="button" onclick="reflSubmitOrder('buy_now')" class="refl-btn-primary">Buy Now</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function reflUpdateOptionVisuals(input) {
    const name = input.name;
    document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
        const wrap = r.closest('.opt-btn-wrap');
        if (wrap) wrap.classList.toggle('active', r.checked);
    });
}

function reflSelectDimension(label, w, h) {
    document.getElementById('reflDimensionsHidden').value = label;
    document.getElementById('reflDimOthersWrap').style.display = 'none';
    document.getElementById('reflDimOthersInput').value = '';
    document.querySelectorAll('#reflCustomSection .refl-dim-btn').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector('#reflCustomSection .refl-dim-btn[data-w="' + w + '"][data-h="' + h + '"]');
    if (btn) btn.classList.add('active');
}

function reflSelectDimensionOthers() {
    document.getElementById('reflDimOthersWrap').style.display = 'block';
    document.querySelectorAll('#reflCustomSection .refl-dim-btn').forEach(b => b.classList.remove('active'));
    const others = document.querySelector('#reflCustomSection .refl-dim-btn[data-others="1"]');
    if (others) others.classList.add('active');
}

function reflSyncDimOthers() {
    const val = document.getElementById('reflDimOthersInput').value.trim();
    document.getElementById('reflDimensionsHidden').value = val;
}

function reflToggleProductFields() {
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass');
    const isCustom = type === 'Custom Reflectorized Sign';

    document.querySelectorAll('.refl-expand').forEach(el => el.style.display = 'none');
    const checkedBlock = document.querySelector('input[name="product_type"]:checked')?.closest('.refl-option-block');
    if (checkedBlock) {
        const tempPlate = checkedBlock.querySelector('.refl-tempPlateFields');
        const gatePass = checkedBlock.querySelector('.refl-gatePassFields');
        if (isTempPlate && tempPlate) tempPlate.style.display = 'block';
        if (isGatePass && gatePass) gatePass.style.display = 'block';
    }

    // Gate Pass / Temp Plate: show shared section (size for gate pass, logo, qty, notes)
    document.querySelectorAll('.refl-gatepass-only').forEach(el => {
        el.style.display = isGatePass ? (el.classList.contains('refl-row') ? 'flex' : 'block') : 'none';
    });
    document.getElementById('reflSharedSection').style.display = (isGatePass || isTempPlate) ? 'block' : 'none';
    document.getElementById('reflNotesSection').style.display = (isGatePass || isTempPlate) ? 'block' : 'none';
    const uploadQtyRow = document.getElementById('reflUploadQtyRow');
    if (uploadQtyRow) uploadQtyRow.style.display = (isGatePass || isTempPlate) ? 'flex' : 'none';

    // Custom: show custom block with fade
    const customEl = document.getElementById('reflCustomSection');
    if (isCustom) {
        customEl.style.display = 'block';
        customEl.classList.add('refl-visible');
        document.getElementById('reflSharedSection').style.display = 'none';
        document.getElementById('reflNotesSection').style.display = 'none';
    } else {
        customEl.classList.remove('refl-visible');
        setTimeout(() => { customEl.style.display = 'none'; }, 250);
    }

    document.getElementById('temp_plate_number').required = isTempPlate;
    document.querySelectorAll('input[name="temp_plate_material"]').forEach(r => r.required = isTempPlate);
    ['gate_pass_subdivision','gate_pass_number','gate_pass_plate','gate_pass_year'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = isGatePass;
    });
    const gateDim = document.getElementById('dimensions_gatepass');
    if (gateDim) gateDim.required = isGatePass;
    const subEl = document.getElementById('subdivision_name_input');
    if (subEl) subEl.required = false;

}

function reflQtyUp() { 
    const q = document.getElementById('quantity');
    q.value = Math.min(999, (parseInt(q.value) || 1) + 1);
}
function reflQtyDown() { 
    const q = document.getElementById('quantity');
    q.value = Math.max(1, (parseInt(q.value) || 1) - 1);
}
function reflQtyUpCustom() { 
    const q = document.getElementById('quantity_custom');
    q.value = Math.min(999, (parseInt(q.value) || 1) + 1);
}
function reflQtyDownCustom() { 
    const q = document.getElementById('quantity_custom');
    q.value = Math.max(1, (parseInt(q.value) || 1) - 1);
}
function reflQtyClamp() { 
    const i = document.getElementById('quantity');
    let v = parseInt(i.value) || 1;
    i.value = Math.min(999, Math.max(1, v));
}
function reflQtyClampCustom() {
    const i = document.getElementById('quantity_custom');
    let v = parseInt(i.value) || 1;
    i.value = Math.min(999, Math.max(1, v));
}

function reflSubmitOrder(action) {
    const form = document.getElementById('reflectorizedForm');
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass');
    const isCustom = type === 'Custom Reflectorized Sign';

    if (isCustom) {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_custom').value;
        document.getElementById('notes_hidden').value = document.getElementById('notes_custom').value || '';
        const preset = document.querySelector('#reflCustomSection input[name="dimension_preset"]:checked');
        if (preset && preset.value === 'Others') {
            document.getElementById('reflDimensionsHidden').value = document.getElementById('reflDimOthersInput').value || '';
        }
        document.getElementById('dimensions_submit').value = document.getElementById('reflDimensionsHidden').value || '';
        document.getElementById('unit_submit').value = 'in';
    } else {
        document.getElementById('dimensions_submit').value = document.getElementById('dimensions_gatepass')?.value || '';
        document.getElementById('unit_submit').value = document.getElementById('unit_gatepass')?.value || 'in';
        document.getElementById('quantity_hidden').value = document.getElementById('quantity').value;
        document.getElementById('notes_hidden').value = document.getElementById('notes_shared').value || '';
        const qtyVal = document.getElementById('quantity').value;
        document.getElementById('quantity_gatepass').value = isGatePass ? qtyVal : '';
        document.getElementById('quantity_signage').value = '';
    }
    form.dataset.action = action;
    form.dispatchEvent(new Event('submit', { cancelable: true }));
}

document.getElementById('reflectorizedForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const type = document.querySelector('input[name="product_type"]:checked')?.value || '';
    const isCustom = type === 'Custom Reflectorized Sign';
    if (isCustom) {
        const dimVal = document.getElementById('reflDimensionsHidden').value;
        if (!dimVal) {
            alert('Please select a dimension or enter custom size.');
            return;
        }
        const preset = document.querySelector('#reflCustomSection input[name="dimension_preset"]:checked');
        if (!preset) {
            alert('Please select a dimension size.');
            return;
        }
        if (preset.value === 'Others' && !document.getElementById('reflDimOthersInput').value.trim()) {
            alert('Please enter custom dimensions.');
            return;
        }
    }
    const formData = new FormData(this);
    formData.append('action', this.dataset.action || 'buy_now');
    fetch('api_add_to_cart_reflectorized.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.href = (this.dataset.action === 'buy_now' ? 'order_review.php?item=' + data.item_key : 'cart.php');
            else alert('Error: ' + (data.message || 'Please fill in all required fields.'));
        })
        .catch(err => { console.error(err); alert('An unexpected error occurred.'); });
});

document.querySelectorAll('input[name="product_type"]').forEach(r => r.addEventListener('change', reflToggleProductFields));
document.addEventListener('DOMContentLoaded', function() {
    reflToggleProductFields();
});
</script>

<style>
.refl-container { max-width: 640px; margin: 0 auto; padding: 0 1rem; }
.refl-main { display: flex; flex-direction: column; gap: 1.25rem; }
.refl-field { min-width: 0; }
.refl-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.refl-row .refl-field { flex: 1; min-width: 120px; }
.refl-top-row .refl-field { flex: 1; min-width: 140px; }
@media (max-width: 640px) {
    .refl-top-row { flex-direction: column; }
    .refl-top-row .refl-field { min-width: 100%; }
}
.refl-col-span-2 { grid-column: span 2; }
.refl-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
@media (max-width: 480px) { .refl-grid-2 { grid-template-columns: 1fr; } }
.refl-note { font-size: 0.8rem; color: #6b7280; line-height: 1.4; }
.refl-hint { font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem; }
.refl-expand { margin-top: 0.75rem; padding: 1rem; background: #f8fafc; border-radius: 8px; }
.refl-options-list { display: flex; flex-direction: column; gap: 0.5rem; }
.refl-option-block { display: flex; flex-direction: column; }
.refl-opt-card { width: 100%; }

.opt-btn-wrap { padding: 0.55rem 1rem; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.875rem; color: #374151; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.4rem; }
.opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn-wrap:has(input:checked), .opt-btn-wrap.active { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: #fff; }
.opt-btn-wrap input { margin: 0; position: absolute; opacity: 0; pointer-events: none; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }

.refl-custom-block { overflow: hidden; opacity: 0; max-height: 0; transition: opacity 0.25s ease, max-height 0.35s ease; }
.refl-custom-block.refl-visible { opacity: 1; max-height: 2000px; }
.refl-custom-inner { padding: 1.25rem; background: #f8fafc; border-radius: 10px; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.refl-dim-presets { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.refl-dim-others { }

.refl-qty-stepper { display: inline-flex; align-items: center; height: 42px; border: 2px solid #d1d5db; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.2s; }
.refl-qty-stepper:focus-within { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.refl-qty-btn { flex: 0 0 40px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 700; font-size: 1.1rem; cursor: pointer; transition: background 0.2s; }
.refl-qty-btn:hover { background: #e5e7eb; }
.refl-qty-input-inline { flex: 1; min-width: 50px; max-width: 80px; border: none; text-align: center; font-weight: 700; outline: none; background: transparent; }
.refl-toggle-row { display: flex; flex-wrap: wrap; gap: 1rem; }
.refl-toggle { display: flex; align-items: center; gap: 0.6rem; cursor: pointer; }
.refl-toggle-label { font-size: 0.875rem; font-weight: 600; color: #374151; }
.refl-checkbox { position: absolute; opacity: 0; }
.refl-slider { width: 42px; height: 22px; background: #d1d5db; border-radius: 11px; transition: 0.25s; position: relative; }
.refl-slider::after { content: ''; position: absolute; width: 16px; height: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.2); transition: 0.25s; }
.refl-checkbox:checked + .refl-slider { background: #0a2530; }
.refl-checkbox:checked + .refl-slider::after { transform: translateX(20px); }

.refl-file-wrap { padding: 0.25rem 0; }
.refl-file-wrap .refl-file-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; background: #f9fafb; font-size: 0.875rem; }
.refl-file-wrap .refl-file-input:hover { background: #f3f4f6; }
.refl-file-wrap .refl-file-input:focus { outline: none; border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.1); }

.refl-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; padding-top: 0.5rem; }
.refl-btn-primary { height: 48px; min-width: 140px; padding: 0 1.25rem; background: #0a2530; color: #fff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 0.02em; transition: all 0.2s; }
.refl-btn-primary:hover { background: #0d3038; transform: translateY(-1px); }
.refl-btn-secondary { height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none; transition: all 0.2s; }
.refl-btn-secondary:hover { background: #f1f5f9; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
