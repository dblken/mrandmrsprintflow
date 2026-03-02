<?php
/**
 * Inventory Items API (v2)
 * CRUD for inventory items and categories.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryManager.php';

require_role(['Admin', 'Staff']);
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_items':
            $cat_id = (int)($_GET['category_id'] ?? 0);
            $search = sanitize($_GET['search'] ?? '');
            
            $sql = "SELECT i.*, c.name as category_name 
                    FROM inv_items i 
                    LEFT JOIN inv_categories c ON i.category_id = c.id 
                    WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($cat_id) {
                $sql .= " AND i.category_id = ?";
                $params[] = $cat_id;
                $types .= 'i';
            }
            if ($search) {
                $sql .= " AND (i.name LIKE ? OR i.sku LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $types .= 'ss';
            }
            $sql .= " ORDER BY i.name ASC";
            
            $items = db_query($sql, $types ?: null, $params ?: null) ?: [];
            
            // Add dynamic SOH info
            foreach ($items as &$item) {
                $item['current_stock'] = InventoryManager::getStockOnHand($item['id']);
                // Compute roll equivalent if applicable
                if ($item['track_by_roll'] && $item['default_roll_length_ft'] > 0) {
                    $item['roll_equivalent'] = round($item['current_stock'] / $item['default_roll_length_ft'], 2);
                } else {
                    $item['roll_equivalent'] = null;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'create_item':
            $name = sanitize($_POST['name'] ?? '');
            if (empty($name)) throw new Exception('Item name is required');
            
            $cat_id = (int)($_POST['category_id'] ?? 0) ?: null;
            $sku = sanitize($_POST['sku'] ?? '') ?: null;
            $unit = sanitize($_POST['unit'] ?? 'pcs');
            $track_by_roll = (int)($_POST['track_by_roll'] ?? 0);
            $roll_length = (float)($_POST['roll_length_ft'] ?? 0) ?: null;
            $min_stock = (float)($_POST['min_stock_level'] ?? 0);
            $allow_negative = (int)($_POST['allow_negative_stock'] ?? 0);
            $unit_cost = (float)($_POST['unit_cost'] ?? 0);
            
            $sql = "INSERT INTO inv_items (category_id, sku, name, unit_of_measure, track_by_roll, default_roll_length_ft, reorder_level, allow_negative_stock, unit_cost) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            global $conn;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssidddd", $cat_id, $sku, $name, $unit, $track_by_roll, $roll_length, $min_stock, $allow_negative, $unit_cost);
            
            if (!$stmt->execute()) throw new Exception("Failed to create item: " . $stmt->error);
            $itemId = $stmt->insert_id;
            
            // Handle initial opening balance if non-roll
            $starting_stock = (float)($_POST['starting_stock'] ?? 0);
            if ($starting_stock > 0 && !$track_by_roll) {
                InventoryManager::recordTransaction($itemId, 'IN', $starting_stock, $unit, 'opening_balance', null, null, 'Initial stock entry');
            }
            
            echo json_encode(['success' => true, 'item_id' => $itemId]);
            break;

        case 'update_item':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            if (!$id || empty($name)) throw new Exception('Item ID and name required');
            
            $cat_id = (int)($_POST['category_id'] ?? 0) ?: null;
            $sku = sanitize($_POST['sku'] ?? '') ?: null;
            $unit = sanitize($_POST['unit'] ?? 'pcs');
            $track_by_roll = (int)($_POST['track_by_roll'] ?? 0);
            $roll_length = (float)($_POST['roll_length_ft'] ?? 0) ?: null;
            $min_stock = (float)($_POST['min_stock_level'] ?? 0);
            $allow_negative = (int)($_POST['allow_negative_stock'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'ACTIVE');
            $unit_cost = (float)($_POST['unit_cost'] ?? 0);
            
            $sql = "UPDATE inv_items 
                    SET category_id=?, sku=?, name=?, unit_of_measure=?, track_by_roll=?, default_roll_length_ft=?, reorder_level=?, allow_negative_stock=?, status=?, unit_cost=? 
                    WHERE id=?";
            
            global $conn;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssiddisdi", $cat_id, $sku, $name, $unit, $track_by_roll, $roll_length, $min_stock, $allow_negative, $status, $unit_cost, $id);
            if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'get_categories':
            $cats = db_query("SELECT * FROM inv_categories ORDER BY sort_order ASC, name ASC") ?: [];
            echo json_encode(['success' => true, 'data' => $cats]);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
