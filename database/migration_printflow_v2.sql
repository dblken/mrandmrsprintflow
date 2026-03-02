-- ============================================================
-- PrintFlow v2 — Full Inventory & Job Order System Migration
-- Run via phpMyAdmin > Import (select database printflow_1 first)
-- Idempotent: uses IF NOT EXISTS / DROP IF EXISTS safely
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- ============================================================
-- 1. inv_categories
-- ============================================================
DROP TABLE IF EXISTS `service_material_rules`;
DROP TABLE IF EXISTS `job_order_materials`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `inv_rolls`;
DROP TABLE IF EXISTS `inv_items`;
DROP TABLE IF EXISTS `inv_categories`;
CREATE TABLE IF NOT EXISTS `inv_categories` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `sort_order` TINYINT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cat_name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- 2. inv_items
-- ============================================================
CREATE TABLE IF NOT EXISTS `inv_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `category_id` INT NULL,
    `sku` VARCHAR(50) NULL,
    `name` VARCHAR(150) NOT NULL,
    `unit_of_measure` VARCHAR(20) NOT NULL DEFAULT 'pcs',
    `track_by_roll` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = roll-based, stock = SUM(remaining_length_ft of OPEN rolls)',
    `default_roll_length_ft` DECIMAL(10, 2) NULL COMMENT 'Standard roll total length (e.g. 164 ft)',
    `reorder_level` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `allow_negative_stock` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item_sku` (`sku`),
    KEY `idx_item_cat` (`category_id`),
    CONSTRAINT `fk_item_cat` FOREIGN KEY (`category_id`) REFERENCES `inv_categories`(`id`) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- 3. inv_rolls  (Tarpaulin + Printed Sticker rolls)
-- ============================================================
CREATE TABLE IF NOT EXISTS `inv_rolls` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `item_id` INT NOT NULL,
    `roll_code` VARCHAR(40) NULL COMMENT 'Optional label/barcode for the physical roll',
    `total_length_ft` DECIMAL(10, 2) NOT NULL DEFAULT 164.00,
    `remaining_length_ft` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('OPEN', 'FINISHED', 'VOID') NOT NULL DEFAULT 'OPEN',
    `supplier` VARCHAR(120) NULL,
    `notes` TEXT NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_roll_item_status` (`item_id`, `status`),
    CONSTRAINT `fk_roll_item` FOREIGN KEY (`item_id`) REFERENCES `inv_items`(`id`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- 4. job_orders
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_orders` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `customer_id` INT NULL COMMENT 'NULL = walk-in / POS customer',
    `customer_name` VARCHAR(120) NULL COMMENT 'Walk-in name override',
    `service_type` ENUM(
        'Tarpaulin Printing',
        'T-shirt Printing',
        'Decals/Stickers (Print/Cut)',
        'Glass Stickers / Wall / Frosted Stickers',
        'Transparent Stickers',
        'Layouts',
        'Reflectorized (Subdivision Stickers/Signages)',
        'Stickers on Sintraboard',
        'Sintraboard Standees',
        'Souvenirs'
    ) NOT NULL,
    `status` ENUM(
        'PENDING',
        'APPROVED',
        'IN_PRODUCTION',
        'COMPLETED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'PENDING',
    `width_ft` DECIMAL(10, 2) NULL,
    `height_ft` DECIMAL(10, 2) NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `total_sqft` DECIMAL(10, 2) NULL COMMENT 'Computed: width × height × qty (for roll-based)',
    `price_per_sqft` DECIMAL(10, 2) NULL,
    `price_per_piece` DECIMAL(10, 2) NULL,
    `estimated_total` DECIMAL(12, 2) NULL,
    `notes` TEXT NULL,
    `artwork_path` TEXT NULL,
    `assigned_to` INT NULL COMMENT 'staff user_id',
    `created_by` INT NULL COMMENT 'user_id who created (admin/staff) or NULL if customer',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jo_status` (`status`),
    KEY `idx_jo_customer` (`customer_id`),
    CONSTRAINT `fk_jo_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`customer_id`) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- 5. job_order_materials
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_order_materials` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `job_order_id` BIGINT NOT NULL,
    `item_id` INT NOT NULL,
    `roll_id` INT NULL COMMENT 'Set for roll-based items',
    `quantity` DECIMAL(10, 2) NOT NULL DEFAULT 1,
    `uom` VARCHAR(20) NOT NULL DEFAULT 'pcs',
    `computed_required_length_ft` DECIMAL(10, 2) NULL COMMENT 'height_ft × qty for roll items',
    `deducted_at` DATETIME NULL COMMENT 'NULL = not yet deducted (idempotency)',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jom_order_item` (`job_order_id`, `item_id`, `roll_id`),
    KEY `idx_jom_item` (`item_id`),
    KEY `idx_jom_roll` (`roll_id`),
    CONSTRAINT `fk_jom_order` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jom_item` FOREIGN KEY (`item_id`) REFERENCES `inv_items`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_jom_roll` FOREIGN KEY (`roll_id`) REFERENCES `inv_rolls`(`id`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- 6. inventory_transactions  (unified ledger)
-- Handles both roll-based and non-roll items
-- UNIQUE key prevents double-deductions (idempotency)
-- ============================================================
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `item_id` INT NOT NULL,
    `roll_id` INT NULL,
    `direction` ENUM('IN', 'OUT') NOT NULL,
    `quantity` DECIMAL(10, 2) NOT NULL,
    `uom` VARCHAR(20) NOT NULL DEFAULT 'pcs',
    `ref_type` VARCHAR(30) NULL COMMENT 'JOB_ORDER | PURCHASE | ADJUSTMENT | VOID',
    `ref_id` BIGINT NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `transaction_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Prevents double-deduction for the same job order + item + roll combination
    UNIQUE KEY `uq_txn_ref` (
        `ref_type`,
        `ref_id`,
        `item_id`,
        `direction`,
        `roll_id`
    ) USING BTREE,
    KEY `idx_txn_item_date` (`item_id`, `transaction_date`),
    KEY `idx_txn_roll` (`roll_id`),
    KEY `idx_txn_ref` (`ref_type`, `ref_id`),
    CONSTRAINT `fk_txn_item` FOREIGN KEY (`item_id`) REFERENCES `inv_items`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_txn_roll` FOREIGN KEY (`roll_id`) REFERENCES `inv_rolls`(`id`) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- 7. service_material_rules  (configurable mapping)
-- ============================================================
CREATE TABLE IF NOT EXISTS `service_material_rules` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `service_type` ENUM(
        'Tarpaulin Printing',
        'T-shirt Printing',
        'Decals/Stickers (Print/Cut)',
        'Glass Stickers / Wall / Frosted Stickers',
        'Transparent Stickers',
        'Layouts',
        'Reflectorized (Subdivision Stickers/Signages)',
        'Stickers on Sintraboard',
        'Sintraboard Standees',
        'Souvenirs'
    ) NOT NULL,
    `item_id` INT NOT NULL COMMENT 'Any item of this ID must have stock > 0',
    `rule_type` ENUM('REQUIRED', 'OPTIONAL') NOT NULL DEFAULT 'REQUIRED' COMMENT 'REQUIRED = service disabled if 0 stock; OPTIONAL = shown as choice',
    PRIMARY KEY (`id`),
    KEY `idx_smr_service` (`service_type`),
    CONSTRAINT `fk_smr_item` FOREIGN KEY (`item_id`) REFERENCES `inv_items`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- END OF MIGRATION
-- Seed data is in database/seed_printflow_v2.php
-- ============================================================