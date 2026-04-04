-- Migration 044: 客戶資料強化 - 新增編號、進件公司、關聯群組

-- 客戶關聯群組表
CREATE TABLE IF NOT EXISTS customer_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_name VARCHAR(100) NOT NULL COMMENT '群組名稱（公司名或統編）',
  tax_id VARCHAR(20) DEFAULT NULL COMMENT '統一編號',
  note VARCHAR(200) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tax_id (tax_id),
  INDEX idx_group_name (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- customers 表新增欄位
ALTER TABLE customers ADD COLUMN IF NOT EXISTS case_number VARCHAR(20) DEFAULT NULL COMMENT '進件編號（年-四位流水號）';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS source_company VARCHAR(50) DEFAULT NULL COMMENT '進件分公司（<禾順>等）';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS original_customer_no VARCHAR(50) DEFAULT NULL COMMENT '原客戶編號（客戶XXX、業#XXX等）';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS related_group_id INT UNSIGNED DEFAULT NULL COMMENT '關聯群組ID';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS completion_date DATE DEFAULT NULL COMMENT '完工日期';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS warranty_note VARCHAR(200) DEFAULT NULL COMMENT '保固期限備註';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS salesperson_name VARCHAR(50) DEFAULT NULL COMMENT '業務姓名';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS payment_info VARCHAR(100) DEFAULT NULL COMMENT '付款方式';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS line_official VARCHAR(100) DEFAULT NULL COMMENT '官方LINE';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS source_branch VARCHAR(20) DEFAULT NULL COMMENT '來源分公司（潭子/員林/海線）';

ALTER TABLE customers ADD INDEX IF NOT EXISTS idx_case_number (case_number);
ALTER TABLE customers ADD INDEX IF NOT EXISTS idx_original_customer_no (original_customer_no);
ALTER TABLE customers ADD INDEX IF NOT EXISTS idx_related_group_id (related_group_id);
ALTER TABLE customers ADD INDEX IF NOT EXISTS idx_source_company (source_company);
ALTER TABLE customers ADD INDEX IF NOT EXISTS idx_completion_date (completion_date);
