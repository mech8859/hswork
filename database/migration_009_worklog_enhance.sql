-- =============================================
-- Migration 009: 工程回報增強
-- =============================================

-- work_logs 新增欄位
ALTER TABLE `work_logs`
  ADD COLUMN `payment_collected` TINYINT(1) DEFAULT 0 COMMENT '是否收款' AFTER `next_visit_note`,
  ADD COLUMN `payment_amount` DECIMAL(10,2) DEFAULT NULL COMMENT '收款金額' AFTER `payment_collected`,
  ADD COLUMN `payment_method` VARCHAR(20) DEFAULT NULL COMMENT '收款方式(cash/transfer/check)' AFTER `payment_amount`,
  ADD COLUMN `payment_note` VARCHAR(255) DEFAULT NULL COMMENT '收款備註' AFTER `payment_method`;

-- 施工照片表
CREATE TABLE IF NOT EXISTS `worklog_photos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `work_log_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL COMMENT '檔案路徑',
  `caption` VARCHAR(255) DEFAULT NULL COMMENT '照片說明',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`work_log_id`) REFERENCES `work_logs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='施工回報照片';

-- material_usage 增加 product_id 欄位（連動產品目錄）
ALTER TABLE `material_usage`
  ADD COLUMN `product_id` INT UNSIGNED DEFAULT NULL COMMENT '關聯產品ID' AFTER `work_log_id`,
  ADD COLUMN `unit_cost` DECIMAL(10,2) DEFAULT NULL COMMENT '單價' AFTER `returned_qty`;
