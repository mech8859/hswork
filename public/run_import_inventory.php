<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ob_implicit_flush(true);

try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗');
}

echo '<h2>Ragic 庫存匯入</h2>';

$jsonFile = __DIR__ . '/../ragic_inventory.json';
if (!file_exists($jsonFile)) {
    $jsonFile = '/home/vhost158992/ragic_inventory.json';
}
if (!file_exists($jsonFile)) {
    die('<p style="color:red">找不到 ragic_inventory.json</p>');
}

$data = json_decode(file_get_contents($jsonFile), true);
echo '<p>庫存記錄: ' . count($data) . ' 筆</p>';
flush();

// 商品映射
$productMap = array();
$stmt = $db->query("SELECT id, model, vendor_model FROM products");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['vendor_model']) $productMap[$row['vendor_model']] = (int)$row['id'];
    if ($row['model']) $productMap[$row['model']] = (int)$row['id'];
}
echo '<p>商品映射: ' . count($productMap) . ' 筆</p>';
flush();

// 分公司映射
$branchMap = array();
$stmt = $db->query("SELECT id, name FROM branches");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$row['name']] = (int)$row['id'];
}

// 確保 inventory 表結構正確
$db->exec("DROP TABLE IF EXISTS inventory");
$db->exec("CREATE TABLE inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    stock_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
    available_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
    reserved_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
    borrowed_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
    display_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_qty DECIMAL(10,2) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_product_branch (product_id, branch_id),
    INDEX idx_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo '<p style="color:green">✓ inventory 表重建完成</p>';
flush();

$insertStmt = $db->prepare("
    INSERT INTO inventory (product_id, branch_id, stock_qty, available_qty, reserved_qty, borrowed_qty, display_qty)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$inserted = 0;
$noProduct = 0;

foreach ($data as $row) {
    $productId = isset($productMap[$row['product_code']]) ? $productMap[$row['product_code']] : null;
    $branchId = isset($branchMap[$row['branch']]) ? $branchMap[$row['branch']] : null;

    if (!$productId) { $noProduct++; continue; }
    if (!$branchId) { continue; }

    $insertStmt->execute(array(
        $productId, $branchId,
        $row['stock_qty'], $row['available_qty'],
        $row['reserved_qty'], $row['borrowed_qty'], $row['display_qty'],
    ));
    $inserted++;
}

echo '<h3>結果</h3>';
echo '<table border="1" cellpadding="6">';
echo "<tr><td style='color:green'>匯入成功</td><td>{$inserted} 筆</td></tr>";
echo "<tr><td style='color:orange'>找不到商品</td><td>{$noProduct} 筆</td></tr>";
echo '</table>';

// 各分公司統計
echo '<h3>各分公司庫存統計</h3>';
echo '<table border="1" cellpadding="6"><tr><th>分公司</th><th>品項數</th><th>總庫存</th><th>總可用</th></tr>';
$stmt = $db->query("
    SELECT b.name, COUNT(*) as cnt, CAST(SUM(i.stock_qty) AS SIGNED) as ts, CAST(SUM(i.available_qty) AS SIGNED) as ta
    FROM inventory i JOIN branches b ON i.branch_id = b.id
    GROUP BY i.branch_id ORDER BY ts DESC
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<tr><td>' . htmlspecialchars($r['name']) . '</td><td>' . $r['cnt'] . '</td><td>' . number_format($r['ts']) . '</td><td>' . number_format($r['ta']) . '</td></tr>';
}
echo '</table>';

echo '<p><a href="products.php">返回產品目錄</a></p>';
