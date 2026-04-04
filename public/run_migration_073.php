<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE stock_out_items ADD COLUMN is_spare TINYINT(1) NOT NULL DEFAULT 0 COMMENT '備品標記: 0=正常品, 1=備品' AFTER note",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] $sql\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
