<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 找出分類為 residential 但名稱看起來不像個人的
$stmt = $db->query("
    SELECT customer_no, name, site_address, category FROM customers 
    WHERE category = 'residential' 
    AND (
        name LIKE '%公司%' OR name LIKE '%企業%' OR name LIKE '%工廠%' OR name LIKE '%工業%'
        OR name LIKE '%醫院%' OR name LIKE '%診所%' OR name LIKE '%銀行%' OR name LIKE '%郵局%'
        OR name LIKE '%學校%' OR name LIKE '%國小%' OR name LIKE '%國中%' OR name LIKE '%大學%'
        OR name LIKE '%社區%' OR name LIKE '%管委會%' OR name LIKE '%大廈%'
        OR name LIKE '%餐廳%' OR name LIKE '%小吃%' OR name LIKE '%咖啡%' OR name LIKE '%早餐%'
        OR name LIKE '%旅館%' OR name LIKE '%飯店%' OR name LIKE '%民宿%'
        OR name LIKE '%教會%' OR name LIKE '%寺%' OR name LIKE '%廟%' OR name LIKE '%宮%'
        OR name LIKE '%協會%' OR name LIKE '%基金會%'
        OR name LIKE '%農會%' OR name LIKE '%漁會%'
        OR name LIKE '%市政%' OR name LIKE '%公所%' OR name LIKE '%政府%'
        OR name LIKE '%商行%' OR name LIKE '%行號%'
        OR name LIKE '%幼兒園%' OR name LIKE '%幼稚園%' OR name LIKE '%補習班%'
        OR name LIKE '%藥局%' OR name LIKE '%藥房%'
        OR name LIKE '%眼鏡%' OR name LIKE '%美髮%' OR name LIKE '%美容%'
        OR name LIKE '%超市%' OR name LIKE '%賣場%' OR name LIKE '%超商%'
        OR name LIKE '%洗衣%' OR name LIKE '%加油站%'
        OR name LIKE '%建設%' OR name LIKE '%營造%'
        OR name LIKE '%物流%' OR name LIKE '%運輸%' OR name LIKE '%貨運%'
        OR name LIKE '%保全%' OR name LIKE '%KTV%'
        OR name LIKE '%股份%' OR name LIKE '%有限%'
    )
    ORDER BY name
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "分類為 residential 但名稱疑似非個人: " . count($rows) . " 筆\n\n";
foreach (array_slice($rows, 0, 50) as $r) {
    echo "{$r['customer_no']} | {$r['name']}\n";
}
if (count($rows) > 50) echo "\n... 還有 " . (count($rows)-50) . " 筆\n";

// 也看看沒有被上面 LIKE 抓到但可能分錯的
echo "\n\n=== residential 總數 ===\n";
$total = $db->query("SELECT COUNT(*) FROM customers WHERE category = 'residential'")->fetchColumn();
echo "residential: {$total}\n";

$allTotal = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
echo "全部: {$allTotal}\n";
echo "比例: " . round($total/$allTotal*100, 1) . "%\n";
