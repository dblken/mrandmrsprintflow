<?php
/**
 * Sintraboard & Standees - Service Order Form
 * PrintFlow - Clean flow: Product Type → Dimensions → Options → Upload → Quantity + Notes
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';

// Common Sintraboard dimension presets (inches)
$dimension_presets = [
    '8 x 10'  => ['w' => 8,  'h' => 10],
    '12 x 18' => ['w' => 12, 'h' => 18],
    '18 x 24' => ['w' => 18, 'h' => 24],
    '24 x 36' => ['w' => 24, 'h' => 36],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $sintra_type = trim($_POST['sintra_type'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $unit = trim($_POST['unit'] ?? 'in');
    $thickness = trim($_POST['thickness'] ?? '');
    $lamination = trim($_POST['lamination'] ?? 'Without Lamination');
    $layout = trim($_POST['layout'] ?? 'Without Layout');
    $notes = trim($_POST['notes'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $quantity = max(1, min(999, $quantity));

    $valid_types = ['Flat Type', '2D Type (with Frame)', 'Standee (Back Stand Support)'];
    if (empty($sintra_type) || !in_array($sintra_type, $valid_types)) {
        $error = 'Please select a Sintraboard Type.';
    } elseif (empty($dimensions)) {
        $error = 'Please specify dimensions.';
    } elseif (empty($thickness)) {
        $error = 'Please select thickness.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            // Parse dimensions for width/height
            $width = ''; $height = '';
            if (preg_match('/^(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)$/i', $dimensions, $m)) {
                $width = $m[1]; $height = $m[2];
            } else {
                $parts = preg_split('/[\s,]+/', $dimensions, 2);
                if (count($parts) >= 2) { $width = trim($parts[0]); $height = trim($parts[1]); }
            }

            if (empty($width) || empty($height)) {
                $error = 'Please enter valid dimensions (e.g. 12 x 18).';
            } else {
                $tmp_dir = __DIR__ . '/../uploads/temp';
                if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0755, true);
                $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
                $tmp_filename = uniqid('sintra_') . '.' . $ext;
                $tmp_path = $tmp_dir . '/' . $tmp_filename;
                file_put_contents($tmp_path, file_get_contents($_FILES['design_file']['tmp_name']));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
                finfo_close($finfo);

                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

                $product_id = ($sintra_type === 'Flat Type') ? 51 : 54;
                $product_name = $sintra_type;
                $sintra_price = ($sintra_type === 'Flat Type') ? 150.00 : 800.00;

                $item_key = $product_id . '_' . time();
                $_SESSION['cart'][$item_key] = [
                    'product_id'       => $product_id,
                    'branch_id'        => $branch_id,
                    'name'             => $product_name,
                    'category'         => 'Sintraboard & Standees',
                    'price'            => $sintra_price,
                    'quantity'         => $quantity,
                    'image'            => '📦',
                    'customization'    => [
                        'Sintra_Type' => $sintra_type,
                        'Dimensions'  => $dimensions,
                        'Unit'        => $unit,
                        'Width'       => $width,
                        'Height'      => $height,
                        'Thickness'   => $thickness,
                        'Lamination'  => $lamination,
                        'Layout'      => $layout,
                        'notes'       => $notes,
                    ],
                    'design_notes'     => $notes,
                    'design_tmp_path'  => $tmp_path,
                    'design_mime'     => $mime,
                    'design_name'     => $_FILES['design_file']['name'],
                    'reference_tmp_path' => null,
                    'reference_mime'  => null,
                    'reference_name'  => null,
                ];

                if (isset($_POST['buy_now'])) {
                    redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
                } else {
                    redirect(BASE_URL . '/customer/cart.php');
                }
            }
        }
    }
}

$page_title = 'Order Sintraboard - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
$qty_default = (int)($_GET['qty'] ?? 1);
$qty_default = max(1, min(999, $qty_default));
?>
<div class="min-h-screen py-8">
    <div class="sintra-container">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Sintraboard & Standees</h1>

        <form method="POST" enctype="multipart/form-data" id="sintraForm" class="sintra-form">
            <?php echo csrf_field(); ?>

            <div class="sintra-main">
                <!-- Branch -->
                <div class="sintra-field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1. Sintraboard Type -->
                <div class="sintra-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sintraboard Type *</label>
                    <div class="opt-btn-group">
                        <?php
                        $types = ['Flat Type', '2D Type (with Frame)', 'Standee (Back Stand Support)'];
                        $sel_type = $_POST['sintra_type'] ?? $_GET['sintra_type'] ?? '';
                        foreach ($types as $t):
                            $checked = ($sel_type === $t) ? 'checked' : '';
                        ?>
                        <label class="opt-btn-wrap">
                            <input type="radio" name="sintra_type" value="<?php echo htmlspecialchars($t); ?>" class="sintra-radio" required onchange="sintraUpdateOptionVisuals(this)" <?php echo $checked; ?>>
                            <span><?php echo htmlspecialchars($t); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. Dimensions -->
                <div class="sintra-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions *</label>
                    <p class="sintra-hint mb-2">Select a preset or enter custom size.</p>
                    <div class="opt-btn-group sintra-dim-presets" id="sintraDimPresets">
                        <?php foreach ($dimension_presets as $label => $d): ?>
                        <label class="opt-btn-wrap sintra-dim-btn" data-w="<?php echo $d['w']; ?>" data-h="<?php echo $d['h']; ?>">
                            <input type="radio" name="dimension_preset" value="<?php echo htmlspecialchars($label); ?>" class="sintra-radio" onchange="sintraSelectDimension('<?php echo htmlspecialchars($label); ?>', <?php echo $d['w']; ?>, <?php echo $d['h']; ?>)">
                            <span><?php echo htmlspecialchars($label); ?> in</span>
                        </label>
                        <?php endforeach; ?>
                        <label class="opt-btn-wrap sintra-dim-btn" data-others="1">
                            <input type="radio" name="dimension_preset" value="Others" class="sintra-radio" onchange="sintraSelectDimensionOthers()">
                            <span>Others</span>
                        </label>
                    </div>
                    <input type="hidden" name="dimensions" id="sintra_dimensions" value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>" required>
                    <div id="sintraDimOthersWrap" class="sintra-dim-others mt-3" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Custom Size (Width × Height)</label>
                        <input type="text" id="sintraDimOthersInput" class="input-field" placeholder="e.g. 10 x 14" oninput="sintraSyncDimOthers()">
                    </div>
                    <div class="sintra-row mt-2">
                        <div class="sintra-field">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit *</label>
                            <select name="unit" id="sintra_unit" class="input-field">
                                <option value="in" <?php echo ($_POST['unit'] ?? 'in') === 'in' ? 'selected' : ''; ?>>Inches (in)</option>
                                <option value="ft" <?php echo ($_POST['unit'] ?? '') === 'ft' ? 'selected' : ''; ?>>Feet (ft)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3. Options: Lamination, Layout, Thickness -->
                <div class="sintra-row">
                    <div class="sintra-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lamination</label>
                        <div class="opt-btn-group">
                            <label class="opt-btn-wrap">
                                <input type="radio" name="lamination" value="With Lamination" class="sintra-radio" onchange="sintraUpdateOptionVisuals(this)" <?php echo ($_POST['lamination'] ?? '') === 'With Lamination' ? 'checked' : ''; ?>>
                                <span>With Lamination</span>
                            </label>
                            <label class="opt-btn-wrap">
                                <input type="radio" name="lamination" value="Without Lamination" class="sintra-radio" onchange="sintraUpdateOptionVisuals(this)" <?php echo ($_POST['lamination'] ?? 'Without Lamination') === 'Without Lamination' ? 'checked' : ''; ?>>
                                <span>Without Lamination</span>
                            </label>
                        </div>
                    </div>
                    <div class="sintra-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Layout</label>
                        <div class="opt-btn-group">
                            <label class="opt-btn-wrap">
                                <input type="radio" name="layout" value="With Layout" class="sintra-radio" onchange="sintraUpdateOptionVisuals(this)" <?php echo ($_POST['layout'] ?? '') === 'With Layout' ? 'checked' : ''; ?>>
                                <span>With Layout</span>
                            </label>
                            <label class="opt-btn-wrap">
                                <input type="radio" name="layout" value="Without Layout" class="sintra-radio" onchange="sintraUpdateOptionVisuals(this)" <?php echo ($_POST['layout'] ?? 'Without Layout') === 'Without Layout' ? 'checked' : ''; ?>>
                                <span>Without Layout</span>
                            </label>
                        </div>
                    </div>
                    <div class="sintra-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Thickness *</label>
                        <select name="thickness" class="input-field" required>
                            <option value="3mm" <?php echo ($_POST['thickness'] ?? '5mm') === '3mm' ? 'selected' : ''; ?>>3mm</option>
                            <option value="5mm" <?php echo ($_POST['thickness'] ?? '5mm') === '5mm' ? 'selected' : ''; ?>>5mm</option>
                            <option value="10mm" <?php echo ($_POST['thickness'] ?? '5mm') === '10mm' ? 'selected' : ''; ?>>10mm</option>
                        </select>
                    </div>
                </div>

                <!-- 4. Upload Image -->
                <div class="sintra-field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <div class="sintra-file-wrap">
                        <input type="file" name="design_file" id="sintra_design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field sintra-file-input" required>
                    </div>
                    <div id="sintraPreviewWrap" class="mt-2" style="display: none;">
                        <img id="sintraPreviewImg" src="" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 2px solid #e5e7eb; object-fit: contain;">
                    </div>
                </div>

                <!-- 5. Quantity + Notes -->
                <div class="sintra-row">
                    <div class="sintra-field" style="flex: 0 0 auto;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Required *</label>
                        <div class="sintra-qty-stepper">
                            <button type="button" onclick="sintraQtyDown()" class="sintra-qty-btn">−</button>
                            <input type="number" name="quantity" id="sintra_quantity" min="1" value="<?php echo $qty_default; ?>" class="sintra-qty-input-inline" oninput="sintraQtyClamp()">
                            <button type="button" onclick="sintraQtyUp()" class="sintra-qty-btn">+</button>
                        </div>
                    </div>
                    <div class="sintra-field" style="flex: 1; min-width: 200px;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="input-field" placeholder="Any special requests?"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="sintra-actions">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" class="sintra-btn-secondary">Back to Services</a>
                    <button type="submit" name="buy_now" value="1" class="sintra-btn-primary">Buy Now</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function sintraUpdateOptionVisuals(input) {
    const name = input.name;
    document.querySelectorAll('input[name="' + name + '"]').forEach(function(r) {
        const wrap = r.closest('.opt-btn-wrap');
        if (wrap) { wrap.classList.remove('active'); if (r.checked) wrap.classList.add('active'); }
    });
}

function sintraSelectDimension(label, w, h) {
    document.getElementById('sintra_dimensions').value = w + ' x ' + h;
    document.getElementById('sintraDimOthersWrap').style.display = 'none';
    document.getElementById('sintraDimOthersInput').value = '';
    document.querySelectorAll('.sintra-dim-btn').forEach(function(b) { b.classList.remove('active'); });
    var btn = document.querySelector('.sintra-dim-btn[data-w="' + w + '"][data-h="' + h + '"]');
    if (btn) btn.classList.add('active');
}

function sintraSelectDimensionOthers() {
    document.getElementById('sintraDimOthersWrap').style.display = 'block';
    document.getElementById('sintra_dimensions').value = '';
    document.querySelectorAll('.sintra-dim-btn').forEach(function(b) { b.classList.remove('active'); });
    var others = document.querySelector('.sintra-dim-btn[data-others="1"]');
    if (others) others.classList.add('active');
}

function sintraSyncDimOthers() {
    document.getElementById('sintra_dimensions').value = document.getElementById('sintraDimOthersInput').value.trim();
}

function sintraQtyUp() {
    var q = document.getElementById('sintra_quantity');
    q.value = Math.min(999, (parseInt(q.value) || 1) + 1);
}
function sintraQtyDown() {
    var q = document.getElementById('sintra_quantity');
    q.value = Math.max(1, (parseInt(q.value) || 1) - 1);
}
function sintraQtyClamp() {
    var q = document.getElementById('sintra_quantity');
    var v = parseInt(q.value) || 1;
    q.value = Math.max(1, Math.min(999, v));
}

// File preview
document.getElementById('sintra_design_file').addEventListener('change', function(e) {
    var file = e.target.files[0];
    var wrap = document.getElementById('sintraPreviewWrap');
    var img = document.getElementById('sintraPreviewImg');
    wrap.style.display = 'none';
    if (file && /^image\/(jpeg|jpg|png)$/i.test(file.type)) {
        var r = new FileReader();
        r.onload = function() { img.src = r.result; wrap.style.display = 'block'; };
        r.readAsDataURL(file);
    }
});

// Form validation
document.getElementById('sintraForm').addEventListener('submit', function(e) {
    var dim = document.getElementById('sintra_dimensions').value.trim();
    if (!dim) {
        e.preventDefault();
        alert('Please specify dimensions.');
        return false;
    }
    if (!document.querySelector('input[name="sintra_type"]:checked')) {
        e.preventDefault();
        alert('Please select a Sintraboard Type.');
        return false;
    }
});

document.querySelectorAll('.sintra-radio').forEach(function(r) {
    r.addEventListener('change', function() { sintraUpdateOptionVisuals(this); });
});
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.opt-btn-wrap').forEach(function(w) {
        if (w.querySelector('input:checked')) w.classList.add('active');
    });
});
</script>

<style>
.sintra-container { max-width: 640px; margin: 0 auto; padding: 0 1rem; }
.sintra-main { display: flex; flex-direction: column; gap: 1.25rem; }
.sintra-field { min-width: 0; }
.sintra-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.sintra-row .sintra-field { flex: 1; min-width: 120px; }
.sintra-hint { font-size: 0.75rem; color: #9ca3af; }
.sintra-dim-presets { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.sintra-dim-others { }

.opt-btn-wrap { padding: 0.55rem 1rem; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.875rem; color: #374151; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.4rem; }
.opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn-wrap:has(input:checked), .opt-btn-wrap.active { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: #fff; }
.opt-btn-wrap input { margin: 0; position: absolute; opacity: 0; pointer-events: none; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }

.sintra-qty-stepper { display: inline-flex; align-items: center; height: 42px; border: 2px solid #d1d5db; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.2s; }
.sintra-qty-stepper:focus-within { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.sintra-qty-btn { flex: 0 0 40px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 700; font-size: 1.1rem; cursor: pointer; transition: background 0.2s; }
.sintra-qty-btn:hover { background: #e5e7eb; }
.sintra-qty-input-inline { flex: 1; min-width: 50px; max-width: 80px; border: none; text-align: center; font-weight: 700; outline: none; background: transparent; }

.sintra-file-wrap { padding: 0.25rem 0; }
.sintra-file-wrap .sintra-file-input { width: 100%; padding: 0.5rem 0.75rem; border: 2px solid #d1d5db; border-radius: 8px; background: #f9fafb; font-size: 0.875rem; }
.sintra-file-wrap .sintra-file-input:hover { background: #f3f4f6; }
.sintra-file-wrap .sintra-file-input:focus { outline: none; border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.1); }

.sintra-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; padding-top: 0.5rem; }
.sintra-btn-primary { height: 48px; min-width: 140px; padding: 0 1.25rem; background: #0a2530; color: #fff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 0.02em; transition: all 0.2s; }
.sintra-btn-primary:hover { background: #0d3038; transform: translateY(-1px); }
.sintra-btn-secondary { height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none; transition: all 0.2s; }
.sintra-btn-secondary:hover { background: #f1f5f9; }
@media (max-width: 640px) { .sintra-row { flex-direction: column; } .sintra-row .sintra-field { min-width: 100%; } }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
