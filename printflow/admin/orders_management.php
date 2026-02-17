<?php
/**
 * Admin Orders Management
 * PrintFlow - Printing Shop PWA  
 * Full CRUD for orders with status updates, filtering, and search
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email as customer_email 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.customer_id 
        WHERE 1=1";
$params = [];
$types = '';

if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($payment_filter)) {
    $sql .= " AND o.payment_status = ?";
    $params[] = $payment_filter;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (o.order_id LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$sql .= " ORDER BY o.order_date DESC LIMIT 50";

$orders = db_query($sql, $types, $params);

// Get statistics
$pending_count = db_query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'")[0]['count'];
$processing_count = db_query("SELECT COUNT(*) as count FROM orders WHERE status = 'Processing'")[0]['count'];
$ready_count = db_query("SELECT COUNT(*) as count FROM orders WHERE status = 'Ready for Pickup'")[0]['count'];

$page_title = 'Orders Management - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Orders Management</h1>
            <span class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($current_user['first_name']); ?></span>
        </header>

        <main>
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="card border-l-4 border-yellow-500">
                    <p class="text-sm text-gray-600 mb-1">Pending Orders</p>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $pending_count; ?></p>
                </div>
                <div class="card border-l-4 border-blue-500">
                    <p class="text-sm text-gray-600 mb-1">Processing</p>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $processing_count; ?></p>
                </div>
                <div class="card border-l-4 border-green-500">
                    <p class="text-sm text-gray-600 mb-1">Ready for Pickup</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo $ready_count; ?></p>
                </div>
            </div>

            <!-- Orders List & Filters -->
            <div class="card">
                <div class="flex flex-col xl:flex-row justify-between xl:items-center gap-4 mb-6">
                    <h3 class="text-lg font-bold whitespace-nowrap">Orders List (<?php echo count($orders); ?>)</h3>
                    
                    <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 flex-grow xl:justify-end">
                        <div class="sm:w-64">
                            <input type="text" name="search" class="input-field py-2 text-sm" placeholder="Search Order # or Customer..." value="<?php echo htmlspecialchars($search); ?>" style="margin-bottom: 0;">
                        </div>
                        
                        <div class="sm:w-40">
                            <select name="status" class="input-field py-2 text-sm" style="margin-bottom: 0;" onchange="this.form.submit()">
                                <option value="">Status: All</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="sm:w-40">
                            <select name="payment" class="input-field py-2 text-sm" style="margin-bottom: 0;" onchange="this.form.submit()">
                                <option value="">Payment: All</option>
                                <option value="Pending" <?php echo $payment_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Paid" <?php echo $payment_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="Failed" <?php echo $payment_filter === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        
                        <!-- Hidden submit button to allow Enter key in search field -->
                        <button type="submit" class="hidden"></button>
                    </form>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left table-fixed">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr class="border-b-2 border-gray-200">
                                <th class="px-4 py-3 w-[10%]">Order #</th>
                                <th class="px-4 py-3 w-[25%]">Customer</th>
                                <th class="px-4 py-3 w-[15%]">Date</th>
                                <th class="px-4 py-3 w-[10%]">Total</th>
                                <th class="px-4 py-3 w-[10%]">Payment</th>
                                <th class="px-4 py-3 w-[15%]">Status</th>
                                <th class="px-4 py-3 w-[15%] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-gray-500">No orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium">#<?php echo $order['order_id']; ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 truncate" style="max-width: 200px;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <div class="text-xs text-gray-500 truncate" style="max-width: 200px;"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo format_date($order['order_date']); ?></td>
                                        <td class="px-4 py-3 font-semibold whitespace-nowrap"><?php echo format_currency($order['total_amount']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo status_badge($order['payment_status'], 'payment'); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo status_badge($order['status'], 'order'); ?></td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button 
                                                @click="$dispatch('open-order-modal', { orderId: <?php echo $order['order_id']; ?> })"
                                                class="text-indigo-600 hover:text-indigo-700 font-medium text-sm"
                                            >
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Order Details Modal (Simplified - full implementation would include AJAX to load order details) -->
<div x-data="{ showModal: false, orderId: null }" 
     @open-order-modal.window="showModal = true; orderId = $event.detail.orderId"
     x-show="showModal"
     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
     style="display: none;">
    <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4" @click.away="showModal = false">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold">Order Details #<span x-text="orderId"></span></h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill= "none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <p class="text-gray-600 mb-4">Full order details would be loaded here via AJAX. Include order items, customer info, payment details, design files, and status update form.</p>
        <div class="flex gap-2">
            <button @click="showModal = false" class="btn-secondary">Close</button>
            <button class="btn-primary">Update Status</button>
        </div>
    </div>
</div>

</body>
</html>
