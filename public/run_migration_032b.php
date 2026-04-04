<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>Migration 032b: 租賃公司聯絡人</h2>';
try {
    $cols = array_column($db->query("SHOW COLUMNS FROM vehicles")->fetchAll(), 'Field');
    $adds = array();
    if (!in_array('leasing_contact', $cols)) $adds[] = "ADD COLUMN leasing_contact VARCHAR(50) DEFAULT NULL COMMENT '租賃公司聯絡人'";
    if (!in_array('leasing_phone', $cols)) $adds[] = "ADD COLUMN leasing_phone VARCHAR(30) DEFAULT NULL COMMENT '租賃公司電話'";
    if (!in_array('leasing_mobile', $cols)) $adds[] = "ADD COLUMN leasing_mobile VARCHAR(30) DEFAULT NULL COMMENT '租賃公司行動電話'";
    if (!in_array('leasing_tax_id', $cols)) $adds[] = "ADD COLUMN leasing_tax_id VARCHAR(20) DEFAULT NULL COMMENT '租賃公司統編'";

    if (!empty($adds)) {
        $db->exec("ALTER TABLE vehicles " . implode(', ', $adds));
        echo '<p style="color:green">✓ 已新增 ' . count($adds) . ' 個欄位</p>';
    } else {
        echo '<p>已是最新</p>';
    }
} catch (Exception $e) {
    echo '<p style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '<br><a href="/vehicles.php">返回車輛管理</a>';
