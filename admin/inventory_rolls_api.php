<?php
/**
 * Inventory Rolls API
 * Roll-specific management.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/RollService.php';

require_role('Admin');
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list_rolls':
            $itemId = (int)($_GET['item_id'] ?? 0);
            if (!$itemId) throw new Exception("Item ID required.");
            $rolls = RollService::getAvailableRolls($itemId);
            echo json_encode(['success' => true, 'data' => $rolls]);
            break;

        case 'add_roll':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $len = (float)($_POST['total_length'] ?? 0);
            $code = sanitize($_POST['roll_code'] ?? '');
            $supplier = sanitize($_POST['supplier'] ?? '');
            
            if (!$itemId || $len <= 0) throw new Exception("Invalid item or length.");
            
            $rollId = RollService::createRoll($itemId, $len, $code, $supplier);
            echo json_encode(['success' => true, 'roll_id' => $rollId]);
            break;

        case 'void_roll':
            $rollId = (int)($_POST['roll_id'] ?? 0);
            $notes = sanitize($_POST['notes'] ?? '');
            if (!$rollId) throw new Exception("Roll ID required.");
            
            $res = RollService::voidRoll($rollId, $notes);
            echo json_encode(['success' => $res]);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
