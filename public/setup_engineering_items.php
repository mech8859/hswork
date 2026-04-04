<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>建立工程項次與線材分類</h2>';

// 1. 建立工程項次表
try {
    $db->exec("CREATE TABLE IF NOT EXISTS engineering_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL COMMENT '項目名稱',
        unit VARCHAR(20) DEFAULT '式' COMMENT '單位',
        default_price DECIMAL(10,0) DEFAULT 0 COMMENT '預設定價',
        default_cost DECIMAL(10,0) DEFAULT 0 COMMENT '預設成本',
        category VARCHAR(50) DEFAULT NULL COMMENT '分類如：光纖工程/監視器工程',
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p style="color:green">✓ engineering_items 表已建立</p>';
} catch (Exception $e) {
    echo '<p style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// 2. 從 Excel 匯入工程項次
$items = array(
    array('光纖工程', '光纖熔接', '芯', 0, 0),
    array('光纖工程', '光纖佈線', '米', 0, 0),
    array('監視器工程', '監視器安裝', '台', 0, 0),
    array('監視器工程', '監視器拆除', '台', 0, 0),
    array('監視器工程', '監視線佈線', '米', 0, 0),
    array('網路線工程', '網路線佈線', '米', 0, 0),
    array('網路線工程', '網路插座安裝', '組', 0, 0),
    array('網路線工程', '資訊面板安裝', '組', 0, 0),
    array('廣播喇叭工程', '喇叭安裝', '台', 0, 0),
    array('廣播喇叭工程', '廣播線佈線', '米', 0, 0),
    array('電話工程', '電話線佈線', '米', 0, 0),
    array('電話工程', '電話插座安裝', '組', 0, 0),
    array('影視對講工程', '對講機安裝', '台', 0, 0),
    array('影視對講工程', '對講線佈線', '米', 0, 0),
    array('電梯工程', '電梯對講安裝', '台', 0, 0),
    array('基本工程', '基本出勤費', '次', 2000, 1500),
    array('基本工程', '高空作業費', '次', 0, 0),
    array('基本工程', '夜間施工費', '次', 0, 0),
);

$chk = $db->query("SELECT COUNT(*) FROM engineering_items")->fetchColumn();
if ($chk == 0) {
    $stmt = $db->prepare("INSERT INTO engineering_items (category, name, unit, default_price, default_cost) VALUES (?,?,?,?,?)");
    foreach ($items as $item) {
        $stmt->execute($item);
    }
    echo '<p style="color:green">✓ 匯入 ' . count($items) . ' 筆工程項次</p>';
} else {
    echo '<p>已有 ' . $chk . ' 筆工程項次</p>';
}

// 3. 產品分類加上「線材」
$existing = $db->query("SELECT id FROM product_categories WHERE name = '線材' AND (parent_id IS NULL OR parent_id = 0)")->fetch();
if (!$existing) {
    $db->exec("INSERT INTO product_categories (name, parent_id) VALUES ('線材', NULL)");
    $lineId = (int)$db->lastInsertId();
    // 加子分類
    $subCats = array('同軸線', '網路線', '電話線', '電源線', '光纖線', '對講線', '喇叭線', '其他線材');
    $subStmt = $db->prepare("INSERT INTO product_categories (name, parent_id) VALUES (?, ?)");
    foreach ($subCats as $sc) {
        $subStmt->execute(array($sc, $lineId));
    }
    echo '<p style="color:green">✓ 產品分類新增「線材」+ ' . count($subCats) . ' 個子分類</p>';
} else {
    echo '<p>線材分類已存在</p>';
}

// 顯示結果
echo '<h3>工程項次</h3>';
echo '<table border="1" cellpadding="4"><tr><th>分類</th><th>項目</th><th>單位</th><th>定價</th></tr>';
foreach ($db->query("SELECT * FROM engineering_items ORDER BY category, sort_order")->fetchAll() as $r) {
    echo '<tr><td>' . htmlspecialchars($r['category']) . '</td><td>' . htmlspecialchars($r['name']) . '</td><td>' . htmlspecialchars($r['unit']) . '</td><td>$' . number_format($r['default_price']) . '</td></tr>';
}
echo '</table>';

echo '<br><a href="/quotations.php">返回報價管理</a>';
