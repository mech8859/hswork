<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

echo "=== case_material_estimates 欄位 ===\n";
$cols = $db->query("SHOW COLUMNS FROM case_material_estimates")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo "{$c['Field']}: {$c['Type']}\n";

echo "\n=== products 的 cost 欄位 ===\n";
$cols2 = $db->query("SHOW COLUMNS FROM products WHERE Field IN ('cost','unit_price','price')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) echo "{$c['Field']}: {$c['Type']}\n";
