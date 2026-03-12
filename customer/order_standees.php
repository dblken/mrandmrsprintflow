<?php
/**
 * Sintraboard Standees - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $size = trim($_POST['size'] ?? ''); $with_stand = trim($_POST['with_stand'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1); $notes = trim($_POST['notes'] ?? '');
    if (empty($size) || $quantity < 1) {
        $error = 'Please fill in Size and Quantity.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    }

    if (empty($error)) {
        // Process file for session
        $tmp_dir = __DIR__ . '/../uploads/temp';
        if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0755, true);
        
        $db_data = file_get_contents($_FILES['design_file']['tmp_name']);
        $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
        $tmp_filename = uniqid('standee_') . '.' . $ext;
        $tmp_path = $tmp_dir . '/' . $tmp_filename;
        file_put_contents($tmp_path, $db_data);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
        finfo_close($finfo);

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $product_id = 0; // Service order
        $item_key = 'stand_' . time();
        
        $_SESSION['cart'][$item_key] = [
            'product_id'     => $product_id,
            'branch_id'      => $branch_id,
            'name'           => 'Sintraboard Standees',
            'category'       => 'Sintraboard & Standees',
            'price'          => 0, // Determined at review or staff side
            'quantity'       => $quantity,
            'image'          => '🕴️',
            'customization'  => [
                'Size' => $size,
                'With_Stand' => $with_stand ?: 'No'
            ],
            'design_notes'   => $notes,
            'design_tmp_path'=> $tmp_path,
            'design_mime'    => $mime,
            'design_name'    => $_FILES['design_file']['name'],
            'reference_tmp_path' => null,
            'reference_mime'     => null,
            'reference_name'     => null
        ];

        redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
    }
}
$page_title = 'Order Sintraboard Standees - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>
<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Sintraboard Standees</h1>
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
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Size *</label>
                    <input type="text" name="size" class="input-field" required placeholder="e.g. 22x28 inches" value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">With Stand?</label>
                    <select name="with_stand" class="input-field">
                        <option value="No">No</option><option value="Yes">Yes</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" class="input-field" required value="<?php echo (int)($_POST['quantity'] ?? 1); ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

