-- Migration 031: 庫存管理完整功能
-- 安全庫存、盤點、異動記錄增強

-- 1. inventory 增加安全庫存欄位
ALTER TABLE inventory
  ADD COLUMN min_qty INT DEFAULT 0 COMMENT '安全庫存量' AFTER display_qty,
  ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER locked;

-- 2. inventory_transactions 增加 qty_after 欄位
ALTER TABLE inventory_transactions
  ADD COLUMN qty_after DECIMAL(10,2) DEFAULT NULL COMMENT '異動後數量' AFTER quantity;

-- 3. 盤點單
CREATE TABLE IF NOT EXISTS stocktakes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stocktake_number VARCHAR(30) NOT NULL COMMENT 'ST-yyyymmdd-nnn',
  stocktake_date DATE NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) DEFAULT '盤點中' COMMENT '盤點中/已完成/已取消',
  note TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  completed_by INT UNSIGNED DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_st_number (stocktake_number),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盤點單';

-- 4. 盤點明細
CREATE TABLE IF NOT EXISTS stocktake_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stocktake_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  system_qty INT DEFAULT 0 COMMENT '系統數量',
  actual_qty INT DEFAULT NULL COMMENT '實際數量',
  diff_qty INT DEFAULT NULL COMMENT '差異數量',
  note VARCHAR(200) DEFAULT NULL,
  FOREIGN KEY (stocktake_id) REFERENCES stocktakes(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盤點明細';
