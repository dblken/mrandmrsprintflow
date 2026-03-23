<?php
/**
 * Staff: Customizations Management
 * Production tracking & material assignment.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

if (!defined('BASE_URL')) define('BASE_URL', '/printflow');
require_role(['Admin', 'Staff', 'Manager']);
$page_title = 'Customizations - PrintFlow';

$branchFilter = printflow_branch_filter_for_user();
$joBranchSql = '';
$joBranchTypes = '';
$joBranchParams = [];
$ordBranchSql = '';
$ordBranchTypes = '';
$ordBranchParams = [];
if ($branchFilter !== null) {
    $b = (int) $branchFilter;
    $joBranchSql = ' AND COALESCE(jo.branch_id, (SELECT o2.branch_id FROM orders o2 WHERE o2.order_id = jo.order_id LIMIT 1)) = ?';
    $joBranchTypes = 'i';
    $joBranchParams = [$b];
    $ordBranchSql = ' AND branch_id = ?';
    $ordBranchTypes = 'i';
    $ordBranchParams = [$b];
}

// Get statistics for KPIs (include both job_orders and regular orders pending review)
<<<<<<< HEAD
$total_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE 1=1" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$total_orders_pending = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
=======
$order_id_from_url = $_GET['order_id'] ?? null;
if ($order_id_from_url) {
    // Basic pre-fetch to satisfy "Always fetch order data using order_id from the URL"
    $pre_fetched = db_query("SELECT * FROM orders WHERE order_id = ?", 'i', [$order_id_from_url])[0] ?? null;
    if (!$pre_fetched) {
        $pre_fetched = db_query("SELECT * FROM job_orders WHERE id = ?", 'i', [$order_id_from_url])[0] ?? null;
    }

    // MANDATORY FIX: If order_id is provided but not found, stop execution.
    if (!$pre_fetched) {
        die("Invalid order.");
    }
}

$total_jobs_jobs = db_query("SELECT COUNT(*) as count FROM job_orders")[0]['count'];
$total_orders_pending = db_query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision')")[0]['count'];
>>>>>>> d84ca5ae2d12f1a3809732a3caf25e36f0537ea6
$total_jobs = $total_jobs_jobs + $total_orders_pending;

$pending_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'PENDING'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$pending_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$pending_jobs = $pending_jobs_jobs + $pending_orders;

$approval_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'APPROVED'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$in_production_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'IN_PRODUCTION'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$in_production_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Processing', 'In Production', 'Printing')" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$in_production = $in_production_jobs + $in_production_orders;

$completed_jobs_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'COMPLETED'" . $joBranchSql,
    $joBranchTypes ?: null,
    $joBranchParams ?: null
)[0]['count'];
$completed_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status = 'Completed'" . $ordBranchSql,
    $ordBranchTypes ?: null,
    $ordBranchParams ?: null
)[0]['count'];
$completed_jobs = $completed_jobs_jobs + $completed_orders;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="turbo-visit-control" content="reload">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .kpi-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px; }
        @media (max-width:768px) { .kpi-row { grid-template-columns:repeat(2, 1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-card.blue::before { background:linear-gradient(90deg,#06A1A1,#9ED7C4); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#06A1A1,#9ED7C4); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }

        /* Action Button Style — matches customers_management.php */
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
        .btn-action.blue { color: #06A1A1; border-color: #06A1A1; }
        .btn-action.blue:hover { background: #06A1A1; color: white; }
        .btn-action.red { color: #ef4444; border-color: #ef4444; }
        .btn-action.red:hover { background: #ef4444; color: white; }
        .btn-action.amber { color: #f59e0b; border-color: #f59e0b; }
        .btn-action.amber:hover { background: #f59e0b; color: white; }
        .btn-action.emerald { color: #059669; border-color: #059669; }
        .btn-action.emerald:hover { background: #059669; color: white; }

        /* Refined Enterprise Table Styles (Uniform with Orders Page) */
        /* Toolbar: tabs wrap + search stays on its own row on narrow screens (no overlap with table) */
        .pf-custom-toolbar {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
            margin-bottom: 20px;
        }
        @media (min-width: 1100px) {
            .pf-custom-toolbar {
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
                gap: 20px;
            }
        }
        .pf-custom-tabs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }
        .pf-custom-search { flex-shrink: 0; }

        .pill-tab { 
            position: relative;
            padding: 8px 14px; 
            font-weight: 600; 
            font-size: 11px; 
            font-family: inherit;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280; 
            border-radius: 9999px; 
            transition: all 0.2s; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px;
            background: transparent;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .pill-tab:hover { background: #f3f4f6; color: #111827; }
        .pill-tab.active { background: #eef2ff; color: #4f46e5; border: 1px solid #4f46e5; }
        .tab-count { 
            background: #4f46e5; 
            color: white; 
            font-size: 10px; 
            padding: 1px 6px; 
            border-radius: 9999px; 
            font-weight: 600;
        }
        .pill-tab:not(.active) .tab-count { background: #e5e7eb; color: #6b7280; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        /* Status Colors to match system standard */
        .badge-fulfilled { background: #dcfce7; color: #15803d; }
        .badge-confirmed { background: #e0f2fe; color: #0369a1; }
        .badge-partial { background: #fef3c7; color: #a16207; }
        .badge-cancelled { background: #fee2e2; color: #b91c1c; }

        /* Unified Table Typography */
        .table-text-main { font-size: 13px; color: #111827; font-weight: 500; }
        .table-text-sub { font-size: 11px; color: #6b7280; font-weight: 400; }
        
        thead th { 
            font-size: 11px; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            color: #6b7280;
            background: #f9fafb;
            border-bottom: 2px solid #f3f4f6;
        }

        .row-indicator {
            position: absolute;
            left: 0;
            top: 2px;
            bottom: 2px;
            width: 3px;
            background: #4f46e5;
            border-radius: 0 4px 4px 0;
            opacity: 0;
            transition: opacity 0.2s;
        }
        tr:hover .row-indicator { opacity: 1; }

        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:560px; max-height:88vh; overflow-y:auto; margin:16px; position:relative; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pf-tab-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.45; } }
        [x-cloak] { display: none !important; }
        /* Real box (not display:contents) so Alpine binds one subtree; min-width avoids flex overflow quirks. */
        .pf-staff-customizations-root { min-width: 0; }
    </style>
</head>
<<<<<<< HEAD
<body x-data="joManager('ALL')" data-base-url="<?php echo htmlspecialchars(BASE_URL); ?>" data-csrf="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
=======
<body>
>>>>>>> 1d610692b6051bc69bfc301d358a7ad23fdab53c
<div class="dashboard-container">
    <?php 
    if (in_array($_SESSION['user_type'] ?? '', ['Staff', 'Manager'])) {
        include __DIR__ . '/../includes/staff_sidebar.php';
    } else {
        include __DIR__ . '/../includes/admin_sidebar.php';
    }
    ?>
    <div class="main-content">
        <div id="staffJoCustomizationsPage" x-data="joManager('ALL')" class="pf-staff-customizations-root">
        <header>
            <h1 class="page-title">Customizations</h1>
        </header>

        <main>
            <!-- KPI Summary Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Customizations</div>
                    <div class="kpi-value"><?php echo $total_jobs; ?></div>
                    <div class="kpi-sub"><?php echo $completed_jobs; ?> completed</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Pending Approval</div>
                    <div class="kpi-value"><?php echo $pending_jobs; ?></div>
                    <div class="kpi-sub">Awaiting review</div>
                </div>
                <div class="kpi-card blue">
                    <div class="kpi-label">Approved</div>
                    <div class="kpi-value"><?php echo $approval_jobs; ?></div>
                    <div class="kpi-sub">Ready for print</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">In Production</div>
                    <div class="kpi-value"><?php echo $in_production; ?></div>
                    <div class="kpi-sub">Currently printing</div>
                </div>
            </div>

            <!-- Jobs List & Filters (matching Enterprise reference) -->
            <div class="card overflow-visible">
                <div class="pf-custom-toolbar">
                    <div class="pf-custom-tabs">
                        <!-- Static tabs: avoids Turbo cache + Alpine.initTree re-running x-for and duplicating buttons. -->
                        <button type="button" @click="activeStatus = 'ALL'" :class="activeStatus === 'ALL' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>ALL</span>
                            <span class="tab-count" x-text="getStatusCount('ALL')"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'PENDING'" :class="activeStatus === 'PENDING' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>PENDING</span>
                            <span class="tab-count" x-text="getStatusCount('PENDING')"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'APPROVED'" :class="activeStatus === 'APPROVED' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>APPROVED</span>
                            <span class="tab-count" x-text="getStatusCount('APPROVED')"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'TO_PAY'" :class="activeStatus === 'TO_PAY' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>TO_PAY</span>
                            <span class="tab-count" x-text="getStatusCount('TO_PAY')"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'VERIFY_PAY'" :class="activeStatus === 'VERIFY_PAY' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>TO VERIFY</span>
                            <span class="tab-count" x-text="getStatusCount('VERIFY_PAY')"></span>
                            <span x-show="getStatusCount('VERIFY_PAY') > 0" style="position:absolute;top:-4px;right:-4px;width:10px;height:10px;background:#ef4444;border-radius:9999px;border:2px solid #fff;animation:pf-tab-pulse 2s ease-in-out infinite;"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'IN_PRODUCTION'" :class="activeStatus === 'IN_PRODUCTION' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>IN_PRODUCTION</span>
                            <span class="tab-count" x-text="getStatusCount('IN_PRODUCTION')"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'TO_RECEIVE'" :class="activeStatus === 'TO_RECEIVE' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>TO PICKUP</span>
                            <span class="tab-count" x-text="getStatusCount('TO_RECEIVE')"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'COMPLETED'" :class="activeStatus === 'COMPLETED' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>COMPLETED</span>
                            <span class="tab-count" x-text="getStatusCount('COMPLETED')"></span>
                        </button>
                        <button type="button" @click="activeStatus = 'CANCELLED'" :class="activeStatus === 'CANCELLED' ? 'active' : ''" class="pill-tab" style="position:relative;">
                            <span>CANCELLED</span>
                            <span class="tab-count" x-text="getStatusCount('CANCELLED')"></span>
                        </button>
                    </div>
                    <div class="pf-custom-search">
                        <div style="position:relative;max-width:280px;">
                            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="search" placeholder="Filter jobs..." style="padding-left:32px;width:100%;min-width:180px;max-width:280px;height:36px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;font-weight:400;outline:none;transition:border-color 0.2s;box-sizing:border-box;" onfocus="this.style.borderColor='#06A1A1'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto -mx-6 px-6" style="clear:both;">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="pl-6 pr-4 py-4 w-[12%] border-b border-gray-100">Order #</th>
                                <th class="px-4 py-4 w-[30%] border-b border-gray-100">Customization Info</th>
                                <th class="px-4 py-4 w-[18%] border-b border-gray-100 text-center">Status</th>
                                <th class="px-4 py-4 w-[20%] border-b border-gray-100">Customer</th>
                                <th class="px-4 py-4 w-[15%] border-b border-gray-100 text-right">Created</th>
                                <th class="px-4 py-4 w-[10%] border-b border-gray-100 text-center uppercase tracking-widest text-[10px]">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="jo in filteredOrders" :key="(jo.order_type || 'JOB') + '-' + jo.id">
                                <tr @click="viewDetails(jo.id, jo.order_type || 'JOB')" class="group transition-all hover:bg-gray-50/50 relative cursor-pointer">
                                    <td class="pl-6 pr-4 py-4 relative">
                                        <div class="row-indicator"></div>
                                        <span class="table-text-main" x-text="(jo.order_type === 'ORDER' ? '#ORD-' : (jo.order_type === 'SERVICE' ? '#SRV-' : '#JO-')) + jo.id.toString().padStart(5, '0')"></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex flex-col gap-0 min-w-0">
                                                <div class="table-text-main truncate" x-text="jo.job_title || jo.service_type"></div>
                                                <div class="table-text-sub uppercase tracking-wider" x-show="jo.order_type !== 'SERVICE'"><span x-text="jo.width_ft"></span>'×<span x-text="jo.height_ft"></span>' • <span x-text="jo.quantity"></span> pcs</div>
                                                <div class="table-text-sub uppercase tracking-wider" x-show="jo.order_type === 'SERVICE'">Service purchase</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <div :class="{
                                        'badge-fulfilled': jo.readiness === 'READY' || jo.status === 'COMPLETED' || jo.status === 'TO_RECEIVE',
                                            'badge-confirmed': jo.status === 'APPROVED' || jo.status === 'IN_PRODUCTION' || jo.status === 'TO_PAY',
                                            'badge-partial': jo.readiness === 'LOW' || jo.status === 'PENDING',
                                            'badge-cancelled': jo.readiness === 'MISSING' || jo.status === 'CANCELLED'
                                        }" class="status-pill" x-text="jo.status === 'COMPLETED' ? 'Fulfilled' : 
                                           (jo.status === 'APPROVED' ? 'Approved' : 
                                           (jo.status === 'TO_PAY' ? 'To Pay' : 
                                           (jo.status === 'VERIFY_PAY' ? 'To Verify' : 
                                           (jo.status === 'IN_PRODUCTION' ? 'Processing' : 
                                           (jo.status === 'TO_RECEIVE' ? 'To Pickup' : jo.status)))))">
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="table-text-main" x-text="jo.first_name + ' ' + (jo.last_name || '')"></div>
                                        <div style="margin-top:4px;">
                                            <span style="font-size:10px; font-weight:500;" class="status-pill" :class="jo.customer_type === 'NEW' ? 'badge-confirmed' : 'badge-fulfilled'" x-text="jo.customer_type"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="table-text-main" x-text="jo.created_at ? new Date(jo.created_at).toLocaleDateString(undefined, {month:'long', day:'numeric', year:'numeric'}) : ''"></div>
                                        <div class="table-text-sub uppercase" x-text="jo.due_date ? 'Due ' + new Date(jo.due_date).toLocaleDateString() : ''"></div>
                                    </td>
                                    <td class="px-4 py-4 text-center space-x-1">
                                        <button @click.stop="viewDetails(jo.id, jo.order_type || 'JOB')" class="btn-action blue">View</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredOrders.length === 0">
                                <td colspan="6" class="px-6 py-24 text-center">
                                    <span class="table-text-sub uppercase tracking-widest">No matching jobs in this stage</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

    <!-- No more materials modal - integrated into details -->

<!-- Image Preview Lightbox -->
<div x-show="previewFile" x-cloak style="position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:10000; display:flex; align-items:center; justify-content:center; padding:40px;">
    <button @click="previewFile = null" style="position:fixed; top:20px; right:25px; background:rgba(255,255,255,0.1); border:none; color:white; font-size:40px; width:50px; height:50px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">&times;</button>
    <div style="max-width:100%; max-height:100%; position:relative;">
        <img :src="previewFile" style="max-width:100%; max-height:85vh; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.1);">
        <div style="margin-top:20px; text-align:center;">
            <a :href="previewFile" download style="background:white; color:#1f2937; padding:10px 24px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; display:inline-flex; align-items:center; gap:8px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download Artwork
            </a>
        </div>
    </div>
</div>

<!-- Customization Details Modal — matching customers_management.php style -->
<div x-show="showDetailsModal" x-cloak>
    <div class="modal-overlay" @click.self="showDetailsModal = false">
        <div class="modal-panel" @click.stop>

            <!-- Loading State -->
            <div x-show="loadingDetails" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#06A1A1;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading job details...</p>
            </div>

            <!-- Content -->
            <div x-show="!loadingDetails && currentJo.id">

                <!-- Modal Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;" x-text="'Customization #' + currentJo.id"></h3>
                        <p style="font-size:12px;color:#6b7280;margin:2px 0 0;" x-text="currentJo.service_type"></p>
                    </div>
                    <button @click="showDetailsModal = false" style="background:transparent;border:none;cursor:pointer;color:#6b7280;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div style="padding:24px;">

                    <!-- Customer Row -->
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;">
                        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#06A1A1,#047676);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:22px;flex-shrink:0;" x-text="currentJo.customer_full_name ? currentJo.customer_full_name[0].toUpperCase() : '?'"></div>
                        <div>
                            <div style="font-size:16px;font-weight:700;color:#1f2937;" x-text="currentJo.customer_full_name"></div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                                <span style="font-size:11px; font-weight:500;" class="status-pill" :class="currentJo.customer_type === 'NEW' ? 'badge-confirmed' : 'badge-fulfilled'" x-text="currentJo.customer_type"></span>
                                <span style="font-size:12px;color:#6b7280;" x-text="currentJo.customer_contact"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Two-column Info Grid -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Service</label>
                            <div style="font-size:13px;color:#1f2937;font-weight:500;" x-text="currentJo.service_type"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Status</label>
                            <span class="status-pill" :class="{
                                'badge-fulfilled': currentJo.status === 'COMPLETED' || currentJo.status === 'TO_RECEIVE',
                                'badge-confirmed': currentJo.status === 'APPROVED' || currentJo.status === 'IN_PRODUCTION' || currentJo.status === 'TO_PAY',
                                'badge-partial': currentJo.status === 'PENDING',
                                'badge-cancelled': currentJo.status === 'CANCELLED'
                            }" x-text="currentJo.status"></span>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Dimensions</label>
                            <div style="font-size:13px;color:#1f2937;" x-text="currentJo.width_ft + '\' × ' + currentJo.height_ft + '\''"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Quantity</label>
                            <div style="font-size:13px;color:#1f2937;" x-text="currentJo.quantity + ' pcs'"></div>
                        </div>
                        <div x-show="!['PENDING', 'Pending Review', 'Pending Approval', 'For Revision'].includes(currentJo.status)">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Estimated Total</label>
                            <div style="font-size:13px;color:#1f2937;font-weight:400;" x-text="'₱' + Number(currentJo.estimated_total || 0).toLocaleString()"></div>
                        </div>
                        <div x-show="!['PENDING', 'Pending Review', 'Pending Approval', 'For Revision'].includes(currentJo.status)">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Amount Paid</label>
                            <div style="font-size:13px;color:#1f2937;font-weight:400;" x-text="'₱' + Number(currentJo.amount_paid || 0).toLocaleString()"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Priority</label>
                            <div style="font-size:13px;font-weight:600;" :style="currentJo.priority === 'HIGH' ? 'color:#ef4444' : 'color:#1f2937'" x-text="currentJo.priority"></div>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Due Date</label>
                            <div style="font-size:13px;color:#1f2937;" :style="isOverdue(currentJo.due_date) ? 'color:#ef4444;' : ''" x-text="currentJo.due_date || 'Not set'"></div>
                        </div>
                    </div>

                    <!-- Dynamic Order Details (service-specific fields from customization_data) -->
                    <template x-if="currentJo.items && currentJo.items.length > 0">
                        <div style="margin-bottom:20px; padding:16px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:12px;">Order Details (Customer Specifications)</label>
                            <template x-for="(item, idx) in currentJo.items" :key="item.order_item_id || idx">
                                <div style="margin-bottom:16px; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;">
                                    <div style="font-size:13px; font-weight:700; color:#1f2937; margin-bottom:10px;" x-text="item.product_name + ' × ' + item.quantity"></div>
                                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:10px;">
                                        <template x-for="([k, v]) in getDisplayableCustom(item.customization)" :key="k">
                                            <div style="padding:8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; min-width:0; overflow-wrap:break-word;">
                                                <div style="font-size:10px; font-weight:600; color:#6b7280; text-transform:uppercase; margin-bottom:2px;" x-text="getCustomLabel(k)"></div>
                                                <div style="font-size:12px; font-weight:500; color:#1f2937; word-break:break-word; overflow-wrap:break-word;" x-text="typeof v === 'object' ? JSON.stringify(v) : v"></div>
                                            </div>
                                        </template>
                                    </div>
                                    <template x-if="item.design_url">
                                        <div style="margin-top:12px;">
                                            <div style="font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px;">Design Preview</div>
                                            <div style="display:flex; align-items:flex-end; gap:12px;">
                                                <img :src="item.design_url" 
                                                     @click="previewFile = item.design_url"
                                                     style="width:140px; height:auto; border-radius:10px; border:1px solid #e2e8f0; cursor:zoom-in; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);" 
                                                     onerror="this.src='/printflow/public/assets/img/file_icon.png';">
                                                <a :href="item.design_url" target="_blank" rel="noopener" style="font-size:11px; color:#4f46e5; text-decoration:none; font-weight:600; padding:6px 10px; background:#f5f3ff; border-radius:6px; transition:all 0.2s;" onmouseover="this.style.background='#ddd6fe'">
                                                    Open Original →
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>


                    <!-- Notes -->
                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:6px;">Production Notes</label>
                        <div style="font-size:13px;color:#6b7280;background:#fffbeb;border:1px solid #fef3c7;padding:10px 14px;border-radius:8px;font-style:italic;word-break:break-word;overflow-wrap:break-word;" x-text="currentJo.notes || (currentJo.items && currentJo.items[0] && (currentJo.items[0].customization?.notes || currentJo.items[0].customization?.additional_notes)) || 'No instructions provided.'"></div>
                    </div>

                    <!-- 4. TO_VERIFY (Payment Verification) -->
                    <template x-if="currentJo.status === 'VERIFY_PAY'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                            <label style="font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;display:block;margin-bottom:16px;">Step 4: Verify Payment Proof</label>
                            
                            <div style="display:flex; gap:20px; align-items:flex-start;">
                                <div style="width:160px; flex-shrink:0;">
                                    <template x-if="currentJo.payment_proof_path">
                                        <img :src="'/printflow/api_view_proof.php?file=' + currentJo.payment_proof_path" 
                                             @click="previewFile = '/printflow/api_view_proof.php?file=' + currentJo.payment_proof_path"
                                             style="width:100%; height:auto; border-radius:8px; border:1px solid #d1d5db; cursor:zoom-in; box-shadow:0 4px 6px rgba(0,0,0,0.1);" 
                                             alt="Proof">
                                    </template>
                                </div>
                                <div style="flex:1;">
                                    <div style="margin-bottom:16px;">
                                        <div style="font-size:11px; color:#6b7280; font-weight:600; text-transform:uppercase;">Amount Submitted</div>
                                        <div style="font-size:22px; font-weight:800; color:#1f2937;" x-text="'₱' + Number(currentJo.payment_submitted_amount || 0).toLocaleString()"></div>
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <button @click="verifyPayment()" class="btn-action emerald" style="flex:1;">✓ Approve Payment</button>
                                        <button @click="rejectPayment()" class="btn-action red" style="flex:1;">✕ Reject</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- 2. APPROVED (Set Price & Materials) -->
                    <template x-if="currentJo.status === 'APPROVED'">
                        <div style="margin-bottom:20px; display:flex; flex-direction:column; gap:20px;">
                            <!-- Pricing Section -->
                            <div style="padding:18px; border-radius:12px; border:1px solid #dcfce7; background:#f0fdf4;">
                                <label style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;display:block;margin-bottom:12px;">Step 2: Pricing & Submission</label>
                                <div style="display:flex; gap:12px; align-items:flex-end;">
                                    <div style="flex:1;">
                                        <label style="font-size:10px; font-weight:700; color:#166534; display:block; margin-bottom:4px;">Grand Total (₱)</label>
                                        <input type="number" x-model.number="jobPriceInput" style="padding:10px; border:1px solid #bbf7d0; border-radius:8px; width:100%; box-sizing:border-box; font-size:14px; font-weight:600;">
                                    </div>
                                    <button @click="submitToPay()" class="btn-action emerald" style="padding:11px 20px; height:42px;">💰 Request Payment</button>
                                </div>
                                <p style="font-size:11px; color:#15803d; margin-top:8px;">Setting the price and clicking 'Request Payment' will notify the customer to pay.</p>
                            </div>

                            <!-- Materials Assignment -->
                            <div style="padding:18px; border-radius:12px; border:1px solid #ddd6fe; background:#f5f3ff;">
                                <label style="font-size:11px;font-weight:700;color:#4f46e5;text-transform:uppercase;display:block;margin-bottom:12px;">Production Materials & Inventory</label>
                                
                                <div style="display:flex; flex-direction:column; gap:12px;">
                                    <select x-model="newMaterialId" @change="newMaterialId = $event.target.value; newMaterialRollId = ''; availableRollsList = []; if(isRollTracked(newMaterialId)) loadAvailableRolls(newMaterialId);" style="width:100%; padding:10px; border:1px solid #e0e7ff; border-radius:8px; font-size:13px; background:white;">
                                        <option value="">-- Select Material to Use --</option>
                                        <template x-for="item in availableMaterialsForCurrentOrder" :key="item.id">
                                            <option :value="item.id" x-text="`${item.name} (${item.current_stock} ${item.unit_of_measure} available)`"></option>
                                        </template>
                                    </select>

                                    <template x-if="newMaterialId">
                                        <div style="display:flex; flex-direction:column; gap:12px; padding:12px; background:white; border-radius:8px; border:1px solid #e0e7ff;">
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                                <div>
                                                    <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Quantity / Length</label>
                                                    <input type="number" x-model.number="newMaterialQty" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;">
                                                </div>
                                                <template x-if="isTarpaulin(newMaterialId)">
                                                    <div>
                                                        <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Height (ft)</label>
                                                        <input type="number" x-model.number="newMaterialHeight" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;">
                                                    </div>
                                                </template>
                                            </div>
                                            <button @click="addMaterialToQueue()" class="btn-action indigo" style="width:100%; padding:8px; font-size:12px;">+ Add Material</button>
                                        </div>
                                    </template>

                                    <!-- Pending Queue -->
                                    <template x-if="pendingMaterials.length > 0">
                                        <div style="margin-top:8px;">
                                            <template x-for="(pm, idx) in pendingMaterials" :key="idx">
                                                <div style="display:flex; align-items:center; justify-content:space-between; background:white; border:1px solid #e0e7ff; border-radius:8px; padding:8px 12px; margin-bottom:4px; font-size:12px;">
                                                    <span style="font-weight:600;" x-text="pm.name"></span>
                                                    <span style="color:#6b7280;" x-text="'× ' + pm.qty + ' ' + pm.uom"></span>
                                                    <button @click="pendingMaterials.splice(idx,1)" style="color:#ef4444; border:none; background:none; cursor:pointer;">✕</button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- 3. TO_PAY (Waiting for Payment) -->
                    <template x-if="currentJo.status === 'TO_PAY'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #dbeafe; background:#f0f9ff;">
                            <label style="font-size:11px;font-weight:700;color:#1e40af;text-transform:uppercase;display:block;margin-bottom:12px;">Step 3: Awaiting Payment</label>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                                <div style="font-size:14px; font-weight:500; color:#1e40af;">Total Outstanding:</div>
                                <div style="font-size:20px; font-weight:800; color:#1e40af;" x-text="'₱' + Number(currentJo.estimated_total || 0).toLocaleString()"></div>
                            </div>
                            <div style="font-size:13px; color:#1e40af; line-height:1.5;">Waiting for the customer to upload payment proof. Once uploaded, it will appear in the TO VERIFY section.</div>
                        </div>
                    </template>

                    <!-- 5. IN_PRODUCTION -->
                    <template x-if="currentJo.status === 'IN_PRODUCTION' || currentJo.status === 'Processing'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #fbd38d; background:#fffaf0;">
                            <label style="font-size:11px;font-weight:700;color:#9c4221;text-transform:uppercase;display:block;margin-bottom:12px;">Step 5: Production In Progress</label>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:14px; color:#9c4221; font-weight:500;">Currently in production phase.</div>
                                <button @click="jobAction('TO_RECEIVE')" class="btn-action amber">📦 Mark as Ready for Pickup</button>
                            </div>
                        </div>
                    </template>

                    <!-- 6. TO_RECEIVE -->
                    <template x-if="currentJo.status === 'TO_RECEIVE'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #c4b5fd; background:#f5f3ff;">
                            <label style="font-size:11px;font-weight:700;color:#5b21b6;text-transform:uppercase;display:block;margin-bottom:12px;">Step 6: Ready for Pickup</label>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="font-size:14px; color:#5b21b6; font-weight:500;">Customer has been notified to pick up the order.</div>
                                <button @click="completeOrder()" class="btn-action emerald">✓ Mark Final Completed</button>
                            </div>
                        </div>
                    </template>

                    <!-- 7. COMPLETED -->
                    <template x-if="currentJo.status === 'COMPLETED'">
                        <div style="margin-bottom:20px; padding:18px; border-radius:12px; border:1px solid #bbf7d0; background:#f0fdf4;">
                            <label style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;display:block;margin-bottom:4px;">Workflow Finished</label>
                            <div style="font-size:15px; font-weight:700; color:#15803d; display:flex; align-items:center; gap:8px;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Order Successfully Completed
                            </div>
                        </div>
                    </template>

                            <template x-if="currentJo.materials && currentJo.materials.length > 0">
<<<<<<< HEAD
                                <div>
                                    <template x-for="m in (currentJo.materials || [])" :key="m.id">
=======
                                <div style="margin-top:20px;">
                                    <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Assigned Production Materials</label>
                                    <template x-for="m in currentJo.materials" :key="m.id">
>>>>>>> d84ca5ae2d12f1a3809732a3caf25e36f0537ea6
                                        <div style="background:white; border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                                            <div>
                                                <div style="font-size:12px; font-weight:600; color:#1f2937;" x-text="m.item_name"></div>
                                                <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                                                    <span x-show="m.track_by_roll == 1">
                                                        Req: <span x-text="m.computed_required_length_ft"></span>'
                                                        <span x-show="m.roll_code"> (Roll: <span x-text="m.roll_code"></span>)</span>
                                                        <span x-show="!m.roll_code"> (Auto Pick Roll)</span>
                                                    </span>
                                                    <span x-show="m.track_by_roll == 0">Qty: <span x-text="m.quantity"></span></span>
                                                    
                                                    <!-- Show Lamination & Waste Metadata safely -->
                                                    <template x-if="m.metadata && m.metadata.lamination_item_id">
                                                        <div style="color:#059669; font-weight:600; margin-top:4px;">
                                                            + Lamination (Auto Pick Roll) — <span x-text="m.metadata.lamination_length_ft"></span>'
                                                        </div>
                                                    </template>
                                                    <template x-if="m.metadata && m.metadata.waste_length_ft !== undefined">
                                                        <div style="color:#b45309; margin-top:2px;">
                                                            Recorded Waste: <span x-text="m.metadata.waste_length_ft"></span>'
                                                        </div>
                                                    </template>

                                                    <span style="color:#06A1A1; font-weight:600; margin-left:8px;" x-show="m.deducted_at">✓ Deducted</span>
                                                </div>
                                            </div>
                                            <template x-if="!m.deducted_at">
                                                <button @click="removeMaterial(m.id)" style="background:none; border:none; color:#ef4444; font-size:11px; font-weight:600; cursor:pointer; padding:4px 8px; border-radius:4px; transition:all 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">Remove</button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>

                    <template x-if="currentJo.ink_usage && currentJo.ink_usage.length > 0">
                        <div style="margin-top:16px;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Ink Consumption Recorded</label>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                <template x-for="ink in (currentJo.ink_usage || [])" :key="ink.id">
                                    <div style="background:#fdf4ff; border:1px solid #fbcfe8; border-radius:6px; padding:6px 10px; font-size:11px; font-weight:600; color:#9d174d;">
                                        <span x-text="ink.item_name + ' → '"></span>
                                        <span x-text="ink.quantity_used + ' bottle'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Artwork Files -->
                    <div x-show="currentJo.files && currentJo.files.length > 0" style="margin-top:16px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Artwork Files</label>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <template x-for="file in (currentJo.files || [])" :key="file.id">
                                <a :href="'/printflow/' + file.file_path.replace(/^\/+/, '')" target="_blank" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#1f2937;transition:border-color 0.2s;" onmouseover="this.style.borderColor='#06A1A1'" onmouseout="this.style.borderColor='#e5e7eb'">
                                    <span style="font-size:12px;font-weight:500;" x-text="file.file_name"></span>
                                    <span style="font-size:11px;color:#06A1A1;font-weight:600;">View ↗</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>


                <!-- Modal Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <!-- Left: Status actions -->
                    <div style="display:flex;gap:8px; flex-wrap:wrap; align-items:center;">
                        <template x-if="['PENDING', 'Pending Review', 'Pending Approval'].includes(currentJo.status)">
                            <div style="display:flex; gap:8px;">
                                <button @click="jobAction('APPROVED')" class="btn-action indigo" style="padding:6px 12px; font-weight:600;">✓ Approve to Set Price</button>
                                <button @click="openRevisionModal()" class="btn-action" style="padding:6px 12px; color:#ef4444; background:#fef2f2; border:1px solid #fee2e2; font-weight:600;">✕ Request Revision</button>
                            </div>
                        </template>

                        <template x-if="currentJo.status !== 'CANCELLED' && currentJo.status !== 'COMPLETED'">
                            <button @click="jobAction('CANCELLED')" class="btn-action red" style="padding:6px 12px;">✕ Cancel</button>
                        </template>
                    </div>
                    <!-- Right: Close -->
                    <button @click="showDetailsModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- REVISION MODAL -->
    <div x-show="showRevisionModal" style="display:none; position:fixed; inset:0; z-index:100; align-items:center; justify-content:center; padding:16px;">
        <!-- Backdrop -->
        <div style="position:absolute; inset:0; background:rgba(17, 24, 39, 0.7); backdrop-filter:blur(4px);" @click="closeRevisionModal()"></div>
        <!-- Modal Panel -->
        <div style="position:relative; background:white; width:100%; max-width:400px; border-radius:16px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid #fee2e2; overflow:hidden;">
            <!-- Header -->
            <div style="padding:16px 20px; border-bottom:1px solid #fee2e2; background:#fef2f2; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:16px; font-weight:700; color:#b91c1c;">Request Revision</h3>
                <button @click="closeRevisionModal()" style="background:none; border:none; color:#f87171; cursor:pointer;" onmouseover="this.style.color='#b91c1c'" onmouseout="this.style.color='#f87171'">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <!-- Body -->
            <div style="padding:20px;">
                <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Reason for Revision</label>
                <select x-model="revisionReasonSelect" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:16px; outline:none;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'">
                    <option value="">-- Select a reason --</option>
                    <option value="Low image quality">Low image quality</option>
                    <option value="Wrong design uploaded">Wrong design uploaded</option>
                    <option value="Incorrect details provided">Incorrect details provided</option>
                    <option value="Not printable / invalid format">Not printable / invalid format</option>
                    <option value="Others">Others</option>
                </select>

                <div x-show="revisionReasonSelect === 'Others'" style="transition:all 0.2s;">
                    <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">Please specify</label>
                    <textarea x-model="revisionReasonText" rows="3" placeholder="Enter custom reason..." style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; outline:none;" onfocus="this.style.borderColor='#f87171'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                </div>
            </div>
            <!-- Footer -->
            <div style="padding:16px 20px; border-top:1px solid #f3f4f6; background:#f9fafb; display:flex; justify-content:flex-end; gap:8px;">
                <button @click="closeRevisionModal()" class="btn-secondary">Cancel</button>
                <button @click="submitRevision()" class="btn-action red">Submit Revision</button>
            </div>
        </div>
    </div>
</div>
        </div><!-- /#staffJoCustomizationsPage -->
    </div><!-- /.main-content -->
</div><!-- /.dashboard-container -->

<script>
    function joManager(defaultStatus = 'PENDING') {
        return {
<<<<<<< HEAD
            statuses: ['ALL', 'PENDING', 'APPROVED', 'TO_PAY', 'TO_VERIFY', 'IN_PRODUCTION', 'TO_RECEIVE', 'COMPLETED', 'CANCELLED'],
=======
>>>>>>> 1d610692b6051bc69bfc301d358a7ad23fdab53c
            activeStatus: defaultStatus || 'ALL',
            orders: [],
            machines: [],
            showDetailsModal: false,
            loadingDetails: false,
            showRevisionModal: false,
            revisionReasonSelect: '',
            revisionReasonText: '',
            previewFile: null,
            currentJo: {},
            availableRolls: {},
            allInventoryItems: [],
            newMaterialId: '',
            newMaterialQty: 1,
            newMaterialHeight: 0,
            newMaterialRollId: '',
            newMaterialNotes: '',
            newMaterialMetadata: {lamination: '', lamination_roll_id: ''},
            pendingMaterials: [],
            availableRollsList: [],
            laminationItemsList: [],
            availableLamRollsList: [],
            impactPreview: null,
            search: '',
            jobPriceInput: 0,
            
            // Ink Settings
            inkCategorySelected: '',
            inkBlue: '',
            inkRed: '',
            inkBlack: '',
            inkYellow: '',

            inkTypes: {
                'TARP': { 'BLUE': 24, 'RED': 25, 'BLACK': 26, 'YELLOW': 27 },
                'L120': { 'BLUE': 28, 'RED': 29, 'BLACK': 30, 'YELLOW': 31 },
                'L130': { 'BLUE': 32, 'RED': 33, 'BLACK': 34, 'YELLOW': 35 }
            },

            customFieldLabels: {
                size: 'Size', color: 'Color', shirt_color: 'Color', print_placement: 'Placement',
                design_type: 'Design Type', template: 'Template', width: 'Width (ft)', height: 'Height (ft)',
                finish: 'Finish', with_eyelets: 'Eyelets', shape: 'Shape', waterproof: 'Waterproof',
                lamination: 'Lamination', laminate_option: 'Lamination Option', layout: 'Layout',
                dimensions: 'Dimensions', needed_date: 'Needed Date', notes: 'Notes', additional_notes: 'Notes',
                tshirt_provider: 'T-Shirt Provider', shirt_source: 'Shirt Source', brand: 'Brand',
                material: 'Material', surface_application: 'Surface', surface_type: 'Surface Type',
                sintraboard_thickness: 'Thickness', is_standee: 'Standee', sticker_type: 'Sticker Type',
                cut_type: 'Cut Type', thickness: 'Thickness', installation_fee: 'Installation Fee'
            },
            customFieldSkip: ['design_upload','reference_upload','Branch_ID','service_type','product_type','unit','design_upload_path','notes','additional_notes'],
            getDisplayableCustom(custom) {
                if (!custom || typeof custom !== 'object') return [];
                const skip = this.customFieldSkip;
                return Object.entries(custom).filter(([k, v]) =>
                    v !== '' && v != null && !skip.includes(k) && !String(k).startsWith('install_')
                );
            },
            getCustomLabel(k) {
                return this.customFieldLabels[k] || k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            },

            get availableMaterialsForCurrentOrder() {
                if (!this.currentJo || !this.allInventoryItems) return [];
                
                const service = String(this.currentJo.service_type || this.currentJo.job_title || '').toUpperCase();
                
                return this.allInventoryItems.filter(item => {
                    // MUST HAVE STOCK > 0
                    const stock = parseFloat(item.current_stock || 0);
                    if (stock <= 0) return false;

                    const cat = String(item.category_name || '').toUpperCase();
                    const name = String(item.name || '').toUpperCase();

                    // FILTER BASED ON ORDER TYPE
                    if (service.includes('T-SHIRT') || service.includes('SHIRT')) {
                        return cat.includes('VINYL') || cat.includes('INK') || cat.includes('HEAT TRANSFER') || cat.includes('APPAREL') || name.includes('SHIRT');
                    } else if (service.includes('TARPAULIN')) {
                        return cat.includes('TARPAULIN') || cat.includes('INK') || cat.includes('EYELET') || name.includes('GLUE') || name.includes('ROPE');
                    } else if (service.includes('STICKER') || service.includes('DECAL')) {
                        return cat.includes('STICKER') || cat.includes('VINYL') || cat.includes('INK') || cat.includes('LAMINATE') || name.includes('LAMINATION');
                    } else if (service.includes('SINTRA') || service.includes('STANDEE')) {
                        return cat.includes('SINTRA') || cat.includes('FOAM') || cat.includes('STICKER') || cat.includes('INK') || cat.includes('STAND');
                    } else if (service.includes('REFLECT') || service.includes('SIGN')) {
                        return cat.includes('REFLECT') || cat.includes('STICKER') || cat.includes('ACRYLIC') || cat.includes('INK') || cat.includes('SINTRA');
                    } else if (service.includes('GLASS') || service.includes('WALL') || service.includes('FROSTED')) {
                        return cat.includes('STICKER') || cat.includes('FROSTED') || cat.includes('INK') || cat.includes('INSTALL');
                    }
                    
                    // Fallback to true if no service string matched cleanly, but still filtered strictly by > 0 stock
                    return true;
                });
            },

            async init() {
                await this.loadOrders();
                await this.loadMachines();
                await this.loadAllInventoryItems();
                
                // Auto-open modal if order_id is in URL
                const params = new URLSearchParams(window.location.search);
                const orderId = params.get('order_id');
                const initialStatus = params.get('status');

                if (initialStatus) {
                    // Map common statuses to tabs
                    const statusMap = {
                        'TO_VERIFY': 'TO_VERIFY',
                        'PENDING_VERIFICATION': 'TO_VERIFY',
                        'DOWNPAYMENT_SUBMITTED': 'TO_VERIFY',
                        'VERIFY_PAY': 'TO_VERIFY',
                        'TO_PAY': 'TO_PAY',
                        'PENDING': 'PENDING',
                        'PENDING_REVIEW': 'PENDING',
                        'APPROVED': 'APPROVED',
                        'PROCESSING': 'IN_PRODUCTION'
                    };
                    const mapped = statusMap[initialStatus.toUpperCase().replace(/\s+/g, '_')] || initialStatus;
                    if (this.statuses.includes(mapped)) {
                        this.activeStatus = mapped;
                    } else if (orderId) {
                        // If we have an order_id but the status doesn't match a tab, default to ALL to ensure it's found
                        this.activeStatus = 'ALL';
                    }
                }

                if (orderId) {
                    const jobType = params.get('job_type') || 'JOB';
                    await this.viewDetails(parseInt(orderId, 10), jobType);
                }
            },

            async loadOrders() {
                try {
                    const [joRes, ordersRes] = await Promise.all([
                        fetch('../admin/job_orders_api.php?action=list_orders&per_page=200').then(r => r.json()),
                        fetch('../admin/job_orders_api.php?action=list_pending_orders').then(r => r.json())
                    ]);

                    const jobOrders = joRes.success ? joRes.data : [];
                    if (!ordersRes.success) {
                        console.warn('list_pending_orders failed:', ordersRes.error || ordersRes);
                    }
                    const regularOrders = ordersRes.success ? ordersRes.data : [];
                    
                    // Merge then sort newest first
                    const combined = [...jobOrders, ...regularOrders];
                    const sorted = combined.sort((a, b) => {
                        const ta = new Date(a.created_at || a.order_date || 0).getTime();
                        const tb = new Date(b.created_at || b.order_date || 0).getTime();
                        return tb - ta;
                    });

                    // Drop duplicate #ORD- rows when a #JO- row exists for the same store order_id
                    const storeIdsWithJob = new Set(
                        jobOrders
                            .filter(j => j.order_id != null && j.order_id !== '')
                            .map(j => String(j.order_id))
                    );

                    this.orders = sorted.filter(row => {
                        if (row.order_type !== 'ORDER') return true;
                        const oid = String(row.order_id ?? row.id ?? '');
                        return !storeIdsWithJob.has(oid);
                    }).map(o => ({
                        ...o,
                        _ts: new Date(o.created_at || o.order_date || 0).getTime()
                    }));
                } catch(err) {
                    console.error('Error loading orders:', err);
                    this.orders = [];
                }
            },

            async loadMachines() {
                const res = await (await fetch('../admin/job_orders_api.php?action=list_machines')).json();
                this.machines = res.success ? res.data : [];
            },

            sameId(a, b) {
                if (a == null || b == null) return false;
                return String(a) === String(b);
            },

            /** job_orders.id for API calls (handles ORDER rows where id is store order_id) */
            effectiveJobId() {
                const j = this.currentJo;
                if (!j) return null;
                if (j.order_type === 'ORDER') {
                    const jid = j.job_order_id;
                    return jid != null && jid !== '' ? Number(jid) : null;
                }
                return j.id != null && j.id !== '' ? Number(j.id) : null;
            },

            /** Resolves job_orders.id from store order_id when job_order_id was missing (API limit / older rows). */
            async resolveEffectiveJobId() {
                let jid = this.effectiveJobId();
                if (jid != null && !Number.isNaN(jid) && jid > 0) return jid;
                const j = this.currentJo;
                if (!j || j.order_type !== 'ORDER') return null;
                const oid = j.order_id ?? j.id;
                if (oid == null || oid === '') return null;
                try {
                    const res = await (await fetch(`../admin/job_orders_api.php?action=resolve_job_for_order&order_id=${encodeURIComponent(oid)}`)).json();
                    if (res.success && res.job_id) {
                        this.currentJo.job_order_id = res.job_id;
                        await this.loadOrders();
                        return Number(res.job_id);
                    }
                } catch (e) {
                    console.error('resolve_job_for_order', e);
                }
                return null;
            },

            findOrder(id, orderType = 'JOB') {
                return this.orders.find(o => this.sameId(o.id, id) && (o.order_type || 'JOB') === (orderType || 'JOB'));
            },

            get filteredOrders() {
                const filtered = this.orders.filter(jo => {
                    let matchStatus = this.activeStatus === 'ALL' || jo.status === this.activeStatus;
                    if (this.activeStatus === 'TO_VERIFY') {
                        const s = String(jo.status || '').toUpperCase();
                        matchStatus = s === 'VERIFY_PAY' || s === 'TO_VERIFY' || s === 'PENDING_VERIFICATION' || s === 'DOWNPAYMENT_SUBMITTED' || jo.payment_proof_status === 'SUBMITTED';
                    } else if (this.activeStatus === 'TO_PAY') {
                        const s = String(jo.status || '').toUpperCase();
                        matchStatus = (s === 'TO_PAY' || s === 'APPROVED') && jo.payment_proof_status !== 'SUBMITTED';
                    }
                    
                    const searchLower = this.search.toLowerCase();
                    const matchSearch = !this.search || 
                        (jo.job_title && jo.job_title.toLowerCase().includes(searchLower)) ||
                        (jo.service_type && jo.service_type.toLowerCase().includes(searchLower)) ||
                        (((jo.first_name || '') + ' ' + (jo.last_name || '')).toLowerCase().includes(searchLower)) ||
                        (jo.id && jo.id.toString().includes(searchLower));
                    return matchStatus && matchSearch;
                });
                return filtered.sort((a, b) => (b._ts || 0) - (a._ts || 0));
            },

            getStatusCount(status) {
                if (status === 'ALL') return this.orders.length;
                if (status === 'TO_VERIFY') {
                    return this.orders.filter(o => {
                        const s = String(o.status || '').toUpperCase();
                        return s === 'VERIFY_PAY' || s === 'TO_VERIFY' || s === 'PENDING_VERIFICATION' || s === 'DOWNPAYMENT_SUBMITTED' || o.payment_proof_status === 'SUBMITTED';
                    }).length;
                }
                if (status === 'TO_PAY') {
                    return this.orders.filter(o => {
                        const s = String(o.status || '').toUpperCase();
                        return (s === 'TO_PAY' || s === 'APPROVED') && o.payment_proof_status !== 'SUBMITTED';
                    }).length;
                }
                return this.orders.filter(o => o.status === status).length;
            },

            async viewDetails(id, orderType = 'JOB') {
                let order = this.findOrder(id, orderType);
                if (orderType === 'SERVICE' || order?.order_type === 'SERVICE') {
                    window.location.href = 'service_order_details.php?id=' + encodeURIComponent(id);
                    return;
                }

                this.showDetailsModal = true;
                this.loadingDetails = true;
                this.currentJo = {};
                const base = document.body.getAttribute('data-base-url') || '/printflow';
                
                if (orderType === 'ORDER') {
                    // Always fetch full order details to get `items` array and dynamic fields
                    try {
                        const detailRes = await (await fetch(`${base}/admin/job_orders_api.php?action=get_regular_order&id=${id}`)).json();
                        if (detailRes.success) {
                            order = detailRes.data;
                        }
                    } catch (e) { console.error('Error fetching order detail:', e); }
                    
                    if (!order || !order.items) {
                        this.loadingDetails = false;
                        this.showDetailsModal = false;
                        alert('Order not found or not accessible.');
                        return;
                    }
                    this.currentJo = { ...order, order_type: 'ORDER' };
                    this.jobPriceInput = this.currentJo.estimated_total || 0;
                    if (!this.currentJo.job_order_id) {
                        await this.resolveEffectiveJobId();
                    }
                    this.loadingDetails = false;
                } else {
                    // JOB ORDER
                    const jid = id || (order ? order.id : null);
                    if (!jid) {
                        this.loadingDetails = false;
                        this.showDetailsModal = false;
                        return;
                    }

                    try {
                        const res = await (await fetch(`${base}/admin/job_orders_api.php?action=get_order&id=${jid}`)).json();
                        if (res.success) {
                            this.currentJo = { ...res.data, order_type: 'JOB' };
                            this.jobPriceInput = this.currentJo.estimated_total || 0;
                            this.resetMaterialForm();
                            this.resetInkForm();
                            for (const m of this.currentJo.materials || []) {
                                if (m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                            }
                        } else {
                            // Fallback: It might be a regular order ID passed with job_type=JOB
                            const fallbackRes = await (await fetch(`${base}/admin/job_orders_api.php?action=get_regular_order&id=${jid}`)).json();
                            if (fallbackRes.success) {
                                this.currentJo = { ...fallbackRes.data, order_type: 'ORDER' };
                                this.jobPriceInput = this.currentJo.estimated_total || 0;
                                if (!this.currentJo.job_order_id) {
                                    await this.resolveEffectiveJobId();
                                }
                            } else {
                                alert('Order details could not be loaded.');
                                this.showDetailsModal = false;
                            }
                        }
                    } catch (e) {
                        console.error('Error loading job details', e);
                        this.showDetailsModal = false;
                    }
                    this.loadingDetails = false;
                }
            },

            isOverdue(date) {
                if(!date) return false;
                return new Date(date) < new Date() && this.activeStatus !== 'COMPLETED' && this.activeStatus !== 'CANCELLED';
            },

            async loadAvailableRolls(itemId) {
                if(this.availableRolls[itemId]) {
                    this.availableRollsList = this.availableRolls[itemId];
                    return;
                }
                const res = await (await fetch(`../admin/inventory_rolls_api.php?action=list_rolls&item_id=${itemId}`)).json();
                if(res.success) {
                    this.availableRolls[itemId] = res.data;
                    this.availableRollsList = res.data;
                }
            },

            isRollTracked(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.track_by_roll == 1;
            },

            async assignRoll(jomId, rollId) {
                const fd = new FormData();
                fd.append('action', 'assign_roll');
                fd.append('jom_id', jomId);
                fd.append('roll_id', rollId);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    await this.refreshMaterials();
                } else {
                    alert(res.error);
                }
            },

            async jobAction(status, machineId = null) {
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    alert('Could not create or find a production job for this store order. Confirm the order has line items in Orders.');
                    return;
                }
                const ok = await this.updateStatus(jid, status, machineId);
                if (ok) this.showDetailsModal = false;
            },

            async updateStatus(id, status, machineId = null, reason = '') {
                if (id == null || id === '' || Number(id) <= 0) {
                    alert('Invalid job order id.');
                    return false;
                }
                const fd = new FormData();
                fd.append('action', 'update_status');
                fd.append('id', id);
                fd.append('status', status);
                if(machineId) fd.append('machine_id', machineId);
                if(reason) fd.append('reason', reason);
                
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    if (this.showDetailsModal && (this.sameId(this.effectiveJobId(), id) || this.sameId(this.currentJo.id, id))) {
                        await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                    }
                    return true;
                }
                alert(res.error);
                return false;
            },

            async verifyPayment() {
                if(!confirm(`Verify payment of ₱${this.currentJo.payment_submitted_amount}?`)) return;
                
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    alert('No linked production job for payment verification.');
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'verify_payment');
                fd.append('id', jid);
                
                const res = await (await fetch('../admin/api_verify_job_payment.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                    alert('Payment verified and balance updated.');
                } else {
                    alert(res.error);
                }
            },

            async rejectPayment() {
                const reason = prompt("Enter reason for rejection (e.g., Unclear image, Incorrect amount):");
                if(!reason) return;
                
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    alert('No linked production job.');
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'reject_payment');
                fd.append('id', jid);
                fd.append('reason', reason);
                
                const res = await (await fetch('../admin/api_verify_job_payment.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');
                    alert('Payment proof rejected.');
                } else {
                    alert(res.error);
                }
            },

            async setJobPrice(id) {
                if(this.jobPriceInput < 0) return;
                let jid = id != null ? id : await this.resolveEffectiveJobId();
                if (!jid) {
                    alert('No linked production job.');
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'set_price');
                fd.append('id', jid);
                fd.append('price', this.jobPriceInput);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(!res.success) {
                    alert(res.error);
                    throw new Error(res.error);
                }
            },

            addMaterialToQueue() {
                if (!this.newMaterialId) return;
                const item = this.allInventoryItems.find(i => i.id == this.newMaterialId);
                if (!item) return;
                let meta = {};
                if (this.isTarpaulin(this.newMaterialId)) {
                    meta.height_ft = this.newMaterialHeight;
                    meta.finishing = this.newMaterialMetadata.finishing || '';
                } else if (this.isSticker(this.newMaterialId)) {
                    meta.lamination = this.newMaterialMetadata.lamination || '';
                }
                this.pendingMaterials.push({
                    item_id: this.newMaterialId,
                    name: item.name,
                    qty: this.newMaterialQty,
                    uom: item.unit_of_measure || 'pcs',
                    roll_id: this.newMaterialRollId || '',
                    notes: this.newMaterialNotes,
                    metadata: meta
                });
                // Reset form
                this.newMaterialId = '';
                this.newMaterialQty = 1;
                this.newMaterialHeight = 0;
                this.newMaterialRollId = '';
                this.newMaterialNotes = '';
                this.newMaterialMetadata = {};
            },

            async submitToPay() {
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    alert('No linked production job for materials and pricing.');
                    return;
                }
                // Save all pending materials from the queue
                for (const pm of this.pendingMaterials) {
                    const fd = new FormData();
                    fd.append('action', 'add_material');
                    fd.append('order_id', jid);
                    fd.append('item_id', pm.item_id);
                    fd.append('quantity', pm.qty);
                    fd.append('uom', pm.uom);
                    fd.append('roll_id', pm.roll_id);
                    fd.append('notes', pm.notes);
                    fd.append('metadata', JSON.stringify(pm.metadata));
                    const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                    if (!res.success) { alert('Failed to save material: ' + res.error); return; }
                }
                this.pendingMaterials = [];

                // Also save the current form if something is still selected
                if (this.newMaterialId) {
                    await this.addMaterial();
                }

                // Handle Ink Usage Check and Submission
                if (this.inkCategorySelected) {
                    const mappedInks = this.inkTypes[this.inkCategorySelected];
                    const inkPayload = [];
                    
                    if (this.inkBlue > 0) inkPayload.push({ item_id: mappedInks['BLUE'], color: 'BLUE', quantity: this.inkBlue });
                    if (this.inkRed > 0) inkPayload.push({ item_id: mappedInks['RED'], color: 'RED', quantity: this.inkRed });
                    if (this.inkBlack > 0) inkPayload.push({ item_id: mappedInks['BLACK'], color: 'BLACK', quantity: this.inkBlack });
                    if (this.inkYellow > 0) inkPayload.push({ item_id: mappedInks['YELLOW'], color: 'YELLOW', quantity: this.inkYellow });

                    if (inkPayload.length > 0) {
                        const fdInk = new FormData();
                        fdInk.append('action', 'save_ink_usage');
                        fdInk.append('order_id', jid);
                        fdInk.append('ink_data', JSON.stringify(inkPayload));

                        const resInk = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fdInk })).json();
                        if (!resInk.success) {
                            alert('Failed to save ink usage: ' + resInk.error);
                            return;
                        }
                    }
                }

                // Re-fetch to get latest materials
                await this.viewDetails(this.currentJo.id, this.currentJo.order_type || 'JOB');

                if ((!this.currentJo.materials || this.currentJo.materials.length === 0) && (!this.currentJo.ink_usage || this.currentJo.ink_usage.length === 0)) {
                    alert('Please add at least one production material or ink before submitting to pay.');
                    return;
                }

                await this.setJobPrice(jid);
                await this.updateStatus(jid, 'TO_PAY');
            },

            async loadAllInventoryItems() {
                const res = await (await fetch('../admin/inventory_items_api.php?action=get_items')).json();
                if(res.success) {
                    this.allInventoryItems = res.data;
                    this.laminationItemsList = this.allInventoryItems.filter(i => i.name.toUpperCase().includes('LAMINATE'));
                }
            },

            async loadAvailableLamRolls(itemId) {
                if(!itemId) return;
                const res = await (await fetch(`../admin/inventory_rolls_api.php?action=list&item_id=${itemId}&status=OPEN`)).json();
                if(res.success) {
                    this.availableLamRollsList = res.data;
                }
            },

            async approveOrder() {
                if (!confirm('Approve this order and request payment?')) return;
                const id = this.currentJo.id;
                const jid = this.effectiveJobId();
                const oid = this.currentJo.order_id || this.currentJo.id;
                
                // Set price first
                if (parseFloat(this.jobPriceInput) > 0) {
                    await this.updatePrice();
                }

                if (this.currentJo.order_type === 'ORDER') {
                    const fd = new FormData();
                    fd.append('order_id', oid);
                    fd.append('status', 'To Pay');
                    fd.append('update_status', '1');
                    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
                    
                    const res = await (await fetch('orders.php', { 
                        method: 'POST', 
                        body: fd, 
                        headers: {'X-Requested-With': 'XMLHttpRequest'} 
                    })).json();

                    if (res.success) {
                        alert('Order approved and moved to To Pay!');
                        await this.loadOrders();
                        this.showDetailsModal = false;
                    } else {
                        alert('Error: ' + (res.error || 'Failed to update order status'));
                    }
                } else {
                    // Custom Job Order
                    await this.updateStatus(id, 'TO_PAY');
                }
            },

            async updatePrice() {
                const jid = this.effectiveJobId();
                const oid = this.currentJo.order_id || this.currentJo.id;
                const price = parseFloat(this.jobPriceInput);
                
                if (this.currentJo.order_type === 'ORDER') {
                   const fd = new FormData();
                   fd.append('action', 'update_order_price');
                   fd.append('order_id', oid);
                   fd.append('price', price);
                   const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                   if (!res.success) alert('Failed to update price: ' + res.error);
                   else {
                       this.currentJo.total_amount = price;
                       this.currentJo.estimated_total = price;
                       console.log('Price updated successfully');
                   }
                } else {
                    await this.setJobPrice(jid);
                }
            },

            async setJobPrice(jid) {
                if (!jid) return;
                const price = parseFloat(this.jobPriceInput);
                const fd = new FormData();
                fd.append('action', 'set_price');
                fd.append('id', jid);
                fd.append('price', price);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if (res.success) {
                    this.currentJo.estimated_total = price;
                } else {
                    alert('Error setting price: ' + res.error);
                }
            },

            openRevisionModal() {
                this.revisionReasonSelect = '';
                this.revisionReasonText = '';
                this.showRevisionModal = true;
            },

            closeRevisionModal() {
                this.showRevisionModal = false;
            },

            async submitRevision() {
                const oid = this.effectiveJobId();
                if (!oid) return;
                
                let finalReason = this.revisionReasonSelect;
                if (finalReason === 'Others' || !finalReason) {
                    finalReason = this.revisionReasonText.trim();
                }
                
                if (!finalReason) {
                    alert('Please select or specify a reason for the revision request.');
                    return;
                }

                if (!confirm(`Submit revision request?\nReason: ${finalReason}`)) return;

                const ok = await this.updateStatus(oid, 'For Revision', null, finalReason);
                if (ok) {
                    this.showRevisionModal = false;
                    this.showDetailsModal = false; // Optionally close details, or let it refresh automatically
                }
            },

            async addMaterial() {
                if(!this.newMaterialId || !this.newMaterialQty) return;
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    alert('No linked production job.');
                    return;
                }
                const item = this.allInventoryItems.find(i => i.id == this.newMaterialId);
                const fd = new FormData();
                fd.append('action', 'add_material');
                fd.append('order_id', jid);
                fd.append('item_id', this.newMaterialId);
                fd.append('quantity', this.newMaterialQty);
                fd.append('uom', item.unit_of_measure || 'pcs');
                fd.append('roll_id', this.newMaterialRollId);
                fd.append('notes', this.newMaterialNotes);
                
                // Construct metadata based on category
                let meta = {};
                if (this.isTarpaulin(this.newMaterialId)) {
                    meta.height_ft = this.newMaterialHeight;
                    meta.finishing = this.newMaterialMetadata.finishing || '';
                } else if (this.isSticker(this.newMaterialId)) {
                    // STICKER LOGIC
                    let orderedHeight = this.currentJo.height_ft > 0 ? this.currentJo.height_ft : 1;
                    meta.waste_length_ft = Math.max(0, this.newMaterialQty - orderedHeight);
                    if (this.newMaterialMetadata.lamination) {
                        meta.lamination_item_id = this.newMaterialMetadata.lamination;
                        meta.lamination_roll_id = this.newMaterialMetadata.lamination_roll_id || null;
                        meta.lamination_length_ft = this.newMaterialQty; // Lamination length matches consumed vinyl length
                    }
                }
                fd.append('metadata', JSON.stringify(meta));

                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    this.resetMaterialForm();
                    await this.refreshMaterials();
                } else {
                    alert(res.error);
                }
            },

            resetMaterialForm() {
                this.newMaterialId = '';
                this.newMaterialQty = 1;
                this.newMaterialHeight = 0;
                this.newMaterialRollId = '';
                this.newMaterialNotes = '';
                this.availableLamRollsList = [];
                this.newMaterialMetadata = {
                    lamination: '',
                    lamination_roll_id: ''
                };
            },

            resetInkForm() {
                this.inkCategorySelected = '';
                this.inkBlue = '';
                this.inkRed = '';
                this.inkBlack = '';
                this.inkYellow = '';
            },

            isTarpaulin(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 2; // confirmed from schema check
            },

            isSticker(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 3;
            },

            isPlate(itemId) {
                const item = this.allInventoryItems.find(i => i.id == itemId);
                return item && item.category_id == 1;
            },

            async removeMaterial(jomId) {
                if(!confirm('Remove this material?')) return;
                const fd = new FormData();
                fd.append('action', 'remove_material');
                fd.append('id', jomId);
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.refreshMaterials();
                } else {
                    alert(res.error);
                }
            },

            async refreshMaterials() {
                const jid = await this.resolveEffectiveJobId();
                if (!jid) return;
                const res = await (await fetch(`../admin/job_orders_api.php?action=get_order&id=${jid}`)).json();
                if(res.success) {
                    this.currentJo = { ...res.data, order_type: 'JOB' };
                    for(const m of (this.currentJo.materials || [])) {
                        if(m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                    }
                }
            },

            async completeOrder(machineId = null) {
                if(!confirm('This will permanently deduct materials from inventory. Confirm?')) return;
                const jid = await this.resolveEffectiveJobId();
                if (!jid) {
                    alert('No linked production job for this entry.');
                    return;
                }
                const ok = await this.updateStatus(jid, 'COMPLETED', machineId);
                if (ok) this.showDetailsModal = false;
            }
        }
    }
    /*
     * Do NOT call Alpine.initTree here when document.readyState !== 'loading' (Turbo body swap).
     * Inline scripts run before turbo:load's setTimeout; initTree(root) + initTree(.main-content) double-mounts x-for (tripled tabs, zero counts).
     * Full load: Alpine.start() (defer) inits the page. Turbo: public/assets/js/turbo-init.js initTree(.main-content) runs after swap.
     */
</script>
</body>
</html>
