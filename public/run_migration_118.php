<?php
/**
 * Migration 118: 報價單新增 cable_cost 線材成本欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$cols = $db->query("SHOW COLUMNS FROM quotations LIKE 'cable_cost'")->fetchAll();
if (empty($cols)) {
    $db->exec("ALTER TABLE quotations ADD COLUMN cable_cost INT DEFAULT 0 AFTER labor_cost_total");
    echo "OK: quotations 新增 cable_cost 欄位\n";
} else {
    echo "SKIP: cable_cost 欄位已存在\n";
}
echo "Migration 118 done.\n";
