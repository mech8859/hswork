<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$cases = $db->query("SELECT case_number, customer_no, customer_name FROM cases WHERE case_number LIKE '2026-%' ORDER BY case_number")->fetchAll(PDO::FETCH_ASSOC);
$custs = $db->query("SELECT case_number, customer_no, name FROM customers WHERE case_number LIKE '2026-%' ORDER BY case_number")->fetchAll(PDO::FETCH_ASSOC);

echo "案件管理 2026: " . count($cases) . " 筆\n";
if (count($cases) > 0) echo "  範圍: {$cases[0]['case_number']} ~ " . end($cases)['case_number'] . "\n";
echo "客戶資料 2026: " . count($custs) . " 筆\n";
if (count($custs) > 0) echo "  範圍: {$custs[0]['case_number']} ~ " . end($custs)['case_number'] . "\n";

$case_map = array();
foreach ($cases as $c) $case_map[$c['case_number']] = $c;
$cust_map = array();
foreach ($custs as $c) $cust_map[$c['case_number']] = $c;

$overlap = array_intersect_key($case_map, $cust_map);
echo "\n重疊編號: " . count($overlap) . " 筆\n";
$i = 0;
foreach ($overlap as $cn => $c) {
    $cu = $cust_map[$cn];
    echo "  {$cn} | 案件:{$c['customer_name']} ({$c['customer_no']}) | 客戶:{$cu['name']} ({$cu['customer_no']})\n";
    if (++$i >= 20) { echo "  ...\n"; break; }
}

// 案件有但客戶沒有
$case_only = array_diff_key($case_map, $cust_map);
echo "\n僅案件有: " . count($case_only) . " 筆\n";
// 客戶有但案件沒有
$cust_only = array_diff_key($cust_map, $case_map);
echo "僅客戶有: " . count($cust_only) . " 筆\n";
