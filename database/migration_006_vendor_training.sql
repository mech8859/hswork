-- =============================================
-- Migration 006: 廠商上課證 + 角色更新 + admin 改名
-- =============================================

-- 1. 建立廠商上課證資料表
CREATE TABLE IF NOT EXISTS `vendor_trainings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `vendor_name` VARCHAR(100) NOT NULL COMMENT '客戶/廠商名稱',
  `training_date` DATE DEFAULT NULL COMMENT '上課日期',
  `expiry_date` DATE DEFAULT NULL COMMENT '有效期限',
  `note` VARCHAR(200) DEFAULT NULL COMMENT '備註',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='廠商上課證';

-- 2. 更新 admin 帳號顯示名稱為「系統管理者」
UPDATE `users` SET `real_name` = '系統管理者' WHERE `username` = 'admin';
