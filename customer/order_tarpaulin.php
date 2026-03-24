<?php
/**
 * Tarpaulin Printing - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

// Pricelist image - check both possible locations
$base_path = __DIR__ . '/../public/';
$pricelist_url = null;
foreach (['images/tarp price range/', 'assets/images/tarp price range/'] as $subpath) {
    foreach (['.webp', '.jpg', '.jpeg', '.png'] as $ext) {
        if (file_exists($base_path . $subpath . 'pricelist' . $ext)) {
            $pricelist_url = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/' . $subpath . 'pricelist' . $ext;
            break 2;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $unit = trim($_POST['unit'] ?? 'ft');
    $lamination = trim($_POST['lamination'] ?? '');
    $finish = trim($_POST['finish'] ?? '');
    $with_eyelets = trim($_POST['with_eyelets'] ?? '');
    $layout = trim($_POST['layout'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $needed_date = trim($_POST['needed_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($width) || empty($height) || $quantity < 1) {
        $error = 'Please fill in Dimensions (Width, Height) and Quantity.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes)) > 500) {
        $error = 'Notes must not exceed 500 characters.';
    } elseif (empty($needed_date)) {
        $error = 'Please select when you need the order.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'tarp_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $w = (float)$width;
                $h = (float)$height;
                $area = $w * $h;
                if ($unit === 'in') {
                    $area = $area / 144;
                }
                $unit_price = 20.00;
                
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'source_page' => 'services',
                    'name' => 'Tarpaulin Printing',
                    'price' => $area * $unit_price * $quantity,
                    'quantity' => $quantity,
                    'category' => 'Tarpaulin',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'width' => $width,
                        'height' => $height,
                        'unit' => $unit,
                        'lamination' => $lamination,
                        'finish' => $finish,
                        'with_eyelets' => $with_eyelets,
                        'layout' => $layout,
                        'needed_date' => $needed_date,
                        'notes' => $notes
                    ]
                ];
                
                if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
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

$page_title = 'Order Tarpaulin - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 tarp-order-container">
        <h1 class="text-2xl font-bold mb-6 tarp-page-title">Tarpaulin Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- View Price List Button -->
        <div class="mb-6">
            <button type="button" onclick="openPricelistModal()" class="view-pricelist-btn">
                View Price List
            </button>
        </div>

        <div class="card tarp-form-card">
            <form action="" method="POST" enctype="multipart/form-data" id="tarpForm" novalidate>
                <?php echo csrf_field(); ?>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1. Dimensions (Feet only) -->
                <div class="mb-4" id="card-dimensions">
                    <label class="block text-sm font-medium text-gray-700 mb-1 dim-label-oneline">Dimensions * <span class="dim-feet-note">(All values are in feet)</span></label>
                    <div class="opt-btn-group dim-preset-row">
                        <button type="button" class="opt-btn" data-width="3" data-height="4" onclick="selectDimension(3, 4, event)">3×4</button>
                        <button type="button" class="opt-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)">4×6</button>
                        <button type="button" class="opt-btn" data-width="5" data-height="8" onclick="selectDimension(5, 8, event)">5×8</button>
                        <button type="button" class="opt-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)">6×8</button>
                        <button type="button" class="opt-btn" id="dim-others-btn" onclick="selectDimensionOthers(event)">Others</button>
                    </div>
                    <input type="hidden" name="width" id="width_hidden">
                    <input type="hidden" name="height" id="height_hidden">
                    <input type="hidden" name="unit" value="ft">
                    <div id="dim-others-inputs" class="dim-others-row" style="display: none;">
                        <div>
                            <label class="dim-label">Width (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_width" class="input-field" placeholder="e.g. 10">
                        </div>
                        <div class="dim-sep">×</div>
                        <div>
                            <label class="dim-label">Height (ft)</label>
                            <input type="text" inputmode="numeric" id="custom_height" class="input-field" placeholder="e.g. 12">
                        </div>
                    </div>
                </div>

                <!-- Row 1: Finish Type + Lamination + Eyelets (separate cards) -->
                <div class="tarp-option-cards-3">
                    <div class="mb-4 tarp-option-col" id="card-finish">
                        <label class="label-with-info">Finish Type * <span class="info-icon" id="finish-info-icon" tabindex="0" role="button" aria-label="Finish type info">ⓘ</span></label>
                        <div class="finish-tooltip" id="finish-tooltip" role="tooltip">
                            <div class="tooltip-row"><strong>Glossy:</strong> Shiny finish · More vibrant colors · Reflective under light</div>
                            <div class="tooltip-row"><strong>Matte:</strong> Non-reflective · Softer look · Better readability outdoors</div>
                        </div>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="finish" value="Matte" required> <span>Matte</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="finish" value="Glossy" required> <span>Glossy</span></label>
                        </div>
                    </div>
                    <div class="mb-4 tarp-option-col" id="card-lamination">
                        <label>Lamination *</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="With Laminate" required> <span>With Laminate</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="Without Laminate" required> <span>Without Laminate</span></label>
                        </div>
                    </div>
                    <div class="mb-4 tarp-option-col" id="card-eyelets">
                        <label>Eyelets *</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="with_eyelets" value="Yes" required> <span>Yes</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="with_eyelets" value="No" required> <span>No</span></label>
                        </div>
                    </div>
                </div>

                <!-- Upload Design (above Layout) -->
                <div class="mb-4" id="card-upload">
                    <div class="tarp-option-col">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                        <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                    </div>
                </div>

                <!-- Layout -->
                <div class="tarp-option-row-2" id="card-layout">
                    <div class="tarp-option-col">
                        <label>Layout *</label>
                        <div class="opt-btn-group opt-btn-inline opt-btn-expand">
                            <label class="opt-btn-wrap"><input type="radio" name="layout" value="With Layout" required> <span>With Layout</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="layout" value="Without Layout" required> <span>Without Layout</span></label>
                        </div>
                    </div>
                </div>

                <!-- Needed Date + Quantity (same structure as t-shirt form) -->
                <div class="tarp-need-qty-row need-qty-card mb-4" id="card-date-qty">
                    <div class="need-qty-row">
                        <div class="tarp-option-col need-qty-date">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                            <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="tarp-option-col tarp-qty-col need-qty-qty">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="tarp-qty-stepper">
                                <button type="button" onclick="decreaseQty()">−</button>
                                <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                                <button type="button" onclick="increaseQty()">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 tarp-notes-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field tarp-notes" placeholder="Any special instructions..." maxlength="500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Buttons -->
                <div class="tshirt-actions-row">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="tshirt-btn tshirt-btn-secondary">Back to Services</a>
                    <button type="submit" name="action" value="add_to_cart" class="tshirt-btn tshirt-btn-secondary">Add to Cart</button>
                    <button type="submit" name="action" value="buy_now" id="buyNowBtn" class="tshirt-btn tshirt-btn-primary">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pricelist Modal -->
<div id="pricelist-modal" style="display: none; position: fixed; inset: 0; z-index: 99999; align-items: center; justify-content: center; padding: 1.5rem; background: rgba(0,0,0,0.5);">
    <div onclick="closePricelistModal()" style="position: absolute; inset: 0;"></div>
    <div style="position: relative; background: #0a2530; border: 1px solid rgba(83, 197, 224, 0.28); border-radius: 1rem; max-width: 72vw; max-height: 90vh; overflow: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.45);">
        <button onclick="closePricelistModal()" style="position: absolute; top: 0.75rem; right: 0.75rem; z-index: 10; width: 36px; height: 36px; border: 1px solid rgba(83, 197, 224, 0.28); background: rgba(15, 53, 68, 0.95); border-radius: 50%; cursor: pointer; font-size: 1.25rem; line-height: 1; color: #d8edf5;">×</button>
        <?php if ($pricelist_url): ?>
        <img src="<?php echo htmlspecialchars($pricelist_url); ?>" alt="Tarpaulin Pricelist" style="width: 100%; max-width: 640px; display: block;">
        <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: #6b7280;">Pricelist image not found.</div>
        <?php endif; ?>
    </div>
</div>

<style>
.tarp-order-container { max-width: 640px; }
.tarp-form-card { overflow: hidden; }
.tarp-page-title { color: #eaf6fb !important; }
.view-pricelist-btn { padding: 0.6rem 1.25rem; background: linear-gradient(135deg, #53C5E0, #32a1c4); color: #fff; font-weight: 700; font-size: 0.9rem; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 6px 16px rgba(50,161,196,0.25); }
.view-pricelist-btn:hover { background: linear-gradient(135deg, #5fd4ed, #3aadcf); transform: translateY(-1px); }

.dim-label-oneline { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem; }
.dim-feet-note { font-size: 0.75rem; font-weight: 500; color: #9fc6d9; }
.opt-btn-group.dim-preset-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem; margin-bottom: 1rem; }
.opt-btn-group.dim-preset-row .opt-btn { width: 100%; min-width: 0; font-size: .86rem !important; font-weight: 500 !important; }
.opt-btn-group.dim-preset-row #dim-others-btn { grid-column: 1 / -1; justify-self: center; width: calc(50% - 0.25rem); }
.opt-btn-expand { display: flex !important; width: 100%; }
.opt-btn-expand .opt-btn-wrap { flex: 1 1 0; min-width: 0; }
.dim-others-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 1.2ch minmax(0, 1fr);
    align-items: end;
    column-gap: 0.3rem;
    row-gap: 0;
    width: 100%;
}
.dim-others-row > div { width: 100%; max-width: none; }
.dim-sep {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: end;
    width: 1.2ch;
    min-width: 0;
    height: 44px;
    font-size: 1.15rem;
    font-weight: 700;
    color: #9fc6d9;
}
.opt-btn-inline { display: inline-flex; gap: 0.5rem; flex-wrap: nowrap; }
.opt-btn-group.opt-btn-inline { flex-wrap: nowrap !important; }
.dim-label { font-size: 0.75rem; color: #9fc6d9; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem; }

/* Row 1: Finish Type + Lamination + Eyelets - give Lamination more space (longer labels) */
.tarp-option-row-3 { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.4fr) minmax(0, 0.7fr); gap: 1rem 1.5rem; align-items: start; margin-bottom: 1rem; }
.tarp-option-cards-3 { display: grid; grid-template-columns: 1fr; gap: 1rem; }
/* Uniform button widths within each field - each pair shares space equally */
.tarp-option-row-3 .opt-btn-group .opt-btn-wrap { flex: 1 1 0; min-width: 0; }
.tarp-option-cards-3 .opt-btn-group .opt-btn-wrap { flex: 1 1 0; min-width: 0; }
/* Row 2: 2 columns (Layout, Quantity) */
.tarp-option-row-2 { display: grid; grid-template-columns: 1fr; gap: 1rem; align-items: start; margin-bottom: 1rem; }
.tarp-need-qty-row { display: block; }
.need-qty-card .need-qty-row { display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.need-qty-card .need-qty-date { flex: 1; min-width: 0; }
.need-qty-card .need-qty-qty { flex: 1; min-width: 0; }
.need-qty-card .need-qty-qty .tarp-qty-stepper { width: 100%; }
.need-qty-card .need-qty-qty .tarp-qty-stepper input { max-width: none; flex: 1; }
.tarp-option-col { min-width: 0; }
.tarp-option-col label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
.label-with-info { display: inline-flex; align-items: center; gap: 0.35rem; }
.info-icon { cursor: help; font-size: 0.9rem; color: #6b7280; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; transition: color 0.2s; }
.info-icon:hover, .info-icon:focus { color: #0a2530; outline: none; }
.finish-tooltip { display: none; position: absolute; z-index: 50; margin-top: 0.25rem; padding: 0.75rem 1rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); font-size: 0.8rem; color: #374151; line-height: 1.5; max-width: 280px; opacity: 0; transition: opacity 0.2s ease; }
.finish-tooltip.visible { display: block; opacity: 1; }
.finish-tooltip .tooltip-row { margin-bottom: 0.35rem; }
.finish-tooltip .tooltip-row:last-child { margin-bottom: 0; }
.tarp-option-col { position: relative; }

.opt-btn, .opt-btn-wrap { padding: 0.65rem 1rem; min-width: 100px; min-height: 44px; display: inline-flex; align-items: center; text-align: center; justify-content: center; border: 2px solid #d1d5db; background: #fff; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: .86rem; color: #374151; transition: all 0.2s ease; white-space: nowrap; }
.opt-btn:hover, .opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn.active, .opt-btn-wrap:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: #fff; }
.opt-btn-wrap { display: inline-flex; align-items: center; }
.opt-btn-wrap input { margin-right: 0.4rem; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.tarp-notes-wrap { max-width: 100%; overflow: hidden; }
.tarp-notes { width: 100%; max-width: 100%; box-sizing: border-box; }
.tarp-qty-col { max-width: 100%; }
.tarp-qty-stepper { display: flex; align-items: center; width: 100%; max-width: 100%; height: 44px; border: 1px solid #d1d5db; border-radius: 10px; background: #fff; overflow: hidden; }
.tarp-qty-stepper button { flex: 0 0 44px; height: 44px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.2rem; cursor: pointer; }
.tarp-qty-stepper input { flex: 1; min-width: 50px; max-width: 80px; border: none; text-align: center; font-weight: 700; font-size: 1rem; outline: none; background: transparent; }
.field-error { margin-top: .4rem; font-size: .75rem; color: #fca5a5; line-height: 1.3; display: block; width: 100%; }
#tarpForm .mb-4.is-invalid, #tarpForm .tarp-option-row-2.is-invalid, #tarpForm .need-qty-card.is-invalid, #tarpForm .tarp-option-col.is-invalid { border-color: rgba(239, 68, 68, 0.35) !important; box-shadow: none !important; }
#tarpForm .mb-4.is-invalid .input-field, #tarpForm .need-qty-card.is-invalid .input-field { border-color: rgba(239, 68, 68, 0.55) !important; }
#tarpForm .mb-4.is-invalid .opt-btn { border-color: rgba(239, 68, 68, 0.55) !important; }
#tarpForm .need-qty-card.is-invalid .tarp-qty-stepper { border-color: rgba(239, 68, 68, 0.55) !important; }

/* T-shirt page visual parity (UI only) */
#tarpForm { display: flex; flex-direction: column; gap: 1rem; }
#tarpForm .mb-4, #tarpForm .tarp-option-row-3, #tarpForm .tarp-option-row-2, #tarpForm .tarp-need-qty-row, #tarpForm .tarp-notes-wrap, #tarpForm #card-upload {
    margin-bottom: 0 !important;
    padding: 1rem;
    background: rgba(10, 37, 48, 0.48);
    border: 1px solid rgba(83, 197, 224, 0.22);
    border-radius: 12px;
    backdrop-filter: blur(4px);
}
#tarpForm label.block,
#tarpForm .label-with-info,
#tarpForm .tarp-option-col > label { font-size: .95rem !important; font-weight: 700 !important; color: #d9e6ef !important; margin-bottom: .55rem !important; }
#tarpForm input[type="radio"] { accent-color: #53c5e0; }
#tarpForm .input-field {
    min-height: 44px;
    padding: .72rem .9rem;
    border-radius: 10px;
    font-size: .95rem;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
    color: #eef7fb !important;
}
#tarpForm .input-field::placeholder { color: #a3bdca !important; }
#tarpForm .input-field:focus {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
}
#tarpForm select.input-field option { background: #0a2530 !important; color: #f8fafc !important; }
#tarpForm select.input-field option:hover,
#tarpForm select.input-field option:focus { background: #53c5e0 !important; color: #06232c !important; }
#tarpForm select.input-field option:checked { background: #53c5e0 !important; color: #06232c !important; }
#tarpForm .input-field[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1) brightness(1.35); opacity: .95; cursor: pointer; }

.opt-btn, .opt-btn-wrap {
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

.tarp-qty-stepper {
    background: rgba(255, 255, 255, 0.04) !important;
    border: 1px solid rgba(83, 197, 224, 0.24) !important;
    border-radius: 10px;
}
.tarp-qty-stepper button {
    background: rgba(83, 197, 224, 0.12) !important;
    color: #d8edf5 !important;
}
.tarp-qty-stepper button:hover { background: rgba(83, 197, 224, 0.2) !important; }
.tarp-qty-stepper input { color: #f8fafc !important; }
#quantity-input::-webkit-outer-spin-button,
#quantity-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
#quantity-input { -moz-appearance: textfield; appearance: textfield; }

.tarp-notes {
    overflow-y: auto;
    resize: vertical;
    min-height: 110px;
    max-height: 220px;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: rgba(83, 197, 224, 0.65) rgba(255, 255, 255, 0.08);
}
.tarp-notes::-webkit-scrollbar { width: 10px; }
.tarp-notes::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.08); border-radius: 999px; }
.tarp-notes::-webkit-scrollbar-thumb {
    background: rgba(83, 197, 224, 0.65);
    border-radius: 999px;
    border: 2px solid rgba(10, 37, 48, 0.55);
}
.tarp-notes::-webkit-scrollbar-thumb:hover { background: rgba(83, 197, 224, 0.85); }

.tshirt-actions-row { display: flex; justify-content: flex-end; align-items: center; gap: .75rem; margin-top: 1.1rem; flex-wrap: wrap; }
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
.tshirt-btn-secondary { background: rgba(255,255,255,.05) !important; border: 1px solid rgba(83, 197, 224, .28) !important; color: #d9e6ef !important; }
.tshirt-btn-secondary:hover { background: rgba(83,197,224,.14) !important; border-color: rgba(83,197,224,.52) !important; color: #fff !important; }
.tshirt-btn-primary { border: none; background: linear-gradient(135deg, #53C5E0, #32a1c4) !important; color: #fff !important; text-transform: uppercase; letter-spacing: .02em; cursor: pointer; box-shadow: 0 10px 22px rgba(50,161,196,0.3); }
.tshirt-btn:active { transform: translateY(1px) scale(0.99); }

.finish-tooltip { background: rgba(10, 37, 48, 0.98) !important; border-color: rgba(83, 197, 224, 0.3) !important; color: #e2f2f8 !important; }
.info-icon:hover, .info-icon:focus { color: #53c5e0 !important; }

/* Final dark-theme enforcement: remove white UI artifacts */
#tarpForm .input-field,
#tarpForm select.input-field,
#tarpForm textarea.input-field,
#tarpForm input[type="date"].input-field,
#tarpForm input[type="number"].input-field,
#tarpForm input[type="text"].input-field {
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #e9f6fb !important;
    box-shadow: none !important;
}
#tarpForm .input-field:hover {
    background: rgba(15, 48, 62, 0.96) !important;
    border-color: rgba(83, 197, 224, 0.4) !important;
}
#tarpForm .input-field:focus,
#tarpForm .input-field:focus-visible {
    background: rgba(16, 52, 67, 0.98) !important;
    border-color: #53c5e0 !important;
    box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.16) !important;
    outline: none !important;
}
#tarpForm .input-field::placeholder {
    color: #a9c1cd !important;
}

#tarpForm .opt-btn,
#tarpForm .opt-btn-wrap {
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
    color: #d6eaf3 !important;
    box-shadow: none !important;
}
#tarpForm .opt-btn:hover,
#tarpForm .opt-btn-wrap:hover {
    background: rgba(18, 56, 72, 0.95) !important;
    border-color: rgba(83, 197, 224, 0.48) !important;
}
#tarpForm .opt-btn.active,
#tarpForm .opt-btn-wrap:has(input:checked) {
    background: linear-gradient(135deg, rgba(83, 197, 224, 0.24), rgba(50, 161, 196, 0.22)) !important;
    border-color: #53c5e0 !important;
    color: #f5fcff !important;
    box-shadow: 0 0 0 2px rgba(83, 197, 224, 0.2) !important;
}

/* Custom dark radios: no default white circles */
#tarpForm .opt-btn-wrap input[type="radio"] {
    -webkit-appearance: none;
    appearance: none;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 1px solid rgba(83, 197, 224, 0.62);
    background: rgba(8, 31, 41, 0.96);
    margin-right: 0.45rem;
    position: relative;
    box-shadow: none !important;
}
#tarpForm .opt-btn-wrap input[type="radio"]::after {
    content: "";
    position: absolute;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    background: transparent;
    transition: background 0.15s ease;
}
#tarpForm .opt-btn-wrap input[type="radio"]:checked {
    border-color: #53c5e0;
    background: rgba(14, 47, 61, 0.98);
}
#tarpForm .opt-btn-wrap input[type="radio"]:checked::after {
    background: #53c5e0;
}

#tarpForm .tarp-qty-stepper {
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
}
#tarpForm .tarp-qty-stepper button {
    background: rgba(19, 62, 79, 0.95) !important;
    color: #d8edf5 !important;
}
#tarpForm .tarp-qty-stepper button:hover {
    background: rgba(83, 197, 224, 0.2) !important;
}
#tarpForm .tarp-qty-stepper input {
    background: rgba(13, 43, 56, 0.92) !important;
    color: #f8fafc !important;
}

/* Notes */
#tarpForm .tarp-notes {
    background: rgba(13, 43, 56, 0.92) !important;
    border: 1px solid rgba(83, 197, 224, 0.26) !important;
}
#tarpForm .tarp-notes::placeholder {
    color: #a9c1cd !important;
}

@media (max-width: 640px) {
    .tarp-option-row-3 { grid-template-columns: 1fr; }
    .tarp-option-row-2 { grid-template-columns: 1fr; align-items: start; }
    .need-qty-card .need-qty-row { flex-direction: column; align-items: stretch; }
    .opt-btn-group { flex-wrap: wrap; }
    .opt-btn-inline { flex-wrap: wrap; }
    .tshirt-actions-row { flex-direction: column; align-items: stretch; }
    .tshirt-btn { width: 100%; }
    .dim-others-row { grid-template-columns: 1fr; }
    .dim-sep { height: auto; justify-self: center; }
}
</style>

<script>
let dimensionMode = 'preset';

function openPricelistModal() {
    document.getElementById('pricelist-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closePricelistModal() {
    document.getElementById('pricelist-modal').style.display = 'none';
    document.body.style.overflow = '';
}

function syncDimensionToHidden() {
    const wh = document.getElementById('width_hidden');
    const hh = document.getElementById('height_hidden');
    if (dimensionMode === 'preset') {
        const btn = document.querySelector('.opt-btn.active');
        wh.value = btn ? btn.dataset.width : '';
        hh.value = btn ? btn.dataset.height : '';
    } else {
        wh.value = document.getElementById('custom_width').value;
        hh.value = document.getElementById('custom_height').value;
    }
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
    if (window.__tarpValidationTriggered) checkTarpFormValid();
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = '';
    syncDimensionToHidden();
    if (window.__tarpValidationTriggered) checkTarpFormValid();
}

function clearFieldError(container) {
    if (!container) return;
    const err = container.querySelector('.field-error');
    container.classList.remove('is-invalid');
    if (err) { err.textContent = ''; err.style.display = 'none'; }
}
function setFieldError(container, message) {
    if (!container) return;
    let err = container.querySelector('.field-error');
    if (!err) { err = document.createElement('div'); err.className = 'field-error'; container.appendChild(err); }
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
function checkTarpFormValid() {
    syncDimensionToHidden();
    const hasDimSelection = document.querySelector('.opt-btn.active');
    const cw = document.getElementById('custom_width');
    const ch = document.getElementById('custom_height');
    const branch = document.querySelector('select[name="branch_id"]');
    const finish = document.querySelector('input[name="finish"]:checked');
    const lamination = document.querySelector('input[name="lamination"]:checked');
    const eyelets = document.querySelector('input[name="with_eyelets"]:checked');
    const file = document.getElementById('design_file');
    const layout = document.querySelector('input[name="layout"]:checked');
    const neededDate = document.getElementById('needed_date');
    const qty = parseInt(document.getElementById('quantity-input')?.value, 10) || 0;
    const cBranch = branch?.closest('.mb-4');
    const cDim = document.getElementById('card-dimensions');
    const cFinish = document.getElementById('card-finish');
    const cLamination = document.getElementById('card-lamination');
    const cEyelets = document.getElementById('card-eyelets');
    const cUpload = document.getElementById('card-upload');
    const cLayout = document.getElementById('card-layout');
    const cDateQty = document.getElementById('card-date-qty');

    let dimOk = !!hasDimSelection;
    if (dimensionMode === 'others') {
        dimOk = dimOk && cw && ch && cw.value.trim() && ch.value.trim();
    }
    const uploadOk = file && file.files && file.files.length > 0;
    const layoutOk = !!layout;
    const dateQtyOk = neededDate?.value.trim() && qty >= 1;
    const branchOk = !!branch?.value;
    const finishOk = !!finish;
    const laminationOk = !!lamination;
    const eyeletsOk = !!eyelets;

    if (window.__tarpValidationTriggered) {
        setFieldError(cBranch, !branchOk ? 'This field is required' : '');
        setFieldError(cDim, !dimOk ? (dimensionMode === 'others' && (!cw?.value.trim() || !ch?.value.trim()) ? 'Please enter Width and Height' : 'Please select a dimension') : '');
        setFieldError(cFinish, !finishOk ? 'This field is required' : '');
        setFieldError(cLamination, !laminationOk ? 'This field is required' : '');
        setFieldError(cEyelets, !eyeletsOk ? 'This field is required' : '');
        setFieldError(cUpload, !uploadOk ? 'This field is required' : '');
        setFieldError(cLayout, !layoutOk ? 'This field is required' : '');
        setFieldError(cDateQty, !dateQtyOk ? 'This field is required' : '');
    } else {
        [cBranch, cDim, cFinish, cLamination, cEyelets, cUpload, cLayout, cDateQty].forEach(clearFieldError);
    }
    return branchOk && dimOk && finishOk && laminationOk && eyeletsOk && uploadOk && layoutOk && dateQtyOk;
}

document.getElementById('tarpForm').addEventListener('submit', function(e) {
    window.__tarpValidationTriggered = true;
    if (!checkTarpFormValid()) {
        e.preventDefault();
        return false;
    }
});

['custom_width','custom_height'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9.]/g, '').slice(0, 6);
        syncDimensionToHidden();
        if (window.__tarpValidationTriggered) checkTarpFormValid();
    });
});
document.getElementById('tarpForm').addEventListener('change', checkTarpFormValid);
document.getElementById('tarpForm').addEventListener('input', checkTarpFormValid);
document.getElementById('design_file')?.addEventListener('change', checkTarpFormValid);
document.getElementById('quantity-input')?.addEventListener('input', checkTarpFormValid);
document.getElementById('needed_date')?.addEventListener('change', checkTarpFormValid);
document.getElementById('tarpForm').addEventListener('invalid', function(e) { e.preventDefault(); }, true);

function increaseQty() {
    const i = document.getElementById('quantity-input');
    i.value = Math.min(999, (parseInt(i.value) || 1) + 1);
    if (window.__tarpValidationTriggered) checkTarpFormValid();
}
function decreaseQty() {
    const i = document.getElementById('quantity-input');
    const v = parseInt(i.value) || 1;
    if (v > 1) { i.value = v - 1; if (window.__tarpValidationTriggered) checkTarpFormValid(); }
}

var finishIcon = document.getElementById('finish-info-icon');
var finishTooltip = document.getElementById('finish-tooltip');
if (finishIcon && finishTooltip) {
    finishIcon.addEventListener('mouseenter', function() { finishTooltip.classList.add('visible'); });
    finishIcon.addEventListener('mouseleave', function() { finishTooltip.classList.remove('visible'); });
    finishIcon.addEventListener('focus', function() { finishTooltip.classList.add('visible'); });
    finishIcon.addEventListener('blur', function() { finishTooltip.classList.remove('visible'); });
    finishIcon.addEventListener('click', function(e) {
        e.preventDefault();
        finishTooltip.classList.toggle('visible');
    });
    document.addEventListener('click', function(e) {
        if (!finishIcon.contains(e.target) && !finishTooltip.contains(e.target)) finishTooltip.classList.remove('visible');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
