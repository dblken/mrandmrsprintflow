<?php
/**
 * AJAX: Get Order Data (Staff)
 * Returns full order details as JSON for modal display
 */

error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

function staff_order_data_json($payload, $status = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        $json = json_encode(['error' => 'Server error while encoding order details.']);
    }
    echo $json;
    exit;
}

function staff_order_data_profile_image($image) {
    if (empty($image) || $image === 'null' || $image === 'undefined') {
        return BASE_PATH . '/public/assets/uploads/profiles/default.png';
    }

    $image = (string)$image;
    if ($image[0] === '/' || strpos($image, 'http') === 0) {
        return $image;
    }

    return BASE_PATH . '/public/assets/uploads/profiles/' . ltrim($image, '/');
}

register_shutdown_function(function() {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error && in_array($error['type'], $fatalTypes, true)) {
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Server error while loading order details.'
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
});

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

function staff_order_data_columns($table) {
    static $cache = [];

    if (!isset($cache[$table])) {
        $rows = db_query("SHOW COLUMNS FROM `{$table}`");
        $cache[$table] = [];
        foreach ($rows as $row) {
            if (!empty($row['Field'])) {
                $cache[$table][$row['Field']] = true;
            }
        }
    }

    return $cache[$table];
}

function staff_order_data_select($columns, $alias, $column, $as, $defaultSql = "''") {
    if (isset($columns[$column])) {
        return "{$alias}.`{$column}` AS {$as}";
    }

    return "{$defaultSql} AS {$as}";
}

try {

// Allow Staff, Admin and Manager to access order data
if (!is_logged_in() || !in_array(get_user_type(), ['Staff', 'Admin', 'Manager'])) {
    staff_order_data_json(['error' => 'Unauthorized'], 401);
}

$branchFilter = printflow_branch_filter_for_user();
$customerColumns = staff_order_data_columns('customers');
$productColumns = staff_order_data_columns('products');

$action = $_GET['action'] ?? 'get_order';

if ($action === 'list_orders') {
    $status = $_GET['status'] ?? '';
    $sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            (SELECT GROUP_CONCAT(DISTINCT p.sku ORDER BY p.sku SEPARATOR '-') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as order_sku,
            (SELECT GROUP_CONCAT(COALESCE(p.name, 'Custom Product') SEPARATOR ', ') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as item_names
            FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id WHERE 1=1";
    $params = [];
    $types = '';

    if ($branchFilter !== null) {
        $sql .= " AND o.branch_id = ?";
        $params[] = $branchFilter;
        $types .= 'i';
    }
    
    if (!empty($status)) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $sql .= " ORDER BY o.order_date DESC LIMIT 50";
    $orders = db_query($sql, $types, $params);
    
    // Format for JS consumption
    foreach ($orders as &$o) {
        $o['order_date_fmt'] = format_date($o['order_date']);
        $o['total_amount_fmt'] = format_currency($o['total_amount']);
        $o['order_code'] = printflow_format_order_code($o['order_id'] ?? 0, $o['order_sku'] ?? '');
    }
    
    staff_order_data_json(['success' => true, 'orders' => $orders]);
}

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    staff_order_data_json(['error' => 'Invalid order ID'], 400);
}

// Get order with customer info
$customer_select = "
               " . staff_order_data_select($customerColumns, 'c', 'first_name', 'cust_first') . ",
               " . staff_order_data_select($customerColumns, 'c', 'last_name', 'cust_last') . ",
               " . staff_order_data_select($customerColumns, 'c', 'email', 'cust_email') . ",
               " . staff_order_data_select($customerColumns, 'c', 'contact_number', 'cust_phone') . ",
               " . staff_order_data_select($customerColumns, 'c', 'customer_id', 'cust_id', 'NULL') . ",
               " . staff_order_data_select($customerColumns, 'c', 'customer_type', 'cust_type', "'REGULAR'") . ",
               " . staff_order_data_select($customerColumns, 'c', 'address', 'cust_address') . ",
               " . staff_order_data_select($customerColumns, 'c', 'profile_picture', 'cust_profile_picture') . "";

if ($branchFilter !== null) {
    $order_result = db_query("
        SELECT o.*,
               (SELECT GROUP_CONCAT(DISTINCT p2.sku ORDER BY p2.sku SEPARATOR '-') FROM order_items oi2 LEFT JOIN products p2 ON oi2.product_id = p2.product_id WHERE oi2.order_id = o.order_id) as order_sku,
               {$customer_select}
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.order_id = ? AND o.branch_id = ?
    ", 'ii', [$order_id, $branchFilter]);
} else {
    $order_result = db_query("
        SELECT o.*,
               (SELECT GROUP_CONCAT(DISTINCT p2.sku ORDER BY p2.sku SEPARATOR '-') FROM order_items oi2 LEFT JOIN products p2 ON oi2.product_id = p2.product_id WHERE oi2.order_id = o.order_id) as order_sku,
               {$customer_select}
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.order_id = ?
    ", 'i', [$order_id]);
}

if (empty($order_result)) {
    staff_order_data_json(['error' => 'Order not found'], 404);
}
$order = $order_result[0];

// Get order items
$product_select = "
       " . staff_order_data_select($productColumns, 'p', 'name', 'product_name') . ",
       " . staff_order_data_select($productColumns, 'p', 'sku', 'sku') . ",
       " . staff_order_data_select($productColumns, 'p', 'category', 'category') . ",
       " . staff_order_data_select($productColumns, 'p', 'product_image', 'product_image') . ",
       " . staff_order_data_select($productColumns, 'p', 'photo_path', 'photo_path') . ",
       " . staff_order_data_select($productColumns, 'p', 'product_type', 'product_type', "'custom'") . "";

$items = db_query("
    SELECT oi.*, {$product_select}
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

    // Removed other orders from same customer as requested
    $customer_orders = [];

// Build items array
$items_out = [];
foreach ($items as $item) {
    $custom_data = json_decode($item['customization_data'] ?? '{}', true) ?? [];
    // Remove design_upload key from display
    unset($custom_data['design_upload']);

    $items_out[] = [
        'order_item_id' => $item['order_item_id'],
        'product_name'  => (function() use ($item, $custom_data) {
            if (!empty($item['product_name'])) return $item['product_name'];
            $name = get_service_name_from_customization($custom_data, 'Custom Order');
            if (!empty($custom_data['product_type']) && $custom_data['product_type'] !== $name) {
                $name .= " (" . $custom_data['product_type'] . ")";
            }
            return $name;
        })(),
        'sku'           => $item['sku'] ?? '',
        'category'      => $item['category'] ?? '',
        'quantity'      => (int)$item['quantity'],
        'unit_price'    => (float)$item['unit_price'],
        'subtotal'      => (float)($item['quantity'] * $item['unit_price']),
        'customization' => $custom_data,
        'has_design'    => !empty($item['design_image']) || !empty($item['design_file']),
        'has_reference' => !empty($item['reference_image_file']),
        'design_name'   => $item['design_image_name'] ?? 'design_file',
        'design_url'    => (!empty($item['design_image']) || !empty($item['design_file']))
                            ? BASE_PATH . '/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id']
                            : null,
        'reference_url' => !empty($item['reference_image_file'])
                            ? BASE_PATH . '/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference'
                            : null,
        'product_image' => (function() use ($item) {
            // photo_path is the primary image field (used by customer/products.php)
            $img = $item['photo_path'] ?: $item['product_image'] ?: null;
            if (empty($img)) return null;
            // If it already has a full path starting with / or http, use as-is
            if ($img[0] === '/' || strpos($img, 'http') === 0) return $img;
            // Bare filename — assume products uploads folder
            return BASE_PATH . '/public/assets/uploads/products/' . $img;
        })(),
        'product_type'  => $item['product_type'] ?? 'custom',
    ];
}

// Build customer orders array
$cust_orders_out = [];
foreach ($customer_orders as $co) {
    $cust_orders_out[] = [
        'order_id'     => $co['order_id'],
        'order_date'   => format_date($co['order_date']),
        'total_amount' => format_currency($co['total_amount']),
        'status'       => $co['status'],
    ];
}

// Get revision history - table doesn't exist yet, return empty array
$revisions_out = [];

// Normalize status for product orders — production statuses are not valid for fixed products
$display_status = $order['status'];
if (($order['order_type'] ?? '') === 'product') {
    $production_statuses = ['Processing', 'In Production', 'Printing', 'Approved Design'];
    if (in_array($display_status, $production_statuses)) {
        $display_status = 'Ready for Pickup';
    }
}

$payment_proof_path = null;
if (!empty($order['payment_proof'])) {
    $payment_proof_path = preg_replace('#^/printflow/#', BASE_PATH . '/', (string)$order['payment_proof']);
    if (strpos($payment_proof_path, 'http') !== 0 && ($payment_proof_path[0] ?? '') !== '/') {
        $payment_proof_path = BASE_PATH . '/' . ltrim($payment_proof_path, '/');
    }
}

staff_order_data_json([
    'order_id'            => $order['order_id'],
    'order_code'          => printflow_format_order_code($order['order_id'] ?? 0, $order['order_sku'] ?? ''),
    'order_date'          => format_datetime($order['order_date']),
    'total_amount'        => format_currency($order['total_amount']),
    'total_raw'           => (float)$order['total_amount'],
    'status'              => $display_status,
    'order_type'          => $order['order_type'] ?? 'product',
    'order_source'        => $order['order_source'] ?? 'online',
    'payment_status'      => $order['payment_status'],
    'payment_reference'   => $order['payment_reference'] ?? '',
    'payment_type'        => $order['payment_type'] ?? 'full_payment',
    'downpayment_amount'  => (float)($order['downpayment_amount'] ?? 0),
    'notes'               => $order['notes'] ?? '',
    'cancelled_by'        => $order['cancelled_by'] ?? '',
    'cancel_reason'       => $order['cancel_reason'] ?? '',
    'cancelled_at'        => !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : '',
    'design_status'       => $order['design_status'] ?? 'Pending',
    'reviewed_by'         => $order['reviewed_by'] ?? null,
    'reviewed_at'         => !empty($order['reviewed_at']) ? format_datetime($order['reviewed_at']) : '',
    'cust_name'           => trim(($order['cust_first'] ?? '') . ' ' . ($order['cust_last'] ?? '')),
    'cust_initial'        => strtoupper(substr($order['cust_first'] ?? 'C', 0, 1)),
    'cust_email'          => $order['cust_email'] ?? '',
    'cust_phone'          => $order['cust_phone'] ?? '',
    'cust_type'           => $order['cust_type'] ?? 'REGULAR',
    'cust_address'        => $order['cust_address'] ?? '',
    'cust_profile_picture'=> staff_order_data_profile_image($order['cust_profile_picture'] ?? null),
    'payment_proof'       => $payment_proof_path,
    'payment_submitted_at'=> !empty($order['payment_submitted_at']) ? format_datetime($order['payment_submitted_at']) : '',
    'revision_count'      => (int)($order['revision_count'] ?? 0),
    'revision_reason'     => $order['revision_reason'] ?? '',
    'items'               => $items_out,
    'customer_orders'     => $cust_orders_out,
    'revisions'           => $revisions_out,
    'csrf_token'          => generate_csrf_token(),
]);

} catch (Throwable $e) {
    staff_order_data_json(['error' => 'Server error while loading order details.'], 500);
}
