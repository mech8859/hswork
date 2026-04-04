-- 自動編號序列表
CREATE TABLE IF NOT EXISTS number_sequences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module VARCHAR(30) NOT NULL UNIQUE,
  module_label VARCHAR(50) NOT NULL,
  prefix VARCHAR(20) NOT NULL DEFAULT '',
  date_format VARCHAR(20) NOT NULL DEFAULT 'Ym',
  `separator` VARCHAR(5) NOT NULL DEFAULT '-',
  seq_digits INT NOT NULL DEFAULT 3,
  last_reset_key VARCHAR(20) DEFAULT NULL,
  last_sequence INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits) VALUES
('cases', '案件', '', 'Ym', '-', 3),
('quotations', '報價單', 'Q', 'Ymd', '-', 3),
('receivables', '應收帳款', 'AR', 'Y', '-', 4),
('receipts', '收款單', 'RC', 'Y', '-', 4),
('payables', '應付帳款', 'AP', 'Y', '-', 4),
('payments', '付款單', 'PM', 'Y', '-', 4),
('purchase_orders', '採購單', 'PUR', 'Ymd', '-', 3),
('requisitions', '請購單', 'PR', 'Ymd', '-', 3),
('warehouse_transfers', '倉庫調撥', 'ST', 'Ymd', '-', 3);
