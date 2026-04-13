<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

echo "=== 搜尋「硬碟」的庫存 ===\n";
$rows = $db->query("
    SELECT i.id, i.product_id, i.warehouse_id, i.branch_id, i.stock_qty, i.available_qty,
           p.name AS product_name, p.model,
           w.name AS warehouse_name
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.id
    LEFT JOIN warehouses w ON i.warehouse_id = w.id
    WHERE p.name LIKE '%硬碟%'
    ORDER BY i.warehouse_id, i.stock_qty DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "共 " . count($rows) . " 筆\n\n";
foreach ($rows as $r) {
    echo "inv#{$r['id']} | wh_id:" . ($r['warehouse_id'] ?: 'NULL') . " branch_id:" . ($r['branch_id'] ?: 'NULL') . " | wh:{$r['warehouse_name']} | qty:{$r['stock_qty']} avail:{$r['available_qty']} | {$r['product_name']} ({$r['model']})\n";
}

echo "\n=== 空 warehouse_id 且有庫存的總數 ===\n";
$cnt = $db->query("SELECT COUNT(*) FROM inventory WHERE (warehouse_id IS NULL OR warehouse_id = 0) AND stock_qty > 0")->fetchColumn();
echo "{$cnt} 筆\n";

$cntAll = $db->query("SELECT COUNT(*) FROM inventory WHERE (warehouse_id IS NULL OR warehouse_id = 0)")->fetchColumn();
echo "含 0 庫存: {$cntAll} 筆\n";
