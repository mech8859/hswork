-- 請款流程項目新增日期欄位
ALTER TABLE case_billing_items
    ADD COLUMN billing_date DATE NULL COMMENT '請款日期' AFTER seq_no;
