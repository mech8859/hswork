-- =============================================
-- Migration 010: 案件管理增強 + 維修單回報
-- =============================================

-- 案件新增欄位
ALTER TABLE `cases`
  ADD COLUMN `planned_start_date` DATE DEFAULT NULL COMMENT '預計施工日' AFTER `address`,
  ADD COLUMN `planned_end_date` DATE DEFAULT NULL COMMENT '預計完工日' AFTER `planned_start_date`,
  ADD COLUMN `is_flexible` TINYINT(1) DEFAULT 0 COMMENT '是否可隨時安排' AFTER `planned_end_date`,
  ADD COLUMN `work_time_start` TIME DEFAULT NULL COMMENT '施工時間起' AFTER `is_flexible`,
  ADD COLUMN `work_time_end` TIME DEFAULT NULL COMMENT '施工時間迄' AFTER `work_time_start`,
  ADD COLUMN `has_time_restriction` TINYINT(1) DEFAULT 0 COMMENT '有無施工時間限制' AFTER `work_time_end`,
  ADD COLUMN `customer_break_time` VARCHAR(100) DEFAULT NULL COMMENT '客戶休息時間' AFTER `has_time_restriction`,
  ADD COLUMN `allow_night_work` TINYINT(1) DEFAULT 0 COMMENT '是否可夜間加班' AFTER `customer_break_time`,
  ADD COLUMN `system_difficulty` TINYINT UNSIGNED DEFAULT NULL COMMENT '系統自動判斷難易度 1-5' AFTER `difficulty`,
  ADD COLUMN `urgency` TINYINT UNSIGNED DEFAULT 3 COMMENT '急迫性 1-5' AFTER `system_difficulty`,
  ADD COLUMN `is_large_project` TINYINT(1) DEFAULT 0 COMMENT '是否大型案件' AFTER `urgency`;

-- 維修單回報表
CREATE TABLE IF NOT EXISTS `repair_reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `repair_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `report_text` TEXT DEFAULT NULL COMMENT '回報內容',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`repair_id`) REFERENCES `repairs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='維修單回報';

-- 維修單回報照片
CREATE TABLE IF NOT EXISTS `repair_photos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `repair_id` INT UNSIGNED NOT NULL,
  `report_id` INT UNSIGNED DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL COMMENT '檔案路徑',
  `caption` VARCHAR(255) DEFAULT NULL COMMENT '照片說明',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`repair_id`) REFERENCES `repairs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='維修單照片';
