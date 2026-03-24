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
$edit_item_key = trim((string)($_GET['edit_item'] ?? $_POST['edit_item'] ?? ''));
$is_edit_mode = false;
$edit_existing_item = null;

if ($edit_item_key !== '' && isset($_SESSION['cart'][$edit_item_key]) && is_array($_SESSION['cart'][$edit_item_key])) {
    $candidate = $_SESSION['cart'][$edit_item_key];
    $cat_name = strtolower(((string)($candidate['category'] ?? '')) . ' ' . ((string)($candidate['name'] ?? '')));
    if (strpos($cat_name, 'glass') !== false || strpos($cat_name, 'frosted') !== false || strpos($cat_name, 'sticker') !== false) {
        $is_edit_mode = true;
        $edit_existing_item = $candidate;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $cust = (array)($candidate['customization'] ?? []);
            $surface = (string)($cust['surface_type'] ?? '');
            $known_surfaces = [
                'Glass (Window/Door/Storefront)',
                'Wall (Painted/Concrete)',
                'Frosted Glass',
                'Mirror',
                'Acrylic/Panel',
            ];
            $_POST['branch_id'] = (string)($candidate['branch_id'] ?? '');
            $_POST['width'] = (string)($cust['width'] ?? '');
            $_POST['height'] = (string)($cust['height'] ?? '');
            $_POST['unit'] = (string)($cust['unit'] ?? 'ft');
            $_POST['surface_type'] = in_array($surface, $known_surfaces, true) ? $surface : 'Others';
            $_POST['surface_type_other'] = in_array($surface, $known_surfaces, true) ? '' : $surface;
            $_POST['lamination'] = (string)($cust['lamination'] ?? '');
            $_POST['installation'] = (string)($cust['installation'] ?? '');
            $_POST['quantity'] = (string)($candidate['quantity'] ?? 1);
            $_POST['needed_date'] = (string)($cust['needed_date'] ?? '');
            $_POST['notes'] = (string)($cust['notes'] ?? '');
            $_POST['install_province'] = (string)($cust['install_province'] ?? '');
            $_POST['install_city'] = (string)($cust['install_city'] ?? '');
            $_POST['install_barangay'] = (string)($cust['install_barangay'] ?? '');
            $_POST['install_street'] = (string)($cust['install_street'] ?? '');
        }
    }
}

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
    if (function_exists('mb_substr')) {
        $notes = mb_substr($notes, 0, 500);
    } else {
        $notes = substr($notes, 0, 500);
    }

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
    } elseif (!$is_edit_mode && (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'Please upload your design.';
    } else {
        $has_new_upload = isset($_FILES['design_file']) && (($_FILES['design_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK);
        $tmp_dest = '';
        $original_name = '';
        $mime = '';
        if ($has_new_upload) {
            $valid = service_order_validate_file($_FILES['design_file']);
            if (!$valid['ok']) {
                $error = $valid['error'];
            } else {
                $original_name = $_FILES['design_file']['name'];
                $mime = $valid['mime'];
                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_name = uniqid('tmp_') . '.' . $ext;
                $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;
                if (!move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                    $error = 'Failed to process uploaded file.';
                }
            }
        } elseif ($is_edit_mode && is_array($edit_existing_item)) {
            $tmp_dest = (string)($edit_existing_item['design_tmp_path'] ?? '');
            $original_name = (string)($edit_existing_item['design_name'] ?? '');
            $mime = (string)($edit_existing_item['design_mime'] ?? '');
        } else {
            $error = 'Please upload your design.';
        }

        if ($error === '') {
            $item_key = ($is_edit_mode && $edit_item_key !== '' && isset($_SESSION['cart'][$edit_item_key])) ? $edit_item_key : ('glass_' . time() . '_' . rand(100, 999));
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
                    'source_page' => 'services',
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

                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
                    redirect("order_review.php?item=" . urlencode($item_key));
                } else {
                    redirect("cart.php");
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
            <form action="" method="POST" enctype="multipart/form-data" id="glassForm" novalidate>
                <?php echo csrf_field(); ?>
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="edit_item" value="<?php echo htmlspecialchars($edit_item_key); ?>">
                <?php endif; ?>

                <div class="mb-4" id="glassBranchCard">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <option value="" selected disabled>Select Branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ((string)$b['id'] === (string)($_POST['branch_id'] ?? '')) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1. Dimensions / Coverage (Feet only) -->
                <div class="mb-4" id="glassDimensionsCard">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dimensions (ft) *</label>
                    <div class="option-grid option-grid-dim">
                        <button type="button" class="opt-btn" data-width="2" data-height="3" onclick="selectDimension(2, 3, event)">2×3</button>
                        <button type="button" class="opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6</button>
                        <button type="button" class="opt-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)">6×8</button>
                        <button type="button" class="opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                    </div>
                    <input type="hidden" name="width" id="width_hidden" value="<?php echo htmlspecialchars($_POST['width'] ?? ''); ?>">
                    <input type="hidden" name="height" id="height_hidden" value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>">
                    <input type="hidden" name="unit" value="ft">
                    <div id="dim-others-inputs" class="dim-others-row" style="display: none; margin-top: 1rem;">
                        <div class="dim-others-field">
                            <label class="dim-label">WIDTH</label>
                            <input type="text" inputmode="numeric" id="custom_width" class="input-field" placeholder="e.g. 10" value="<?php echo htmlspecialchars($_POST['width'] ?? ''); ?>">
                        </div>
                        <div class="dim-sep">×</div>
                        <div class="dim-others-field">
                            <label class="dim-label">HEIGHT</label>
                            <input type="text" inputmode="numeric" id="custom_height" class="input-field" placeholder="e.g. 12" value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- 2. Surface Type (3×2 grid) -->
                <div class="mb-4" id="glassSurfaceCard">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Surface Type *</label>
                    <div class="option-grid option-grid-3x2">
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Glass (Window/Door/Storefront)" <?php echo (($_POST['surface_type'] ?? '') === 'Glass (Window/Door/Storefront)') ? 'checked' : ''; ?>> <span>Glass</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Wall (Painted/Concrete)" <?php echo (($_POST['surface_type'] ?? '') === 'Wall (Painted/Concrete)') ? 'checked' : ''; ?>> <span>Wall</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Frosted Glass" <?php echo (($_POST['surface_type'] ?? '') === 'Frosted Glass') ? 'checked' : ''; ?>> <span>Frosted Glass</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Mirror" <?php echo (($_POST['surface_type'] ?? '') === 'Mirror') ? 'checked' : ''; ?>> <span>Mirror</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Acrylic/Panel" <?php echo (($_POST['surface_type'] ?? '') === 'Acrylic/Panel') ? 'checked' : ''; ?>> <span>Acrylic/Panel</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="surface_type" value="Others" <?php echo (($_POST['surface_type'] ?? '') === 'Others') ? 'checked' : ''; ?>> <span>Others</span></label>
                    </div>
                    <div id="surface-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="surface_type_other" id="surface_type_other" class="input-field" placeholder="Specify surface type..." maxlength="100" value="<?php echo htmlspecialchars($_POST['surface_type_other'] ?? ''); ?>">
                    </div>
                </div>

                <!-- 3. Lamination -->
                <div class="mb-4" id="glassLaminationCard">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lamination *</label>
                    <div class="opt-btn-group glass-opt-group-wide">
                        <label class="opt-btn-wrap"><input type="radio" name="lamination" value="With Laminate" <?php echo (($_POST['lamination'] ?? '') === 'With Laminate') ? 'checked' : ''; ?>> <span>With Laminate</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="lamination" value="Without Laminate" <?php echo (($_POST['lamination'] ?? '') === 'Without Laminate') ? 'checked' : ''; ?>> <span>Without Laminate</span></label>
                    </div>
                </div>

                <!-- 4. Installation -->
                <div class="mb-4" id="glassInstallCard">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Installation</label>
                    <div class="opt-btn-group glass-opt-group-wide">
                        <label class="opt-btn-wrap"><input type="radio" name="installation" value="With Installation" <?php echo (($_POST['installation'] ?? '') === 'With Installation') ? 'checked' : ''; ?>> <span>With Installation</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="installation" value="Without Installation" <?php echo (($_POST['installation'] ?? '') === 'Without Installation') ? 'checked' : ''; ?>> <span>Without Installation</span></label>
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

                <!-- 5. Needed Date + Quantity -->
                <div class="mb-4" id="glassNeedQtyCard">
                    <div class="need-qty-row">
                        <div class="need-qty-date">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="need-qty-qty">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="qty-control qty-control-shopee">
                                <button type="button" onclick="decreaseQty()" class="qty-btn">−</button>
                                <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                                <button type="button" onclick="increaseQty()" class="qty-btn">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Upload Design -->
                <div class="mb-4" id="glassUploadCard">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" <?php echo $is_edit_mode ? '' : 'required'; ?>>
                    <?php if ($is_edit_mode): ?><p class="sintra-hint mt-2">Leave empty to keep your current uploaded design.</p><?php endif; ?>
                </div>

                <div class="mb-4 tarp-notes-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field tarp-notes" maxlength="500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Buttons -->
                <div class="tshirt-actions-row">
                    <?php if ($is_edit_mode): ?>
                        <a href="cart.php" class="tshirt-btn tshirt-btn-secondary">Cancel</a>
                        <button type="submit" name="action" value="save_changes" class="tshirt-btn tshirt-btn-primary">Save Changes</button>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                        <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                        <button type="submit" name="action" value="buy_now" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.glass-order-container { max-width: 860px; }
.glass-form-card { overflow: hidden; }
.dim-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem; }
.dim-sep {
    color: #9ca3af;
    font-weight: 700;
    align-self: stretch;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding-top: 1.55rem;
    margin-bottom: 0;
    line-height: 1;
}
.dim-others-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
.dim-others-field { flex: 1 1 0; min-width: 140px; }
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
.glass-opt-group-wide { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.65rem; }
.glass-opt-group-wide .opt-btn-wrap { width: 100%; justify-content: center; }
.qty-control { display: flex; align-items: center; height: 42px; border: 2px solid #d1d5db; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.2s ease; width: fit-content; }
.qty-control:focus-within { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.qty-btn { flex: 0 0 42px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.2rem; cursor: pointer; transition: background 0.2s; }
.qty-btn:hover { background: #e5e7eb; }
.qty-control input { flex: 1; min-width: 50px; max-width: 80px; border: none; text-align: center; font-weight: 700; font-size: 1rem; outline: none; background: transparent; }
.tarp-notes-wrap { max-width: 100%; overflow: hidden; }
.tarp-notes { width: 100%; max-width: 100%; box-sizing: border-box; }
.need-qty-row { display: flex; gap: 1rem; align-items: flex-end; }
.need-qty-date,
.need-qty-qty { flex: 1 1 0; min-width: 0; }
.need-qty-qty .qty-control { width: 100%; max-width: none; }
.need-qty-qty .qty-control input { max-width: none; min-width: 70px; }
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
    .need-qty-row { flex-direction: column; align-items: stretch; }
    .glass-opt-group-wide { grid-template-columns: 1fr; }
    .opt-btn-inline { flex-wrap: wrap; }
}
@media (max-width: 640px) {
    .glass-qdu-item { width: 100%; }
}

/* --- T-shirt visual language applied to Glass form (UI only) --- */
.glass-order-container { max-width: 640px; }
.glass-order-container h1 { color: #eaf6fb !important; }
.glass-form-card.card {
    background: rgba(10, 37, 48, 0.55);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 1.25rem;
    box-shadow: 0 12px 40px rgba(2, 12, 18, 0.35);
}
#glassForm {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    color-scheme: dark;
}
#glassForm .mb-4 {
    margin-bottom: 0 !important;
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
#glassForm label.block {
    font-size: .95rem !important;
    font-weight: 700 !important;
    color: #d9e6ef !important;
    margin-bottom: .55rem !important;
}
#glassForm .input-field {
    min-height: 44px;
    padding: .72rem .9rem;
    border-radius: 10px;
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #e9f6fb !important;
    box-shadow: none !important;
}
#glassForm .input-field::placeholder { color: #a3bdca !important; }
#glassForm .input-field:focus,
#glassForm .input-field:focus-visible {
    background: rgba(16, 52, 67, 0.98) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
    outline: none !important;
}
#glassForm select.input-field option {
    background: #0a2530 !important;
    color: #f8fafc !important;
}
#glassForm input[type="radio"] { accent-color: #53c5e0; }

.dim-label { color: #9fc6d9 !important; }
.dim-label { font-size: .86rem; text-transform: uppercase; letter-spacing: .01em; }
.dim-sep { color: #d2e7f1; }

.opt-btn, .opt-btn-wrap {
    min-height: 44px;
    border-radius: 10px;
    font-weight: 500;
    font-size: .86rem;
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(83, 197, 224, 0.2) !important;
    color: #d2e7f1 !important;
}
.opt-btn:hover, .opt-btn-wrap:hover {
    background: rgba(83, 197, 224, 0.12) !important;
    border-color: rgba(83, 197, 224, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.12);
}
.opt-btn.active,
.opt-btn-wrap:has(input:checked) {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.28), rgba(50, 161, 196, 0.24)) !important;
    border-color: #53c5e0 !important;
    color: #f8fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.22), 0 8px 18px rgba(11, 42, 56, 0.35);
}

.qty-control {
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
}
.qty-control:focus-within {
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.16);
}
.qty-btn {
    background: rgba(83, 197, 224, 0.12) !important;
    color: #d8edf5 !important;
}
.qty-btn:hover { background: rgba(83, 197, 224, 0.22) !important; }
.qty-control input { color: #f8fafc !important; }

#glassForm textarea[name="notes"].input-field {
    overflow-y: auto;
    resize: vertical;
    min-height: 110px;
    max-height: 220px;
    max-width: 100%;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08);
}
#glassForm textarea[name="notes"].input-field::-webkit-scrollbar { width: 10px; }
#glassForm textarea[name="notes"].input-field::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.08); border-radius: 999px; }
#glassForm textarea[name="notes"].input-field::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.65); border-radius: 999px; border: 2px solid rgba(10, 37, 48, 0.55); }
#glassForm textarea[name="notes"].input-field::-webkit-scrollbar-thumb:hover { background: rgba(83, 197, 224, 0.85); }

.field-error {
    margin-top: .45rem;
    color: #fca5a5;
    font-size: .8rem;
    font-weight: 600;
    line-height: 1.25;
}
#glassForm .mb-4.is-invalid {
    border-color: rgba(248, 113, 113, 0.95) !important;
    box-shadow: 0 0 0 2px rgba(248, 113, 113, 0.2) inset;
}
#glassForm .input-field.is-invalid,
#glassForm .qty-control.is-invalid {
    border-color: #f87171 !important;
    box-shadow: 0 0 0 2px rgba(248, 113, 113, 0.16) !important;
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
    background: rgba(255,255,255,.05) !important;
    color: #d9e6ef !important;
    border: 1px solid rgba(83,197,224,.28) !important;
}
.tshirt-btn-secondary:hover { background: rgba(83,197,224,.14) !important; color: #fff !important; }
.tshirt-btn-primary {
    border: none;
    background: linear-gradient(135deg, #53C5E0, #32a1c4) !important;
    color: #fff !important;
    text-transform: uppercase;
    letter-spacing: .02em;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(50,161,196,0.3);
}

#install-address-section {
    background: rgba(8, 32, 42, 0.65) !important;
    border: 1px solid rgba(83, 197, 224, 0.22) !important;
    border-radius: 12px !important;
}
#install-address-section > div:first-child {
    background: rgba(84, 56, 7, 0.35) !important;
    border-color: rgba(253, 230, 138, 0.45) !important;
    color: #facc15 !important;
}
#install-address-section label[style] { color: #9fc6d9 !important; }

@media (max-width: 640px) {
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
    .tshirt-btn { width: 100%; }
}
</style>

<script>
const ADDR_API = '<?php echo $addr_api; ?>';
const GLASS_IS_EDIT_MODE = <?php echo $is_edit_mode ? 'true' : 'false'; ?>;
const GLASS_PREFILL = <?php echo json_encode([
    'width' => (string)($_POST['width'] ?? ''),
    'height' => (string)($_POST['height'] ?? ''),
    'surface_type' => (string)($_POST['surface_type'] ?? ''),
    'surface_type_other' => (string)($_POST['surface_type_other'] ?? ''),
    'lamination' => (string)($_POST['lamination'] ?? ''),
    'installation' => (string)($_POST['installation'] ?? ''),
    'install_province' => (string)($_POST['install_province'] ?? ''),
    'install_city' => (string)($_POST['install_city'] ?? ''),
    'install_barangay' => (string)($_POST['install_barangay'] ?? ''),
    'install_street' => (string)($_POST['install_street'] ?? ''),
], JSON_UNESCAPED_UNICODE); ?>;
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
    function clearFieldError(containerId, inputEl) {
        const card = document.getElementById(containerId);
        if (!card) return;
        card.classList.remove('is-invalid');
        const err = card.querySelector('.field-error');
        if (err) err.remove();
        if (inputEl) inputEl.classList.remove('is-invalid');
    }
    function setFieldError(containerId, message, inputEl) {
        const card = document.getElementById(containerId);
        if (!card) return;
        card.classList.add('is-invalid');
        if (inputEl) inputEl.classList.add('is-invalid');
        let err = card.querySelector('.field-error');
        if (!err) {
            err = document.createElement('div');
            err.className = 'field-error';
            card.appendChild(err);
        }
        err.textContent = message;
    }

    clearFieldError('glassBranchCard', document.querySelector('select[name="branch_id"]'));
    clearFieldError('glassDimensionsCard');
    clearFieldError('glassSurfaceCard', document.getElementById('surface_type_other'));
    clearFieldError('glassLaminationCard');
    clearFieldError('glassNeedQtyCard', document.getElementById('needed_date'));
    clearFieldError('glassUploadCard', document.querySelector('input[name="design_file"]'));

    syncDimensionToHidden();
    let hasError = false;
    const branch = document.querySelector('select[name="branch_id"]');
    const neededDate = document.getElementById('needed_date');
    const qtyInput = document.getElementById('quantity-input');
    const uploadInput = document.querySelector('input[name="design_file"]');

    if (!branch || !branch.value) {
        setFieldError('glassBranchCard', 'This field is required.', branch);
        hasError = true;
    }

    const hasDim = document.querySelector('.opt-btn.active');
    if (!hasDim) {
        setFieldError('glassDimensionsCard', 'This field is required.');
        hasError = true;
    }
    if (dimensionMode === 'others') {
        const cw = document.getElementById('custom_width').value.trim();
        const ch = document.getElementById('custom_height').value.trim();
        if (!cw || !ch) {
            setFieldError('glassDimensionsCard', 'Please enter both width and height.');
            hasError = true;
        }
    }
    const surf = document.querySelector('input[name="surface_type"]:checked');
    if (!surf) {
        setFieldError('glassSurfaceCard', 'This field is required.');
        hasError = true;
    }
    if (surf && surf.value === 'Others' && !document.getElementById('surface_type_other').value.trim()) {
        setFieldError('glassSurfaceCard', 'Please specify your surface type.');
        hasError = true;
    }
    if (!document.querySelector('input[name="lamination"]:checked')) {
        setFieldError('glassLaminationCard', 'This field is required.');
        hasError = true;
    }
    if (!neededDate || !neededDate.value || (parseInt(qtyInput.value, 10) || 0) < 1) {
        const qtyControl = document.querySelector('#glassNeedQtyCard .qty-control');
        if ((parseInt(qtyInput.value, 10) || 0) < 1 && qtyControl) qtyControl.classList.add('is-invalid');
        setFieldError('glassNeedQtyCard', 'Needed date and quantity are required.', neededDate);
        hasError = true;
    }
    if (!GLASS_IS_EDIT_MODE && (!uploadInput || !uploadInput.files || uploadInput.files.length === 0)) {
        setFieldError('glassUploadCard', 'This field is required.', uploadInput);
        hasError = true;
    }
    if (hasError) {
        e.preventDefault();
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
if (GLASS_PREFILL.surface_type) {
    const surf = Array.from(document.querySelectorAll('input[name="surface_type"]')).find(r => r.value === GLASS_PREFILL.surface_type);
    if (surf) {
        surf.checked = true;
        surf.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
if (GLASS_PREFILL.surface_type_other) {
    const other = document.getElementById('surface_type_other');
    if (other) other.value = GLASS_PREFILL.surface_type_other;
}
if (GLASS_PREFILL.lamination) {
    const lam = Array.from(document.querySelectorAll('input[name="lamination"]')).find(r => r.value === GLASS_PREFILL.lamination);
    if (lam) lam.checked = true;
}
if (GLASS_PREFILL.installation) {
    const ins = Array.from(document.querySelectorAll('input[name="installation"]')).find(r => r.value === GLASS_PREFILL.installation);
    if (ins) {
        ins.checked = true;
        ins.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

const presetBtn = document.querySelector('.opt-btn[data-width="' + GLASS_PREFILL.width + '"][data-height="' + GLASS_PREFILL.height + '"]');
if (presetBtn && GLASS_PREFILL.width && GLASS_PREFILL.height) {
    selectDimension(GLASS_PREFILL.width, GLASS_PREFILL.height, { preventDefault: function(){}, target: presetBtn });
} else if (GLASS_PREFILL.width || GLASS_PREFILL.height) {
    const cw = document.getElementById('custom_width');
    const ch = document.getElementById('custom_height');
    if (cw) cw.value = GLASS_PREFILL.width || '';
    if (ch) ch.value = GLASS_PREFILL.height || '';
    const othersBtn = document.getElementById('dim-others-btn');
    if (othersBtn) {
        selectDimensionOthers({ preventDefault: function(){}, target: othersBtn });
        syncDimensionToHidden();
    }
}

if (GLASS_PREFILL.installation === 'With Installation') {
    toggleInstallAddress(true);
    loadProvinces().then(function() {
        const prov = document.getElementById('install_province');
        const city = document.getElementById('install_city');
        const brgy = document.getElementById('install_barangay');
        const street = document.getElementById('install_street');
        if (prov && GLASS_PREFILL.install_province) {
            prov.value = GLASS_PREFILL.install_province;
            prov.dispatchEvent(new Event('change', { bubbles: true }));
            setTimeout(function() {
                if (city && GLASS_PREFILL.install_city) {
                    city.value = GLASS_PREFILL.install_city;
                    city.dispatchEvent(new Event('change', { bubbles: true }));
                    setTimeout(function() {
                        if (brgy && GLASS_PREFILL.install_barangay) brgy.value = GLASS_PREFILL.install_barangay;
                        if (street && GLASS_PREFILL.install_street) street.value = GLASS_PREFILL.install_street;
                    }, 350);
                }
            }, 350);
        }
    });
} else {
    toggleInstallAddress(false);
    loadProvinces();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
