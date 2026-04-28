-- migration_139_quotation_labor_unit_hours.sql
-- 報價單新增「施工時數」欄位（每人工時，與施工天數擇一）
-- 業務填寫天數 → 總人時 = 天數 × 人數 × 8
-- 業務填寫時數 → 總人時 = 時數 × 人數
-- 總施工時數 (labor_hours) 改為一律後端計算、不可手動輸入

ALTER TABLE quotations
    ADD COLUMN labor_unit_hours DECIMAL(6,2) NULL COMMENT '每人工時（與天數擇一）' AFTER labor_people;
