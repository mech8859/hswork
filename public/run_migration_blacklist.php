<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗: ' . $e->getMessage());
}

echo '<h2>Migration: 客戶黑名單欄位</h2>';

$cols = array(
    array('is_blacklisted', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '黑名單'"),
    array('blacklist_reason', "TEXT DEFAULT NULL COMMENT '黑名單原因'"),
);

foreach ($cols as $c) {
    try {
        $db->exec("ALTER TABLE customers ADD COLUMN " . $c[0] . " " . $c[1]);
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
echo '<p><a href="customers.php">返回客戶管理</a></p>';
