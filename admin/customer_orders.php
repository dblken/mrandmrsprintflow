<?php
/**
 * Customer Orders View
 * PrintFlow - Printing Shop PWA
 * innovative: displays specific customer's order history and stats
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: customers_management.php");
    exit;
}

$customer_id = (int)$_GET['id'];

// Fetch Customer Details
$customer = db_query("SELECT * FROM customers WHERE customer_id = ?", "i", [$customer_id]);
if (empty($customer)) {
    die("Customer not found.");
}
$customer = $customer[0];
$customer_name = $customer['first_name'] . ' ' . $customer['last_name'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for orders
$sql = "SELECT * FROM orders WHERE customer_id = ?";
$params = [$customer_id];
$types = 'i';

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($payment_filter)) {
    $sql .= " AND payment_status = ?";
    $params[] = $payment_filter;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (order_id LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $types .= 's';
}

$sql .= " ORDER BY order_date DESC";

$orders = db_query($sql, $types, $params);

// Get Customer Statistics
$total_orders = count($orders);
$total_spent = db_query("SELECT SUM(total_amount) as total FROM orders WHERE customer_id = ? AND payment_status = 'Paid'", "i", [$customer_id])[0]['total'] ?? 0;
$last_order = db_query("SELECT MAX(order_date) as last_date FROM orders WHERE customer_id = ?", "i", [$customer_id])[0]['last_date'] ?? 'N/A';

$page_title = 'Orders - ' . $customer_name;
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
    <style>
        /* KPI Row */
        .kpi-row { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px; }
        @media (max-width:768px) { .kpi-row { grid-template-columns:1fr; } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-value { font-size:26px; font-weight:800; color:#1f2937; font-variant-numeric:tabular-nums; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }
        
        /* Modal */
        [x-cloak] { display: none !important; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:720px; max-height:85vh; overflow-y:auto; margin:16px; position:relative; }

        /* Action Button Style */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 16px;
            border: 1px solid #14b8a6; /* teal-500 */
            color: #14b8a6;
            background: transparent;
            border-radius: 9999px; /* full rounded */
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-action:hover {
            background: #14b8a6;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(20, 184, 166, 0.2);
        }
    </style>
</head>
<body x-data="orderModal()">

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <div class="flex items-center gap-4">
                <a href="customers_management.php" class="text-gray-500 hover:text-gray-700">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div>
                    <h1 class="page-title">Orders: <?php echo htmlspecialchars($customer_name); ?></h1>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($customer['email']); ?></p>
                </div>
            </div>
        </header>

        <main>
            <!-- KPI Summary Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo $total_orders; ?></div>
                    <div class="kpi-sub">Lifetime orders</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Total Spent</div>
                    <div class="kpi-value">₱<?php echo number_format($total_spent, 2); ?></div>
                    <div class="kpi-sub">On paid orders</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Last Order</div>
                    <div class="kpi-value" style="font-size:18px; line-height:39px;">
                        <?php echo $last_order !== 'N/A' ? date('M j, Y', strtotime($last_order)) : 'Never'; ?>
                    </div>
                    <div class="kpi-sub">Most recent activity</div>
                </div>
            </div>

            <!-- Orders List -->
            <div class="card">
                <div class="flex flex-col xl:flex-row justify-between xl:items-center gap-4 mb-6">
                    <h3 class="text-lg font-bold whitespace-nowrap">Order History</h3>
                    
                    <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 flex-grow xl:justify-end">
                        <input type="hidden" name="id" value="<?php echo $customer_id; ?>">
                        
                        <div class="sm:w-64">
                            <input type="text" name="search" class="input-field py-2 text-sm" placeholder="Search Order #..." value="<?php echo htmlspecialchars($search); ?>" style="margin-bottom: 0;">
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
                            </select>
                        </div>
                        
                        <button type="submit" class="hidden"></button>
                    </form>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left table-fixed">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr class="border-b-2 border-gray-200">
                                <th class="px-4 py-3 w-[15%]">Order #</th>
                                <th class="px-4 py-3 w-[20%]">Date</th>
                                <th class="px-4 py-3 w-[15%]">Total</th>
                                <th class="px-4 py-3 w-[15%]">Payment</th>
                                <th class="px-4 py-3 w-[15%]">Status</th>
                                <th class="px-4 py-3 w-[20%] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500">No orders found for this customer.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium">#<?php echo $order['order_id']; ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo format_date($order['order_date']); ?></td>
                                        <td class="px-4 py-3 font-semibold whitespace-nowrap"><?php echo format_currency($order['total_amount']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo status_badge($order['payment_status'], 'payment'); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo status_badge($order['status'], 'order'); ?></td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button 
                                                @click="openModal(<?php echo $order['order_id']; ?>)"
                                                class="btn-action"
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

<!-- Order Details Modal (Same structure as orders_management.php) -->
<div x-show="showModal" x-cloak>
    <div class="modal-overlay" @click.self="showModal = false">
        <div class="modal-panel" @click.stop>
            <!-- Loading -->
            <div x-show="loading" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading order details...</p>
            </div>
            <!-- Error -->
            <div x-show="errorMsg && !loading" style="padding:32px;text-align:center;">
                <p style="color:#ef4444;font-size:14px;margin-bottom:12px;" x-text="errorMsg"></p>
                <button @click="showModal = false" class="btn-secondary">Close</button>
            </div>
            <!-- Content -->
            <div x-show="order && !loading">
                <!-- Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;">Order #<span x-text="order?.order_id"></span></h3>
                        <p style="font-size:13px;color:#6b7280;margin:2px 0 0;" x-text="order?.order_date"></p>
                    </div>
                    <button @click="showModal = false" style="width:32px;height:32px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Info Grid -->
                <div style="padding:20px 24px;display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div style="background:#f9fafb;border-radius:10px;padding:16px;border:1px solid #f3f4f6;">
                        <h4 style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Customer</h4>
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <div x-text="order?.customer_initial" style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:14px;flex-shrink:0;"></div>
                            <div>
                                <div x-text="order?.customer_name" style="font-weight:600;font-size:14px;color:#1f2937;"></div>
                                <div x-text="order?.customer_email" style="font-size:12px;color:#6b7280;"></div>
                            </div>
                        </div>
                        <div style="font-size:13px;color:#6b7280;">
                            <span>Phone: </span><span x-text="order?.customer_phone" style="color:#1f2937;font-weight:500;"></span>
                        </div>
                    </div>
                    <div style="background:#f9fafb;border-radius:10px;padding:16px;border:1px solid #f3f4f6;">
                        <h4 style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Order Status</h4>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:13px;color:#6b7280;">Status</span>
                                <span x-html="statusBadge(order?.status, 'order')"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:13px;color:#6b7280;">Payment</span>
                                <span x-html="statusBadge(order?.payment_status, 'payment')"></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:13px;color:#6b7280;">Total</span>
                                <span x-text="order?.total_amount" style="font-weight:700;font-size:16px;color:#1f2937;"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Items Table -->
                <div style="padding:0 24px 20px;">
                    <h4 style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 12px;">Order Items</h4>
                    <div style="border:1px solid #f3f4f6;border-radius:10px;overflow:hidden;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#f9fafb;">
                                    <th style="text-align:left;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Product</th>
                                    <th style="text-align:center;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Qty</th>
                                    <th style="text-align:right;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Price</th>
                                    <th style="text-align:right;padding:10px 14px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="items.length === 0">
                                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#9ca3af;">No items found</td></tr>
                                </template>
                                <template x-for="item in items" :key="item.sku">
                                    <tr style="border-top:1px solid #f3f4f6;">
                                        <td style="padding:10px 14px;">
                                            <div x-text="item.product_name" style="font-weight:500;color:#1f2937;"></div>
                                            <div x-text="item.category" style="font-size:11px;color:#9ca3af;"></div>
                                        </td>
                                        <td style="padding:10px 14px;text-align:center;" x-text="item.quantity"></td>
                                        <td style="padding:10px 14px;text-align:right;color:#6b7280;" x-text="item.unit_price_formatted"></td>
                                        <td style="padding:10px 14px;text-align:right;font-weight:600;color:#1f2937;" x-text="item.subtotal_formatted"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot x-show="items.length > 0">
                                <tr style="border-top:2px solid #e5e7eb;background:#f9fafb;">
                                    <td colspan="3" style="padding:12px 14px;text-align:right;font-weight:600;font-size:14px;">Total</td>
                                    <td style="padding:12px 14px;text-align:right;font-weight:700;font-size:15px;color:#1f2937;" x-text="order?.total_amount"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <!-- Notes -->
                <template x-if="order?.notes">
                    <div style="padding:0 24px 20px;">
                        <h4 style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 8px;">Notes</h4>
                        <p x-text="order.notes" style="font-size:13px;color:#6b7280;background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #f3f4f6;margin:0;"></p>
                    </div>
                </template>
                <!-- Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;">
                    <button @click="showModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function orderModal() {
    return {
        showModal: false,
        loading: false,
        errorMsg: '',
        order: null,
        items: [],

        openModal(orderId) {
            this.showModal = true;
            this.loading = true;
            this.errorMsg = '';
            this.order = null;
            this.items = [];

            fetch('/printflow/admin/api_order_details.php?id=' + orderId)
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.order = data.order;
                        this.items = data.items;
                    } else {
                        this.errorMsg = data.error || 'Failed to load order details.';
                    }
                })
                .catch(err => {
                    this.loading = false;
                    this.errorMsg = 'Network error. Please try again.';
                    console.error('Order details fetch error:', err);
                });
        },

        statusBadge(status, type) {
            // Re-using the same badge logic from orders_management.php can also be centralized in a js file
            const colors = {
                order: {
                    'Pending': 'background:#fef3c7;color:#92400e;',
                    'Processing': 'background:#dbeafe;color:#1e40af;',
                    'Ready for Pickup': 'background:#dcfce7;color:#166534;',
                    'Completed': 'background:#f3f4f6;color:#374151;',
                    'Cancelled': 'background:#fee2e2;color:#991b1b;'
                },
                payment: {
                    'Pending': 'background:#fef3c7;color:#92400e;',
                    'Unpaid': 'background:#fee2e2;color:#991b1b;',
                    'Paid': 'background:#dcfce7;color:#166534;',
                    'Refunded': 'background:#f3f4f6;color:#374151;',
                    'Failed': 'background:#fee2e2;color:#991b1b;'
                }
            };
            const style = (colors[type] && colors[type][status]) || 'background:#f3f4f6;color:#374151;';
            return `<span style="display:inline-flex;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;${style}">${status || 'N/A'}</span>`;
        }
    };
}
</script>

</body>
</html>
