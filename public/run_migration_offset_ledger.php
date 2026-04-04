<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗: ' . $e->getMessage());
}

echo '<h2>Migration: 立沖帳記錄表</h2>';

// 立帳記錄主表
try {
    $db->exec("CREATE TABLE IF NOT EXISTS offset_ledger (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        journal_entry_id INT UNSIGNED NOT NULL COMMENT '來源傳票ID',
        journal_line_id INT UNSIGNED NOT NULL COMMENT '來源分錄行ID',
        account_id INT UNSIGNED NOT NULL COMMENT '會計科目',
        cost_center_id INT UNSIGNED DEFAULT NULL COMMENT '部門中心',
        relation_type VARCHAR(20) DEFAULT NULL COMMENT '往來類型: customer/vendor/other',
        relation_id INT UNSIGNED DEFAULT NULL COMMENT '往來編號',
        relation_name VARCHAR(200) DEFAULT NULL COMMENT '往來對象名稱',
        voucher_date DATE NOT NULL COMMENT '傳票日期',
        voucher_number VARCHAR(30) DEFAULT NULL COMMENT '傳票號碼',
        direction ENUM('debit','credit') NOT NULL COMMENT '借貸方向',
        original_amount DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '原始金額',
        offset_total DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '已沖額合計',
        remaining_amount DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '未沖額',
        status ENUM('open','partial','closed') NOT NULL DEFAULT 'open' COMMENT '狀態: open=未沖/partial=部分沖/closed=全沖',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_account (account_id),
        INDEX idx_relation (relation_type, relation_id),
        INDEX idx_status (status),
        INDEX idx_journal (journal_entry_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='立帳記錄表'");
    echo '<p style="color:green">✓ offset_ledger 表建立完成</p>';
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo '<p style="color:#888">offset_ledger 已存在</p>';
    } else {
        echo '<p style="color:red">✗ ' . $e->getMessage() . '</p>';
    }
}

// 沖帳明細表
try {
    $db->exec("CREATE TABLE IF NOT EXISTS offset_details (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ledger_id INT UNSIGNED NOT NULL COMMENT '對應立帳記錄ID',
        journal_entry_id INT UNSIGNED NOT NULL COMMENT '沖帳傳票ID',
        journal_line_id INT UNSIGNED NOT NULL COMMENT '沖帳分錄行ID',
        offset_amount DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '本次沖帳金額',
        voucher_date DATE NOT NULL COMMENT '沖帳傳票日期',
        voucher_number VARCHAR(30) DEFAULT NULL COMMENT '沖帳傳票號碼',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ledger (ledger_id),
        INDEX idx_journal (journal_entry_id),
        FOREIGN KEY (ledger_id) REFERENCES offset_ledger(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='沖帳明細表'");
    echo '<p style="color:green">✓ offset_details 表建立完成</p>';
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo '<p style="color:#888">offset_details 已存在</p>';
    } else {
        echo '<p style="color:red">✗ ' . $e->getMessage() . '</p>';
    }
}

// 在 journal_entry_lines 加上 offset_ledger_id 欄位（沖帳行關聯到哪筆立帳）
try {
    $db->exec("ALTER TABLE journal_entry_lines ADD COLUMN offset_ledger_id INT UNSIGNED DEFAULT NULL COMMENT '沖帳對應的立帳記錄ID'");
    echo '<p style="color:green">✓ journal_entry_lines.offset_ledger_id 欄位新增</p>';
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo '<p style="color:#888">offset_ledger_id 已存在</p>';
    } else {
        echo '<p style="color:red">✗ ' . $e->getMessage() . '</p>';
    }
}

echo '<p style="color:green;font-weight:bold">完成！</p>';
echo '<p><a href="accounting.php?action=journals">返回傳票管理</a></p>';
