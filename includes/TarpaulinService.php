<?php
/**
 * Tarpaulin Service
 * Handles specialized logic for roll-based tarpaulin inventory.
 */

require_once __DIR__ . '/functions.php';

class TarpaulinService {

    /**
     * Deducts inventory for ALL tarpaulin items in an order.
     */
    public static function deductInventoryForOrder($orderId) {
        $details = db_query("SELECT order_item_id, roll_id, required_length_ft FROM order_tarp_details WHERE is_deducted = 0 AND order_item_id IN (SELECT order_item_id FROM order_items WHERE order_id = ?)", 'i', [$orderId]);
        
        if (!$details) return true;

        foreach ($details as $d) {
            self::deductRollInventory($d['order_item_id'], $d['roll_id'], $d['required_length_ft']);
            db_execute("UPDATE order_tarp_details SET is_deducted = 1 WHERE order_item_id = ?", 'i', [$d['order_item_id']]);
        }
        return true;
    }

    /**
     * Records a deduction for a job order item from a specific roll.
     * Enforces idempotency via the UNIQUE key on inventory_transactions.
     */
    public static function deductRollInventory($orderItemId, $rollId, $requiredLength) {
        require_once __DIR__ . '/InventoryManager.php';
        try {
            // InventoryManager::deductRollMaterial handles transactions, roll updates, and ledger recording
            return InventoryManager::deductRollMaterial($orderItemId, $rollId, $requiredLength);
        } catch (Throwable $e) {
            throw new Exception("Failed to deduct tarpaulin: " . $e->getMessage());
        }
    }

    /**
     * Get available rolls for a given width
     */
    public static function getAvailableRolls($width) {
        // Query from common inv_rolls table
        return db_query("SELECT id, roll_code, remaining_length_ft FROM inv_rolls WHERE width_ft = ? AND status = 'OPEN' ORDER BY received_at ASC", 'i', [$width]) ?: [];
    }

    /**
     * Helper to calculate required length
     */
    public static function calculateLength($height, $qty) {
        return (float)$height * (int)$qty;
    }
}
?>
