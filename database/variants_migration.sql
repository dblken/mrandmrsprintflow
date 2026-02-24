-- ============================================================
-- PrintFlow: Product Variants & BOM Migration
-- Safe to run multiple times
-- Compatible with MySQL 5.7+ and 8.0+
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
-- ------------------------------------------------------------
-- 1. product_variants
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_variants` (
    `variant_id` INT NOT NULL AUTO_INCREMENT,
    `product_id` INT NOT NULL,
    `variant_name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`variant_id`),
    KEY `idx_pv_product` (`product_id`),
    CONSTRAINT `pv_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;
-- ------------------------------------------------------------
-- 2. variant_materials  (Bill of Materials per variant)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `variant_materials` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `variant_id` INT NOT NULL,
    `material_id` INT NOT NULL,
    `quantity_required` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_vm_variant` (`variant_id`),
    KEY `idx_vm_material` (`material_id`),
    CONSTRAINT `vm_variant_fk` FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`variant_id`) ON DELETE CASCADE,
    CONSTRAINT `vm_material_fk` FOREIGN KEY (`material_id`) REFERENCES `materials`(`material_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;
-- ------------------------------------------------------------
-- 3. material_usage_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `material_usage_logs` (
    `log_id` INT NOT NULL AUTO_INCREMENT,
    `order_id` INT NOT NULL,
    `order_item_id` INT DEFAULT NULL,
    `variant_id` INT DEFAULT NULL,
    `material_id` INT NOT NULL,
    `quantity_deducted` DECIMAL(10, 2) NOT NULL,
    `deducted_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_mul_order` (`order_id`),
    KEY `idx_mul_material` (`material_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;
-- ------------------------------------------------------------
-- 4. Add variant_id to order_items (nullable — backward compatible)
--    Use a stored procedure to check before altering
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS `pf_add_variant_id_column`;
DELIMITER $$ CREATE PROCEDURE `pf_add_variant_id_column`() BEGIN IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'order_items'
        AND COLUMN_NAME = 'variant_id'
) THEN
ALTER TABLE `order_items`
ADD COLUMN `variant_id` INT NULL DEFAULT NULL
AFTER `product_id`;
END IF;
END $$ DELIMITER;
CALL `pf_add_variant_id_column`();
DROP PROCEDURE IF EXISTS `pf_add_variant_id_column`;
-- ------------------------------------------------------------
-- 5. Add FK on order_items.variant_id (if not yet present)
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS `pf_add_variant_fk`;
DELIMITER $$ CREATE PROCEDURE `pf_add_variant_fk`() BEGIN IF NOT EXISTS (
    SELECT 1
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME = 'order_items'
        AND CONSTRAINT_NAME = 'oi_variant_fk'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
) THEN
ALTER TABLE `order_items`
ADD CONSTRAINT `oi_variant_fk` FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`variant_id`) ON DELETE
SET NULL;
END IF;
END $$ DELIMITER;
CALL `pf_add_variant_fk`();
DROP PROCEDURE IF EXISTS `pf_add_variant_fk`;
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- Migration complete.
-- Created:  product_variants, variant_materials, material_usage_logs
-- Modified: order_items (added nullable variant_id + FK)
-- ============================================================