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
            // ... (keep as is)
            $cat_id   = (int)($_GET['category_id'] ?? 0);
            $search   = sanitize($_GET['search'] ?? '');
            $sort     = sanitize($_GET['sort'] ?? 'name');
            $dir      = strtoupper(sanitize($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $active_only     = (int)($_GET['active_only'] ?? 0);
            $include_inactive = (int)($_GET['include_inactive'] ?? 1);

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

            if ($active_only) { $sql .= " AND i.status = 'ACTIVE'"; }
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
            foreach ($items as &$item) {
                $item['current_stock'] = InventoryManager::getStockOnHand($item['id']);
                if ($item['track_by_roll'] && $item['default_roll_length_ft'] > 0) {
                    $item['roll_equivalent'] = round($item['current_stock'] / $item['default_roll_length_ft'], 2);
                } else { $item['roll_equivalent'] = null; }
            }
            unset($item);
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'create_item':
        case 'update_item':
            $isUpdate = ($action === 'update_item');
            $errors = [];
            
            $id = $isUpdate ? (int)($_POST['id'] ?? 0) : 0;
            if ($isUpdate && !$id) $errors['id'] = 'Item ID is required.';

            $name = trim(sanitize($_POST['name'] ?? ''));
            if (empty($name)) $errors['name'] = 'Item name is required.';
            else if (strlen($name) < 2 || strlen($name) > 100) $errors['name'] = 'Item name must be between 2 and 100 characters.';
            else if (preg_match('/^\d+$/', $name)) $errors['name'] = 'Item name cannot contain only numbers.';
            else $name = ucwords(strtolower($name));

            $cat_id = (int)($_POST['category_id'] ?? 0);
            if (!$cat_id) $errors['category_id'] = 'Please select a category.';

            $unit = sanitize($_POST['unit'] ?? '');
            if (empty($unit)) $errors['unit'] = 'Please select a unit of measure.';

            $sku = sanitize($_POST['sku'] ?? '') ?: null;
            $track_by_roll = (int)($_POST['track_by_roll'] ?? 0);
            $roll_length = (float)($_POST['roll_length_ft'] ?? 0) ?: null;
            $min_stock = (float)($_POST['min_stock_level'] ?? 0);
            if ($min_stock < 0) $errors['min_stock_level'] = 'Reorder level cannot be negative.';

            $unit_cost = (float)($_POST['unit_cost'] ?? 0);
            if ($unit_cost <= 0) $errors['unit_cost'] = 'Unit cost must be greater than 0.';
            else if ($unit_cost > 1000000) $errors['unit_cost'] = 'Unit cost is too high.';

            if ($unit === 'ft' && ($roll_length === null || $roll_length <= 0)) {
                $errors['roll_length_ft'] = 'Standard Roll Length is required for Feet (ft) UOM.';
            } else if ($roll_length !== null && ($roll_length < 1 || $roll_length > 1000)) {
                $errors['roll_length_ft'] = 'Standard Roll Length must be between 1 and 1000 ft.';
            }

            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }

            if ($isUpdate) {
                $status = in_array(sanitize($_POST['status'] ?? ''), ['ACTIVE', 'INACTIVE']) ? sanitize($_POST['status']) : 'ACTIVE';
                $sql = "UPDATE inv_items SET category_id=?, sku=?, name=?, unit_of_measure=?, track_by_roll=?, default_roll_length_ft=?, reorder_level=?, status=?, unit_cost=? WHERE id=?";
                global $conn;
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssiddsdi", $cat_id, $sku, $name, $unit, $track_by_roll, $roll_length, $min_stock, $status, $unit_cost, $id);
                if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);
                $stmt->close();
                echo json_encode(['success' => true]);
            } else {
                $sql = "INSERT INTO inv_items (category_id, sku, name, unit_of_measure, track_by_roll, default_roll_length_ft, reorder_level, unit_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                global $conn;
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssiddd", $cat_id, $sku, $name, $unit, $track_by_roll, $roll_length, $min_stock, $unit_cost);
                if (!$stmt->execute()) throw new Exception("Create failed: " . $stmt->error);
                $itemId = $stmt->insert_id;
                $stmt->close();
                $starting_stock = max(0, (float)($_POST['starting_stock'] ?? 0));

                // Opening balance behavior:
                // - Standard items: one IN ledger entry (no roll record).
                // - Roll-tracked items: create roll(s) using receiveStock (no DB changes; uses existing inv_rolls + ledger).
                if ($starting_stock > 0) {
                    if (!$track_by_roll) {
                        InventoryManager::receiveStock($itemId, $starting_stock, $unit, null, 'opening_balance', null, 'Initial stock entry');
                    } else {
                        $method = sanitize($_POST['stock_input_method'] ?? '');
                        $starting_rolls = (int)($_POST['starting_rolls'] ?? 0);

                        // For roll items, quantity must be in feet.
                        $uomForRoll = ($unit === 'ft') ? 'ft' : $unit;

                        if ($method === 'rolls' && $starting_rolls > 0 && $roll_length !== null && $roll_length > 0) {
                            // Create N rolls, each of length roll_length.
                            for ($i = 1; $i <= $starting_rolls; $i++) {
                                InventoryManager::receiveStock(
                                    $itemId,
                                    (float)$roll_length,
                                    $uomForRoll,
                                    ['roll_code' => 'OPEN-' . $itemId . '-' . $i],
                                    'opening_balance',
                                    null,
                                    'Initial stock entry'
                                );
                            }
                        } else {
                            // Default: treat starting_stock as total feet and create a single roll.
                            InventoryManager::receiveStock(
                                $itemId,
                                $starting_stock,
                                $uomForRoll,
                                null,
                                'opening_balance',
                                null,
                                'Initial stock entry'
                            );
                        }
                    }
                }
                echo json_encode(['success' => true, 'item_id' => $itemId]);
            }
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
