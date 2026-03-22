<?php
/**
 * Staff Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['status'];
        $staff_id = get_user_id();

        // Fetch current status and customer ID
        $order_info = db_query("SELECT customer_id, status FROM orders WHERE order_id = ?", 'i', [$order_id]);
        
        if (!empty($order_info)) {
            $current_status = $order_info[0]['status'];
            $customer_id = $order_info[0]['customer_id'];

            // Only proceed if the status is actually changing
            if ($current_status !== $new_status) {
                // Use the centralized update_order_status logic
                $success = update_order_status($order_id, $new_status, $staff_id);

                if ($success) {
                    // Log activity
                    log_activity($staff_id, 'Order Status Update', "Updated Order #{$order_id} to {$new_status}");

                    // Notify customer
                    if ($new_status === 'To Pay') {
                        $msg = "💳 Your order #{$order_id} has been approved! Please prepare your payment upon pickup.";
                    } else {
                        $msg = "Your order #{$order_id} status has been updated to: {$new_status}";
                    }
                    
                    // Pass order_id as data_id for shortcut linking
                    create_notification($customer_id, 'Customer', $msg, 'Order', false, false, $order_id);
                    add_order_system_message($order_id, $msg);
                }
            } else {
                // Status is already the same, consider it a "soft" success
                $success = true;
            }
        } else {
            $success = false;
        }

        if ($success) {
            // If AJAX, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'new_status' => $new_status]);
                exit;
            }

            redirect('/printflow/staff/orders.php?success=1');
        } else {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database update failed']);
                exit;
            }
            redirect('/printflow/staff/orders.php?error=1');
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';

// Build query
$sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
        (SELECT GROUP_CONCAT(COALESCE(p.name, 'Custom Product') SEPARATOR ', ') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as item_names,
        (SELECT oi.customization_data FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization
        FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id WHERE 1=1";
$params = [];
$types = '';

if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Pagination settings
$items_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o WHERE 1=1";
$count_params = [];
$count_types = '';

if (!empty($status_filter)) {
    $count_sql .= " AND o.status = ?";
    $count_params[] = $status_filter;
    $count_types .= 's';
}

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

$sql .= " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$orders = db_query($sql, $types, $params);

$page_title = 'Orders - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <link rel="stylesheet" href="/printflow/public/assets/css/chat.css">
    <style>
        /* ── Order Detail Modal ─────────────────────────────────── */
        #orderModal {
            position: fixed; inset: 0; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            opacity: 0; pointer-events: none;
            transition: opacity 0.25s ease;
        }
        #orderModal.open { opacity: 1; pointer-events: all; }

        .om-backdrop {
            position: absolute; inset: 0;
            background: rgba(15,23,42,0.55);
            backdrop-filter: blur(4px);
            transition: opacity 0.25s ease;
        }

        .om-panel {
            position: relative; z-index: 1;
            background: #fff;
            border-radius: 20px;
            width: 100%; max-width: 1400px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            transform: translateY(24px) scale(0.97);
            transition: transform 0.3s cubic-bezier(.34,1.56,.64,1), opacity 0.25s ease;
            opacity: 0;
        }
        #orderModal.open .om-panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .om-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 24px 28px 20px;
            border-bottom: 1px solid #f1f5f9;
            position: sticky; top: 0; background: #fff; border-radius: 20px 20px 0 0; z-index: 2;
        }
        .om-title { font-size: 1.35rem; font-weight: 800; color: #0f172a; }
        .om-subtitle { font-size: 0.78rem; color: #94a3b8; margin-top: 2px; }
        .om-close {
            width: 36px; height: 36px; border-radius: 50%;
            border: none; background: #f1f5f9; color: #64748b;
            cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;
            transition: background 0.15s, color 0.15s;
        }
        .om-close:hover { background: #e2e8f0; color: #0f172a; }

        .om-body { padding: 24px 28px 28px; }
        .om-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 700px) { .om-grid { grid-template-columns: 1fr; } }

        .om-card {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 14px; padding: 20px;
        }
        .om-card-title {
            font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.07em; color: #94a3b8; margin-bottom: 14px;
        }
        .om-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px;
        }
        .om-row:last-child { border-bottom: none; }
        .om-label { color: #6b7280; }
        .om-value { font-weight: 600; color: #1e293b; text-align: right; }

        .om-notes {
            margin-top: 14px; padding: 14px 16px;
            background: linear-gradient(135deg,#fffbeb,#fef3c7);
            border: 1px solid #fde68a; border-radius: 12px;
            max-height: 180px; overflow-y: auto;
        }
        .om-notes-title { font-size: 12px; font-weight: 800; color: #92400e; margin-bottom: 6px; }
        .om-notes-text { font-size: 13px; color: #b45309; line-height: 1.6; overflow-wrap: anywhere; word-break: break-word; }

        .om-cust-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .om-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg,#667eea,#764ba2);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 16px; flex-shrink: 0;
        }



        /* Items table */
        .om-items-section { margin-top: 20px; }
        .om-items-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; margin-bottom: 12px; }
        .om-items-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .om-items-table th {
            text-align: left; padding: 8px 10px;
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: #94a3b8;
            border-bottom: 2px solid #e2e8f0;
        }
        .om-items-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .om-items-table tr:last-child td { border-bottom: none; }
        .om-items-total td { border-top: 2px solid #e2e8f0 !important; font-weight: 700; }

        /* Design image */
        .om-design-wrap { margin-top: 10px; }
        .om-design-img {
            max-width: 140px; border-radius: 8px; border: 2px solid #e2e8f0;
            cursor: zoom-in; transition: transform 0.2s, box-shadow 0.2s;
        }
        .om-design-img:hover { transform: scale(1.04); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }

        /* Customs chips */
        .om-custom-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .om-chip {
            background: #e0e7ff; color: #4338ca;
            border-radius: 99px; padding: 2px 10px;
            font-size: 11px; font-weight: 600;
        }

        /* Loader */
        .om-loader { text-align: center; padding: 64px 0; }
        .om-spinner {
            width: 40px; height: 40px; border-radius: 50%;
            border: 3px solid #e2e8f0; border-top-color: #6366f1;
            animation: om-spin 0.7s linear infinite; margin: 0 auto 12px;
        }
        @keyframes om-spin { to { transform: rotate(360deg); } }

        /* Alert flash inside modal */
        .om-alert { border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; }
        .om-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .om-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* Customer orders list */
        .om-cust-orders { margin-top: 14px; }
        .om-co-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #f1f5f9; font-size: 12.5px; }
        .om-co-row:last-child { border-bottom: none; }

        /* Status badge replicated in JS */
        .badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .badge-purple { background: #ede9fe; color: #5b21b6; }

        /* Table hover + clickable rows */
        .staff-orders-table tbody tr { transition: background 0.1s; }
        .staff-orders-table tbody tr:hover td { background: #f9fafb; }

        /* ── Centered Status Overlay ───────────────────────── */
        .om-status-overlay {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            z-index: 100; pointer-events: none; opacity: 0;
            transition: opacity 0.3s ease;
        }
        .om-status-overlay.active { opacity: 1; pointer-events: all; }
        
        .om-status-toast {
            background: rgba(15, 23, 42, 0.9);
            color: #fff; padding: 16px 24px; border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            display: flex; flex-direction: column; align-items: center; gap: 12px;
            transform: scale(0.9); transition: transform 0.3s cubic-bezier(.34,1.56,.64,1);
            max-width: 280px; text-align: center;
        }
        .om-status-overlay.active .om-status-toast { transform: scale(1); }
        .om-status-toast-icon { font-size: 2rem; }
        .om-status-toast-msg { font-size: 14px; font-weight: 600; line-height: 1.4; }
    </style>
</head>
<body>


<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Orders Management</h1>
        </header>

        <main>
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4" style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px;">
                    Order status updated successfully!
                </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card">
                <form method="GET" id="statusFilterForm" style="display:flex; gap:16px; align-items:flex-end;">
                    <div style="flex:1;">
                        <label>Filter by Status</label>
                        <select name="status" id="statusFilterSelect" class="input-field">
                            <option value="">All Statuses</option>
<<<<<<< HEAD
                            <option value="Pending Review" <?php echo $status_filter === 'Pending Review' ? 'selected' : ''; ?>>Pending Review</option>
                            <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="To Pay" <?php echo $status_filter === 'To Pay' ? 'selected' : ''; ?>>To Pay</option>
                            <option value="Downpayment Submitted" <?php echo $status_filter === 'Downpayment Submitted' ? 'selected' : ''; ?>>Downpayment Submitted</option>
                            <option value="Pending Verification" <?php echo $status_filter === 'Pending Verification' ? 'selected' : ''; ?>>Pending Verification</option>
                            <option value="For Revision" <?php echo $status_filter === 'For Revision' ? 'selected' : ''; ?>>For Revision</option>
=======
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
>>>>>>> 04d53d75d5323397db2238c2717dfa1e7e2e79fe
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Ready for Pickup" <?php echo $status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="staff-orders-table">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700;">Order #</th>
                                <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700;">Customer</th>
                                <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700;">Date</th>
                                <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700;">Total</th>
                                <th style="padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700;">Status</th>
                                <th style="padding: 12px; text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 700;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="staff-order-row" onclick="openOrderModal(<?php echo $order['order_id']; ?>)" style="border-bottom: 1px solid #f1f5f9; cursor: pointer;">
                                    <td style="padding: 16px 12px; vertical-align: middle;">
                                        <div style="font-weight: 700; color: #1e293b; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                            #<?php echo $order['order_id']; ?>
                                            <?php 
                                            $unread = get_unread_chat_count($order['order_id'], 'User');
                                            if ($unread > 0): 
                                            ?>
                                                <span style="background: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; animation: pulse 2s infinite;" title="<?php echo $unread; ?> new messages from customer">
                                                    <?php echo $unread; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($order['item_names'])): ?>
                                            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px; font-weight: 500;">
                                                <?php 
                                                    $display_items = $order['item_names'];
                                                    if ($display_items === 'Custom Product' || $display_items === 'Custom Order') {
                                                        $display_items = get_service_name_from_customization($order['first_item_customization'] ?? '{}', $display_items);
                                                        
                                                        $c_json = json_decode($order['first_item_customization'] ?? '{}', true);
                                                        if (!empty($c_json['product_type']) && $c_json['product_type'] !== $display_items) {
                                                            $display_items .= " (" . $c_json['product_type'] . ")";
                                                        }
                                                    }
                                                    echo htmlspecialchars(strlen($display_items) > 100 ? substr($display_items, 0, 100) . '...' : $display_items); 
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 16px 12px; vertical-align: middle; color: #334155; font-weight: 500;"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td style="padding: 16px 12px; vertical-align: middle; color: #64748b; font-size: 13px;"><?php echo format_date($order['order_date']); ?></td>
                                    <td style="padding: 16px 12px; vertical-align: middle; font-weight: 700; color: #1e293b;"><?php echo format_currency($order['total_amount']); ?></td>
                                    <td style="padding: 16px 12px; vertical-align: middle;"><?php echo status_badge($order['status'], 'order'); ?></td>
                                    <td style="padding: 16px 12px; vertical-align: middle; text-align: right;">
                                        <div style="display: flex; justify-content: flex-end; gap: 4px;">
                                            <button
                                                onclick="openStaffOrderManage(<?php echo $order['order_id']; ?>)"
                                                style="background: #ecfdf5; border: none; color: #059669; font-size: 12px; font-weight: 700; cursor: pointer; padding: 6px 12px; border-radius: 8px; transition: all 0.2s;"
                                                onmouseover="this.style.background='#d1fae5'; this.style.transform='translateY(-1px)'"
                                                onmouseout="this.style.background='#ecfdf5'; this.style.transform='translateY(0)'"
                                            >
                                                Manage
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>/staff/chats.php?order_id=<?php echo $order['order_id']; ?>"
                                                style="display:inline-block; background: #f5f3ff; border: none; color: #7c3aed; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 8px; transition: all 0.2s; text-decoration:none;"
                                                onmouseover="this.style.background='#ede9fe'; this.style.transform='translateY(-1px)'"
                                                onmouseout="this.style.background='#f5f3ff'; this.style.transform='translateY(0)'"
                                            >
                                                Message
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php echo get_pagination_links($current_page, $total_pages, ['status' => $status_filter]); ?>
        </main>
    </div>
</div>

<!-- ══════════════════════════════════════════
     ORDER DETAIL MODAL
═══════════════════════════════════════════ -->
<div id="orderModal" role="dialog" aria-modal="true" aria-labelledby="omTitle">
    <div class="om-backdrop" onclick="closeOrderModal()"></div>
    <div class="om-panel">
        <div class="om-header">
            <div>
                <div class="om-title" id="omTitle">Order Details</div>
                <div class="om-subtitle" id="omSubtitle">Loading…</div>
            </div>
            <button class="om-close" onclick="closeOrderModal()" aria-label="Close">✕</button>
        </div>
        <div class="om-body" id="omBody">
            <!-- Loader -->
            <div class="om-loader">
                <div class="om-spinner"></div>
                <div style="color:#94a3b8; font-size:14px;">Fetching order details…</div>
            </div>
        </div>

        <!-- Status Overlay (Centered Toast) -->
        <div id="omStatusOverlay" class="om-status-overlay">
            <div class="om-status-toast">
                <div id="omStatusIcon" class="om-status-toast-icon">✅</div>
                <div id="omStatusMsg" class="om-status-toast-msg">Status Updated!</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     REVISION MODAL
═══════════════════════════════════════════ -->
<style>
    #revisionModal {
        position: fixed; inset: 0; z-index: 10001;
        display: flex; align-items: center; justify-content: center;
        padding: 16px; opacity: 0; pointer-events: none;
        transition: opacity 0.2s ease;
    }
    #revisionModal.open { opacity: 1; pointer-events: all; }
    .rev-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); }
    .rev-panel {
        position: relative; z-index: 1; background: #fff; border-radius: 20px;
        width: 100%; max-width: 450px; padding: 28px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        transform: scale(0.95); transition: transform 0.2s;
    }
    #revisionModal.open .rev-panel { transform: scale(1); }
    .rev-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
    .rev-sub { font-size: 0.9rem; color: #64748b; margin-bottom: 20px; }
</style>

<div id="revisionModal" role="dialog" aria-modal="true">
    <div class="rev-backdrop" onclick="closeRevisionModal()"></div>
    <div class="rev-panel">
        <div class="rev-title">Request Design Revision</div>
        <p class="rev-sub">Please select a reason for the revision request. This will be sent to the customer.</p>
        
        <form id="revForm" action="request_revision_process.php" method="POST">
            <input type="hidden" name="order_id" id="revOrderId">
            <input type="hidden" name="csrf_token" id="revCsrfToken">
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:8px;">Reason for Revision</label>
                <select name="revision_reason_select" class="input-field" required onchange="handleReasonChange(this)">
                    <option value="" disabled selected>Select a reason...</option>
                    <option value="Low image quality / Blurry file">Low image quality / Blurry file</option>
                    <option value="Incorrect dimensions / Size issue">Incorrect dimensions / Size issue</option>
                    <option value="Wrong file format">Wrong file format</option>
                    <option value="Design not print-ready">Design not print-ready</option>
                    <option value="Incomplete details">Incomplete details</option>
                    <option value="Copyright or restricted content">Copyright or restricted content</option>
                    <option value="Other">Other (Please specify)</option>
                </select>
            </div>

            <div id="revOtherWrapper" style="display:none; margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:8px;">Specify Other Reason</label>
                <textarea name="revision_reason_other" id="revOtherInput" class="input-field" style="height:80px;"></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <button type="button" class="btn-secondary" onclick="closeRevisionModal()">Cancel</button>
                <button type="submit" class="btn-primary">Send Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStaffOrderManage(orderId) {
    window.location.href = `/printflow/staff/customizations.php?order_id=${orderId}`;
}
</script>

<script>
let currentOrderId = null;

// ── Status badge helper ──────────────────────────────────
function statusBadge(val) {
    const map = {
        'Completed':        'badge-green',
        'Pending':          'badge-yellow',
<<<<<<< HEAD
        'Pending Review':   'badge-yellow',
        'Approved':         'badge-green',
        'To Pay':           'badge-blue',
        'Downpayment Submitted': 'badge-yellow',
        'Pending Verification': 'badge-yellow',
=======
        'Pending Approval': 'badge-yellow',
>>>>>>> 04d53d75d5323397db2238c2717dfa1e7e2e79fe
        'Processing':       'badge-blue',
        'In Production':    'badge-blue',
        'Printing':         'badge-blue',
        'For Revision':     'badge-red',
        'Ready for Pickup': 'badge-purple',
        'Cancelled':        'badge-red',
        'Paid':             'badge-green',
        'Unpaid':           'badge-gray',
        'Partial':          'badge-yellow',
    };
    const cls = map[val] || 'badge-gray';
    return `<span class="badge ${cls}">${val}</span>`;
}

// ── Open / close ─────────────────────────────────────────
window.openOrderModal = function openOrderModal(orderId) {
    currentOrderId = orderId;
    const modal = document.getElementById('orderModal');
    document.getElementById('omTitle').textContent = `Order #${orderId}`;
    document.getElementById('omSubtitle').textContent = 'Loading…';
    document.getElementById('omBody').innerHTML = `
        <div class="om-loader">
            <div class="om-spinner"></div>
            <div style="color:#94a3b8;font-size:14px;">Fetching order details…</div>
        </div>`;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(`/printflow/staff/get_order_data.php?id=${orderId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => {
        const contentType = r.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            // Non-JSON response — show raw text for debugging
            return r.text().then(txt => {
                console.error('Non-JSON response from API:', txt);
                document.getElementById('omBody').innerHTML =
                    `<div class="om-alert om-alert-error">Server returned unexpected response (HTTP ${r.status}). Check console for details.</div>`;
                return null;
            });
        }
        return r.json();
    })
    .then(data => {
        if (!data) return; // handled above
        if (data.error) {
            document.getElementById('omBody').innerHTML =
                `<div class="om-alert om-alert-error">Error: ${data.error}</div>`;
            return;
        }
        console.log("Order Data:", data);
        try {
            renderOrderModal(data);
        } catch (err) {
            console.error("Render Error:", err);
            document.getElementById('omBody').innerHTML =
                `<div class="om-alert om-alert-error">Rendering Error: ${err.message}</div>`;
        }
    })
    .catch(err => {
        console.error("Fetch Error:", err);
        document.getElementById('omBody').innerHTML =
                `<div class="om-alert om-alert-error">Network Error: ${err.message}</div>`;
    });
};

function closeOrderModal() {
    const modal = document.getElementById('orderModal');
    modal.classList.remove('open');
    document.body.style.overflow = '';
    currentOrderId = null;
}

// Close on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeOrderModal(); });

// ── Render ───────────────────────────────────────────────
function renderOrderModal(d) {
    document.getElementById('omSubtitle').textContent = d.order_date;

    let cancelBlock = '';
    if (d.status === 'Cancelled' && (d.cancelled_by || d.cancel_reason)) {
        cancelBlock = `
            <div style="margin-top:12px;padding:12px;background:#fef2f2;border:1px solid #fee2e2;border-radius:10px;">
                <div style="font-weight:700;color:#ef4444;font-size:12px;margin-bottom:4px;">Cancellation Details</div>
                <div style="font-size:12px;color:#b91c1c;">
                    <b>By:</b> ${esc(d.cancelled_by)}<br>
                    <b>Reason:</b> ${esc(d.cancel_reason)}<br>
                    ${d.cancelled_at ? `<b>At:</b> ${esc(d.cancelled_at)}` : ''}
                </div>
            </div>`;
    }

    let revisionBlock = '';
    if (d.status === 'For Revision' && d.revision_reason) {
        revisionBlock = `
            <div style="margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #dbeafe;border-radius:10px;">
                <div style="font-weight:700;color:#2563eb;font-size:12px;margin-bottom:4px;">Revision Requested</div>
                <div style="font-size:12px;color:#1e40af;">
                    <b>Reason:</b> ${esc(d.revision_reason)}<br>
                    <b>Count:</b> ${d.revision_count}
                </div>
            </div>`;
    }

    let notesBlock = '';
    if (d.notes) {
        notesBlock = `
            <div class="om-notes">
                <div class="om-notes-title">📝 Customer Notes</div>
                <div class="om-notes-text">${esc(d.notes).replace(/\n/g,'<br>')}</div>
            </div>`;
    }

    let downBlock = '';
    if (d.downpayment_amount > 0) {
        downBlock = `
            <div class="om-row">
                <span class="om-label" style="color:#b45309;font-weight:600;">Mandatory Downpayment</span>
                <span class="om-value" style="color:#b45309;">PHP ${d.downpayment_amount.toFixed(2)}</span>
            </div>`;
    }

    let payRefBlock = '';
    if (d.payment_reference) {
        payRefBlock = `
            <div class="om-row">
                <span class="om-label">Payment Reference</span>
                <span class="om-value">${esc(d.payment_reference)}</span>
            </div>`;
    }

    let paymentProofBlock = '';
    if (d.payment_proof) {
        paymentProofBlock = `
            <div style="margin-top:16px; padding:16px; background:#f0fdf4; border:1px solid #dcfce7; border-radius:12px;">
                <div style="font-weight:700; color:#15803d; font-size:12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                    <span>📄 Payment Proof Content</span>
                    <span style="font-size:10px; color:#16a34a;">Submitted: ${d.payment_submitted_at}</span>
                </div>
                <a href="${d.payment_proof}" target="_blank" style="display:block; border-radius:8px; overflow:hidden; border:2px solid white; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                    <img src="${d.payment_proof}" alt="Payment Proof" style="width:100%; height:auto; display:block;">
                </a>
                ${d.status === 'Downpayment Submitted' ? `
                <div style="margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <button class="btn-primary" onclick="verifyPayment(${d.order_id}, 'Approve')" style="background:#22c55e; font-size:12px; padding:8px;">Approve Payment</button>
                    <button class="btn-secondary" onclick="verifyPayment(${d.order_id}, 'Reject')" style="color:#ef4444; border-color:#fee2e2; font-size:12px; padding:8px;">Reject Payment</button>
                </div>
                ` : ''}
            </div>`;
    }

    // Status options




    // Items
    let itemsHTML = '';
    d.items.forEach(item => {
        // Customization UI
        let customizationHTML = '';
        if (item.customization && Object.keys(item.customization).length) {
            let gridItems = '';
            let largeBlocks = '';
            
            Object.entries(item.customization).forEach(([k, v]) => {
                if (!v || v === 'No' || v === 'None' || v === 'none') return;
                
                // Specific exclusions for Reflectorized Temporary Plates
                const isReflectorized = (item.category || '').toLowerCase().includes('reflectorized') || 
                                       (item.customization.service_type || '').toLowerCase().includes('reflectorized');
                const isTempPlate = (item.customization.product_type || '').includes('Temporary Plate');
                const isGatePass = (item.customization.product_type || '').includes('Gate Pass');
                const isStreetSignage = (item.customization.product_type || '').includes('Street');
                const exclusions = ['unit', 'bg_color', 'text_color', 'arrow_direction', 'quantity', 'material_type', 'shape', 'with_border', 'rounded_corners', 'with_numbering', 'install_service', 'need_proof', 'reflective_color', 'inches', 'product_type', 'dimensions', 'service_type'];
                const gpOnlyExclusions = ['bg_color', 'text_color', 'reflective_color', 'text_content', 'arrow_direction', 'with_numbering', 'install_service', 'need_proof', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];
                const ssOnlyExclusions = ['bg_color', 'text_color', 'reflective_color', 'with_numbering', 'starting_number', 'mounting_option', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];
                
                if (isReflectorized && isTempPlate && (exclusions.includes(k) || v === 'inches')) return;
                if (isReflectorized && isGatePass && (gpOnlyExclusions.includes(k) || k === 'quantity_gatepass')) return;
                if (isReflectorized && isStreetSignage && ssOnlyExclusions.includes(k)) return;

                const label = k.replace(/_/g, ' ');
                const isLarge = k.toLowerCase().includes('description') || k.toLowerCase() === 'notes';
                
                // If it's the same as global order notes, skip rendering it for the item
                if (k.toLowerCase() === 'notes' && v === d.notes) return;
                
                if (isLarge) {
                    largeBlocks += `
                        <div style="grid-column: 1 / -1; margin-top: 8px; padding: 12px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px;">
                            <div style="font-size: 12px; font-weight: 800; color: #92400e; text-transform: uppercase; margin-bottom: 6px;">📝 ${esc(label)}</div>
                            <div style="font-size: 14px; color: #b45309; line-height: 1.5; font-weight: 500; max-height: 150px; overflow-y: auto; overflow-wrap: anywhere; word-break: break-word; padding-right: 4px;">
                                ${esc(String(v)).replace(/\n/g,'<br>')}
                            </div>
                        </div>`;
                } else {
                    gridItems += `
                        <div style="padding: 6px 0;">
                            <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">${esc(label)}</div>
                            <div style="font-size: 15px; font-weight: 700; color: #1e293b;">${esc(String(v))}</div>
                        </div>`;
                }
            });

            customizationHTML = `
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px; letter-spacing: 0.05em;">Customization Details</div>
                    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px;">
                        ${gridItems}
                    </div>
                    ${largeBlocks}
                </div>`;
        }

        // Design & Reference Previews
        let designPreHTML = '';
        if (item.has_design || item.has_reference) {
            let previews = '';
            
            if (item.has_design) {
                previews += `
                    <div style="width:100%; margin-bottom:12px; text-align: right;">
                        <div style="font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.025em;">Customer Design</div>
                        <a href="${item.design_url}" target="_blank" style="display: block; border-radius: 12px; overflow: hidden; border: 2px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
                            <img src="${item.design_url}" alt="Design" style="width: 100%; max-height: 400px; object-fit: cover; display: block;" onerror="this.outerHTML='<div style=\'padding:20px;font-size:12px;color:#94a3b8;text-align:center;\'>N/A</div>'">
                        </a>
                        <a href="${item.design_url}" target="_blank" style="display: inline-block; font-size: 12px; color: #6366f1; margin-top: 8px; font-weight: 700; text-decoration: none; background: #eef2ff; padding: 4px 10px; border-radius: 6px;">↗ View Full</a>
                    </div>`;
            }
            
            if (item.has_reference) {
                previews += `
                    <div style="width:100%; margin-bottom:12px; text-align: right;">
                        <div style="font-size: 13px; font-weight: 800; color: #475569; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.025em;">Reference Image</div>
                        <a href="${item.reference_url}" target="_blank" style="display: block; border-radius: 12px; overflow: hidden; border: 2px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
                            <img src="${item.reference_url}" alt="Reference" style="width: 100%; max-height: 400px; object-fit: cover; display: block;" onerror="this.outerHTML='<div style=\'padding:20px;font-size:12px;color:#94a3b8;text-align:center;\'>N/A</div>'">
                        </a>
                        <a href="${item.reference_url}" target="_blank" style="display: inline-block; font-size: 12px; color: #4f46e5; margin-top: 8px; font-weight: 700; text-decoration: none; background: #f5f3ff; padding: 4px 10px; border-radius: 6px;">↗ View Full</a>
                    </div>`;
            }

            designPreHTML = `
                <div style="display: flex; flex-direction: column; gap: 12px; align-items: flex-end;">
                    ${previews}
                </div>`;
        }

        try {
            itemsHTML += `
                <div class="notif-item-row" style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; padding: 20px 0; border-bottom: 1px solid #e2e8f0; word-wrap: break-word; overflow-wrap: anywhere;">
                    <!-- Left Side (60%) -->
                    <div style="flex: 1 1 55%; min-width: 300px;">
                        <div style="font-weight: 800; color: #0f172a; font-size: 18px; margin-bottom: 4px; line-height: 1.2;">
                            ${esc(item.product_name || 'Custom Product')}
                        </div>
                        <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 16px;">${esc(item.category || 'General')}</div>
                        
                        ${customizationHTML || ''}
                        
                        <div style="margin-top: 16px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                            <div>
                                <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Quantity</div>
                                <div style="font-size: 15px; font-weight: 700; color: #1e293b;">${item.quantity || 0}</div>
                            </div>
                            <div>
                                <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Unit Price</div>
                                <div style="font-size: 15px; font-weight: 700; color: #1e293b;">PHP ${(item.unit_price || 0).toFixed(2)}</div>
                            </div>
                            <div>
                                <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Subtotal</div>
                                <div style="font-size: 15px; font-weight: 700; color: #6366f1;">PHP ${(item.subtotal || 0).toFixed(2)}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side (40%) -->
                    <div style="flex: 1 1 35%; min-width: 250px; text-align: right;">
                        ${designPreHTML || ''}
                    </div>
                </div>`;
        } catch (err) {
            console.error("Error rendering item row:", err);
            itemsHTML += `<div style="padding:10px; color:red;">Error rendering item details.</div>`;
        }
    });

    document.getElementById('omBody').innerHTML = `
        <div id="omFlash"></div>

        <div class="om-grid">
            <!-- Order Info -->
            <div class="om-card">
                <div class="om-card-title">Order Information</div>
                <div class="om-row">
                    <span class="om-label">Order Date</span>
                    <span class="om-value">${esc(d.order_date)}</span>
                </div>
                <div class="om-row">
                    <span class="om-label">Total Amount</span>
                    <span class="om-value" style="color:#6366f1;font-size:15px;">${esc(d.total_amount)}</span>
                </div>
                <div class="om-row">
                    <span class="om-label">Current Status</span>
                    <span class="om-value">${statusBadge(d.status)}</span>
                </div>
                <div class="om-row">
                    <span class="om-label">Payment Status</span>
                    <span class="om-value">${statusBadge(d.payment_status)}</span>
                </div>
                <!-- Flex Payment Option Display -->
                ${d.payment_type !== 'full_payment' ? `
                <div class="om-row">
                    <span class="om-label" style="color:#6366f1;">Payment Option</span>
                    <span class="om-value" style="color:#6366f1; font-weight:600;">
                        ${d.payment_type === '50_percent' ? '50% Downpayment' : 'Pay Upon Pickup'}
                    </span>
                </div>
                ` : ''}
                ${downBlock}
                ${payRefBlock}
                ${paymentProofBlock}

                <!-- Design Review Section -->
                <div id="omDesignReviewSection" style="margin-top:16px; padding:16px; background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
                    <div style="font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.07em; color:#94a3b8; margin-bottom:12px;">Design Review Queue</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span style="font-size:13px; font-weight:600; color:#1e293b;">Design Status:</span>
                        ${statusBadge(d.design_status || 'Pending')}
                    </div>
                    ${(() => {
                        // Buttons should be visible as long as it's not Approved
                        // (Allows unlimited revision requests from staff)
                        const showButtons = (d.design_status !== 'Approved');
                        const isFinalState = ['Completed', 'Cancelled'].includes(d.status);
                        
                        if (showButtons && !isFinalState) {
                            return `
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                <button class="btn-primary" onclick="approveDesign(${d.order_id}, '${esc(d.csrf_token)}')" style="background:#22c55e; font-size:12px; padding:8px;">Approve Design</button>
                                <button class="btn-secondary" onclick="openRevisionModal(${d.order_id}, '${esc(d.csrf_token)}')" style="color:#ef4444; border-color:#fee2e2; font-size:12px; padding:8px;">Request Revision</button>
                            </div>
                            `;
                        }
                        return '';
                    })()}

                    ${d.design_status === 'Approved' ? `
                    <div style="font-size:11px; color:#16a34a; font-weight:600; text-align:center;">
                        ✅ Design successfully approved${d.reviewed_at ? ' on ' + d.reviewed_at : ''}.
                    </div>
                    ${d.items && d.items[0] && d.items[0].has_design ? `
                        <div style="margin-top:10px; border: 1px solid #dcfce7; border-radius: 8px; overflow: hidden;">
                             <img src="${d.items[0].design_url}" style="width:100%; height:auto; display:block;">
                        </div>
                    ` : ''}
                    ` : ''}
                    ${d.design_status === 'Revision Requested' ? `
                    <div style="font-size:11px; color:#2563eb; font-weight:600; text-align:center;">
                        ℹ️ Revision requested from customer.
                    </div>
                    ` : ''}

                    ${d.revisions && d.revisions.length > 0 ? `
                        <div style="margin-top:16px; border-top:1px dashed #cbd5e1; padding-top:16px;">
                            <div style="font-size:12px; font-weight:700; color:#475569; margin-bottom:10px;">🕒 Past Revisions & Rejections</div>
                            ${d.revisions.map((rev, idx) => `
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin-bottom:8px;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                        <span style="font-size:11px; font-weight:700; color:#1e293b;">Review ${d.revisions.length - idx}</span>
                                        <span style="font-size:10px; color:#64748b;">${rev.created_at}</span>
                                    </div>
                                    <div style="font-size:11px; color:#b45309; margin-bottom:8px; line-height:1.4;"><strong>Discarded Reason:</strong> ${esc(rev.revision_reason)}</div>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <a href="${rev.design_url}" target="_blank" style="display:block; border-radius:4px; overflow:hidden; border:1px solid #cbd5e1; width:40px; height:40px; background:#fff;">
                                            <img src="${rev.design_url}" alt="Rejected Design" style="width:100%; height:100%; object-fit:cover;" onerror="this.outerHTML='<div style=\\'font-size:10px;color:#94a3b8;line-height:40px;text-align:center;\\'>N/A</div>'">
                                        </a>
                                        <div style="flex:1;">
                                            <div style="font-size:10px; font-weight:600; color:#475569;">Rejected Design File</div>
                                            <div style="font-size:10px; color:#94a3b8;">${esc(rev.product_name)}</div>
                                            <a href="${rev.design_url}" target="_blank" style="font-size:10px; color:#6366f1; text-decoration:none; font-weight:600;">↗ View Full Size</a>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>

                ${notesBlock}
                ${cancelBlock}
                ${revisionBlock}

                <!-- Update Status Form Removed (Managed in Production Sidebar) -->
            </div>

            <div style="display:flex; flex-direction:column; gap:20px;">

                <!-- Customer Info -->
                <div class="om-card">
                    <div class="om-card-title">Customer Information</div>
                <div class="om-cust-header">
                    <div class="om-avatar">${esc(d.cust_initial)}</div>
                    <div>
                        <div style="font-weight:700;font-size:15px;color:#1e293b;">${esc(d.cust_name)}</div>
                        <div style="font-size:12px;color:#9ca3af;">Customer</div>
                    </div>
                </div>
                <div class="om-row">
                    <span class="om-label">Email</span>
                    <span class="om-value">${esc(d.cust_email)}</span>
                </div>
                <div class="om-row">
                    <span class="om-label">Contact Number</span>
                    <span class="om-value">${esc(d.cust_phone)}</span>
                </div>

            </div>
        </div>

        <!-- Items -->
        <div class="om-items-section" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
            <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9;">
                Order Items (${d.items.length})
            </div>
            <div style="display: flex; flex-direction: column;">
                ${itemsHTML}
            </div>
            <div style="display: flex; justify-content: flex-end; align-items: center; padding-top: 20px; margin-top: 10px;">
                <div style="font-size: 16px; font-weight: 700; color: #475569; margin-right: 16px;">Total Amount:</div>
                <div style="font-size: 20px; font-weight: 800; color: #6366f1;">${esc(d.total_amount)}</div>
            </div>
        </div>
    `;
}

// ── Update status via AJAX ───────────────────────────────
function updateOrderStatus(btn, orderId, csrfToken) {
    const newStatus = document.getElementById('omStatusSelect').value;
    // Removed flash div usage here

    const formData = new FormData();
    formData.append('update_status', '1');
    formData.append('order_id', orderId);
    formData.append('status', newStatus);
    formData.append('csrf_token', csrfToken);

    const originalHtml = btn ? btn.innerHTML : 'Update Status';

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="om-spinner" style="width:14px;height:14px;border-width:2px;margin:0 6px 0 0;display:inline-block;vertical-align:middle;"></span> Updating...';
    }

    fetch('/printflow/staff/orders.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
        if (res.success) {
            showStatusOverlay('✅', `Status successfully updated to "${res.new_status}"!`);
            
            // Update the status badge in the table row immediately
            const badge = document.querySelector(`button[onclick="openOrderModal(${orderId})"]`)
                ?.closest('tr')?.querySelector('td:nth-child(5)');
            if (badge) {
                badge.innerHTML = statusBadge(res.new_status);
                // Visual highlight effect
                badge.style.transition = 'background 0.3s, transform 0.3s';
                badge.style.background = '#d1fae5';
                badge.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    badge.style.background = 'transparent';
                    badge.style.transform = 'scale(1)';
                }, 1500);
            }

            // Refresh modal data AFTER a delay so the message can be read
            setTimeout(() => {
                fetch(`/printflow/staff/get_order_data.php?id=${orderId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    renderOrderModal(data);
                });
            }, 1500);
        } else {
            showStatusOverlay('❌', `Failed to update status: ${res.error || 'Please try again.'}`, true);
        }
    })
    .catch(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
        showStatusOverlay('⚠️', 'Network error. Please try again.', true);
    });
}

function showStatusOverlay(icon, msg, isError = false) {
    const overlay = document.getElementById('omStatusOverlay');
    const iconEl = document.getElementById('omStatusIcon');
    const msgEl = document.getElementById('omStatusMsg');
    
    iconEl.textContent = icon;
    msgEl.textContent = msg;
    
    overlay.classList.add('active');
    
    // Auto-hide after 2.5 seconds
    setTimeout(() => {
        overlay.classList.remove('active');
    }, 2500);
}

function verifyPayment(orderId, action) {
    if (!confirm(`Are you sure you want to ${action.toLowerCase()} this payment?`)) return;
    
    showStatusOverlay('⌛', 'Processing Power verification...');

    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('action', action);

    fetch('/printflow/staff/api_verify_payment.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showStatusOverlay('✅', `Payment ${action === 'Approve' ? 'Approved' : 'Rejected'} successfully!`);
            setTimeout(() => {
                openOrderModal(orderId); // Refresh modal
                // Update table badge if it exists
                const badge = document.querySelector(`button[onclick="openOrderModal(${orderId})"]`)?.closest('tr')?.querySelector('td:nth-child(5)');
                if (badge) badge.innerHTML = statusBadge(res.new_status);
            }, 1000);
        } else {
            showStatusOverlay('❌', `Error: ${res.error}`, true);
        }
    })
    .catch(() => {
        showStatusOverlay('⚠️', 'Network error. Please try again.', true);
    });
}

// ── Design Review Actions ────────────────────────────────
function approveDesign(orderId, csrfToken) {
    if (!confirm('Are you sure you want to approve this design?')) return;

    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('csrf_token', csrfToken);

    fetch('/printflow/staff/approve_design_process.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showStatusOverlay('✅', res.message);
            setTimeout(() => {
                openOrderModal(orderId); // Refresh modal
            }, 1000);
        } else {
            alert(res.error || 'Failed to approve design');
        }
    })
    .catch(() => alert('Network error occurred'));
}

function openRevisionModal(orderId, csrfToken) {
    document.getElementById('revOrderId').value = orderId;
    document.getElementById('revCsrfToken').value = csrfToken;
    document.getElementById('revisionModal').classList.add('open');
}

function closeRevisionModal() {
    document.getElementById('revisionModal').classList.remove('open');
    document.getElementById('revForm').reset();
    document.getElementById('revOtherWrapper').style.display = 'none';
}

function handleReasonChange(select) {
    const otherWrapper = document.getElementById('revOtherWrapper');
    const otherInput = document.getElementById('revOtherInput');
    const reasonValue = select.value;

    if (reasonValue === 'Other') {
        otherWrapper.style.display = 'block';
        otherInput.required = true;
    } else {
        otherWrapper.style.display = 'none';
        otherInput.required = false;
    }
    
    // Update the actual text being sent if it's a predefined reason
    // We'll handle this in the process file or by using a hidden input.
    // For now, let's just make sure the process file can handle both.
}

// Intercept revision form submission to combine reason
document.getElementById('revForm')?.addEventListener('submit', function(e) {
    // Disable submit button to prevent spam/duplicate notifications
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        if (submitBtn.disabled) {
            e.preventDefault();
            return false;
        }
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        submitBtn.style.opacity = '0.7';
        submitBtn.style.cursor = 'not-allowed';
    }

    const select = this.querySelector('select[name="revision_reason_select"]');
    const other = this.querySelector('textarea[name="revision_reason_other"]');
    
    let finalReason = select.value;
    if (finalReason === 'Other') {
        finalReason = other.value;
    }
    
    // We can either update a hidden field or just let the PHP handle it.
    // Let's add a hidden field for convenience.
    let reasonHidden = this.querySelector('input[name="revision_reason"]');
    if (!reasonHidden) {
        reasonHidden = document.createElement('input');
        reasonHidden.type = 'hidden';
        reasonHidden.name = 'revision_reason';
        this.appendChild(reasonHidden);
    }
    reasonHidden.value = finalReason;
});

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

function formatCurrency(val) {
    return '₱' + parseFloat(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Auto-open modal if order_id is in URL
window.addEventListener('DOMContentLoaded', () => {
    const statusSelect = document.getElementById('statusFilterSelect');
    if (statusSelect) {
        statusSelect.addEventListener('change', () => {
            const params = new URLSearchParams(window.location.search);
            const next = statusSelect.value.trim();
            if (next) {
                params.set('status', next);
            } else {
                params.delete('status');
            }
            params.delete('page');
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order_id');
    if (orderId) {
        openOrderModal(orderId);
    }
});
</script>

</body>
</html>
