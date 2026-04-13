<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// 修正所有有 pack_qty 的產品的 cost_per_unit
$rows = $db->query("SELECT id, name, cost, pack_qty FROM products WHERE pack_qty IS NOT NULL AND pack_qty > 0")->fetchAll(PDO::FETCH_ASSOC);
echo "=== 修正 cost_per_unit ===\n";
foreach ($rows as $r) {
    $cpu = round((float)$r['cost'] / (float)$r['pack_qty'], 4);
    $db->prepare("UPDATE products SET cost_per_unit = ? WHERE id = ?")->execute(array($cpu, $r['id']));
    echo "#{$r['id']} {$r['name']} | {$r['cost']}/{$r['pack_qty']} = {$cpu}\n";
}

// 沒有 pack_qty 但有 cost 的，cost_per_unit = cost
$db->exec("UPDATE products SET cost_per_unit = cost WHERE (pack_qty IS NULL OR pack_qty = 0) AND cost > 0 AND (cost_per_unit IS NULL OR cost_per_unit = 0)");
$affected = $db->query("SELECT ROW_COUNT()")->fetchColumn();
echo "\n非箱裝產品 cost_per_unit = cost: {$affected} 筆\n";
