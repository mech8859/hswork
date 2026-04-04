<?php
/**
 * 重新對應產品分類
 * 用禾順 API 取得每個產品的 categoryId，對應到新的 product_categories
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

$db = Database::getInstance();

echo "=== 重新對應產品分類 ===\n\n";

// 1. 建立禾順 source_id → hswork category_id 映射
$catMap = array();
$cats = $db->query("SELECT id, source_id FROM product_categories WHERE source_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cats as $c) {
    $catMap[$c['source_id']] = $c['id'];
}
echo "分類映射: " . count($catMap) . " 筆\n";

// 2. 從禾順 API 取得產品的分類資訊
$token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjE4MDNhM2Y5LTY2NmMtNGEwYy1hZjA5LTgyODg5NTQ5NjQwYyIsImVtcGxveWVlSWQiOiJhZG1pbiIsIm5hbWUiOiLns7vntbHnrqHnkIblk6EiLCJyZWFsTmFtZSI6Iuezu-e1seeuoeeQhuWToSIsInJvbGUiOiJBRE1JTiIsImRlcGFydG1lbnQiOm51bGwsImlhdCI6MTc3NDk0Nzk2OSwiZXhwIjoxNzc3NTM5OTY5fQ.sGtZjkNQJdwWr8VRWOT1Nw3iDX-CJRPkBC1OWQltOqw';

// 取得所有產品（分頁）
$page = 1;
$pageSize = 100;
$updated = 0;
$notFound = 0;
$noCat = 0;
$total = 0;

while (true) {
    $url = "https://hershun.labkuby.org/api/products?page={$page}&pageSize={$pageSize}&isActive=true";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (!isset($result['data']) || !isset($result['data']['items'])) {
        echo "API 回應異常，停止\n";
        break;
    }

    $items = $result['data']['items'];
    if (empty($items)) break;

    foreach ($items as $item) {
        $total++;
        $sourceId = $item['id'];
        $catId = isset($item['categoryId']) ? $item['categoryId'] : null;

        if (!$catId) {
            $noCat++;
            continue;
        }

        // 找 hswork 產品
        $stmt = $db->prepare("SELECT id FROM products WHERE source_id = ?");
        $stmt->execute(array($sourceId));
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $notFound++;
            continue;
        }

        // 找對應的 hswork 分類 ID
        if (isset($catMap[$catId])) {
            $newCatId = $catMap[$catId];
            $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($newCatId, $product['id']));
            $updated++;
        }
    }

    $totalPages = isset($result['data']['totalPages']) ? $result['data']['totalPages'] : 0;
    echo "第 {$page}/{$totalPages} 頁，累計處理 {$total} 筆\n";
    ob_flush(); flush();

    if ($page >= $totalPages) break;
    $page++;
}

echo "\n=== 完成 ===\n";
echo "總計: {$total} 筆\n";
echo "已更新分類: {$updated} 筆\n";
echo "無分類: {$noCat} 筆\n";
echo "找不到產品: {$notFound} 筆\n";
