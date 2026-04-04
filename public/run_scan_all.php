<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
$db = Database::getInstance();

// 先清掉之前測試的
$db->exec("DELETE FROM customer_files WHERE note = '從ERP客戶資料匯入'");
echo "清除舊資料完成\n";

$sql = file_get_contents(__DIR__ . '/../database/customer_scan_import_all.sql');
$statements = array_filter(array_map('trim', explode(";\n", $sql)));
$ok = 0; $err = 0;
foreach ($statements as $stmt) {
    $stmt = rtrim(trim($stmt), ';');
    if (empty($stmt) || strpos($stmt, '--') === 0 || strpos($stmt, 'SET') === 0) continue;
    try {
        $db->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        $err++;
        if ($err <= 5) echo "ERR: " . $e->getMessage() . "\n";
    }
}
$count = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
echo "\nINSERT 批次: {$ok} 成功, {$err} 錯誤\n";
echo "customer_files 總數: {$count}\n";
