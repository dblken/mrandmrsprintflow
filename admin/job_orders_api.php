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
            $itemId = (int)($_POST['item_id'] ?? 0);
            $qty = (float)($_POST['quantity'] ?? 1);
            $uom = sanitize($_POST['uom'] ?? 'pcs');
            $rollId = !empty($_POST['roll_id']) ? (int)$_POST['roll_id'] : null;
            $notes = sanitize($_POST['notes'] ?? '');
            $metadata = isset($_POST['metadata']) ? json_decode($_POST['metadata'], true) : null;
            
            if (!$orderId || !$itemId) throw new Exception("Incomplete material data.");
            $res = JobOrderService::addMaterial($orderId, $itemId, $qty, $uom, $rollId, $notes, $metadata);
            echo json_encode(['success' => true, 'id' => $res]);
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

        case 'save_ink_usage':
            $orderId = (int)($_POST['order_id'] ?? 0);
            $inkDataSrc = $_POST['ink_data'] ?? '[]';
            $inkData = json_decode($inkDataSrc, true);
            if (!$orderId) throw new Exception("Order ID required.");
            
            $res = JobOrderService::saveInkUsage($orderId, $inkData);
            echo json_encode(['success' => $res]);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
