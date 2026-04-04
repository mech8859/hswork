<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// 直接看 Ragic JSON 中這筆的日期
$data = json_decode(file_get_contents(__DIR__ . '/../data/ragic_payables.json'), true);
foreach ($data as $rid => $rec) {
    if (($rec['付款單號'] ?? '') === 'P1-20260223-051') {
        echo "Ragic raw: 建立日期=" . $rec['建立日期'] . "\n";
        $converted = str_replace('/', '-', $rec['建立日期']);
        echo "Converted: " . $converted . "\n";
        break;
    }
}

// DB 中的值
echo "\nDB value:\n";
$stmt = $db->prepare("SELECT id, payable_number, create_date, ragic_id, updated_at FROM payables WHERE payable_number = ?");
$stmt->execute(array('P1-20260223-051'));
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if ($r) {
    foreach ($r as $k => $v) echo "$k: $v\n";
}

// 手動更新測試
echo "\nManual update test:\n";
$db->prepare("UPDATE payables SET create_date = '2025-12-30' WHERE payable_number = 'P1-20260223-051'")->execute();
$stmt2 = $db->prepare("SELECT create_date FROM payables WHERE payable_number = ?");
$stmt2->execute(array('P1-20260223-051'));
echo "After update: " . $stmt2->fetchColumn() . "\n";
