<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$results = array();

// 修改現有 vehicles 表，加入缺少的欄位
$alterSqls = array(
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS brand VARCHAR(50) DEFAULT NULL COMMENT '品牌' AFTER plate_number",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS model VARCHAR(100) DEFAULT NULL COMMENT '型號' AFTER brand",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS year INT DEFAULT NULL COMMENT '出廠年份' AFTER model",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS color VARCHAR(20) DEFAULT NULL COMMENT '顏色' AFTER year",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS custodian_id INT DEFAULT NULL COMMENT '保管人ID' AFTER color",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS last_maintenance_date DATE DEFAULT NULL COMMENT '上次保養日期' AFTER custodian_id",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS maintenance_mileage INT DEFAULT NULL COMMENT '保養里程' AFTER last_maintenance_date",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS next_maintenance_date DATE DEFAULT NULL COMMENT '下次保養日期' AFTER maintenance_mileage",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS current_mileage INT DEFAULT NULL COMMENT '目前里程' AFTER next_maintenance_date",
    "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS note TEXT DEFAULT NULL AFTER current_mileage",
);

foreach ($alterSqls as $sql) {
    try {
        $db->exec($sql);
        $results[] = 'OK: ' . substr($sql, 0, 80);
    } catch (Exception $e) {
        $results[] = 'ERR: ' . $e->getMessage();
    }
}

echo '<h2>Migration 012b - Alter vehicles table</h2>';
echo '<ul>';
foreach ($results as $r) {
    echo '<li>' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';

// 顯示新的結構
$stmt = $db->query("DESCRIBE vehicles");
echo '<h3>Updated vehicles structure:</h3><pre>';
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . "\n";
}
echo '</pre>';
echo '<p><a href="/vehicles.php">Go to Vehicle Management</a></p>';
