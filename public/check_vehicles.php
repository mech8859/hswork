<?php
require_once '/raid/vhost/hswork.com.tw/includes/Database.php';
try {
    $pdo = Database::getInstance();
    $result = $pdo->query("SHOW TABLES LIKE 'vehicles'");
    $row = $result->fetch();
    echo $row ? "vehicles 資料表存在" : "vehicles 資料表不存在";
    
    echo "<br><br>所有資料表：<br>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) echo "- $t<br>";
} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage();
}
