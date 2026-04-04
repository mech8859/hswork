<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗');
}

echo '<h2>修復 offset_ledger 表</h2>';

// 檢查現有欄位
$cols = $db->query("SHOW COLUMNS FROM offset_ledger")->fetchAll(PDO::FETCH_COLUMN);
echo '<p>現有欄位: ' . implode(', ', $cols) . '</p>';

// 如果缺欄位就重建
if (!in_array('cost_center_id', $cols)) {
    echo '<p style="color:orange">缺少 cost_center_id，重建表...</p>';
    $db->exec("DROP TABLE IF EXISTS offset_details");
    $db->exec("DROP TABLE IF EXISTS offset_ledger");

    $db->exec("CREATE TABLE offset_ledger (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        journal_entry_id INT UNSIGNED NOT NULL,
        journal_line_id INT UNSIGNED NOT NULL,
        account_id INT UNSIGNED NOT NULL,
        cost_center_id INT UNSIGNED DEFAULT NULL,
        relation_type VARCHAR(20) DEFAULT NULL,
        relation_id INT UNSIGNED DEFAULT NULL,
        relation_name VARCHAR(200) DEFAULT NULL,
        voucher_date DATE NOT NULL,
        voucher_number VARCHAR(30) DEFAULT NULL,
        direction ENUM('debit','credit') NOT NULL,
        original_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        offset_total DECIMAL(14,2) NOT NULL DEFAULT 0,
        remaining_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        status ENUM('open','partial','closed') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_account (account_id),
        INDEX idx_relation (relation_type, relation_id),
        INDEX idx_status (status),
        INDEX idx_journal (journal_entry_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p style="color:green">✓ offset_ledger 重建完成</p>';

    $db->exec("CREATE TABLE offset_details (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ledger_id INT UNSIGNED NOT NULL,
        journal_entry_id INT UNSIGNED NOT NULL,
        journal_line_id INT UNSIGNED NOT NULL,
        offset_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        voucher_date DATE NOT NULL,
        voucher_number VARCHAR(30) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ledger (ledger_id),
        INDEX idx_journal (journal_entry_id),
        FOREIGN KEY (ledger_id) REFERENCES offset_ledger(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p style="color:green">✓ offset_details 重建完成</p>';
} else {
    echo '<p style="color:green">欄位完整，無需修復</p>';
}

// 驗證
$cols2 = $db->query("SHOW COLUMNS FROM offset_ledger")->fetchAll(PDO::FETCH_COLUMN);
echo '<p>修復後欄位: ' . implode(', ', $cols2) . '</p>';

echo '<p><a href="accounting.php?action=journals">返回傳票管理</a></p>';
