<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);

try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗');
}

echo '<h2>Ragic 商品匯入產品目錄</h2>';

$jsonFile = __DIR__ . '/../ragic_products.json';
if (!file_exists($jsonFile)) {
    die('<p style="color:red">找不到 ragic_products.json</p>');
}

$products = json_decode(file_get_contents($jsonFile), true);
echo '<p>Ragic 商品: ' . count($products) . ' 筆</p>';

// 取得現有產品（用 vendor_model 或 model 比對 Ragic 商品編號）
$existByCode = array();
$stmt = $db->query("SELECT id, model, vendor_model, name FROM products");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['vendor_model']) $existByCode[$row['vendor_model']] = $row;
    if ($row['model']) $existByCode[$row['model']] = $row;
}
echo '<p>現有產品: ' . $db->query("SELECT COUNT(*) FROM products")->fetchColumn() . ' 筆</p>';

// 取得現有分類
$categories = array();
$stmt = $db->query("SELECT id, name FROM product_categories");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categories[$row['name']] = (int)$row['id'];
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$newCats = 0;

$insertStmt = $db->prepare("
    INSERT INTO products (vendor_model, name, brand, model, unit, category_id, cost, price, retail_price, specifications, description, is_active, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
");

$updateStmt = $db->prepare("
    UPDATE products SET
        name = CASE WHEN ? != '' AND name = '' THEN ? ELSE name END,
        brand = CASE WHEN ? != '' AND (brand IS NULL OR brand = '') THEN ? ELSE brand END,
        unit = CASE WHEN ? != '' AND (unit IS NULL OR unit = '') THEN ? ELSE unit END,
        category_id = COALESCE(?, category_id),
        cost = CASE WHEN ? > 0 AND (cost IS NULL OR cost = 0) THEN ? ELSE cost END,
        price = CASE WHEN ? > 0 AND (price IS NULL OR price = 0) THEN ? ELSE price END,
        specifications = CASE WHEN ? != '' AND (specifications IS NULL OR specifications = '') THEN ? ELSE specifications END,
        updated_at = NOW()
    WHERE id = ?
");

foreach ($products as $p) {
    $code = trim($p['code']);
    if (!$code) { $skipped++; continue; }

    // 分類處理
    $catName = trim($p['type']);
    $catId = null;
    if ($catName && isset($categories[$catName])) {
        $catId = $categories[$catName];
    } elseif ($catName) {
        $db->prepare("INSERT INTO product_categories (name, created_at) VALUES (?, NOW())")->execute(array($catName));
        $catId = (int)$db->lastInsertId();
        $categories[$catName] = $catId;
        $newCats++;
    }

    $cost = max((float)$p['cost'], (float)$p['cost1']);
    $price = (float)$p['price'];
    $model = trim($p['model']);
    $spec = trim($p['spec']);

    // 用商品編號(code)比對 vendor_model 或 model
    if (isset($existByCode[$code])) {
        // 已存在：更新空欄位
        $updateStmt->execute(array(
            $p['name'], $p['name'],
            $p['brand'], $p['brand'],
            $p['unit'], $p['unit'],
            $catId,
            $cost, $cost,
            $price, $price,
            $spec, $spec,
            $existByCode[$code]['id'],
        ));
        if ($updateStmt->rowCount() > 0) $updated++;
    } elseif ($model && isset($existByCode[$model])) {
        // 用型號比對
        $updateStmt->execute(array(
            $p['name'], $p['name'],
            $p['brand'], $p['brand'],
            $p['unit'], $p['unit'],
            $catId,
            $cost, $cost,
            $price, $price,
            $spec, $spec,
            $existByCode[$model]['id'],
        ));
        if ($updateStmt->rowCount() > 0) $updated++;
    } else {
        // 新增
        $insertStmt->execute(array(
            $code,
            $p['name'],
            $p['brand'],
            $model ?: $code,
            $p['unit'],
            $catId,
            $cost,
            $price,
            $price,
            $spec,
            $p['note'],
        ));
        $inserted++;
    }
}

echo "<h3>結果</h3>";
echo "<table border='1' cellpadding='6'>";
echo "<tr><td style='color:green'>新增</td><td>{$inserted} 筆</td></tr>";
echo "<tr><td style='color:blue'>更新</td><td>{$updated} 筆</td></tr>";
echo "<tr><td>跳過</td><td>{$skipped} 筆</td></tr>";
echo "<tr><td>新建分類</td><td>{$newCats} 個</td></tr>";
echo "</table>";

$total = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$catTotal = $db->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
echo "<h3>產品目錄統計</h3>";
echo "<p>產品總數: {$total} 筆</p>";
echo "<p>分類總數: {$catTotal} 個</p>";

echo '<p><a href="products.php">返回產品目錄</a></p>';
