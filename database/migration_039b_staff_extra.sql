-- Migration 039b: 補充 is_sales, education_level 欄位，修改 ENUM 為 VARCHAR
ALTER TABLE users ADD COLUMN is_sales TINYINT(1) DEFAULT 0 COMMENT '業務(可承接業務)' AFTER is_engineer;
ALTER TABLE users ADD COLUMN education_level VARCHAR(20) DEFAULT NULL COMMENT '最高教育程度' AFTER blood_type;
ALTER TABLE users MODIFY COLUMN marital_status VARCHAR(20) DEFAULT NULL COMMENT '婚姻狀態';
ALTER TABLE users MODIFY COLUMN employment_status VARCHAR(20) DEFAULT 'active' COMMENT '在職狀態';
