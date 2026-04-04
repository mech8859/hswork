<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
$db = Database::getInstance();

// 清 DB 記錄
$deleted = $db->exec("DELETE FROM customer_files");
echo "DB customer_files 刪除: {$deleted} 筆\n";

// 清主機上的檔案目錄
$baseDir = __DIR__ . '/uploads/customers';
$dirs = glob($baseDir . '/*', GLOB_ONLYDIR);
$fileCount = 0;
$dirCount = 0;
foreach ($dirs as $d) {
    $files = glob($d . '/*');
    foreach ($files as $f) {
        if (is_file($f)) { unlink($f); $fileCount++; }
    }
    rmdir($d);
    $dirCount++;
}
echo "刪除檔案: {$fileCount} 個\n";
echo "刪除目錄: {$dirCount} 個\n";
echo "\n完成！\n";
