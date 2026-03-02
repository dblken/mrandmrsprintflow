<?php
/**
 * Staff: Job Orders Management
 * Production tracking & material assignment.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
$page_title = 'Production Workflow - PrintFlow';

// Get statistics for KPIs
$total_jobs = db_query("SELECT COUNT(*) as count FROM job_orders")[0]['count'];
$pending_jobs = db_query("SELECT COUNT(*) as count FROM job_orders WHERE status = 'PENDING'")[0]['count'];
$approval_jobs = db_query("SELECT COUNT(*) as count FROM job_orders WHERE status = 'APPROVED'")[0]['count'];
$in_production = db_query("SELECT COUNT(*) as count FROM job_orders WHERE status = 'IN_PRODUCTION'")[0]['count'];
$completed_jobs = db_query("SELECT COUNT(*) as count FROM job_orders WHERE status = 'COMPLETED'")[0]['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
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
        .btn-action.blue { color: #3b82f6; border-color: #3b82f6; }
        .btn-action.blue:hover { background: #3b82f6; color: white; }
        .btn-action.red { color: #ef4444; border-color: #ef4444; }
        .btn-action.red:hover { background: #ef4444; color: white; }
        .btn-action.amber { color: #f59e0b; border-color: #f59e0b; }
        .btn-action.amber:hover { background: #f59e0b; color: white; }
        .btn-action.emerald { color: #059669; border-color: #059669; }
        .btn-action.emerald:hover { background: #059669; color: white; }

        /* Refined Enterprise Table Styles (Uniform with Orders Page) */
        .pill-tab { 
            padding: 8px 16px; 
            font-weight: 600; 
            font-size: 13px; 
            color: #6b7280; 
            border-radius: 9999px; 
            transition: all 0.2s; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            background: transparent;
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
        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="joManager('ALL')">
<div class="dashboard-container">
    <?php 
    if ($_SESSION['user_type'] === 'Staff') {
        include __DIR__ . '/../includes/staff_sidebar.php';
    } else {
        include __DIR__ . '/../includes/admin_sidebar.php';
    }
    ?>
    <div class="main-content">
        <header>
            <h1 class="page-title"><?php echo $_SESSION['user_type'] === 'Staff' ? 'Active Production' : 'Production Jobs'; ?></h1>
        </header>

        <main>
            <!-- KPI Summary Row -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Jobs</div>
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
                <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; margin-bottom:24px; flex-wrap: wrap;">
                    <div style="display:flex; gap:8px;">
                        <template x-for="st in statuses">
                            <button 
                                @click="activeStatus = st" 
                                :class="activeStatus === st ? 'active' : ''"
                                class="pill-tab"
                            >
                                <span x-text="st === 'VERIFY_PAY' ? 'TO VERIFY' : st"></span>
                                <span class="tab-count" x-text="getStatusCount(st)"></span>
                                <span x-show="st === 'VERIFY_PAY' && getStatusCount(st) > 0" class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse border-2 border-white"></span>
                            </button>
                        </template>
                    </div>

                    <div style="display:flex; align-items:center; gap:16px;">
                        <div style="position:relative;">
                            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;pointer-events:none;" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="search" placeholder="Filter jobs..." style="padding-left:32px; width:220px; height:36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; font-weight:400; outline:none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto -mx-6 px-6">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="pl-6 pr-4 py-4 w-[12%] border-b border-gray-100">Order #</th>
                                <th class="px-4 py-4 w-[30%] border-b border-gray-100">Job Information</th>
                                <th class="px-4 py-4 w-[18%] border-b border-gray-100 text-center">Status</th>
                                <th class="px-4 py-4 w-[20%] border-b border-gray-100">Customer</th>
                                <th class="px-4 py-4 w-[15%] border-b border-gray-100 text-right">Created</th>
                                <th class="px-4 py-4 w-[10%] border-b border-gray-100 text-center uppercase tracking-widest text-[10px]">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="jo in filteredOrders" :key="jo.id">
                                <tr @click="viewDetails(jo.id)" class="group transition-all hover:bg-gray-50/50 relative cursor-pointer">
                                    <td class="pl-6 pr-4 py-4 relative">
                                        <div class="row-indicator"></div>
                                        <span class="table-text-main" x-text="'#JO-' + jo.id.toString().padStart(5, '0')"></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex flex-col gap-0 min-w-0">
                                                <div class="table-text-main truncate" x-text="jo.job_title || jo.service_type"></div>
                                                <div class="table-text-sub uppercase tracking-wider"><span x-text="jo.width_ft"></span>'×<span x-text="jo.height_ft"></span>' • <span x-text="jo.quantity"></span> pcs</div>
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
                                           (jo.status === 'IN_PRODUCTION' ? 'Processing' : 
                                           (jo.status === 'TO_RECEIVE' ? 'To Receive' : jo.status))))">
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
                                        <button @click.stop="viewDetails(jo.id)" class="btn-action blue">View</button>
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
    </div>
</div>

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

<!-- Job Details Modal — matching customers_management.php style -->
<div x-show="showDetailsModal" x-cloak>
    <div class="modal-overlay" @click.self="showDetailsModal = false">
        <div class="modal-panel" @click.stop>

            <!-- Loading State -->
            <div x-show="loadingDetails" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading job details...</p>
            </div>

            <!-- Content -->
            <div x-show="!loadingDetails && currentJo.id">

                <!-- Modal Header -->
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;" x-text="'Job #JO-' + currentJo.id"></h3>
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
                        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:22px;flex-shrink:0;" x-text="currentJo.customer_full_name ? currentJo.customer_full_name[0].toUpperCase() : '?'"></div>
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
                        <div>
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Estimated Total</label>
                            <div style="font-size:13px;color:#1f2937;font-weight:400;" x-text="'₱' + Number(currentJo.estimated_total || 0).toLocaleString()"></div>
                        </div>
                        <div>
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

                    <!-- Notes -->
                    <div style="margin-bottom:20px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:6px;">Production Notes</label>
                        <div style="font-size:13px;color:#6b7280;background:#fffbeb;border:1px solid #fef3c7;padding:10px 14px;border-radius:8px;font-style:italic;" x-text="currentJo.notes || 'No instructions provided.'"></div>
                    </div>

                    <!-- Payment Proof Section -->
                    <template x-if="currentJo.payment_proof_status && currentJo.payment_proof_status !== 'NONE'">
                        <div style="margin-bottom:20px; padding:16px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:12px;">Payment Proof</label>
                            
                            <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start;">
                                <div style="width:120px; flex-shrink:0;">
                                    <template x-if="currentJo.payment_proof_path">
                                        <img :src="'/printflow/api_view_proof.php?file=' + currentJo.payment_proof_path" 
                                             @click="previewFile = '/printflow/api_view_proof.php?file=' + currentJo.payment_proof_path"
                                             style="width:100%; height:auto; border-radius:8px; border:1px solid #d1d5db; cursor:zoom-in; box-shadow:0 1px 3px rgba(0,0,0,0.1);" 
                                             alt="Proof">
                                    </template>
                                </div>
                                <div style="flex:1; min-width:200px;">
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                                        <div>
                                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:600;">Status</div>
                                            <span class="status-pill" :class="{
                                                'bg-blue-100 text-blue-800': currentJo.payment_proof_status === 'SUBMITTED',
                                                'bg-green-100 text-green-800': currentJo.payment_proof_status === 'VERIFIED',
                                                'bg-red-100 text-red-800': currentJo.payment_proof_status === 'REJECTED'
                                            }" x-text="currentJo.payment_proof_status"></span>
                                        </div>
                                        <div>
                                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:600;">Amount Submitted</div>
                                            <div style="font-size:14px; font-weight:700; color:#1f2937;" x-text="'₱' + Number(currentJo.payment_submitted_amount || 0).toLocaleString()"></div>
                                        </div>
                                        <div>
                                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:600;">Method</div>
                                            <div style="font-size:13px; font-weight:500; color:#1f2937;" x-text="currentJo.payment_method || '-'"></div>
                                        </div>
                                        <div style="grid-column: span 2;">
                                            <div style="font-size:10px; color:#6b7280; text-transform:uppercase; font-weight:600;">Reference No.</div>
                                            <div style="font-size:13px; font-weight:500; color:#1f2937; word-break:break-all;" x-text="currentJo.payment_reference || '-'"></div>
                                        </div>
                                    </div>
                                    
                                    <template x-if="currentJo.payment_proof_status === 'SUBMITTED'">
                                        <div style="display:flex; gap:8px; margin-top:12px;">
                                            <button @click="verifyPayment()" class="btn-action emerald" style="flex:1;">✓ Accept & Verify</button>
                                            <button @click="rejectPayment()" class="btn-action red" style="flex:1;">✕ Reject</button>
                                        </div>
                                    </template>
                                    
                                    <template x-if="currentJo.payment_proof_status === 'REJECTED'">
                                        <div style="margin-top:12px; font-size:12px; color:#b91c1c; background:#fee2e2; padding:8px 12px; rounded-radius:6px;">
                                            <span style="font-weight:700;">Reason:</span> <span x-text="currentJo.payment_rejection_reason"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Materials -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin:0;">Production Materials</label>
                            <template x-if="currentJo.status === 'APPROVED'">
                                <span class="text-[10px] font-bold text-indigo-500 uppercase"></span>
                            </template>
                        </div>
                        
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <template x-if="currentJo.status === 'APPROVED'">
                                <div style="background:#f5f3ff; border:1px solid #ddd6fe; padding:16px; border-radius:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <div style="font-weight:700; font-size:12px; color:#4f46e5; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                        Assign Production Materials
                                    </div>

                                    <!-- Pending Materials Queue -->
                                    <template x-if="pendingMaterials.length > 0">
                                        <div style="margin-bottom:12px;">
                                            <div style="font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:6px;">To Be Added:</div>
                                            <template x-for="(pm, idx) in pendingMaterials" :key="idx">
                                                <div style="display:flex; align-items:center; justify-content:space-between; background:white; border:1px solid #e0e7ff; border-radius:8px; padding:8px 12px; margin-bottom:4px; font-size:12px;">
                                                    <span style="font-weight:600; color:#1f2937;" x-text="pm.name"></span>
                                                    <span style="color:#6b7280;" x-text="'× ' + pm.qty + ' ' + pm.uom"></span>
                                                    <button @click="pendingMaterials.splice(idx,1)" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:16px;">×</button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <!-- Item Selection -->
                                        <div>
                                            <select x-model="newMaterialId" @change="newMaterialId = $event.target.value; newMaterialRollId = ''; availableRollsList = []; if(isRollTracked(newMaterialId)) loadAvailableRolls(newMaterialId);" style="width:100%; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:white;">
                                                <option value="">-- Select Material / Item --</option>
                                                <template x-for="item in allInventoryItems" :key="item.id">
                                                    <option :value="item.id" x-text="item.name"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <template x-if="newMaterialId">
                                            <div style="display:flex; flex-direction:column; gap:12px; animation: slideDown 0.2s ease-out;">
                                                <!-- Dynamic Fields: Tarpaulin -->
                                                <template x-if="isTarpaulin(newMaterialId)">
                                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                                        <div>
                                                            <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Height (ft)</label>
                                                            <input type="number" x-model.number="newMaterialHeight" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;" placeholder="Height">
                                                        </div>
                                                        <div>
                                                            <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Quantity (pcs)</label>
                                                            <input type="number" x-model.number="newMaterialQty" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;" placeholder="Qty">
                                                        </div>
                                                        <div style="grid-column: span 2;">
                                                            <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Finishing (Optional)</label>
                                                            <input type="text" x-model="newMaterialMetadata.finishing" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;" placeholder="e.g. Eyelets, Rope, Hemming">
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Dynamic Fields: Printed Sticker -->
                                                <template x-if="isSticker(newMaterialId)">
                                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                                        <div>
                                                            <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Consumed Length (ft)</label>
                                                            <input type="number" x-model.number="newMaterialQty" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;" placeholder="Length">
                                                        </div>
                                                        <div>
                                                            <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Lamination</label>
                                                            <select x-model="newMaterialMetadata.lamination" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:white;">
                                                                <option value="">None</option>
                                                                <option value="GLOSS">Gloss</option>
                                                                <option value="MATTE">Matte</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Dynamic Fields: Plate / Generic -->
                                                <template x-if="!isTarpaulin(newMaterialId) && !isSticker(newMaterialId)">
                                                    <div style="display:flex; gap:10px; align-items:flex-end;">
                                                        <div style="flex:1;">
                                                            <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Quantity</label>
                                                            <input type="number" x-model.number="newMaterialQty" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px;" placeholder="Qty">
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Roll Selector (for roll-tracked items) -->
                                                <template x-if="isRollTracked(newMaterialId)">
                                                    <div>
                                                        <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Select Roll <span style="font-weight:400;color:#9ca3af;">(optional — auto-picks oldest if skipped)</span></label>
                                                        <select x-model="newMaterialRollId" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:white;">
                                                            <option value="">— Auto-pick oldest open roll —</option>
                                                            <template x-for="roll in availableRollsList" :key="roll.id">
                                                                <option :value="roll.id" x-text="(roll.roll_code || '#'+roll.id) + ' — ' + roll.remaining_length_ft + ' ft left'"></option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                </template>

                                                <!-- Notes -->
                                                <div>
                                                    <label style="font-size:10px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Production Notes</label>
                                                    <textarea x-model="newMaterialNotes" style="width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:12px; min-height:40px;" placeholder="Notes for this material..."></textarea>
                                                </div>

                                                <!-- Add to Queue button -->
                                                <button @click="addMaterialToQueue()" :disabled="!newMaterialId" style="width:100%; padding:9px; background:#4f46e5; color:white; border:none; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">+ Add to Material List</button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                        </div>
                    </div>

                    <!-- Set Price Form -->
                    <template x-if="currentJo.status === 'APPROVED'">
                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:16px; border-radius:12px; margin-top:20px;">
                            <div style="font-weight:700; font-size:12px; color:#166534; margin-bottom:12px;">Finalize Job Price</div>
                            <div style="display:flex; gap:10px; align-items:flex-end;">
                                <div style="flex:1;">
                                    <label style="font-size:10px; font-weight:700; color:#166534; display:block; margin-bottom:4px;">Total Price (₱)</label>
                                    <!-- Saved when clicking Submit to Pay -->
                                    <input type="number" x-model.number="jobPriceInput" style="width:100%; padding:8px; border:1px solid #bbf7d0; border-radius:8px; font-size:13px;" placeholder="0.00">
                            </div>
                        </div>
                    </template>

                    <!-- Artwork Files -->
                    <div x-show="currentJo.files && currentJo.files.length > 0" style="margin-top:16px;">
                        <label style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:8px;">Artwork Files</label>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <template x-for="file in currentJo.files" :key="file.id">
                                <a :href="'/printflow/' + file.file_path.replace(/^\/+/, '')" target="_blank" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#1f2937;transition:border-color 0.2s;" onmouseover="this.style.borderColor='#3b82f6'" onmouseout="this.style.borderColor='#e5e7eb'">
                                    <span style="font-size:12px;font-weight:500;" x-text="file.file_name"></span>
                                    <span style="font-size:11px;color:#3b82f6;font-weight:600;">View ↗</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>


                <!-- Modal Footer -->
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <!-- Left: Status actions -->
                    <div style="display:flex;gap:8px;">
                        <template x-if="currentJo.status === 'PENDING'">
                            <button @click="updateStatus(currentJo.id, 'APPROVED'); showDetailsModal = false;" class="btn-action blue">Approve</button>
                        </template>
                        <template x-if="currentJo.status === 'APPROVED'">
                            <button @click="submitToPay()" class="btn-action amber">Submit to Pay</button>
                        </template>
                        <template x-if="currentJo.status === 'TO_PAY'">
                            <button @click="updateStatus(currentJo.id, 'IN_PRODUCTION'); showDetailsModal = false;" class="btn-action blue">Start Production</button>
                        </template>
                        <template x-if="currentJo.status === 'IN_PRODUCTION'">
                            <button @click="updateStatus(currentJo.id, 'TO_RECEIVE'); showDetailsModal = false;" class="btn-action amber">To Receive</button>
                        </template>
                        <template x-if="currentJo.status === 'TO_RECEIVE'">
                            <button @click="completeOrder(currentJo.id); showDetailsModal = false;" class="btn-action emerald">Mark Complete</button>
                        </template>

                        <template x-if="currentJo.status !== 'CANCELLED' && currentJo.status !== 'COMPLETED'">
                            <button @click="updateStatus(currentJo.id, 'CANCELLED'); showDetailsModal = false;" class="btn-action red">Cancel</button>
                        </template>
                    </div>
                    <!-- Right: Close -->
                    <button @click="showDetailsModal = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function joManager(defaultStatus = 'PENDING') {
        return {
            statuses: ['ALL', 'PENDING', 'APPROVED', 'VERIFY_PAY', 'TO_PAY', 'IN_PRODUCTION', 'TO_RECEIVE', 'COMPLETED', 'CANCELLED'],
            activeStatus: defaultStatus || 'ALL',
            orders: [],
            machines: [],
            showDetailsModal: false,
            loadingDetails: false,
            previewFile: null,
            currentJo: {},
            availableRolls: {},
            allInventoryItems: [],
            newMaterialId: '',
            newMaterialQty: 1,
            newMaterialHeight: 0,
            newMaterialRollId: '',
            newMaterialNotes: '',
            newMaterialMetadata: {},
            pendingMaterials: [],
            availableRollsList: [],
            impactPreview: null,
            search: '',
            jobPriceInput: 0,

            async init() {
                await this.loadOrders();
                await this.loadMachines();
                await this.loadAllInventoryItems();
            },

            async loadOrders() {
                const res = await (await fetch('../admin/job_orders_api.php?action=list_orders')).json();
                if(res.success) {
                    this.orders = res.data;
                }
            },

            async loadMachines() {
                const res = await (await fetch('../admin/job_orders_api.php?action=list_machines')).json();
                this.machines = res.success ? res.data : [];
            },

            get filteredOrders() {
                return this.orders.filter(jo => {
                    let matchStatus = this.activeStatus === 'ALL' || jo.status === this.activeStatus;
                    if (this.activeStatus === 'VERIFY_PAY') {
                        matchStatus = jo.payment_proof_status === 'SUBMITTED';
                    } else if (this.activeStatus === 'TO_PAY') {
                        // Exclude verifying ones from TO_PAY view if they are being verified
                        matchStatus = jo.status === 'TO_PAY' && jo.payment_proof_status !== 'SUBMITTED';
                    }
                    
                    const searchLower = this.search.toLowerCase();
                    const matchSearch = !this.search || 
                        (jo.job_title && jo.job_title.toLowerCase().includes(searchLower)) ||
                        (jo.service_type && jo.service_type.toLowerCase().includes(searchLower)) ||
                        ((jo.first_name + ' ' + (jo.last_name || '')).toLowerCase().includes(searchLower)) ||
                        (jo.id.toString().includes(searchLower));
                    return matchStatus && matchSearch;
                });
            },

            getStatusCount(status) {
                if (status === 'ALL') return this.orders.length;
                if (status === 'VERIFY_PAY') return this.orders.filter(o => o.payment_proof_status === 'SUBMITTED').length;
                if (status === 'TO_PAY') return this.orders.filter(o => o.status === 'TO_PAY' && o.payment_proof_status !== 'SUBMITTED').length;
                return this.orders.filter(o => o.status === status).length;
            },

            async viewDetails(id) {
                this.showDetailsModal = true;
                this.loadingDetails = true;
                this.currentJo = {};
                const res = await (await fetch(`../admin/job_orders_api.php?action=get_order&id=${id}`)).json();
                this.loadingDetails = false;
                if(res.success) {
                    this.currentJo = res.data;
                    this.jobPriceInput = this.currentJo.estimated_total || 0;
                    this.resetMaterialForm();
                    // Auto-load available rolls for relevant materials
                    for(const m of this.currentJo.materials) {
                        if(m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                    }
                } else {
                    this.showDetailsModal = false;
                    alert(res.error || 'Could not load job details.');
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

            async updateStatus(id, status, machineId = null) {
                const fd = new FormData();
                fd.append('action', 'update_status');
                fd.append('id', id);
                fd.append('status', status);
                if(machineId) fd.append('machine_id', machineId);
                
                const res = await (await fetch('../admin/job_orders_api.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    // If we were viewing details, refresh them
                    if (this.currentJo.id === id) {
                        await this.viewDetails(id);
                    }
                } else {
                    alert(res.error);
                }
            },

            async verifyPayment() {
                if(!confirm(`Verify payment of ₱${this.currentJo.payment_submitted_amount}?`)) return;
                
                const fd = new FormData();
                fd.append('action', 'verify_payment');
                fd.append('id', this.currentJo.id);
                
                const res = await (await fetch('../admin/api_verify_job_payment.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    await this.viewDetails(this.currentJo.id);
                    alert('Payment verified and balance updated.');
                } else {
                    alert(res.error);
                }
            },

            async rejectPayment() {
                const reason = prompt("Enter reason for rejection (e.g., Unclear image, Incorrect amount):");
                if(!reason) return;
                
                const fd = new FormData();
                fd.append('action', 'reject_payment');
                fd.append('id', this.currentJo.id);
                fd.append('reason', reason);
                
                const res = await (await fetch('../admin/api_verify_job_payment.php', { method: 'POST', body: fd })).json();
                if(res.success) {
                    await this.loadOrders();
                    await this.viewDetails(this.currentJo.id);
                    alert('Payment proof rejected.');
                } else {
                    alert(res.error);
                }
            },

            async setJobPrice(id) {
                if(this.jobPriceInput < 0) return;
                const fd = new FormData();
                fd.append('action', 'set_price');
                fd.append('id', id);
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
                // Save all pending materials from the queue
                for (const pm of this.pendingMaterials) {
                    const fd = new FormData();
                    fd.append('action', 'add_material');
                    fd.append('order_id', this.currentJo.id);
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

                // Re-fetch to get latest materials
                await this.viewDetails(this.currentJo.id);

                if (!this.currentJo.materials || this.currentJo.materials.length === 0) {
                    alert('Please add at least one production material before submitting to pay.');
                    return;
                }

                await this.setJobPrice(this.currentJo.id);
                await this.updateStatus(this.currentJo.id, 'TO_PAY');
            },

            async loadAllInventoryItems() {
                const res = await (await fetch('../admin/inventory_items_api.php?action=get_items')).json();
                if(res.success) this.allInventoryItems = res.data;
            },

            async addMaterial() {
                if(!this.newMaterialId || !this.newMaterialQty) return;
                const item = this.allInventoryItems.find(i => i.id == this.newMaterialId);
                const fd = new FormData();
                fd.append('action', 'add_material');
                fd.append('order_id', this.currentJo.id);
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
                    meta.lamination = this.newMaterialMetadata.lamination || '';
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
                this.newMaterialQty = this.currentJo.quantity || 1;
                this.newMaterialHeight = this.currentJo.height_ft || 0;
                this.newMaterialRollId = '';
                this.newMaterialNotes = '';
                this.newMaterialMetadata = {};
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
                const res = await (await fetch(`../admin/job_orders_api.php?action=get_order&id=${this.currentJo.id}`)).json();
                if(res.success) {
                    this.currentJo = res.data;
                    for(const m of this.currentJo.materials) {
                        if(m.track_by_roll == 1) this.loadAvailableRolls(m.item_id);
                    }
                }
            },

            async completeOrder(id, machineId = null) {
                if(!confirm('This will permanently deduct materials from inventory. Confirm?')) return;
                this.updateStatus(id, 'COMPLETED', machineId);
            }
        }
    }
</script>
</body>
</html>
