<?php
/**
 * T-Shirt Printing - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

// Print placement options with image mapping (placement name -> image filename)
// One image per unique print type; Left/Right Chest combined to avoid duplicate image
$placement_options = [
    'Front Center Print' => 'Front Center Print.webp',
    'Back Upper Print' => 'Back Upper Print.webp',
    'Left/Right Chest Print' => 'Left Right Chest Print.webp',
    'Bottom Hem Print' => 'Buttom Hem Print.webp',
    'Sleeve Print' => 'Sleeve Print.webp',
    'Long Sleeve Arm Print' => 'Long Sleeve Arm Print.webp',
];

$img_base = (defined('BASE_URL') ? BASE_URL : '/printflow') . '/public/assets/images/tshirt_replacement/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $shirt_source = trim($_POST['shirt_source'] ?? '');
    $shirt_type = trim($_POST['shirt_type'] ?? '');
    $shirt_type_other = trim($_POST['shirt_type_other'] ?? '');
    $shirt_color = trim($_POST['shirt_color'] ?? '');
    $color_other = trim($_POST['color_other'] ?? '');
    $size = trim($_POST['size'] ?? '');
    $print_placement = trim($_POST['print_placement'] ?? '');
    $lamination = trim($_POST['lamination'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');

    $color_display = ($shirt_color === 'Other') ? $color_other : $shirt_color;
    $shirt_type_display = ($shirt_type === 'Others') ? $shirt_type_other : $shirt_type;

    $shop_provides = ($shirt_source === 'Shop will provide the shirt');
    $proceed = false;
    if (empty($shirt_source)) {
        $error = 'Please select whether the shop or customer will provide the shirt.';
    } elseif ($shop_provides) {
        if (empty($shirt_type_display) || empty($color_display) || empty($size) || empty($print_placement) || empty($lamination) || $quantity < 1) {
            $error = 'Please fill in Shirt Type, Color, Size, Print Placement, Lamination, and Quantity.';
        } elseif ($shirt_type === 'Others' && empty($shirt_type_other)) {
            $error = 'Please enter your custom shirt type.';
        } elseif ($shirt_color === 'Other' && empty($color_other)) {
            $error = 'Please enter your custom shirt color.';
        } else {
            $proceed = true;
        }
    } else {
        if (empty($color_display) || empty($print_placement) || empty($lamination) || $quantity < 1) {
            $error = 'Please fill in Color, Print Placement, Lamination, and Quantity.';
        } elseif ($shirt_color === 'Other' && empty($color_other)) {
            $error = 'Please enter your custom shirt color.';
        } else {
            $proceed = true;
        }
    }

    if ($proceed && (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'Please upload your design file.';
        $proceed = false;
    }

    if ($proceed) {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'tshirt_' . time() . '_' . rand(100, 999);
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = __DIR__ . '/../uploads/temp/' . $new_name;
            
            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'name' => 'T-Shirt Printing',
                    'price' => 350.00,
                    'quantity' => $quantity,
                    'category' => 'T-Shirts',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'shirt_source' => $shirt_source,
                        'shirt_type' => $shirt_type_display,
                        'shirt_color' => $color_display,
                        'size' => $size,
                        'print_placement' => $print_placement,
                        'lamination' => $lamination,
                        'quantity' => $quantity,
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

$page_title = 'Order T-Shirt - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">T-Shirt Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card">
            <form action="" method="POST" enctype="multipart/form-data" id="tshirtForm">
                <?php echo csrf_field(); ?>

                <!-- Top Notice -->
                <div class="tshirt-top-notice mb-4">
                    Please choose whether the shirt will be provided by the shop or by the customer. It is recommended that customers provide their own shirt so they can ensure the correct size and preferred quality for their use.
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1. Shirt Source (must be first) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shirt Source *</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_source" value="Shop will provide the shirt"> <span>Shop will provide the shirt</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_source" value="Customer will provide the shirt"> <span>Customer will provide the shirt</span></label>
                    </div>
                    <div id="shop-provides-note" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 0.875rem; color: #92400e;">
                        Additional charges apply since shirt is included. Shirt cost + print cost will be charged.
                    </div>
                </div>

                <!-- 2. Shirt Type (3×2 grid) -->
                <div class="mb-4" id="shirt-type-section">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shirt Type <span id="shirt-type-required-mark">*</span></label>
                    <div class="option-grid option-grid-3x2">
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Crew Neck"> <span>Crew Neck</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="V-Neck"> <span>V-Neck</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Polo"> <span>Polo</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Raglan"> <span>Raglan</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Long Sleeve"> <span>Long Sleeve</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_type" value="Others"> <span>Others</span></label>
                    </div>
                    <div id="shirt-type-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="shirt_type_other" id="shirt_type_other" class="input-field" placeholder="Enter custom shirt type">
                    </div>
                </div>

                <!-- 3. Shirt Color (3×3, Others centered) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shirt Color *</label>
                    <div class="option-grid option-grid-3x3 option-grid-color">
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Black"> <span>Black</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="White"> <span>White</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Red"> <span>Red</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Blue"> <span>Blue</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Navy"> <span>Navy</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="shirt_color" value="Grey"> <span>Grey</span></label>
                        <label class="opt-btn-wrap opt-btn-others"><input type="radio" name="shirt_color" value="Other"> <span>Others</span></label>
                    </div>
                    <div id="color-other-wrap" style="display: none; margin-top: 0.75rem;">
                        <input type="text" name="color_other" id="color_other" class="input-field" placeholder="Enter custom color">
                    </div>
                </div>

                <!-- 4. Size (shown only when Shop provides) -->
                <div class="mb-4" id="size-section" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Size <span id="size-required-mark">*</span></label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap"><input type="radio" name="size" value="S"> <span>S</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="size" value="M"> <span>M</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="size" value="L"> <span>L</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="size" value="XL"> <span>XL</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="size" value="2XL"> <span>2XL</span></label>
                        <label class="opt-btn-wrap"><input type="radio" name="size" value="3XL"> <span>3XL</span></label>
                    </div>
                </div>

                <!-- 5. Print Placement (with hover preview) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Print Placement *</label>
                    <div class="placement-preview-area" id="placement-preview">
                        <div class="placement-preview-placeholder" id="placement-preview-placeholder">Hover over an option to preview</div>
                        <img id="placement-preview-img" class="placement-preview-img" src="" alt="" style="display: none;">
                    </div>
                    <div class="placement-grid">
                        <?php foreach ($placement_options as $name => $img_file): 
                            $img_url = $img_base . rawurlencode($img_file);
                        ?>
                        <label class="placement-card" data-img="<?php echo htmlspecialchars($img_url); ?>" data-name="<?php echo htmlspecialchars($name); ?>">
                            <input type="radio" name="print_placement" value="<?php echo htmlspecialchars($name); ?>" required>
                            <div class="placement-img-wrap">
                                <img src="<?php echo $img_url; ?>" alt="<?php echo htmlspecialchars($name); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="placement-fallback" style="display:none; width:100%; height:100%; background:#f3f4f6; align-items:center; justify-content:center; font-size:0.7rem; color:#6b7280; text-align:center; padding:0.5rem;"><?php echo htmlspecialchars($name); ?></div>
                            </div>
                            <span class="placement-label"><?php echo htmlspecialchars($name); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 6. File Upload -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                </div>

                <!-- 7. Lamination + Quantity (One Row) -->
                <div class="mb-4 lam-qty-row">
                    <div class="lam-qty-lam">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lamination *</label>
                        <div class="lam-options">
                            <label class="opt-btn-wrap lam-opt"><input type="radio" name="lamination" value="With Laminate"> <span>With Laminate</span></label>
                            <label class="opt-btn-wrap lam-opt"><input type="radio" name="lamination" value="Without Laminate"> <span>Without Laminate</span></label>
                        </div>
                    </div>
                    <div class="lam-qty-qty">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <div class="qty-control qty-control-shopee">
                            <button type="button" onclick="tshirtDecreaseQty()" class="qty-btn">−</button>
                            <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                            <button type="button" onclick="tshirtIncreaseQty()" class="qty-btn">+</button>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field" placeholder="Any special instructions..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <!-- 7. Buttons - Bottom-right, side-by-side, no icons, same style as other services -->
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" style="height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none;">Back to Services</a>
                    <button type="submit" name="buy_now" value="1" id="buyNowBtn" disabled style="height: 48px; min-width: 140px; padding: 0 1.25rem; background: #9ca3af; color: #fff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: not-allowed; text-transform: uppercase; letter-spacing: 0.02em; transition: all 0.2s;">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.opt-btn-wrap { padding: 0.65rem 1rem; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; color: #374151; transition: all 0.25s ease; }
.opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn-wrap:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: rgba(10,37,48,0.03); }
.opt-btn-wrap input { margin-right: 0.5rem; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.option-grid { display: grid; gap: 0.5rem; }
.option-grid-3x2 { grid-template-columns: repeat(3, 1fr); }
.option-grid-3x3 { grid-template-columns: repeat(3, 1fr); }
.option-grid-color .opt-btn-others { grid-column: 2; }
.lam-qty-row { display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap; }
.lam-qty-lam { flex: 1; min-width: 0; }
.lam-qty-qty { flex-shrink: 0; }
.lam-options { display: flex; flex-wrap: nowrap; gap: 0.5rem; }
.lam-opt { white-space: nowrap; padding: 0.5rem 0.75rem; font-size: 0.85rem; }
.qty-control { display: flex; align-items: center; height: 42px; border: 2px solid #d1d5db; border-radius: 8px; background: #fff; overflow: hidden; transition: border-color 0.2s ease; }
.qty-control:focus-within { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.qty-control-shopee { width: 110px; flex-shrink: 0; }
.qty-btn { flex: 0 0 36px; width: 36px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: background 0.2s; }
.qty-btn:hover { background: #e5e7eb; }
.qty-control input { flex: 1; min-width: 28px; border: none; text-align: center; font-weight: 700; font-size: 0.95rem; outline: none; background: transparent; }
.tshirt-top-notice { padding: 1rem; background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0ea5e9; border-radius: 8px; font-size: 0.875rem; color: #0369a1; line-height: 1.5; }

.placement-preview-area { width: 100%; max-width: 280px; aspect-ratio: 1; margin: 0 auto 1rem; border-radius: 10px; overflow: hidden; background: #f9fafb; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.placement-preview-placeholder { font-size: 0.8rem; color: #9ca3af; text-align: center; padding: 1rem; }
.placement-preview-img { width: 100%; height: 100%; object-fit: contain; transition: opacity 0.2s; }
.placement-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
.placement-card { display: flex; flex-direction: column; align-items: center; cursor: pointer; border: 2px solid #d1d5db; border-radius: 8px; padding: 0.5rem; background: #fff; transition: all 0.2s ease; }
.placement-card:hover { border-color: #0a2530; background: #f9fafb; }
.placement-card:has(input:checked) { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); }
.placement-card input { position: absolute; opacity: 0; pointer-events: none; }
.placement-img-wrap { width: 100%; aspect-ratio: 1; border-radius: 6px; overflow: hidden; background: #f3f4f6; position: relative; }
.placement-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.placement-label { font-size: 0.7rem; font-weight: 600; text-align: center; margin-top: 0.5rem; line-height: 1.2; color: #374151; }
@media (max-width: 640px) {
    .option-grid-3x2, .option-grid-3x3 { grid-template-columns: repeat(2, 1fr); }
    .option-grid-color .opt-btn-others { grid-column: 1 / -1; justify-self: center; }
    .placement-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .lam-qty-row { flex-direction: column; align-items: stretch; }
    .lam-qty-qty { width: 110px; }
    .lam-options { flex-wrap: wrap; }
}
</style>

<script>
function tshirtIncreaseQty() {
    const i = document.getElementById('quantity-input');
    i.value = Math.min(999, (parseInt(i.value) || 1) + 1);
    checkFormValid();
}
function tshirtDecreaseQty() {
    const i = document.getElementById('quantity-input');
    const v = parseInt(i.value) || 1;
    if (v > 1) { i.value = v - 1; checkFormValid(); }
}

document.querySelectorAll('input[name="shirt_source"]').forEach(r => {
    r.addEventListener('change', function() {
        const shopProvides = this.value === 'Shop will provide the shirt';
        document.getElementById('shop-provides-note').style.display = shopProvides ? 'block' : 'none';
        document.getElementById('size-section').style.display = shopProvides ? 'block' : 'none';
        document.querySelectorAll('input[name="size"]').forEach(s => { s.required = shopProvides; if (!shopProvides) s.checked = false; });
        document.querySelectorAll('input[name="shirt_type"]').forEach(s => s.required = shopProvides);
        document.getElementById('size-required-mark').textContent = shopProvides ? '*' : '';
        document.getElementById('shirt-type-required-mark').textContent = shopProvides ? '*' : '';
        checkFormValid();
    });
});

document.querySelectorAll('input[name="shirt_type"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('shirt-type-other-wrap').style.display = this.value === 'Others' ? 'block' : 'none';
        document.getElementById('shirt_type_other').required = this.value === 'Others';
        checkFormValid();
    });
});

document.querySelectorAll('input[name="shirt_color"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('color-other-wrap').style.display = this.value === 'Other' ? 'block' : 'none';
        document.getElementById('color_other').required = this.value === 'Other';
        checkFormValid();
    });
});

function checkFormValid() {
    const shirtSource = document.querySelector('input[name="shirt_source"]:checked');
    const shirtType = document.querySelector('input[name="shirt_type"]:checked');
    const shirtTypeOther = document.getElementById('shirt_type_other');
    const shirtColor = document.querySelector('input[name="shirt_color"]:checked');
    const colorOther = document.getElementById('color_other');
    const size = document.querySelector('input[name="size"]:checked');
    const placement = document.querySelector('input[name="print_placement"]:checked');
    const lamination = document.querySelector('input[name="lamination"]:checked');
    const qty = parseInt(document.getElementById('quantity-input').value) || 0;
    const file = document.getElementById('design_file');

    let ok = !!shirtSource && !!shirtColor && !!placement && !!lamination && qty >= 1 && file.files.length > 0;
    if (shirtColor && shirtColor.value === 'Other') ok = ok && colorOther.value.trim() !== '';

    const shopProvides = shirtSource && shirtSource.value === 'Shop will provide the shirt';
    if (shopProvides) {
        ok = ok && !!shirtType && !!size;
        if (shirtType && shirtType.value === 'Others') ok = ok && shirtTypeOther.value.trim() !== '';
    }

    const btn = document.getElementById('buyNowBtn');
    if (ok) {
        btn.disabled = false;
        btn.style.background = '#0a2530';
        btn.style.cursor = 'pointer';
    } else {
        btn.disabled = true;
        btn.style.background = '#9ca3af';
        btn.style.cursor = 'not-allowed';
    }
}

document.getElementById('tshirtForm').addEventListener('change', checkFormValid);
document.getElementById('tshirtForm').addEventListener('input', checkFormValid);
document.getElementById('design_file').addEventListener('change', checkFormValid);
document.getElementById('quantity-input').addEventListener('input', checkFormValid);

// Print placement: hover = preview, click = select. Preview persists (never clears).
const previewImg = document.getElementById('placement-preview-img');
const previewPlaceholder = document.getElementById('placement-preview-placeholder');

function showPreview(imgUrl) {
    if (imgUrl) {
        previewImg.src = imgUrl;
        previewImg.style.display = 'block';
        previewPlaceholder.style.display = 'none';
    } else {
        previewImg.src = '';
        previewImg.style.display = 'none';
        previewPlaceholder.style.display = 'block';
    }
}

document.querySelectorAll('.placement-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        showPreview(this.dataset.img);
    });
    card.addEventListener('mouseleave', function() {
        // Persistent: do not clear. Last hovered/clicked image stays visible.
    });
});

document.querySelectorAll('input[name="print_placement"]').forEach(r => {
    r.addEventListener('change', function() {
        const card = this.closest('.placement-card');
        if (card) showPreview(card.dataset.img);
    });
});

checkFormValid();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
