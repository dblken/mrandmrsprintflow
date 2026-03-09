<?php
/**
 * InventoryManager (v3) — Enterprise-Grade
 *
 * Single entry point for ALL stock mutations in PrintFlow.
 * No other code should write directly to inventory_transactions.
 *
 * Key guarantees:
 *  - All mutations run inside a DB transaction (READ COMMITTED isolation).
 *  - Row-level SELECT FOR UPDATE prevents concurrent double-deductions.
 *  - Deadlock/lock-timeout retries (up to MAX_RETRY attempts).
 *  - Idempotency: duplicate (ref_type, ref_id, item_id, direction) pairs are silently skipped.
 *  - Append-only ledger: this class never UPDATE or DELETE from inventory_transactions.
 *  - Strict non-negative stock: issueStock() always throws if insufficient stock.
 *  - allow_negative_stock field is intentionally ignored (legacy DB column only).
 *  - Status-aware: INACTIVE items are blocked from issue/purchase; adjustStock() allows them.
 *  - Optional warehouse_id accepted by all public methods (future multi-location support).
 *
 * @version 3.0
 */

require_once __DIR__ . '/db.php';

class InventoryManager {

    /** Maximum attempts on deadlock / lock-timeout before giving up. */
    const MAX_RETRY = 3;
    /** MySQL deadlock error number. */
    const ERR_DEADLOCK = 1213;
    /** MySQL lock-wait timeout error number. */
    const ERR_LOCK_TIMEOUT = 1205;

    // -------------------------------------------------------------------------
    // PUBLIC MUTATION ENTRY POINTS
    // -------------------------------------------------------------------------

    /**
     * Receive stock (IN). Creates a ledger IN record.
     * For roll-based items, also creates an inv_roll record.
     *
     * @param int         $itemId
     * @param float       $quantity       Quantity to receive
     * @param string|null $uom            Override UOM (uses item default if null)
     * @param array|null  $rollData       ['roll_code'=>..., 'supplier'=>...] for roll items
     * @param string      $refType        Ledger reference type (default: 'PURCHASE')
     * @param int|null    $refId          Related business record ID
     * @param string      $notes          Optional notes
     * @param int|null    $warehouseId    Reserved for future multi-location support
     */
    public static function receiveStock(
        int $itemId,
        float $quantity,
        ?string $uom = null,
        ?array $rollData = null,
        string $refType = 'PURCHASE',
        ?int $refId = null,
        string $notes = '',
        ?int $warehouseId = null
    ) {
        return self::withRetry(function () use ($itemId, $quantity, $uom, $rollData, $refType, $refId, $notes) {
            global $conn;
            $item = self::getItem($itemId);
            if (!$item) throw new Exception("Item not found (id=$itemId).");

            self::beginTransaction();
            try {
                $uom    = $uom ?: $item['unit_of_measure'];
                $rollId = null;

                if ($item['track_by_roll']) {
                    require_once __DIR__ . '/RollService.php';
                    $rollCode = trim($rollData['roll_code'] ?? '');
                    if ($rollCode === '') {
                        $rollCode = 'AUTO-' . strtoupper(substr($item['name'], 0, 3)) . '-' . date('YmdHis');
                    }
                    $rollId = RollService::createRoll(
                        $itemId, 
                        $quantity, 
                        $rollCode, 
                        $rollData['supplier'] ?? null,
                        $rollData['width_ft'] ?? 0
                    );
                }

                self::recordTransaction($itemId, 'IN', $quantity, $uom, $refType, $refId, $rollId, $notes);
                $conn->commit();
                return true;
            } catch (Throwable $e) {
                if ($conn->in_transaction) $conn->rollback();
                throw $e;
            }
        });
    }

    /**
     * Issue stock (OUT). Enforces strict non-negative balance.
     * Roll-tracked items are automatically FIFO-deducted via RollService.
     * INACTIVE items are blocked.
     *
     * @param int         $itemId
     * @param float       $quantity
     * @param string|null $uom
     * @param string      $refType
     * @param int|null    $refId
     * @param string      $notes
     * @param int|null    $warehouseId    Reserved for future multi-location support
     */
    public static function issueStock(
        int $itemId,
        float $quantity,
        ?string $uom = null,
        string $refType = 'ADJUSTMENT',
        ?int $refId = null,
        string $notes = '',
        ?int $warehouseId = null
    ) {
        return self::withRetry(function () use ($itemId, $quantity, $uom, $refType, $refId, $notes) {
            global $conn;
            $item = self::getItem($itemId);
            if (!$item) throw new Exception("Item not found (id=$itemId).");
            if ($item['status'] === 'INACTIVE') {
                throw new Exception("Cannot issue stock for inactive material '{$item['name']}'.");
            }

            // Roll-based items: delegate to FIFO deduction (which handles its own transaction + locking)
            if ($item['track_by_roll']) {
                require_once __DIR__ . '/RollService.php';
                return RollService::deductFIFO($itemId, $quantity, $refType, $refId, $notes);
            }

            self::beginTransaction();
            try {
                // Row-level lock for concurrency safety
                $soh = self::getLockedStockOnHand($itemId);

                if ($soh < $quantity) {
                    self::logFailure('INSUFFICIENT_STOCK', $itemId, $quantity, $soh);
                    throw new Exception(
                        "Insufficient stock for '{$item['name']}'. Available: " .
                        number_format($soh, 2) . " {$item['unit_of_measure']}, Requested: " .
                        number_format($quantity, 2) . "."
                    );
                }

                self::recordTransaction($itemId, 'OUT', $quantity, $uom ?: $item['unit_of_measure'], $refType, $refId, null, $notes);
                $conn->commit();
                return true;
            } catch (Throwable $e) {
                if ($conn->in_transaction) $conn->rollback();
                throw $e;
            }
        });
    }

    /**
     * Adjust stock (positive or negative correction).
     * ALLOWED for INACTIVE items (to correct mistakes).
     *
     * @param int         $itemId
     * @param float       $adjustmentQty  Signed quantity (positive = add, negative = remove)
     * @param string      $refType
     * @param int|null    $refId
     * @param string      $notes
     * @param int|null    $warehouseId    Reserved for future multi-location support
     */
    public static function adjustStock(
        int $itemId,
        float $adjustmentQty,
        string $refType = 'ADJUSTMENT',
        ?int $refId = null,
        string $notes = '',
        ?int $warehouseId = null
    ) {
        return self::withRetry(function () use ($itemId, $adjustmentQty, $refType, $refId, $notes) {
            global $conn;
            $item = self::getItem($itemId);
            if (!$item) throw new Exception("Item not found (id=$itemId).");
            // Note: adjustStock intentionally allows INACTIVE items for correction purposes.

            self::beginTransaction();
            try {
                if ($adjustmentQty < 0) {
                    // Negative adjustment: enforce non-negative balance
                    $soh = self::getLockedStockOnHand($itemId);
                    $deduct = abs($adjustmentQty);
                    if ($soh < $deduct) {
                        self::logFailure('INSUFFICIENT_STOCK_ADJUSTMENT', $itemId, $deduct, $soh);
                        throw new Exception(
                            "Adjustment would result in negative stock for '{$item['name']}'. Available: " .
                            number_format($soh, 2) . ", Attempting to remove: " . number_format($deduct, 2) . "."
                        );
                    }
                    self::recordTransaction($itemId, 'OUT', $deduct, $item['unit_of_measure'], $refType, $refId, null, $notes);
                } else {
                    $qty = abs($adjustmentQty);
                    if ($qty > 0) {
                        self::recordTransaction($itemId, 'IN', $qty, $item['unit_of_measure'], $refType, $refId, null, $notes);
                    }
                }
                $conn->commit();
                return true;
            } catch (Throwable $e) {
                if ($conn->in_transaction) $conn->rollback();
                throw $e;
            }
        });
    }

    /**
     * Convenience wrapper for roll-material deduction (used by job services).
     * Routes directly to RollService::deductFromRoll().
     *
     * @param int $orderItemId
     * @param int $rollId
     * @param float $requiredLength
     * @param int|null $warehouseId    Reserved for future multi-location support
     */
    public static function deductRollStock(
        int $orderItemId,
        int $rollId,
        float $requiredLength,
        ?int $warehouseId = null
    ) {
        require_once __DIR__ . '/RollService.php';
        $item = db_query("SELECT order_id FROM order_items WHERE order_item_id = ?", 'i', [$orderItemId]);
        $orderId = $item[0]['order_id'] ?? 0;
        return RollService::deductFromRoll($rollId, $requiredLength, $orderId, null);
    }

    // -------------------------------------------------------------------------
    // INTERNAL LEDGER RECORDING (append-only, never updates or deletes)
    // -------------------------------------------------------------------------

    /**
     * Appends a single row to inventory_transactions (the ledger).
     * Idempotency: if an identical (ref_type, ref_id, item_id, direction, roll_id) record
     * already exists, the insert is silently skipped and true is returned.
     *
     * This is the ONLY method that writes to inventory_transactions.
     */
    public static function recordTransaction(
        $itemId, $direction, $quantity, $uom,
        $refType, $refId, $rollId = null, $notes = '', $userId = null, $date = null
    ) {
        global $conn;

        $date     = $date ?: date('Y-m-d');
        $quantity = abs((float)$quantity);
        $uom      = $uom ?: 'pcs';
        $userId   = $userId ?: ($_SESSION['user_id'] ?? null);
        
        // Generate a random transaction ID for extra traceability
        $txnId = strtoupper(bin2hex(random_bytes(4)) . '-' . time());

        $sql  = "INSERT INTO inventory_transactions 
                    (item_id, transaction_id, roll_id, direction, quantity, uom, ref_type, ref_id, notes, created_by, transaction_date) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // Types: i (itemId), s (txnId), i (rollId), s (direction), d (qty), s (uom), s (refType), i (refId), s (notes), i (userId), s (date)
        $stmt->bind_param("isisdsssiss", $itemId, $txnId, $rollId, $direction, $quantity, $uom, $refType, $refId, $notes, $userId, $date);

        try {
            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                $stmt->close();
                return $id;
            }
        } catch (Exception $e) {
            // MySQL error 1062 = Duplicate Entry (unique constraint — idempotency key)
            if (isset($conn->errno) && $conn->errno == 1062) {
                return true; // already recorded, skip silently
            }
            throw new Exception("Ledger recording failed: " . $e->getMessage());
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // STOCK QUERIES
    // -------------------------------------------------------------------------

    /**
     * Returns the current Stock On Hand for an item.
     * For roll-tracked items: sums remaining_length_ft of OPEN rolls.
     * For standard items: sums IN - OUT from the ledger.
     */
    public static function getStockOnHand(int $itemId): float {
        $item = self::getItem($itemId);
        if (!$item) return 0.0;

        if ($item['track_by_roll']) {
            $res = db_query("SELECT SUM(remaining_length_ft) as soh FROM inv_rolls WHERE item_id = ? AND status = 'OPEN'", 'i', [$itemId]);
        } else {
            $res = db_query("SELECT SUM(IF(direction='IN', quantity, -quantity)) as soh FROM inventory_transactions WHERE item_id = ?", 'i', [$itemId]);
        }
        return (float)($res[0]['soh'] ?? 0);
    }

    /**
     * Returns SOH using SELECT FOR UPDATE (use only inside an active transaction).
     * Prevents concurrent readers from acting on stale balances.
     */
    private static function getLockedStockOnHand(int $itemId): float {
        global $conn;
        // Lock the item row to prevent concurrent updates
        $stmt = $conn->prepare("SELECT id FROM inv_items WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $stmt->close();

        return self::getStockOnHand($itemId);
    }

    /**
     * Fetches item details from inv_items.
     */
    public static function getItem(int $id): ?array {
        $res = db_query("SELECT * FROM inv_items WHERE id = ?", 'i', [$id]);
        return $res[0] ?? null;
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPERS
    // -------------------------------------------------------------------------

    /**
     * Starts a DB transaction with READ COMMITTED isolation for consistent locking.
     */
    private static function beginTransaction(): void {
        global $conn;
        $conn->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
        $conn->begin_transaction();
    }

    /**
     * Executes a callable with automatic deadlock/lock-timeout retry.
     * Retries up to MAX_RETRY times before re-throwing the exception.
     */
    private static function withRetry(callable $fn) {
        $attempt = 0;
        while (true) {
            try {
                return $fn();
            } catch (Throwable $e) {
                global $conn;
                $errno = $conn->errno ?? 0;
                $attempt++;
                if (in_array($errno, [self::ERR_DEADLOCK, self::ERR_LOCK_TIMEOUT]) && $attempt < self::MAX_RETRY) {
                    // Log the retry for monitoring
                    self::logWarning("DEADLOCK_RETRY attempt={$attempt} errno={$errno}");
                    usleep(50000 * $attempt); // 50ms, 100ms back-off
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Logs a warning-level inventory event for operational monitoring.
     */
    private static function logWarning(string $message): void {
        error_log("[InventoryManager][WARN] " . $message);
    }

    /**
     * Logs a failure-level inventory event for operational monitoring.
     */
    private static function logFailure(string $type, int $itemId, float $requested, float $available): void {
        error_log(sprintf(
            "[InventoryManager][FAIL] type=%s item_id=%d requested=%.2f available=%.2f",
            $type, $itemId, $requested, $available
        ));
    }
}
