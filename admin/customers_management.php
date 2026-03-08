<?php
/**
 * Admin Customers Management  
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);

$current_user = get_logged_in_user();

// Get all customers
$search   = trim($_GET['search'] ?? '');
$sort     = $_GET['sort'] ?? 'created_at';
$dir      = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

$sort_cols = ['customer_id','name','email','phone','created_at'];
$sort = in_array($sort, $sort_cols) ? $sort : 'created_at';
$sort_col_sql = match($sort) {
    'name'        => "CONCAT(first_name,' ',last_name)",
    'email'       => 'email',
    'phone'       => 'contact_number',
    'customer_id' => 'customer_id',
    default       => 'created_at',
};

$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

// Count total (must happen before ORDER BY and LIMIT)
$count_sql = "SELECT COUNT(*) as total FROM customers WHERE 1=1";
if (!empty($search)) {
    $count_sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
}
$total_filtered = db_query($count_sql, $types ?: null, $params ?: null)[0]['total'] ?? 0;
$total_pages = max(1, ceil($total_filtered / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql .= " ORDER BY $sort_col_sql $dir LIMIT $per_page OFFSET $offset";
$customers = db_query($sql, $types ?: null, $params ?: null) ?: [];

// Get statistics
$total_customers = db_query("SELECT COUNT(*) as count FROM customers")[0]['count'];

// Sort helpers
$build_sort_url = function(string $col) use ($sort, $dir): string {
    $p = array_filter(['sort'=>$col,'dir'=>($sort===$col&&$dir==='ASC')?'DESC':'ASC','search'=>$_GET['search']??'']);
    return '?'.http_build_query($p);
};
$sort_icon = fn(string $col): string => $sort===$col?($dir==='ASC'?' ▲':' ▼'):'';

$page_title = 'Customers Management - Admin';
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
        /* Action Button Style */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border: 1px solid transparent;
            background: transparent;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-action.teal { color: #14b8a6; border-color: #14b8a6; }
        .btn-action.teal:hover { background: #14b8a6; color: white; }
        .btn-action.blue { color: #3b82f6; border-color: #3b82f6; }
        .btn-action.blue:hover { background: #3b82f6; color: white; }
        .btn-action.red { color: #ef4444; border-color: #ef4444; }
        .btn-action.red:hover { background: #ef4444; color: white; }

        /* Modal Styles */
        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:500px; max-height:85vh; overflow-y:auto; margin:16px; position:relative; }

        @keyframes spin { to { transform: rotate(360deg); } }
        [x-cloak] { display: none !important; }

        /* Search Box */
        .search-box { position:relative; }
        .search-box input { padding-left:36px; width:220px; height:38px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:#fff; transition: border-color 0.2s; }
        .search-box input:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
        .search-box .search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }

        /* Clickable Row */
        .customer-row { cursor: pointer; transition: all 0.2s; }
        .customer-row:hover { background-color: #f8fafc !important; }
        .customer-row .actions { pointer-events: auto; }

        /* Tab Styles */
        .tab-btn { padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 8px; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; margin-right: 8px; }
        .tab-btn.active { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
        .tab-btn:not(.active) { color: #6b7280; background: #f9fafb; }
        .tab-btn:hover:not(.active) { background: #f3f4f6; }
        .tab-content { min-height: 250px; }
        .history-item { padding: 10px 0; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
        .history-item:last-child { border-bottom: none; }

        /* Mobile Header */
        .mobile-header { display: none; }
        @media (max-width: 768px) {
            .mobile-header { display: flex; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #fff; z-index: 60; padding: 0 20px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e7eb; }
            .mobile-menu-btn { font-size: 24px; background: none; border: none; cursor: pointer; color: #1f2937; }
            .search-box input { width: 100%; }
        }

        /* Print Styles */
        @media print {
            .sidebar, .mobile-header, .no-print, header, .search-box { display: none !important; }
            .main-content { margin-left: 0 !important; padding-top: 0 !important; }
            .dashboard-container { display: block !important; }
            .card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            body { background: white !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            th, td { border: 1px solid #ccc !important; padding: 8px 12px !important; font-size: 12px !important; }
            th { background: #f3f4f6 !important; font-weight: 700 !important; }
            .btn-action { display: none !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
            .print-header h2 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
            .print-header p { font-size: 12px; color: #6b7280; }
        }
        .print-header { display: none; }
    </style>
</head>
<body x-data="customerModal()">

<div class="dashboard-container">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span style="font-weight:600;font-size:18px;">PrintFlow</span>
        </div>
    </div>

    <!-- Sidebar -->
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Customers Management</h1>
            <button class="btn-secondary no-print" onclick="window.print()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
        </header>

        <main>
            <!-- Print Header (visible only when printing) -->
            <div class="print-header">
                <h2>PrintFlow - Customer List</h2>
                <p>Generated on <?php echo date('F j, Y g:i A'); ?> | Total Customers: <?php echo $total_customers; ?></p>
            </div>

            <!-- Messages -->
            <?php if (isset($success_msg)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 no-print"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (isset($error_msg)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 no-print"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <!-- Customers Table -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                    <h3 style="font-size:16px; font-weight:700; color:#1f2937; margin:0;">Customers List <span style="font-size:13px; font-weight:400; color:#9ca3af;">(<?php echo $total_filtered; ?>)</span></h3>
                    <form method="GET" id="filterForm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;" class="no-print">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
                        <div class="search-box">
                            <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="search" id="searchInput" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>" onkeydown="if(event.key==='Enter'){this.form.submit();}">
                        </div>
                        <button type="submit" class="btn-secondary" style="height:38px;padding:0 14px;font-size:13px;">Search</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3"><a href="<?php echo $build_sort_url('customer_id'); ?>" style="text-decoration:none;color:inherit;">ID<?php echo $sort_icon('customer_id'); ?></a></th>
                                <th class="text-left py-3"><a href="<?php echo $build_sort_url('name'); ?>" style="text-decoration:none;color:inherit;">Name<?php echo $sort_icon('name'); ?></a></th>
                                <th class="text-left py-3"><a href="<?php echo $build_sort_url('email'); ?>" style="text-decoration:none;color:inherit;">Email<?php echo $sort_icon('email'); ?></a></th>
                                <th class="text-left py-3"><a href="<?php echo $build_sort_url('phone'); ?>" style="text-decoration:none;color:inherit;">Contact<?php echo $sort_icon('phone'); ?></a></th>
                                <th class="text-left py-3"><a href="<?php echo $build_sort_url('created_at'); ?>" style="text-decoration:none;color:inherit;">Registered<?php echo $sort_icon('created_at'); ?></a></th>
                                <th class="text-right py-3 no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customersTableBody">
                            <?php foreach ($customers as $customer): ?>
                                <tr class="border-b hover:bg-gray-50 customer-row" @click="openModal(<?php echo $customer['customer_id']; ?>)">
                                    <td class="py-3"><?php echo $customer['customer_id']; ?></td>
                                    <td class="py-3 font-medium name-cell">
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                    </td>
                                    <td class="py-3 email-cell"><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td class="py-3"><?php echo htmlspecialchars($customer['contact_number'] ?? 'N/A'); ?></td>
                                    <td class="py-3"><?php echo format_date($customer['created_at']); ?></td>
                                    <td class="py-3 text-right space-x-1 no-print actions" @click.stop>
                                        <button @click="openModal(<?php echo $customer['customer_id']; ?>)" class="btn-action blue">
                                            Profile
                                        </button>
                                        <button @click="openTransactionModal(<?php echo $customer['customer_id']; ?>)" class="btn-action teal">
                                            Transactions
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="emptyCustomersRow" style="display: none;">
                                <td colspan="6" class="py-8 text-center text-gray-500">No customers found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="customersPagination">
                    <?php
                    $pagination_params = array_filter(['search'=>$search,'sort'=>$sort,'dir'=>$dir]);
                    echo render_pagination($page, $total_pages, $pagination_params);
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Customer Profile Modal -->
<div x-show="showModal" x-cloak>
    <div class="modal-overlay" @click.self="showModal = false">
        <div class="modal-panel" style="max-width: 650px;" @click.stop>
            <div x-show="loading" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading customer profile...</p>
            </div>
            <div x-show="errorMsg && !loading" style="padding:32px;text-align:center;">
                <p style="color:#ef4444;font-size:14px;margin-bottom:12px;" x-text="errorMsg"></p>
                <button @click="showModal = false" class="btn-secondary">Close</button>
            </div>
            <div x-show="customer && !loading">
                <!-- Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;">Customer Profile</h3>
                    <button @click="showModal = false" style="background:transparent;border:none;cursor:pointer;color:#6b7280;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <!-- Customer Info -->
                <div style="padding:24px;border-bottom:1px solid #f3f4f6;">
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                        <div x-text="customer?.initial" style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:20px;"></div>
                        <div style="flex:1;">
                            <div x-text="(customer?.first_name || '') + (customer?.middle_name ? ' ' + customer.middle_name : '') + ' ' + (customer?.last_name || '')" style="font-size:20px;font-weight:700;color:#1f2937;margin-bottom:8px;"></div>
                        </div>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;font-size:13px;">
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">First Name *</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.first_name || 'N/A'"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">Middle Name</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.middle_name || 'N/A'"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">Last Name *</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.last_name || 'N/A'"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">Email</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.email || 'N/A'"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">Contact Number</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.contact_number || 'N/A'"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">Date of Birth</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.dob || 'N/A'"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">Gender</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.gender || 'N/A'"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;display:block;">Registered</label>
                            <span style="color:#1f2937;font-weight:500;" x-text="customer?.created_at || 'N/A'"></span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;">
                    <button @click="showModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Transactions Modal -->
<div x-show="showTransactionModal" x-cloak>
    <div class="modal-overlay" @click.self="showTransactionModal = false">
        <div class="modal-panel" style="max-width: 650px;" @click.stop>
            <div x-show="transLoading" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading transactions...</p>
            </div>
            
            <div x-show="!transLoading">
                <!-- Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;">Customer Transactions</h3>
                        <p style="font-size:13px;color:#6b7280;margin:2px 0 0 0;" x-text="customerName"></p>
                    </div>
                    <button @click="showTransactionModal = false" style="background:transparent;border:none;cursor:pointer;color:#6b7280;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div style="padding:16px 24px 0;">
                    <div style="display:flex;border-bottom:2px solid #f3f4f6;margin-bottom:16px;">
                        <button class="tab-btn" :class="{ 'active': transActiveTab === 'orders' }" @click="transActiveTab = 'orders'; loadTransTabData('orders', 1)">Orders</button>
                        <button class="tab-btn" :class="{ 'active': transActiveTab === 'customizations' }" @click="transActiveTab = 'customizations'; loadTransTabData('customizations', 1)">Customizations</button>
                    </div>

                    <div class="tab-content" style="padding-bottom:16px;min-height:300px;">
                        <div x-show="tabLoading" style="padding:32px;text-align:center;">
                            <div style="width:24px;height:24px;border:2px solid #e5e7eb;border-top-color:#6366f1;border-radius:50%;animation:spin 0.6s linear infinite;margin:0 auto 8px;"></div>
                            <p style="font-size:12px;color:#9ca3af;">Loading...</p>
                        </div>

                        <!-- Orders Tab -->
                        <div x-show="transActiveTab === 'orders' && !tabLoading">
                            <template x-if="orders.length === 0">
                                <p style="text-align:center;padding:40px;color:#9ca3af;font-size:14px;">No orders found.</p>
                            </template>
                            <template x-for="order in orders" :key="order.order_id">
                                <div class="history-item" style="padding:12px; border:1px solid #f3f4f6; border-radius:8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <div style="font-weight:600;color:#1f2937;" x-text="'Order #' + order.order_id"></div>
                                        <div style="font-size:12px;color:#6b7280;" x-text="new Date(order.order_date).toLocaleDateString()"></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-weight:600;color:#1f2937;" x-text="'₱' + parseFloat(order.total_amount).toFixed(2)"></div>
                                        <div style="font-size:11px;" x-html="getStatusBadge(order.status)"></div>
                                    </div>
                                </div>
                            </template>
                            <!-- Orders Pagination -->
                            <div x-show="ordersPagination && ordersPagination.total_pages > 1" style="margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px;">
                                <button x-show="ordersPagination.current_page > 1" @click="loadTransTabData('orders', ordersPagination.current_page - 1)" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;font-size:12px;">Previous</button>
                                <span style="font-size:12px;color:#6b7280;" x-text="`Page ${ordersPagination.current_page} of ${ordersPagination.total_pages}`"></span>
                                <button x-show="ordersPagination.current_page < ordersPagination.total_pages" @click="loadTransTabData('orders', ordersPagination.current_page + 1)" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;font-size:12px;">Next</button>
                            </div>
                        </div>

                        <!-- Customizations Tab -->
                        <div x-show="transActiveTab === 'customizations' && !tabLoading">
                            <template x-if="customizations.length === 0">
                                <p style="text-align:center;padding:40px;color:#9ca3af;font-size:14px;">No customizations found.</p>
                            </template>
                            <template x-for="custom in customizations" :key="custom.id">
                                <div class="history-item" style="padding:12px; border:1px solid #f3f4f6; border-radius:8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <div style="font-weight:600;color:#1f2937;" x-text="custom.service_type"></div>
                                        <div style="font-size:12px;color:#6b7280;" x-text="new Date(custom.created_at).toLocaleDateString()"></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-weight:600;color:#1f2937;" x-text="custom.estimated_total ? '₱' + parseFloat(custom.estimated_total).toFixed(2) : 'Pending'"></div>
                                        <div style="font-size:11px;" x-html="getStatusBadge(custom.status)"></div>
                                    </div>
                                </div>
                            </template>
                            <!-- Customizations Pagination -->
                            <div x-show="customizationsPagination && customizationsPagination.total_pages > 1" style="margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px;">
                                <button x-show="customizationsPagination.current_page > 1" @click="loadTransTabData('customizations', customizationsPagination.current_page - 1)" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;font-size:12px;">Previous</button>
                                <span style="font-size:12px;color:#6b7280;" x-text="`Page ${customizationsPagination.current_page} of ${customizationsPagination.total_pages}`"></span>
                                <button x-show="customizationsPagination.current_page < customizationsPagination.total_pages" @click="loadTransTabData('customizations', customizationsPagination.current_page + 1)" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;font-size:12px;">Next</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;">
                    <button @click="showTransactionModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Search is now server-side via form submission.


    // Customer Modal (Alpine.js component)
    function customerModal() {
        return {
            showModal: false,
            loading: false,
            errorMsg: '',
            customer: null,

            // Transaction Modal State
            showTransactionModal: false,
            transLoading: false,
            transActiveTab: 'orders',
            customerName: '',
            tabLoading: false,
            orders: [],
            customizations: [],
            ordersPagination: null,
            customizationsPagination: null,

            openModal(id) {
                this.showModal = true;
                this.loading = true;
                this.errorMsg = '';
                this.customer = null;

                fetch('api_customer_details.php?id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        this.loading = false;
                        if(data.success) { 
                            this.customer = data.customer;
                        } else { 
                            this.errorMsg = data.error || 'Unknown error'; 
                        }
                    })
                    .catch(e => { 
                        this.loading = false; 
                        this.errorMsg = 'Failed to load customer details.'; 
                        console.error('Fetch error:', e);
                    });
            },

            openTransactionModal(id) {
                this.showTransactionModal = true;
                this.transLoading = true;
                this.transActiveTab = 'orders';
                this.customerName = '';
                this.orders = [];
                this.customizations = [];

                // Load basic info first for the header
                fetch('api_customer_details.php?id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        this.transLoading = false;
                        if(data.success) {
                            this.customer = data.customer;
                            this.customerName = (data.customer.first_name || '') + ' ' + (data.customer.last_name || '');
                            this.loadTransTabData('orders', 1);
                        }
                    })
                    .catch(e => {
                        this.transLoading = false;
                        console.error('Error:', e);
                    });
            },

            async loadTransTabData(tab, page = 1) {
                if (!this.customer?.customer_id) return;
                
                this.tabLoading = true;
                
                try {
                    if (tab === 'orders') {
                        const res = await fetch(`api_customer_details.php?customer_id=${this.customer.customer_id}&page=${page}`);
                        const data = await res.json();
                        this.orders = data.data || [];
                        this.ordersPagination = data.pagination || null;
                    } else if (tab === 'customizations') {
                        const res = await fetch(`job_orders_api.php?action=list_orders&customer_id=${this.customer.customer_id}&page=${page}`);
                        const data = await res.json();
                        this.customizations = data.data || [];
                        this.customizationsPagination = data.pagination || null;
                    }
                } catch (e) {
                    console.error(`Error loading ${tab}:`, e);
                } finally {
                    this.tabLoading = false;
                }
            },

            getStatusBadge(status) {
                const statusMap = {
                    'Pending': 'background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:12px;font-weight:600;',
                    'Processing': 'background:#dbeafe;color:#2563eb;padding:2px 8px;border-radius:12px;font-weight:600;',
                    'Ready for Pickup': 'background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-weight:600;',
                    'Completed': 'background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-weight:600;',
                    'Cancelled': 'background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:12px;font-weight:600;',
                    // Job order statuses
                    'PENDING': 'background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:12px;font-weight:600;',
                    'APPROVED': 'background:#dbeafe;color:#2563eb;padding:2px 8px;border-radius:12px;font-weight:600;',
                    'IN_PRODUCTION': 'background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-weight:600;',
                    'COMPLETED': 'background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-weight:600;',
                };
                const style = statusMap[status] || 'background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:12px;font-weight:600;';
                return `<span style="${style}">${status}</span>`;
            }
        };
    }
</script>

</body>
</html>
