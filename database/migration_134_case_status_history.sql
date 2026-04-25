-- =============================================
-- Migration 134: 案件狀態變更歷史 + 報表隱藏（案件更新進度報表用）
-- =============================================

-- 案件狀態變更歷史
CREATE TABLE IF NOT EXISTS `case_status_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `old_status` VARCHAR(50) DEFAULT NULL COMMENT '前一進度 (cases.status)',
  `new_status` VARCHAR(50) DEFAULT NULL COMMENT '本次進度 (cases.status)',
  `old_sub_status` VARCHAR(50) DEFAULT NULL COMMENT '前一狀態 (cases.sub_status)',
  `new_sub_status` VARCHAR(50) DEFAULT NULL COMMENT '本次狀態 (cases.sub_status)',
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_case` (`case_id`, `changed_at`),
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件狀態變更歷史';

-- 報表隱藏（案件更新進度報表，僅在報表內隱藏，不影響案件本身）
CREATE TABLE IF NOT EXISTS `case_progress_hidden` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `case_id` INT UNSIGNED NOT NULL,
  `hidden_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_case` (`user_id`, `case_id`),
  KEY `idx_case` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件更新進度報表 - 使用者隱藏紀錄';
