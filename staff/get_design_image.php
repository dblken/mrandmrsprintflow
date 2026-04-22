<?php
/**
 * Serve design image from BLOB
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

require_role(['Admin', 'Staff', 'Customer']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("ID required");
}

$res = db_query("
    SELECT oi.design_image, oi.order_id, o.customer_id, o.branch_id
    FROM order_items oi
    LEFT JOIN orders o ON o.order_id = oi.order_id
    WHERE oi.order_item_id = ?
", 'i', [$id]);
if (!$res) {
    header('Content-Type: image/png');
    readfile(__DIR__ . '/../public/assets/images/services/default.png');
    exit;
}

$row = $res[0];
$userType = get_user_type();
$userId = get_user_id();

if ($userType === 'Customer') {
    if ((int)($row['customer_id'] ?? 0) !== (int)$userId) {
        http_response_code(403);
        exit('Forbidden');
    }
} else {
    $branchId = printflow_branch_filter_for_user();
    if ($branchId !== null && (int)($row['branch_id'] ?? 0) !== (int)$branchId) {
        http_response_code(403);
        exit('Forbidden');
    }
}

if (empty($row['design_image'])) {
    // Return placeholder
    header('Content-Type: image/png');
    readfile(__DIR__ . '/../public/assets/images/services/default.png');
    exit;
}

$image = $row['design_image'];
// Try to detect content type or default to png
header('Content-Type: image/png');
echo $image;
exit;
