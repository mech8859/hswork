<?php
/**
 * Migration 112: payments_out 加 exclude_from_branch_stats
 *
 * 用途：標記「會計補帳但不列入分公司年度統計」的付款
 * 例：去年年終獎金，會計做今年帳，但不該算在分公司今年支出
 *
 * 預設 0 = 列入統計（不影響既有資料）
 * 1 = 不列入分公司年度統計
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$sqls = array(
    "ALTER TABLE payments_out ADD COLUMN exclude_from_branch_stats TINYINT(1) NOT NULL DEFAULT 0 COMMENT '不列入分公司年度統計（補帳/跨年度調整）'",
    "ALTER TABLE payments_out ADD INDEX idx_exclude_from_branch_stats (exclude_from_branch_stats)",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "SKIP: $sql\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nDone.\n";
