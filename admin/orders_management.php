<?php
/**
 * Admin Orders Management
 * PrintFlow - Printing Shop PWA  
 * Full CRUD for orders with status updates, filtering, and search (branch-aware)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/branch_ui.php';

require_role(['Admin', 'Manager']);

$current_user = get_logged_in_user();

// ── Branch Context (operational page) ─────────────────
$branchCtx = init_branch_context(false); // analytics-style — allow All
$branchId  = $branchCtx['selected_branch_id'];

// Get filter parameters
$status_filter  = $_GET['status']  ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search         = $_GET['search']  ?? '';
$branch_filter  = '';
if ($branchId !== 'all') {
    $branch_filter = (int)$branchId;
}
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Get sorting parameters
$sort = $_GET['sort'] ?? 'order_id';
$dir  = strtoupper($_GET['dir'] ?? 'DESC');

// Validate sort
$allowed_sorts = ['order_id', 'customer_name', 'order_date', 'total_amount', 'payment_status', 'status'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'order_id';
}
if (!in_array($dir, ['ASC', 'DESC'])) {
    $dir = 'DESC';
}

// Build query (always join branches)
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email as customer_email, b.branch_name 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.customer_id 
        LEFT JOIN branches b ON o.branch_id = b.id 
        WHERE 1=1";
$params = [];
$types = '';

// ── Branch filter ──────────────────────────────────
if ($branch_filter !== '') {
    $sql .= " AND o.branch_id = ?";
    $params[] = $branch_filter;
    $types .= 'i';
}

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
    $search_term = '%' . $search . '%';
    $sql .= " AND (o.order_id LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR o.notes LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sssss';
}

// Count total results - wrap as subquery to avoid GROUP BY issues with JOINs
$count_sql = "SELECT COUNT(*) as total FROM ({$sql}) as count_wrap";
$total_orders = db_query($count_sql, $types, $params)[0]['total'];
$total_pages = max(1, ceil($total_orders / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sort_col = $sort;
if ($sort === 'customer_name') {
    $sort_col = "CONCAT(c.first_name, ' ', c.last_name)";
} elseif ($sort === 'order_date') {
    $sort_col = "o.order_date";
} elseif ($sort === 'total_amount') {
    $sort_col = "o.total_amount";
} elseif ($sort === 'payment_status') {
    $sort_col = "o.payment_status";
} elseif ($sort === 'status') {
    $sort_col = "o.status";
} else {
    $sort_col = "o.order_id";
}

$sql .= " ORDER BY {$sort_col} {$dir} LIMIT $per_page OFFSET $offset";

$orders = db_query($sql, $types, $params);

// Get statistics (branch-aware)
[$bSqlFrag, $bT, $bP] = branch_where_parts('o', $branchId);

$total_count      = db_query("SELECT COUNT(*) as count FROM orders o WHERE 1=1 {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$pending_count    = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Pending' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$processing_count = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Processing' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$ready_count      = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Ready for Pickup' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;
$completed_count  = db_query("SELECT COUNT(*) as count FROM orders o WHERE o.status = 'Completed' {$bSqlFrag}", $bT ?: null, $bP ?: null)[0]['count'] ?? 0;

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
    <?php render_branch_css(); ?>
    <style>
        /* KPI Row - matches reports page */
        .kpi-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px; }
        @media (max-width:768px) { .kpi-row { grid-template-columns:repeat(2, 1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-card.blue::before { background:linear-gradient(90deg,#3b82f6,#60a5fa); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
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
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Orders Management</h1>
            <?php render_branch_selector($branchCtx); ?>
        </header>

        <main>
            <?php render_branch_context_banner($branchCtx['branch_name']); ?>
            <!-- KPI Summary Row (matches reports page style) -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo $total_count; ?></div>
                    <div class="kpi-sub"><?php echo $completed_count; ?> completed</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Pending Orders</div>
                    <div class="kpi-value"><?php echo $pending_count; ?></div>
                    <div class="kpi-sub">Awaiting action</div>
                </div>
                <div class="kpi-card blue">
                    <div class="kpi-label">Processing</div>
                    <div class="kpi-value"><?php echo $processing_count; ?></div>
                    <div class="kpi-sub">In progress</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Ready for Pickup</div>
                    <div class="kpi-value"><?php echo $ready_count; ?></div>
                    <div class="kpi-sub">Awaiting customer</div>
                </div>
            </div>

            <!-- Orders List & Filters -->
            <div class="card">
                <form method="GET" action="" style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
                    
                    <span style="font-size:13px; color:#6b7280; white-space:nowrap;">Showing <strong style="color:#1f2937;"><?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_orders); ?></strong> of <?php echo $total_orders; ?> orders</span>
                    
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap;">
                        <select name="payment" style="height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; padding:0 8px; width:160px; flex-shrink:0;" onchange="this.form.submit()">
                            <option value="">Payment: All</option>
                            <option value="Unpaid" <?php echo $payment_filter === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="Pending Verification" <?php echo $payment_filter === 'Pending Verification' ? 'selected' : ''; ?>>Pending Verification</option>
                            <option value="Paid" <?php echo $payment_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Failed" <?php echo $payment_filter === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="Refunded" <?php echo $payment_filter === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>

                        <select name="status" style="height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; padding:0 8px; width:140px; flex-shrink:0;" onchange="this.form.submit()">
                            <option value="">Status: All</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        
                        <div style="position:relative; flex-shrink:0;">
                            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="search" id="searchInput" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>"
                                   style="padding-left:32px; width:160px; height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;">
                        </div>
                    </div>
                </form>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left table-fixed">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <?php
                            // Helper for sort links
                            $build_sort_url = function($col) use ($sort, $dir, $search, $status_filter, $payment_filter) {
                                $new_dir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
                                $params = ['sort' => $col, 'dir' => $new_dir];
                                if ($search) $params['search'] = $search;
                                if ($status_filter) $params['status'] = $status_filter;
                                if ($payment_filter) $params['payment'] = $payment_filter;
                                return '?' . http_build_query($params);
                            };
                            $sort_icon = function($col) use ($sort, $dir) {
                                if ($sort !== $col) return '';
                                return $dir === 'ASC' ? ' <span style="font-size:10px;">▲</span>' : ' <span style="font-size:10px;">▼</span>';
                            };
                            ?>
                            <tr class="border-b-2 border-gray-200">
                                <th class="px-4 py-3 w-[10%]">
                                    <a href="<?php echo $build_sort_url('order_id'); ?>" class="hover:text-teal-600 block">Order #<?php echo $sort_icon('order_id'); ?></a>
                                </th>
                                <th class="px-4 py-3 w-[25%]">
                                    <a href="<?php echo $build_sort_url('customer_name'); ?>" class="hover:text-teal-600 block">Customer<?php echo $sort_icon('customer_name'); ?></a>
                                </th>
                                <th class="px-4 py-3 w-[15%]">
                                    <a href="<?php echo $build_sort_url('order_date'); ?>" class="hover:text-teal-600 block">Date<?php echo $sort_icon('order_date'); ?></a>
                                </th>
                                <th class="px-4 py-3 w-[15%]">Branch</th>
                                <th class="px-4 py-3 w-[10%]">
                                    <a href="<?php echo $build_sort_url('total_amount'); ?>" class="hover:text-teal-600 block">Total<?php echo $sort_icon('total_amount'); ?></a>
                                </th>
                                <th class="px-4 py-3 w-[10%]">
                                    <a href="<?php echo $build_sort_url('payment_status'); ?>" class="hover:text-teal-600 block">Payment<?php echo $sort_icon('payment_status'); ?></a>
                                </th>
                                <th class="px-4 py-3 w-[15%]">
                                    <a href="<?php echo $build_sort_url('status'); ?>" class="hover:text-teal-600 block">Status<?php echo $sort_icon('status'); ?></a>
                                </th>
                                <th class="px-4 py-3 w-[15%] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php if (empty($orders)): ?>
                                <tr id="emptyOrdersRow">
                                    <td colspan="8" class="py-8 text-center text-gray-500">
                                        <?php echo $search ? 'No orders found matching "' . htmlspecialchars($search) . '"' : 'No orders found'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyOrdersRow" style="display:none;">
                                    <td colspan="8" class="py-8 text-center text-gray-500">
                                        No orders found
                                    </td>
                                </tr>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium"><?php echo $order['order_id']; ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($order['customer_name']); ?>"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <div class="text-xs text-gray-500" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($order['customer_email']); ?>"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php echo format_date($order['order_date']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap"><?php
                                            echo get_branch_badge_html(
                                                (int)($order['branch_id'] ?? 0),
                                                $order['branch_name'] ?? 'Main'
                                            );
                                        ?></td>
                                        <td class="px-4 py-3 font-semibold whitespace-nowrap"><?php echo format_currency($order['total_amount']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php
                                                $pc = match($order['payment_status']) {
                                                    'Pending' => 'background:#fef9c3;color:#854d0e;',
                                                    'Paid'    => 'background:#dcfce7;color:#166534;',
                                                    'Failed'  => 'background:#fee2e2;color:#991b1b;',
                                                    default   => 'background:#fef9c3;color:#854d0e;'
                                                };
                                            ?>
                                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $pc; ?>">
                                                <?php echo $order['payment_status']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php
                                                $sc = match($order['status']) {
                                                    'Pending'           => 'background:#fef9c3;color:#854d0e;',
                                                    'Processing'        => 'background:#dbeafe;color:#1e40af;',
                                                    'Ready for Pickup'  => 'background:#ede9fe;color:#5b21b6;',
                                                    'Completed'         => 'background:#dcfce7;color:#166534;',
                                                    'Cancelled'         => 'background:#fee2e2;color:#991b1b;',
                                                    default             => 'background:#fef9c3;color:#854d0e;'
                                                };
                                            ?>
                                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $sc; ?>">
                                                <?php echo $order['status']; ?>
                                            </span>
                                        </td>
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
                <div id="ordersPagination">
                    <?php 
                    $pagination_params = [];
                    if ($search) $pagination_params['search'] = $search;
                    if ($status_filter) $pagination_params['status'] = $status_filter;
                    if ($payment_filter) $pagination_params['payment'] = $payment_filter;
                    $pagination_params['sort'] = $sort;
                    $pagination_params['dir'] = $dir;
                    echo render_pagination($page, $total_pages, $pagination_params); 
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Order Details Modal -->
<div x-show="showModal"
     x-cloak>
    
    <!-- Overlay -->
    <div class="modal-overlay" @click.self="showModal = false">
        <!-- Modal Panel -->
        <div class="modal-panel" @click.stop>
            
            <!-- Loading State -->
            <div x-show="loading" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading order details...</p>
            </div>

            <!-- Error State -->
            <div x-show="errorMsg && !loading" style="padding:32px;text-align:center;">
                <p style="color:#ef4444;font-size:14px;margin-bottom:12px;" x-text="errorMsg"></p>
                <button @click="showModal = false" class="btn-secondary">Close</button>
            </div>

            <!-- Order Details Content -->
            <div x-show="order && !loading">
                <!-- Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;">Order #<span x-text="order?.order_id"></span></h3>
                        <p style="font-size:13px;color:#6b7280;margin:2px 0 0;" x-text="order?.order_date"></p>
                        <p style="font-size:12px;color:#4F46E5;margin:3px 0 0;font-weight:600;"><span x-text="order?.branch_name"></span></p>
                    </div>
                    <button @click="showModal = false" style="width:32px;height:32px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Customer & Order Info Grid -->
                <div style="padding:20px 24px;display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <!-- Customer Info -->
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

                    <!-- Order Status -->
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

                <!-- Order Items Table -->
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
                                            <div x-text="item.product_name" style="font-weight:500;color:#1f2937;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" :title="item.product_name"></div>
                                            <template x-if="item.variant_name">
                                                <div style="margin-top:3px;">
                                                    <span x-text="'📐 ' + item.variant_name"
                                                          style="display:inline-flex;align-items:center;background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:500;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" :title="'📐 ' + item.variant_name"></span>
                                                </div>
                                            </template>
                                            <div x-text="item.category" style="font-size:11px;color:#9ca3af;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" :title="item.category"></div>
                                            
                                            <!-- Tarpaulin/Sticker Specific Specs (Roll-based) -->
                                            <template x-if="item.category && (item.category.toUpperCase().includes('TARPAULIN') || item.category.toUpperCase().includes('STKR'))">
                                                <div style="margin-top:8px;">
                                                    <div x-show="!item.editingTarp" style="font-size:12px; background:#f0fdf4; padding:8px; border-radius:8px; border:1px solid #dcfce7;">
                                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                                            <div>
                                                                <template x-if="item.tarp_details">
                                                                    <div>
                                                                        <span style="color:#166534; font-weight:600;" x-text="item.tarp_details.width_ft + ' x ' + item.tarp_details.height_ft + ' ft'"></span>
                                                                        <span style="color:#6b7280; margin-left:8px;" x-text="'Roll: ' + (item.tarp_details.roll_code || 'Not Assigned')"></span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="!item.tarp_details">
                                                                    <span style="color:#991b1b; font-weight:600;">Dimensions not set</span>
                                                                </template>
                                                            </div>
                                                            <button @click="startTarpEdit(item)" style="font-size:11px; color:#4F46E5; background:none; border:none; cursor:pointer; font-weight:600; text-decoration:underline;">Configure</button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div x-show="item.editingTarp" style="font-size:12px; background:#fff; padding:12px; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                                                            <div>
                                                                <label style="display:block; font-size:10px; color:#6b7280; margin-bottom:2px;">Width (FT)</label>
                                                                <input type="number" x-model="item.tempWidth" @change="fetchRolls(item)" style="width:100% !important; height:32px; border:1px solid #e5e7eb; border-radius:6px; padding:0 8px;">
                                                            </div>
                                                            <div>
                                                                <label style="display:block; font-size:10px; color:#6b7280; margin-bottom:2px;">Height (FT)</label>
                                                                <input type="number" x-model="item.tempHeight" style="width:100% !important; height:32px; border:1px solid #e5e7eb; border-radius:6px; padding:0 8px;">
                                                            </div>
                                                        </div>
                                                        <div style="margin-bottom:8px;">
                                                            <label style="display:block; font-size:10px; color:#6b7280; margin-bottom:2px;">Inventory Roll</label>
                                                            <select x-model="item.tempRollId" style="width:100% !important; height:32px; border:1px solid #e5e7eb; border-radius:6px; padding:0 8px; display:block;">
                                                                <option value="">Select a Roll</option>
                                                                <template x-for="roll in item.availableRolls || []" :key="roll.id">
                                                                    <option :value="roll.id" x-text="roll.roll_code + ' (' + roll.remaining_length_ft + ' ft left)'"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                        <div style="display:flex; gap:8px; justify-content:flex-end;">
                                                            <button @click="item.editingTarp = false" style="padding:4px 10px; font-size:11px; background:#f3f4f6; border-radius:6px; border:none; cursor:pointer;">Cancel</button>
                                                            <button @click="saveTarpSpecs(item)" style="padding:4px 10px; font-size:11px; background:#4F46E5; color:white; border-radius:6px; border:none; cursor:pointer;" :disabled="item.savingTarp">Save</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
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
    // Submit form on enter for search natively handled by form HTML

function orderModal() {
    return {
        showModal: false,
        loading: false,
        errorMsg: '',
        order: null,
        items: [],
        selectedStatus: 'Pending',
        updatingStatus: false,
        statusUpdateMsg: '',
        statusUpdateError: false,

        openModal(orderId) {
            this.showModal = true;
            this.loading = true;
            this.errorMsg = '';
            this.statusUpdateMsg = '';
            this.order = null;
            this.items = [];

            fetch('/printflow/admin/api_order_details.php?id=' + orderId)
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.order = data.order;
                        this.items = data.items.map(i => ({
                            ...i,
                            editingTarp: false,
                            savingTarp: false,
                            tempWidth: i.tarp_details?.width_ft || 0,
                            tempHeight: i.tarp_details?.height_ft || 0,
                            tempRollId: i.tarp_details?.roll_id || '',
                            availableRolls: []
                        }));
                        this.selectedStatus = data.order.status;
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

        startTarpEdit(item) {
            item.editingTarp = true;
            if (item.tempWidth > 0 && item.availableRolls.length === 0) {
                this.fetchRolls(item);
            }
        },

        fetchRolls(item) {
            if (!item.tempWidth || item.tempWidth <= 0) return;
            fetch('/printflow/admin/api_tarp_rolls.php?action=list_available&width=' + item.tempWidth)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        item.availableRolls = data.rolls;
                    }
                });
        },

        async saveTarpSpecs(item) {
            if (!item.tempWidth || !item.tempHeight || !item.tempRollId) {
                alert('Please fill all tarpaulin specifications.');
                return;
            }
            item.savingTarp = true;
            try {
                const resp = await fetch('/printflow/admin/api_save_tarp_specs.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        order_item_id: item.order_item_id,
                        roll_id: item.tempRollId,
                        width_ft: item.tempWidth,
                        height_ft: item.tempHeight,
                        csrf_token: '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    item.tarp_details = {
                        width_ft: item.tempWidth,
                        height_ft: item.tempHeight,
                        roll_id: item.tempRollId,
                        roll_code: item.availableRolls.find(r => r.id == item.tempRollId)?.roll_code || 'Assigned'
                    };
                    item.editingTarp = false;
                } else {
                    alert(data.error || 'Failed to save specifications.');
                }
            } catch (e) {
                alert('Network error.');
            }
            item.savingTarp = false;
        },

        async updateStatus() {
            if (!this.order) return;
            this.updatingStatus = true;
            this.statusUpdateMsg = '';
            try {
                const resp = await fetch('/printflow/admin/api_update_order_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        order_id: this.order.order_id,
                        status: this.selectedStatus,
                        csrf_token: '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    this.statusUpdateMsg = data.message;
                    this.statusUpdateError = false;
                    this.order.status = this.selectedStatus;
                    // Reload page to refresh KPI counts
                    setTimeout(() => location.reload(), 1200);
                } else {
                    this.statusUpdateMsg = data.error || 'Update failed.';
                    this.statusUpdateError = true;
                }
            } catch (e) {
                this.statusUpdateMsg = 'Network error.';
                this.statusUpdateError = true;
            }
            this.updatingStatus = false;
        },

        statusBadge(status, type) {
            const colors = {
                order: {
                    'Pending': 'background:#fef3c7;color:#92400e;',
                    'Processing': 'background:#dbeafe;color:#1e40af;',
                    'Ready for Pickup': 'background:#dcfce7;color:#166534;',
                    'Completed': 'background:#dcfce7;color:#166534;',
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
