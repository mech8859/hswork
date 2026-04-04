<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

echo "=== 測試 ajax_categories ===\n";
$cats = $db->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name")->fetchAll();
echo "頂層分類: " . count($cats) . " 個\n";
foreach (array_slice($cats, 0, 5) as $c) {
    echo "  ID={$c['id']}: {$c['name']}\n";
}

echo "\n=== JSON 格式 ===\n";
echo json_encode(array_slice($cats, 0, 5), JSON_UNESCAPED_UNICODE);

echo "\n\n=== 測試 quotations.php 的 ajax_categories ===\n";
// 模擬呼叫
$_GET['action'] = 'ajax_categories';
// 不能直接 include，用 curl 測試
echo "請在瀏覽器開啟: /quotations.php?action=ajax_categories\n";
echo "看是否有回傳 JSON\n";
