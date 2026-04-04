-- =============================================
-- Migration: 維修單
-- =============================================
CREATE TABLE IF NOT EXISTS `repairs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `repair_number` VARCHAR(20) NOT NULL UNIQUE COMMENT '維修單號',
  `branch_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(30) DEFAULT NULL,
  `customer_address` VARCHAR(200) DEFAULT NULL,
  `engineer_id` INT UNSIGNED DEFAULT NULL,
  `repair_date` DATE NOT NULL,
  `total_amount` DECIMAL(10,0) NOT NULL DEFAULT 0,
  `status` ENUM('draft','completed','invoiced') NOT NULL DEFAULT 'draft',
  `note` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`engineer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='維修單';

CREATE TABLE IF NOT EXISTS `repair_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `repair_id` INT UNSIGNED NOT NULL,
  `description` VARCHAR(200) NOT NULL COMMENT '項目說明',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,0) NOT NULL DEFAULT 0,
  `amount` DECIMAL(10,0) NOT NULL DEFAULT 0,
  FOREIGN KEY (`repair_id`) REFERENCES `repairs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='維修單項目';

-- inter_branch_support 加半天選項
ALTER TABLE `inter_branch_support`
  MODIFY `charge_type` ENUM('full_day','half_day','hourly') NOT NULL;
