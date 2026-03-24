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

        <div class="card refl-order-card">
        <form id="reflectorizedForm" class="refl-form" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="service_type" value="Reflectorized Signage">

            <div class="refl-main">
                <!-- Branch (on top) -->
                <div class="refl-field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <option value="" selected disabled>Select Branch</option>
                        <?php foreach($branches as $b): ?><option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>

                <!-- Product Type -->
                <div class="refl-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type of Reflectorized Product *</label>
                    <select name="product_type" id="refl_product_type" class="input-field refl-select-btn" required onchange="reflToggleProductFields()">
                        <option value="" selected disabled>Select Type of Reflectorized Product</option>
                        <option value="Subdivision / Gate Pass (Vehicle Sticker)">Subdivision / Gate Pass (Vehicle Sticker)</option>
                        <option value="Plate Number / Temporary Plate">Plate Number / Temporary Plate</option>
                        <option value="Custom Reflectorized Sign">Custom Reflectorized Sign</option>
                    </select>
                    <div class="refl-expand refl-tempPlateFields" style="display: none;">
                        <div class="refl-subsection">
                            <p class="refl-note mb-2">Please choose the material before proceeding.</p>
                            <div class="opt-btn-group">
                                <label class="opt-btn-wrap"><input type="radio" name="temp_plate_material" value="Acrylic" class="refl-radio" onclick="event.stopPropagation()"> <span>Acrylic</span></label>
                                <label class="opt-btn-wrap"><input type="radio" name="temp_plate_material" value="Aluminum Sheet" class="refl-radio" onclick="event.stopPropagation()"> <span>Aluminum Sheet</span></label>
                                <label class="opt-btn-wrap refl-temp-material-center"><input type="radio" name="temp_plate_material" value="Aluminum Coated (Steel Plate)" class="refl-radio" onclick="event.stopPropagation()"> <span>Aluminum Coated (Steel)</span></label>
                            </div>
                        </div>
                        <div class="refl-grid-2 mt-3">
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Plate Number *</label><input type="text" name="temp_plate_number" id="temp_plate_number" class="input-field" placeholder="Must match OR/CR" onclick="event.stopPropagation()"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">TEMPORARY PLATE text</label><input type="text" name="temp_plate_text" class="input-field refl-readonly-fixed" value="TEMPORARY PLATE" readonly tabindex="-1"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">MV File Number</label><input type="text" name="mv_file_number" class="input-field" placeholder="Optional" onclick="event.stopPropagation()"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Dealer Name</label><input type="text" name="dealer_name" class="input-field" placeholder="Optional" onclick="event.stopPropagation()"></div>
                        </div>
                        <div id="reflNeedQtyCardTemp" class="refl-need-qty-card mt-3">
                            <div class="refl-need-qty-row">
                                <div class="refl-need-qty-date">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                                    <input type="date" id="needed_date_temp" class="input-field" required min="<?php echo date('Y-m-d'); ?>" onclick="event.stopPropagation()">
                                </div>
                                <div class="refl-need-qty-qty">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <div class="refl-qty-stepper">
                                        <button type="button" onclick="reflQtyDownTemp()" class="refl-qty-btn">−</button>
                                        <input type="number" id="quantity_temp" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClampTemp()">
                                        <button type="button" onclick="reflQtyUpTemp()" class="refl-qty-btn">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="refl-expand refl-gatePassFields" style="display: none;">
                        <div class="refl-grid-2">
                            <div class="refl-col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Subdivision / Company Name *</label><input type="text" name="gate_pass_subdivision" id="gate_pass_subdivision" class="input-field" placeholder="e.g. GREEN VALLEY SUBDIVISION" onclick="event.stopPropagation()"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Gate Pass Number *</label><input type="text" name="gate_pass_number" id="gate_pass_number" class="input-field" placeholder="e.g. GP-0215" onclick="event.stopPropagation()"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Plate Number *</label><input type="text" name="gate_pass_plate" id="gate_pass_plate" class="input-field" placeholder="e.g. ABC 1234" onclick="event.stopPropagation()"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Year / Validity *</label><input type="text" name="gate_pass_year" id="gate_pass_year" class="input-field" placeholder="e.g. VALID UNTIL: 2026" onclick="event.stopPropagation()"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Type</label><select name="gate_pass_vehicle_type" class="input-field refl-select-btn" onclick="event.stopPropagation()"><option value="">Select</option><option value="Car">Car</option><option value="Motorcycle">Motorcycle</option></select></div>
                        </div>
                        <div class="refl-size-unit-card mt-3" id="reflSizeSection">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Exact Size *</label>
                            <div class="refl-size-split-row">
                                <div class="refl-size-split-field">
                                    <label class="refl-size-split-label" for="dimensions_gatepass_w">WIDTH (IN)</label>
                                    <input type="text" id="dimensions_gatepass_w" class="input-field" placeholder="e.g. 10" onclick="event.stopPropagation()">
                                </div>
                                <span class="refl-size-split-x" aria-hidden="true">×</span>
                                <div class="refl-size-split-field">
                                    <label class="refl-size-split-label" for="dimensions_gatepass_h">HEIGHT (IN)</label>
                                    <input type="text" id="dimensions_gatepass_h" class="input-field" placeholder="e.g. 12" onclick="event.stopPropagation()">
                                </div>
                            </div>
                        </div>
                        <div id="reflUploadQtyRow" class="refl-row mt-3">
                            <div id="reflLogoSection" class="refl-field" style="display: block; flex: 1; min-width: 200px;">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                                <div class="refl-file-wrap">
                                    <input type="file" name="gate_pass_logo" id="gate_pass_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field refl-file-input" onclick="event.stopPropagation()">
                                </div>
                            </div>
                        </div>
                        <div id="reflNeedQtyCard" class="refl-need-qty-card mt-3">
                            <div class="refl-need-qty-row">
                                <div class="refl-need-qty-date">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                                    <input type="date" id="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" onclick="event.stopPropagation()">
                                </div>
                                <div id="reflSharedSection" class="refl-need-qty-qty">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <div class="refl-qty-stepper">
                                        <button type="button" onclick="reflQtyDown()" class="refl-qty-btn">−</button>
                                        <input type="number" id="quantity" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClamp()">
                                        <button type="button" onclick="reflQtyUp()" class="refl-qty-btn">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="reflTypeChangeNotice" class="refl-type-change-notice" style="display:none;">
                        You changed your order type. Previous inputs for the old type were cleared.
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
                        <div class="refl-field refl-custom-card">
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
                                <label class="block text-sm font-medium text-gray-700 mb-2">Custom Size *</label>
                                <div class="refl-size-split-row">
                                    <div class="refl-size-split-field">
                                        <label class="refl-size-split-label" for="reflDimOthersW">WIDTH (IN)</label>
                                        <input type="text" id="reflDimOthersW" class="input-field" placeholder="e.g. 10" oninput="reflSyncDimOthers()">
                                    </div>
                                    <span class="refl-size-split-x" aria-hidden="true">×</span>
                                    <div class="refl-size-split-field">
                                        <label class="refl-size-split-label" for="reflDimOthersH">HEIGHT (IN)</label>
                                        <input type="text" id="reflDimOthersH" class="input-field" placeholder="e.g. 14" oninput="reflSyncDimOthers()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="refl-field refl-custom-card">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lamination</label>
                            <div class="opt-btn-group">
                                <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="With Laminate" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>With Lamination</span></label>
                                <label class="opt-btn-wrap"><input type="radio" name="laminate_option" value="Without Laminate" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>Without Lamination</span></label>
                            </div>
                        </div>
                        <div class="refl-field refl-custom-card">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Layout</label>
                            <div class="opt-btn-group">
                                <label class="opt-btn-wrap"><input type="radio" name="layout" value="With Layout" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>With Layout</span></label>
                                <label class="opt-btn-wrap"><input type="radio" name="layout" value="Without Layout" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>Without Layout</span></label>
                            </div>
                        </div>

                        <div class="refl-field refl-custom-card">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Material Brand</label>
                            <div class="opt-btn-group">
                                <label class="opt-btn-wrap"><input type="radio" name="material_type" value="Kiwalite (Japan Brand)" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>Kiwalite (Japan Brand)</span></label>
                                <label class="opt-btn-wrap"><input type="radio" name="material_type" value="3M Brand" class="refl-radio" onchange="reflUpdateOptionVisuals(this)"> <span>3M Brand</span></label>
                            </div>
                        </div>

                        <div class="refl-field refl-custom-card" style="flex: 1; min-width: 200px;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                            <div class="refl-file-wrap">
                                <input type="file" name="signage_logo" id="signage_logo" accept=".jpg,.jpeg,.png,.pdf" class="input-field refl-file-input">
                            </div>
                        </div>
                        <div id="reflNeedQtyCardCustom" class="refl-need-qty-card">
                            <div class="refl-need-qty-row">
                                <div class="refl-need-qty-date">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                                    <input type="date" id="needed_date_custom" class="input-field" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="refl-need-qty-qty">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <div class="refl-qty-stepper">
                                        <button type="button" onclick="reflQtyDownCustom()" class="refl-qty-btn">−</button>
                                        <input type="number" id="quantity_custom" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="refl-qty-input-inline" oninput="reflQtyClampCustom()">
                                        <button type="button" onclick="reflQtyUpCustom()" class="refl-qty-btn">+</button>
                                    </div>
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
                <input type="hidden" name="needed_date" id="needed_date_hidden" value="<?php echo date('Y-m-d'); ?>">
                <input type="hidden" name="other_instructions" id="notes_hidden">
                <input type="hidden" name="dimensions" id="dimensions_submit">
                <input type="hidden" name="unit" id="unit_submit" value="in">

                <div class="refl-actions">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="refl-btn-secondary">Back to Services</a>
                    <button type="button" onclick="reflSubmitOrder('add_to_cart')" class="refl-btn-secondary">Add to Cart</button>
                    <button type="button" onclick="reflSubmitOrder('buy_now')" class="refl-btn-primary">Buy Now</button>
                </div>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
let reflLastSelectedType = '';

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
    const ow = document.getElementById('reflDimOthersW');
    const oh = document.getElementById('reflDimOthersH');
    if (ow) ow.value = '';
    if (oh) oh.value = '';
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
    const w = (document.getElementById('reflDimOthersW')?.value || '').trim();
    const h = (document.getElementById('reflDimOthersH')?.value || '').trim();
    document.getElementById('reflDimensionsHidden').value = (w && h) ? (w + ' x ' + h) : '';
}

function reflTypeHasData(type) {
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass');
    const isCustom = type === 'Custom Reflectorized Sign';

    if (isTempPlate) {
        const hasMaterial = !!document.querySelector('input[name="temp_plate_material"]:checked');
        const plate = (document.getElementById('temp_plate_number')?.value || '').trim();
        const mv = (document.querySelector('input[name="mv_file_number"]')?.value || '').trim();
        const dealer = (document.querySelector('input[name="dealer_name"]')?.value || '').trim();
        const needed = (document.getElementById('needed_date_temp')?.value || '').trim();
        const qty = (document.getElementById('quantity_temp')?.value || '').trim();
        const notes = (document.getElementById('notes_shared')?.value || '').trim();
        return hasMaterial || !!plate || !!mv || !!dealer || !!needed || (!!qty && qty !== '1') || !!notes;
    }
    if (isGatePass) {
        const ids = ['gate_pass_subdivision', 'gate_pass_number', 'gate_pass_plate', 'gate_pass_year', 'dimensions_gatepass_w', 'dimensions_gatepass_h', 'needed_date'];
        const hasText = ids.some(id => ((document.getElementById(id)?.value || '').trim() !== ''));
        const hasVehicle = !!(document.querySelector('select[name="gate_pass_vehicle_type"]')?.value || '').trim();
        const hasFile = !!(document.getElementById('gate_pass_logo')?.files?.length);
        const qty = (document.getElementById('quantity')?.value || '').trim();
        const notes = (document.getElementById('notes_shared')?.value || '').trim();
        return hasText || hasVehicle || hasFile || (!!qty && qty !== '1') || !!notes;
    }
    if (isCustom) {
        const hasPreset = !!document.querySelector('#reflCustomSection input[name="dimension_preset"]:checked');
        const hasHiddenDim = !!(document.getElementById('reflDimensionsHidden')?.value || '').trim();
        const ow = (document.getElementById('reflDimOthersW')?.value || '').trim();
        const oh = (document.getElementById('reflDimOthersH')?.value || '').trim();
        const hasOthers = !!ow || !!oh;
        const hasLam = !!document.querySelector('input[name="laminate_option"]:checked');
        const hasLayout = !!document.querySelector('input[name="layout"]:checked');
        const hasMaterial = !!document.querySelector('input[name="material_type"]:checked');
        const hasFile = !!(document.getElementById('signage_logo')?.files?.length);
        const needed = (document.getElementById('needed_date_custom')?.value || '').trim();
        const qty = (document.getElementById('quantity_custom')?.value || '').trim();
        const notes = (document.getElementById('notes_custom')?.value || '').trim();
        return hasPreset || hasHiddenDim || hasOthers || hasLam || hasLayout || hasMaterial || hasFile || !!needed || (!!qty && qty !== '1') || !!notes;
    }
    return false;
}

function reflClearTypeFields(type) {
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass');
    const isCustom = type === 'Custom Reflectorized Sign';

    if (isTempPlate) {
        document.querySelectorAll('input[name="temp_plate_material"]').forEach(r => { r.checked = false; });
        ['temp_plate_number', 'needed_date_temp'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        const mv = document.querySelector('input[name="mv_file_number"]'); if (mv) mv.value = '';
        const dealer = document.querySelector('input[name="dealer_name"]'); if (dealer) dealer.value = '';
        const qty = document.getElementById('quantity_temp'); if (qty) qty.value = 1;
        const notes = document.getElementById('notes_shared'); if (notes) notes.value = '';
    }
    if (isGatePass) {
        ['gate_pass_subdivision', 'gate_pass_number', 'gate_pass_plate', 'gate_pass_year', 'dimensions_gatepass_w', 'dimensions_gatepass_h', 'needed_date'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        const vehicle = document.querySelector('select[name="gate_pass_vehicle_type"]'); if (vehicle) vehicle.value = '';
        const file = document.getElementById('gate_pass_logo'); if (file) file.value = '';
        const qty = document.getElementById('quantity'); if (qty) qty.value = 1;
        const notes = document.getElementById('notes_shared'); if (notes) notes.value = '';
    }
    if (isCustom) {
        document.querySelectorAll('#reflCustomSection input[name="dimension_preset"]').forEach(r => { r.checked = false; });
        ['reflDimensionsHidden', 'reflDimOthersW', 'reflDimOthersH', 'notes_custom', 'needed_date_custom'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        document.querySelectorAll('input[name="laminate_option"], input[name="layout"], input[name="material_type"]').forEach(r => { r.checked = false; });
        const signage = document.getElementById('signage_logo'); if (signage) signage.value = '';
        const qty = document.getElementById('quantity_custom'); if (qty) qty.value = 1;
        const othersWrap = document.getElementById('reflDimOthersWrap'); if (othersWrap) othersWrap.style.display = 'none';
        document.querySelectorAll('#reflCustomSection .refl-dim-btn').forEach(b => b.classList.remove('active'));
    }
    document.querySelectorAll('.opt-btn-wrap.active').forEach(w => {
        const input = w.querySelector('input[type="radio"]');
        if (input && !input.checked) w.classList.remove('active');
    });
}

function reflShowTypeChangeNotice() {
    const notice = document.getElementById('reflTypeChangeNotice');
    if (!notice) return;
    notice.style.display = 'block';
    clearTimeout(window.__reflTypeChangeTimer);
    window.__reflTypeChangeTimer = setTimeout(() => { notice.style.display = 'none'; }, 3500);
}

function reflClearFieldError(container) {
    if (!container) return;
    const err = container.querySelector('.field-error');
    container.classList.remove('is-invalid');
    if (err) {
        err.textContent = '';
        err.style.display = 'none';
    }
}

function reflSetFieldError(container, message) {
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

function reflCheckFormValid() {
    const showErrors = window.__reflValidationTriggered === true;
    const form = document.getElementById('reflectorizedForm');
    const type = document.getElementById('refl_product_type')?.value || '';
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass');
    const isCustom = type === 'Custom Reflectorized Sign';

    const branch = form?.querySelector('select[name="branch_id"]');
    const cBranch = branch?.closest('.refl-field');
    const cType = document.getElementById('refl_product_type')?.closest('.refl-field');

    const cTemp = document.querySelector('.refl-tempPlateFields');
    const tempMaterial = document.querySelector('input[name="temp_plate_material"]:checked');
    const tempPlateNo = (document.getElementById('temp_plate_number')?.value || '').trim();
    const tempNeed = (document.getElementById('needed_date_temp')?.value || '').trim();
    const tempQty = parseInt(document.getElementById('quantity_temp')?.value || '0', 10) || 0;

    const cGate = document.querySelector('.refl-gatePassFields');
    const gpSub = (document.getElementById('gate_pass_subdivision')?.value || '').trim();
    const gpNo = (document.getElementById('gate_pass_number')?.value || '').trim();
    const gpPlate = (document.getElementById('gate_pass_plate')?.value || '').trim();
    const gpYear = (document.getElementById('gate_pass_year')?.value || '').trim();
    const gpW = (document.getElementById('dimensions_gatepass_w')?.value || '').trim();
    const gpH = (document.getElementById('dimensions_gatepass_h')?.value || '').trim();
    const gpNeed = (document.getElementById('needed_date')?.value || '').trim();
    const gpQty = parseInt(document.getElementById('quantity')?.value || '0', 10) || 0;
    const gpFile = document.getElementById('gate_pass_logo');

    const cCustom = document.getElementById('reflCustomSection');
    const cCustomDim = document.querySelector('#reflCustomSection .refl-custom-card');
    const cCustomLam = document.querySelector('#reflCustomSection input[name="laminate_option"]')?.closest('.refl-custom-card');
    const cCustomLayout = document.querySelector('#reflCustomSection input[name="layout"]')?.closest('.refl-custom-card');
    const cCustomMat = document.querySelector('#reflCustomSection input[name="material_type"]')?.closest('.refl-custom-card');
    const cCustomUpload = document.getElementById('signage_logo')?.closest('.refl-custom-card');
    const cCustomNeedQty = document.getElementById('reflNeedQtyCardCustom');
    const customPreset = document.querySelector('#reflCustomSection input[name="dimension_preset"]:checked');
    const customW = (document.getElementById('reflDimOthersW')?.value || '').trim();
    const customH = (document.getElementById('reflDimOthersH')?.value || '').trim();
    const customDim = (document.getElementById('reflDimensionsHidden')?.value || '').trim();
    const customLam = !!document.querySelector('input[name="laminate_option"]:checked');
    const customLayout = !!document.querySelector('input[name="layout"]:checked');
    const customMat = !!document.querySelector('input[name="material_type"]:checked');
    const customFile = document.getElementById('signage_logo');
    const customNeed = (document.getElementById('needed_date_custom')?.value || '').trim();
    const customQty = parseInt(document.getElementById('quantity_custom')?.value || '0', 10) || 0;

    let ok = !!(branch && branch.value && type);
    if (isTempPlate) ok = ok && !!tempMaterial && !!tempPlateNo && !!tempNeed && tempQty >= 1;
    if (isGatePass) ok = ok && !!gpSub && !!gpNo && !!gpPlate && !!gpYear && !!gpW && !!gpH && !!gpNeed && gpQty >= 1 && !!(gpFile && gpFile.files && gpFile.files.length > 0);
    if (isCustom) {
        const othersOk = customPreset && customPreset.value === 'Others' ? (!!customW && !!customH) : true;
        ok = ok && !!customPreset && !!customDim && othersOk && customLam && customLayout && customMat && !!(customFile && customFile.files && customFile.files.length > 0) && !!customNeed && customQty >= 1;
    }

    if (showErrors) {
        reflSetFieldError(cBranch, branch && !branch.value ? 'This field is required' : '');
        reflSetFieldError(cType, !type ? 'This field is required' : '');
        reflSetFieldError(cTemp, isTempPlate && (!tempMaterial || !tempPlateNo || !tempNeed || tempQty < 1) ? 'This field is required' : '');
        reflSetFieldError(cGate, isGatePass && (!gpSub || !gpNo || !gpPlate || !gpYear || !gpW || !gpH || !gpNeed || gpQty < 1 || !(gpFile && gpFile.files && gpFile.files.length > 0)) ? 'This field is required' : '');
        const othersNeed = customPreset && customPreset.value === 'Others' && (!customW || !customH);
        reflSetFieldError(cCustomDim, isCustom && (!customPreset || !customDim || othersNeed) ? 'This field is required' : '');
        reflSetFieldError(cCustomLam, isCustom && !customLam ? 'This field is required' : '');
        reflSetFieldError(cCustomLayout, isCustom && !customLayout ? 'This field is required' : '');
        reflSetFieldError(cCustomMat, isCustom && !customMat ? 'This field is required' : '');
        reflSetFieldError(cCustomUpload, isCustom && !(customFile && customFile.files && customFile.files.length > 0) ? 'This field is required' : '');
        reflSetFieldError(cCustomNeedQty, isCustom && (!customNeed || customQty < 1) ? 'This field is required' : '');
    } else {
        [cBranch, cType, cTemp, cGate, cCustomDim, cCustomLam, cCustomLayout, cCustomMat, cCustomUpload, cCustomNeedQty].forEach(reflClearFieldError);
    }
    return ok;
}

function reflToggleProductFields() {
    const type = document.getElementById('refl_product_type')?.value || '';
    const isTempPlate = type.includes('Temporary Plate');
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass');
    const isCustom = type === 'Custom Reflectorized Sign';

    if (reflLastSelectedType && type && reflLastSelectedType !== type) {
        if (reflTypeHasData(reflLastSelectedType)) {
            reflClearTypeFields(reflLastSelectedType);
            reflShowTypeChangeNotice();
        }
    }
    reflLastSelectedType = type;

    document.querySelectorAll('.refl-expand').forEach(el => el.style.display = 'none');
    const tempPlate = document.querySelector('.refl-tempPlateFields');
    const gatePass = document.querySelector('.refl-gatePassFields');
    if (isTempPlate && tempPlate) tempPlate.style.display = 'block';
    if (isGatePass && gatePass) gatePass.style.display = 'block';

    // Shared notes for Gate Pass / Temp Plate
    document.getElementById('reflNotesSection').style.display = (isGatePass || isTempPlate) ? 'block' : 'none';

    // Custom: show custom block with fade
    const customEl = document.getElementById('reflCustomSection');
    if (isCustom) {
        customEl.style.display = 'block';
        customEl.classList.add('refl-visible');
        document.getElementById('reflNotesSection').style.display = 'none';
    } else {
        customEl.classList.remove('refl-visible');
        setTimeout(() => { customEl.style.display = 'none'; }, 250);
    }

    document.getElementById('temp_plate_number').required = isTempPlate;
    document.querySelectorAll('input[name="temp_plate_material"]').forEach(r => r.required = isTempPlate);
    const tempNeedDate = document.getElementById('needed_date_temp');
    const tempQty = document.getElementById('quantity_temp');
    if (tempNeedDate) tempNeedDate.required = isTempPlate;
    if (tempQty) tempQty.required = isTempPlate;
    ['gate_pass_subdivision','gate_pass_number','gate_pass_plate','gate_pass_year'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = isGatePass;
    });
    const gateDimW = document.getElementById('dimensions_gatepass_w');
    const gateDimH = document.getElementById('dimensions_gatepass_h');
    if (gateDimW) gateDimW.required = isGatePass;
    if (gateDimH) gateDimH.required = isGatePass;
    const customNeedDate = document.getElementById('needed_date_custom');
    const customQty = document.getElementById('quantity_custom');
    if (customNeedDate) customNeedDate.required = isCustom;
    if (customQty) customQty.required = isCustom;
    const subEl = document.getElementById('subdivision_name_input');
    if (subEl) subEl.required = false;

}

function reflQtyUp() { 
    const q = document.getElementById('quantity');
    q.value = Math.min(999, (parseInt(q.value) || 1) + 1);
    reflCheckFormValid();
}
function reflQtyDown() { 
    const q = document.getElementById('quantity');
    q.value = Math.max(1, (parseInt(q.value) || 1) - 1);
    reflCheckFormValid();
}
function reflQtyUpCustom() { 
    const q = document.getElementById('quantity_custom');
    q.value = Math.min(999, (parseInt(q.value) || 1) + 1);
    reflCheckFormValid();
}
function reflQtyDownCustom() { 
    const q = document.getElementById('quantity_custom');
    q.value = Math.max(1, (parseInt(q.value) || 1) - 1);
    reflCheckFormValid();
}
function reflQtyClamp() { 
    const i = document.getElementById('quantity');
    let v = parseInt(i.value) || 1;
    i.value = Math.min(999, Math.max(1, v));
    reflCheckFormValid();
}
function reflQtyClampCustom() {
    const i = document.getElementById('quantity_custom');
    let v = parseInt(i.value) || 1;
    i.value = Math.min(999, Math.max(1, v));
    reflCheckFormValid();
}
function reflQtyUpTemp() {
    const q = document.getElementById('quantity_temp');
    q.value = Math.min(999, (parseInt(q.value) || 1) + 1);
    reflCheckFormValid();
}
function reflQtyDownTemp() {
    const q = document.getElementById('quantity_temp');
    q.value = Math.max(1, (parseInt(q.value) || 1) - 1);
    reflCheckFormValid();
}
function reflQtyClampTemp() {
    const i = document.getElementById('quantity_temp');
    let v = parseInt(i.value) || 1;
    i.value = Math.min(999, Math.max(1, v));
    reflCheckFormValid();
}

function reflSubmitOrder(action) {
    const form = document.getElementById('reflectorizedForm');
    const type = document.getElementById('refl_product_type')?.value || '';
    const isGatePass = type.includes('Subdivision / Gate Pass') || type.includes('Gate Pass');
    const isCustom = type === 'Custom Reflectorized Sign';

    if (isCustom) {
        document.getElementById('quantity_hidden').value = document.getElementById('quantity_custom').value;
        const neededDateCustom = document.getElementById('needed_date_custom');
        if (neededDateCustom && neededDateCustom.value) {
            document.getElementById('needed_date_hidden').value = neededDateCustom.value;
        }
        document.getElementById('notes_hidden').value = document.getElementById('notes_custom').value || '';
        const preset = document.querySelector('#reflCustomSection input[name="dimension_preset"]:checked');
        if (preset && preset.value === 'Others') {
            reflSyncDimOthers();
        }
        document.getElementById('dimensions_submit').value = document.getElementById('reflDimensionsHidden').value || '';
        document.getElementById('unit_submit').value = 'in';
    } else {
        const neededDateSelected = isGatePass ? document.getElementById('needed_date') : document.getElementById('needed_date_temp');
        if (neededDateSelected && neededDateSelected.value) {
            document.getElementById('needed_date_hidden').value = neededDateSelected.value;
        }
        const gw = (document.getElementById('dimensions_gatepass_w')?.value || '').trim();
        const gh = (document.getElementById('dimensions_gatepass_h')?.value || '').trim();
        document.getElementById('dimensions_submit').value = (gw && gh) ? (gw + ' x ' + gh) : '';
        document.getElementById('unit_submit').value = 'in';
        document.getElementById('quantity_hidden').value = isGatePass ? document.getElementById('quantity').value : document.getElementById('quantity_temp').value;
        document.getElementById('notes_hidden').value = document.getElementById('notes_shared').value || '';
        const qtyVal = isGatePass ? document.getElementById('quantity').value : document.getElementById('quantity_temp').value;
        document.getElementById('quantity_gatepass').value = isGatePass ? qtyVal : '';
        document.getElementById('quantity_signage').value = '';
    }
    form.dataset.action = action;
    form.dispatchEvent(new Event('submit', { cancelable: true }));
}

document.getElementById('reflectorizedForm').addEventListener('submit', function(e) {
    e.preventDefault();
    window.__reflValidationTriggered = true;
    if (!reflCheckFormValid()) return;
    const formData = new FormData(this);
    formData.append('action', this.dataset.action || 'add_to_cart');
    fetch('api_add_to_cart_reflectorized.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.href = (this.dataset.action === 'buy_now' ? 'order_review.php?item=' + data.item_key : 'cart.php');
            else alert('Error: ' + (data.message || 'Please fill in all required fields.'));
        })
        .catch(err => { console.error(err); alert('An unexpected error occurred.'); });
});

document.getElementById('reflectorizedForm').addEventListener('invalid', function(e) {
    e.preventDefault();
}, true);
document.getElementById('reflectorizedForm').addEventListener('change', reflCheckFormValid);
document.getElementById('reflectorizedForm').addEventListener('input', reflCheckFormValid);

document.getElementById('refl_product_type')?.addEventListener('change', reflToggleProductFields);
document.addEventListener('DOMContentLoaded', function() {
    reflLastSelectedType = document.getElementById('refl_product_type')?.value || '';
    reflToggleProductFields();
    reflCheckFormValid();
});
</script>

<style>
.refl-container { max-width: 640px; margin: 0 auto; padding: 0 1rem; }
.refl-container h1 { color: #eaf6fb !important; }
#reflectorizedForm.refl-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    color-scheme: dark;
    font-family: inherit;
}
#reflectorizedForm,
#reflectorizedForm * { font-family: inherit; }
.refl-order-card.card {
    background: rgba(10, 37, 48, 0.55);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 1.25rem;
    box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35);
}
.refl-main { display: flex; flex-direction: column; gap: 1rem; }
.refl-field { min-width: 0; }
.refl-custom-inner { min-width: 0; padding: 0; background: transparent; border: 0; border-radius: 0; backdrop-filter: none; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 1rem; }
.refl-custom-inner {
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
.refl-custom-card {
    padding: 0.9rem;
    border-radius: 10px;
    border: 1px solid rgba(83, 197, 224, 0.22);
    background: rgba(8, 32, 42, 0.6);
}
.refl-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.refl-row .refl-field { flex: 1; min-width: 120px; }
.refl-top-row .refl-field { flex: 1; min-width: 140px; }
.refl-need-qty-card {
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(83, 197, 224, 0.22);
    background: rgba(8, 32, 42, 0.6);
}
.refl-need-qty-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.refl-need-qty-date { flex: 1 1 0; min-width: 0; }
.refl-need-qty-qty { flex: 1 1 0; min-width: 0; }
.refl-need-qty-qty .refl-qty-stepper { width: 100%; }
.refl-need-qty-qty .refl-qty-input-inline {
    max-width: none;
    min-width: 0;
}
.refl-size-unit-card {
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(83, 197, 224, 0.22);
    background: rgba(10, 37, 48, 0.48);
}
.refl-size-unit-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.refl-size-unit-field { flex: 1; min-width: 0; }
.refl-size-split-row {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    flex-wrap: nowrap;
}
.refl-size-split-field { flex: 1; min-width: 0; }
.refl-size-split-label {
    display: block;
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: #9fc6d9 !important;
    text-transform: uppercase;
    margin-bottom: 0.45rem;
}
.refl-size-split-x {
    flex: 0 0 auto;
    padding-bottom: 0.72rem;
    font-size: 1.15rem;
    font-weight: 600;
    color: #d2e7f1;
    line-height: 1;
    user-select: none;
}
.refl-col-span-2 { grid-column: span 2; }
.refl-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
.refl-note { font-size: 0.875rem; color: #9fc6d9; line-height: 1.4; }
.refl-hint { font-size: 0.875rem; color: #9fc6d9; margin-top: 0.25rem; }
.refl-expand {
    margin-top: 0.75rem;
    padding: 1rem;
    background: rgba(8, 32, 42, 0.65);
    border-radius: 10px;
    border: 1px solid rgba(83, 197, 224, 0.2);
}
.refl-gatePassFields .refl-grid-2 > div,
.refl-gatePassFields .refl-row .refl-field {
    padding: 0.9rem;
    border-radius: 10px;
    border: 1px solid rgba(83, 197, 224, 0.22);
    background: rgba(10, 37, 48, 0.48);
}
.refl-options-list { display: flex; flex-direction: column; gap: 0.5rem; }
.refl-option-block { display: flex; flex-direction: column; }
.refl-opt-card { width: 100%; }
.refl-temp-material-center {
    grid-column: 1 / -1;
    justify-self: center;
    width: calc(50% - 0.3rem);
}
.refl-readonly-fixed {
    pointer-events: none;
    user-select: none;
    cursor: default;
    opacity: 0.95;
}
.refl-type-change-notice {
    margin-top: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    background: rgba(83, 197, 224, 0.14);
    border: 1px solid rgba(83, 197, 224, 0.35);
    color: #bfe6f2;
    font-size: 0.875rem;
    line-height: 1.35;
}
.field-error {
    margin-top: .4rem;
    font-size: .75rem;
    color: #fca5a5;
    line-height: 1.3;
    display: block;
    width: 100%;
}
#reflectorizedForm .is-invalid {
    border-color: rgba(239, 68, 68, 0.35) !important;
    box-shadow: none !important;
}
#reflectorizedForm .is-invalid .input-field,
#reflectorizedForm .is-invalid .refl-qty-stepper {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#reflectorizedForm .is-invalid .refl-readonly-fixed.input-field {
    border-color: rgba(83, 197, 224, 0.26) !important;
}
#reflectorizedForm .is-invalid .opt-btn-wrap {
    border-color: rgba(239, 68, 68, 0.45) !important;
}

#reflectorizedForm label.block {
    font-size: 0.95rem !important;
    font-weight: 700 !important;
    color: #d9e6ef !important;
    margin-bottom: 0.55rem !important;
    line-height: 1.25;
}
#reflectorizedForm .input-field {
    min-height: 44px;
    padding: 0.72rem 0.9rem;
    border-radius: 10px;
    font-size: 0.95rem;
    width: 100%;
    box-sizing: border-box;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #e9f6fb !important;
    box-shadow: none !important;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    font-weight: 400;
    line-height: 1.25;
}
#reflectorizedForm .input-field::placeholder {
    color: #a9c1cd !important;
    font-size: 0.9rem;
}
#reflNeedQtyCard #needed_date.input-field,
#reflNeedQtyCardTemp #needed_date_temp.input-field,
#reflNeedQtyCardCustom #needed_date_custom.input-field {
    min-height: 42px;
    height: 42px;
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
}
#reflectorizedForm .input-field:focus,
#reflectorizedForm .input-field:focus-visible {
    background: rgba(16, 52, 67, 0.98) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
    outline: none !important;
}
#reflectorizedForm select.input-field option {
    background: #0a2530 !important;
    color: #f8fafc !important;
}
#reflectorizedForm select.refl-select-btn {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 3rem;
    background-image:
        linear-gradient(180deg, rgba(83, 197, 224, 0.18), rgba(50, 161, 196, 0.18)),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%23d8edf5' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat, no-repeat;
    background-position: right 0 top 0, right 0.95rem center;
    background-size: 2.4rem 100%, 14px 14px;
    cursor: pointer;
}
#reflectorizedForm input[type="radio"] { accent-color: #53c5e0; }

.opt-btn-wrap {
    min-height: 44px;
    padding: 0.65rem 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.86rem;
    text-align: center;
    line-height: 1.25;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(83, 197, 224, 0.2) !important;
    color: #d2e7f1 !important;
    transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
    gap: 0.45rem;
}
.opt-btn-wrap:hover {
    background: rgba(83, 197, 224, 0.12) !important;
    border-color: rgba(83, 197, 224, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.12);
}
.opt-btn-wrap:has(input:checked),
.opt-btn-wrap.active {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important;
    border-color: #53c5e0 !important;
    color: #f8fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.22), 0 8px 18px rgba(11, 42, 56, 0.35);
}
.opt-btn-wrap input {
    margin: 0;
    position: static;
    opacity: 1;
    pointer-events: auto;
}
.opt-btn-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.6rem; width: 100%; }
.refl-dim-presets { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; }

.refl-custom-block { overflow: hidden; opacity: 0; max-height: 0; transition: opacity 0.25s ease, max-height 0.35s ease; }
.refl-custom-block.refl-visible { opacity: 1; max-height: 2200px; }

.refl-qty-stepper {
    display: inline-flex;
    align-items: center;
    height: 42px;
    border-radius: 10px;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
    overflow: hidden;
}
.refl-qty-stepper:focus-within {
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.16);
}
.refl-qty-btn {
    flex: 0 0 36px;
    height: 42px;
    border: none;
    background: rgba(83, 197, 224, 0.12) !important;
    color: #d8edf5 !important;
    font-weight: 800;
    font-size: 1.1rem;
    cursor: pointer;
    transition: background 0.2s;
}
.refl-qty-btn:hover { background: rgba(83, 197, 224, 0.22) !important; }
.refl-qty-input-inline {
    flex: 1;
    min-width: 50px;
    max-width: 88px;
    border: none;
    text-align: center;
    font-weight: 700;
    outline: none;
    background: transparent !important;
    color: #f8fafc !important;
    height: 42px;
}
.refl-qty-input-inline::-webkit-outer-spin-button,
.refl-qty-input-inline::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.refl-qty-input-inline { -moz-appearance: textfield; appearance: textfield; }

.refl-file-wrap { padding: 0.25rem 0; }
.refl-file-wrap .refl-file-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 10px;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #e9f6fb !important;
    font-size: 0.95rem;
}

#reflectorizedForm textarea.input-field {
    overflow-y: auto;
    resize: vertical;
    min-height: 110px;
    max-height: 220px;
    max-width: 100%;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08);
}
#reflectorizedForm textarea.input-field::-webkit-scrollbar { width: 10px; }
#reflectorizedForm textarea.input-field::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.08); border-radius: 999px; }
#reflectorizedForm textarea.input-field::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.65); border-radius: 999px; border: 2px solid rgba(10, 37, 48, 0.55); }
#reflectorizedForm textarea.input-field::-webkit-scrollbar-thumb:hover { background: rgba(83, 197, 224, 0.85); }

.refl-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; padding-top: 0.5rem; }
.refl-btn-primary {
    height: 46px;
    min-width: 150px;
    padding: 0 1.15rem;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #53c5e0, #32a1c4) !important;
    color: #fff !important;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(50, 161, 196, 0.3);
    transition: all 0.2s;
}
.refl-btn-secondary {
    height: 46px;
    min-width: 150px;
    padding: 0 1.15rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(83, 197, 224, 0.28) !important;
    color: #d9e6ef !important;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.refl-btn-secondary:hover {
    background: rgba(83, 197, 224, 0.14) !important;
    border-color: rgba(83, 197, 224, 0.52) !important;
    color: #fff !important;
}
.refl-btn-primary:hover { transform: translateY(-1px); }

@media (max-width: 640px) {
    .refl-top-row { flex-direction: column; }
    .refl-top-row .refl-field { min-width: 100%; }
    .refl-need-qty-row { flex-direction: column; align-items: stretch; }
    .refl-need-qty-qty { width: 100%; }
    .refl-size-unit-row { flex-direction: column; align-items: stretch; }
    .refl-size-unit-field { width: 100%; }
    .refl-size-split-row { flex-direction: column; align-items: stretch; }
    .refl-size-split-x { align-self: center; padding: 0.35rem 0; }
    .opt-btn-group { grid-template-columns: 1fr; }
    .refl-dim-presets { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .refl-actions { flex-direction: column; align-items: stretch; }
    .refl-btn-primary, .refl-btn-secondary { width: 100%; }
}
@media (max-width: 480px) {
    .refl-grid-2 { grid-template-columns: 1fr; }
    .refl-row { flex-direction: column; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
