<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
echo "<h2>Migration 034: 立沖系統</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .warn{color:orange;}</style>";
$sqls = array(
    "ALTER TABLE journal_entry_lines ADD COLUMN offset_flag TINYINT(1) DEFAULT 0 COMMENT '立沖: 0=否, 1=立帳, 2=沖帳' AFTER relation_name",
    "ALTER TABLE journal_entry_lines ADD COLUMN offset_amount DECIMAL(14,2) DEFAULT 0 COMMENT '立沖金額' AFTER offset_flag",
    "ALTER TABLE journal_entry_lines ADD COLUMN offset_ref_id INT UNSIGNED DEFAULT NULL COMMENT '沖帳對應立帳行ID' AFTER offset_amount",
    "CREATE TABLE IF NOT EXISTS offset_ledger (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      journal_line_id INT UNSIGNED NOT NULL,
      journal_entry_id INT UNSIGNED NOT NULL,
      account_id INT UNSIGNED NOT NULL,
      relation_type VARCHAR(20) NOT NULL,
      relation_id INT UNSIGNED NOT NULL,
      relation_name VARCHAR(100),
      original_amount DECIMAL(14,2) NOT NULL,
      offset_amount DECIMAL(14,2) DEFAULT 0,
      balance DECIMAL(14,2) NOT NULL,
      direction VARCHAR(10) NOT NULL,
      voucher_date DATE,
      description VARCHAR(200),
      status VARCHAR(20) DEFAULT 'open',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_account_relation (account_id, relation_type, relation_id),
      INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "<p class='ok'>OK: " . htmlspecialchars(substr($sql, 0, 80)) . "...</p>";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'already exists') !== false) {
            echo "<p class='warn'>已存在: " . htmlspecialchars(substr($sql, 0, 80)) . "</p>";
        } else {
            echo "<p style='color:red'>ERROR: {$msg}</p>";
        }
    }
}
echo "<p><a href='/accounting.php?action=journals'>返回傳票管理</a></p>";
