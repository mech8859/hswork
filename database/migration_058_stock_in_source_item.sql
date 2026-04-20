-- 入庫明細回連到出庫明細（手動餘料入庫精準對應，避免同型號正常出貨列被誤算為退回）
ALTER TABLE `stock_in_items`
  ADD COLUMN `source_item_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '對應 stock_out_items.id（手動餘料入庫時來源出庫列）' AFTER `stock_in_id`,
  ADD INDEX `idx_source_item_id` (`source_item_id`);
