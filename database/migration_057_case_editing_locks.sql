-- 案件編輯鎖定（防衝突）
CREATE TABLE IF NOT EXISTS `case_editing_locks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `user_name` VARCHAR(100) NOT NULL COMMENT '快速顯示用',
  `locked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `heartbeat_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_case_user` (`case_id`, `user_id`),
  KEY `idx_heartbeat` (`heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件編輯鎖定';
