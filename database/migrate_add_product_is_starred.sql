-- 產品目錄星標欄位（產品分類用，管理權限才可操作）
-- 日期：2026-04-21
ALTER TABLE products
    ADD COLUMN is_starred TINYINT(1) NOT NULL DEFAULT 0 COMMENT '星標分類（0=未標記,1=已標記）' AFTER is_active,
    ADD INDEX idx_is_starred (is_starred);
