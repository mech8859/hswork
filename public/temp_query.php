<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// 加欄位
$sqls = array(
    "ALTER TABLE products ADD COLUMN pack_qty DECIMAL(10,2) DEFAULT NULL COMMENT '每箱/每捲數量（如305米/箱）' AFTER labor_cost",
    "ALTER TABLE products ADD COLUMN cost_per_unit DECIMAL(10,4) DEFAULT NULL COMMENT '每單位成本（自動計算：cost/pack_qty）' AFTER pack_qty",
);

foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 60) . "...\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (already exists): " . substr($sql, 0, 60) . "...\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

// 驗證
echo "\n=== products 價格相關欄位 ===\n";
$cols = $db->query("SHOW COLUMNS FROM products WHERE Field IN ('cost','price','retail_price','labor_cost','pack_qty','cost_per_unit','unit')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo "{$c['Field']}: {$c['Type']} | {$c['Comment']}\n";
