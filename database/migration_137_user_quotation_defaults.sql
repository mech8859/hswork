-- Migration 137: 業務個人預設報價內容
-- 收款條件 / 附註說明 個人預設
CREATE TABLE IF NOT EXISTS `user_quotation_defaults` (
  `user_id` INT UNSIGNED PRIMARY KEY,
  `payment_terms` TEXT NULL,
  `notes` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='業務報價單個人預設（收款條件、附註）';
