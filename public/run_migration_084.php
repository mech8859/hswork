<?php
/**
 * Migration 084: stock_out_items 加 is_confirmed 支援逐品項確認出庫
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$sqls = array(
    "ALTER TABLE stock_out_items ADD COLUMN is_confirmed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '品項已確認出庫' AFTER sort_order",
    "ALTER TABLE stock_out_items ADD COLUMN confirmed_at DATETIME DEFAULT NULL AFTER is_confirmed",
    // 已確認的出庫單，品項也標記已確認
    "UPDATE stock_out_items soi JOIN stock_outs so ON soi.stock_out_id = so.id SET soi.is_confirmed = 1, soi.confirmed_at = so.confirmed_at WHERE so.status = '已確認'",
);

foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 80) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (exists)\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}
echo "\nDone.\n";
