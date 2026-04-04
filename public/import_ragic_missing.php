<?php
/**
 * 匯入 Ragic 獨有產品（hswork 中不存在的）+ 下載圖片
 * 使用方式: /import_ragic_missing.php?token=hswork2026img
 */
if (($_GET['token'] ?? '') !== 'hswork2026img') die('Token required');

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();

// 讀取圖片 JSON
$imgJson = __DIR__ . '/../ragic_product_images.json';
$imgData = json_decode(file_get_contents($imgJson), true);
$imgMap = array();
foreach ($imgData as $item) {
    $imgMap[$item['code']] = $item['image'];
}

// 讀取 ragic_products.json（全部產品資料）
$prodJson = __DIR__ . '/../ragic_products.json';
$allProducts = json_decode(file_get_contents($prodJson), true);

// 建立 code -> product map
$ragicMap = array();
foreach ($allProducts as $p) {
    $ragicMap[$p['code']] = $p;
}

// 找出 hswork 中已存在的 vendor_model
$existStmt = $db->query("SELECT vendor_model FROM products WHERE vendor_model IS NOT NULL AND vendor_model != ''");
$existCodes = array();
while ($row = $existStmt->fetch(PDO::FETCH_ASSOC)) {
    $existCodes[$row['vendor_model']] = true;
}

// 也檢查 model
$existStmt2 = $db->query("SELECT model FROM products WHERE model IS NOT NULL AND model != ''");
while ($row = $existStmt2->fetch(PDO::FETCH_ASSOC)) {
    $existCodes[$row['model']] = true;
}

// 取得分類對應
$catStmt = $db->query("SELECT id, name FROM product_categories");
$catMap = array();
while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
    $catMap[$row['name']] = $row['id'];
}

// Ragic 圖片下載設定
$ragicBaseUrl = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=';
$uploadDir = __DIR__ . '/uploads/products/images/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

echo "<h2>匯入 Ragic 獨有產品</h2><pre>";

$inserted = 0;
$skipped = 0;
$imgDownloaded = 0;

// 找出有圖片但 hswork 沒有的產品
$missingCodes = array();
foreach ($imgMap as $code => $img) {
    if (!isset($existCodes[$code])) {
        $missingCodes[] = $code;
    }
}

echo "hswork 中不存在且有圖片的產品: " . count($missingCodes) . " 筆\n\n";

foreach ($missingCodes as $code) {
    $ragic = isset($ragicMap[$code]) ? $ragicMap[$code] : null;

    // 基本資料（從 ragic_products.json 或用 code 當名稱）
    $name = $ragic ? ($ragic['name'] ?: $code) : $code;
    $model = $ragic ? ($ragic['model'] ?: '') : '';
    $brand = $ragic ? ($ragic['brand'] ?: '') : '';
    $unit = $ragic ? ($ragic['unit'] ?: '台') : '台';
    $cost = $ragic ? (float)($ragic['cost'] ?: 0) : 0;
    $price = $ragic ? (float)($ragic['price'] ?: 0) : 0;
    $spec = $ragic ? ($ragic['spec'] ?: '') : '';
    $typeName = $ragic ? ($ragic['type'] ?: '') : '';
    $note = $ragic ? ($ragic['note'] ?: '') : '';

    // 分類
    $categoryId = isset($catMap[$typeName]) ? $catMap[$typeName] : null;

    // 下載圖片
    $imagePath = '';
    $ragicFile = $imgMap[$code];
    if ($ragicFile) {
        $encodedFile = rawurlencode($ragicFile);
        $downloadUrl = $ragicBaseUrl . $encodedFile;

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $downloadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . base64_encode('hscctvttv@gmail.com:hstc88588859')
            ),
        ));
        $imgContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode === 200 && strlen($imgContent) > 100) {
            $ext = 'jpg';
            if (strpos($contentType, 'png') !== false) $ext = 'png';
            elseif (strpos($contentType, 'gif') !== false) $ext = 'gif';

            $origName = $ragicFile;
            if (strpos($ragicFile, '@') !== false) {
                $origName = substr($ragicFile, strpos($ragicFile, '@') + 1);
            }
            $saveName = preg_replace('/[^a-zA-Z0-9._-]/', '', $code . '_' . $origName);
            if (!pathinfo($saveName, PATHINFO_EXTENSION)) $saveName .= '.' . $ext;

            file_put_contents($uploadDir . $saveName, $imgContent);
            $imagePath = '/uploads/products/images/' . $saveName;
            $imgDownloaded++;
        }
    }

    // INSERT 產品
    $stmt = $db->prepare("
        INSERT INTO products (vendor_model, name, model, brand, unit, category_id, cost, price, specifications, description, image, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
    ");
    $stmt->execute(array(
        $code, $name, $model, $brand, $unit, $categoryId,
        $cost > 0 ? $cost : null,
        $price > 0 ? $price : null,
        $spec ?: null, $note ?: null,
        $imagePath ?: null
    ));
    $inserted++;

    if ($inserted % 20 === 0) {
        echo "已匯入: $inserted / " . count($missingCodes) . " (圖片: $imgDownloaded)\n";
        flush();
    }
}

echo "\n=== 完成 ===\n";
echo "新增產品: $inserted\n";
echo "下載圖片: $imgDownloaded\n";
echo "</pre>";
