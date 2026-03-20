<?php
/**
 * Staff - Service Order Details
 * Shows all options, uploaded files (preview), notes
 * Approve -> Processing | Reject -> Rejected
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) redirect(BASE_URL . '/staff/service_orders.php');

$order = db_query("SELECT so.*, c.first_name, c.last_name, c.email, c.contact_number 
                  FROM service_orders so 
                  LEFT JOIN customers c ON so.customer_id = c.customer_id 
                  WHERE so.id = ?", 'i', [$order_id]);
if (empty($order)) redirect(BASE_URL . '/staff/service_orders.php');
$order = $order[0];

$success = '';
$error = '';

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['approve'])) {
        db_execute("UPDATE service_orders SET status = 'Processing' WHERE id = ?", 'i', [$order_id]);
        if (function_exists('create_notification')) {
            create_notification($order['customer_id'], 'Customer', "Your service order #{$order_id} has been approved and is now processing.", 'Order', true, false);
        }
        $success = 'Order approved. Status set to Processing.';
        $order['status'] = 'Processing';
    } elseif (isset($_POST['reject'])) {
        db_execute("UPDATE service_orders SET status = 'Rejected' WHERE id = ?", 'i', [$order_id]);
        if (function_exists('create_notification')) {
            create_notification($order['customer_id'], 'Customer', "Your service order #{$order_id} has been rejected.", 'Order', true, false);
        }
        $success = 'Order rejected.';
        $order['status'] = 'Rejected';
    } elseif (isset($_POST['update_status'])) {
        $new_status = $_POST['status'];
        if (in_array($new_status, ['Pending', 'Approved', 'Processing', 'Completed', 'Rejected'])) {
            db_execute("UPDATE service_orders SET status = ? WHERE id = ?", 'si', [$new_status, $order_id]);
            $success = "Status updated to {$new_status}.";
            $order['status'] = $new_status;
        }
    }
}

$details = db_query("SELECT field_name, field_value FROM service_order_details WHERE order_id = ?", 'i', [$order_id]);
$files = db_query("SELECT id, file_data, mime_type, original_name, file_path FROM service_order_files WHERE order_id = ?", 'i', [$order_id]);

$page_title = 'Service Order #' . $order_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .detail-label { color: #6b7280; }
        .detail-value { font-weight: 500; }
        .file-preview { max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>
    <div class="main-content">
        <header>
            <a href="service_orders.php" class="text-indigo-600 hover:underline text-sm">← Back to Service Orders</a>
            <h1 class="page-title">Service Order #<?php echo $order_id; ?></h1>
        </header>
        <main>
            <?php if ($success): ?><div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="detail-grid">
                <div class="card">
                    <h2 class="text-lg font-semibold mb-4">Order Info</h2>
                    <div class="detail-row"><span class="detail-label">Service</span><span class="detail-value"><?php echo htmlspecialchars($order['service_name']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Status</span><?php echo status_badge($order['status'], 'order'); ?></div>
                    <div class="detail-row"><span class="detail-label">Date</span><span class="detail-value"><?php echo format_datetime($order['created_at']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Total</span><span class="detail-value"><?php echo format_currency($order['total_price']); ?></span></div>

                    <?php if ($order['status'] === 'Pending'): ?>
                    <div class="mt-4 pt-4 border-t flex gap-2">
                        <form method="POST" class="inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" name="approve" value="1" class="btn-primary">Approve</button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Reject this order?');">
                            <?php echo csrf_field(); ?>
                            <button type="submit" name="reject" value="1" class="btn-secondary" style="background:#fef2f2;color:#b91c1c;">Reject</button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4 pt-4 border-t">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="update_status" value="1">
                            <label class="block text-sm font-medium mb-2">Update Status</label>
                            <div class="flex gap-2">
                                <select name="status" class="input-field flex-1">
                                    <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Approved" <?php echo $order['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="Completed" <?php echo $order['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Rejected" <?php echo $order['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <button type="submit" class="btn-primary">Update</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <h2 class="text-lg font-semibold mb-4">Customer</h2>
                    <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($order['email'] ?? 'N/A'); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Contact</span><span class="detail-value"><?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?></span></div>
                </div>
            </div>

            <div class="card mt-6">
                <h2 class="text-lg font-semibold mb-4">Order Details (Selected Options)</h2>
                <dl class="grid grid-cols-2 gap-2">
                    <?php foreach ($details as $d): ?>
                    <div class="detail-row"><dt class="detail-label"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $d['field_name']))); ?></dt><dd class="detail-value"><?php echo htmlspecialchars($d['field_value']); ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </div>

            <?php if (!empty($files)): ?>
            <div class="card mt-6">
                <h2 class="text-lg font-semibold mb-4">Uploaded Design Files</h2>
                <div class="flex flex-wrap gap-6">
                    <?php foreach ($files as $f):
                        $has_blob   = !empty($f['file_data']);
                        $has_legacy = !empty($f['file_path'] ?? '');
                        $is_img_mime = in_array($f['mime_type'] ?? '', ['image/jpeg', 'image/jpg', 'image/png']);
                        $display_name = $f['original_name'] ?: 'design file';
                    ?>
                    <div>
                        <?php if ($has_blob): ?>
                            <?php if ($is_img_mime): ?>
                                <a href="/printflow/public/serve_design.php?type=service_file&id=<?php echo (int)$f['id']; ?>" target="_blank">
                                    <img src="/printflow/public/serve_design.php?type=service_file&id=<?php echo (int)$f['id']; ?>"
                                         alt="Design" class="file-preview"
                                         onerror="this.outerHTML='<span style=\'color:#6b7280;\'>⚠️ Could not load image</span>'">
                                </a>
                            <?php else: ?>
                                <a href="/printflow/public/serve_design.php?type=service_file&id=<?php echo (int)$f['id']; ?>"
                                   target="_blank" class="block p-4 border rounded bg-gray-50">
                                    🖼️ <?php echo htmlspecialchars($display_name); ?>
                                </a>
                            <?php endif; ?>
                        <?php elseif ($has_legacy): ?>
                            <?php
                                $ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
                                $is_img = in_array($ext, ['jpg','jpeg','png','gif']);
                                $full_url = BASE_URL . '/' . $f['file_path'];
                            ?>
                            <?php if ($is_img): ?>
                                <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($full_url); ?>" alt="" class="file-preview">
                                </a>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank" class="block p-4 border rounded bg-gray-50">
                                    📄 <?php echo htmlspecialchars($display_name ?: basename($f['file_path'])); ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="block p-4 border rounded bg-gray-50 text-gray-400 text-sm">No file data available</div>
                        <?php endif; ?>
                        <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($display_name); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
