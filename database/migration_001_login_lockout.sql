-- =============================================
-- Migration: 登入失敗鎖定功能
-- =============================================

-- 登入失敗記錄表
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL COMMENT '嘗試登入的帳號',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP位址',
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_username_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登入失敗記錄';

-- users 表加入鎖定欄位
ALTER TABLE `users`
  ADD COLUMN `locked_until` DATETIME DEFAULT NULL COMMENT '帳號鎖定到何時' AFTER `last_login_at`,
  ADD COLUMN `failed_login_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '連續登入失敗次數' AFTER `locked_until`;
