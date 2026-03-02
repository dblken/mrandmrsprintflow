<?php
/**
 * Inventory Service
 * Handles business logic for transaction-based inventory system.
 */

require_once __DIR__ . '/functions.php';

class InventoryService {

    /**
     * Records a new inventory transaction and updates the cached current_stock.
     * 
     * @param int $itemId The ID of the item
     * @param string $type The transaction type (opening_balance, purchase, issue, adjustment_up, adjustment_down, return, transfer_in, transfer_out)
     * @param float $quantity The quantity (positive or negative depending on type)
     * @param string $date The transaction date (YYYY-MM-DD)
     * @param string|null $refType Reference type (e.g., JobOrder)
     * @param int|null $refId Reference ID
     * @param string $notes Optional notes
     * @param int|null $userId User ID making the transaction
     * @return int The ID of the new transaction
     * @throws Exception If negative stock is not allowed and transaction would result in negative stock
     */
    public static function recordTransaction($itemId, $type, $quantity, $date, $refType = null, $refId = null, $notes = '', $userId = null) {
        // 1. Validate Item
        $item = db_query("SELECT * FROM inv_items WHERE id = ?", 'i', [$itemId]);
        if (!$item) {
            throw new Exception("Item not found.");
        }
        $item = $item[0];

        // 2. Determine if it's an IN or OUT transaction based on type
        $outTypes = ['issue', 'adjustment_down', 'transfer_out'];
        $isOut = in_array($type, $outTypes);
        
        // Ensure quantity sign is correct: IN should be positive, OUT should be negative
        $finalQuantity = $isOut ? -abs((float)$quantity) : abs((float)$quantity);

        // 3. Check for negative stock if it's an OUT transaction
        if ($isOut && !$item['allow_negative_stock']) {
            $currentStock = self::getStockOnHand($itemId);
            if (($currentStock + $finalQuantity) < 0) {
                throw new Exception("Insufficient stock for item '{$item['name']}'. Available: {$currentStock}, Requested: " . abs($finalQuantity));
            }
        }

        // 4. Insert Transaction
        $sql = "INSERT INTO inventory_transactions 
                (item_id, transaction_date, transaction_type, quantity, reference_type, reference_id, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        // We use a custom DB insert function here assuming one exists, or build the query
        // Since we don't have a direct execute that returns ID easily with the standard functions without modifications,
        // we'll use the global $conn object directly for this specific transaction to ensure atomicity and get insert ID.
        
        global $conn;
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            
            $stmt->bind_param("issdsisi", $itemId, $date, $type, $finalQuantity, $refType, $refId, $notes, $userId);
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
            
            $transactionId = $stmt->insert_id;
            $stmt->close();

            // 5. Update cached current_stock on the item table
            $updateSql = "UPDATE inv_items SET current_stock = current_stock + ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) throw new Exception("Update prepare failed: " . $conn->error);
            
            $updateStmt->bind_param("di", $finalQuantity, $itemId);
            if (!$updateStmt->execute()) throw new Exception("Update execute failed: " . $updateStmt->error);
            
            $updateStmt->close();

            // Commit
            $conn->commit();
            
            return $transactionId;
        } catch (Throwable $e) {
            if ($conn->in_transaction) {
                $conn->rollback();
            }
            throw new Exception("Failed to record transaction: " . $e->getMessage());
        }
    }

    /**
     * Gets the dynamically calculated Stock On Hand (SOH) from transactions.
     * This is the source of truth, compared to the cached `current_stock` column.
     * 
     * @param int $itemId
     * @return float
     */
    public static function getStockOnHand($itemId) {
        $result = db_query("SELECT COALESCE(SUM(quantity), 0) as soh FROM inventory_transactions WHERE item_id = ?", 'i', [$itemId]);
        return $result ? (float)$result[0]['soh'] : 0.00;
    }

    /**
     * Recalculates and heals the cached `current_stock` column for all items.
     * Use this periodically (e.g., via cron) or if desync is suspected.
     */
    public static function healStockCache() {
        $sql = "UPDATE inv_items i 
                SET current_stock = (
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM inventory_transactions 
                    WHERE item_id = i.id
                )";
        return db_execute($sql);
    }
    
    /**
     * Calculate Roll Equivalent for a specific item
     */
    public static function getRollEquivalent($itemId) {
        $item = db_query("SELECT current_stock, roll_length_ft FROM inv_items WHERE id = ?", 'i', [$itemId]);
        if ($item && !empty($item[0]['roll_length_ft']) && $item[0]['roll_length_ft'] > 0) {
            return (float)$item[0]['current_stock'] / (float)$item[0]['roll_length_ft'];
        }
        return null;
    }
}
