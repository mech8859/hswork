<?php
/**
 * Migration 140：cases 加 final_deal_amount（完工後最後成交金額）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
try {
    $exists = $db->query("SHOW COLUMNS FROM cases LIKE 'final_deal_amount'")->fetch();
    if ($exists) {
        echo "[skip] cases.final_deal_amount 已存在\n";
    } else {
        $db->exec("ALTER TABLE cases ADD COLUMN final_deal_amount DECIMAL(12,2) DEFAULT NULL COMMENT '完工後最後成交金額（含稅）；NULL 表示不需修改' AFTER total_amount");
        echo "OK: cases.final_deal_amount 已建立\n";
    }
    AuditLog::log('cases', 'migration', 0, 'Migration 140: cases 加 final_deal_amount');
    echo "Migration 140 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
