-- 請款流程項目表
CREATE TABLE IF NOT EXISTS case_billing_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    seq_no INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '序號',
    payment_category VARCHAR(30) NOT NULL COMMENT '帳款類別: 訂金/第一期款/第二期款/第三期款/尾款/保留款/退款',
    amount_untaxed DECIMAL(12,0) DEFAULT NULL COMMENT '未稅金額',
    tax_amount DECIMAL(12,0) DEFAULT NULL COMMENT '稅金',
    total_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '總金額',
    tax_included TINYINT(1) NOT NULL DEFAULT 0 COMMENT '含稅: 1=含稅反推, 0=未稅',
    note TEXT COMMENT '備註',
    customer_paid TINYINT(1) NOT NULL DEFAULT 0 COMMENT '客戶通知已付款',
    customer_paid_info TEXT COMMENT '付款資訊說明',
    customer_billable TINYINT(1) NOT NULL DEFAULT 0 COMMENT '客戶通知可請款',
    is_billed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '已請款（會計打勾）',
    billed_info TEXT COMMENT '已請款資訊',
    invoice_number VARCHAR(50) DEFAULT NULL COMMENT '發票號碼',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_case_id (case_id),
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
