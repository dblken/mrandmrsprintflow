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
        $fields = [
            'size' => $size,
            'color' => $color,
            'print_placement' => $print_placement,
            'quantity' => $quantity,
            'design_type' => $design_type,
            'notes' => $notes,
        ];
        $files = [];
        if ($design_type === 'upload' && isset($_FILES['design_file']) && $_FILES['design_file']['error'] === UPLOAD_ERR_OK) {
            $valid = service_order_validate_file($_FILES['design_file']);
            if (!$valid['ok']) {
                $error = $valid['error'];
            } else {
                $files[] = ['file' => $_FILES['design_file'], 'prefix' => 'design'];
            }
        } elseif ($design_type === 'template') {
            $fields['template'] = trim($_POST['template'] ?? '');
        }
        if (!$error) {
            $result = service_order_create('T-Shirt Printing', $customer_id, $branch_id, $fields, $files);
            if ($result['success']) {
                $_SESSION['order_success_id'] = $result['order_id'];
                redirect(BASE_URL . '/customer/order_success.php?service=tshirt');
            }
            $error = $result['error'] ?: 'Failed to submit order.';
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
            <form method="POST" enctype="multipart/form-data">
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
                            <option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color *</label>
                        <select name="color" class="input-field" required>
                            <option value="Black">Black</option><option value="White">White</option><option value="Red">Red</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Print Placement *</label>
                    <select name="print_placement" class="input-field" required>
                        <option value="Front">Front</option><option value="Back">Back</option><option value="Both">Both</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" class="input-field" required value="<?php echo (int)($_POST['quantity'] ?? 1); ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Design</label>
                    <select name="design_type" id="design_type" class="input-field">
                        <option value="upload">Upload Design</option>
                        <option value="template">Choose Template</option>
                    </select>
                </div>
                <div class="mb-4" id="upload-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" id="design_file_input">
                </div>
                <div class="mb-4 hidden" id="template-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Template Name</label>
                    <input type="text" name="template" class="input-field" placeholder="e.g. Standard Logo">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn-primary w-full">Submit Order</button>
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

