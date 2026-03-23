<?php
/**
 * Decals / Stickers - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $shape = trim($_POST['shape'] ?? 'Custom'); // Shape hidden for now; default
    $size = trim($_POST['size'] ?? '');
    $finish = trim($_POST['finish'] ?? 'Glossy');
    $laminate_option = trim($_POST['laminate_option'] ?? 'Without Laminate');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($size) || empty($needed_date) || $quantity < 1) {
        $error = 'Please fill in Size, Quantity, and Needed Date.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'stickers_' . time() . '_' . rand(100, 999);
            
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = service_order_temp_dir() . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'name' => 'Decals / Stickers',
                    'price' => 50.00, // Base price per cut/set
                    'quantity' => $quantity,
                    'category' => 'Decals & Stickers',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'shape' => $shape,
                        'size' => $size,
                        'finish' => $finish,
                        'laminate_option' => $laminate_option,
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

$page_title = 'Order Stickers - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Decals / Stickers</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card">
            <form action="" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="shape" value="Custom">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem; margin-bottom:0.85rem;">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" style="min-height: 40px; display: flex; align-items: flex-end; white-space: nowrap;">Branch *</label>
                        <select name="branch_id" class="input-field" style="height: 42px; padding-top: 0; padding-bottom: 0;" required>
                            <?php foreach($branches as $b): ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" style="min-height: 40px; display: flex; align-items: flex-end; white-space: nowrap;">Dimensions (W × H, inches) *</label>
                        <input type="text" name="size" class="input-field" style="height: 42px;" required placeholder="e.g. 2x2" value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>">
                    </div>
                </div>
                <div class="stickers-row-1 mb-4" style="display:grid; grid-template-columns:1fr 1fr minmax(0,110px); gap:1rem; align-items:end;">
                    <div style="min-width:0;">
                        <label class="block text-sm font-medium text-gray-700 mb-1" style="min-height: 40px; display: flex; align-items: flex-end; white-space: nowrap;"><span>Finish <button type="button" onclick="openFinishInfo()" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;border:none;background:#e2e8f0;color:#334155;font-size:11px;font-weight:700;cursor:pointer;vertical-align:middle;">i</button></span></label>
                        <select name="finish" class="input-field" style="height: 42px; padding-top: 0; padding-bottom: 0;">
                            <option value="Glossy">Glossy</option><option value="Matte">Matte</option>
                        </select>
                    </div>
                    <div style="min-width:0;">
                        <label class="block text-sm font-medium text-gray-700 mb-1" style="min-height: 40px; display: flex; align-items: flex-end; white-space: nowrap;">Laminate *</label>
                        <select name="laminate_option" class="input-field" style="height: 42px; padding-top: 0; padding-bottom: 0;">
                            <option value="With Laminate">With Laminate</option>
                            <option value="Without Laminate">Without Laminate</option>
                        </select>
                    </div>
                    <div style="min-width:0;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <div class="sticker-qty-stepper" style="display:inline-flex; align-items:center; width:110px; height:36px; border:1px solid #d1d5db; border-radius:6px; overflow:hidden; background:#fff;">
                            <button type="button" onclick="stickerQtyDown()" style="flex:0 0 32px; width:32px; height:36px; border:none; border-right:1px solid #e5e7eb; background:#f8fafc; color:#374151; font-size:1rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:6px 0 0 6px;">−</button>
                            <input type="number" id="sticker-qty" name="quantity" min="1" max="999" required value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>" style="flex:1; min-width:36px; border:none; text-align:center; font-weight:700; font-size:0.875rem; outline:none; background:transparent; padding:0 4px; height:36px;" oninput="stickerQtyClamp()">
                            <button type="button" onclick="stickerQtyUp()" style="flex:0 0 32px; width:32px; height:36px; border:none; border-left:1px solid #e5e7eb; background:#f8fafc; color:#374151; font-size:1rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:0 6px 6px 0;">+</button>
                        </div>
                    </div>
                </div>
                <div class="stickers-row-2 mb-4" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; align-items:end;">
                    <div style="min-width:0;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                        <input type="date" name="needed_date" class="input-field" style="width:100%; height:42px;" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>">
                        <p style="font-size:0.72rem; color:#6b7280; margin-top:4px;">Date when you need the order ready</p>
                    </div>
                    <div style="min-width:0;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Your File (Design, Image, or PDF) – Max 5MB</label>
                        <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required style="height:42px; padding-top:8px;">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" style="height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none; transition: all 0.2s;">Back to Services</a>
                    <button type="submit" name="buy_now" value="1" style="height: 48px; min-width: 140px; padding: 0 1.25rem; background: #0a2530; color: #ffffff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.02em;">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stickers-row-1, .stickers-row-2 { display: grid !important; }
@media (max-width: 640px) {
    .stickers-row-1, .stickers-row-2 { grid-template-columns: 1fr !important; }
}
.sticker-qty-stepper { box-sizing: border-box; }
.sticker-qty-stepper * { box-sizing: border-box; }
</style>
<div id="finishInfoModal" style="display:none; position:fixed; inset:0; z-index:99999; align-items:center; justify-content:center; padding:1rem;">
    <div onclick="closeFinishInfo()" style="position:absolute; inset:0; background:rgba(0,0,0,0.45);"></div>
    <div style="position:relative; background:#fff; border-radius:14px; width:min(920px, 96vw); max-height:90vh; overflow:auto; padding:1rem 1rem 1.25rem;">
        <button type="button" onclick="closeFinishInfo()" style="position:absolute; top:8px; right:8px; border:none; background:#f1f5f9; border-radius:999px; width:30px; height:30px; cursor:pointer;">x</button>
        <h3 style="font-size:1.1rem; font-weight:800; margin:0 0 0.8rem 0; color:#0f172a;">Finish Type Guide</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:0.9rem;">
            <div style="border:1px solid #e2e8f0; border-radius:12px; padding:0.8rem; background:#f8fafc;">
                <div style="font-weight:800; margin-bottom:0.5rem;">Matte Finish</div>
                <img src="/printflow/public/images/products/product_21.jpg" alt="Matte sample" style="width:100%; height:160px; object-fit:cover; border-radius:10px; margin-bottom:0.6rem;">
                <ul style="margin:0; padding-left:1.05rem; font-size:0.86rem; color:#334155; line-height:1.5;">
                    <li>Non-shiny surface</li><li>Smooth and elegant appearance</li><li>Reduces glare and reflections</li><li>Ideal for minimalist, professional, or premium designs</li><li>Easier to read under strong lighting</li>
                </ul>
            </div>
            <div style="border:1px solid #e2e8f0; border-radius:12px; padding:0.8rem; background:#f8fafc;">
                <div style="font-weight:800; margin-bottom:0.5rem;">Glossy Finish</div>
                <img src="/printflow/public/images/products/product_26.jpg" alt="Glossy sample" style="width:100%; height:160px; object-fit:cover; border-radius:10px; margin-bottom:0.6rem;">
                <ul style="margin:0; padding-left:1.05rem; font-size:0.86rem; color:#334155; line-height:1.5;">
                    <li>Shiny and reflective surface</li><li>Colors appear more vibrant and bright</li><li>Eye-catching and smooth texture</li><li>Best for colorful designs, logos, and photos</li><li>Reflects light and gives a polished look</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function stickerQtyClamp() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10);
    if (!v || v < 1) v = 1;
    if (v > 999) v = 999;
    input.value = v;
}
function stickerQtyUp() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.min(v + 1, 999);
}
function stickerQtyDown() {
    const input = document.getElementById('sticker-qty');
    let v = parseInt(input.value, 10) || 1;
    input.value = Math.max(v - 1, 1);
}
function openFinishInfo() {
    document.getElementById('finishInfoModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeFinishInfo() {
    document.getElementById('finishInfoModal').style.display = 'none';
    document.body.style.overflow = '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
