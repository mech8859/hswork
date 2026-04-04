<?php
/**
 * 透過網頁執行產品匯入（用 token 保護，完成後請刪除此檔）
 * 用法: https://yoursite/import_products_run.php?token=hswork2026import
 */
set_time_limit(600);
ini_set('memory_limit', '256M');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 簡易 token 保護
if (($_GET['token'] ?? '') !== 'hswork2026import') {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$apiBase = 'https://hershun.labkuby.org/api';
$username = 'admin';
$password = 'hs1357924680';
$db = Database::getInstance();

// Helper: execute prepared statement
function dbExec($db, $sql, $params = array()) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

echo "=== 禾順產品匯入工具 ===\n\n";

// ---- Step 0: 建表 ----
echo "[0] 建立資料表...\n";

$db->exec("CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `source_id` INT UNSIGNED DEFAULT NULL COMMENT '來源系統ID',
  `name` VARCHAR(100) NOT NULL COMMENT '分類名稱',
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT '上層分類ID',
  `sort` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_parent` (`parent_id`),
  INDEX `idx_source` (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='產品分類'");

$db->exec("CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `source_id` VARCHAR(50) DEFAULT NULL COMMENT '來源系統UUID',
  `name` VARCHAR(255) NOT NULL COMMENT '產品名稱',
  `model` VARCHAR(100) DEFAULT NULL COMMENT '型號',
  `brand` VARCHAR(100) DEFAULT NULL COMMENT '品牌',
  `supplier` VARCHAR(100) DEFAULT NULL COMMENT '供應商',
  `description` TEXT DEFAULT NULL COMMENT '說明',
  `specifications` VARCHAR(255) DEFAULT NULL COMMENT '規格',
  `warranty_text` VARCHAR(50) DEFAULT NULL COMMENT '保固',
  `unit` VARCHAR(20) DEFAULT '台' COMMENT '單位',
  `price` DECIMAL(10,2) DEFAULT 0 COMMENT '售價',
  `cost` DECIMAL(10,2) DEFAULT 0 COMMENT '成本',
  `retail_price` DECIMAL(10,2) DEFAULT 0 COMMENT '零售價',
  `labor_cost` DECIMAL(10,2) DEFAULT NULL COMMENT '工資',
  `image` VARCHAR(500) DEFAULT NULL COMMENT '主圖片URL',
  `gallery` TEXT DEFAULT NULL COMMENT '圖片集(JSON)',
  `datasheet` VARCHAR(500) DEFAULT NULL COMMENT '規格書URL',
  `category_id` INT UNSIGNED DEFAULT NULL COMMENT '分類ID',
  `stock` INT DEFAULT 0 COMMENT '庫存',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '是否啟用',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_model` (`model`),
  INDEX `idx_source` (`source_id`),
  INDEX `idx_name` (`name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='產品目錄'");

echo "  資料表建立完成\n\n";

// ---- Step 1: 登入取得 token ----
echo "[1] 登入禾順系統...\n";
$loginData = json_encode(array('employeeId' => $username, 'password' => $password));
$ch = curl_init($apiBase . '/auth/login');
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $loginData,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
));
$resp = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$resp) {
    die("連線失敗: $err\n");
}

$loginResult = json_decode($resp, true);
if (!$loginResult || empty($loginResult['data']['accessToken'])) {
    die("登入失敗! HTTP=$httpCode\nResponse: $resp\n");
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
        CURLOPT_TIMEOUT => 60,
    ));
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

// ---- Step 2: 取得分類 ----
echo "[2] 取得分類...\n";
$catResult = apiGet($apiBase . '/products/categories', $token);
$categories = isset($catResult['data']) ? $catResult['data'] : array();

function flattenCategories($cats, &$out) {
    foreach ($cats as $c) {
        $out[] = array(
            'source_id' => $c['id'],
            'name' => $c['name'],
            'parent_source_id' => $c['parentId'],
            'sort' => isset($c['sort']) ? $c['sort'] : 0
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
$db->exec("DELETE FROM product_categories");

$catMap = array();
$catStmt = $db->prepare("INSERT INTO product_categories (source_id, name, parent_id, sort) VALUES (?, ?, ?, ?)");

foreach ($flatCats as $cat) {
    if ($cat['parent_source_id'] === null) {
        $catStmt->execute(array($cat['source_id'], $cat['name'], null, $cat['sort']));
        $catMap[$cat['source_id']] = $db->lastInsertId();
    }
}

for ($round = 0; $round < 5; $round++) {
    foreach ($flatCats as $cat) {
        if ($cat['parent_source_id'] !== null && !isset($catMap[$cat['source_id']])) {
            if (isset($catMap[$cat['parent_source_id']])) {
                $catStmt->execute(array($cat['source_id'], $cat['name'], $catMap[$cat['parent_source_id']], $cat['sort']));
                $catMap[$cat['source_id']] = $db->lastInsertId();
            }
        }
    }
}
echo "  已寫入 " . count($catMap) . " 個分類\n\n";

// ---- Step 4: 取得全部產品 ----
echo "[4] 取得產品...\n";
$firstPage = apiGet($apiBase . '/products?page=1&pageSize=1&isActive=true', $token);
$total = isset($firstPage['data']['total']) ? $firstPage['data']['total'] : 0;
echo "  共 $total 筆產品\n";

$allProducts = array();
$pages = (int)ceil($total / 100);

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
$db->exec("DELETE FROM products");

$prodStmt = $db->prepare(
    "INSERT INTO products (source_id, name, model, brand, supplier, description, specifications, warranty_text, unit, price, cost, retail_price, labor_cost, image, gallery, datasheet, category_id, stock, is_active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$inserted = 0;
$imageBase = 'https://hershun.labkuby.org';

foreach ($allProducts as $p) {
    $localCatId = isset($catMap[$p['categoryId']]) ? $catMap[$p['categoryId']] : null;

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

    $prodStmt->execute(array(
        $p['id'],
        $p['name'],
        isset($p['model']) ? $p['model'] : null,
        isset($p['brand']) ? $p['brand'] : null,
        isset($p['supplier']) ? $p['supplier'] : null,
        isset($p['description']) ? $p['description'] : null,
        isset($p['specifications']) ? $p['specifications'] : null,
        isset($p['warrantyText']) ? $p['warrantyText'] : null,
        isset($p['unit']) ? $p['unit'] : '台',
        (float)(isset($p['price']) ? $p['price'] : 0),
        (float)(isset($p['cost']) ? $p['cost'] : 0),
        (float)(isset($p['retailPrice']) ? $p['retailPrice'] : 0),
        isset($p['laborCost']) && $p['laborCost'] ? (float)$p['laborCost'] : null,
        $image,
        $gallery,
        isset($p['datasheet']) ? $p['datasheet'] : null,
        $localCatId,
        (int)(isset($p['stock']) ? $p['stock'] : 0),
        (isset($p['isActive']) && $p['isActive']) ? 1 : 0
    ));
    $inserted++;

    if ($inserted % 200 === 0) {
        echo "  已寫入 $inserted 筆\n";
    }
}

echo "  完成! 共寫入 $inserted 筆產品\n\n";
echo "=== 匯入完成 ===\n";
echo "分類: " . count($catMap) . " 個\n";
echo "產品: $inserted 筆\n";
echo "\n提醒: 請匯入完成後刪除此檔案 (import_products_run.php)\n";
