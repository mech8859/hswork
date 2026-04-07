<?php
/**
 * Migration 107: approval_rules 加 case_types 欄位（無訂金排工簽核用）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$sqls = array(
    "ALTER TABLE approval_rules ADD COLUMN case_types VARCHAR(200) DEFAULT NULL COMMENT '案件類型(逗號分隔)'",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) {
        echo (strpos($e->getMessage(), 'Duplicate') !== false ? "SKIP\n" : "ERROR: " . $e->getMessage() . "\n");
    }
}
echo "Done.\n";
