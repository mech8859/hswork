<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE approval_rules ADD COLUMN condition_type VARCHAR(20) DEFAULT 'amount' COMMENT '條件類型: amount=金額, product=產品, category=產品分類' AFTER max_amount",
    "ALTER TABLE approval_rules ADD COLUMN product_ids TEXT DEFAULT NULL COMMENT '指定產品ID(逗號分隔)' AFTER condition_type",
    "ALTER TABLE approval_rules ADD COLUMN product_category_id INT DEFAULT NULL COMMENT '指定產品分類ID' AFTER product_ids",
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
