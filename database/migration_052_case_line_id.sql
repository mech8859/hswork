-- Migration 052: 案件加 contact_line_id 欄位
ALTER TABLE cases ADD COLUMN IF NOT EXISTS contact_line_id VARCHAR(100) DEFAULT NULL COMMENT 'LINE ID' AFTER contact_person;
