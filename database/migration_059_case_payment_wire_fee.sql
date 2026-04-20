-- 案件帳款交易加匯費欄位（客人扣匯費時記錄，計算尾款時 -wire_fee）
ALTER TABLE `case_payments`
  ADD COLUMN `wire_fee` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '匯費（扣除後計算尾款）' AFTER `tax_amount`;
