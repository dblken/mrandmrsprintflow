<?php
/**
 * AJAX: Get Order Items (Customer)
 * Returns order items + full order details as JSON for modal display
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

function customer_order_items_json($payload, $status = 200) {
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

register_shutdown_function(function() {
    $error = error_get_last();
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error && in_array($error['type'], $fatal_types, true)) {
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

require_role('Customer');

function pf_asset_kind(?string $mime, ?string $path): string {
    $mime = strtolower(trim((string)$mime));
    $path = strtolower(trim((string)$path));

    if ($mime === 'application/pdf') {
        return 'pdf';
    }

    if ($path !== '' && preg_match('/\.pdf(?:$|\?)/', $path)) {
        return 'pdf';
    }

    return 'image';
}

$base_path = defined('BASE_PATH') ? BASE_PATH : (function_exists('pf_app_base_path') ? pf_app_base_path() : '');

$order_id = (int)($_GET['id'] ?? 0);
$customer_id = get_user_id();

if (!$order_id) {
    customer_order_items_json(['error' => 'Invalid order ID'], 400);
}

// Verify order belongs to this customer
$order_result = db_query("
    SELECT o.*, b.branch_name,
           (SELECT jo.payment_proof_status
            FROM job_orders jo
            WHERE jo.order_id = o.order_id
            ORDER BY jo.payment_verified_at DESC, jo.id DESC
            LIMIT 1) as latest_payment_proof_status,
           (SELECT jo.payment_status
            FROM job_orders jo
            WHERE jo.order_id = o.order_id
            ORDER BY jo.payment_verified_at DESC, jo.id DESC
            LIMIT 1) as latest_job_payment_status,
           (SELECT jo.payment_rejection_reason
            FROM job_orders jo
            WHERE jo.order_id = o.order_id
              AND jo.payment_rejection_reason IS NOT NULL
              AND jo.payment_rejection_reason != ''
            ORDER BY jo.payment_verified_at DESC, jo.id DESC
            LIMIT 1) as payment_rejection_reason,
           (SELECT GROUP_CONCAT(DISTINCT p.sku ORDER BY p.sku SEPARATOR '-') FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id) as order_sku
    FROM orders o 
    LEFT JOIN branches b ON o.branch_id = b.id 
    WHERE o.order_id = ? AND o.customer_id = ?
", 'ii', [$order_id, $customer_id]);

if (empty($order_result)) {
    customer_order_items_json(['error' => 'Order not found'], 404);
}
$order = $order_result[0];
$latest_payment_proof_status = strtoupper((string)($order['latest_payment_proof_status'] ?? ''));
$latest_job_payment_status = strtoupper((string)($order['latest_job_payment_status'] ?? ''));
$is_rejected_payment = (strcasecmp((string)($order['status'] ?? ''), 'Rejected') === 0)
    || ($latest_payment_proof_status === 'REJECTED');

$payment_status = (string)($order['payment_status'] ?? 'Not Specified');
if ($is_rejected_payment) {
    $payment_status = 'Unpaid';
} elseif ($latest_job_payment_status === 'PAID') {
    $payment_status = 'Paid';
} elseif ($latest_job_payment_status === 'UNPAID') {
    $payment_status = 'Unpaid';
} elseif ($latest_job_payment_status === 'PARTIAL') {
    $payment_status = 'Partial';
}

$service_final_price_pending_statuses = ['Pending', 'Pending Approval', 'Pending Review', 'For Revision', 'Approved'];
$service_final_price_locked = in_array((string)($order['status'] ?? ''), $service_final_price_pending_statuses, true);

$first_item_customization = [];
$first_item_raw_customization = db_query(
    "SELECT customization_data FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC LIMIT 1",
    'i',
    [$order_id]
);
if (!empty($first_item_raw_customization[0]['customization_data'])) {
    $first_item_customization = json_decode((string)$first_item_raw_customization[0]['customization_data'], true) ?: [];
}

$is_service_order = false;
if (strcasecmp((string)($order['order_type'] ?? ''), 'custom') === 0) {
    $is_service_order = true;
} elseif (!empty($first_item_customization['service_type'])) {
    $is_service_order = true;
} elseif ((int)($order['reference_id'] ?? 0) > 0) {
    $is_service_order = true;
}

// Get items with design info
$items = db_query("
    SELECT oi.*, p.name as product_name, p.category
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

$items_out = [];
$service_items_raw = [];
$order_total_amount = (float)($order['total_amount'] ?? 0);
$item_count = is_array($items) ? count($items) : 0;
foreach ($items as $item) {
    $custom_data = json_decode($item['customization_data'] ?? '{}', true) ?: [];
    unset($custom_data['design_upload'], $custom_data['reference_upload']);

    $raw_quantity = max(1, (int)($item['quantity'] ?? 0));
    $raw_unit_price = (float)($item['unit_price'] ?? 0);
    $raw_subtotal = $raw_quantity * $raw_unit_price;

    // Some single-item custom/service orders store the final amount only on orders.total_amount.
    if ($raw_subtotal <= 0 && $item_count === 1 && $order_total_amount > 0) {
        $raw_subtotal = $order_total_amount;
        $raw_unit_price = $order_total_amount / $raw_quantity;
    }

    $service_items_raw[] = [
        'raw_subtotal' => $raw_subtotal,
        'payload' => [
        'order_item_id' => (int)$item['order_item_id'],
        'product_name'  => printflow_resolve_order_item_name($item['product_name'] ?? 'Order Item', $custom_data, 'Order Item'),
        'category'      => (strtolower($item['category'] ?? '') === 'merchandise') ? '' : ($item['category'] ?? ''),
        'quantity'      => $raw_quantity,
        'unit_price'    => format_currency($raw_unit_price),
        'subtotal'      => format_currency($raw_subtotal),
        'estimated_price' => format_currency($raw_subtotal),
        'final_price'   => format_currency($raw_subtotal),
        'customization' => $custom_data,
        'has_design'    => !empty($item['design_image']) || !empty($item['design_file']),
        'has_reference' => !empty($item['reference_image_file']),
        'design_kind'   => pf_asset_kind($item['design_image_mime'] ?? '', $item['design_file'] ?? ''),
        'reference_kind'=> pf_asset_kind('', $item['reference_image_file'] ?? ''),
        'design_url'    => (!empty($item['design_image']) || !empty($item['design_file']))
                            ? $base_path . '/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id']
                            : null,
        'reference_url' => !empty($item['reference_image_file'])
                            ? $base_path . '/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference'
                            : null,
        ],
    ];
}

$estimated_order_amount = (float)($order['estimated_price'] ?? 0);
if ($estimated_order_amount <= 0) {
    foreach ($service_items_raw as $entry) {
        $estimated_order_amount += (float)($entry['raw_subtotal'] ?? 0);
    }
}

$estimated_sum = 0.0;
foreach ($service_items_raw as $entry) {
    $estimated_sum += (float)($entry['raw_subtotal'] ?? 0);
}

foreach ($service_items_raw as $index => $entry) {
    $payload = $entry['payload'];
    $raw_subtotal = (float)($entry['raw_subtotal'] ?? 0);
    $final_item_amount = null;

    if (!$service_final_price_locked && $order_total_amount > 0 && $item_count === 1) {
        $final_item_amount = $order_total_amount > 0 ? $order_total_amount : $raw_subtotal;
    } elseif (!$service_final_price_locked && $estimated_sum > 0 && $order_total_amount > 0) {
        $final_item_amount = ($raw_subtotal / $estimated_sum) * $order_total_amount;
    }

    $payload['estimated_price'] = format_currency($raw_subtotal);
    $payload['final_price'] = ($final_item_amount !== null && $final_item_amount > 0)
        ? format_currency($final_item_amount)
        : 'To Be Discussed';
    $items_out[] = $payload;
}

// Cancellation / revision details
$cancel_info = '';
if ($order['status'] === 'Cancelled') {
    $cancel_info = trim(($order['cancelled_by'] ? 'By: ' . $order['cancelled_by'] : '') . ' | ' . ($order['cancel_reason'] ?? ''), ' |');
}

$can_cancel = can_customer_cancel_order($order);
$restriction_msg = '';
if (!$can_cancel && !in_array($order['status'], ['Cancelled', 'Completed'])) {
    switch ($order['status']) {
        case 'To Pay':
            $restriction_msg = printflow_format_order_code($order['order_id'], $order['order_sku'] ?? '') . " is already ready for payment.";
            break;
        case 'In Production':
        case 'Printing':
            $restriction_msg = printflow_format_order_code($order['order_id'], $order['order_sku'] ?? '') . " is already in production.";
            break;
        case 'Ready for Pickup':
            $restriction_msg = printflow_format_order_code($order['order_id'], $order['order_sku'] ?? '') . " is already ready for pickup.";
            break;
        default:
            $restriction_msg = printflow_format_order_code($order['order_id'], $order['order_sku'] ?? '') . " is already being processed.";
            break;
    }
}

// Rating details
$rating_data = null;
if (in_array($order['status'], ['Completed', 'To Rate', 'Rated'], true)) {
    $rating_res = db_query("SELECT * FROM reviews WHERE order_id = ?", 'i', [$order_id]);
    if (!empty($rating_res)) {
        $r = $rating_res[0];
        $rating_data = [
            'rating' => (int)$r['rating'],
            'comment' => $r['comment'] ?? '',
            'image_url' => null, // Multiple images handled by review_images table
            'created_at' => format_datetime($r['created_at']),
            'view_url' => ($r['review_type'] === 'custom') 
                ? "/printflow/customer/order_service_dynamic.php?service_id=" . $r['reference_id'] . "#review-" . $r['id']
                : "/printflow/customer/order_create.php?product_id=" . $r['reference_id'] . "#review-" . $r['id']
        ];
    }
}

customer_order_items_json([
    'order_id'         => $order['order_id'],
    'order_code'       => printflow_format_order_code($order['order_id'], $order['order_sku'] ?? ''),
    'order_date'       => format_datetime($order['order_date']),
    'is_service_order' => $is_service_order,
    'estimated_price'  => $is_service_order
        ? ($estimated_order_amount > 0
            ? format_currency($estimated_order_amount)
            : 'Pending')
        : null,
    'total_amount'     => ($is_service_order && ($service_final_price_locked || $order_total_amount <= 0))
        ? 'To Be Discussed'
        : format_currency($order['total_amount']),
    'status'           => $order['status'],
    'payment_status'   => $payment_status,
    'payment_method'   => $order['payment_method'] ?? 'Not Specified',
    'payment_proof_status' => $latest_payment_proof_status,
    'branch_name'      => $order['branch_name'] ?? 'Not Specified',
    'estimated_comp'   => ($order['estimated_completion'] ?? null) ? format_date($order['estimated_completion']) : 'Waiting for confirmation from the shop',
    'notes'            => $order['notes'] ?? '',
    'cancelled_by'     => $order['cancelled_by'] ?? '',
    'cancel_reason'    => $order['cancel_reason'] ?? '',
    'cancelled_at'     => !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : '',
    'design_status'    => $order['design_status'] ?? 'Pending',
    'revision_reason'  => $order['revision_reason'] ?? '',
    'payment_rejection_reason' => $order['payment_rejection_reason'] ?? '',
    'items'            => $items_out,
    'can_cancel'       => $can_cancel,
    'cancel_restriction_msg' => $restriction_msg,
    'rating_data'      => $rating_data,
    'csrf_token'       => generate_csrf_token()
]);
