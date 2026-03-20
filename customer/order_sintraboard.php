<?php
/**
 * Stickers on Sintraboard - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $thickness = trim($_POST['thickness'] ?? '');
    $stand_type = trim($_POST['stand_type'] ?? '');
    $lamination = trim($_POST['lamination'] ?? 'No Lamination');
    $cut_type = trim($_POST['cut_type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $sintra_type = trim($_POST['sintra_type'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);

    if (empty($width) || empty($height) || empty($thickness) || empty($stand_type) || empty($cut_type) || empty($sintra_type)) {
        $error = 'Please fill in all required fields.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $sintra_product_id = ($sintra_type === 'Flat Type') ? 51 : 54;
            $sintra_price = ($sintra_type === 'Flat Type') ? 150.00 : 800.00;
            $product_name = ($sintra_type === 'Flat Type') ? 'Sintraboard Flat - Grid Menu' : 'Life size standee with face hole';

            // Read the binary data of the uploaded file
            $data = file_get_contents($_FILES['design_file']['tmp_name']);
            if ($data === false || $data === '') {
                $error = "Failed to process the uploaded design file. Please try again.";
            } else {
                // Store temp file path for cart processing later
                $tmp_dir = __DIR__ . '/../uploads/temp';
                if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0755, true);
                
                $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
                $tmp_filename = uniqid('sintra_') . '.' . $ext;
                $tmp_path = $tmp_dir . '/' . $tmp_filename;
                file_put_contents($tmp_path, $data);
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
                finfo_close($finfo);
                
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                $item_key = $sintra_product_id . '_' . time();
                
                $_SESSION['cart'][$item_key] = [
                    'product_id'     => $sintra_product_id,
                    'branch_id'      => $branch_id,
                    'name'           => $product_name,
                    'category'       => 'Sintraboard & Standees',
                    'price'          => $sintra_price,
                    'quantity'       => $quantity,
                    'image'          => '📦',
                    'customization'  => [
                        'Sintra_Type' => $sintra_type,
                        'Width'      => $width,
                        'Height'     => $height,
                        'Thickness'  => $thickness,
                        'Stand_Type' => $stand_type,
                        'Lamination' => $lamination,
                        'Cut_Type'   => $cut_type
                    ],
                    'design_notes'   => $notes,
                    'design_tmp_path'=> $tmp_path,
                    'design_mime'    => $mime,
                    'design_name'    => $_FILES['design_file']['name'],
                    'reference_tmp_path' => null,
                    'reference_mime'     => null,
                    'reference_name'     => null
                ];
                
                // Redirect user based on action
                if (isset($_POST['buy_now'])) {
                    redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
                } else {
                    redirect(BASE_URL . '/customer/cart.php');
                }
            }
        }
    }
}
$page_title = 'Order Stickers on Sintraboard - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>
<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 800px;">
        <div class="mb-8 overflow-hidden rounded-2xl shadow-lg border border-gray-100 bg-white">
            <div class="text-center p-6 bg-gray-50 border-b border-gray-100 flex flex-col items-center">
                <div style="width: 150px; height: 150px; border-radius: 12px; overflow: hidden; border: 2px solid #e5e7eb; background: white; margin-bottom: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <img id="previewImage" src="/printflow/public/images/services/Sintraboard Standees.jpg" alt="Sintraboard Standees" style="width: 100%; height: 100%; object-fit: cover; transition: opacity 0.3s;">
                </div>
                <h1 class="text-2xl font-black text-black uppercase tracking-widest">Sintra Board Standees</h1>
            </div>
            
            <div class="p-8">
                <?php if ($error): ?><div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-8">
                    <?php echo csrf_field(); ?>
                    
                    <!-- 1. Branch & Sintra Board Type -->
                    <div class="card shadow-sm border border-gray-100 p-6 rounded-xl bg-white">
                        <h2 class="text-lg font-bold mb-4 flex items-center gap-2 uppercase tracking-wider border-b pb-2">
                            <span class="bg-black text-white w-6 h-6 flex items-center justify-center rounded-full text-xs">1</span>
                            Branch & Sintra Board Type *
                        </h2>
                        
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Branch *</label>
                            <select name="branch_id" class="input-field w-full" required>
                                <?php foreach($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php $selected_type = $_POST['sintra_type'] ?? $_GET['sintra_type'] ?? ''; ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50 transition-colors <?php echo $selected_type == 'With Face Hole' ? 'border-black bg-gray-50' : ''; ?>">
                                <input type="radio" name="sintra_type" value="With Face Hole" required onchange="changePreview('With Face Hole')" <?php echo $selected_type == 'With Face Hole' ? 'checked' : ''; ?>>
                                <span class="text-xs font-bold">With Face Hole (Standee with hole for face)</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50 transition-colors <?php echo $selected_type == 'Flat Type' ? 'border-black bg-gray-50' : ''; ?>">
                                <input type="radio" name="sintra_type" value="Flat Type" required onchange="changePreview('Flat Type')" <?php echo $selected_type == 'Flat Type' ? 'checked' : ''; ?>>
                                <span class="text-xs font-bold">Flat Type (No hole – regular sintra board)</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- 2. Size & Board Details -->
                    <div class="card shadow-sm border border-gray-100 p-6 rounded-xl bg-white">
                        <h2 class="text-lg font-bold mb-4 flex items-center gap-2 uppercase tracking-wider border-b pb-2">
                            <span class="bg-black text-white w-6 h-6 flex items-center justify-center rounded-full text-xs">2</span>
                            Size & Board Details
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Width (inches) *</label>
                                <input type="number" step="0.01" name="width" class="input-field w-full" required min="0.01" value="<?php echo htmlspecialchars($_POST['width'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Height (inches) *</label>
                                <input type="number" step="0.01" name="height" class="input-field w-full" required min="0.01" value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Thickness *</label>
                                <select name="thickness" class="input-field w-full" required>
                                    <option value="" disabled selected>Select Thickness</option>
                                    <option value="3mm" <?php echo ($_POST['thickness'] ?? '') == '3mm' ? 'selected' : ''; ?>>3mm</option>
                                    <option value="5mm" <?php echo ($_POST['thickness'] ?? '') == '5mm' ? 'selected' : ''; ?>>5mm</option>
                                    <option value="10mm" <?php echo ($_POST['thickness'] ?? '') == '10mm' ? 'selected' : ''; ?>>10mm</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Design Upload -->
                    <div class="card shadow-sm border border-gray-100 p-6 rounded-xl bg-white">
                        <h2 class="text-lg font-bold mb-4 flex items-center gap-2 uppercase tracking-wider border-b pb-2">
                            <span class="bg-black text-white w-6 h-6 flex items-center justify-center rounded-full text-xs">3</span>
                            Design Upload
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">📎 Upload Your File (Design, Image, or PDF) – Max 5MB</label>
                                <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf,.ai" class="input-field w-full p-2" required>
                                <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-tighter">Max file size: 10MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Notes (Optional)</label>
                                <textarea name="notes" rows="3" class="input-field w-full" placeholder="Add special instructions, layout changes, or color preferences..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Stand & Finishing -->
                    <div class="card shadow-sm border border-gray-100 p-6 rounded-xl bg-white">
                        <h2 class="text-lg font-bold mb-4 flex items-center gap-2 uppercase tracking-wider border-b pb-2">
                            <span class="bg-black text-white w-6 h-6 flex items-center justify-center rounded-full text-xs">4</span>
                            Stand & Finishing Options
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Stand Type *</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <label class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50 transition-colors <?php echo ($_POST['stand_type'] ?? '') == 'With Metal Stand' ? 'border-black bg-gray-50' : ''; ?>">
                                        <input type="radio" name="stand_type" value="With Metal Stand" required <?php echo ($_POST['stand_type'] ?? '') == 'With Metal Stand' ? 'checked' : ''; ?>>
                                        <span class="text-xs font-bold">Metal Stand</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50 transition-colors <?php echo ($_POST['stand_type'] ?? '') == 'With Foldable Support (Easel Type)' ? 'border-black bg-gray-50' : ''; ?>">
                                        <input type="radio" name="stand_type" value="With Foldable Support (Easel Type)" <?php echo ($_POST['stand_type'] ?? '') == 'With Foldable Support (Easel Type)' ? 'checked' : ''; ?>>
                                        <span class="text-xs font-bold">Foldable</span>
                                    </label>
                                    <label class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50 transition-colors <?php echo ($_POST['stand_type'] ?? '') == 'Board Only (No Stand)' ? 'border-black bg-gray-50' : ''; ?>">
                                        <input type="radio" name="stand_type" value="Board Only (No Stand)" <?php echo ($_POST['stand_type'] ?? '') == 'Board Only (No Stand)' ? 'checked' : ''; ?>>
                                        <span class="text-xs font-bold">Board Only</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Lamination (Optional)</label>
                                    <select name="lamination" class="input-field w-full">
                                        <option value="No Lamination">No Lamination</option>
                                        <option value="Matte">Matte</option>
                                        <option value="Gloss">Gloss</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cut Type *</label>
                                    <select name="cut_type" class="input-field w-full" required onchange="if(this.value=='Die-Cut (Custom Shape)') document.getElementById('diecut-note').classList.remove('hidden'); else document.getElementById('diecut-note').classList.add('hidden');">
                                        <option value="Standard Rectangle Cut">Standard Rectangle Cut</option>
                                        <option value="Die-Cut (Custom Shape)">Die-Cut (Custom Shape)</option>
                                    </select>
                                    <p id="diecut-note" class="hidden text-[10px] text-amber-600 mt-1 font-bold italic">Die-cut may require additional charges depending on complexity.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Quantity -->
                    <div class="card shadow-sm border border-gray-100 p-6 rounded-xl bg-white">
                        <h2 class="text-lg font-bold mb-4 flex items-center gap-2 uppercase tracking-wider border-b pb-2">
                            <span class="bg-black text-white w-6 h-6 flex items-center justify-center rounded-full text-xs">5</span>
                            Quantity
                        </h2>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Quantity *</label>
                            <input type="number" name="quantity" min="1" class="input-field w-full" required value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                        </div>
                    </div>

                    <div style="display:flex; gap:1rem; margin-top:2rem;">
                        <!-- Buy Now Button (Solid) -->
                        <button type="submit" name="buy_now" value="1" style="flex:1; height: 56px; display: flex; align-items: center; justify-content: center; background: #0a2530; color: #ffffff; font-weight: 800; border-radius: 12px; border: none; cursor: pointer; transition: all 0.2s; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.02em; box-shadow: 4px 4px 0px rgba(10, 37, 48, 0.1);">
                            Buy Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <p class="mt-4 text-sm text-gray-500 text-center"><a href="<?php echo BASE_URL; ?>/customer/services.php" class="text-black font-bold uppercase hover:underline">← Back to Services</a></p>
    </div>
</div>
<script>
function changePreview(type) {
    const img = document.getElementById('previewImage');
    if (type === 'With Face Hole') {
        img.src = '/printflow/public/images/services/Sintraboard Standees.jpg';
    } else {
        img.src = '/printflow/public/images/products/standeeflat.jpg';
    }
}
<?php if ($selected_type): ?>
changePreview('<?php echo htmlspecialchars($selected_type); ?>');
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

