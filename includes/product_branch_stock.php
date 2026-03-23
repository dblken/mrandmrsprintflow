<?php
/**
 * Per-branch stock for catalog products (products table stays canonical for admins).
 * Staff POS / manager UI use branch rows when present; otherwise fall back to products.stock_quantity.
 */

if (!defined('PRODUCT_BRANCH_STOCK_LOADED')) {
    define('PRODUCT_BRANCH_STOCK_LOADED', true);
}

require_once __DIR__ . '/db.php';

/**
 * Create product_branch_stock if missing (idempotent).
 */
function printflow_ensure_product_branch_stock_table(): void {
    static $done = false;
    if ($done) {
        return;
    }
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS `product_branch_stock` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT NOT NULL,
        `branch_id` INT NOT NULL,
        `stock_quantity` INT NOT NULL DEFAULT 0,
        `low_stock_level` INT NOT NULL DEFAULT 10,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_product_branch` (`product_id`, `branch_id`),
        KEY `idx_branch` (`branch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    @$conn->query($sql);
    $done = true;
}

/**
 * Effective quantity + low threshold for a product at a branch.
 *
 * @return array{0:int,1:int} [stock_quantity, low_stock_level]
 */
function printflow_product_effective_stock(int $productId, int $branchId): array {
    printflow_ensure_product_branch_stock_table();
    if ($productId <= 0 || $branchId <= 0) {
        return [0, 10];
    }
    $pbs = db_query(
        'SELECT stock_quantity, low_stock_level FROM product_branch_stock WHERE product_id = ? AND branch_id = ? LIMIT 1',
        'ii',
        [$productId, $branchId]
    );
    if (!empty($pbs)) {
        return [(int)$pbs[0]['stock_quantity'], (int)$pbs[0]['low_stock_level']];
    }
    $p = db_query(
        'SELECT stock_quantity, COALESCE(low_stock_level, 10) AS low_stock_level FROM products WHERE product_id = ? LIMIT 1',
        'i',
        [$productId]
    );
    if (empty($p)) {
        return [0, 10];
    }
    return [(int)$p[0]['stock_quantity'], (int)$p[0]['low_stock_level']];
}

/**
 * Upsert branch-level stock (managers).
 */
function printflow_product_branch_stock_upsert(int $productId, int $branchId, int $stockQty, int $lowLevel): bool {
    printflow_ensure_product_branch_stock_table();
    if ($productId <= 0 || $branchId <= 0) {
        return false;
    }
    $res = db_execute(
        'INSERT INTO product_branch_stock (product_id, branch_id, stock_quantity, low_stock_level)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity), low_stock_level = VALUES(low_stock_level)',
        'iiii',
        [$productId, $branchId, $stockQty, $lowLevel]
    );
    return $res !== false;
}

/**
 * Deduct sold quantity at branch: branch row if exists, else products.stock_quantity.
 */
function printflow_product_deduct_stock_for_branch(int $productId, int $branchId, int $qty): bool {
    printflow_ensure_product_branch_stock_table();
    if ($productId <= 0 || $qty <= 0) {
        return false;
    }
    if ($branchId > 0) {
        $row = db_query(
            'SELECT stock_quantity FROM product_branch_stock WHERE product_id = ? AND branch_id = ? LIMIT 1',
            'ii',
            [$productId, $branchId]
        );
        if (!empty($row)) {
            $cur = (int)$row[0]['stock_quantity'];
            if ($cur < $qty) {
                return false;
            }
            $u = db_execute(
                'UPDATE product_branch_stock SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND branch_id = ? AND stock_quantity >= ?',
                'iiii',
                [$qty, $productId, $branchId, $qty]
            );
            return $u !== false;
        }
    }
    $p = db_query('SELECT stock_quantity FROM products WHERE product_id = ? LIMIT 1', 'i', [$productId]);
    if (empty($p) || (int)$p[0]['stock_quantity'] < $qty) {
        return false;
    }
    $u = db_execute(
        'UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?',
        'iii',
        [$qty, $productId, $qty]
    );
    return $u !== false;
}
