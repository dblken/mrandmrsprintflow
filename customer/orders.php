<?php
/**
 * Customer Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');
ensure_ratings_table_exists();

$customer_id = get_user_id();
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');
// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

// Get order statistics for the summary cards
$total_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?", 'i', [$customer_id]);
$total_orders = $total_orders_result[0]['count'] ?? 0;

$pending_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status IN ('Pending', 'Pending Approval', 'For Revision')", 'i', [$customer_id]);
$pending_orders = $pending_orders_result[0]['count'] ?? 0;

$processing_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status IN ('Processing', 'In Production', 'Printing')", 'i', [$customer_id]);
$processing_orders = $processing_orders_result[0]['count'] ?? 0;

$ready_orders_result = db_query("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'Ready for Pickup'", 'i', [$customer_id]);
$ready_orders = $ready_orders_result[0]['count'] ?? 0;

// TikTok style tabs (redirect removed tabs to completed)
$active_tab = $_GET['tab'] ?? 'all';
if (in_array($active_tab, ['torate', 'totalorders'], true)) {
    $active_tab = 'completed';
}

// Tab mappings to exact statuses
$tab_status_map = [
    'pending'    => ['Pending', 'Pending Approval', 'Pending Review', 'For Revision'],
    'approved'   => ['Approved'],
    'toverify'   => ['To Verify', 'Downpayment Submitted', 'Pending Verification'],
    'topay'      => ['To Pay'],
    'production' => ['In Production', 'Processing', 'Printing', 'Paid – In Process'],
    'pickup'     => ['Ready for Pickup'],
    'rejected'   => ['Rejected'],
    'torate'     => ['To Rate', 'Rated', 'Completed'],
    'completed'  => ['Completed', 'To Rate', 'Rated'],
    'cancelled'  => ['Cancelled'],
    'totalorders' => ['Completed', 'To Rate', 'Rated', 'Finished', 'Released', 'Claimed'],
];

// Statuses where price is hidden from customer
$HIDDEN_PRICE_STATUSES = ['Pending', 'Pending Approval', 'Pending Review', 'For Revision', 'Approved'];

$has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
$has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
$first_product_image_expr = "''";
if ($has_product_image && $has_photo_path) {
    $first_product_image_expr = "COALESCE(p.photo_path, p.product_image)";
} elseif ($has_product_image) {
    $first_product_image_expr = "p.product_image";
} elseif ($has_photo_path) {
    $first_product_image_expr = "p.photo_path";
}

// Per-tab order counts for status indicators.
$status_counts_raw = db_query("
    SELECT status, COUNT(*) AS total
    FROM orders
    WHERE customer_id = ?
    GROUP BY status
", 'i', [$customer_id]);

$status_counts = [];
foreach ($status_counts_raw as $row) {
    $status_counts[$row['status']] = (int)$row['total'];
}

$tab_counts = [
    'all' => (int)$total_orders,
    'pending' => 0,
    'approved' => 0,
    'toverify' => 0,
    'topay' => 0,
    'production' => 0,
    'pickup' => 0,
    'rejected' => 0,
    'torate' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'totalorders' => 0
];

foreach ($tab_status_map as $tab_key => $statuses) {
    foreach ($statuses as $status_name) {
        $tab_counts[$tab_key] += $status_counts[$status_name] ?? 0;
    }
}

// Build query
$sql = "SELECT o.*, 
        (SELECT GROUP_CONCAT(DISTINCT p.sku ORDER BY p.sku SEPARATOR '-') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as order_sku,
        (SELECT GROUP_CONCAT(COALESCE(p.name, 'Service Order') SEPARATOR ', ') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as item_names,
        (SELECT COALESCE(p.name, 'Service Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_name,
        (SELECT p.product_id FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_id,
        (SELECT {$first_product_image_expr} FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_image,
        (SELECT oi.customization_data FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization,
        (SELECT oi.order_item_id FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_id,
        (SELECT IF(oi.design_image IS NOT NULL AND oi.design_image != '', 1, 0) FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_has_design,
        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.order_id) as total_quantity,
        (SELECT r.rating FROM reviews r WHERE r.order_id = o.order_id LIMIT 1) as rating_value,
        (SELECT jo.payment_rejection_reason
         FROM job_orders jo
         WHERE jo.order_id = o.order_id
           AND jo.payment_rejection_reason IS NOT NULL
           AND jo.payment_rejection_reason != ''
         ORDER BY jo.payment_verified_at DESC, jo.id DESC
         LIMIT 1) as payment_rejection_reason
        FROM orders o WHERE o.customer_id = ?";
$count_sql = "SELECT COUNT(*) as total FROM orders o WHERE o.customer_id = ?";
$params = [$customer_id];
$count_params = [$customer_id]; // Need this for the count query
$types = 'i';
$count_types = 'i'; // Need this for the count query

if ($active_tab !== 'all' && isset($tab_status_map[$active_tab])) {
    $statuses = $tab_status_map[$active_tab];
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    
    $sql .= " AND o.status IN ($placeholders)";
    $count_sql .= " AND o.status IN ($placeholders)";
    
    foreach ($statuses as $s) {
        $params[] = $s;
        $count_params[] = $s; // Also add to count params
        $types .= 's';
        $count_types .= 's'; // Also add to count types
    }
}

// Pagination settings
$items_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = (int)($total_result[0]['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_items / $items_per_page));
if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// Use inline LIMIT/OFFSET for all tabs, including "All".
$limit = (int)$items_per_page;
$offset_val = (int)$offset;
$display_sort_expr = "
    CASE
        WHEN LOWER(TRIM(COALESCE(o.status, ''))) = 'cancelled' AND o.cancelled_at IS NOT NULL THEN o.cancelled_at
        WHEN LOWER(TRIM(COALESCE(o.status, ''))) IN ('completed', 'to rate', 'rated') AND o.updated_at IS NOT NULL THEN o.updated_at
        WHEN LOWER(TRIM(COALESCE(o.status, ''))) IN ('ready for pickup', 'to receive', 'ready') AND o.updated_at IS NOT NULL THEN o.updated_at
        WHEN LOWER(TRIM(COALESCE(o.status, ''))) IN ('in production', 'processing', 'printing', 'paid - in process', 'paid â€“ in process') AND o.updated_at IS NOT NULL THEN o.updated_at
        WHEN LOWER(TRIM(COALESCE(o.status, ''))) = 'approved' AND o.updated_at IS NOT NULL THEN o.updated_at
        WHEN LOWER(TRIM(COALESCE(o.status, ''))) NOT IN ('pending', 'pending approval', 'pending review') AND o.updated_at IS NOT NULL THEN o.updated_at
        ELSE o.order_date
    END
";
$sql .= " ORDER BY {$display_sort_expr} DESC, o.order_id DESC LIMIT {$limit} OFFSET {$offset_val}";

$orders_raw = db_query($sql, $types, $params);
$orders = is_array($orders_raw) ? $orders_raw : [];
foreach ($orders as &$order) {
    $order['order_code'] = printflow_format_order_code($order['order_id'] ?? 0, $order['order_sku'] ?? '');
    $order['_display_timestamp_meta'] = printflow_customer_order_timestamp_meta($order);
    $order['_display_ts'] = !empty($order['_display_timestamp_meta']['datetime'])
        ? (strtotime((string)$order['_display_timestamp_meta']['datetime']) ?: 0)
        : 0;
}
unset($order);

usort($orders, static function (array $a, array $b): int {
    $ta = (int)($a['_display_ts'] ?? 0);
    $tb = (int)($b['_display_ts'] ?? 0);
    if ($ta === $tb) {
        return (int)($b['order_id'] ?? 0) <=> (int)($a['order_id'] ?? 0);
    }
    return $tb <=> $ta;
});

$page_title = 'My Orders - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Orders Page — dark background compatible */
.orders-theme-page {
    --lp-text: #e0f2fe;
    --lp-muted: #94a3b8;
    --lp-border: rgba(83,197,224,0.18);
    --lp-accent: #53c5e0;
    --lp-accent-l: #7acae3;
    color: #e0f2fe;
    position: relative;
    z-index: 1;
}
.orders-theme-page *,
.orders-theme-page *::before,
.orders-theme-page *::after {
    box-sizing: border-box;
}
.orders-page-container { margin-top: 1rem; margin-bottom: 2rem; max-width: 1100px; margin-left: auto; margin-right: auto; padding: 0 1rem; }

.orders-theme-page .unified-dashboard {
    background: rgba(0, 49, 61, 0.88);
    border: 1px solid rgba(83,197,224,0.18) !important;
    border-radius: 12px !important;
    overflow: hidden;
    margin-bottom: 3rem;
    box-shadow: none;
    backdrop-filter: blur(8px);
}

.orders-theme-page .tt-tabs-wrapper {
    position: sticky; top: 0px; z-index: 40;
    background: rgba(0,28,36,0.98);
    border-bottom: 1px solid rgba(83,197,224,0.15) !important;
    border-radius: 0 !important;
    padding: 0.75rem;
}

.orders-theme-page .tt-tabs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    scrollbar-width: none;
    padding: 0.2rem 0.5rem;
    justify-content: flex-start;
}
@media (min-width: 900px) {
    .orders-theme-page .tt-tabs { justify-content: space-between; width: 100%; gap: 0.25rem; }
    .orders-theme-page .tt-tab { flex: 1; justify-content: center; }
}
.orders-theme-page .tt-tabs::-webkit-scrollbar { display: none; }
.orders-theme-page .tt-tab {
    padding: 0.75rem 1.25rem;
    font-size: 0.82rem;
    color: #94a3b8;
    font-weight: 700;
    text-decoration: none;
    border-radius: 10px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    border: 1px solid transparent;
}
@media (max-width: 640px) {
    .orders-theme-page .tt-tab { padding: 0.7rem 1.1rem; font-size: 0.85rem; }
}
.orders-theme-page .tt-tab:hover {
    color: #eaf6fb;
    background: rgba(83,197,224,0.08);
    border-color: rgba(83,197,224,0.15);
}
.orders-theme-page .tt-tab.active {
    background: rgba(83, 197, 224, 0.15);
    color: #53c5e0;
    border-color: rgba(83, 197, 224, 0.4);
    box-shadow: 0 4px 15px rgba(83, 197, 224, 0.1);
}
.orders-theme-page .tt-tab-count {
    font-size: 0.7rem;
    background: rgba(255,255,255,0.05);
    padding: 2px 7px;
    border-radius: 6px !important;
    font-weight: 800;
    transition: all 0.3s;
}
.orders-theme-page .tt-tab.active .tt-tab-count {
    background: #53c5e0;
    color: #00151b;
}

.orders-theme-page .orders-list-content {
    background: #ffffff;
    min-height: 0;
}
.orders-theme-page .ct-order-card {
    padding: 0.95rem 2rem;
    transition: background 0.2s, border-color 0.2s;
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    margin-bottom: 0 !important;
    box-shadow: none !important;
    cursor: pointer;
}
@media (max-width: 640px) {
    .orders-theme-page .ct-order-card { padding: 1rem; }
}
.orders-theme-page .ct-order-card + .ct-order-card { border-top: 1px solid #e2e8f0 !important; }
.orders-theme-page .ct-order-card:hover { background: #f8fafc !important; }

.orders-theme-page .card-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.65rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #e2e8f0;
}
@media (max-width: 640px) {
    .orders-theme-page .card-top-row { margin-bottom: 0.85rem; padding-bottom: 0.85rem; }
}
.orders-theme-page .order-id-chip {
    font-size: 0.7rem;
    font-weight: 900;
    color: #0e7490;
    background: rgba(14,116,144,0.08);
    padding: 4px 10px;
    border-radius: 6px !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
@media (max-width: 640px) {
    .orders-theme-page .order-id-chip { font-size: 0.75rem; padding: 5px 12px; }
}
.orders-theme-page .card-content { display: flex; gap: 1.35rem; align-items: flex-start; }
@media (max-width: 640px) {
    .orders-theme-page .card-content { flex-direction: column; gap: 1rem; }
}
.orders-theme-page .img-preview-box {
    width: 70px; height: 70px; flex-shrink: 0;
    border-radius: 8px !important; overflow: hidden;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}
@media (max-width: 640px) {
    .orders-theme-page .img-preview-box { width: 80px; height: 80px; }
}
.orders-theme-page .img-preview-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.orders-theme-page .ct-order-card:hover .img-preview-box img { transform: scale(1.05); }

.orders-theme-page .details-column { flex: 1; min-width: 0; }
@media (max-width: 640px) {
    .orders-theme-page .details-column { width: 100%; }
}
.orders-theme-page .order-title {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 0.35rem;
    line-height: 1.3;
}
@media (max-width: 640px) {
    .orders-theme-page .order-title { font-size: 1.05rem; }
}
.orders-theme-page .qty-tag { font-size: 0.75rem; color: #0e7490; font-weight: 800; }
@media (max-width: 640px) {
    .orders-theme-page .qty-tag { font-size: 0.8rem; }
}
.orders-theme-page .timestamp-text { font-size: 0.72rem; color: #64748b; margin-top: 0.4rem; font-weight: 700; }
@media (max-width: 640px) {
    .orders-theme-page .timestamp-text { font-size: 0.75rem; margin-top: 0.5rem; }
}
.orders-theme-page .rejected-reason-text {
    margin-top: 0.55rem;
    font-size: 0.76rem;
    line-height: 1.45;
    color: #991b1b;
    font-weight: 700;
}
@media (max-width: 640px) {
    .orders-theme-page .rejected-reason-text { font-size: 0.8rem; }
}

.orders-theme-page .pricing-column { text-align: right; min-width: 280px; display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem; }
@media (max-width: 640px) {
    .orders-theme-page .pricing-column { text-align: left; align-items: flex-start; min-width: unset; width: 100%; gap: 1rem; }
}
.orders-theme-page .final-price { font-size: 1.25rem; font-weight: 900; color: #0f172a; line-height: 1; }
@media (max-width: 640px) {
    .orders-theme-page .final-price { font-size: 1.4rem; }
}
.orders-theme-page .hidden-price-msg { font-size: 0.72rem; color: #64748b; font-style: italic; line-height: 1.4; margin-bottom: 0.25rem; }
@media (max-width: 640px) {
    .orders-theme-page .hidden-price-msg { font-size: 0.78rem; }
}
.orders-theme-page .card-actions-inline { display: flex; gap: 0.5rem; flex-wrap: wrap; }
@media (max-width: 640px) {
    .orders-theme-page .card-actions-inline { width: 100%; gap: 0.65rem; }
}

.card-footer-actions {
    display: flex; justify-content: flex-end; gap: 0.85rem;
    margin-top: 0.75rem; padding-top: 0.85rem;
    border-top: 1px solid #e2e8f0;
}
.orders-theme-page .action-button {
    padding: 0.5rem 1rem;
    border-radius: 6px !important;
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    transition: all 0.2s; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;
    justify-content: center;
    min-height: 40px;
}
@media (max-width: 640px) {
    .orders-theme-page .action-button {
        flex: 1 1 calc(50% - 0.35rem);
        font-size: 0.75rem;
        padding: 0.65rem 1rem;
        min-height: 44px;
    }
    .orders-theme-page .action-button svg { width: 16px; height: 16px; }
}
.orders-theme-page .btn-chat {
    background: rgba(14, 116, 144, 0.08) !important;
    color: #0e7490 !important;
    border: 1px solid rgba(14, 116, 144, 0.32) !important;
    box-shadow: none;
}
.orders-theme-page .btn-chat:hover {
    background: #0e7490 !important;
    color: #ffffff !important;
    box-shadow: 0 8px 18px rgba(14, 116, 144, 0.18);
}
.orders-theme-page .btn-main-blue {
    background: transparent !important;
    color: #0f172a !important;
    border: 1px solid #0e7490 !important;
}
.orders-theme-page .btn-main-blue:hover {
    background: rgba(14, 116, 144, 0.08) !important;
    box-shadow: 0 8px 18px rgba(14, 116, 144, 0.14);
}
.orders-theme-page .btn-rate-order {
    background: rgba(249, 115, 22, 0.1) !important;
    color: #f97316 !important;
    border: 1px solid rgba(249, 115, 22, 0.4) !important;
    border-radius: 10px !important;
}
.orders-theme-page .btn-rate-order:hover {
    background: #f97316 !important;
    color: #fff !important;
    box-shadow: 0 0 15px rgba(249, 115, 22, 0.4);
}

.orders-theme-page .status-pill,
#itemsModal .status-pill {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 999px !important;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
}
@media (max-width: 640px) {
    .orders-theme-page .status-pill,
    #itemsModal .status-pill { font-size: 0.7rem; padding: 4px 12px; }
}
.orders-theme-page .st-pending, #itemsModal .st-pending  { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.orders-theme-page .st-approved, #itemsModal .st-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.orders-theme-page .st-production, #itemsModal .st-production { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
.orders-theme-page .st-ready, #itemsModal .st-ready    { background: #cffafe; color: #155e75; border: 1px solid #a5f3fc; }
.orders-theme-page .st-completed, #itemsModal .st-completed { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.orders-theme-page .st-cancelled, .orders-theme-page .st-unpaid, #itemsModal .st-cancelled, #itemsModal .st-unpaid { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.rated-status-tag {
    font-size: 0.72rem; font-weight: 700; color: #b45309;
    padding: 4px 10px; background: #fef9c3;
    border-radius: 6px !important; border: 1px solid #fde68a;
}

.empty-view {
    text-align: center; padding: 6rem 2rem;
    background: rgba(0, 21, 27, 0.3); border-radius: 12px; border: 1px dashed rgba(83, 197, 224, 0.2);
}
.empty-view-title { font-size: 1.5rem; font-weight: 800; color: #eaf6fb; margin-bottom: 0.75rem; letter-spacing: 0.03em; }
.empty-view-sub { color: #9fc4d4; font-size: 0.95rem; margin-bottom: 2rem; }
.empty-view-btn {
    display: inline-block; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 0.95rem; font-weight: 800; text-align: center; text-transform: uppercase; background: transparent; color: #eaf6fb; border: 2px solid #53c5e0; letter-spacing: 0.05em; transition: all 0.3s;
}
.empty-view-btn:hover {
    background: rgba(83, 197, 224, 0.15); box-shadow: 0 0 20px rgba(83, 197, 224, 0.2);
}

/* Modal stays dark — it overlays everything */
#itemsModal {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 2rem 1rem; opacity: 0; pointer-events: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(8, 12, 15, 0.85);
}
#itemsModal.open { opacity: 1; pointer-events: auto; }
.im-panel {
    background: #ffffff !important;
    border: 1px solid #e2e8f0;
    border-radius: 12px !important;
    width: 100%; max-width: 1150px;
    max-height: calc(100vh - 4rem);
    display: flex; flex-direction: column;
    box-shadow: 0 24px 60px rgba(15,23,42,0.18);
    overflow: hidden;
    transform: scale(0.95);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
#itemsModal.open .im-panel { transform: scale(1); }
.im-header {
    padding: 0.75rem 1.25rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
}
.im-title { font-size: 1rem; font-weight: 800; color: #0f172a !important; margin: 0; }
.im-subtitle { font-size: 0.82rem; color: #64748b !important; margin-top: 4px; font-weight: 600; }
.im-close {
    width: 42px; height: 42px; border-radius: 8px !important;
    display: flex; align-items: center; justify-content: center;
    background: #ffffff; border: 1px solid #e2e8f0; color: #64748b;
    cursor: pointer; transition: all 0.2s; font-size: 1.2rem;
}
.im-close:hover { background: #fee2e2; color: #b91c1c; }
.im-body { padding: 1.5rem; overflow-y: auto; flex: 1; scrollbar-width: thin; scrollbar-color: #0e7490 #e2e8f0; }
.im-body::-webkit-scrollbar { width: 10px; }
.im-body::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 999px; }
.im-body::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #0e7490, #0a2530); border-radius: 999px; border: 2px solid #e2e8f0; }
.im-body::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #0f766e, #0a2530); }
.im-dashboard { display: grid; grid-template-columns: 1fr 340px; gap: 2rem; }
.im-main { display: flex; flex-direction: column; gap: 1.5rem; min-width: 0; }
.im-sidebar { display: flex; flex-direction: column; gap: 1.25rem; }
.im-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.im-table th { text-align: left; padding: 0.9rem 1.25rem; font-size: 0.72rem; font-weight: 800; color: #64748b; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
.im-table td { padding: 1.25rem 1.25rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; color: #0f172a; }
.im-table th:first-child,
.im-table td:first-child { width: auto; }
.im-table th:nth-child(2),
.im-table td:nth-child(2) { width: 110px; }
.im-table th:nth-child(3),
.im-table td:nth-child(3) { width: 150px; }
.im-sec-card { background: #ffffff; border: 1px solid #e2e8f0; border-left: 3px solid #cbd5e1; border-radius: 8px; padding: 0.75rem 1.25rem; display: flex; flex-direction: column; }
.im-sec-card.accent { border-left-color: #0e7490; background: #f8fafc; }
.im-label { font-size: 0.68rem; color: #64748b; font-weight: 800; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.08em; }
.im-val { font-size: 0.95rem; font-weight: 700; color: #334155; line-height: 1.55; }
.im-spec-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; margin: 0.95rem 0 0; }
.im-spec-card { background: #f8fafc; border: 1px solid #dbeafe; padding: 0.8rem 0.9rem; border-radius: 10px; min-width: 0; }
.im-spec-label { display: block; font-size: 0.65rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.3rem; }
.im-spec-value { display: block; font-size: 0.95rem; font-weight: 700; color: #0f172a; line-height: 1.45; word-break: break-word; }
.im-thumb { width: 90px; height: 90px; object-fit: cover; border-radius: 6px !important; border: 1px solid #e2e8f0; background: #f8fafc; }
.im-item-title { font-size: 1rem; font-weight: 800; color: #0f172a; line-height: 1.4; margin-bottom: 0.15rem; }
.im-meta-title { font-size: 0.85rem; font-weight: 800; color: #0f172a; text-transform: none; letter-spacing: 0; margin-bottom: 0.75rem; padding-bottom: 0.45rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
.im-meta-value { font-size: 0.95rem; font-weight: 700; color: #334155; line-height: 1.5; }
.im-qty-value, .im-total-value { font-size: 1rem; font-weight: 800; line-height: 1.4; }
.im-qty-value { color: #0f172a; }
.im-total-value { color: #0e7490; }
.im-note-card { margin-top: 1.5rem; padding: 1.15rem; background: rgba(83, 197, 224, 0.08); border: 1px solid rgba(83, 197, 224, 0.14); border-left: 4px solid #53c5e0; border-radius: 10px; }
.im-note-label { font-size: 0.75rem; font-weight: 800; color: #0e7490; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.45rem; }
.im-note-copy { font-size: 0.95rem; color: #334155; line-height: 1.6; font-weight: 600; }
.im-reject-card {
    padding: 1rem 1.1rem;
    background: linear-gradient(180deg, #fff7ed 0%, #fff1f2 100%);
    border: 1px solid #fecaca;
    border-radius: 14px;
}
.im-reject-title {
    font-size: 0.78rem;
    font-weight: 900;
    color: #9a3412;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.45rem;
}
.im-reject-copy {
    font-size: 0.95rem;
    line-height: 1.55;
    color: #7f1d1d;
    font-weight: 600;
}
.im-upload-picker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 46px;
    padding: 0.85rem 1rem;
    border: 1px dashed #0e7490;
    border-radius: 12px;
    background: rgba(14, 116, 144, 0.05);
    color: #0e7490;
    font-size: 0.75rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: all 0.2s ease;
}
.im-upload-picker:hover {
    background: rgba(14, 116, 144, 0.1);
    border-color: #0a2530;
    color: #0a2530;
}
.im-upload-filename {
    font-size: 0.78rem;
    color: #475569;
    font-weight: 700;
    line-height: 1.45;
    word-break: break-word;
}
.im-primary-action {
    width: 100%;
    min-height: 48px;
    padding: 0.95rem 1rem;
    border: none;
    border-radius: 12px;
    background: #0a2530;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition: all 0.2s ease;
}
.im-primary-action:hover {
    background: #0d3038;
    box-shadow: 0 12px 24px rgba(10, 37, 48, 0.18);
}
.im-primary-action:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    box-shadow: none;
}

#cancelModal {
    position: fixed; inset: 0; z-index: 100000;
    display: flex; align-items: center; justify-content: center;
    padding: 1.5rem; opacity: 0; pointer-events: none;
    transition: all 0.25s ease;
    background: rgba(15,23,42,0.6);
    backdrop-filter: blur(4px);
}
#cancelModal.open { opacity: 1; pointer-events: auto; }
.cm-box {
    background: #0a2530 !important;
    border: 1px solid rgba(83,197,224,0.2);
    border-radius: 20px;
    width: 100%; max-width: 460px;
    padding: 2rem;
    box-shadow: 0 40px 100px rgba(0,0,0,0.5);
}
.cm-opt-label {
    display: flex; align-items: center; gap: 1rem; padding: 1rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; margin-bottom: 0.6rem;
    cursor: pointer; transition: all 0.2s; color: #fff;
}
.cm-opt-label:hover { background: rgba(255,255,255,0.06); }
.cm-opt-label.active { border-color: #53c5e0; background: rgba(83,197,224,0.1); }

@media (max-width: 640px) {
    .im-dashboard { grid-template-columns: 1fr; }
    .orders-page-container { padding: 0 0.75rem; }
    .unified-dashboard { margin-bottom: 2rem; }
    .im-table th:nth-child(2),
    .im-table td:nth-child(2) { width: 84px; }
    .im-table th:nth-child(3),
    .im-table td:nth-child(3) { width: 110px; }
    .im-table th,
    .im-table td { padding-left: 0.85rem; padding-right: 0.85rem; }
}

.capitalize-first { display: inline-block; }
.capitalize-first::first-letter { text-transform: uppercase; }
</style>


<div class="orders-theme-page min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <div id="orderSuccessBannerHost">
            <?php if (isset($_SESSION['order_success'])):
                $order_success_msg = $_SESSION['order_success'];
                unset($_SESSION['order_success']);
            ?>
                <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 1rem 1.5rem; border-radius: 12px; margin: 0.75rem 0 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 600; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink: 0;">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($order_success_msg); ?></span>
                </div>
                <script>
                    window.__pfOrderSuccessMessage = <?php echo json_encode($order_success_msg); ?>;
                    try { localStorage.setItem('pf_order_success_msg', window.__pfOrderSuccessMessage); } catch (e) {}
                </script>
            <?php endif; ?>
        </div>
        <div class="mb-8 mt-2"></div>
        
        <!-- Unified Dashboard Container -->
        <div class="unified-dashboard">
            <!-- Sticky Navigation Tabs -->
            <div class="tt-tabs-wrapper">
                <div class="tt-tabs" id="ttTabsScrollContainer">
                    <a href="?tab=all" class="tt-tab <?php echo $active_tab === 'all' ? 'active' : ''; ?>">All <span class="tt-tab-count"><?php echo $tab_counts['all']; ?></span></a>
                    <a href="?tab=pending" class="tt-tab <?php echo $active_tab === 'pending' ? 'active' : ''; ?>">Pending <span class="tt-tab-count"><?php echo $tab_counts['pending']; ?></span></a>
                    <a href="?tab=approved" class="tt-tab <?php echo $active_tab === 'approved' ? 'active' : ''; ?>">Approved <span class="tt-tab-count"><?php echo $tab_counts['approved']; ?></span></a>
                    <a href="?tab=topay" class="tt-tab <?php echo $active_tab === 'topay' ? 'active' : ''; ?>">To Pay <span class="tt-tab-count"><?php echo $tab_counts['topay']; ?></span></a>
                    <a href="?tab=toverify" class="tt-tab <?php echo $active_tab === 'toverify' ? 'active' : ''; ?>">To Verify <span class="tt-tab-count"><?php echo $tab_counts['toverify']; ?></span></a>
                    <a href="?tab=production" class="tt-tab <?php echo $active_tab === 'production' ? 'active' : ''; ?>">Production <span class="tt-tab-count"><?php echo $tab_counts['production']; ?></span></a>
                    <a href="?tab=pickup" class="tt-tab <?php echo $active_tab === 'pickup' ? 'active' : ''; ?>">Ready <span class="tt-tab-count"><?php echo $tab_counts['pickup']; ?></span></a>
                    <a href="?tab=rejected" class="tt-tab <?php echo $active_tab === 'rejected' ? 'active' : ''; ?>">Rejected <span class="tt-tab-count"><?php echo $tab_counts['rejected']; ?></span></a>
                    <a href="?tab=completed" class="tt-tab <?php echo $active_tab === 'completed' ? 'active' : ''; ?>">Completed <span class="tt-tab-count"><?php echo $tab_counts['completed']; ?></span></a>
                    <a href="?tab=cancelled" class="tt-tab <?php echo $active_tab === 'cancelled' ? 'active' : ''; ?>">Cancelled <span class="tt-tab-count"><?php echo $tab_counts['cancelled']; ?></span></a>
                </div>
            </div>

            <!-- Dashboard Content Area -->
            <div class="orders-list-content">
                <?php if (empty($orders)): ?>
                    <div class="empty-view">
                        <div class="empty-view-title">No orders found</div>
                        <div class="empty-view-sub">Orders from this category will show up here.</div>
                        <a href="<?php echo BASE_URL; ?>/customer/services.php" class="empty-view-btn">Browse Services</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $index => $order): ?>
                        <?php 
                            // ... (logic remains same)
                            $s = strtolower($order['status']);
                            $st_cls = 'st-pending';
                            if (strpos($s, 'approved') !== false) $st_cls = 'st-approved';
                            elseif (strpos($s, 'production') !== false || strpos($s, 'processing') !== false || strpos($s, 'printing') !== false) $st_cls = 'st-production';
                            elseif (strpos($s, 'ready') !== false || strpos($s, 'pickup') !== false) $st_cls = 'st-ready';
                            elseif (strpos($s, 'completed') !== false || strpos($s, 'rated') !== false || strpos($s, 'rate') !== false) $st_cls = 'st-completed';
                            elseif (strpos($s, 'rejected') !== false) $st_cls = 'st-cancelled';
                            elseif (strpos($s, 'cancelled') !== false) $st_cls = 'st-cancelled';
                            
                            $c_json = !empty($order['first_item_customization']) ? json_decode($order['first_item_customization'], true) : [];
                            $d_name = printflow_resolve_order_item_name($order['first_product_name'] ?? 'Order Item', $c_json, 'Order Item');
                            $preview_url = get_preview_image_for_order_ui($order, $d_name);
                            $timestamp_meta = $order['_display_timestamp_meta'] ?? printflow_customer_order_timestamp_meta($order);
                        ?>
                        <div class="ct-order-card" id="order-card-<?php echo $order['order_id']; ?>" data-order-id="<?php echo $order['order_id']; ?>" data-status="<?php echo htmlspecialchars($order['status']); ?>" onclick="openItemsModal(<?php echo $order['order_id']; ?>)">
                            <div class="card-top-row">
                                <span class="order-id-chip"><?php echo htmlspecialchars($order['order_code']); ?></span>
                                <div class="status-pill <?php echo $st_cls; ?>"><?php echo htmlspecialchars($order['status']); ?></div>
                            </div>

                            <div class="card-content">
                                <div class="img-preview-box"><img src="<?php echo htmlspecialchars($preview_url); ?>" alt="Preview" onerror="this.src='<?php echo BASE_URL; ?>/public/assets/images/services/default.png';"></div>
                                <div class="details-column">
                                    <h3 class="order-title"><?php echo htmlspecialchars($d_name); ?></h3>
                                    <div class="qty-tag"><?php echo max(1, (int)($order['total_quantity'] ?? 0)); ?> Items</div>
                                    <p class="timestamp-text"><?php echo htmlspecialchars($timestamp_meta['text']); ?></p>
                                    <?php if (strcasecmp((string)($order['status'] ?? ''), 'Rejected') === 0 && !empty($order['payment_rejection_reason'])): ?>
                                        <p class="rejected-reason-text">Rejected reason: <?php echo htmlspecialchars($order['payment_rejection_reason']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="pricing-column">
                                    <div class="mb-1">
                                        <?php if (in_array($order['status'], $HIDDEN_PRICE_STATUSES)): ?>
                                            <p class="hidden-price-msg">Quote is being finalized by our production team</p>
                                        <?php else: ?>
                                            <p class="final-price"><?php echo format_currency($order['total_amount']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-actions-inline" onclick="event.stopPropagation()">
                                        <a href="<?php echo BASE_URL; ?>/customer/chat.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-chat" style="padding: 0.45rem 0.85rem; font-size: 0.68rem;">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                            Message
                                        </a>
                                        <?php if (strtolower(trim($order['status'])) === 'to pay'): ?>
                                        <a href="<?php echo BASE_URL; ?>/customer/payment.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-main" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); padding: 0.45rem 0.85rem; font-size: 0.68rem; position: relative; z-index: 10; white-space: nowrap;">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            Pay Now
                                        </a>
                                        <?php endif; ?>
                                        <button type="button" class="action-button btn-main-blue" style="padding: 0.45rem 0.85rem; font-size: 0.68rem;" onclick="openItemsModal(<?php echo $order['order_id']; ?>, event)">View Details</button>
                                        <?php if (in_array($order['status'], ['Completed', 'To Rate', 'Rated'], true)): ?>
                                            <?php if (empty($order['rating_value'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/customer/rate_order.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-rate-order" style="padding: 0.45rem 0.85rem; font-size: 0.68rem;">
                                                    ★ Rate
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo BASE_URL; ?>/customer/reviews.php?order_id=<?php echo $order['order_id']; ?>" class="action-button btn-rate-order" style="padding: 0.45rem 0.85rem; font-size: 0.68rem;">
                                                    ★ Rated
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Image Lightbox Modal -->
        <div id="lightboxModal" class="fixed inset-0 z-[100001] hidden flex items-center justify-center p-4 bg-black/90" onclick="closeLightbox()">
            <img id="lightboxImg" class="max-w-full max-h-full shadow-2xl transition-transform duration-300 transform scale-95" src="" alt="Full Preview">
            <div class="absolute top-6 right-6 text-white cursor-pointer hover:text-red-400 transition-colors">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
        </div>


            <div class="mt-12">
                <?php echo get_pagination_links($current_page, $total_pages, ['tab' => $active_tab]); ?>
            </div>
    </div>
</div>

<?php 
// Inline helper for this specific page theme
function get_preview_image_for_order_ui($order, $display_name) {
    if (!empty($order['first_item_has_design']) && !empty($order['first_item_id'])) {
        return BASE_URL . "/public/serve_design.php?type=order_item&id=" . (int)$order['first_item_id'];
    }
    $product_img = "";
    $pn = trim($order['first_product_name'] ?? '');
    if ($pn && strtolower($display_name) === strtolower($pn)) {
        if (!empty($order['first_product_image'])) {
            $img = $order['first_product_image'];
            if ($img[0] !== '/' && strpos($img, 'http') === false) {
                if (file_exists(__DIR__ . '/../uploads/products/' . $img)) {
                    return BASE_URL . '/uploads/products/' . $img;
                }
            } else {
                return $img;
            }
        }
        $prod_id = (int)($order['first_product_id'] ?? 0);
        if ($prod_id > 0) {
            $img_base = __DIR__ . "/../public/images/products/product_" . $prod_id;
            if (file_exists($img_base . ".jpg")) return BASE_URL . "/public/images/products/product_" . $prod_id . ".jpg";
            if (file_exists($img_base . ".png")) return BASE_URL . "/public/images/products/product_" . $prod_id . ".png";
        }
    }
    return get_service_image_url($display_name);
}
?>

<script>
document.body.classList.add('orders-page');
const CUSTOMER_BASE_URL = <?php echo json_encode(BASE_URL); ?>;

// ── Highlight + scroll to a specific order card from notification ──
window.addEventListener('DOMContentLoaded', () => {
    if (!window.__pfOrderSuccessMessage) {
        try { window.__pfOrderSuccessMessage = localStorage.getItem('pf_order_success_msg') || ''; } catch (e) {}
    }
    if (window.__pfOrderSuccessMessage) {
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        window.scrollTo({ top: 0, behavior: 'auto' });
        renderOrderSuccessBanner(window.__pfOrderSuccessMessage);
        try { localStorage.removeItem('pf_order_success_msg'); } catch (e) {}
    }
    const params = new URLSearchParams(window.location.search);
    const highlightIdRaw = params.get('highlight');
    const highlightId = highlightIdRaw ? parseInt(highlightIdRaw, 10) : 0;
    if (highlightId > 0) {
        // Open the details modal immediately for maximum responsiveness
        if (typeof openItemsModal === 'function') {
            openItemsModal(highlightId);
        }

        const card = document.querySelector(`[data-order-id="${highlightId}"]`);
        if (card) {
            // Scroll into view with a slight delay so the page fully renders
            setTimeout(() => {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Apply a teal highlight pulse
                card.style.transition = 'box-shadow 0.3s, border-color 0.3s';
                card.style.borderColor = 'rgba(83, 197, 224, 0.85)';
                card.style.boxShadow = '0 0 0 3px rgba(83, 197, 224, 0.35), 0 16px 48px rgba(2, 12, 18, 0.5)';
                // Fade back after 2.5s
                setTimeout(() => {
                    card.style.borderColor = '';
                    card.style.boxShadow = '';
                }, 2500);
            }, 300);
        }
    }

    <?php if (isset($_SESSION['success'])): 
        $msg = $_SESSION['success'];
        unset($_SESSION['success']);
    ?>
    showSuccessModal(
        'Action Completed',
        '<?php echo addslashes($msg); ?>',
        'orders.php',
        'services.php',
        'Refresh List',
        'Back to Services'
    );
    <?php endif; ?>
});
</script>

<!-- Modal: Order Details -->
<div id="itemsModal" onclick="if(event.target === this) closeItemsModal()">
    <div class="im-panel">
        <div class="im-header">
            <div>
                <h2 class="im-title" id="imTitle">Order Details</h2>
                <p class="im-subtitle" id="imSubtitle"></p>
            </div>
            <button class="im-close" onclick="closeItemsModal()">✕</button>
        </div>
        <div class="im-body" id="imBody">
            <div class="flex flex-col items-center justify-center py-16">
                <div class="w-10 h-10 border-4 border-white/10 border-t-blue-400 rounded-full animate-spin"></div>
                <p class="mt-4 text-slate-400 font-bold text-sm">Gathering details...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cancellation -->
<div id="cancelModal" onclick="if(event.target === this) closeCancelModal()">
    <div class="cm-box">
        <h2 class="text-2xl font-black text-white mb-2">Cancel Order?</h2>
        <p class="text-slate-400 font-medium text-sm mb-6">Please tell us why you want to cancel. This action cannot be undone once confirmed.</p>
        
        <div class="space-y-2">
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Change of mind"><span class="font-bold">Change of mind</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Incorrect order details"><span class="font-bold">Incorrect details</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Found another provider"><span class="font-bold">Found cheaper elsewhere</span></label>
            <label class="cm-opt-label"><input type="radio" name="cancel_reason" value="Other"><span class="font-bold">Other reasons</span></label>
            <textarea id="cmOtherInput" class="w-full mt-3 p-4 bg-white/5 border-2 border-white/10 rounded-2xl hidden focus:border-blue-400 transition-all outline-none text-sm font-medium text-white" placeholder="Please specify your reason..."></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-8">
            <button class="btn-secondary" onclick="closeCancelModal()">Go Back</button>
            <button class="btn-danger" id="cmConfirmBtn" onclick="submitOrderCancellation()" disabled>Cancel Order</button>
        </div>
    </div>
</div>

<script>
function imBadge(val) {
    const s = String(val || '').toLowerCase();
    let cls = 'st-pending';
    if (s.includes('unpaid')) cls = 'st-unpaid';
    else if (s.includes('approved')) cls = 'st-approved';
    else if (s.includes('production') || s.includes('processing') || s.includes('printing')) cls = 'st-production';
    else if (s.includes('ready') || s.includes('pickup')) cls = 'st-ready';
    else if (s.includes('completed') || s.includes('rated') || s.includes('paid')) cls = 'st-completed';
    else if (s.includes('rejected') || s.includes('cancelled')) cls = 'st-cancelled';
    return `<span class="status-pill ${cls}">${escIM(val)}</span>`;
}

let currentOrderItemsRequest = null;
let currentOrderItemsRequestToken = 0;

function renderItemsModalLoadingState() {
    document.getElementById('imBody').innerHTML = `
        <div class="flex flex-col items-center justify-center py-16">
            <div class="w-10 h-10 border-4 border-slate-200 border-t-blue-500 rounded-full animate-spin"></div>
            <p class="mt-4 text-slate-500 font-bold text-sm">Gathering details...</p>
        </div>
    `;
}

function renderItemsModalErrorState(message) {
    document.getElementById('imBody').innerHTML = `<p class="text-red-500 font-bold text-center">${escIM(message || 'Unable to load order details right now.')}</p>`;
}

function openItemsModal(orderId, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const modal = document.getElementById('itemsModal');
    document.getElementById('imTitle').textContent = `Order`;
    document.getElementById('imSubtitle').textContent = 'Fetching data...';
    renderItemsModalLoadingState();
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    if (currentOrderItemsRequest) {
        currentOrderItemsRequest.abort();
    }

    currentOrderItemsRequestToken += 1;
    const requestToken = currentOrderItemsRequestToken;
    currentOrderItemsRequest = new AbortController();

    fetch(`${CUSTOMER_BASE_URL}/customer/get_order_items.php?id=${orderId}&_=${Date.now()}`, {
        signal: currentOrderItemsRequest.signal,
        cache: 'no-store',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(async r => {
        const raw = await r.text();
        let data = null;
        try {
            data = JSON.parse(raw);
        } catch (parseError) {
            if (!r.ok) {
                throw new Error(`Request failed with status ${r.status}`);
            }
            throw new Error('Invalid order details response');
        }
        if (!r.ok) {
            throw new Error(data && data.error ? data.error : `Request failed with status ${r.status}`);
        }
        return data;
    })
    .then(data => {
        if (requestToken !== currentOrderItemsRequestToken) {
            return;
        }
        if (data.error) {
            renderItemsModalErrorState(data.error);
            return;
        }

        document.getElementById('imSubtitle').textContent = data.order_code + ' • Order placed on ' + data.order_date;

        // Safety check for items
        const itemsList = Array.isArray(data.items) ? data.items : [];
        const rows = itemsList.map(item => {
            let specs = '';
            if (item.customization) {
                const specItems = Object.entries(item.customization)
                    .filter(([k,v]) => v && v !== 'No' && v !== 'None' && !['service_type','product_type','quantity','notes','design_upload','reference_upload'].includes(k))
                    .map(([k,v]) => `
                        <div class="im-spec-card">
                            <span class="im-spec-label capitalize-first">${k.replace(/_/g,' ')}</span>
                            <span class="im-spec-value">${escIM(v)}</span>
                        </div>
                    `)
                    .join('');
                if (specItems) specs = `<div class="im-spec-grid">${specItems}</div>`;
            }
            
            const design = item.has_design ? `<div class="block cursor-zoom-in" onclick="openLightbox('${item.design_url}')"><div style="font-size: 9px; font-weight: 900; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Final Design</div><img src="${item.design_url}" class="im-thumb hover:scale-105 transition-transform" alt="Design"></div>` : '';
            const reference = item.has_reference ? `<div class="block cursor-zoom-in" onclick="openLightbox('${item.reference_url}')"><div style="font-size: 9px; font-weight: 900; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Reference</div><img src="${item.reference_url}" class="im-thumb hover:scale-105 transition-transform" alt="Reference"></div>` : '';

            return `<tr>
                <td style="min-width: 250px;">
                    <div class="im-item-title">${escIM(item.product_name)}</div>
                    ${specs}
                    
                    ${design || reference ? `
                        <div style="margin-top: 1rem;">
                            <div class="im-meta-title">Uploaded Assets</div>
                            <div style="display: flex; gap: 0.75rem;">${design}${reference}</div>
                        </div>
                    ` : ''}
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <div class="im-qty-value">${item.quantity}</div>
                </td>
                <td style="text-align: right; vertical-align: middle; white-space: nowrap;">
                    <div class="im-total-value">${escIM(item.subtotal)}</div>
                </td>
            </tr>`;
        }).join('');

        document.getElementById('imBody').innerHTML = `
            <div class="im-dashboard">
                <div class="im-main">
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                        <table class="im-table">
                            <colgroup>
                                <col style="width:auto;">
                                <col style="width:110px;">
                                <col style="width:150px;">
                            </colgroup>
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th>Service Description</th>
                                    <th style="text-align: center;">Qty</th>
                                    <th style="text-align: right;">Total Price</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                    
                    ${data.notes ? `
                        <div class="im-note-card">
                            <div class="im-note-label">Customer Notes</div>
                            <div class="im-note-copy">"${escIM(data.notes)}"</div>
                        </div>
                    ` : ''}
                </div>

                <!-- Right: Metadata & Status Sidebar -->
                <div class="im-sidebar">
                    <div class="im-sec-card accent">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="im-label">Current status</div>
                                ${data.status === 'Rejected'
                                    ? `<div style="margin-top: 0.5rem;">${imBadge(data.status)}</div>`
                                    : `<div class="im-val" style="font-size: 1rem; color: #0f172a;">${data.status}</div>`}
                            </div>
                            ${data.status === 'Rejected' ? '' : `<div style="transform: scale(0.9); transform-origin: top right;">${imBadge(data.status)}</div>`}
                        </div>
                        <div class="im-label" style="margin-top: 16px;">Branch processing</div>
                        <div class="im-val">${escIM(data.branch_name)}</div>
                    </div>

                    <div class="im-sec-card">
                        <div class="im-label" style="margin-bottom: 12px;">Payment information</div>
                        <div class="space-y-4">
                            <div><div class="im-label" style="margin-bottom: 4px;">Method</div><div class="im-val">${escIM(data.payment_method)}</div></div>
                            <div><div class="im-label" style="margin-bottom: 4px;">Status</div><div>${imBadge(data.payment_status)}</div></div>
                            <div><div class="im-label" style="margin-bottom: 4px;">Total price</div><div class="im-val" style="font-size: 1rem; color: #0e7490;">${escIM(data.total_amount)}</div></div>
                        </div>
                    </div>

                    <div class="im-sec-card" style="border-left-color: #fbbf24;">
                        <div class="im-label" style="color: #f59e0b;">Estimated completion</div>
                        <div class="im-val" style="color: #d97706;">${escIM(data.estimated_comp || 'Gathering timeframe...')}</div>
                    </div>

                    <!-- Actions Area -->
                    <div class="mt-auto pt-4 space-y-3">
                        ${data.design_status === 'Revision Requested' ? `
                            <div class="im-reject-card">
                                <div class="im-reject-title">Revision requested</div>
                                <p class="im-reject-copy">${escIM(data.revision_reason || 'Please upload the corrected design, then submit it again for shop review.')}</p>
                                <div style="display:flex; flex-direction:column; gap:0.85rem; margin-top:1rem;">
                                    <label for="designReuploadInput-${data.order_id}" class="im-upload-picker">Choose updated design</label>
                                    <input type="file" id="designReuploadInput-${data.order_id}" style="display:none;" onchange="handleDesignFilePick(this, ${data.order_id})" accept="image/*,application/pdf">
                                    <div class="im-upload-filename" id="designReuploadFileName-${data.order_id}">No file selected</div>
                                    <button type="button" id="designReuploadSubmit-${data.order_id}" onclick="submitDesignReupload(${data.order_id}, '${data.csrf_token}')" class="im-primary-action" disabled>Submit Updated Design</button>
                                </div>
                            </div>
                        ` : ''}

                        ${data.status === 'Rejected' ? `
                            <div class="im-reject-card">
                                <div class="im-reject-title">Payment rejected</div>
                                <p class="im-reject-copy">${escIM(data.payment_rejection_reason || 'Your payment proof was rejected. Please review the reason and submit your payment proof again.')}</p>
                                <div style="display:flex; flex-direction:column; gap:0.85rem; margin-top:1rem;">
                                    <a href="${CUSTOMER_BASE_URL}/customer/payment.php?order_id=${data.order_id}" class="im-primary-action" style="display:flex; align-items:center; justify-content:center; text-decoration:none;">Resubmit Payment</a>
                                </div>
                            </div>
                        ` : ''}

                        ${['Completed', 'To Rate', 'Rated'].includes(data.status) ? (
                            data.rating_data
                                ? `<a href="${data.rating_data.view_url}" class="w-full py-3.5 bg-[rgba(249,115,22,0.1)] text-[#f97316] text-[11px] font-black border border-[rgba(249,115,22,0.4)] hover:bg-[#f97316] hover:text-white transition-all tracking-widest flex items-center justify-center gap-2 rounded-xl">★ VIEW YOUR REVIEW</a>`
                                : `<a href="${CUSTOMER_BASE_URL}/customer/rate_order.php?order_id=${data.order_id}" class="w-full py-3.5 bg-[rgba(249,115,22,0.1)] text-[#f97316] text-[11px] font-black border border-[rgba(249,115,22,0.4)] hover:bg-[#f97316] hover:text-white transition-all tracking-widest flex items-center justify-center gap-2 rounded-xl">★ RATE THIS ORDER</a>`
                        ) : ''}

                        ${data.can_cancel ? `
                            <button onclick="openCancelModal(${data.order_id}, '${data.csrf_token}')" class="w-full py-3 text-red-400 text-xs font-black border border-red-400/20 hover:bg-red-400/10 transition-all tracking-widest">Cancel order request</button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    })
    .catch((error) => {
        if (requestToken !== currentOrderItemsRequestToken) {
            return;
        }
        if (error && error.name === 'AbortError') {
            return;
        }
        renderItemsModalErrorState(error && error.message ? error.message : 'Connection error. Please try again.');
    })
    .finally(() => {
        if (requestToken === currentOrderItemsRequestToken) {
            currentOrderItemsRequest = null;
        }
    });
}

function closeItemsModal() {
    if (currentOrderItemsRequest) {
        currentOrderItemsRequest.abort();
        currentOrderItemsRequest = null;
    }
    document.getElementById('itemsModal').classList.remove('open');
    document.body.style.overflow = '';
}

// Cancellation Logic
let cancelOrderId = null, cancelCsrfToken = null;
function openCancelModal(id, token) {
    cancelOrderId = id; cancelCsrfToken = token;
    document.getElementById('cancelModal').classList.add('open');
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('open');
}

document.addEventListener('change', e => {
    if (e.target.name === 'cancel_reason') {
        document.querySelectorAll('.cm-opt-label').forEach(l => l.classList.remove('active'));
        e.target.closest('.cm-opt-label').classList.add('active');
        document.getElementById('cmOtherInput').classList.toggle('hidden', e.target.value !== 'Other');
        document.getElementById('cmConfirmBtn').disabled = false;
    }
});

function submitOrderCancellation() {
    const reasonEl = document.querySelector('input[name="cancel_reason"]:checked');
    if (!reasonEl) return;
    const reason = reasonEl.value, details = document.getElementById('cmOtherInput').value;
    if (reason === 'Other' && !details.trim()) { showToast("Please specify the reason."); return; }

    const btn = document.getElementById('cmConfirmBtn');
    btn.disabled = true; btn.textContent = 'Processing...';

    const fd = new FormData();
    fd.append('ajax', '1'); fd.append('order_id', cancelOrderId);
    fd.append('csrf_token', cancelCsrfToken); fd.append('reason', reason); fd.append('details', details);

    fetch(`${CUSTOMER_BASE_URL}/customer/cancel_order.php`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast("Order Cancelled Successfullly.");
            window.location.reload();
        } else {
            showToast(data.error || "Failed to cancel.");
            btn.disabled = false; btn.textContent = 'Cancel Order';
        }
    });
}

function triggerDesignReupload(orderId) { document.getElementById('designReuploadInput-' + orderId).click(); }
const pendingDesignReuploads = {};
function handleDesignFilePick(input, orderId) {
    const file = input && input.files && input.files[0] ? input.files[0] : null;
    const nameEl = document.getElementById('designReuploadFileName-' + orderId);
    const submitBtn = document.getElementById('designReuploadSubmit-' + orderId);
    if (!file) {
        delete pendingDesignReuploads[orderId];
        if (nameEl) nameEl.textContent = 'No file selected';
        if (submitBtn) submitBtn.disabled = true;
        return;
    }
    pendingDesignReuploads[orderId] = file;
    if (nameEl) nameEl.textContent = `Selected file: ${file.name}`;
    if (submitBtn) submitBtn.disabled = false;
}
function submitDesignReupload(orderId, csrfToken) {
    const file = pendingDesignReuploads[orderId];
    if (!file) {
        showToast('Please choose a file first.');
        return;
    }
    if (!confirm(`Upload "${file.name}"?`)) return;
    const submitBtn = document.getElementById('designReuploadSubmit-' + orderId);
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
    }

    const fd = new FormData();
    fd.append('order_id', orderId); fd.append('csrf_token', csrfToken); fd.append('design_file', file);
    
    fetch(`${CUSTOMER_BASE_URL}/customer/reupload_design_process.php`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) window.location.reload();
        else {
            showToast(res.error || 'Upload failed');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Resubmit Order';
            }
        }
    });
}

function openLightbox(url) {
    const lb = document.getElementById('lightboxModal');
    const img = document.getElementById('lightboxImg');
    img.src = url;
    lb.classList.remove('hidden');
    lb.classList.add('flex');
    setTimeout(() => { img.classList.remove('scale-95'); img.classList.add('scale-100'); }, 10);
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    const lb = document.getElementById('lightboxModal');
    const img = document.getElementById('lightboxImg');
    img.classList.remove('scale-100'); img.classList.add('scale-95');
    setTimeout(() => {
        lb.classList.remove('flex');
        lb.classList.add('hidden');
        document.body.style.overflow = '';
    }, 200);
}

function escIM(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderOrderSuccessBanner(message) {
    const host = document.getElementById('orderSuccessBannerHost');
    if (!host || !message) return;
    host.innerHTML = `
        <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 1rem 1.5rem; border-radius: 12px; margin: 0.75rem 0 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 600; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink: 0;">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>${escIM(message)}</span>
        </div>
    `;
}

async function refreshOrdersList() {
    try {
        const resp = await fetch(window.location.href, { headers: { 'X-Requested-With': 'fetch' } });
        const html = await resp.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nextDashboard = doc.querySelector('.unified-dashboard');
        const currentDashboard = document.querySelector('.unified-dashboard');
        if (nextDashboard && currentDashboard) {
            currentDashboard.innerHTML = nextDashboard.innerHTML;
        }
    } catch (e) {
        // Ignore refresh failures to avoid blocking UI.
    }
}

// ── Polling Logic (Updated for New Classes) ───────────────────
(function startOrdersPolling() {
    const activeTab = '<?php echo addslashes($active_tab); ?>';
    if (window.__ordersPollingInterval) return;

    function notifyNewOrder(orderId) {
        const lastNotified = parseInt(localStorage.getItem('pf_last_order_notice_id') || '0', 10);
        if (orderId <= lastNotified) return;
        localStorage.setItem('pf_last_order_notice_id', String(orderId));
        renderOrderSuccessBanner(`Order #${orderId} placed successfully! Our team will review and price your order shortly.`);
        if (typeof showToast === 'function') {
            showToast(`Order #${orderId} placed successfully! Our team will review and price your order shortly.`);
        }
    }

    const statusMap = {
        'Pending': 'st-pending', 'Pending Approval': 'st-pending', 'Pending Review': 'st-pending',
        'Approved': 'st-approved', 'To Pay': 'st-pending', 'To Verify': 'st-pending',
        'In Production': 'st-production', 'Processing': 'st-production', 'Printing': 'st-production',
        'Ready for Pickup': 'st-ready', 'To Receive': 'st-ready', 'Completed': 'st-completed',
        'To Rate': 'st-completed', 'Rated': 'st-completed', 'Rejected': 'st-cancelled', 'Cancelled': 'st-cancelled'
    };

    const statusToTab = {
        'Pending': 'pending',
        'Pending Approval': 'pending',
        'Pending Review': 'pending',
        'For Revision': 'pending',
        'Approved': 'approved',
        'To Pay': 'topay',
        'To Verify': 'toverify',
        'Downpayment Submitted': 'toverify',
        'Pending Verification': 'toverify',
        'In Production': 'production',
        'Processing': 'production',
        'Printing': 'production',
        'Ready for Pickup': 'pickup',
        'Rejected': 'rejected',
        'To Receive': 'pickup',
        'Completed': 'completed',
        'To Rate': 'torate',
        'Rated': 'torate',
        'Cancelled': 'cancelled'
    };

    function shouldReloadForNewOrder(order) {
        if (!order) return false;
        if (activeTab === 'all') return true;
        const mapped = statusToTab[order.status] || 'pending';
        return mapped === activeTab;
    }

    function doesOrderBelongToActiveTab(order) {
        if (!order) return false;
        if (activeTab === 'all') return true;
        const mapped = statusToTab[order.status] || 'pending';
        if (activeTab === 'completed') {
            return mapped === 'completed' || mapped === 'torate';
        }
        return mapped === activeTab;
    }

    function poll() {
        fetch(`${CUSTOMER_BASE_URL}/customer/api_customer_orders.php?_=${Date.now()}`, { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const cards = Array.from(document.querySelectorAll('.ct-order-card'));
            const existingIds = new Set(cards.map(card => parseInt(card.dataset.orderId, 10)).filter(id => !Number.isNaN(id)));
            const newOrder = data.orders.find(order => !existingIds.has(order.order_id));
            if (newOrder && shouldReloadForNewOrder(newOrder)) {
                notifyNewOrder(newOrder.order_id);
                refreshOrdersList();
                return;
            }

            const shouldRefreshForMissingVisibleOrder = data.orders.some(order => doesOrderBelongToActiveTab(order) && !existingIds.has(order.order_id));
            if (shouldRefreshForMissingVisibleOrder) {
                refreshOrdersList();
                return;
            }

            const shouldRefreshForMovedOrder = cards.some(card => {
                const orderId = parseInt(card.dataset.orderId, 10);
                if (Number.isNaN(orderId)) return false;
                const latest = data.orders.find(order => order.order_id === orderId);
                return latest ? !doesOrderBelongToActiveTab(latest) : false;
            });
            if (shouldRefreshForMovedOrder) {
                refreshOrdersList();
                return;
            }

            data.orders.forEach(order => {
                const card = document.getElementById('order-card-' + order.order_id);
                if (!card) return;
                const hadStatusChange = card.dataset.status !== order.status;
                if (!hadStatusChange) return;

                if (activeTab === 'all') {
                    refreshOrdersList();
                    return;
                }

                card.dataset.status = order.status;
                const pill = card.querySelector('.status-pill');
                if (pill) {
                    pill.textContent = order.status;
                    pill.className = 'status-pill ' + (statusMap[order.status] || 'st-pending');
                }
                const timestampEl = card.querySelector('.timestamp-text');
                if (timestampEl && order.display_timestamp_text) {
                    timestampEl.textContent = order.display_timestamp_text;
                }
                const priceEl = card.querySelector('.final-price');
                if (priceEl && order.total_amount) {
                    priceEl.textContent = '₱' + parseFloat(order.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
                }
                
                card.style.transition = 'background 0.3s';
                card.style.background = 'rgba(83, 197, 224, 0.12)'; // Brief teal highlight
                setTimeout(() => { card.style.background = ''; card.style.transition = ''; }, 1800);
            });
        });
    }
    poll();
    window.__ordersPollingInterval = setInterval(poll, 2000);
})();

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeItemsModal(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
