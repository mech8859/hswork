<?php
require_once 'config/database.php';
$result = $pdo->query("SHOW TABLES LIKE 'vehicles'");
$row = $result->fetch();
echo $row ? "vehicles 資料表存在\n" : "vehicles 資料表不存在\n";
