<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$items = $db->query("SELECT category, name, unit, default_price FROM engineering_items ORDER BY category, sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
$current = '';
foreach ($items as $i) {
    if ($i['category'] !== $current) {
        $current = $i['category'];
        echo "\n【{$current}】\n";
    }
    echo "  - {$i['name']} ({$i['unit']}) \${$i['default_price']}\n";
}
echo "\n總計: " . count($items) . " 筆\n";
