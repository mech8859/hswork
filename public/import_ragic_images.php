<?php
/**
 * 從 Ragic 下載商品圖片並更新到 products 表
 * 使用方式: /import_ragic_images.php?token=hswork2026img
 *
 * 圖片來源: Ragic 商品資訊表 (inventory/6)
 * 圖片 URL: https://ap15.ragic.com/sims/file.jsp?a=hstcc&f={filename}
 * 儲存位置: /uploads/products/images/
 */

if (($_GET['token'] ?? '') !== 'hswork2026img') {
    die('Token required');
}

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/bootstrap.php';

$jsonFile = __DIR__ . '/../ragic_product_images.json';
if (!file_exists($jsonFile)) {
    die('ragic_product_images.json not found');
}

$images = json_decode(file_get_contents($jsonFile), true);
if (!$images) {
    die('JSON parse error');
}

$db = Database::getInstance();
$ragicBaseUrl = 'https://ap15.ragic.com/sims/file.jsp?a=hstcc&f=';
$uploadDir = __DIR__ . '/uploads/products/images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Ragic 登入取得 cookie (用瀏覽器 session 的方式不行，改用直接下載)
$cookieFile = sys_get_temp_dir() . '/ragic_cookie.txt';

// 先登入取得 session
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => 'https://ap15.ragic.com/hstcc/inventory/6',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode('hscctvttv@gmail.com:hstc88588859')
    ),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
));
curl_exec($ch);
curl_close($ch);

echo "<h2>Ragic 商品圖片匯入</h2>";
echo "<p>共 " . count($images) . " 筆有圖片的產品</p>";
echo "<pre>";

$updated = 0;
$skipped = 0;
$failed = 0;
$notFound = 0;

foreach ($images as $i => $item) {
    $code = $item['code'];
    $ragicFile = $item['image'];

    // 找到對應的 product (用 vendor_model 或 model 比對商品編號)
    $stmt = $db->prepare("
        SELECT id, image FROM products
        WHERE vendor_model = ? OR model = ?
        LIMIT 1
    ");
    $stmt->execute(array($code, $code));
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $notFound++;
        if (!empty($_GET['debug'])) echo "NOT FOUND: $code\n";
        continue;
    }

    // 已有圖片的跳過
    if (!empty($product['image']) && strpos($product['image'], '/uploads/') === 0) {
        $skipped++;
        continue;
    }

    // 從 Ragic 下載圖片
    $encodedFile = rawurlencode($ragicFile);
    $downloadUrl = $ragicBaseUrl . $encodedFile;

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $downloadUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . base64_encode('hscctvttv@gmail.com:hstc88588859')
        ),
    ));
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode !== 200 || strlen($imgData) < 100) {
        $failed++;
        if (($i + 1) % 50 === 0) echo "Progress: " . ($i + 1) . "/" . count($images) . " (failed: $failed)\n";
        continue;
    }

    // 判斷副檔名
    $ext = 'jpg';
    if (strpos($contentType, 'png') !== false) $ext = 'png';
    elseif (strpos($contentType, 'gif') !== false) $ext = 'gif';
    elseif (strpos($contentType, 'webp') !== false) $ext = 'webp';

    // 從 @ 後面取得原始檔名
    $origName = $ragicFile;
    if (strpos($ragicFile, '@') !== false) {
        $origName = substr($ragicFile, strpos($ragicFile, '@') + 1);
    }

    $saveName = $product['id'] . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $origName);
    if (!pathinfo($saveName, PATHINFO_EXTENSION)) {
        $saveName .= '.' . $ext;
    }

    $savePath = $uploadDir . $saveName;
    file_put_contents($savePath, $imgData);

    // 更新 DB
    $webPath = '/uploads/products/images/' . $saveName;
    $db->prepare("UPDATE products SET image = ? WHERE id = ?")->execute(array($webPath, $product['id']));

    $updated++;
    if (($i + 1) % 50 === 0 || $updated % 20 === 0) {
        echo "Progress: " . ($i + 1) . "/" . count($images) . " | Updated: $updated, Skipped: $skipped, Failed: $failed, NotFound: $notFound\n";
        flush();
    }
}

echo "\n=== 完成 ===\n";
echo "更新: $updated\n";
echo "跳過(已有圖片): $skipped\n";
echo "下載失敗: $failed\n";
echo "找不到對應產品: $notFound\n";
echo "</pre>";

// 清理 cookie
if (file_exists($cookieFile)) unlink($cookieFile);
