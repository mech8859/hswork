<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 進件公司
$companies = array('禾順', '理創');
$sort = 0;
foreach ($companies as $c) {
    $sort++;
    try {
        $db->prepare("INSERT INTO dropdown_options (category, option_key, label, sort_order, is_active, is_system) VALUES ('case_company', ?, ?, ?, 1, 0)")
           ->execute(array($c, $c, $sort));
        echo "OK: case_company - {$c}\n";
    } catch (PDOException $e) {
        echo "SKIP: {$c} - " . $e->getMessage() . "\n";
    }
}

// 案件來源
$sources = array('電話', '總公司', '總公司/網站', '老客戶', '店面', '官方LINE', '臉書', '私人');
$sort = 0;
foreach ($sources as $s) {
    $sort++;
    try {
        $db->prepare("INSERT INTO dropdown_options (category, option_key, label, sort_order, is_active, is_system) VALUES ('case_source', ?, ?, ?, 1, 0)")
           ->execute(array($s, $s, $sort));
        echo "OK: case_source - {$s}\n";
    } catch (PDOException $e) {
        echo "SKIP: {$s} - " . $e->getMessage() . "\n";
    }
}
echo "\n完成！";
