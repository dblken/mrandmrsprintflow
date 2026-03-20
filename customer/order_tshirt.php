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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $size = trim($_POST['size'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $print_placement = trim($_POST['print_placement'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $design_type = trim($_POST['design_type'] ?? 'upload');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($size) || empty($color) || empty($print_placement) || $quantity < 1) {
        $error = 'Please fill in Size, Color, Print Placement, and Quantity.';
    } elseif ($design_type === 'upload' && (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'Please upload your design or choose a template.';
    } else {
        $item_key = 'tshirt_' . time() . '_' . rand(100, 999);
        $tmp_path = null;
        $mime = null;
        $original_name = null;

        if ($design_type === 'upload') {
            $valid = service_order_validate_file($_FILES['design_file']);
            if (!$valid['ok']) {
                $error = $valid['error'];
            } else {
                $original_name = $_FILES['design_file']['name'];
                $mime = $valid['mime'];
                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_name = uniqid('tmp_') . '.' . $ext;
                $tmp_dest = __DIR__ . '/../uploads/temp/' . $new_name;
                
                if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                    $tmp_path = $tmp_dest;
                } else {
                    $error = 'Failed to process uploaded file.';
                }
            }
        }

        if (!$error) {
            // Push to cart session
            $_SESSION['cart'][$item_key] = [
                'type' => 'Service',
                'name' => 'T-Shirt Printing',
                'price' => 150.00, // Base price
                'quantity' => $quantity,
                'category' => 'T-Shirts',
                'branch_id' => $branch_id,
                'design_tmp_path' => $tmp_path,
                'design_name' => $original_name,
                'design_mime' => $mime,
                'customization' => [
                    'size' => $size,
                    'color' => $color,
                    'print_placement' => $print_placement,
                    'design_type' => $design_type,
                    'template' => ($design_type === 'template') ? trim($_POST['template'] ?? '') : null,
                    'notes' => $notes
                ]
            ];
            
            if (isset($_POST['buy_now'])) {
                redirect("order_review.php?item=" . urlencode($item_key));
            } else {
                redirect("cart.php");
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
            <form action="" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Size *</label>
                        <select name="size" class="input-field" required>
                            <option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option><option value="2XL">2XL</option><option value="3XL">3XL</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color *</label>
                        <select name="color" class="input-field" required>
                            <option value="Black">Black</option><option value="White">White</option><option value="Red">Red</option><option value="Blue">Blue</option><option value="Navy">Navy</option><option value="Grey">Grey</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Print Placement *</label>
                    <select name="print_placement" class="input-field" required>
                        <option value="Front">Front</option><option value="Back">Back</option><option value="Both">Both</option><option value="Pocket">Pocket</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" class="input-field" required value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Design</label>
                    <select name="design_type" id="design_type" class="input-field">
                        <option value="upload">Upload Design</option>
                        <option value="template">Choose Template</option>
                    </select>
                </div>

                <div class="mb-4" id="upload-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">📎 Upload Your File (Design, Image, or PDF) – Max 5MB</label>
                    <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field">
                </div>

                <div class="mb-4 hidden" id="template-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                    <input type="text" name="template" class="input-field" placeholder="e.g. Standard Logo">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field" placeholder="Any special instructions..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div style="display:flex; gap:1rem; margin-top:2rem;">
                    <!-- Buy Now Button (Solid) -->
                    <button type="submit" name="buy_now" value="1" style="flex:1; height: 56px; display: flex; align-items: center; justify-content: center; background: #0a2530; color: #ffffff; font-weight: 800; border-radius: 12px; border: none; cursor: pointer; transition: all 0.2s; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.02em; box-shadow: 4px 4px 0px rgba(10, 37, 48, 0.1);">
                        Buy Now
                    </button>
                </div>
            </form>
        </div>
        <p class="mt-4 text-sm text-gray-500 text-center"><a href="<?php echo BASE_URL; ?>/customer/services.php" class="text-indigo-600 hover:underline">← Back to Services</a></p>
    </div>
</div>

<script>
document.getElementById('design_type').addEventListener('change', function() {
    var upload = document.getElementById('upload-wrap');
    var template = document.getElementById('template-wrap');
    if (this.value === 'template') { upload.classList.add('hidden'); template.classList.remove('hidden'); } else { upload.classList.remove('hidden'); template.classList.add('hidden'); }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
