-- =============================================
-- Migration 024: 案件金額欄位
-- =============================================

ALTER TABLE `cases`
  ADD COLUMN `deal_amount` DECIMAL(12,0) DEFAULT NULL COMMENT '成交金額(未稅)' AFTER `quote_amount`,
  ADD COLUMN `is_tax_included` VARCHAR(30) DEFAULT NULL COMMENT '是否含稅' AFTER `deal_amount`,
  ADD COLUMN `tax_amount` DECIMAL(12,0) DEFAULT NULL COMMENT '稅金' AFTER `is_tax_included`,
  ADD COLUMN `total_amount` DECIMAL(12,0) DEFAULT NULL COMMENT '含稅金額' AFTER `tax_amount`,
  ADD COLUMN `deposit_amount` DECIMAL(12,0) DEFAULT NULL COMMENT '訂金金額' AFTER `total_amount`,
  ADD COLUMN `deposit_payment_date` DATE DEFAULT NULL COMMENT '訂金付款日' AFTER `deposit_amount`,
  ADD COLUMN `deposit_method` VARCHAR(30) DEFAULT NULL COMMENT '訂金支付方式' AFTER `deposit_payment_date`,
  ADD COLUMN `balance_amount` DECIMAL(12,0) DEFAULT NULL COMMENT '尾款' AFTER `deposit_method`,
  ADD COLUMN `completion_amount` DECIMAL(12,0) DEFAULT NULL COMMENT '完工金額(含稅)' AFTER `balance_amount`,
  ADD COLUMN `total_collected` DECIMAL(12,0) DEFAULT NULL COMMENT '總收款金額' AFTER `completion_amount`;
