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
            $tmp_dest = __DIR__ . '/../uploads/temp/' . $new_name;
            
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

$page_title = 'Order Tarpaulin - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 tarp-order-container">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Tarpaulin Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- View Price List Button -->
        <div class="mb-6">
            <button type="button" onclick="openPricelistModal()" class="view-pricelist-btn">
                View Price List
            </button>
        </div>

        <div class="card tarp-form-card">
            <form action="" method="POST" enctype="multipart/form-data" id="tarpForm">
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
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dimensions *</label>
                    <p class="dim-feet-note">(All values are in feet)</p>
                    <div class="opt-btn-group dim-preset-row" style="margin-bottom: 1rem;">
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

                <!-- Row 1: Finish Type + Lamination + Eyelets (3 columns, one row) -->
                <div class="tarp-option-row-3">
                    <div class="tarp-option-col">
                        <label class="label-with-info">Finish Type <span class="info-icon" id="finish-info-icon" tabindex="0" role="button" aria-label="Finish type info">ⓘ</span></label>
                        <div class="finish-tooltip" id="finish-tooltip" role="tooltip">
                            <div class="tooltip-row"><strong>Glossy:</strong> Shiny finish · More vibrant colors · Reflective under light</div>
                            <div class="tooltip-row"><strong>Matte:</strong> Non-reflective · Softer look · Better readability outdoors</div>
                        </div>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="finish" value="Matte"> <span>Matte</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="finish" value="Glossy"> <span>Glossy</span></label>
                        </div>
                    </div>
                    <div class="tarp-option-col">
                        <label>Lamination</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="With Laminate"> <span>With Laminate</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="lamination" value="Without Laminate"> <span>Without Laminate</span></label>
                        </div>
                    </div>
                    <div class="tarp-option-col">
                        <label>Eyelets</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="with_eyelets" value="Yes"> <span>Yes</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="with_eyelets" value="No"> <span>No</span></label>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Layout + Quantity (2 columns) -->
                <div class="tarp-option-row-2">
                    <div class="tarp-option-col">
                        <label>Layout</label>
                        <div class="opt-btn-group opt-btn-inline">
                            <label class="opt-btn-wrap"><input type="radio" name="layout" value="With Layout"> <span>With Layout</span></label>
                            <label class="opt-btn-wrap"><input type="radio" name="layout" value="Without Layout"> <span>Without Layout</span></label>
                        </div>
                    </div>
                    <div class="tarp-option-col tarp-qty-col">
                        <label>Quantity *</label>
                        <div class="tarp-qty-stepper">
                            <button type="button" onclick="decreaseQty()">−</button>
                            <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                            <button type="button" onclick="increaseQty()">+</button>
                        </div>
                    </div>
                </div>

                <!-- Needed Date + Upload Design (one row) -->
                <div class="tarp-date-upload-row mb-4">
                    <div class="tarp-option-col">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date * <span class="text-gray-500 font-normal">(dd/mm/yyyy)</span></label>
                        <input type="date" name="needed_date" id="needed_date" class="input-field" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="tarp-option-col">
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

<!-- Pricelist Modal -->
<div id="pricelist-modal" style="display: none; position: fixed; inset: 0; z-index: 99999; align-items: center; justify-content: center; padding: 1.5rem; background: rgba(0,0,0,0.5);">
    <div onclick="closePricelistModal()" style="position: absolute; inset: 0;"></div>
    <div style="position: relative; background: #fff; border-radius: 1rem; max-width: 72vw; max-height: 90vh; overflow: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.3);">
        <button onclick="closePricelistModal()" style="position: absolute; top: 0.75rem; right: 0.75rem; z-index: 10; width: 36px; height: 36px; border: none; background: #f3f4f6; border-radius: 50%; cursor: pointer; font-size: 1.25rem; line-height: 1; color: #374151;">×</button>
        <?php if ($pricelist_url): ?>
        <img src="<?php echo htmlspecialchars($pricelist_url); ?>" alt="Tarpaulin Pricelist" style="width: 100%; max-width: 640px; display: block;">
        <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: #6b7280;">Pricelist image not found.</div>
        <?php endif; ?>
    </div>
</div>

<style>
.tarp-order-container { max-width: 860px; }
.tarp-form-card { overflow: hidden; }
.view-pricelist-btn { padding: 0.6rem 1.25rem; background: #0a2530; color: #fff; font-weight: 700; font-size: 0.9rem; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; }
.view-pricelist-btn:hover { background: #0d3038; transform: translateY(-1px); }

.dim-feet-note { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.75rem; }
.dim-others-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
.opt-btn-inline { display: inline-flex; gap: 0.5rem; flex-wrap: nowrap; }
.opt-btn-group.opt-btn-inline { flex-wrap: nowrap !important; }
.dim-label { font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem; }
.dim-sep { color: #9ca3af; font-weight: 600; margin-bottom: 0.5rem; align-self: center; }

/* Row 1: Finish Type + Lamination + Eyelets - give Lamination more space (longer labels) */
.tarp-option-row-3 { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.4fr) minmax(0, 0.7fr); gap: 1rem 1.5rem; align-items: start; margin-bottom: 1rem; }
/* Uniform button widths within each field - each pair shares space equally */
.tarp-option-row-3 .opt-btn-group .opt-btn-wrap { flex: 1 1 0; min-width: 0; }
/* Row 2: 2 columns (Layout, Quantity) */
.tarp-option-row-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem 1.5rem; align-items: start; margin-bottom: 1rem; }
.tarp-date-upload-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem 1.5rem; align-items: end; }
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

.opt-btn, .opt-btn-wrap { padding: 0.5rem 1rem; min-width: 100px; text-align: center; justify-content: center; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; color: #374151; transition: all 0.2s ease; white-space: nowrap; }
.opt-btn:hover, .opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn.active, .opt-btn-wrap:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: #fff; }
.opt-btn-wrap { display: inline-flex; align-items: center; }
.opt-btn-wrap input { margin-right: 0.4rem; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.tarp-notes-wrap { max-width: 100%; overflow: hidden; }
.tarp-notes { width: 100%; max-width: 100%; box-sizing: border-box; }
.tarp-qty-col { max-width: 100%; }
.tarp-qty-stepper { display: flex; align-items: center; width: fit-content; max-width: 100%; height: 42px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; overflow: hidden; }
.tarp-qty-stepper button { flex: 0 0 42px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.2rem; cursor: pointer; }
.tarp-qty-stepper input { flex: 1; min-width: 50px; max-width: 80px; border: none; text-align: center; font-weight: 700; font-size: 1rem; outline: none; background: transparent; }
@media (max-width: 640px) {
    .tarp-option-row-3 { grid-template-columns: 1fr; }
    .tarp-option-row-2 { grid-template-columns: 1fr; align-items: start; }
    .tarp-date-upload-row { grid-template-columns: 1fr; }
    .opt-btn-group { flex-wrap: wrap; }
    .opt-btn-inline { flex-wrap: wrap; }
    .dim-others-row { flex-direction: column; align-items: stretch; }
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
}

function selectDimensionOthers(e) {
    e.preventDefault();
    dimensionMode = 'others';
    document.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('dim-others-btn').classList.add('active');
    document.getElementById('dim-others-inputs').style.display = '';
    syncDimensionToHidden();
}

document.getElementById('tarpForm').addEventListener('submit', function(e) {
    syncDimensionToHidden();
    const hasDimSelection = document.querySelector('.opt-btn.active');
    if (!hasDimSelection) {
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
    } else {
        const btn = document.querySelector('.opt-btn.active');
        if (!btn || !btn.dataset.width || !btn.dataset.height) {
            e.preventDefault();
            alert('Please select a dimension preset or fill Others.');
            return false;
        }
    }
});

['custom_width','custom_height'].forEach(id => {
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
