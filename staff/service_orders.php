<?php
/**
 * Staff - Service Orders List
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

service_order_ensure_tables();

$filter = $_GET['status'] ?? '';
$sql = "SELECT so.*, c.first_name, c.last_name, c.email, c.contact_number 
        FROM service_orders so 
        LEFT JOIN customers c ON so.customer_id = c.customer_id 
        ORDER BY so.created_at DESC";
$params = [];
$types = '';
if ($filter && in_array($filter, ['Pending', 'Approved', 'Processing', 'Completed', 'Rejected'])) {
    $sql = "SELECT so.*, c.first_name, c.last_name, c.email, c.contact_number 
            FROM service_orders so 
            LEFT JOIN customers c ON so.customer_id = c.customer_id 
            WHERE so.status = ? 
            ORDER BY so.created_at DESC";
    $params = [$filter];
    $types = 's';
}

$orders = $params ? db_query($sql, $types, $params) : db_query($sql);

$page_title = 'Service Orders - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>
    <div class="main-content">
        <header>
            <h1 class="page-title">Service Orders</h1>
            <div class="flex gap-2">
                <a href="service_orders.php" class="btn-secondary">All</a>
                <a href="service_orders.php?status=Pending" class="btn-secondary">Pending</a>
                <a href="service_orders.php?status=Processing" class="btn-secondary">Processing</a>
                <a href="service_orders.php?status=Completed" class="btn-secondary">Completed</a>
            </div>
        </header>
        <main>
            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 bg-gray-50">
                            <th class="text-left py-3 px-4">ID</th>
                            <th class="text-left py-3 px-4">Service</th>
                            <th class="text-left py-3 px-4">Customer</th>
                            <th class="text-left py-3 px-4">Status</th>
                            <th class="text-left py-3 px-4">Date</th>
                            <th class="text-right py-3 px-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr><td colspan="6" class="py-8 text-center text-gray-500">No service orders found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-mono">#<?php echo $o['id']; ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($o['service_name']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')); ?></td>
                            <td class="py-3 px-4"><?php echo status_badge($o['status'], 'order'); ?></td>
                            <td class="py-3 px-4"><?php echo format_datetime($o['created_at']); ?></td>
                            <td class="py-3 px-4 text-right"><a href="service_order_details.php?id=<?php echo $o['id']; ?>" class="text-indigo-600 hover:underline">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>
</body>
</html>
