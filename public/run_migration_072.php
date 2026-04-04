<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE quotations ADD COLUMN quote_company VARCHAR(20) DEFAULT 'hershun' COMMENT '報價公司: hershun=禾順, lichuang=理創' AFTER branch_id",
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
