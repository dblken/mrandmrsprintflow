<?php
/**
 * Inventory Items API (v2)
 * CRUD for inventory items and categories.
 *
 * active_only=1   → only ACTIVE items (default for POS/Orders)
 * include_inactive=1 → all items (for management views)
 * Default: include all (for backward compatibility with management pages)
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
            $cat_id   = (int)($_GET['category_id'] ?? 0);
            $search   = sanitize($_GET['search'] ?? '');
            $sort     = sanitize($_GET['sort'] ?? 'name');
            $dir      = strtoupper(sanitize($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $active_only     = (int)($_GET['active_only'] ?? 0);
            $include_inactive = (int)($_GET['include_inactive'] ?? 1); // default: show all in management view

            $sort_cols = [
                'name'          => 'i.name',
                'sku'           => 'i.sku',
                'category_name' => 'category_name',
                'track_by_roll' => 'i.track_by_roll',
                'unit_cost'     => 'i.unit_cost',
                'reorder_level' => 'i.reorder_level',
            ];
            $orderBy = $sort_cols[$sort] ?? 'i.name';

            $sql    = "SELECT i.*, c.name as category_name 
                       FROM inv_items i 
                       LEFT JOIN inv_categories c ON i.category_id = c.id 
                       WHERE 1=1";
            $params = [];
            $types  = '';

            // Status filter: active_only takes precedence
            if ($active_only) {
                $sql .= " AND i.status = 'ACTIVE'";
            }
            // If neither flag is set, include all (management default)

            if ($cat_id) {
                $sql .= " AND i.category_id = ?";
                $params[] = $cat_id;
                $types   .= 'i';
            }
            if ($search) {
                $sql .= " AND (i.name LIKE ? OR i.sku LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $types   .= 'ss';
            }
            $sql .= " ORDER BY $orderBy $dir";

            $items = db_query($sql, $types ?: null, $params ?: null) ?: [];

            // Add dynamic SOH info
            foreach ($items as &$item) {
                $item['current_stock'] = InventoryManager::getStockOnHand($item['id']);
                if ($item['track_by_roll'] && $item['default_roll_length_ft'] > 0) {
                    $item['roll_equivalent'] = round($item['current_stock'] / $item['default_roll_length_ft'], 2);
                } else {
                    $item['roll_equivalent'] = null;
                }
            }
            unset($item);

            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'create_item':
            $name = sanitize($_POST['name'] ?? '');
            if (empty($name)) throw new Exception('Item name is required');

            $cat_id        = (int)($_POST['category_id'] ?? 0) ?: null;
            $sku           = sanitize($_POST['sku'] ?? '') ?: null;
            $unit          = sanitize($_POST['unit'] ?? 'pcs');
            $track_by_roll = (int)($_POST['track_by_roll'] ?? 0);
            $roll_length   = (float)($_POST['roll_length_ft'] ?? 0) ?: null;
            $min_stock     = max(0, (float)($_POST['min_stock_level'] ?? 0));
            $unit_cost     = max(0, (float)($_POST['unit_cost'] ?? 0));

            // Roll length validation: required when UOM is 'ft'
            if ($unit === 'ft' && ($roll_length === null || $roll_length <= 0)) {
                throw new Exception('Standard Roll Length is required for Feet (ft) UOM and must be greater than 0.');
            }
            if ($roll_length !== null && ($roll_length < 1 || $roll_length > 1000)) {
                throw new Exception('Standard Roll Length must be between 1 and 1000 ft.');
            }

            // NOTE: allow_negative_stock field is preserved in DB for legacy data, but we no longer
            // accept or apply it via the application. All stock is strictly non-negative.
            $sql = "INSERT INTO inv_items (category_id, sku, name, unit_of_measure, track_by_roll, default_roll_length_ft, reorder_level, unit_cost) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            global $conn;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssiddd", $cat_id, $sku, $name, $unit, $track_by_roll, $roll_length, $min_stock, $unit_cost);

            if (!$stmt->execute()) throw new Exception("Failed to create item: " . $stmt->error);
            $itemId = $stmt->insert_id;
            $stmt->close();

            // Handle initial opening balance if non-roll
            $starting_stock = max(0, (float)($_POST['starting_stock'] ?? 0));
            if ($starting_stock > 0 && !$track_by_roll) {
                InventoryManager::receiveStock($itemId, $starting_stock, $unit, null, 'opening_balance', null, 'Initial stock entry');
            }

            echo json_encode(['success' => true, 'item_id' => $itemId]);
            break;

        case 'update_item':
            $id   = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            if (!$id || empty($name)) throw new Exception('Item ID and name required');

            $cat_id        = (int)($_POST['category_id'] ?? 0) ?: null;
            $sku           = sanitize($_POST['sku'] ?? '') ?: null;
            $unit          = sanitize($_POST['unit'] ?? 'pcs');
            $track_by_roll = (int)($_POST['track_by_roll'] ?? 0);
            $roll_length   = (float)($_POST['roll_length_ft'] ?? 0) ?: null;
            $min_stock     = max(0, (float)($_POST['min_stock_level'] ?? 0));
            $status        = in_array(sanitize($_POST['status'] ?? ''), ['ACTIVE', 'INACTIVE']) ? sanitize($_POST['status']) : 'ACTIVE';
            $unit_cost     = max(0, (float)($_POST['unit_cost'] ?? 0));

            // Roll length validation
            if ($unit === 'ft' && ($roll_length === null || $roll_length <= 0)) {
                throw new Exception('Standard Roll Length is required for Feet (ft) UOM and must be greater than 0.');
            }
            if ($roll_length !== null && ($roll_length < 1 || $roll_length > 1000)) {
                throw new Exception('Standard Roll Length must be between 1 and 1000 ft.');
            }

            // NOTE: allow_negative_stock is intentionally NOT updated — field exists in DB for legacy purposes only.
            $sql = "UPDATE inv_items 
                    SET category_id=?, sku=?, name=?, unit_of_measure=?, track_by_roll=?, default_roll_length_ft=?, reorder_level=?, status=?, unit_cost=? 
                    WHERE id=?";

            global $conn;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssiddsdi", $cat_id, $sku, $name, $unit, $track_by_roll, $roll_length, $min_stock, $status, $unit_cost, $id);
            if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);
            $stmt->close();

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
