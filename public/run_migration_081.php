<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE requisition_items ADD COLUMN unit_price DECIMAL(12,2) DEFAULT 0 COMMENT '單價' AFTER quantity",
    "ALTER TABLE requisition_items ADD COLUMN amount DECIMAL(12,2) DEFAULT 0 COMMENT '小計' AFTER unit_price",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
