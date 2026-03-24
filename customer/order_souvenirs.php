<?php
/**
 * Souvenirs - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();

$page_title = 'Order Souvenirs - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$souvenir_type_options = ['Mug', 'Keychain', 'Tote Bag', 'Pen', 'Tumbler', 'T-Shirt'];
?>
<div class="min-h-screen py-8 souvenir-order-page">
    <div class="container mx-auto px-4 souvenir-order-container" style="max-width: 640px;">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold souvenir-page-title">Souvenirs</h1>
        </div>
        <div class="card p-6 souvenir-order-card">
            <form id="souvenirForm" method="POST" enctype="multipart/form-data" class="souvenir-order-form" novalidate>
                <?php echo csrf_field(); ?>

                <!-- Branch -->
                <div class="mb-4" id="card-branch-souvenir">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" id="souvenir_branch_id" class="input-field w-full">
                        <option value="" selected disabled>Please select</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Mirrors order_tshirt.php #shirt-type-section (same classes, no per-radio onchange; IDs/names are souvenir-specific) -->
                <div class="mb-4" id="souvenir-type-section">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Souvenir Type <span id="souvenir-type-required-mark">*</span></label>
                    <div class="option-grid option-grid-3x2 souvenir-type-grid">
                        <?php foreach ($souvenir_type_options as $st): ?>
                        <label class="opt-btn-wrap"><input type="radio" name="souvenir_type" value="<?php echo htmlspecialchars($st); ?>"> <span><?php echo htmlspecialchars($st); ?></span></label>
                        <?php endforeach; ?>
                        <label class="opt-btn-wrap opt-btn-others"><input type="radio" name="souvenir_type" value="Others"> <span>Others</span></label>
                    </div>
                    <div id="souvenir-type-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="souvenir_type_other" id="souvenir_type_other" class="input-field" placeholder="Enter custom shirt type">
                    </div>
                </div>

                <!-- Custom Print -->
                <div class="mb-4" id="card-custom-print-souvenir">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Custom Print? *</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="custom_print" value="No"> <span>No</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="custom_print" value="Yes"> <span>Yes – I have a design</span></label>
                    </div>
                </div>

                <!-- Design Upload -->
                <div class="mb-4" id="card-upload-souvenir">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Design <span id="upload-asterisk" class="souvenir-upload-asterisk" style="display: none;" aria-hidden="true">*</span>
                        (JPG, PNG, PDF – max 5MB)
                        <span id="upload-hint" class="font-normal normal-case text-sm ml-1 text-gray-400">(Optional)</span>
                    </label>
                    <div id="souvenir-upload-shell" class="souvenir-upload-shell">
                        <input type="file" name="design_file" id="design_file"
                               accept=".jpg,.jpeg,.png,.pdf"
                               class="input-field souvenir-input-h souvenir-file-input">
                    </div>
                </div>

                <!-- Lamination -->
                <div class="mb-4" id="card-lamination-souvenir">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lamination *</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="lamination" value="With Lamination"> <span>With Lamination</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="lamination" value="Without Lamination"> <span>Without Lamination</span></label>
                    </div>
                </div>

                <!-- Needed Date + Quantity -->
                <div class="mb-4 need-qty-card souvenir-need-qty-card" id="card-date-qty-souvenir">
                    <div class="need-qty-row">
                        <div class="need-qty-date souvenir-field-inner" id="souvenir-wrap-date" style="min-width:0;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field souvenir-input-h souvenir-date-full" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="need-qty-qty souvenir-field-inner" id="souvenir-wrap-qty" style="min-width:0;">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="sticker-qty-stepper sticker-qty-stepper-wide souvenir-qty-stepper">
                                <button type="button" onclick="souvenirQtyDown()">−</button>
                                <input type="number" id="souvenir-qty" name="quantity" min="1" max="999" required value="<?php echo max(1, (int)($_GET['qty'] ?? 1)); ?>" oninput="souvenirQtyClamp()">
                                <button type="button" onclick="souvenirQtyUp()">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes / Special Instructions</label>
                    <textarea name="notes" rows="3" class="input-field w-full"
                              placeholder="e.g., preferred colors, text to print, placement..."></textarea>
                </div>

                <!-- Buttons -->
                <div class="tshirt-actions-row">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                    <button type="button" onclick="submitSouvenirOrder('add_to_cart')" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                    <button type="button" onclick="submitSouvenirOrder('buy_now')" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function souvenirUpdateOpt(input) {
    const name = input.name;
    document.querySelectorAll('input[name="' + name + '"]').forEach(function(r) {
        const wrap = r.closest('.opt-btn-wrap');
        if (wrap) { wrap.classList.remove('active'); if (r.checked) wrap.classList.add('active'); }
    });
    if (input.name === 'souvenir_type') {
        toggleSouvenirTypeOther();
    }
}

function toggleSouvenirTypeOther() {
    var checked = document.querySelector('#souvenirForm input[name="souvenir_type"]:checked');
    var wrap = document.getElementById('souvenir-type-other-wrap');
    var inp = document.getElementById('souvenir_type_other');
    var show = checked && checked.value === 'Others';
    if (wrap) wrap.style.display = show ? 'block' : 'none';
    if (!show && inp) inp.value = '';
    if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
}

function clearSouvenirFieldError(container) {
    if (!container) return;
    var err = container.querySelector('.field-error');
    container.classList.remove('is-invalid');
    if (err) { err.textContent = ''; err.style.display = 'none'; }
}

function setSouvenirFieldError(container, message) {
    if (!container) return;
    var err = container.querySelector('.field-error');
    if (message) {
        if (!err) { err = document.createElement('div'); err.className = 'field-error'; container.appendChild(err); }
        container.classList.add('is-invalid');
        err.textContent = message;
        err.style.display = 'block';
    } else {
        container.classList.remove('is-invalid');
        if (err) { err.textContent = ''; err.style.display = 'none'; }
    }
}

function checkSouvenirFormValid() {
    var show = window.__souvenirValidationTriggered === true;
    var branchSel = document.getElementById('souvenir_branch_id');
    var typeEl = document.querySelector('#souvenirForm input[name="souvenir_type"]:checked');
    var typeOtherInp = document.getElementById('souvenir_type_other');
    var typeOtherVal = (typeOtherInp && typeOtherInp.value) ? typeOtherInp.value.trim() : '';
    var typeOtherLen = typeOtherVal.length;
    var nd = document.getElementById('needed_date');
    var qtyEl = document.getElementById('souvenir-qty');
    var qty = parseInt(qtyEl && qtyEl.value, 10) || 0;
    var file = document.getElementById('design_file');
    var customEl = document.querySelector('input[name="custom_print"]:checked');
    var lamEl = document.querySelector('input[name="lamination"]:checked');
    var customYes = customEl && customEl.value === 'Yes';

    var okBranch = branchSel && branchSel.value !== '';
    var okType = !!typeEl && (typeEl.value !== 'Others' || (typeOtherLen >= 1 && typeOtherLen <= 50));
    var okCustom = !!customEl;
    var okLam = !!lamEl;
    var okDate = false;
    if (nd && nd.value) {
        var d = new Date(nd.value + 'T12:00:00');
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        if (d >= today) okDate = true;
    }
    var okQty = qty >= 1 && qty <= 999;
    var okFile = !customYes || (file && file.files && file.files.length > 0);

    var cBranch = document.getElementById('card-branch-souvenir');
    var cType = document.getElementById('souvenir-type-section');
    var cCustom = document.getElementById('card-custom-print-souvenir');
    var cLam = document.getElementById('card-lamination-souvenir');
    var cUpload = document.getElementById('card-upload-souvenir');
    var cDate = document.getElementById('souvenir-wrap-date');
    var cQty = document.getElementById('souvenir-wrap-qty');

    var reqMsg = 'This field is required';
    if (show) {
        setSouvenirFieldError(cBranch, !okBranch ? reqMsg : '');
        setSouvenirFieldError(cType, !okType ? reqMsg : '');
        setSouvenirFieldError(cCustom, !okCustom ? reqMsg : '');
        setSouvenirFieldError(cLam, !okLam ? reqMsg : '');
        setSouvenirFieldError(cUpload, !okFile ? reqMsg : '');
        setSouvenirFieldError(cDate, !okDate ? reqMsg : '');
        setSouvenirFieldError(cQty, !okQty ? reqMsg : '');
    } else {
        [cBranch, cType, cCustom, cLam, cUpload, cDate, cQty].forEach(clearSouvenirFieldError);
    }
    return okBranch && okType && okCustom && okLam && okDate && okQty && okFile;
}

function toggleDesignUpload() {
    const checked = document.querySelector('input[name="custom_print"]:checked');
    const isYes = checked && checked.value === 'Yes';
    const hint = document.getElementById('upload-hint');
    const fileInput = document.getElementById('design_file');
    const shell = document.getElementById('souvenir-upload-shell');
    const asterisk = document.getElementById('upload-asterisk');

    if (isYes) {
        hint.textContent = '(Required)';
        hint.style.color = '#ef4444';
        hint.style.fontWeight = '700';
        fileInput.required = true;
        if (asterisk) { asterisk.style.display = 'inline'; asterisk.setAttribute('aria-hidden', 'false'); }
        if (shell) {
            shell.classList.add('souvenir-upload-shell--required');
        }
    } else {
        hint.textContent = '(Optional)';
        hint.style.color = '#9ca3af';
        hint.style.fontWeight = 'normal';
        fileInput.required = false;
        if (asterisk) { asterisk.style.display = 'none'; asterisk.setAttribute('aria-hidden', 'true'); }
        if (shell) {
            shell.classList.remove('souvenir-upload-shell--required');
        }
    }
    if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
}

function souvenirQtyClamp() {
    const input = document.getElementById('souvenir-qty');
    if (!input) return;
    let v = parseInt(input.value, 10);
    if (!v || v < 1) v = 1;
    if (v > 999) v = 999;
    input.value = v;
    if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
}

function souvenirQtyUp() {
    const input = document.getElementById('souvenir-qty');
    if (!input) return;
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.min(v + 1, 999);
    if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
}

function souvenirQtyDown() {
    const input = document.getElementById('souvenir-qty');
    if (!input) return;
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.max(v - 1, 1);
    if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
}

function submitSouvenirOrder(action) {
    const form = document.getElementById('souvenirForm');
    form.dataset.action = action;
    const event = new Event('submit', { cancelable: true });
    form.dispatchEvent(event);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#souvenirForm .opt-btn-wrap').forEach(function(w) {
        if (w.querySelector('input:checked')) w.classList.add('active');
    });
    toggleSouvenirTypeOther();
    toggleDesignUpload();
});

var souvenirFormEl = document.getElementById('souvenirForm');
if (souvenirFormEl) {
    souvenirFormEl.addEventListener('invalid', function(e) { e.preventDefault(); }, true);
    souvenirFormEl.addEventListener('change', function(e) {
        var t = e.target;
        if (t && t.type === 'radio') {
            if (t.name === 'custom_print') {
                toggleDesignUpload();
            }
            souvenirUpdateOpt(t);
        }
        if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
    });
    souvenirFormEl.addEventListener('input', function() {
        if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
    });
    souvenirFormEl.addEventListener('submit', function(e) {
        e.preventDefault();
        window.__souvenirValidationTriggered = true;
        if (!checkSouvenirFormValid()) {
            return;
        }
        const action = this.dataset.action || 'add_to_cart';
        const buttons = this.querySelectorAll('button');
        buttons.forEach(btn => btn.disabled = true);

        const formData = new FormData(this);
        formData.append('action', action);

        fetch('api_add_to_cart_souvenirs.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (action === 'buy_now') {
                    window.location.href = 'order_review.php?item=' + data.item_key;
                } else {
                    window.location.href = 'cart.php';
                }
            } else {
                alert(data.message);
                buttons.forEach(btn => btn.disabled = false);
            }
        })
        .catch(err => {
            alert('An error occurred. Please try again.');
            console.error(err);
            buttons.forEach(btn => btn.disabled = false);
        });
    });
}
var souvenirFileEl = document.getElementById('design_file');
if (souvenirFileEl) {
    souvenirFileEl.addEventListener('change', function() {
        if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
    });
}
var souvenirTypeOtherEl = document.getElementById('souvenir_type_other');
if (souvenirTypeOtherEl) {
    souvenirTypeOtherEl.addEventListener('input', function() {
        if (this.value.length > 50) this.value = this.value.slice(0, 50);
        if (window.__souvenirValidationTriggered) checkSouvenirFormValid();
    });
}

</script>

<style>
.souvenir-order-page .souvenir-page-title {
    color: #eaf6fb !important;
}
.souvenir-order-card.card {
    background: rgba(10, 37, 48, 0.55);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 1.25rem;
    box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35);
}
#souvenirForm.souvenir-order-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    color-scheme: dark;
}
/* Section cards — same contract as #tshirtForm .mb-4 on order_tshirt.php */
#souvenirForm .mb-4 {
    margin-bottom: 0 !important;
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
#souvenirForm label.block {
    font-size: 0.95rem !important;
    font-weight: 700 !important;
    color: #d9e6ef !important;
    margin-bottom: 0.55rem !important;
}
#souvenirForm label .text-gray-400 {
    color: #9fc6d9 !important;
}
#souvenirForm .field-error {
    margin-top: 0.4rem;
    font-size: 0.75rem;
    color: #fca5a5;
    line-height: 1.3;
    display: block;
    width: 100%;
}
#souvenirForm .mb-4.is-invalid,
#souvenirForm .need-qty-card.is-invalid {
    border-color: rgba(239, 68, 68, 0.35) !important;
    box-shadow: none !important;
}
#souvenirForm .souvenir-field-inner.is-invalid {
    border-radius: 8px;
}
#souvenirForm .mb-4.is-invalid .input-field,
#souvenirForm .need-qty-card.is-invalid .input-field {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#souvenirForm .souvenir-field-inner.is-invalid .input-field.souvenir-date-full {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#souvenirForm .souvenir-field-inner.is-invalid .sticker-qty-stepper {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#souvenirForm .mb-4.is-invalid .souvenir-upload-shell {
    border-color: rgba(239, 68, 68, 0.55) !important;
    box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.25);
}
#souvenirForm .mb-4.is-invalid .opt-btn-wrap {
    border-color: rgba(239, 68, 68, 0.55) !important;
}
#souvenirForm .input-field {
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
}
#souvenirForm .input-field::placeholder {
    color: #a9c1cd !important;
}
#souvenirForm .input-field:focus,
#souvenirForm .input-field:focus-visible {
    background: rgba(16, 52, 67, 0.98) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
    outline: none !important;
}
#souvenirForm select.input-field option {
    background: #0a2530 !important;
    color: #f8fafc !important;
}
#souvenirForm select.input-field option:hover,
#souvenirForm select.input-field option:focus {
    background: #53c5e0 !important;
    color: #06232c !important;
}
#souvenirForm select.input-field option:checked {
    background: #53c5e0 !important;
    color: #06232c !important;
}
#souvenirForm .input-field[type="date"]::-webkit-calendar-picker-indicator {
    filter: brightness(0) invert(1);
    opacity: 1;
    cursor: pointer;
}
#souvenirForm .input-field.souvenir-input-h:not(.souvenir-file-input) {
    min-height: 42px !important;
    height: 42px !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}
.souvenir-date-full { width: 100%; }
.souvenir-file-input {
    padding: 0.5rem 0.65rem !important;
    height: auto !important;
    min-height: 44px;
    line-height: 1.35;
}
#souvenirForm .souvenir-upload-shell {
    border: 1px solid rgba(83, 197, 224, 0.26);
    border-radius: 10px;
    padding: 0.35rem 0.5rem;
    background: rgba(13, 43, 56, 0.92);
    transition: border-color 0.2s, box-shadow 0.2s;
}
#souvenirForm .souvenir-upload-shell--required {
    border-color: rgba(239, 68, 68, 0.45);
    box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.2);
}
#souvenirForm .souvenir-upload-shell .souvenir-file-input {
    width: 100%;
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    min-height: 40px;
}
#souvenirForm .souvenir-upload-shell .souvenir-file-input:focus,
#souvenirForm .souvenir-upload-shell .souvenir-file-input:focus-visible {
    outline: none !important;
    box-shadow: none !important;
}
#souvenirForm .souvenir-upload-shell .souvenir-file-input::file-selector-button,
#souvenirForm .souvenir-upload-shell .souvenir-file-input::-webkit-file-upload-button {
    margin-right: 0.85rem;
    padding: 0.45rem 1rem;
    border: none;
    border-radius: 6px;
    background: #fff !important;
    color: #0a2530 !important;
    font-weight: 700;
    font-size: 0.8rem;
    cursor: pointer;
    font-family: inherit;
}
#souvenirForm .souvenir-upload-shell .souvenir-file-input::file-selector-button:hover,
#souvenirForm .souvenir-upload-shell .souvenir-file-input::-webkit-file-upload-button:hover {
    background: #f1f5f9 !important;
}
.souvenir-need-qty-card .need-qty-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.souvenir-need-qty-card .need-qty-date { flex: 1; min-width: 0; }
.souvenir-need-qty-card .need-qty-qty { flex: 1; min-width: 0; }
.souvenir-need-qty-card .need-qty-qty .sticker-qty-stepper-wide { width: 100%; max-width: 100%; }
.souvenir-need-qty-card .need-qty-qty .sticker-qty-stepper-wide input { max-width: none; flex: 1; }
.sticker-qty-stepper {
    display: inline-flex;
    align-items: center;
    width: 110px;
    height: 42px;
    border: 1px solid rgba(83, 197, 224, 0.24);
    border-radius: 10px;
    overflow: hidden;
    background: rgba(13, 43, 56, 0.92);
    box-sizing: border-box;
}
.sticker-qty-stepper * { box-sizing: border-box; }
.sticker-qty-stepper button {
    flex: 0 0 36px;
    height: 42px;
    border: none;
    background: rgba(83, 197, 224, 0.12);
    color: #d8edf5;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sticker-qty-stepper button:hover { background: rgba(83, 197, 224, 0.22); }
.sticker-qty-stepper input {
    flex: 1;
    min-width: 36px;
    border: none;
    text-align: center;
    font-weight: 700;
    font-size: 0.875rem;
    outline: none;
    background: rgba(13, 43, 56, 0.92);
    color: #f8fafc;
    padding: 0 4px;
    height: 42px;
}
#souvenir-qty::-webkit-outer-spin-button,
#souvenir-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
#souvenir-qty { -moz-appearance: textfield; appearance: textfield; }
/* option-grid / radios — aligned with order_tshirt.php (base + redesign gaps/columns) */
#souvenirForm .option-grid {
    display: grid;
    gap: 0.5rem;
    width: 100%;
}
#souvenirForm .option-grid-3x2 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.6rem;
}
#souvenirForm .souvenir-type-grid .opt-btn-others {
    grid-column: 2;
}
#souvenirForm .opt-btn-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.6rem;
    width: 100%;
}
#souvenirForm .opt-btn-wrap {
    min-height: 44px;
    padding: 0.65rem 0.75rem;
    display: flex;
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
}
#souvenirForm .opt-btn-wrap:hover {
    background: rgba(83, 197, 224, 0.12) !important;
    border-color: rgba(83, 197, 224, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.12);
}
#souvenirForm .opt-btn-wrap:has(input:checked),
#souvenirForm .opt-btn-wrap.active {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important;
    border-color: #53c5e0 !important;
    color: #f8fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.22), 0 8px 18px rgba(11, 42, 56, 0.35);
}
#souvenirForm input[type="radio"] {
    accent-color: #53c5e0;
}
#souvenirForm .opt-btn-wrap input {
    margin-right: 0.5rem;
}
.tshirt-actions-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
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
    font-size: 0.9rem;
    font-weight: 700;
    transition: all 0.2s;
    box-sizing: border-box;
}
.tshirt-btn-secondary {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(83, 197, 224, 0.28) !important;
    color: #d9e6ef !important;
}
.tshirt-btn-secondary:hover {
    background: rgba(83, 197, 224, 0.14) !important;
    border-color: rgba(83, 197, 224, 0.52) !important;
    color: #fff !important;
}
.tshirt-btn-primary {
    border: none;
    background: linear-gradient(135deg, #53c5e0, #32a1c4) !important;
    color: #fff !important;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(50, 161, 196, 0.3);
}
.tshirt-btn:active {
    transform: translateY(1px) scale(0.99);
}
@media (max-width: 640px) {
    #souvenirForm .option-grid-3x2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    #souvenirForm .souvenir-type-grid .opt-btn-others {
        grid-column: 1 / -1;
        justify-self: center;
        width: calc(50% - 0.3rem);
        max-width: 100%;
    }
    .souvenir-need-qty-card .need-qty-row {
        flex-direction: column;
        align-items: stretch;
    }
    .tshirt-actions-row {
        flex-direction: column;
        align-items: stretch;
    }
    .tshirt-btn {
        width: 100%;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
