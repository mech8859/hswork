-- Migration 033: 傳票分錄加往來類型
ALTER TABLE journal_entry_lines ADD COLUMN relation_type VARCHAR(20) DEFAULT NULL COMMENT '往來類型: customer/vendor/other' AFTER cost_center_id;
ALTER TABLE journal_entry_lines ADD COLUMN relation_id INT UNSIGNED DEFAULT NULL COMMENT '往來對象ID' AFTER relation_type;
ALTER TABLE journal_entry_lines ADD COLUMN relation_name VARCHAR(100) DEFAULT NULL COMMENT '往來對象名稱' AFTER relation_id;
