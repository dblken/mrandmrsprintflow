<?php
/**
 * Staff: Customizations V2 — redirect shim
 *
 * The POS "Set Price" button on some builds redirects here.
 * This file forwards all query parameters to the canonical
 * customizations.php page so that the POS return-to-pos flow
 * works correctly.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config.php';
}

// Map legacy parameter names sent by older pos.php builds to the
// parameter names that customizations.php actually reads.
//
// Old:  ?order_id=X&return_to_pos=1&customization_id=Y
// New:  ?order_id=X&job_type=CUSTOMIZATION&return_to_pos=1&status=APPROVED&source_order_id=Z

$params = [];

// order_id in the old build was the customization_id (the primary key of the
// customizations table row).  customizations.php expects exactly that.
$order_id          = isset($_GET['order_id'])         ? (int)$_GET['order_id']         : 0;
$customization_id  = isset($_GET['customization_id']) ? (int)$_GET['customization_id'] : 0;
$return_to_pos     = isset($_GET['return_to_pos'])    ? (int)$_GET['return_to_pos']    : 0;
$status            = isset($_GET['status'])           ? trim($_GET['status'])           : '';
$source_order_id   = isset($_GET['source_order_id'])  ? (int)$_GET['source_order_id']  : 0;

// Determine the right customization ID to open in the modal.
// Some builds pass only customization_id, others pass order_id as the cust id.
$modal_id = $customization_id > 0 ? $customization_id : $order_id;

$params['order_id']  = $modal_id;
$params['job_type']  = 'CUSTOMIZATION';
$params['status']    = ($status !== '') ? $status : 'APPROVED';

if ($return_to_pos) {
    $params['return_to_pos'] = '1';
}

// Pass source_order_id only when set so customizations.php can link the order.
if ($source_order_id > 0) {
    $params['source_order_id'] = $source_order_id;
} elseif ($order_id > 0 && $order_id !== $modal_id) {
    // order_id was a real order id (not the customization primary key)
    $params['source_order_id'] = $order_id;
}

$target = BASE_PATH . '/staff/customizations.php?' . http_build_query($params);

header('Location: ' . $target, true, 302);
exit;
