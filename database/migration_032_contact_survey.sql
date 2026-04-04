-- Migration 032: 新增聯絡地址 + 場勘日期
ALTER TABLE cases ADD COLUMN contact_address VARCHAR(300) DEFAULT NULL COMMENT '聯絡地址' AFTER address;
ALTER TABLE cases ADD COLUMN survey_date DATE DEFAULT NULL COMMENT '場勘日期' AFTER deal_date;
