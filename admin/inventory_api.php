<?php
/**
 * Inventory API — AJAX Endpoint
 * PrintFlow - Dynamic Inventory Module
 * Handles all AJAX requests for inventory operations
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ─── Categories ───────────────────────────────────
        case 'get_categories':
            $cats = db_query("SELECT * FROM material_categories ORDER BY category_name ASC");
            echo json_encode(['success' => true, 'data' => $cats ?: []]);
            break;

        case 'create_category':
            $name = sanitize($_POST['category_name'] ?? '');
            if (empty($name)) throw new Exception('Category name is required');
            $id = db_execute("INSERT INTO material_categories (category_name) VALUES (?)", 's', [$name]);
            echo json_encode(['success' => true, 'category_id' => $id]);
            break;

        case 'delete_category':
            $id = (int)($_POST['category_id'] ?? 0);
            if (!$id) throw new Exception('Invalid category');
            db_execute("DELETE FROM material_categories WHERE category_id = ?", 'i', [$id]);
            echo json_encode(['success' => true]);
            break;

        // ─── Materials ────────────────────────────────────
        case 'get_materials':
            $cat_id = (int)($_GET['category_id'] ?? 0);
            $sql = "SELECT * FROM materials";
            $params = [];
            $types = '';
            if ($cat_id) {
                $sql .= " WHERE category_id = ?";
                $params[] = $cat_id;
                $types = 'i';
            }
            $sql .= " ORDER BY material_name ASC";
            $mats = db_query($sql, $types, $params);
            echo json_encode(['success' => true, 'data' => $mats ?: []]);
            break;

        case 'create_material':
            $cat_id = (int)($_POST['category_id'] ?? 0);
            $name = sanitize($_POST['material_name'] ?? '');
            $opening = (float)($_POST['opening_stock'] ?? 0);
            $unit = sanitize($_POST['unit'] ?? 'ft');
            if (!$cat_id || empty($name)) throw new Exception('Category and material name are required');
            $id = db_execute(
                "INSERT INTO materials (category_id, material_name, opening_stock, current_stock, unit) VALUES (?, ?, ?, ?, ?)",
                'isdds', [$cat_id, $name, $opening, $opening, $unit]
            );
            echo json_encode(['success' => true, 'material_id' => $id]);
            break;

        case 'update_material':
            $mid = (int)($_POST['material_id'] ?? 0);
            $name = sanitize($_POST['material_name'] ?? '');
            $opening = (float)($_POST['opening_stock'] ?? 0);
            $unit = sanitize($_POST['unit'] ?? 'ft');
            if (!$mid) throw new Exception('Invalid material');
            db_execute(
                "UPDATE materials SET material_name = ?, opening_stock = ?, unit = ? WHERE material_id = ?",
                'sdsi', [$name, $opening, $unit, $mid]
            );
            echo json_encode(['success' => true]);
            break;

        case 'delete_material':
            $mid = (int)($_POST['material_id'] ?? 0);
            if (!$mid) throw new Exception('Invalid material');
            db_execute("DELETE FROM materials WHERE material_id = ?", 'i', [$mid]);
            echo json_encode(['success' => true]);
            break;

        // ─── Monthly Data ─────────────────────────────────
        case 'get_monthly_data':
            $cat_id = (int)($_GET['category_id'] ?? 0);
            $month  = (int)($_GET['month'] ?? date('n'));
            $year   = (int)($_GET['year'] ?? date('Y'));
            if (!$cat_id) throw new Exception('Category required');

            // Get materials in this category
            $materials = db_query(
                "SELECT material_id, material_name, opening_stock, unit FROM materials WHERE category_id = ? ORDER BY material_name ASC",
                'i', [$cat_id]
            );

            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            $result = [];
            foreach ($materials ?: [] as $mat) {
                $mid = $mat['material_id'];

                // Get all movements for this material in this month
                $movements = db_query(
                    "SELECT DAY(movement_date) as day_num, quantity_change 
                     FROM material_stock_movements 
                     WHERE material_id = ? AND MONTH(movement_date) = ? AND YEAR(movement_date) = ?
                     ORDER BY movement_date ASC",
                    'iii', [$mid, $month, $year]
                );

                // Build day map
                $day_data = [];
                $total_change = 0;
                foreach ($movements ?: [] as $mv) {
                    $day_data[(int)$mv['day_num']] = (float)$mv['quantity_change'];
                    $total_change += (float)$mv['quantity_change'];
                }

                $result[] = [
                    'material_id'   => $mid,
                    'material_name' => $mat['material_name'],
                    'opening_stock' => (float)$mat['opening_stock'],
                    'unit'          => $mat['unit'],
                    'days'          => $day_data,
                    'total_stock'   => (float)$mat['opening_stock'] + $total_change
                ];
            }

            echo json_encode([
                'success'       => true,
                'days_in_month' => $days_in_month,
                'month'         => $month,
                'year'          => $year,
                'materials'     => $result
            ]);
            break;

        // ─── Save Movement (Upsert) ──────────────────────
        case 'save_movement':
            $mid   = (int)($_POST['material_id'] ?? 0);
            $date  = $_POST['movement_date'] ?? '';
            $qty   = (float)($_POST['quantity_change'] ?? 0);
            $notes = sanitize($_POST['notes'] ?? 'Manual entry');

            if (!$mid || empty($date)) throw new Exception('Material ID and date required');

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new Exception('Invalid date format');

            if ($qty == 0) {
                // Remove the movement if quantity is zero
                db_execute(
                    "DELETE FROM material_stock_movements WHERE material_id = ? AND movement_date = ?",
                    'is', [$mid, $date]
                );
            } else {
                // Upsert: insert or update on duplicate key
                global $conn;
                $stmt = $conn->prepare(
                    "INSERT INTO material_stock_movements (material_id, movement_date, quantity_change, notes)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity_change = VALUES(quantity_change), notes = VALUES(notes)"
                );
                $stmt->bind_param('isds', $mid, $date, $qty, $notes);
                $stmt->execute();
                $stmt->close();
            }

            // Recalculate current_stock
            $total = db_query(
                "SELECT COALESCE(SUM(quantity_change), 0) as total FROM material_stock_movements WHERE material_id = ?",
                'i', [$mid]
            );
            $mat = db_query("SELECT opening_stock FROM materials WHERE material_id = ?", 'i', [$mid]);
            $new_stock = ((float)($mat[0]['opening_stock'] ?? 0)) + ((float)($total[0]['total'] ?? 0));
            db_execute("UPDATE materials SET current_stock = ? WHERE material_id = ?", 'di', [$new_stock, $mid]);

            echo json_encode(['success' => true, 'new_total' => $new_stock]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
