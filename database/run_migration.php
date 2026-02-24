<?php
/**
 * PrintFlow - Variant Migration Runner
 * Run once via CLI: php run_migration.php
 * Or visit: http://localhost/printflow/database/run_migration.php
 * (Protect this file - delete after running!)
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '1234');
define('DB_NAME', 'printflow_1');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
$conn->set_charset('utf8mb4');

$steps = [];

// ---------------------------------------------------------------
// Helper
// ---------------------------------------------------------------
function run_sql($conn, $label, $sql) {
    global $steps;
    // multi_query supports multiple statements
    if ($conn->query($sql)) {
        $steps[] = "✅ $label";
    } else {
        $steps[] = "❌ $label — " . $conn->error;
    }
}

function column_exists($conn, $table, $column) {
    $db = DB_NAME;
    $res = $conn->query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'"
    );
    return $res && $res->num_rows > 0;
}

function fk_exists($conn, $table, $fk_name) {
    $db = DB_NAME;
    $res = $conn->query(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA='$db' AND TABLE_NAME='$table'
           AND CONSTRAINT_NAME='$fk_name' AND CONSTRAINT_TYPE='FOREIGN KEY'"
    );
    return $res && $res->num_rows > 0;
}

// ---------------------------------------------------------------
// 1. product_variants
// ---------------------------------------------------------------
run_sql($conn, 'Create product_variants table', "
CREATE TABLE IF NOT EXISTS `product_variants` (
  `variant_id`   INT           NOT NULL AUTO_INCREMENT,
  `product_id`   INT           NOT NULL,
  `variant_name` VARCHAR(150)  NOT NULL,
  `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`       ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at`   TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`variant_id`),
  KEY `idx_pv_product` (`product_id`),
  CONSTRAINT `pv_product_fk` FOREIGN KEY (`product_id`)
    REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

// ---------------------------------------------------------------
// 2. variant_materials
// ---------------------------------------------------------------
run_sql($conn, 'Create variant_materials table', "
CREATE TABLE IF NOT EXISTS `variant_materials` (
  `id`                INT           NOT NULL AUTO_INCREMENT,
  `variant_id`        INT           NOT NULL,
  `material_id`       INT           NOT NULL,
  `quantity_required` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_vm_variant`  (`variant_id`),
  KEY `idx_vm_material` (`material_id`),
  CONSTRAINT `vm_variant_fk` FOREIGN KEY (`variant_id`)
    REFERENCES `product_variants`(`variant_id`) ON DELETE CASCADE,
  CONSTRAINT `vm_material_fk` FOREIGN KEY (`material_id`)
    REFERENCES `materials`(`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

// ---------------------------------------------------------------
// 3. material_usage_logs
// ---------------------------------------------------------------
run_sql($conn, 'Create material_usage_logs table', "
CREATE TABLE IF NOT EXISTS `material_usage_logs` (
  `log_id`            INT           NOT NULL AUTO_INCREMENT,
  `order_id`          INT           NOT NULL,
  `order_item_id`     INT           DEFAULT NULL,
  `variant_id`        INT           DEFAULT NULL,
  `material_id`       INT           NOT NULL,
  `quantity_deducted` DECIMAL(10,2) NOT NULL,
  `deducted_at`       TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_mul_order`    (`order_id`),
  KEY `idx_mul_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
");

// ---------------------------------------------------------------
// 4. Add variant_id column to order_items (if not exists)
// ---------------------------------------------------------------
if (!column_exists($conn, 'order_items', 'variant_id')) {
    run_sql($conn, 'Add variant_id column to order_items', "
        ALTER TABLE `order_items`
          ADD COLUMN `variant_id` INT NULL DEFAULT NULL AFTER `product_id`
    ");
} else {
    $steps[] = "ℹ️ order_items.variant_id already exists — skipped";
}

// ---------------------------------------------------------------
// 5. Add FK on order_items.variant_id (if not exists)
// ---------------------------------------------------------------
if (!fk_exists($conn, 'order_items', 'oi_variant_fk')) {
    run_sql($conn, 'Add FK oi_variant_fk on order_items', "
        ALTER TABLE `order_items`
          ADD CONSTRAINT `oi_variant_fk`
            FOREIGN KEY (`variant_id`)
            REFERENCES `product_variants`(`variant_id`)
            ON DELETE SET NULL
    ");
} else {
    $steps[] = "ℹ️ FK oi_variant_fk already exists — skipped";
}

$conn->close();

// ---------------------------------------------------------------
// Output
// ---------------------------------------------------------------
$is_cli = PHP_SAPI === 'cli';
if (!$is_cli) echo "<pre style='font-family:monospace;font-size:14px;'>\n";
echo "PrintFlow Variant Migration\n";
echo str_repeat("=", 40) . "\n";
foreach ($steps as $step) {
    echo $step . "\n";
}
echo str_repeat("=", 40) . "\n";
echo "Done.\n";
if (!$is_cli) echo "</pre>";
