<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗');
}
echo '<h2>Products 表結構</h2><pre>';
$cols = $db->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ')' . ($c['Key'] === 'PRI' ? ' PK' : '') . "\n";
}
echo '</pre>';

echo '<h3>前3筆資料</h3><pre>';
$rows = $db->query("SELECT * FROM products LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    foreach ($r as $k => $v) {
        if ($v) echo "$k: $v\n";
    }
    echo "---\n";
}
echo '</pre>';

$total = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
echo "<p>總筆數: $total</p>";
