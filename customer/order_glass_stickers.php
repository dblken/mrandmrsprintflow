<?php
/**
 * Glass & Wall Sticker Printing - Service Order Form
 * PrintFlow - Service-Based Ordering
 * Simplified flow: Dimensions/Coverage, Surface Type, Installation, File Upload, Quantity, Needed Date
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';
$addr_api = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/api_address_public.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $unit = trim($_POST['unit'] ?? 'ft');
    $surface_type = trim($_POST['surface_type'] ?? '');
    $surface_other = trim($_POST['surface_type_other'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $installation = trim($_POST['installation'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $province = trim($_POST['install_province'] ?? '');
    $city = trim($_POST['install_city'] ?? '');
    $barangay = trim($_POST['install_barangay'] ?? '');
    $street = trim($_POST['install_street'] ?? '');

    $surface_display = ($surface_type === 'Others' && $surface_other) ? $surface_other : $surface_type;

    if (empty($width) || empty($height) || $quantity < 1) {
        $error = 'Please fill in Dimensions and Quantity.';
    } elseif (empty($surface_type)) {
        $error = 'Please select Surface Type.';
    } elseif ($surface_type === 'Others' && empty($surface_other)) {
        $error = 'Please specify your surface type when Others is selected.';
    } elseif (empty($lamination)) {
        $error = 'Please select Lamination.';
    } elseif (empty($needed_date)) {
        $error = 'Please select when you need the order.';
    } elseif ($installation === 'With Installation' && (empty($province) || empty($city) || empty($barangay) || empty($street))) {
        $error = 'Please complete the installation address (Province, City/Municipality, Barangay, Street/Purok).';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'glass_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = __DIR__ . '/../uploads/temp/' . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $w = (float)$width;
                $h = (float)$height;
                $area = $w * $h;
                if ($unit === 'in') $area = $area / 144;
                $unit_price = 45.00;
                $base_price = $area * $unit_price * $quantity;

                $installation_fee = 0;
                if ($installation === 'With Installation') {
                    $installation_fee = 500 + ($area * 15);
                }

                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'product_id' => 11,
                    'name' => 'Glass & Wall Sticker Printing',
                    'price' => $base_price + $installation_fee,
                    'quantity' => $quantity,
                    'category' => 'Glass & Wall Sticker Printing',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'width' => $width,
                        'height' => $height,
                        'unit' => $unit,
                        'surface_type' => $surface_display,
                        'lamination' => $lamination,
                        'installation' => $installation,
                        'installation_fee' => $installation_fee,
                        'install_province' => $province,
                        'install_city' => $city,
                        'install_barangay' => $barangay,
                        'install_street' => $street,
                        'needed_date' => $needed_date,
                        'notes' => $notes
                    ]
                ];

                if (isset($_POST['buy_now'])) {
                    redirect("order_review.php?item=" . urlencode($item_key));
                } else {
                    redirect("cart.php");
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

$page_title = 'Order Glass & Wall Sticker - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 glass-order-container">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Glass & Wall Sticker Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="card glass-form-card">
            <form action="" method="POST" enctype="multipart/form-data" id="glassForm">
                <?php echo csrf_field(); ?>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1. Dimensions / Coverage (Feet only) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dimensions / Coverage *</label>
                    <p class="dim-feet-note">(Values are in feet)</p>
                    <div class="option-grid option-grid-dim">
                        <button type="button" class="opt-btn" data-width="2" data-height="3" onclick="selectDimension(2, 3, event)">2×3</button>
                        <button type="button" class="opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6</button>
                        <button type="button" class="opt-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)">6×8</button>
                        <button type="button" class="opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                    </div>
                    <input type="hidden" name="width" id="width_hidden">
                    <input type="hidden" name="height" id="height_hidden">
                    <input type="hidden" name="unit" value="ft">
                    <div id="dim-others-inputs" class="dim-others-row" style="display: none; margin-top: 1rem;">
                        <div>
                            <label class="dim-label">Custom Width (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_width" class="input-field" placeholder="e.g. 10">
                        </div>
                        <div class="dim-sep">×</div>
                        <div>
                            <label class="dim-label">Custom Height (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_height" class="input-field" placeholder="e.g. 12">
                        </div>
                    </div>
                </div>

                <!-- 2. Surface Type (3×2 grid) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Surface Type *</label>
                    <div class="option-grid option-grid-3x2">
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Glass (Window/Door/Storefront)"> <span>Glass</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Wall (Painted/Concrete)"> <span>Wall</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Frosted Glass"> <span>Frosted Glass</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Mirror"> <span>Mirror</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Acrylic/Panel"> <span>Acrylic/Panel</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Others"> <span>Others</span></label>
                    </div>
                    <div id="surface-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="surface_type_other" id="surface_type_other" class="input-field" placeholder="Specify surface type..." maxlength="100">
                    </div>
                </div>

                <!-- 3. Lamination + Installation (One Row) -->
                <div class="glass-one-row mb-4">
                    <div class="glass-one-row-item">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lamination *</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="With Laminate"> <span>With Laminate</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="Without Laminate"> <span>Without Laminate</span></label>
                        </div>
                    </div>
                    <div class="glass-one-row-item">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Installation</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="installation" value="With Installation"> <span>With Installation</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="installation" value="Without Installation"> <span>Without Installation</span></label>
                        </div>
                    </div>
                </div>

                <div id="install-address-section" style="display: none; margin-bottom: 1rem; padding: 1rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                    <div style="margin-bottom: 1rem; padding: 0.75rem 1rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 0.875rem; color: #92400e;">
                        <strong>Installation fee varies based on distance.</strong> A base fee is applied; the final amount may be adjusted after location confirmation by our team.
                    </div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Installation Address *</label>
                    <div class="space-y-3">
                        <div>
                            <label style="font-size: 0.75rem; color: #6b7280; font-weight: 600; display: block; margin-bottom: 0.25rem;">Province</label>
                            <select name="install_province" id="install_province" class="input-field" disabled>
                                <option value="">— Select Province —</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: #6b7280; font-weight: 600; display: block; margin-bottom: 0.25rem;">City / Municipality</label>
                            <select name="install_city" id="install_city" class="input-field" disabled>
                                <option value="">— Select City / Municipality —</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: #6b7280; font-weight: 600; display: block; margin-bottom: 0.25rem;">Barangay</label>
                            <select name="install_barangay" id="install_barangay" class="input-field" disabled>
                                <option value="">— Select Barangay —</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: #6b7280; font-weight: 600; display: block; margin-bottom: 0.25rem;">Street / Purok</label>
                            <input type="text" name="install_street" id="install_street" class="input-field" placeholder="Street name, Purok, etc." disabled>
                        </div>
                    </div>
                </div>

                <!-- Quantity + Needed Date + Upload Design (One Row) -->
                <div class="glass-qty-date-upload-row mb-4">
                    <div class="glass-qdu-item">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <div class="qty-control">
                            <button type="button" onclick="decreaseQty()" class="qty-btn">−</button>
                            <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                            <button type="button" onclick="increaseQty()" class="qty-btn">+</button>
                        </div>
                    </div>
                    <div class="glass-qdu-item">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date * <span class="text-gray-500 font-normal">(dd/mm/yyyy)</span></label>
                        <input type="date" name="needed_date" id="needed_date" class="input-field input-same-height" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="glass-qdu-item">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                        <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                    </div>
                </div>

                <div class="mb-4 tarp-notes-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field tarp-notes"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Buttons -->
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" style="height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none;">Back to Services</a>
                    <button type="submit" name="buy_now" value="1" style="height: 48px; min-width: 140px; padding: 0 1.25rem; background: #0a2530; color: #ffffff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 0.02em;">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.glass-order-container { max-width: 860px; }
.glass-form-card { overflow: hidden; }
.dim-feet-note { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.75rem; }
.dim-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem; }
.dim-sep { color: #9ca3af; font-weight: 600; margin-bottom: 0.5rem; align-self: center; }
.dim-others-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
.option-grid { display: grid; gap: 0.5rem; }
.option-grid-dim { grid-template-columns: repeat(4, 1fr); }
.option-grid-3x2 { grid-template-columns: repeat(3, 1fr); }
.glass-one-row { display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-end; }
.glass-one-row-item { flex: 1 1 auto; min-width: 160px; }
.opt-btn-inline { display: flex; gap: 0.5rem; flex-wrap: nowrap; }
.opt-btn, .opt-btn-wrap { padding: 0.5rem 1rem; min-width: 80px; text-align: center; justify-content: center; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; color: #374151; transition: all 0.25s ease; white-space: nowrap; }
.opt-btn:hover, .opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn.active, .opt-btn-wrap:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: rgba(10,37,48,0.03); }
.opt-btn-wrap { display: inline-flex; align-items: center; }
.opt-btn-wrap input { margin-right: 0.4rem; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.qty-control { display: flex; align-items: center; height: 42px; border: 2px solid #d1d5db; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.2s ease; width: fit-content; }
.qty-control:focus-within { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.qty-btn { flex: 0 0 42px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.2rem; cursor: pointer; transition: background 0.2s; }
.qty-btn:hover { background: #e5e7eb; }
.qty-control input { flex: 1; min-width: 50px; max-width: 80px; border: none; text-align: center; font-weight: 700; font-size: 1rem; outline: none; background: transparent; }
.tarp-notes-wrap { max-width: 100%; overflow: hidden; }
.tarp-notes { width: 100%; max-width: 100%; box-sizing: border-box; }
.glass-qty-date-upload-row { display: flex; flex-wrap: wrap; gap: 1rem 1.5rem; align-items: flex-end; }
.glass-qdu-item { flex: 1 1 auto; min-width: 140px; }
.input-same-height { height: 42px; padding: 0 0.75rem; box-sizing: border-box; }
@media (max-width: 768px) {
    .option-grid-dim { grid-template-columns: repeat(2, 1fr); }
    .option-grid-3x2 { grid-template-columns: repeat(2, 1fr); }
    .glass-one-row { flex-direction: column; align-items: stretch; }
    .glass-one-row-item { min-width: 0; }
    .glass-qty-date-upload-row { flex-direction: column; align-items: stretch; }
    .glass-qdu-item { min-width: 0; }
    .opt-btn-inline { flex-wrap: wrap; }
}
@media (max-width: 640px) {
    .glass-qdu-item { width: 100%; }
}
</style>

<script>
const ADDR_API = '<?php echo $addr_api; ?>';
let dimensionMode = 'preset';

function syncDimensionToHidden() {
    const wh = document.getElementById('width_hidden');
    const hh = document.getElementById('height_hidden');
    wh.value = dimensionMode === 'preset' ? (document.querySelector('.opt-btn.active')?.dataset?.width || '') : document.getElementById('custom_width').value;
    hh.value = dimensionMode === 'preset' ? (document.querySelector('.opt-btn.active')?.dataset?.height || '') : document.getElementById('custom_height').value;
}

function selectDimension(w, h, e) {
    e.preventDefault();
    dimensionMode = 'preset';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    e.target.closest('.opt-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = 'none';
    document.getElementById('custom_width').value = '';
    document.getElementById('custom_height').value = '';
    syncDimensionToHidden();
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = '';
    syncDimensionToHidden();
}

document.querySelectorAll('input[name="surface_type"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('surface-other-wrap').style.display = this.value === 'Others' ? 'block' : 'none';
    });
});

document.querySelectorAll('input[name="installation"]').forEach(r => {
    r.addEventListener('change', function() { toggleInstallAddress(this.value === 'With Installation'); });
});

function toggleInstallAddress(show) {
    const sec = document.getElementById('install-address-section');
    const prov = document.getElementById('install_province');
    const city = document.getElementById('install_city');
    const brgy = document.getElementById('install_barangay');
    const street = document.getElementById('install_street');
    sec.style.display = show ? 'block' : 'none';
    [prov, city, brgy, street].forEach(el => { el.disabled = !show; el.required = show; });
    if (!show) { prov.value = ''; city.innerHTML = '<option value="">— Select City / Municipality —</option>'; city.disabled = true; brgy.innerHTML = '<option value="">— Select Barangay —</option>'; brgy.disabled = true; street.value = ''; }
}

async function loadProvinces() {
    const sel = document.getElementById('install_province');
    try {
        const r = await fetch(ADDR_API + '?address_action=provinces');
        const d = await r.json();
        if (d.success && d.data) {
            sel.innerHTML = '<option value="">— Select Province —</option>' + d.data.map(p => '<option value="' + (p.code || p.name) + '">' + p.name + '</option>').join('');
            sel.disabled = false;
        }
    } catch (e) { console.error(e); }
}

document.getElementById('install_province').addEventListener('change', async function() {
    const code = this.value;
    const city = document.getElementById('install_city');
    const brgy = document.getElementById('install_barangay');
    city.innerHTML = '<option value="">— Select City / Municipality —</option>';
    brgy.innerHTML = '<option value="">— Select Barangay —</option>';
    city.disabled = true;
    brgy.disabled = true;
    if (!code) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=cities&province_code=' + encodeURIComponent(code));
        const d = await r.json();
        if (d.success && d.data) {
            city.innerHTML = '<option value="">— Select City / Municipality —</option>' + d.data.map(c => '<option value="' + (c.code || c.name) + '">' + c.name + '</option>').join('');
            city.disabled = false;
        }
    } catch (e) { console.error(e); }
});

document.getElementById('install_city').addEventListener('change', async function() {
    const code = this.value;
    const brgy = document.getElementById('install_barangay');
    brgy.innerHTML = '<option value="">— Select Barangay —</option>';
    brgy.disabled = true;
    if (!code) return;
    try {
        const r = await fetch(ADDR_API + '?address_action=barangays&city_code=' + encodeURIComponent(code));
        const d = await r.json();
        if (d.success && d.data) {
            brgy.innerHTML = '<option value="">— Select Barangay —</option>' + d.data.map(b => '<option value="' + (b.code || b.name) + '">' + b.name + '</option>').join('');
            brgy.disabled = false;
        }
    } catch (e) { console.error(e); }
});

document.getElementById('glassForm').addEventListener('submit', function(e) {
    syncDimensionToHidden();
    const hasDim = document.querySelector('.opt-btn.active');
    if (!hasDim) {
        e.preventDefault();
        alert('Please select a dimension preset or Others.');
        return false;
    }
    if (dimensionMode === 'others') {
        const cw = document.getElementById('custom_width').value.trim();
        const ch = document.getElementById('custom_height').value.trim();
        if (!cw || !ch) {
            e.preventDefault();
            alert('Please enter Custom Width and Custom Height when Others is selected.');
            return false;
        }
    }
    const surf = document.querySelector('input[name="surface_type"]:checked');
    if (!surf) {
        e.preventDefault();
        alert('Please select Surface Type.');
        return false;
    }
    if (surf.value === 'Others' && !document.getElementById('surface_type_other').value.trim()) {
        e.preventDefault();
        alert('Please specify your surface type when Others is selected.');
        return false;
    }
    if (!document.querySelector('input[name="lamination"]:checked')) {
        e.preventDefault();
        alert('Please select Lamination.');
        return false;
    }
});

['custom_width', 'custom_height'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9.]/g, '').slice(0, 6);
        syncDimensionToHidden();
    });
});

function increaseQty() {
    const i = document.getElementById('quantity-input');
    i.value = Math.min(999, (parseInt(i.value) || 1) + 1);
}
function decreaseQty() {
    const i = document.getElementById('quantity-input');
    const v = parseInt(i.value) || 1;
    if (v > 1) i.value = v - 1;
}

document.querySelectorAll('#install-address-section select, #install-address-section input').forEach(el => {
    if (el.name && el.name.startsWith('install_')) el.required = false;
});
toggleInstallAddress(false);
loadProvinces();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
