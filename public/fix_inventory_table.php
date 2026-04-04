<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗');
}

echo '<h2>修復 inventory 表</h2>';

// 檢查現有欄位
$cols = $db->query("SHOW COLUMNS FROM inventory")->fetchAll(PDO::FETCH_COLUMN);
echo '<p>現有欄位: ' . implode(', ', $cols) . '</p>';

// 如果有 branch_id 但沒 warehouse_id，加上 warehouse_id
if (in_array('branch_id', $cols) && !in_array('warehouse_id', $cols)) {
    $db->exec("ALTER TABLE inventory ADD COLUMN warehouse_id INT UNSIGNED DEFAULT NULL AFTER branch_id");
    $db->exec("UPDATE inventory SET warehouse_id = branch_id");
    echo '<p style="color:green">✓ 新增 warehouse_id 並從 branch_id 複製</p>';
}

// 確保 warehouses 表存在（用 branches 當作 warehouses）
try {
    $db->query("SELECT 1 FROM warehouses LIMIT 1");
    echo '<p>warehouses 表已存在</p>';
} catch (Exception $e) {
    // 建立 warehouses 視圖指向 branches
    $db->exec("CREATE OR REPLACE VIEW warehouses AS SELECT id, name, code, is_active FROM branches");
    echo '<p style="color:green">✓ 建立 warehouses 視圖 (映射到 branches)</p>';
}

// 驗證
$cols2 = $db->query("SHOW COLUMNS FROM inventory")->fetchAll(PDO::FETCH_COLUMN);
echo '<p>修復後欄位: ' . implode(', ', $cols2) . '</p>';

$cnt = $db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
echo '<p>庫存筆數: ' . $cnt . '</p>';

echo '<p style="color:green;font-weight:bold">完成！</p>';
echo '<p><a href="inventory.php">前往庫存管理</a></p>';
