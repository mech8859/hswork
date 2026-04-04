-- =============================================
-- Migration 023: 下拉選單選項管理 + 案件欄位擴充
-- =============================================

-- 通用下拉選單選項表（管理者可新增/編輯/刪除）
CREATE TABLE IF NOT EXISTS `dropdown_options` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(50) NOT NULL COMMENT '分類: customer_demand, system_type, deposit_method',
  `label` VARCHAR(200) NOT NULL COMMENT '顯示文字',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cat_active (`category`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='下拉選單選項';

-- 案件新增欄位（Ragic 有但之前未匯入的）
ALTER TABLE `cases`
  ADD COLUMN `system_type` VARCHAR(100) DEFAULT NULL COMMENT '系統別' AFTER `description`,
  ADD COLUMN `quote_amount` DECIMAL(12,0) DEFAULT NULL COMMENT '報價金額' AFTER `system_type`,
  ADD COLUMN `notes` TEXT DEFAULT NULL COMMENT '備註（非客戶需求）' AFTER `quote_amount`;
