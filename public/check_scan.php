<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$total = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
$erp = $db->query("SELECT COUNT(*) FROM customer_files WHERE note = '從ERP客戶資料匯入'")->fetchColumn();
echo "customer_files 總數: {$total}\n";
echo "ERP匯入的: {$erp}\n";

// 看上傳目錄
echo "\n=== 主機上 uploads/customers/ ===\n";
$dirs = glob(__DIR__ . '/uploads/customers/*', GLOB_ONLYDIR);
echo "客戶目錄數: " . count($dirs) . "\n";

$totalFiles = 0;
foreach ($dirs as $d) {
    $files = glob($d . '/*.{jpg,JPG,jpeg,png,pdf}', GLOB_BRACE);
    $totalFiles += count($files);
}
echo "檔案總數: {$totalFiles}\n";
