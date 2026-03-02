<?php
/**
 * Inventory Transactions API
 * Handles recording and fetching stock movements
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryManager.php';

require_role('Admin');
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user = get_logged_in_user();

try {
    switch ($action) {
        case 'get_transactions':
            $item_id = (int)($_GET['item_id'] ?? 0);
            $type = sanitize($_GET['type'] ?? '');
            $start_date = sanitize($_GET['start_date'] ?? '');
            $end_date = sanitize($_GET['end_date'] ?? '');
            
            $sql = "SELECT t.*, i.name as item_name, i.unit_of_measure as unit, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                    FROM inventory_transactions t
                    JOIN inv_items i ON t.item_id = i.id
                    LEFT JOIN users u ON t.created_by = u.user_id
                    WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($item_id) {
                $sql .= " AND t.item_id = ?";
                $params[] = $item_id;
                $types .= 'i';
            }
            if ($type) {
                // If type is IN or OUT filter by direction
                if (in_array(strtoupper($type), ['IN', 'OUT'])) {
                    $sql .= " AND t.direction = ?";
                } else {
                    $sql .= " AND t.ref_type = ?";
                }
                $params[] = $type;
                $types .= 's';
            }
            if ($start_date && $end_date) {
                $sql .= " AND t.transaction_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= 'ss';
            }
            
            $sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT 500";
            
            $transactions = db_query($sql, $types ?: null, $params ?: null) ?: [];
            
            echo json_encode(['success' => true, 'data' => $transactions]);
            break;

        case 'record_transaction':
            $item_id = (int)($_POST['item_id'] ?? 0);
            $type = sanitize($_POST['transaction_type'] ?? '');
            $quantity = (float)($_POST['quantity'] ?? 0);
            $date = sanitize($_POST['transaction_date'] ?? date('Y-m-d'));
            $ref_type = sanitize($_POST['reference_type'] ?? '') ?: null;
            $ref_id = (int)($_POST['reference_id'] ?? 0) ?: null;
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (!$item_id || empty($type) || $quantity == 0) {
                throw new Exception("Item, valid Type, and non-zero Quantity are required.");
            }
            
            // Mapping old types to direction
            $inTypes = ['opening_balance', 'purchase', 'return', 'transfer_in', 'adjustment_up'];
            $direction = in_array($type, $inTypes) ? 'IN' : 'OUT';

            if ($direction === 'IN') {
                $rollData = null;
                if (!empty($_POST['roll_code'])) {
                    $rollData = ['roll_code' => sanitize($_POST['roll_code'])];
                }
                
                // For IN transactions, use receiveStock to handle roll tracking logic
                $success = InventoryManager::receiveStock($item_id, $quantity, $_POST['uom'] ?? null, $rollData);
                $transactionId = 0; // receiveStock doesn't return the ID, but we can return success
            } else {
                $transactionId = InventoryManager::recordTransaction(
                    $item_id, 
                    $direction, 
                    $quantity, 
                    $_POST['uom'] ?? null, 
                    $type, 
                    $ref_id, 
                    null, 
                    $notes, 
                    $user['user_id'] ?? null,
                    $date
                );
            }
            
            echo json_encode(['success' => true, 'transaction_id' => $transactionId]);
            break;

        case 'get_current_stock':
            $item_id = (int)($_GET['item_id'] ?? 0);
            if (!$item_id) throw new Exception("Item ID required");
            $soh = InventoryManager::getStockOnHand($item_id);
            echo json_encode(['success' => true, 'soh' => $soh]);
            break;

        case 'get_history':
            $item_id = (int)($_GET['item_id'] ?? 0);
            if (!$item_id) throw new Exception("Item ID required");
            
            // Get last 30 days of data
            $days = [];
            for ($i = 29; $i >= 0; $i--) {
                $days[] = date('Y-m-d', strtotime("-$i days"));
            }
            
            $start = $days[0];
            $end = $days[29];
            
            // 1. Get initial stock BEFORE the 30-day window
            $preBalance = db_query(
                "SELECT SUM(IF(direction='IN', quantity, -quantity)) as balance FROM inventory_transactions WHERE item_id = ? AND transaction_date < ?",
                'is', [$item_id, $start]
            );
            $runningBalance = (float)$preBalance[0]['balance'];
            
            // 2. Get all movements in the 30-day window
            $movements = db_query(
                "SELECT DATE(created_at) as t_date, SUM(IF(direction='IN', quantity, -quantity)) as daily_total 
                 FROM inventory_transactions 
                 WHERE item_id = ? AND created_at BETWEEN ? AND ? 
                 GROUP BY t_date ORDER BY t_date ASC",
                'iss', [$item_id, $start . ' 00:00:00', $end . ' 23:59:59']
            );
            
            $movementMap = [];
            foreach ($movements as $m) {
                $movementMap[$m['transaction_date']] = (float)$m['daily_total'];
            }
            
            // 3. Build the daily trend
            $history = [];
            foreach ($days as $date) {
                $change = $movementMap[$date] ?? 0;
                $runningBalance += $change;
                $history[] = [
                    'date' => $date,
                    'stock' => $runningBalance,
                    'change' => $change
                ];
            }
            
            echo json_encode(['success' => true, 'data' => $history]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
