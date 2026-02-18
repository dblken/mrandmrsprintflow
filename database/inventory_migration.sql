-- Dynamic Inventory Module Migration
-- PrintFlow - Printing Shop PWA
-- Creates material_categories, materials, material_stock_movements
-- Material Categories
CREATE TABLE IF NOT EXISTS material_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Materials (linked to categories)
CREATE TABLE IF NOT EXISTS materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    material_name VARCHAR(100) NOT NULL,
    opening_stock DECIMAL(10, 2) DEFAULT 0.00,
    current_stock DECIMAL(10, 2) DEFAULT 0.00,
    unit VARCHAR(20) DEFAULT 'ft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES material_categories(category_id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Stock Movements (normalized — one row per material per date)
CREATE TABLE IF NOT EXISTS material_stock_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    movement_date DATE NOT NULL,
    quantity_change DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) DEFAULT 'Manual entry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE,
    UNIQUE KEY unique_material_date (material_id, movement_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;