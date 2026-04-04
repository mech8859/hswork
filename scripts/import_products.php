<?php
/**
 * 從禾順管理系統匯入產品資料
 * 用法: php scripts/import_products.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';

// ---- 設定 ----
$apiBase = 'https://hershun.labkuby.org/api';
$username = 'admin';
$password = 'hs1357924680';

echo "=== 禾順產品匯入工具 ===\n\n";

// ---- Step 1: 登入取得 token ----
echo "[1] 登入中...\n";
$loginData = json_encode(['employeeId' => $username, 'password' => $password]);
$ch = curl_init($apiBase . '/auth/login');
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $loginData,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    CURLOPT_SSL_VERIFYPEER => false,
));
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$loginResult = json_decode($resp, true);
if (!$loginResult || empty($loginResult['data']['accessToken'])) {
    echo "登入失敗! HTTP=$httpCode\n";
    echo "Response: $resp\n";
    exit(1);
}
$token = $loginResult['data']['accessToken'];
echo "  登入成功\n\n";

// ---- Helper: API GET ----
function apiGet($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $token),
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

// ---- Step 2: 取得分類 ----
echo "[2] 取得分類...\n";
$catResult = apiGet($apiBase . '/products/categories', $token);
$categories = $catResult['data'];

function flattenCategories($cats, &$out) {
    foreach ($cats as $c) {
        $out[] = array(
            'source_id' => $c['id'],
            'name' => $c['name'],
            'parent_source_id' => $c['parentId'],
            'sort' => $c['sort']
        );
        if (!empty($c['children'])) {
            flattenCategories($c['children'], $out);
        }
    }
}

$flatCats = array();
flattenCategories($categories, $flatCats);
echo "  共 " . count($flatCats) . " 個分類\n\n";

// ---- Step 3: 寫入分類 ----
echo "[3] 寫入分類到資料庫...\n";
$db = Database::getInstance();

// 清空舊資料
$db->query("DELETE FROM product_categories");

// source_id -> local_id 對照
$catMap = array();

// 先寫入無 parent 的
foreach ($flatCats as $cat) {
    if ($cat['parent_source_id'] === null) {
        $db->query(
            "INSERT INTO product_categories (source_id, name, parent_id, sort) VALUES (?, ?, NULL, ?)",
            array($cat['source_id'], $cat['name'], $cat['sort'])
        );
        $catMap[$cat['source_id']] = $db->lastInsertId();
    }
}

// 再寫入有 parent 的（可能多層，跑 3 輪確保都寫入）
for ($round = 0; $round < 5; $round++) {
    foreach ($flatCats as $cat) {
        if ($cat['parent_source_id'] !== null && !isset($catMap[$cat['source_id']])) {
            if (isset($catMap[$cat['parent_source_id']])) {
                $db->query(
                    "INSERT INTO product_categories (source_id, name, parent_id, sort) VALUES (?, ?, ?, ?)",
                    array($cat['source_id'], $cat['name'], $catMap[$cat['parent_source_id']], $cat['sort'])
                );
                $catMap[$cat['source_id']] = $db->lastInsertId();
            }
        }
    }
}
echo "  已寫入 " . count($catMap) . " 個分類\n\n";

// ---- Step 4: 取得全部產品 ----
echo "[4] 取得產品...\n";

// 先取總數
$firstPage = apiGet($apiBase . '/products?page=1&pageSize=1&isActive=true', $token);
$total = $firstPage['data']['total'];
echo "  共 $total 筆產品\n";

$allProducts = array();
$pages = ceil($total / 100);

for ($p = 1; $p <= $pages; $p++) {
    $pageData = apiGet($apiBase . '/products?page=' . $p . '&pageSize=100&isActive=true', $token);
    if (!empty($pageData['data']['items'])) {
        $allProducts = array_merge($allProducts, $pageData['data']['items']);
    }
    echo "  頁 $p/$pages (" . count($allProducts) . "筆)\n";
}
echo "\n";

// ---- Step 5: 寫入產品 ----
echo "[5] 寫入產品到資料庫...\n";
$db->query("DELETE FROM products");

$inserted = 0;
$imageBase = 'https://hershun.labkuby.org';

foreach ($allProducts as $p) {
    $localCatId = isset($catMap[$p['categoryId']]) ? $catMap[$p['categoryId']] : null;

    // 圖片處理：加上完整 URL
    $image = null;
    if (!empty($p['image'])) {
        $image = (strpos($p['image'], 'http') === 0) ? $p['image'] : $imageBase . $p['image'];
    }

    $gallery = null;
    if (!empty($p['gallery'])) {
        $galleryUrls = array();
        foreach ($p['gallery'] as $g) {
            $galleryUrls[] = (strpos($g, 'http') === 0) ? $g : $imageBase . $g;
        }
        $gallery = json_encode($galleryUrls);
    }

    $db->query(
        "INSERT INTO products (source_id, name, model, brand, supplier, description, specifications, warranty_text, unit, price, cost, retail_price, labor_cost, image, gallery, datasheet, category_id, stock, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        array(
            $p['id'],
            $p['name'],
            $p['model'],
            $p['brand'],
            $p['supplier'],
            $p['description'],
            $p['specifications'],
            $p['warrantyText'],
            $p['unit'] ?: '台',
            (float)($p['price'] ?: 0),
            (float)($p['cost'] ?: 0),
            (float)($p['retailPrice'] ?: 0),
            $p['laborCost'] ? (float)$p['laborCost'] : null,
            $image,
            $gallery,
            $p['datasheet'],
            $localCatId,
            (int)($p['stock'] ?: 0),
            $p['isActive'] ? 1 : 0
        )
    );
    $inserted++;

    if ($inserted % 100 === 0) {
        echo "  已寫入 $inserted 筆\n";
    }
}

echo "  完成! 共寫入 $inserted 筆產品\n\n";
echo "=== 匯入完成 ===\n";
