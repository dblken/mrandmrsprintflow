<?php
/**
 * Inventory Manager (v2)
 * Unified service for all PrintFlow inventory types.
 */

require_once __DIR__ . '/db.php';

class InventoryManager {

    /**
     * Records a new inventory transaction and enforces idempotency.
     */
    public static function recordTransaction($itemId, $direction, $quantity, $uom, $refType, $refId, $rollId = null, $notes = '', $userId = null, $date = null) {
        global $conn;
        
        $date = $date ?: date('Y-m-d');
        $quantity = abs((float)$quantity);
        $userId = $userId ?: ($_SESSION['user_id'] ?? null);

        // Deduplication check (UNIQUE key in DB will also catch this but we can handle it gracefully here)
        // Note: The DB unique key is `uq_txn_ref` (ref_type, ref_id, item_id, direction, roll_id)
        
        $sql = "INSERT INTO inventory_transactions (item_id, roll_id, direction, quantity, uom, ref_type, ref_id, notes, created_by, transaction_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssssis", $itemId, $rollId, $direction, $quantity, $uom, $refType, $refId, $notes, $userId, $date);
        
        try {
            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                $stmt->close();
                return $id;
            }
        } catch (Exception $e) {
            // Error 1062 is Duplicate Entry
            if (isset($conn->errno) && $conn->errno == 1062) {
                return true; 
            }
            throw new Exception("Ledger recording failed: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Receives new stock (IN).
     */
    public static function receiveStock($itemId, $quantity, $uom = null, $rollData = null) {
        global $conn;
        
        $item = self::getItem($itemId);
        if (!$item) throw new Exception("Item not found.");

        $conn->begin_transaction();
        try {
            $uom = $uom ?: $item['unit_of_measure'];
            $rollId = null;

            // Handle roll creation if it's a roll item and data is provided
            if ($item['track_by_roll']) {
                require_once __DIR__ . '/RollService.php';
                $rollCode = $rollData['roll_code'] ?? '';
                if (empty($rollCode)) {
                    $rollCode = 'AUTO-' . strtoupper(substr($item['name'], 0, 3)) . '-' . date('YmdHis');
                }
                $rollId = RollService::createRoll(
                    $itemId, 
                    $quantity, // For new reception, total length = quantity received
                    $rollCode, 
                    $rollData['supplier'] ?? null
                );
            }

            // Record transaction
            self::recordTransaction($itemId, 'IN', $quantity, $uom, 'PURCHASE', null, $rollId);

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($conn->in_transaction) $conn->rollback();
            throw $e;
        }
    }

    /**
     * Issues stock for non-roll items (OUT).
     */
    public static function issueStock($itemId, $quantity, $uom = null, $refType = 'ADJUSTMENT', $refId = null, $notes = '', $ignoreRollCheck = false, $allowNegativeBypass = false) {
        $item = self::getItem($itemId);
        if (!$item) throw new Exception("Item not found.");
        
        if ($item['track_by_roll'] && !$ignoreRollCheck) {
            throw new Exception("Use RollService::deductFromRoll for roll-tracked items.");
        }

        $soh = self::getStockOnHand($itemId);
        // For roll-based items used with ignoreRollCheck, skip the SOH check since stock lives in inv_rolls
        $skipSohCheck = ($item['track_by_roll'] && $ignoreRollCheck) || $allowNegativeBypass;
        if (!$skipSohCheck && $soh < $quantity && !$item['allow_negative_stock']) {
            throw new Exception("Insufficient stock for '{$item['name']}'. Have: $soh, Need: $quantity");
        }

        return self::recordTransaction($itemId, 'OUT', $quantity, $uom ?: $item['unit_of_measure'], $refType, $refId, null, $notes);
    }

    /**
     * Gets accurate Stock On Hand based on v2 rules.
     */
    public static function getStockOnHand($itemId) {
        $item = self::getItem($itemId);
        if (!$item) return 0;

        if ($item['track_by_roll']) {
            // Stock is the sum of remaining lengths of all OPEN rolls
            $sql = "SELECT SUM(remaining_length_ft) as soh FROM inv_rolls WHERE item_id = ? AND status = 'OPEN'";
            $res = db_query($sql, 'i', [$itemId]);
            return (float)($res[0]['soh'] ?? 0);
        } else {
            // Stock is the sum of IN - sum of OUT transactions
            $sql = "SELECT SUM(IF(direction='IN', quantity, -quantity)) as soh FROM inventory_transactions WHERE item_id = ?";
            $res = db_query($sql, 'i', [$itemId]);
            return (float)($res[0]['soh'] ?? 0);
        }
    }

    /**
     * Get item details.
     */
    public static function getItem($id) {
        $res = db_query("SELECT * FROM inv_items WHERE id = ?", 'i', [$id]);
        return $res[0] ?? null;
    }

    /**
     * Convenience method for roll deduction (used by TarpaulinService).
     */
    public static function deductRollMaterial($orderItemId, $rollId, $requiredLength) {
        require_once __DIR__ . '/RollService.php';
        // We use orderItemId as the jobOrderId for now, or we might need to look up the order_id
        $item = db_query("SELECT order_id FROM order_items WHERE order_item_id = ?", 'i', [$orderItemId]);
        $orderId = $item[0]['order_id'] ?? 0;
        
        return RollService::deductFromRoll($rollId, $requiredLength, $orderId, null);
    }
}
