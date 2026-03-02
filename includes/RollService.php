<?php
/**
 * Roll Service
 * Specialized logic for roll-based material tracking.
 * PrintFlow v2
 */

require_once __DIR__ . '/db.php';

class RollService {

    /**
     * Create a new roll record.
     */
    public static function createRoll($itemId, $totalLength, $rollCode = null, $supplier = null) {
        global $conn;
        
        $sql = "INSERT INTO inv_rolls (item_id, roll_code, total_length_ft, remaining_length_ft, status, supplier, received_at) 
                VALUES (?, ?, ?, ?, 'OPEN', ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdds", $itemId, $rollCode, $totalLength, $totalLength, $supplier);
        
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            return $id;
        }
        
        throw new Exception("Failed to create roll: " . $stmt->error);
    }

    /**
     * Deduct length from a specific roll.
     * Uses SELECT ... FOR UPDATE for concurrency safety.
     * 
     * @param int $rollId
     * @param float $lengthToDeduct
     * @param int $jobOrderId The ID of the job order for ledger reference
     * @param int|null $jobOrderMaterialId To mark as deducted (idempotency)
     * @return bool
     */
    public static function deductFromRoll($rollId, $lengthToDeduct, $jobOrderId, $jobOrderMaterialId = null) {
        global $conn;

        // 1. Lock the roll row
        $stmt = $conn->prepare("SELECT id, item_id, remaining_length_ft, status FROM inv_rolls WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $rollId);
        $stmt->execute();
        $roll = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$roll) throw new Exception("Roll ID #{$rollId} not found.");
        if ($roll['status'] !== 'OPEN') throw new Exception("Roll is no longer open.");
        
        if ($roll['remaining_length_ft'] < $lengthToDeduct) {
            throw new Exception("Insufficient length in roll. Available: {$roll['remaining_length_ft']}ft, Requested: {$lengthToDeduct}ft");
        }

        // 2. Perform Deduction
        $newRemaining = (float)$roll['remaining_length_ft'] - (float)$lengthToDeduct;
        
        // Auto-finish if remaining length is negligible
        $newStatus = ($newRemaining <= 0.01) ? 'FINISHED' : 'OPEN';
        $finAt = ($newStatus === 'FINISHED') ? date('Y-m-d H:i:s') : null;

        $update = $conn->prepare("UPDATE inv_rolls SET remaining_length_ft = ?, status = ?, finished_at = ? WHERE id = ?");
        $update->bind_param("dssi", $newRemaining, $newStatus, $finAt, $rollId);
        if (!$update->execute()) throw new Exception("Failed to update roll balance: " . $update->error);
        $update->close();

        // 3. Record in Ledger (InventoryManager handles the insertion)
        require_once __DIR__ . '/InventoryManager.php';
        InventoryManager::recordTransaction(
            $roll['item_id'], 
            'OUT', 
            $lengthToDeduct, 
            'ft', 
            'JOB_ORDER', 
            $jobOrderId, 
            $rollId, 
            "Deducted for Job #{$jobOrderId}"
        );

        // 4. Update Job Order Material status (if ID provided) to mark as deducted
        if ($jobOrderMaterialId) {
            $stmt = $conn->prepare("UPDATE job_order_materials SET deducted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $jobOrderMaterialId);
            $stmt->execute();
            $stmt->close();
        }

        return true;
    }

    /**
     * Get available rolls for an item.
     */
    public static function getAvailableRolls($itemId) {
        return db_query(
            "SELECT * FROM inv_rolls WHERE item_id = ? AND status = 'OPEN' AND remaining_length_ft > 0 ORDER BY received_at ASC",
            'i',
            [$itemId]
        ) ?: [];
    }
    
    /**
     * Mark a roll as void.
     */
    public static function voidRoll($rollId, $notes = '') {
        return db_execute(
            "UPDATE inv_rolls SET status = 'VOID', notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?",
            'si',
            ["\nVoided: " . $notes, $rollId]
        );
    }
}
