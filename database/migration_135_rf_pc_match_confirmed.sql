-- Migration 135: 備用金→零用金 人工核對紀錄
-- 兩個 _id 必須允許 NULL，因為支援「單邊核對」（unmatched_rf 或 unmatched_pc 單獨標已核對）
CREATE TABLE IF NOT EXISTS `rf_pc_match_confirmed` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rf_id` INT UNSIGNED DEFAULT NULL COMMENT 'reserve_fund.id（單邊 pc 核對時為 NULL）',
  `pc_id` INT UNSIGNED DEFAULT NULL COMMENT 'petty_cash.id（單邊 rf 核對時為 NULL）',
  `confirmed_by` INT UNSIGNED DEFAULT NULL,
  `confirmed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_rf` (`rf_id`),
  UNIQUE KEY `uk_pc` (`pc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='備用金支出→零用金收入 人工核對配對';
