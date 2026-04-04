<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
echo "<h2>Migration 033: 傳票往來類型</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .warn{color:orange;}</style>";
$sqls = array(
    "ALTER TABLE journal_entry_lines ADD COLUMN relation_type VARCHAR(20) DEFAULT NULL COMMENT '往來類型' AFTER cost_center_id",
    "ALTER TABLE journal_entry_lines ADD COLUMN relation_id INT UNSIGNED DEFAULT NULL COMMENT '往來對象ID' AFTER relation_type",
    "ALTER TABLE journal_entry_lines ADD COLUMN relation_name VARCHAR(100) DEFAULT NULL COMMENT '往來對象名稱' AFTER relation_id",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "<p class='ok'>OK: " . htmlspecialchars(substr($sql, 0, 80)) . "...</p>";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false) {
            echo "<p class='warn'>已存在: " . htmlspecialchars(substr($sql, 0, 80)) . "</p>";
        } else {
            echo "<p style='color:red'>ERROR: {$msg}</p>";
        }
    }
}
echo "<p><a href='/accounting.php?action=journals'>返回傳票管理</a></p>";
