-- Migration 041: 預算管理表
CREATE TABLE IF NOT EXISTS budgets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year INT NOT NULL COMMENT '年度',
  month TINYINT NOT NULL COMMENT '月份 1-12',
  account_id INT UNSIGNED NOT NULL COMMENT '會計科目',
  cost_center_id INT UNSIGNED NULL COMMENT '成本中心（NULL=全公司）',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '預算金額',
  note VARCHAR(200) DEFAULT NULL COMMENT '備註',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_budget (year, month, account_id, cost_center_id),
  INDEX idx_year_month (year, month),
  INDEX idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='預算管理';
