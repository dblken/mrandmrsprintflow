<?php
/**
 * Customer Orders Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();
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

// TikTok style tabs
$active_tab = $_GET['tab'] ?? 'all';

// Tab mappings to exact statuses
$tab_status_map = [
    'pending' => ['Pending', 'Pending Approval', 'For Revision'],
    'topay' => ['To Pay'],
    'production' => ['In Production', 'Processing', 'Printing'], // include legacy for safety
    'pickup' => ['Ready for Pickup'],
    'completed' => ['Completed']
];

// Build query
$sql = "SELECT o.*, 
        (SELECT GROUP_CONCAT(COALESCE(p.name, 'Custom Order') SEPARATOR ', ') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as item_names,
        (SELECT COALESCE(p.name, 'Custom Order') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_name,
        (SELECT p.product_id FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_product_id,
        (SELECT oi.customization_data FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization,
        (SELECT oi.order_item_id FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_id,
        (SELECT IF(oi.design_image IS NOT NULL AND oi.design_image != '', 1, 0) FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_has_design
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

// Pagination settings (restored)
$items_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

$sql .= " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$orders = db_query($sql, $types, $params);

$page_title = 'My Orders - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="/printflow/public/assets/css/chat.css">

<style>
/* TikTok Style Orders Nav */
.tt-tabs-wrapper {
    position: sticky; top: 72px; z-index: 40;
    background: #fff; border-bottom: 5px solid #f3f4f6;
    margin: -2rem -1rem 1.5rem -1rem; padding: 0 1rem;
    overflow-x: auto; white-space: nowrap; scrollbar-width: none;
}
.tt-tabs-wrapper::-webkit-scrollbar { display: none; }
.tt-tabs {
    display: flex; gap: 1.5rem; padding: 0.5rem 0 0 0;
}
.tt-tab {
    padding: 0.75rem 0.25rem; font-size: 0.9375rem; color: #64748b; font-weight: 500;
    border-bottom: 2px solid transparent; text-decoration: none; position: relative;
    transition: color 0.2s;
}
.tt-tab:hover { color: #1e293b; }
.tt-tab.active {
    color: #111827; font-weight: 700;
}
.tt-tab.active::after {
    content: ''; position: absolute; bottom: -2px; left: 0; right: 0;
    height: 2px; background: #000; border-radius: 2px 2px 0 0;
}

/* TikTok Style Empty State */
.tt-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 4rem 1rem; text-align: center;
}
.tt-empty-icon {
    width: 120px; height: 120px; margin-bottom: 1rem; opacity: 0.7;
}
.tt-empty-title {
    font-size: 1.1rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem;
}
.tt-empty-sub {
    font-size: 0.9rem; color: #6b7280; font-weight: 500;
}

@media (min-width: 768px) {
    .tt-tabs-wrapper { margin: -1rem 0 2rem 0; padding: 0; border-bottom: 1px solid #e5e7eb; }
}
</style>

<div class="min-h-screen py-4 md:py-8 bg-gray-50 md:bg-transparent">
    <div class="container mx-auto" style="max-width:1100px;">

        <!-- Stats Cards (Transferred from Dashboard) -->
        <div class="ct-stats" style="margin-bottom: 2rem; margin-top: 1rem;">
            <div class="ct-stat-card yellow">
                <p class="ct-stat-label">Pending</p>
                <p class="ct-stat-value"><?php echo $pending_orders; ?></p>
            </div>
            <div class="ct-stat-card blue">
                <p class="ct-stat-label">Processing</p>
                <p class="ct-stat-value"><?php echo $processing_orders; ?></p>
            </div>
            <div class="ct-stat-card green">
                <p class="ct-stat-label">Ready for Pickup</p>
                <p class="ct-stat-value"><?php echo $ready_orders; ?></p>
            </div>
            <div class="ct-stat-card gray">
                <p class="ct-stat-label">Total Orders</p>
                <p class="ct-stat-value"><?php echo $total_orders; ?></p>
            </div>
        </div>

        <!-- TikTok Tabs -->
        <div class="tt-tabs-wrapper">
            <div class="tt-tabs">
                <a href="?tab=all" class="tt-tab <?php echo $active_tab === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?tab=pending" class="tt-tab <?php echo $active_tab === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?tab=topay" class="tt-tab <?php echo $active_tab === 'topay' ? 'active' : ''; ?>">To pay</a>
                <a href="?tab=production" class="tt-tab <?php echo $active_tab === 'production' ? 'active' : ''; ?>">In Production</a>
                <a href="?tab=pickup" class="tt-tab <?php echo $active_tab === 'pickup' ? 'active' : ''; ?>">Ready for pickup</a>
                <a href="?tab=completed" class="tt-tab <?php echo $active_tab === 'completed' ? 'active' : ''; ?>">Completed</a>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="tt-empty">
                <!-- SVG Shopping Bag Empty State mimicking TikTok -->
                <svg class="tt-empty-icon" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M70 70 L60 140 L130 140 L140 70 Z" stroke="#9ca3af" stroke-width="4" stroke-linejoin="round"/>
                    <path d="M85 70 V55 C85 45 115 45 115 55 V70" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <path d="M85 90 C85 105 115 105 115 90" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <path d="M50 40 L65 55" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <path d="M120 30 L135 45 M135 30 L120 45" stroke="#9ca3af" stroke-width="4" stroke-linecap="round"/>
                    <circle cx="140" cy="50" r="4" fill="#9ca3af"/>
                    <circle cx="55" cy="80" r="3" fill="#9ca3af"/>
                    <path d="M145 90 C155 90 155 100 145 100 C135 100 135 110 145 110" stroke="#9ca3af" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M45 100 C35 100 35 110 45 110 C55 110 55 120 45 120" stroke="#9ca3af" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="tt-empty-title">No orders yet</div>
                <div class="tt-empty-sub">Start shopping!</div>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $index => $order): ?>
                <div class="ct-order-card" id="order-card-<?php echo $order['order_id']; ?>">
                    <!-- Card Top: product image + info + price -->
                    <div style="display:flex; gap:14px; align-items:flex-start; padding-bottom:12px; border-bottom:1px solid #f1f5f9;">
                        <!-- Product Image -->
                        <div style="flex-shrink:0;">
                            <?php 
                            // Determine item display name and base category first
                            $display_name = !empty($order['first_product_name']) ? $order['first_product_name'] : 'Order Items';
                            $service_category = '';
                            if ($display_name === 'Custom Order' && !empty($order['first_item_customization'])) {
                                $c_json = json_decode($order['first_item_customization'], true);
                                if (!empty($c_json['service_type'])) {
                                    $display_name = $c_json['service_type'];
                                    $service_category = $c_json['service_type'];
                                    if (!empty($c_json['product_type'])) {
                                        $display_name .= " (" . $c_json['product_type'] . ")";
                                    }
                                }
                            }

                            // Determine image or design
                            $show_design = !empty($order['first_item_has_design']) && !empty($order['first_item_id']);
                            $prod_id = (int)($order['first_product_id'] ?? 0);
                            $product_img = "";
                            
                            // 1. Try to fetch photo_path from database
                            if (!$show_design && $prod_id > 0) {
                                $prod_data = db_query("SELECT photo_path FROM products WHERE product_id = ?", 'i', [$prod_id]);
                                if (!empty($prod_data) && !empty($prod_data[0]['photo_path'])) {
                                    $product_img = $prod_data[0]['photo_path'];
                                }
                            }
                            
                            // 2. Check explicit product ID image (file-based fallback)
                            if (!$show_design && empty($product_img) && $prod_id > 0) {
                                $img_base = "../public/images/products/product_" . $prod_id;
                                if (file_exists($img_base . ".jpg")) {
                                    $product_img = "/printflow/public/images/products/product_" . $prod_id . ".jpg";
                                } elseif (file_exists($img_base . ".png")) {
                                    $product_img = "/printflow/public/images/products/product_" . $prod_id . ".png";
                                }
                            }

                            // 3. Fallback based on category/service_type for Service Orders without specific product
                            if (!$show_design && empty($product_img)) {
                                $cat_lower = strtolower($service_category ?: $display_name);
                                if (strpos($cat_lower, 'reflectorized') !== false || strpos($cat_lower, 'signage') !== false) {
                                    $product_img = "/printflow/public/images/products/signage.jpg";
                                } elseif (strpos($cat_lower, 'tarpaulin') !== false) {
                                    $product_img = "/printflow/public/images/products/product_41.jpg";
                                } elseif (strpos($cat_lower, 'sintraboard') !== false || strpos($cat_lower, 'standee') !== false) {
                                    $product_img = "/printflow/public/images/services/Sintraboard Standees.jpg";
                                } elseif (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) {
                                    $product_img = "/printflow/public/images/products/product_31.jpg";
                                } elseif (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) {
                                    if (strpos($cat_lower, 'glass') !== false || strpos($cat_lower, 'frosted') !== false) {
                                        $product_img = "/printflow/public/images/products/Glass Stickers  Wall  Frosted Stickers.png";
                                    } else {
                                        $product_img = "/printflow/public/images/products/product_21.jpg";
                                    }
                                } elseif (strpos($cat_lower, 'souvenir') !== false) {
                                    // Default image for souvenirs (or general fallback)
                                    $product_img = "/printflow/public/assets/images/icon-192.png";
                                }
                            }
                            ?>

                            <?php if ($show_design): ?>
                                <a href="/printflow/public/serve_design.php?type=order_item&id=<?php echo (int)$order['first_item_id']; ?>" target="_blank" style="display:block; width:72px; height:72px; border-radius:12px; overflow:hidden; border:2px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                                    <img src="/printflow/public/serve_design.php?type=order_item&id=<?php echo (int)$order['first_item_id']; ?>" style="width:100%; height:100%; object-fit:cover;" alt="Product Image" onerror="this.src='/printflow/public/assets/images/placeholder.png';">
                                </a>
                            <?php elseif (!empty($product_img)): ?>
                                <div style="width:72px; height:72px; border-radius:12px; overflow:hidden; border:2px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.1); background:#f8fafc; display:flex; align-items:center; justify-content:center;">
                                    <img src="<?php echo $product_img; ?>" style="max-width:100%; max-height:100%; object-fit:contain;" alt="Product Image">
                                </div>
                            <?php else: ?>
                                <!-- Universal Absolute Fallback (Printflow Purple Logo) if all else fails -->
                                <div style="width:72px; height:72px; border-radius:12px; overflow:hidden; border:2px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.1); background:#f8fafc; display:flex; align-items:center; justify-content:center;">
                                    <img src="/printflow/public/assets/images/icon-192.png" style="width:70%; height:70%; object-fit:contain; opacity:0.8;" alt="Printflow Logo">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Order Info -->
                        <div style="flex:1; min-width:0;">
                            <!-- Bold product name -->
                            <div style="font-size:1rem; font-weight:800; color:#1e293b; line-height:1.3; margin-bottom:3px;">
                                <?php echo htmlspecialchars($display_name); ?>
                                <?php 
                                // Count additional items
                                if (!empty($order['item_names'])) {
                                    $item_count_arr = explode(', ', $order['item_names']);
                                    if (count($item_count_arr) > 1): ?>
                                        <span style="font-size:0.75rem; color:#94a3b8; font-weight:500;"> +<?php echo count($item_count_arr) - 1; ?> more</span>
                                    <?php endif;
                                }
                                ?>
                            </div>
                            <!-- Order ID -->
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span style="font-size:0.8rem; color:#64748b; font-weight:600;">Order #<?php echo $order['order_id']; ?></span>
                                <?php 
                                $unread = get_unread_chat_count($order['order_id'], 'Customer');
                                if ($unread > 0): 
                                ?>
                                    <span style="background:#ef4444; color:white; border-radius:50%; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; animation:pulse 2s infinite;" title="<?php echo $unread; ?> new messages"><?php echo $unread; ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- Date -->
                            <p style="font-size:0.73rem; color:#94a3b8; font-weight:500; margin-top:2px; margin-bottom:0;"><?php echo format_datetime($order['order_date']); ?></p>
                        </div>

                        <!-- Price + Status -->
                        <div style="text-align:right; flex-shrink:0;">
                            <p class="ct-order-amount" style="font-size:1.15rem; font-weight:800; color:#4f46e5; margin:0;"><?php echo format_currency($order['total_amount']); ?></p>
                            <div style="margin-top:4px;"><?php echo status_badge($order['status'], 'order'); ?></div>
                        </div>
                    </div>

                    <!-- Card Bottom: See More + Message Shop -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                        <button class="ct-toggle-btn" onclick="toggleOrderDetails(<?php echo $order['order_id']; ?>)" style="margin-top:0;">
                            <span>See More</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <button type="button" onclick="openOrderChat(<?php echo $order['order_id']; ?>, 'PrintFlow Support')" style="background:#4F46E5; color:white; border:none; padding:8px 16px; border-radius:8px; font-weight:700; display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            💬 Message Shop
                        </button>
                    </div>

                    <!-- Expandable Details -->
                    <div class="ct-order-meta hidden" id="order-meta-<?php echo $order['order_id']; ?>">
                        <div>
                            <p class="ct-order-meta-label">Payment Status</p>
                            <p class="ct-order-meta-value"><?php echo status_badge($order['payment_status'], 'payment'); ?></p>
                        </div>
                        <div>
                            <p class="ct-order-meta-label">Estimated Completion</p>
                            <p class="ct-order-meta-value"><?php echo ($order['estimated_completion'] ?? null) ? format_date($order['estimated_completion']) : 'TBD'; ?></p>
                        </div>
                        <div style="display:flex; align-items:center;">
                            <button
                                onclick="openItemsModal(<?php echo $order['order_id']; ?>)"
                                class="ct-view-link"
                                style="background:none;border:none;cursor:pointer;padding:0;font-family:inherit;"
                            >View Details →</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <div class="mt-8">
                <?php echo get_pagination_links($current_page, $total_pages, ['tab' => $active_tab]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle individual order details
function toggleOrderDetails(orderId) {
    const meta = document.getElementById('order-meta-' + orderId);
    const btn = document.querySelector(`#order-card-${orderId} .ct-toggle-btn`);
    const span = btn.querySelector('span');
    const svg = btn.querySelector('svg');
    
    if (meta.classList.contains('hidden')) {
        meta.classList.remove('hidden');
        span.textContent = 'See Less';
        svg.style.transform = 'rotate(180deg)';
    } else {
        meta.classList.add('hidden');
        span.textContent = 'See More';
        svg.style.transform = 'rotate(0deg)';
    }
}

// Trigger success modal if success message exists
window.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_SESSION['success'])): 
        $msg = $_SESSION['success'];
        unset($_SESSION['success']);
    ?>
    showSuccessModal(
        '✅ Action Completed',
        '<?php echo addslashes($msg); ?>',
        '#', // primary doesn't matter much here, maybe just refresh
        'services.php',
        'Close',
        'Go to Dashboard'
    );
    <?php endif; ?>
});
</script>

<!-- ══ Order Items Modal ══ -->
<style>
/* Base modal */
#itemsModal {
    position:fixed; inset:0; z-index:9999999;
    display:flex; align-items:center; justify-content:center;
    padding:16px;
    opacity:0; pointer-events:none;
    transition:opacity 0.25s ease;
}
#itemsModal.open { opacity:1; pointer-events:all; }

.im-backdrop {
    position:absolute; inset:0;
    background:rgba(0,0,0,0.45);
}
.im-panel {
    position:relative; z-index:1;
    background:#fff; border-radius:20px;
    width:100%;
    max-width:560px;
    max-height:88vh; 
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    opacity:0; transform:translateY(22px) scale(0.97);
    transition:
        max-width 0.4s cubic-bezier(.34,1.2,.64,1),
        transform 0.32s cubic-bezier(.34,1.56,.64,1),
        opacity 0.25s ease;
}
#itemsModal.open .im-panel { opacity:1; transform:translateY(0) scale(1); }
/* Expanded state – wider panel */
#itemsModal.expanded .im-panel { max-width:780px; }

.im-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 24px 16px;
    border-bottom:1px solid #f1f5f9;
    background:#fff;
    border-radius:20px 20px 0 0; 
    z-index:2;
    gap:12px;
    flex-shrink: 0;
}
.im-title { font-size:1.1rem; font-weight:800; color:#1e293b; flex:1; min-width:0; }
.im-subtitle { font-size:0.75rem; color:#94a3b8; margin-top:2px; }

.im-close {
    width:32px; height:32px; border-radius:50%; flex-shrink:0;
    border:none; background:#f1f5f9; color:#64748b;
    cursor:pointer; font-size:1rem;
    display:flex; align-items:center; justify-content:center;
    transition:background 0.15s;
}
.im-close:hover { background:#e2e8f0; }

.im-body { 
    padding:20px 24px 24px; 
    overflow-y: auto;
    flex: 1;
}

/* Custom scrollbar for im-body */
.im-body::-webkit-scrollbar {
    width: 6px;
}
.im-body::-webkit-scrollbar-track {
    background: transparent;
}
.im-body::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.im-body::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

/* Items table */
.im-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.im-table th {
    text-align:left; padding:8px 10px;
    font-size:0.65rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.06em; color:#94a3b8;
    border-bottom:2px solid #e2e8f0;
}
.im-table td { padding:11px 10px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
.im-table tbody tr:last-child td { border-bottom:none; }
.im-total-row { border-top:2px solid #e2e8f0 !important; font-weight:800; }

/* Expand section */
.im-expand-btn {
    display:flex; align-items:center; justify-content:center; gap:6px;
    width:100%; margin-top:16px; padding:10px;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
    font-size:13px; font-weight:700; color:#6366f1;
    cursor:pointer; transition:background 0.15s, border-color 0.15s;
}
.im-expand-btn:hover { background:#eef2ff; border-color:#c7d2fe; }
.im-expand-icon { transition:transform 0.3s ease; font-size:11px; }
.im-expand-btn.active .im-expand-icon { transform:rotate(180deg); }

/* Full details section – slide open */
.im-full-details {
    overflow:hidden;
    max-height:0;
    transition:max-height 0.5s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease;
    opacity:0;
}
.im-full-details.open { max-height:2000px; opacity:1; }
.im-full-details-inner { padding-top:20px; border-top:1px solid #f1f5f9; margin-top:18px; }

/* Info grid */
.im-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
@media (max-width:500px) { .im-info-grid { grid-template-columns:1fr; } }
.im-info-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; }
.im-info-label { font-size:0.7rem; color:#94a3b8; margin-bottom:4px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
.im-info-value { font-size:13.5px; font-weight:700; color:#1e293b; }

/* Notes box */
.im-notes {
    margin-bottom:16px; padding:14px 16px;
    background:linear-gradient(135deg,#fffbeb,#fef3c7);
    border:1px solid #fde68a; border-radius:12px;
    max-height: 150px; overflow-y: auto;
}
.im-notes-title { font-size:12px; font-weight:800; color:#92400e; margin-bottom:6px; }
.im-notes-text { font-size:13px; color:#b45309; line-height:1.6; overflow-wrap: anywhere; word-break: break-word; }

/* Design thumb */
.im-design-thumb { max-width:100px; border-radius:8px; border:2px solid #e2e8f0; display:block; margin-top:6px; cursor:zoom-in; transition:transform 0.2s; }
.im-design-thumb:hover { transform:scale(1.05); }

/* Custom chips */
.im-chips { display:flex; flex-wrap:wrap; gap:5px; margin-top:5px; }
.im-chip { 
    background:#e0e7ff; color:#4338ca; border-radius:99px; padding:2px 9px; 
    font-size:11px; font-weight:600; 
    overflow-wrap: anywhere; word-break: break-word; white-space: normal;
}

/* Status badges */
.im-badge { display:inline-block; padding:2px 10px; border-radius:99px; font-size:11px; font-weight:700; }
.im-badge-green { background:#d1fae5; color:#065f46; }
.im-badge-yellow { background:#fef3c7; color:#92400e; }
.im-badge-red { background:#fee2e2; color:#991b1b; }
.im-badge-blue { background:#dbeafe; color:#1e40af; }
.im-badge-gray { background:#f3f4f6; color:#374151; }
.im-badge-purple { background:#ede9fe; color:#5b21b6; }

/* Loader */
.im-loader { text-align:center; padding:48px 0; }
.im-spinner {
    width:36px; height:36px; border-radius:50%;
    border:3px solid #e2e8f0; border-top-color:#6366f1;
    animation:im-spin 0.7s linear infinite; margin:0 auto 10px;
}
@keyframes im-spin { to { transform:rotate(360deg); } }

/* ── Cancel Order Modal ─────────────────────────────────── */
#cancelModal {
    position:fixed; inset:0; z-index:10000000;
    display:flex; align-items:center; justify-content:center;
    padding:16px; opacity:0; pointer-events:none;
    transition:opacity 0.2s ease;
}
#cancelModal.open { opacity:1; pointer-events:all; }
.cm-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.45); }
.cm-panel {
    position:relative; z-index:1; background:#fff; border-radius:20px;
    width:100%; max-width:400px; padding:24px;
    box-shadow:0 20px 50px rgba(0,0,0,0.3);
    transform:scale(0.95); transition:transform 0.2s;
}
#cancelModal.open .cm-panel { transform:scale(1); }
.cm-title { font-size:1.25rem; font-weight:800; color:#0f172a; margin-bottom:8px; }
.cm-sub { font-size:0.9rem; color:#64748b; margin-bottom:20px; line-height:1.5; }

.cm-options { display:flex; flex-direction:column; gap:10px; margin-bottom:20px; }
.cm-opt {
    display:flex; align-items:center; gap:10px; padding:12px 14px;
    border:1px solid #e2e8f0; border-radius:12px; cursor:pointer;
    transition:background 0.1s, border-color 0.1s;
}
.cm-opt:hover { background:#f8fafc; }
.cm-opt.active { background:#f0f7ff; border-color:#3b82f6; }
.cm-opt input { display:none; }
.cm-opt-text { font-size:14px; font-weight:600; color:#1e293b; }

#cmOtherInput {
    width:100%; margin-top:10px; padding:10px;
    border:1px solid #e2e8f0; border-radius:8px; font-size:13px;
    display:none;
}
.cm-btns { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.cm-btn-cancel {
    padding:12px; border-radius:12px; background:#f1f5f9; color:#64748b;
    font-weight:700; font-size:14px; border:none; cursor:pointer;
}
.cm-btn-confirm {
    padding:12px; border-radius:12px; background:#ef4444; color:#fff;
    font-weight:700; font-size:14px; border:none; cursor:pointer;
    box-shadow:0 4px 12px rgba(239,68,68,0.25);
}
.cm-btn-confirm:disabled { opacity:0.5; cursor:not-allowed; }
</style>

<div id="itemsModal" role="dialog" aria-modal="true">
    <div class="im-backdrop" onclick="closeItemsModal()"></div>
    <div class="im-panel">
        <div class="im-header">
            <div>
                <div class="im-title" id="imTitle">Order Items</div>
                <div class="im-subtitle" id="imSubtitle"></div>
            </div>
            <button class="im-close" onclick="closeItemsModal()">✕</button>
        </div>
        <div class="im-body" id="imBody">
            <div class="im-loader"><div class="im-spinner"></div></div>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<div id="cancelModal" role="dialog" aria-modal="true">
    <div class="cm-backdrop" onclick="closeCancelModal()"></div>
    <div class="cm-panel">
        <div class="cm-title">Cancel Order</div>
        <p class="cm-sub">Please select a reason for cancelling your order. This helps us improve our service.</p>
        
        <div class="cm-options">
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Change of mind"><span class="cm-opt-text">Change of mind</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Incorrect order details"><span class="cm-opt-text">Incorrect order details</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Budget concerns"><span class="cm-opt-text">Budget concerns</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Found another provider"><span class="cm-opt-text">Found another provider</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Urgent order / Long processing time"><span class="cm-opt-text">Urgent order / Long processing time</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Payment issue"><span class="cm-opt-text">Payment issue</span></label>
            <label class="cm-opt"><input type="radio" name="cancel_reason" value="Other"><span class="cm-opt-text">Other</span></label>
            <textarea id="cmOtherInput" placeholder="Please specify your reason..."></textarea>
        </div>

        <div class="cm-btns">
            <button class="cm-btn-cancel" onclick="closeCancelModal()">Back</button>
            <button class="cm-btn-confirm" id="cmConfirmBtn" onclick="submitOrderCancellation()">Confirm Cancellation</button>
        </div>
    </div>
</div>

<script>
let imExpanded = false;

function imBadge(val) {
    const m = {
        'Completed':'im-badge-green','Pending':'im-badge-yellow',
        'Processing':'im-badge-blue',
        'In Production':'im-badge-blue','Printing':'im-badge-blue',
        'Ready for Pickup':'im-badge-purple','Cancelled':'im-badge-red',
        'For Revision':'im-badge-blue','Paid':'im-badge-green',
        'Unpaid':'im-badge-gray','Partial':'im-badge-yellow',
    };
    return `<span class="im-badge ${m[val]||'im-badge-gray'}">${escIM(val)}</span>`;
}

function openItemsModal(orderId) {
    imExpanded = false;
    const modal = document.getElementById('itemsModal');
    modal.classList.remove('expanded');
    document.getElementById('imTitle').textContent = `Order #${orderId}`;
    document.getElementById('imSubtitle').textContent = '';
    document.getElementById('imBody').innerHTML =
        `<div class="im-loader"><div class="im-spinner"></div><div style="color:#94a3b8;font-size:13px;margin-top:6px;">Loading…</div></div>`;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(`/printflow/customer/get_order_items.php?id=${orderId}`)
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            document.getElementById('imBody').innerHTML =
                `<p style="color:#ef4444;font-size:13px;">${escIM(data.error)}</p>`;
            return;
        }

        document.getElementById('imSubtitle').textContent = data.order_date;

        // ── Items table rows ──────────────────────────────────
        const rows = data.items.map(item => {
            let chips = '';
            let itemNotes = '';
            if (item.customization && Object.keys(item.customization).length) {
                let chipItems = '';
                Object.entries(item.customization).forEach(([k, v]) => {
                    if (!v || v === 'No' || v === 'None' || v === 'none') return;
                    
                    // Specific exclusions for Reflectorized Temporary Plates
                    const isReflectorized = (item.category || '').toLowerCase().includes('reflectorized') || 
                                           (item.customization.service_type || '').toLowerCase().includes('reflectorized');
                    const isTempPlate = (item.customization.product_type || '').includes('Temporary Plate');
                    const isGatePass = (item.customization.product_type || '').includes('Gate Pass');
                    const exclusions = ['unit', 'bg_color', 'text_color', 'arrow_direction', 'quantity', 'material_type', 'shape', 'with_border', 'rounded_corners', 'with_numbering', 'install_service', 'need_proof', 'reflective_color', 'inches', 'quantity_gatepass', 'dimensions', 'product_type', 'service_type'];
                    const gpOnlyExclusions = ['bg_color', 'text_color', 'reflective_color', 'text_content', 'arrow_direction', 'with_numbering', 'install_service', 'need_proof', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];
                    
                    if (isReflectorized && isTempPlate && (exclusions.includes(k) || v === 'inches')) return;
                    if (isReflectorized && isGatePass && (gpOnlyExclusions.includes(k) || k === 'quantity_gatepass')) return;

                    const label = k.replace(/_/g, ' ');
                    
                    // Skip if item note is same as global order note
                    if (k.toLowerCase() === 'notes' && v === data.notes) return;
                    if (k.toLowerCase() === 'notes' || k.toLowerCase().includes('description')) {
                        itemNotes += `
                            <div style="margin-top:8px; padding:10px; background:#fffbeb; border:1px solid #fef3c7; border-radius:8px;">
                                <div style="font-size:10px; font-weight:800; color:#92400e; text-transform:uppercase; margin-bottom:4px;">📝 ${escIM(label)}</div>
                                <div style="font-size:12px; color:#b45309; line-height:1.4; max-height:100px; overflow-y:auto; overflow-wrap:anywhere; word-break:break-word;">
                                    ${escIM(String(v)).replace(/\n/g,'<br>')}
                                </div>
                            </div>`;
                    } else {
                        chipItems += `<span class="im-chip">${escIM(label)}: ${escIM(String(v))}</span>`;
                    }
                });
                if (chipItems) chips = `<div class="im-chips">${chipItems}</div>`;
            }
            const design = item.has_design
                ? `<div style="margin-top:8px;">
                      <div style="font-size:9px;color:#94a3b8;font-weight:700;margin-bottom:3px;text-transform:uppercase;">Final Design</div>
                      <a href="${escIM(item.design_url)}" target="_blank">
                        <img src="${escIM(item.design_url)}" class="im-design-thumb"
                             alt="Design"
                             onerror="this.outerHTML='<span style=\\'color:#9ca3af;font-size:11px;\\'>⚠️ No preview</span>'">
                      </a>
                   </div>`
                : `<div style="font-size:11px;color:#9ca3af;margin-top:8px;">No design file</div>`;

            const reference = item.has_reference
                ? `<div style="margin-top:8px;">
                      <div style="font-size:9px;color:#94a3b8;font-weight:700;margin-bottom:3px;text-transform:uppercase;">Reference Image</div>
                      <a href="${escIM(item.reference_url)}" target="_blank">
                        <img src="${escIM(item.reference_url)}" class="im-design-thumb"
                             alt="Reference"
                             onerror="this.outerHTML='<span style=\\'color:#9ca3af;font-size:11px;\\'>⚠️ No preview</span>'">
                      </a>
                   </div>`
                : '';

            return `<tr>
                <td>
                    <div style="font-weight:700;color:#1e293b;">${escIM(item.product_name)}</div>
                    ${item.category ? `<div style="font-size:11px;color:#9ca3af;">${escIM(item.category)}</div>` : ''}
                    ${chips}
                    ${itemNotes}
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        ${design}
                        ${reference}
                    </div>
                </td>
                <td style="text-align:center;">${item.quantity}</td>
                <td>${escIM(item.unit_price)}</td>
                <td style="font-weight:700;color:#4f46e5;">${escIM(item.subtotal)}</td>
            </tr>`;
        }).join('');

        // ── Full details (hidden initially) ──────────────────
        let notesHTML = '';
        if (data.notes) {
            notesHTML = `<div class="im-notes">
                <div class="im-notes-title">📝 Your Order Notes</div>
                <div class="im-notes-text">${escIM(data.notes).replace(/\n/g,'<br>')}</div>
            </div>`;
        }

        let cancelHTML = '';
        if (data.status === 'Cancelled' && (data.cancelled_by || data.cancel_reason)) {
            cancelHTML = `<div style="margin-top:12px;padding:12px;background:#fef2f2;border:1px solid #fee2e2;border-radius:10px;font-size:12px;color:#b91c1c;">
                <b>Cancelled by:</b> ${escIM(data.cancelled_by)}<br>
                <b>Reason:</b> ${escIM(data.cancel_reason)}
                ${data.cancelled_at ? `<br><b>Date:</b> ${escIM(data.cancelled_at)}` : ''}
            </div>`;
        }

        let revisionHTML = '';
        if (data.status === 'For Revision' && data.revision_reason) {
            revisionHTML = `<div style="margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #dbeafe;border-radius:10px;font-size:12px;color:#1e40af;">
                <b>Revision needed:</b> ${escIM(data.revision_reason)}
            </div>`;
        }

        document.getElementById('imBody').innerHTML = `
            <table class="im-table">
                <thead><tr>
                    <th>Product</th>
                    <th style="text-align:center;">Qty</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr></thead>
                <tbody>${rows}</tbody>
                <tfoot><tr>
                    <td colspan="3" style="text-align:right;padding:12px 10px;" class="im-total-row">Total</td>
                    <td style="padding:12px 10px;color:#4f46e5;font-size:15px;" class="im-total-row">${escIM(data.total_amount)}</td>
                </tr></tfoot>
            </table>

            <!-- Expand button -->
            <button class="im-expand-btn" id="imExpandBtn" onclick="toggleFullDetails()">
                <span>View Full Order Details</span>
                <span class="im-expand-icon">▼</span>
            </button>

            <!-- Full details panel (hidden) -->
            <div class="im-full-details" id="imFullDetails">
                <div class="im-full-details-inner">
                    ${notesHTML}
                    <div class="im-info-grid">
                        <div class="im-info-card">
                            <div class="im-info-label">Order Status</div>
                            <div class="im-info-value">${imBadge(data.status)}</div>
                        </div>
                        <div class="im-info-card">
                            <div class="im-info-label">Payment</div>
                            <div class="im-info-value">${imBadge(data.payment_status)}</div>
                        </div>
                        <div class="im-info-card">
                            <div class="im-info-label">Estimated Completion</div>
                            <div class="im-info-value">${escIM(data.estimated_comp)}</div>
                        </div>
                        <div class="im-info-card">
                            <div class="im-info-label">Date Placed</div>
                            <div class="im-info-value">${escIM(data.order_date)}</div>
                        </div>
                    </div>
                    ${cancelHTML}
                    
                    <!-- Design Review Status for Customer -->
                    <div style="margin-top:16px; padding:15px; border-radius:12px; background:#fff; border:1px solid #e2e8f0; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <div style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:10px;">Design Review Status</div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:13.5px; font-weight:600; color:#334155;">Status:</span>
                            ${imBadge(data.design_status || 'Pending')}
                        </div>
                        
                        ${data.design_status === 'Revision Requested' ? `
                            <div style="margin-top:12px; padding:12px; background:#eff6ff; border:1px solid #dbeafe; border-radius:10px;">
                                <div style="font-weight:700; color:#2563eb; font-size:12px; margin-bottom:4px;">Revision Reason:</div>
                                <div style="font-size:12px; color:#1e40af; line-height:1.4;">${escIM(data.revision_reason)}</div>
                            </div>
                            <div style="margin-top:14px;">
                                <button onclick="triggerDesignReupload(${data.order_id})" style="width:100%; padding:12px; background:#4f46e5; color:#fff; border:none; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                                    <span>📤 Re-upload New Design</span>
                                </button>
                                <input type="file" id="designReuploadInput-${data.order_id}" style="display:none;" onchange="handleDesignReupload(this, ${data.order_id}, '${data.csrf_token}')" accept="image/*,application/pdf">
                            </div>
                        ` : ''}

                        ${data.design_status === 'Approved' ? `
                            <div style="margin-top:10px; text-align:center; color:#16a34a; font-size:12px; font-weight:600;">
                                ✅ Your design has been approved for production.
                            </div>
                        ` : ''}
                    </div>

                    ${revisionHTML}

                    <div id="imCancelSection" style="margin-top:20px; padding-top:20px; border-top:1px solid #f1f5f9;">
                        ${data.can_cancel 
                            ? `<button class="im-cancel-trigger-btn" onclick="openCancelModal(${data.order_id}, '${data.csrf_token}')" 
                                       style="width:100%; padding:14px; background:#fff; border:2px solid #fee2e2; border-radius:12px; color:#ef4444; font-weight:800; font-size:14px; cursor:pointer; transition:all 0.2s;">
                                   Cancel Order
                               </button>`
                            : (data.cancel_restriction_msg 
                                ? `<div style="padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; color:#94a3b8; font-size:13px; text-align:center; font-weight:600;">
                                       ${escIM(data.cancel_restriction_msg)}
                                   </div>`
                                : '')
                        }
                    </div>
                </div>
            </div>`;
    })
    .catch(() => {
        document.getElementById('imBody').innerHTML =
            `<p style="color:#ef4444;font-size:13px;">Failed to load. Please try again.</p>`;
    });
}

// ── Cancellation Logic ───────────────────────────────────
let cancelOrderId = null;
let cancelCsrfToken = null;

function openCancelModal(orderId, csrfToken) {
    cancelOrderId = orderId;
    cancelCsrfToken = csrfToken;
    const modal = document.getElementById('cancelModal');
    modal.classList.add('open');
    
    // Reset options
    document.querySelectorAll('.cm-opt').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('input[name="cancel_reason"]').forEach(rb => rb.checked = false);
    document.getElementById('cmOtherInput').style.display = 'none';
    document.getElementById('cmOtherInput').value = '';
    document.getElementById('cmConfirmBtn').disabled = true;
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('open');
    cancelOrderId = null;
    cancelCsrfToken = null;
}

// Handle radio button clicks
document.addEventListener('change', e => {
    if (e.target.name === 'cancel_reason') {
        const opts = document.querySelectorAll('.cm-opt');
        opts.forEach(opt => {
            const radio = opt.querySelector('input');
            opt.classList.toggle('active', radio.checked);
        });
        
        const otherInput = document.getElementById('cmOtherInput');
        otherInput.style.display = (e.target.value === 'Other') ? 'block' : 'none';
        
        document.getElementById('cmConfirmBtn').disabled = false;
    }
});

function submitOrderCancellation() {
    const reasonEl = document.querySelector('input[name="cancel_reason"]:checked');
    if (!reasonEl) return;
    
    const reason = reasonEl.value;
    const details = document.getElementById('cmOtherInput').value;
    
    if (reason === 'Other' && !details.trim()) {
        alert("Please specify your reason.");
        return;
    }

    const btn = document.getElementById('cmConfirmBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Processing…';

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('order_id', cancelOrderId);
    fd.append('csrf_token', cancelCsrfToken);
    fd.append('reason', reason);
    fd.append('details', details);

    fetch('/printflow/customer/cancel_order.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeCancelModal();
            closeItemsModal();
            // Show success alert and refresh
            alert("Order #" + cancelOrderId + " has been cancelled.");
            window.location.reload();
        } else {
            alert(data.error || "Failed to cancel order.");
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(() => {
        alert("A network error occurred.");
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

function toggleFullDetails() {
    imExpanded = !imExpanded;
    const modal = document.getElementById('itemsModal');
    const panel = document.getElementById('imFullDetails');
    const btn   = document.getElementById('imExpandBtn');

    panel.classList.toggle('open', imExpanded);
    btn.classList.toggle('active', imExpanded);
    modal.classList.toggle('expanded', imExpanded);

    const spanText = btn.querySelector('span');
    spanText.textContent = imExpanded ? 'Hide Order Details' : 'View Full Order Details';
}

function closeItemsModal() {
    const modal = document.getElementById('itemsModal');
    modal.classList.remove('open','expanded');
    document.body.style.overflow = '';
    imExpanded = false;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeItemsModal(); });

function triggerDesignReupload(orderId) {
    document.getElementById('designReuploadInput-' + orderId).click();
}

function handleDesignReupload(input, orderId, csrfToken) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    if (!confirm(`Are you sure you want to upload "${file.name}" as your new design?`)) {
        input.value = '';
        return;
    }

    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('csrf_token', csrfToken);
    fd.append('design_file', file);

    // Show loading state
    const btn = input.previousElementSibling;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Uploading...</span>';

    fetch('/printflow/customer/reupload_design_process.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('Design successfully re-uploaded! The staff will review it shortly.');
            window.location.reload();
        } else {
            alert(res.error || 'Failed to upload design');
            btn.disabled = false;
            btn.innerHTML = originalContent;
            input.value = '';
        }
    })
    .catch(() => {
        alert('Network error occurred');
        btn.disabled = false;
        btn.innerHTML = originalContent;
        input.value = '';
    });
}

function escIM(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php include __DIR__ . '/../includes/order_chat.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


