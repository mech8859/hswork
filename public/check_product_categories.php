<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== 產品分類（含線材/配件相關）===\n\n";

// 所有頂層分類
$rows = $db->query("
    SELECT pc.id, pc.name, pc.parent_id, pp.name AS parent_name, COUNT(p.id) AS product_count
    FROM product_categories pc
    LEFT JOIN product_categories pp ON pc.parent_id = pp.id
    LEFT JOIN products p ON p.category_id = pc.id AND p.is_active = 1
    GROUP BY pc.id
    ORDER BY pc.parent_id, pc.name
")->fetchAll(PDO::FETCH_ASSOC);

echo "--- 所有分類 ---\n";
foreach ($rows as $r) {
    $parent = $r['parent_name'] ? "[{$r['parent_name']}]" : "[頂層]";
    $highlight = '';
    if (strpos($r['name'], '線') !== false || strpos($r['name'], '配件') !== false || strpos($r['name'], '耗材') !== false || strpos($r['name'], '五金') !== false) {
        $highlight = ' ★';
    }
    echo "  ID:{$r['id']} | {$parent} > {$r['name']} | 產品:{$r['product_count']}{$highlight}\n";
}

echo "\n--- 線材/配件 關鍵字搜尋 ---\n";
$keywords = array('線', '配件', '耗材', '五金', 'cable', '接頭', '轉接', '插座', '端子');
foreach ($keywords as $kw) {
    $like = '%' . $kw . '%';
    $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE is_active = 1 AND (name LIKE ? OR category_id IN (SELECT id FROM product_categories WHERE name LIKE ?))");
    $cnt->execute(array($like, $like));
    echo "  關鍵字 '{$kw}': " . $cnt->fetchColumn() . " 筆產品\n";
}
