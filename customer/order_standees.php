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
    $needed_date = trim($_POST['needed_date'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1); $notes = trim($_POST['notes'] ?? '');
    if (empty($size) || empty($needed_date) || $quantity < 1) {
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
                'With_Stand' => $with_stand ?: 'No',
                'needed_date' => $needed_date
            ],
            'design_notes'   => $notes,
            'design_tmp_path'=> $tmp_path,
            'design_mime'    => $mime,
            'design_name'    => $_FILES['design_file']['name'],
            'reference_tmp_path' => null,
            'reference_mime'     => null,
            'reference_name'     => null
        ];

        if (isset($_POST['buy_now'])) {
            redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
        } else {
            redirect(BASE_URL . '/customer/cart.php');
        }
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                    <input type="date" name="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>">
                    <p style="font-size:0.72rem; color:#6b7280; margin-top:4px;">Date when you need the order ready</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" max="999" class="input-field" required value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">📎 Upload Your File (Design, Image, or PDF) – Max 5MB</label>
                    <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

