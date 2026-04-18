<?php
/**
 * Migration 132: 報價單新增 cable_not_used 旗標
 *  - 用於標記「此案件無使用線材」，避免強制同步時把 0 當成漏填
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$cols = $db->query("SHOW COLUMNS FROM quotations LIKE 'cable_not_used'")->fetchAll();
if (empty($cols)) {
    $db->exec("ALTER TABLE quotations ADD COLUMN cable_not_used TINYINT(1) NOT NULL DEFAULT 0 COMMENT '此案件無使用線材' AFTER cable_cost");
    echo "OK: quotations 新增 cable_not_used 欄位\n";
} else {
    echo "SKIP: cable_not_used 欄位已存在\n";
}
echo "Migration 132 done.\n";
