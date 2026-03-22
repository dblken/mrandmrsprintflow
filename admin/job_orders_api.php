<?php
/**
 * Job Orders API
 * Admin/Staff CRUD for job orders and material assignment.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/JobOrderService.php';

require_role(['Admin', 'Staff', 'Customer']); // Allow Admin (read), Staff (manage), Customer (create/track)
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list_orders':
            $status = sanitize($_GET['status'] ?? '');
            $sql = "SELECT jo.*, c.first_name, c.last_name, c.customer_type, c.transaction_count 
                    FROM job_orders jo 
                    LEFT JOIN customers c ON jo.customer_id = c.customer_id 
                    WHERE 1=1";
            $params = []; $types = '';
            if ($status) {
                $sql .= " AND jo.status = ?";
                $params[] = $status; $types .= 's';
            }
            if (isset($_GET['customer_id'])) {
                $sql .= " AND jo.customer_id = ?";
                $params[] = (int)$_GET['customer_id']; $types .= 'i';
            }
            
            // Pagination for customer-specific requests
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = isset($_GET['customer_id']) ? 10 : 50; // Limit to 10 for customer profile
            $offset = ($page - 1) * $per_page;
            
            // Get total count for pagination
            $count_sql = str_replace('SELECT jo.*, c.first_name, c.last_name, c.customer_type, c.transaction_count FROM', 'SELECT COUNT(*) as total FROM', $sql);
            $total_count = db_query($count_sql, $types ?: null, $params ?: null)[0]['total'] ?? 0;
            
            $sql .= " ORDER BY jo.priority = 'HIGH' DESC, jo.due_date ASC, jo.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $per_page; $params[] = $offset; $types .= 'ii';
            $orders = db_query($sql, $types ?: null, $params ?: null) ?: [];
            
            // Enrich with readiness and cost
            foreach ($orders as &$jo) {
                $jo['readiness'] = JobOrderService::getMaterialReadiness($jo['id']);
                $jo['estimated_cost'] = JobOrderService::calculateJobCost($jo['id']);
            }
            
            $response = ['success' => true, 'data' => $orders];
            if (isset($_GET['customer_id'])) {
                $response['pagination'] = [
                    'current_page' => $page,
                    'total_pages' => max(1, ceil($total_count / $per_page)),
                    'total_items' => $total_count,
                    'per_page' => $per_page
                ];
            }
            
            echo json_encode($response);
            break;

        case 'list_pending_orders':
            // Fetch regular product orders with pending status for staff customization dashboard
            $sql = "SELECT 
                        o.order_id as id,
                        o.order_id,
                        o.customer_id,
                        c.first_name,
                        c.last_name,
                        c.customer_type,
                        c.transaction_count,
                        CONCAT(c.first_name, ' ', c.last_name) as customer_full_name,
                        COALESCE(c.contact_number, c.email, '') as customer_contact,
                        'ORDER' as order_type,
                        'Standard Order' as service_type,
                        GROUP_CONCAT(DISTINCT CONCAT(p.name, ' - ', oi.quantity, 'pcs') SEPARATOR ', ') as job_title,
                        '1' as width_ft,
                        '1' as height_ft,
                        SUM(oi.quantity) as quantity,
                        CASE 
                            WHEN o.status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision') THEN 'PENDING'
                            WHEN o.status IN ('Design Approved', 'Approved') THEN 'APPROVED'
                            WHEN o.status IN ('Pending Verification', 'Downpayment Submitted') THEN 'PENDING'
                            WHEN o.status IN ('To Pay', 'Paid – In Process') THEN 'TO_PAY'
                            WHEN o.status IN ('Processing', 'In Production', 'Printing') THEN 'IN_PRODUCTION'
                            WHEN o.status = 'Ready for Pickup' THEN 'TO_RECEIVE'
                            WHEN o.status = 'Completed' THEN 'COMPLETED'
                            WHEN o.status = 'Cancelled' THEN 'CANCELLED'
                            ELSE o.status
                        END as status,
                        'PAID' as payment_proof_status,
                        'NO' as payment_status,
                        '' as materials,
                        o.order_date as created_at,
                        o.order_date,
                        NULL as due_date,
                        NULL as priority,
                        o.total_amount as estimated_total
                    FROM orders o
                    LEFT JOIN order_items oi ON o.order_id = oi.order_id
                    LEFT JOIN products p ON oi.product_id = p.product_id
                    LEFT JOIN customers c ON o.customer_id = c.customer_id
                    WHERE 1=1
                    GROUP BY o.order_id
                    ORDER BY o.order_date DESC
                    LIMIT 50";
            
            $pending_orders = db_query($sql) ?: [];
            
            // Format to match job_orders structure
            foreach ($pending_orders as &$order) {
                $order['readiness'] = 'READY'; // Regular orders don't have material tracking
                $order['estimated_cost'] = 0;
            }
            
            echo json_encode(['success' => true, 'data' => $pending_orders]);
            break;

        case 'list_machines':
            $machines = db_query("SELECT * FROM machines WHERE status = 'ACTIVE'") ?: [];
            echo json_encode(['success' => true, 'data' => $machines]);
            break;

        case 'get_order':
            $id = (int)($_GET['id'] ?? 0);
            $order = JobOrderService::getOrder($id);
            if (!$order) throw new Exception("Order not found.");
            $order['readiness'] = JobOrderService::getMaterialReadiness($id);
            echo json_encode(['success' => true, 'data' => $order]);
            break;

        case 'get_regular_order':
            // Full order details for regular (orders table) - includes items + customization_data
            if (!in_array($_SESSION['user_type'] ?? '', ['Admin', 'Staff', 'Manager'])) {
                throw new Exception("Unauthorized");
            }
            $order_id = (int)($_GET['id'] ?? 0);
            if (!$order_id) throw new Exception("Order ID required.");
            $order_row = db_query("
                SELECT o.*, c.first_name, c.last_name, c.customer_type, c.contact_number, c.email,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_full_name,
                       COALESCE(c.contact_number, c.email, '') as customer_contact
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE o.order_id = ?
            ", 'i', [$order_id]);
            if (empty($order_row)) throw new Exception("Order not found.");
            $o = $order_row[0];
            $status_map = [
                'Pending' => 'PENDING', 'Pending Review' => 'PENDING', 'Pending Approval' => 'PENDING',
                'For Revision' => 'PENDING', 'Design Approved' => 'APPROVED', 'Approved' => 'APPROVED',
                'Pending Verification' => 'PENDING', 'Downpayment Submitted' => 'PENDING',
                'To Pay' => 'TO_PAY', 'Paid – In Process' => 'TO_PAY',
                'Processing' => 'IN_PRODUCTION', 'In Production' => 'IN_PRODUCTION', 'Printing' => 'IN_PRODUCTION',
                'Ready for Pickup' => 'TO_RECEIVE', 'Completed' => 'COMPLETED', 'Cancelled' => 'CANCELLED'
            ];
            $db_status = $o['status'] ?? '';
            $mapped_status = $status_map[$db_status] ?? $db_status;
            $items = db_query("
                SELECT oi.*, p.name as product_name, p.category
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ?
            ", 'i', [$order_id]) ?: [];
            require_once __DIR__ . '/../includes/order_ui_helper.php';
            $items_out = [];
            $first_custom = [];
            $total_qty = 0;
            $width_ft = '1';
            $height_ft = '1';
            foreach ($items as $item) {
                $custom = json_decode($item['customization_data'] ?? '{}', true) ?: [];
                if (empty($first_custom)) $first_custom = $custom;
                $total_qty += (int)$item['quantity'];
                if (!empty($custom['width']) && !empty($custom['height'])) {
                    $width_ft = (string)$custom['width'];
                    $height_ft = (string)$custom['height'];
                } elseif (!empty($custom['dimensions'])) {
                    $d = $custom['dimensions'];
                    if (is_string($d) && preg_match('/^(\d+)\s*[x×]\s*(\d+)$/i', $d, $m)) {
                        $width_ft = $m[1];
                        $height_ft = $m[2];
                    } else {
                        $width_ft = (string)$d;
                        $height_ft = '';
                    }
                }
                $name = $item['product_name'] ?: get_service_name_from_customization($custom, 'Custom Order');
                $items_out[] = [
                    'order_item_id'   => $item['order_item_id'],
                    'product_name'    => $name,
                    'quantity'        => (int)$item['quantity'],
                    'customization'   => $custom,
                    'design_url'     => (!empty($item['design_image']) || !empty($item['design_file']))
                        ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] : null,
                    'reference_url'  => !empty($item['reference_image_file'])
                        ? '/printflow/public/serve_design.php?type=order_item&id=' . (int)$item['order_item_id'] . '&field=reference' : null,
                ];
            }
            $service_name = get_service_name_from_customization($first_custom, $items_out[0]['product_name'] ?? 'Standard Order');
            $data = [
                'id'                   => $o['order_id'],
                'order_id'             => $o['order_id'],
                'order_type'           => 'ORDER',
                'customer_full_name'   => $o['customer_full_name'] ?? trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')),
                'customer_contact'     => $o['customer_contact'] ?? '',
                'customer_type'        => ($o['transaction_count'] ?? 0) <= 1 ? 'NEW' : 'RETURNING',
                'service_type'         => $service_name,
                'job_title'            => implode(', ', array_map(function($i) { return $i['product_name'] . ' - ' . $i['quantity'] . 'pcs'; }, $items_out)),
                'width_ft'             => $width_ft,
                'height_ft'            => $height_ft,
                'quantity'             => $total_qty,
                'status'               => $mapped_status,
                'estimated_total'      => (float)($o['total_amount'] ?? 0),
                'amount_paid'          => (($o['payment_status'] ?? '') === 'Paid') ? (float)($o['total_amount'] ?? 0) : (float)($o['amount_paid'] ?? 0),
                'notes'                => $o['notes'] ?? '',
                'payment_proof_status' => 'PAID',
                'payment_status'       => 'NO',
                'readiness'            => 'READY',
                'items'                => $items_out,
            ];
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'update_status':
            $id = (int)($_POST['id'] ?? 0);
            $status = sanitize($_POST['status'] ?? '');
            $machineId = isset($_POST['machine_id']) ? (int)$_POST['machine_id'] : null;
            if (!$id || !$status) throw new Exception("ID and status required.");
            
            $res = JobOrderService::updateStatus($id, $status, $machineId);
            echo json_encode(['success' => $res]);
            break;

        case 'create_order':
            $service = sanitize($_POST['service_type'] ?? '');
            if (!$service) throw new Exception("Service type required.");
            
            $width = (float)($_POST['width_ft'] ?? 0);
            $height = (float)($_POST['height_ft'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 1);
            $notes = sanitize($_POST['notes'] ?? '');
            
            // 1. Map Service to Materials (Auto-link required items)
            require_once __DIR__ . '/../includes/ServiceAvailabilityChecker.php';
            $materials_rules = db_query(
                "SELECT item_id, rule_type FROM service_material_rules WHERE service_type = ?",
                's', [$service]
            ) ?: [];
            
            $orderMaterials = [];
            foreach ($materials_rules as $rule) {
                $item = InventoryManager::getItem($rule['item_id']);
                
                // If the item is roll-tracked, only auto-link if the width matches
                if ($item['track_by_roll']) {
                    // We assume 'width_ft' is stored in inv_items or we infer from category
                    // For now, let's check if the item name contains the width or if we can match it
                    // A better way: check the 'default_roll_length_ft' or add a width column
                    // Looking at the data, items 13, 14, 15 are 3ft, 4ft, 5ft
                    $match = false;
                    if (strpos($item['name'], (int)$width . 'FT') !== false) $match = true;
                    if (strpos($item['name'], (int)$width . 'ft') !== false) $match = true;
                    if ((int)$item['default_roll_length_ft'] == (int)$width) $match = true; // Fallback

                    if (!$match) continue; 
                }

                $orderMaterials[] = [
                    'item_id' => $rule['item_id'],
                    'quantity' => $qty,
                    'uom' => ($height > 0) ? 'ft' : 'pcs',
                    'computed_len' => ($height > 0) ? ($height * $qty) : 0
                ];
            }

            $orderId = JobOrderService::createOrder([
                'customer_id'     => ($_SESSION['user_type'] === 'Customer') ? $_SESSION['user_id'] : ($_POST['customer_id'] ?? null),
                'customer_name'   => sanitize($_POST['customer_name'] ?? ''),
                'service_type'    => $service,
                'width_ft'        => $width,
                'height_ft'       => $height,
                'quantity'        => $qty,
                'total_sqft'      => $width * $height * $qty,
                'price_per_sqft'  => null, // Staff will fill
                'price_per_piece' => null,
                'estimated_total' => null,
                'notes'           => $notes,
                'artwork_path'    => null,
                'created_by'      => ($_SESSION['user_type'] !== 'Customer') ? $_SESSION['user_id'] : null
            ], $orderMaterials);
            
            echo json_encode(['success' => true, 'id' => $orderId]);
            break;

        case 'assign_roll':
            $jomId = (int)($_POST['jom_id'] ?? 0);
            $rollId = (int)($_POST['roll_id'] ?? 0);
            if (!$jomId || !$rollId) throw new Exception("Incomplete assignment data.");
            
            $res = JobOrderService::assignRoll($jomId, $rollId);
            echo json_encode(['success' => $res]);
            break;

        case 'set_price':
            $id = (int)($_POST['id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            if (!$id) throw new Exception("ID required.");
            // Setting the price also means updating the required payment to match exactly
            $res = db_execute("UPDATE job_orders SET estimated_total = ?, required_payment = ? WHERE id = ?", 'ddi', [$price, $price, $id]);
            echo json_encode(['success' => (bool)$res]);
            break;

        case 'add_material':
            $orderId = (int)($_POST['order_id'] ?? 0);
            $orderType = isset($_POST['order_type']) ? sanitize($_POST['order_type']) : null;
            $itemId = (int)($_POST['item_id'] ?? 0);
            $qty = (float)($_POST['quantity'] ?? 1);
            $uom = sanitize($_POST['uom'] ?? 'pcs');
            $rollId = !empty($_POST['roll_id']) ? (int)$_POST['roll_id'] : null;
            $notes = sanitize($_POST['notes'] ?? '');
            $metadata = isset($_POST['metadata']) ? json_decode($_POST['metadata'], true) : null;
            
            if (!$orderId || !$itemId) throw new Exception("Incomplete material data.");
            $res = JobOrderService::addMaterial($orderId, $itemId, $qty, $uom, $rollId, $notes, $metadata, $orderType);
            echo json_encode(['success' => true, 'id' => $res]);
            break;

        case 'save_ink_usage':
            $orderId = (int)($_POST['order_id'] ?? 0);
            $orderType = isset($_POST['order_type']) ? sanitize($_POST['order_type']) : null;
            $inkData = isset($_POST['ink_data']) ? json_decode($_POST['ink_data'], true) : [];
            
            if (!$orderId) throw new Exception("Order ID required.");
            $res = JobOrderService::saveInkUsage($orderId, $inkData, $orderType);
            echo json_encode(['success' => true]);
            break;

        case 'preview_impact':
            $itemId = (int)($_GET['item_id'] ?? 0);
            $rollId = isset($_GET['roll_id']) ? (int)$_GET['roll_id'] : null;
            $qty = (float)($_GET['quantity'] ?? 0);
            $height = (float)($_GET['height'] ?? 0);
            
            $res = JobOrderService::previewImpact($itemId, $rollId, $qty, $height);
            echo json_encode(['success' => true, 'data' => $res]);
            break;

        case 'remove_material':
            $jomId = (int)($_POST['id'] ?? 0);
            if (!$jomId) throw new Exception("ID required.");
            $res = JobOrderService::removeMaterial($jomId);
            echo json_encode(['success' => $res]);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
