-- case_payments 加入簽核狀態
ALTER TABLE case_payments ADD COLUMN approval_status VARCHAR(20) DEFAULT NULL COMMENT 'pending/approved/rejected, NULL=不需簽核' AFTER image_path;
