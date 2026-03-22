<?php
/**
 * Staff Notifications Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

$staff_id = get_user_id();

// Mark as read
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?", 'ii', [$notification_id, $staff_id]);
    if (!empty($_GET['next'])) {
        redirect((string)$_GET['next']);
    }
    redirect('/printflow/staff/notifications.php');
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    db_execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$staff_id]);
    redirect('/printflow/staff/notifications.php');
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

// Get notifications with customer names + service/image context
$notifications = db_query("
    SELECT 
        n.*,
        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
        -- Priority 1: Job Order details if it's a job order
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
    LEFT JOIN customers c ON n.customer_id = c.customer_id 
    LEFT JOIN job_orders jo ON n.data_id = jo.id AND n.type = 'Job Order'
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 100", 'i', [$staff_id]);

$page_title = 'Notifications - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <link rel="stylesheet" href="/printflow/public/assets/css/chat.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .notif-wrapper { background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.05); padding:24px; margin-bottom:2rem; }
        .notif-item {
            display:flex; align-items:center; gap:14px; padding:16px 18px;
            border-radius:10px; border:1px solid #f1f5f9; margin-bottom:10px;
            transition:all 0.18s; text-decoration:none; color:inherit; border-left:4px solid transparent;
        }
        .notif-item:hover { background:#f8fafc; border-color:#e2e8f0; }
        .notif-item.unread { background:#f3f4f6; border-left-color:#cbd5e1; }
        .notif-avatar { width:46px; height:46px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; }
        .notif-avatar img { width:100%; height:100%; object-fit:cover; }
        .notif-dot { width:8px; height:8px; border-radius:999px; background:#ef4444; margin-left:auto; flex-shrink:0; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h1 class="page-title" style="margin-bottom: 0;">Notifications</h1>
            <?php 
            $unread_count = db_query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$staff_id])[0]['count'];
            if ($unread_count > 0): 
            ?>
                <a href="?mark_all_read=1" 
                   class="btn-primary" 
                   style="background: #0a2530; color: white; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(10, 37, 48, 0.2);"
                   onmouseover="this.style.background='#0d3038'"
                   onmouseout="this.style.background='#0a2530'">
                    MARK ALL READ
                </a>
            <?php endif; ?>
        </header>

        <main>
        <?php if (empty($notifications)): ?>
            <div class="card" style="text-align:center; padding:48px 24px;">
                <div style="font-size:48px; margin-bottom:12px;">🔔</div>
                <p style="color:#6b7280; font-size:14px;">No notifications yet</p>
            </div>
        <?php else: ?>

            <!-- Filter Bar Removed -->


            <?php
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
            $grouped_notifications = array_filter($grouped_notifications);
            
            foreach ($grouped_notifications as $group => $notifs): ?>
                <div style="margin-top: 24px; margin-bottom: 12px;">
                    <h3 style="font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 8px;"><?php echo htmlspecialchars($group); ?></h3>
                </div>
                <?php foreach ($notifs as $notif):
                // Get unread chat count for this order (staff = receiver 'User')
                $chat_unread = 0;
                $is_rating_notif = (
                    (string)$notif['type'] === 'Rating' ||
                    stripos((string)$notif['message'], 'rating') !== false ||
                    stripos((string)$notif['message'], 'review') !== false
                );

                if (!empty($notif['data_id']) && $notif['type'] === 'Order' && !$is_rating_notif) {
                    $chat_unread = get_unread_chat_count($notif['data_id'], 'User');
                }
                
                // Determine redirection URL
                $redirect_url = "#";
                if ($is_rating_notif) {
                    $redirect_url = "/printflow/staff/reviews.php";
                } elseif (!empty($notif['data_id']) && $notif['type'] === 'Message') {
                    $redirect_url = "/printflow/staff/chats.php?order_id=" . $notif['data_id'];
                } elseif (!empty($notif['data_id']) && $notif['type'] === 'Order') {
                    $redirect_url = "/printflow/staff/customizations.php?order_id=" . $notif['data_id'];
                }
            ?>
                <?php
                    $item_href = $notif['is_read']
                        ? $redirect_url
                        : ('/printflow/staff/notifications.php?mark_read=' . (int)$notif['notification_id'] . '&next=' . urlencode($redirect_url));
                ?>
                <a href="<?php echo $item_href; ?>" class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                <div class="notif-avatar">
                    <?php 
                        $final_image_url = "";
                        $raw_service_name = trim((string)($notif['service_name'] ?? ''));
                        
                        // Handle "Order Update" generic title
                        if (in_array(strtolower($raw_service_name), ['', 'custom order', 'customer order', 'service order', 'order item', 'order update'])) {
                            if (!empty($notif['first_item_customization'])) {
                                $name_data = json_decode($notif['first_item_customization'], true);
                                if (!empty($name_data['service_type'])) {
                                    $raw_service_name = $name_data['service_type'];
                                }
                            } elseif (!empty($notif['jo_service_category'])) {
                                $raw_service_name = $notif['jo_service_category'];
                            }
                        }
                        
                        $display_name = normalize_service_name($raw_service_name, 'Order Update');

                        // 1. Try Design Image (BLOB serving script)
                        if (!empty($notif['design_image'])) {
                            // Using the new get_design_image.php script
                            $final_image_url = "/printflow/staff/get_design_image.php?id=" . $notif['first_item_id'];
                        }
                        // 2. Try Product/Service Image or Job artwork_path
                        elseif (!empty($notif['product_image'])) {
                            $final_image_url = $notif['product_image'];
                            // If it's a relative path to uploads, ensure full path
                            if (strpos($final_image_url, 'uploads/') === 0) {
                                $final_image_url = '/printflow/' . $final_image_url;
                            }
                        }
                        // 3. Fallbacks
                        else {
                            $cust_data = json_decode($notif['first_item_customization'] ?? '{}', true);
                            $cat_lower = strtolower($notif['jo_service_category'] ?? $cust_data['service_type'] ?? $display_name);
                            
                            if (strpos($cat_lower, 'reflectorized') !== false || strpos($cat_lower, 'signage') !== false) {
                                $final_image_url = "/printflow/public/images/products/signage.jpg";
                            } elseif (strpos($cat_lower, 'tarpaulin') !== false) {
                                $final_image_url = "/printflow/public/images/products/product_41.jpg";
                            } elseif (strpos($cat_lower, 'sintraboard') !== false || strpos($cat_lower, 'standee') !== false) {
                                $final_image_url = "/printflow/public/images/services/Sintraboard Standees.jpg";
                            } elseif (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) {
                                $final_image_url = "/printflow/public/images/products/product_31.jpg";
                            } elseif (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) {
                                if (strpos($cat_lower, 'glass') !== false || strpos($cat_lower, 'frosted') !== false) {
                                    $final_image_url = "/printflow/public/images/products/Glass Stickers  Wall  Frosted Stickers.png";
                                } else {
                                    $final_image_url = "/printflow/public/images/products/product_21.jpg";
                                }
                            } else {
                                $final_image_url = "/printflow/public/assets/images/icon-192.png";
                            }
                        }
                    ?>
                    <img src="<?php echo htmlspecialchars($final_image_url); ?>" alt="Service" onerror="this.src='/printflow/public/assets/images/icon-192.png';">
                </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:14px; line-height:1.45; color:#334155;">
                            <strong style="color:#0f172a;"><?php echo htmlspecialchars($display_name); ?></strong>
                            - <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div style="margin-top:4px; font-size:12px; color:#94a3b8;"><?php echo format_datetime($notif['created_at']); ?></div>
                        <?php if ($chat_unread > 0): ?>
                            <div style="margin-top:6px; font-size:11px; color:#b91c1c; font-weight:700;">
                                <?php echo $chat_unread; ?> unread chat message<?php echo $chat_unread > 1 ? 's' : ''; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($notif['data_id']) && in_array($notif['type'], ['Order', 'Message', 'Design']) && !$is_rating_notif): ?>
                            <div style="margin-top:10px;">
                                <a href="<?php echo BASE_URL; ?>/staff/chats.php?order_id=<?php echo (int)$notif['data_id']; ?>"
                                    style="display:inline-block; border:none; background:#0a2530; color:#fff; border-radius:7px; padding:6px 12px; font-size:12px; font-weight:700; text-decoration:none;"
                                >
                                    Open Chat
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                        <span class="notif-dot"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; endforeach; ?>

            <!-- Shown 0 logic removed as filter is gone -->

        <?php endif; ?>
        </main>
    </div>
</div>


</body>
</html>
