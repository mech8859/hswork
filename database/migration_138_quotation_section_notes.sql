-- migration_138_quotation_section_notes.sql
-- 報價單區段備註欄（編輯時輸入，列印時以淺綠底顯示在該區段下）
-- 兩種報價單格式（普銷 simple / 專案 project）共用

ALTER TABLE quotation_sections
    ADD COLUMN notes TEXT NULL COMMENT '區段備註，列印淺綠底顯示' AFTER discount_amount;
