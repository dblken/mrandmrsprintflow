<?php
/**
 * Serve Design Image / File
 * PrintFlow - Printing Shop PWA
 * Serves design images from either the database (BLOB) or the filesystem.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Role-based access (Customers can only see their own, Staff can see all)
if (!is_logged_in()) {
    http_response_code(403);
    die('Unauthorized');
}

$type = $_GET['type'] ?? 'order_item'; // 'order_item', 'temp_cart', etc.
$id   = (int)($_GET['id'] ?? 0);
$field = $_GET['field'] ?? 'design'; // 'design' or 'reference'

if (!$id) {
    http_response_code(404);
    die('Not Found');
}

$user_id = get_user_id();
$is_staff = is_staff() || is_admin() || is_manager();

if ($type === 'order_item') {
    // 1. Check if user has access to this order
    if (!$is_staff) {
        $check = db_query("SELECT o.customer_id FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.order_item_id = ?", 'i', [$id]);
        if (empty($check) || $check[0]['customer_id'] != $user_id) {
            http_response_code(403);
            die('Unauthorized access to this order item.');
        }
    }

    // 2. Get data
    $item = db_query("SELECT design_image, design_image_mime, design_file, reference_image_file FROM order_items WHERE order_item_id = ?", 'i', [$id])[0] ?? null;

    if (!$item) {
        http_response_code(404);
        die('Item not found');
    }

    if ($field === 'reference') {
        $path = $item['reference_image_file'];
        if ($path && file_exists(__DIR__ . '/..' . $path)) {
            $full_path = __DIR__ . '/..' . $path;
            $mime = mime_content_type($full_path);
            header("Content-Type: $mime");
            readfile($full_path);
            exit;
        }
    } else {
        // Try BLOB first
        if (!empty($item['design_image'])) {
            $mime = $item['design_image_mime'] ?: 'image/jpeg';
            header("Content-Type: $mime");
            echo $item['design_image'];
            exit;
        }
        // Then try File
        if ($item['design_file'] && file_exists(__DIR__ . '/..' . $item['design_file'])) {
            $full_path = __DIR__ . '/..' . $item['design_file'];
            $mime = mime_content_type($full_path);
            header("Content-Type: $mime");
            readfile($full_path);
            exit;
        }
    }
}

http_response_code(404);
echo "Image not found.";
?>
