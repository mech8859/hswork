-- 產品分類表
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `source_id` INT UNSIGNED DEFAULT NULL COMMENT '來源系統ID',
  `name` VARCHAR(100) NOT NULL COMMENT '分類名稱',
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT '上層分類ID',
  `sort` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_parent` (`parent_id`),
  INDEX `idx_source` (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='產品分類';

-- 產品表
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `source_id` VARCHAR(50) DEFAULT NULL COMMENT '來源系統UUID',
  `name` VARCHAR(255) NOT NULL COMMENT '產品名稱',
  `model` VARCHAR(100) DEFAULT NULL COMMENT '型號',
  `brand` VARCHAR(100) DEFAULT NULL COMMENT '品牌',
  `supplier` VARCHAR(100) DEFAULT NULL COMMENT '供應商',
  `description` TEXT DEFAULT NULL COMMENT '說明',
  `specifications` VARCHAR(255) DEFAULT NULL COMMENT '規格',
  `warranty_text` VARCHAR(50) DEFAULT NULL COMMENT '保固',
  `unit` VARCHAR(20) DEFAULT '台' COMMENT '單位',
  `price` DECIMAL(10,2) DEFAULT 0 COMMENT '售價',
  `cost` DECIMAL(10,2) DEFAULT 0 COMMENT '成本',
  `retail_price` DECIMAL(10,2) DEFAULT 0 COMMENT '零售價',
  `labor_cost` DECIMAL(10,2) DEFAULT NULL COMMENT '工資',
  `image` VARCHAR(500) DEFAULT NULL COMMENT '主圖片URL',
  `gallery` TEXT DEFAULT NULL COMMENT '圖片集(JSON)',
  `datasheet` VARCHAR(500) DEFAULT NULL COMMENT '規格書URL',
  `category_id` INT UNSIGNED DEFAULT NULL COMMENT '分類ID',
  `stock` INT DEFAULT 0 COMMENT '庫存',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '是否啟用',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_model` (`model`),
  INDEX `idx_source` (`source_id`),
  INDEX `idx_name` (`name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='產品目錄';
