<?php
/**
 * Customer Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
    $back_filter = isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '';
    redirect('/printflow/customer/notifications.php' . $back_filter);
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    db_execute("UPDATE notifications SET is_read = 1 WHERE customer_id = ? AND is_read = 0", 'i', [$customer_id]);
    redirect('/printflow/customer/notifications.php');
}

// Choose available product image column for compatibility across DB versions.
$has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
$has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
$product_image_column = 'NULL';
if ($has_photo_path && $has_product_image) {
    $product_image_column = "COALESCE(p.photo_path, p.product_image)";
} elseif ($has_photo_path) {
    $product_image_column = "p.photo_path";
} elseif ($has_product_image) {
    $product_image_column = "p.product_image";
}

// Get all notifications with order and product details
$notifications = db_query("
    SELECT 
        n.*,
        o.order_id,
        CASE WHEN n.type = 'Job Order' THEN jo.job_title ELSE 
            (SELECT p.name FROM order_items oi 
             LEFT JOIN products p ON oi.product_id = p.product_id 
             WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
        END as service_name,
        CASE WHEN n.type = 'Job Order' THEN jo.service_type ELSE NULL END as jo_service_category,
        CASE WHEN n.type = 'Job Order' THEN jo.artwork_path ELSE 
            (SELECT {$product_image_column} FROM products p 
             INNER JOIN order_items oi ON oi.product_id = p.product_id 
             WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1)
        END as product_image,
        (SELECT oi.customization_data FROM order_items oi 
         WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_customization,
        (SELECT oi.order_item_id FROM order_items oi 
         WHERE oi.order_id = n.data_id ORDER BY oi.order_item_id ASC LIMIT 1) as first_item_id,
        (SELECT oi.design_image FROM order_items oi 
         WHERE oi.order_id = n.data_id AND oi.design_image IS NOT NULL ORDER BY oi.order_item_id ASC LIMIT 1) as design_image
    FROM notifications n
    LEFT JOIN orders o ON n.data_id = o.order_id AND n.type IN ('Order', 'Status', 'Message', 'Rating')
    LEFT JOIN job_orders jo ON n.data_id = jo.id AND n.type = 'Job Order'
    WHERE n.customer_id = ? 
    ORDER BY n.created_at DESC LIMIT 100
", 'i', [$customer_id]);

// Categorize by read status for display
$grouped_notifications = [
    'New' => [],
    'Earlier' => []
];
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) {
        $grouped_notifications['New'][] = $n;
    } else {
        $grouped_notifications['Earlier'][] = $n;
    }
}
// Remove empty groups
$grouped_notifications = array_filter($grouped_notifications);
$unread_total = array_reduce($notifications, function($carry, $item) {
    return $carry + ($item['is_read'] ? 0 : 1);
}, 0);

$page_title = 'Notifications - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .notif-wrapper {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        padding: 24px;
        min-height: 500px;
        margin-bottom: 2rem;
    }
    .notif-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }
    .notif-title-group {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .notif-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1a202c;
    }
    .notif-count-badge {
        background: #0a2530;
        color: white;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 6px;
    }
    .mark-all-btn {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 500;
        transition: color 0.2s;
    }
    .mark-all-btn:hover {
        color: #0a2530;
    }
    .notif-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px 24px;
        transition: all 0.2s;
        cursor: pointer;
        position: relative;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        border-left: 4px solid transparent;
    }
    .notif-item:hover {
        background: #f8fafc;
    }
    .notif-item:last-child {
        border-bottom: none;
    }
    .notif-item.unread {
        background: #f3f4f6;
        border-left-color: #cbd5e1;
    }
    .notif-item.unread::after {
        content: '';
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
    }
    .notif-avatar {
        width: 80px;
        height: 80px;
        min-width: 80px;
        border-radius: 8px;
        overflow: hidden;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        overflow: hidden;
    }
    .notif-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .notif-content {
        flex: 1;
        min-width: 0;
    }
    .notif-text {
        font-size: 0.875rem;
        line-height: 1.5;
        color: #64748b;
        margin-bottom: 4px;
        overflow-wrap: anywhere;
        word-break: break-word;
        white-space: normal;
    }
    .notif-text b {
        color: #1e293b;
        font-weight: 700;
    }
    .notif-time {
        font-size: 0.8125rem;
        color: #94a3b8;
    }
    .notif-msg-box {
        margin-top: 12px;
        padding: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.8125rem;
        color: #64748b;
        line-height: 1.6;
    }
    .notif-msg-box:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .notif-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }
    .notif-btn {
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
    }
    .notif-btn-primary {
        background: #0a2530;
        color: white;
    }
    .notif-btn-secondary {
        background: #f1f5f9;
        color: #475569;
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 1100px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h1 class="ct-page-title" style="margin-bottom: 0;">Notifications</h1>
                <?php if ($unread_total > 0): ?>
                    <span class="count-badge" style="background: #0a2530; color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;"><?php echo $unread_total; ?></span>
                <?php endif; ?>
            </div>
            <a href="?mark_all_read=1" class="btn-secondary" style="font-size: 0.875rem; border-radius: 8px; padding: 0.5rem 1rem; text-decoration: none;">Mark all as read</a>
        </div>

        <div class="notif-wrapper">

            <?php if (empty($notifications)): ?>
                <div class="text-center py-20">
                    <p class="text-gray-400">No notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($grouped_notifications as $group => $notifs): ?>
                        <div>
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2 px-2"><?php echo htmlspecialchars($group); ?></h3>
                            <div class="space-y-1">
                                <?php foreach ($notifs as $notif): 
                            // Determine "Avatar" or Icon based on message
                            $avatar_text = "PF"; // Default
                            $is_chat = (strpos(strtolower($notif['message']), 'message') !== false || strpos(strtolower($notif['message']), 'chat') !== false);
                            $is_order = (strpos(strtolower($notif['message']), 'order') !== false);
                            
                            $icon = "🔔";
                            if ($is_chat) $icon = "💬";
                            if ($is_order) $icon = "📦";

                            // Prefer actual service name from customization over product name (e.g. Transparent Sticker not "Sticker Pack")
                            $name_data = !empty($notif['first_item_customization']) ? json_decode($notif['first_item_customization'], true) : [];
                            $raw_service_name = trim((string)($name_data['service_type'] ?? $notif['jo_service_category'] ?? $notif['service_name'] ?? ''));
                            if (empty($raw_service_name) || in_array(strtolower($raw_service_name), ['custom order', 'customer order', 'service order', 'order item', 'order update'])) {
                                $raw_service_name = get_service_name_from_customization($name_data, $notif['service_name'] ?? 'Order Update');
                            }
                            $display_name = normalize_service_name($raw_service_name, 'Order Update');

                            // Determine image: design first, then service image from correct service name
                            $final_image_url = "";
                            if (!empty($notif['design_image'])) {
                                $final_image_url = "/printflow/staff/get_design_image.php?id=" . $notif['first_item_id'];
                            } elseif (!empty($notif['product_image']) && strtolower(trim($display_name)) === strtolower(trim($notif['service_name'] ?? ''))) {
                                $final_image_url = $notif['product_image'];
                                if (strpos($final_image_url, 'uploads/') === 0) {
                                    $final_image_url = '/printflow/' . $final_image_url;
                                }
                            } else {
                                $final_image_url = get_service_image_url($raw_service_name ?: $display_name);
                            }
                            $fallback_img = '/printflow/public/assets/images/placeholder.jpg';

                            // Determine redirection link
                            $link = "/printflow/customer/notifications.php?mark_read=" . $notif['notification_id'];
                            $is_rating_notif = (
                                (string)$notif['type'] === 'Rating' ||
                                stripos((string)$notif['message'], 'rate your experience') !== false ||
                                stripos((string)$notif['message'], 'rate your order') !== false
                            );
                            if (!empty($notif['data_id'])) {
                                if ($is_rating_notif) {
                                    $link = "/printflow/customer/rate_order.php?order_id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                                } elseif ($notif['type'] === 'Order' || $notif['type'] === 'Status') {
                                    $link = "/printflow/customer/order_details.php?id=" . $notif['data_id'] . "&mark_read=" . $notif['notification_id'];
                                } elseif ($notif['type'] === 'Message') {
                                    $link = "/printflow/customer/order_details.php?id=" . $notif['data_id'] . "&chat=open&mark_read=" . $notif['notification_id'];
                                }
                            }
                        ?>
                            <a href="<?php echo $link; ?>" class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                
                                <div class="notif-avatar">
                                    <img src="<?php echo htmlspecialchars($final_image_url); ?>" alt="<?php echo htmlspecialchars($display_name); ?>" class="notif-image" onerror="this.src='<?php echo $fallback_img; ?>';">
                                </div>

                                <div class="notif-content">
                                    <div class="notif-text" style="<?php echo $notif['is_read'] ? '' : 'font-weight: 600; color: #1e293b;'; ?>">
                                        <strong><?php echo htmlspecialchars($display_name); ?></strong> – 
                                        <?php 
                                            $msg = htmlspecialchars($notif['message']);
                                            $msg = preg_replace('/(Order #\d+)/', '<b>$1</b>', $msg);
                                            echo $msg;
                                        ?>
                                        <?php if ($notif['is_read'] == 0): ?>
                                            <span style="color: #ef4444; font-weight: 800; font-size: 0.6rem; margin-left: 0.25rem;">●</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-time"><?php echo time_elapsed_string($notif['created_at']); ?></div>
                                </div>

                                <?php if (!empty($notif['data_id'])): ?>
                                    <div class="notif-actions">
                                        <span class="notif-btn notif-btn-secondary"><?php echo $is_rating_notif ? 'Rate Now' : 'View'; ?></span>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

