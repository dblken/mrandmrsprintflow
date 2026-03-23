<?php
/**
 * Job Orders API
 * Admin/Staff CRUD for job orders and material assignment.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/JobOrderService.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

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
            
            // Pagination
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per_page = isset($_GET['customer_id'])
                ? 10
                : min(500, max(1, (int)($_GET['per_page'] ?? 250)));
            $offset = ($page - 1) * $per_page;
            
            // Get total count
            $count_sql = str_replace('SELECT jo.*, c.first_name, c.last_name, c.customer_type, c.transaction_count FROM', 'SELECT COUNT(*) as total FROM', $sql);
            $total_count = db_query($count_sql, $types ?: null, $params ?: null)[0]['total'] ?? 0;
            
            $sql .= " ORDER BY jo.priority = 'HIGH' DESC, jo.due_date ASC, jo.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $per_page; $params[] = $offset; $types .= 'ii';
            $orders = db_query($sql, $types ?: null, $params ?: null) ?: [];
            
            if (!empty($orders)) {
                $orderIds = array_column($orders, 'id');
                $ids_str = implode(',', array_map('intval', $orderIds));

                // 1. Batch Fetch ALL Materials for these jobs
                $materials = db_query("SELECT m.*, i.track_by_roll FROM job_order_materials m JOIN inv_items i ON m.item_id = i.id WHERE m.job_order_id IN ($ids_str)") ?: [];
                $materialsByJob = [];
                $item_ids_needed = [];
                foreach ($materials as $m) {
                    $materialsByJob[$m['job_order_id']][] = $m;
                    $item_ids_needed[] = $m['item_id'];
                    $meta = json_decode($m['metadata'] ?? '{}', true);
                    if (!empty($meta['lamination_item_id'])) $item_ids_needed[] = $meta['lamination_item_id'];
                }

                // 2. Batch Fetch ALL Inks for these jobs
                $inks = db_query("SELECT * FROM job_order_ink_usage WHERE job_order_id IN ($ids_str)") ?: [];
                $inksByJob = [];
                foreach ($inks as $ink) {
                    $inksByJob[$ink['job_order_id']][] = $ink;
                    $item_ids_needed[] = $ink['item_id'];
                }

                // 3. Batch Fetch SOH for all items needed
                $stockMap = [];
                if (!empty($item_ids_needed)) {
                    $unique_items = array_unique($item_ids_needed);
                    $items_str = implode(',', array_map('intval', $unique_items));
                    
                    // From rolls
                    $rollStocks = db_query("SELECT item_id, SUM(remaining_length_ft) as soh FROM inv_rolls WHERE item_id IN ($items_str) AND status = 'OPEN' GROUP BY item_id") ?: [];
                    foreach ($rollStocks as $rs) $stockMap[$rs['item_id']] = (float)$rs['soh'];
                    
                    // From transactions (for non-roll items)
                    $transStocks = db_query("SELECT item_id, SUM(IF(direction='IN', quantity, -quantity)) as soh FROM inventory_transactions WHERE item_id IN ($items_str) GROUP BY item_id") ?: [];
                    foreach ($transStocks as $ts) {
                        if (!isset($stockMap[$ts['item_id']])) $stockMap[$ts['item_id']] = (float)$ts['soh'];
                    }
                }

                // 4. Enrich orders using the pre-fetched data
                foreach ($orders as &$jo) {
                    $jo['order_type'] = 'JOB';
                    $jobMats = $materialsByJob[$jo['id']] ?? [];
                    $jobInks = $inksByJob[$jo['id']] ?? [];
                    
                    // Calculate readiness
                    $readiness = 'READY';
                    $total_cost = 0;
                    
                    foreach ($jobMats as $m) {
                        $qty_needed = ($m['track_by_roll'] == 1) ? (float)($m['computed_required_length_ft'] ?: 0) : (float)$m['quantity'];
                        $itemStock = $stockMap[$m['item_id']] ?? 0;
                        
                        if ($itemStock <= 0) $readiness = 'MISSING';
                        elseif ($itemStock < $qty_needed && $readiness !== 'MISSING') $readiness = 'LOW';
                        
                        $total_cost += $qty_needed * (float)$m['unit_cost_at_assignment'];

                        // Check lamination
                        $meta = json_decode($m['metadata'] ?? '{}', true);
                        if (!empty($meta['lamination_item_id']) && !empty($meta['lamination_length_ft'])) {
                            $lamStock = $stockMap[$meta['lamination_item_id']] ?? 0;
                            if ($lamStock <= 0) $readiness = 'MISSING';
                            elseif ($lamStock < (float)$meta['lamination_length_ft'] && $readiness !== 'MISSING') $readiness = 'LOW';
                        }
                    }
                    
                    foreach ($jobInks as $ink) {
                        $inkStock = $stockMap[$ink['item_id']] ?? 0;
                        if ($inkStock < (float)$ink['quantity_used']) $readiness = 'MISSING';
                    }

                    $jo['readiness'] = $readiness;
                    $jo['estimated_cost'] = $total_cost;
                }
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

        case 'resolve_job_for_order':
            if (!in_array(get_user_type() ?? '', ['Admin', 'Staff'], true)) {
                throw new Exception('Unauthorized');
            }
            $orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
            if (!$orderId) {
                throw new Exception('order_id required');
            }
            $row = db_query('SELECT id FROM job_orders WHERE order_id = ? ORDER BY id ASC LIMIT 1', 'i', [$orderId]);
            $jobId = $row[0]['id'] ?? null;
            // Checkout sometimes leaves orders without job_orders if job creation failed — backfill from order_items (same rules as checkout)
            if ($jobId === null) {
                $created = JobOrderService::ensureJobsForStoreOrder($orderId);
                $jobId = $created !== null ? $created : null;
            }
            echo json_encode(['success' => true, 'job_id' => $jobId !== null ? (int)$jobId : null]);
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
                        TRIM(CONCAT_WS(', ', NULLIF(TRIM(c.street_address), ''), NULLIF(TRIM(c.barangay), ''), NULLIF(TRIM(c.city), ''))) as customer_contact,
                        'ORDER' as order_type,
                        COALESCE(MAX(p.category), 'Custom Order') as service_type,
                        GROUP_CONCAT(DISTINCT CONCAT(p.name, ' - ', oi.quantity, 'pcs') SEPARATOR ', ') as job_title,
                        '1' as width_ft,
                        '1' as height_ft,
                        SUM(oi.quantity) as quantity,
                        CASE 
                            WHEN o.status IN ('Pending', 'Pending Review', 'Pending Approval', 'For Revision') THEN 'PENDING'
                            WHEN o.status IN ('Design Approved', 'Approved') THEN 'APPROVED'
                            WHEN o.status IN ('Pending Verification', 'Downpayment Submitted') THEN 'VERIFY_PAY'
                            WHEN o.status IN ('To Pay', 'Paid – In Process') THEN 'TO_PAY'
                            WHEN o.status IN ('Processing', 'In Production', 'Printing') THEN 'IN_PRODUCTION'
                            WHEN o.status = 'Ready for Pickup' THEN 'TO_RECEIVE'
                            WHEN o.status = 'Completed' THEN 'COMPLETED'
                            WHEN o.status = 'Cancelled' THEN 'CANCELLED'
                            ELSE o.status
                        END as status,
                        CASE 
                            WHEN o.status IN ('Pending Verification', 'Downpayment Submitted') THEN 'SUBMITTED'
                            WHEN o.status IN ('Completed', 'Ready for Pickup', 'Processing', 'In Production', 'Printing', 'Paid – In Process') THEN 'VERIFIED'
                            ELSE 'NONE'
                        END as payment_proof_status,
                        'NO' as payment_status,
                        '' as materials,
                        o.order_date as created_at,
                        o.order_date,
                        NULL as due_date,
                        NULL as priority,
                        o.total_amount as estimated_total,
                        (SELECT MIN(jo.id) FROM job_orders jo WHERE jo.order_id = o.order_id) AS job_order_id
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
            unset($order);

            // Service purchases (service_orders) — same dashboard shape; order_type SERVICE
            service_order_ensure_tables();
            $svc_sql = "SELECT 
                    so.id AS id,
                    so.id AS order_id,
                    so.customer_id,
                    c.first_name,
                    c.last_name,
                    c.customer_type,
                    c.transaction_count,
                    TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) AS customer_full_name,
                    TRIM(CONCAT_WS(', ', NULLIF(TRIM(c.street_address), ''), NULLIF(TRIM(c.barangay), ''), NULLIF(TRIM(c.city), ''))) AS customer_contact,
                    'SERVICE' AS order_type,
                    so.service_name AS service_type,
                    so.service_name AS job_title,
                    '1' AS width_ft,
                    '1' AS height_ft,
                    1 AS quantity,
                    CASE 
                        WHEN so.status IN ('Pending Review', 'Pending', 'Pending Approval', 'For Revision') THEN 'PENDING'
                        WHEN so.status = 'Approved' THEN 'APPROVED'
                        WHEN so.status = 'Processing' THEN 'IN_PRODUCTION'
                        WHEN so.status IN ('Ready for Pickup', 'Ready For Pickup') THEN 'TO_RECEIVE'
                        WHEN so.status = 'Completed' THEN 'COMPLETED'
                        WHEN so.status IN ('Rejected', 'Cancelled') THEN 'CANCELLED'
                        ELSE 'PENDING'
                    END AS status,
                    'PAID' AS payment_proof_status,
                    'NO' AS payment_status,
                    '' AS materials,
                    so.created_at AS created_at,
                    so.created_at AS order_date,
                    NULL AS due_date,
                    NULL AS priority,
                    so.total_price AS estimated_total
                FROM service_orders so
                LEFT JOIN customers c ON so.customer_id = c.customer_id
                WHERE so.status IN ('Pending Review', 'Pending', 'Pending Approval', 'For Revision', 'Approved', 'Processing', 'Ready for Pickup', 'Ready For Pickup')
                ORDER BY so.created_at DESC
                LIMIT 50";
            $svc_orders = db_query($svc_sql) ?: [];
            foreach ($svc_orders as &$so) {
                $so['readiness'] = 'READY';
                $so['estimated_cost'] = 0;
            }
            unset($so);

            $merged = array_merge($pending_orders, $svc_orders);
            usort($merged, function ($a, $b) {
                $ta = strtotime($a['created_at'] ?? $a['order_date'] ?? 'now');
                $tb = strtotime($b['created_at'] ?? $b['order_date'] ?? 'now');
                return $tb <=> $ta;
            });

            echo json_encode(['success' => true, 'data' => $merged]);
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
                'Pending Verification' => 'VERIFY_PAY', 'Downpayment Submitted' => 'VERIFY_PAY',
                'To Pay' => 'TO_PAY', 'Paid – In Process' => 'TO_PAY',
                'Processing' => 'IN_PRODUCTION', 'In Production' => 'IN_PRODUCTION', 'Printing' => 'IN_PRODUCTION',
                'Ready for Pickup' => 'TO_RECEIVE', 'Completed' => 'COMPLETED', 'Cancelled' => 'CANCELLED'
            ];
            $db_status = $o['status'] ?? '';
            $mapped_status = $status_map[$db_status] ?? $db_status;
            
            // Map payment proof status for staff dashboard
            $payment_proof_status = 'NONE';
            if (in_array($db_status, ['Pending Verification', 'Downpayment Submitted'])) {
                $payment_proof_status = 'SUBMITTED';
            } elseif (in_array($db_status, ['Completed', 'Ready for Pickup', 'Processing', 'In Production', 'Printing', 'Paid – In Process'])) {
                $payment_proof_status = 'VERIFIED';
            }

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
            $service_name = get_service_name_from_customization($first_custom, $items_out[0]['product_name'] ?? 'Custom Order');
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
                'payment_proof_status' => $payment_proof_status,
                'payment_proof_path'   => $o['payment_proof'] ?? null,
                'payment_submitted_amount' => (float)($o['downpayment_amount'] ?? 0),
                'payment_proof_uploaded_at' => $o['payment_submitted_at'] ?? null,
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
            $reason = sanitize($_POST['reason'] ?? '');
            if (!$id || !$status) throw new Exception("ID and status required.");
            
            if ($status === 'For Revision' && $reason !== '') {
                db_execute("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[REVISION REQUEST] ', ?) WHERE id = ?", 'si', [$reason, $id]);
                $o = db_query("SELECT order_id FROM job_orders WHERE id = ?", 'i', [$id]);
                if (!empty($o) && !empty($o[0]['order_id'])) {
                    require_once __DIR__ . '/../includes/functions.php';
                    add_order_system_message($o[0]['order_id'], "Revision required: " . $reason);
                    db_execute("UPDATE orders SET revision_reason = ? WHERE order_id = ?", 'si', [$reason, $o[0]['order_id']]);
                }
            }
            
            $res = JobOrderService::updateStatus($id, $status, $machineId);
            echo json_encode(['success' => $res]);
            break;

        case 'update_order_price':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            if (!$order_id) throw new Exception("Order ID required.");
            $sql = "UPDATE orders SET total_amount = ? WHERE order_id = ?";
            $res = db_execute($sql, 'di', [$price, $order_id]);
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
