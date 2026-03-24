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
    $branch_id = trim($_POST['branch_id'] ?? '1');
    $layout_type = trim($_POST['layout_type'] ?? '');
    $rush = trim($_POST['rush'] ?? '');
    $needed_date = trim($_POST['needed_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($layout_type) || empty($needed_date)) {
        $error = 'Please select type of layout and provide needed date.';
    } else {
        $tmp_dir = service_order_temp_dir();
        $tmp_path = null;
        $mime = null;
        $design_name = null;

        if (isset($_FILES['reference_file']) && $_FILES['reference_file']['error'] === UPLOAD_ERR_OK) {
            $valid = service_order_validate_file($_FILES['reference_file']);
            if ($valid['ok']) {
                $db_data = file_get_contents($_FILES['reference_file']['tmp_name']);
                $ext = pathinfo($_FILES['reference_file']['name'], PATHINFO_EXTENSION);
                $tmp_filename = uniqid('layout_') . '.' . $ext;
                $tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
                file_put_contents($tmp_path, $db_data);
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['reference_file']['tmp_name']);
                finfo_close($finfo);
                $design_name = $_FILES['reference_file']['name'];
            }
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $item_key = 'layout_' . time();
        
        $_SESSION['cart'][$item_key] = [
            'product_id'     => 0,
            'source_page'    => 'services',
            'branch_id'      => $branch_id,
            'name'           => 'Layout Design Service',
            'category'       => 'Graphic Design',
            'price'          => 0, // Determined after review
            'quantity'       => 1,
            'image'          => '🎨',
            'customization'  => [
                'Layout_Type' => $layout_type,
                'Rush_Order'  => $rush ?: 'No',
                'needed_date' => $needed_date
            ],
            'design_notes'   => $description,
            'design_tmp_path'=> $tmp_path,
            'design_mime'    => $mime,
            'design_name'    => $design_name,
            'reference_tmp_path' => null,
            'reference_mime'     => null,
            'reference_name'     => null
        ];

        if (($_POST['action'] ?? '') === 'buy_now' || isset($_POST['buy_now'])) {
            redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
        } else {
            redirect(BASE_URL . '/customer/cart.php');
        }
    }
}
$page_title = 'Layout Design Service - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>
<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Layout Design Service</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card p-6">
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field w-full" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type of Layout *</label>
                    <select name="layout_type" class="input-field w-full" required>
                        <option value="Logo">Logo</option><option value="Banner">Banner</option><option value="Invitation">Invitation</option><option value="Poster">Poster</option><option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rush?</label>
                    <select name="rush" class="input-field w-full">
                        <option value="No">No</option><option value="Yes">Yes</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                    <input type="date" name="needed_date" class="input-field w-full" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['needed_date'] ?? ''); ?>">
                    <p style="font-size:0.72rem; color:#6b7280; margin-top:4px;">Date when you need the order ready</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="4" class="input-field w-full" placeholder="Describe your layout needs..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">📎 Upload Your File (Design, Image, or PDF) – Max 5MB</label>
                    <input type="file" name="reference_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field w-full">
                </div>
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" style="height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none; transition: all 0.2s;">Back to Services</a>
                    <button type="submit" name="action" value="add_to_cart" style="height: 48px; min-width: 140px; padding: 0 1.25rem; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; cursor: pointer; transition: all 0.2s;">Add to Cart</button>
                    <button type="submit" name="action" value="buy_now" style="height: 48px; min-width: 140px; padding: 0 1.25rem; background: #0a2530; color: #ffffff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.02em;">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

