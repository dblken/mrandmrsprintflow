<?php
/**
 * Ensures `products.product_type` exists (ENUM fixed|custom). Older DBs may lack it.
 */
function printflow_ensure_products_product_type_column(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    global $conn;
    $check = @$conn->query("SHOW COLUMNS FROM `products` LIKE 'product_type'");
    if (!$check || $check->num_rows > 0) {
        if ($check) {
            $check->free();
        }
        return;
    }
    $check->free();
    $sql = "ALTER TABLE `products` ADD COLUMN `product_type` ENUM('fixed','custom') NOT NULL DEFAULT 'custom' AFTER `category`";
    if (!@$conn->query($sql)) {
        $fallback = "ALTER TABLE `products` ADD COLUMN `product_type` ENUM('fixed','custom') NOT NULL DEFAULT 'custom'";
        if (!@$conn->query($fallback)) {
            error_log('ensure_products_schema: ' . $conn->error);
        }
    }
}
