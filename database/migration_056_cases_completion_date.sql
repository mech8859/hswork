-- 案件新增完工日期欄位
ALTER TABLE cases ADD COLUMN completion_date DATE DEFAULT NULL COMMENT '完工日期' AFTER completion_amount;
