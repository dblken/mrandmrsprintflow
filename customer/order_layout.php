<?php
/**
 * Layout Design Service - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $layout_type = trim($_POST['layout_type'] ?? '');
    $rush = trim($_POST['rush'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($layout_type)) {
        $error = 'Please select type of layout.';
    } else {
        $fields = ['layout_type' => $layout_type, 'rush' => $rush ?: 'No', 'description' => $description];
        $files = [];
        if (isset($_FILES['reference_file']) && $_FILES['reference_file']['error'] === UPLOAD_ERR_OK) {
            $valid = service_order_validate_file($_FILES['reference_file']);
            if ($valid['ok']) $files[] = ['file' => $_FILES['reference_file'], 'prefix' => 'reference'];
        }
        $result = service_order_create('Layout Design Service', $customer_id, $fields, $files);
        if ($result['success']) { $_SESSION['order_success_id'] = $result['order_id']; redirect(BASE_URL . '/customer/order_success.php?service=layout'); }
        $error = $result['error'] ?: 'Failed to submit order.';
    }
}
$page_title = 'Layout Design Service - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Layout Design Service</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type of Layout *</label>
                    <select name="layout_type" class="input-field" required>
                        <option value="Logo">Logo</option><option value="Banner">Banner</option><option value="Invitation">Invitation</option><option value="Poster">Poster</option><option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rush?</label>
                    <select name="rush" class="input-field">
                        <option value="No">No</option><option value="Yes">Yes</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="4" class="input-field" placeholder="Describe your layout needs..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference Upload (optional)</label>
                    <input type="file" name="reference_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field">
                </div>
                <button type="submit" class="btn-primary w-full">Submit Order</button>
            </form>
        </div>
        <p class="mt-4 text-sm text-gray-500 text-center"><a href="<?php echo BASE_URL; ?>/customer/services.php" class="text-indigo-600 hover:underline">← Back to Services</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

