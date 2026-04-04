ALTER TABLE customers ADD COLUMN IF NOT EXISTS warranty_date DATE DEFAULT NULL COMMENT '保固日期' AFTER completion_date;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(100) DEFAULT NULL COMMENT '付款條件（匯款/現金/支票/月結等）' AFTER payment_info;
ALTER TABLE customers ADD INDEX IF NOT EXISTS idx_warranty_date (warranty_date);
