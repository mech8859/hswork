-- Migration 034: 立沖系統
-- 1. journal_entry_lines 加立沖欄位
ALTER TABLE journal_entry_lines ADD COLUMN offset_flag TINYINT(1) DEFAULT 0 COMMENT '立沖: 0=否, 1=立帳, 2=沖帳' AFTER relation_name;
ALTER TABLE journal_entry_lines ADD COLUMN offset_amount DECIMAL(14,2) DEFAULT 0 COMMENT '立沖金額' AFTER offset_flag;
ALTER TABLE journal_entry_lines ADD COLUMN offset_ref_id INT UNSIGNED DEFAULT NULL COMMENT '沖帳對應立帳行ID' AFTER offset_amount;

-- 2. 立沖餘額表
CREATE TABLE IF NOT EXISTS offset_ledger (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  journal_line_id INT UNSIGNED NOT NULL COMMENT '來源分錄行',
  journal_entry_id INT UNSIGNED NOT NULL COMMENT '來源傳票',
  account_id INT UNSIGNED NOT NULL COMMENT '會計科目',
  relation_type VARCHAR(20) NOT NULL COMMENT '往來類型',
  relation_id INT UNSIGNED NOT NULL COMMENT '往來對象ID',
  relation_name VARCHAR(100) COMMENT '往來對象名稱',
  original_amount DECIMAL(14,2) NOT NULL COMMENT '原始金額',
  offset_amount DECIMAL(14,2) DEFAULT 0 COMMENT '已沖金額',
  balance DECIMAL(14,2) NOT NULL COMMENT '未沖餘額',
  direction VARCHAR(10) NOT NULL COMMENT 'debit/credit',
  voucher_date DATE COMMENT '傳票日期',
  description VARCHAR(200) COMMENT '摘要',
  status VARCHAR(20) DEFAULT 'open' COMMENT 'open/partial/closed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_account_relation (account_id, relation_type, relation_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
