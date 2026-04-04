<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗: ' . $e->getMessage());
}

echo '<h2>Migration: 請款資訊欄位</h2>';

$cols = array(
    array('billing_title', "VARCHAR(200) DEFAULT NULL COMMENT '發票抬頭'"),
    array('billing_tax_id', "VARCHAR(20) DEFAULT NULL COMMENT '統一編號'"),
    array('billing_contact', "VARCHAR(100) DEFAULT NULL COMMENT '帳務請款聯絡人'"),
    array('billing_phone', "VARCHAR(50) DEFAULT NULL COMMENT '請款聯絡人電話'"),
    array('billing_mobile', "VARCHAR(50) DEFAULT NULL COMMENT '手機'"),
    array('billing_address', "VARCHAR(500) DEFAULT NULL COMMENT '發票寄送地址'"),
    array('billing_email', "VARCHAR(200) DEFAULT NULL COMMENT '電子發票寄送mail'"),
    array('billing_note', "TEXT DEFAULT NULL COMMENT '請款備註'"),
);

foreach ($cols as $c) {
    try {
        $db->exec("ALTER TABLE cases ADD COLUMN " . $c[0] . " " . $c[1]);
        echo "<p style='color:green'>✓ " . $c[0] . "</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<p style='color:#888'>" . $c[0] . " 已存在</p>";
        } else {
            echo "<p style='color:red'>✗ " . $c[0] . ": " . $e->getMessage() . "</p>";
        }
    }
}
echo '<p style="color:green;font-weight:bold">完成！</p>';
echo '<p><a href="cases.php">返回案件管理</a></p>';
