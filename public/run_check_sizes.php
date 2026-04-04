<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

echo "=== 系統大小檢查 ===\n\n";

// 1. 資料庫大小
$stmt = $db->query("
    SELECT table_name,
           ROUND(data_length/1024/1024, 2) AS data_mb,
           ROUND(index_length/1024/1024, 2) AS index_mb,
           table_rows
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
    ORDER BY data_length DESC
");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalMB = 0;
echo "資料庫各表大小:\n";
foreach ($tables as $t) {
    $size = (float)$t['data_mb'] + (float)$t['index_mb'];
    $totalMB += $size;
    if ($size > 0.01) {
        echo sprintf("  %-35s %8s MB  %8s 筆\n", $t['table_name'], number_format($size, 2), number_format($t['table_rows']));
    }
}
echo sprintf("\n  資料庫合計: %.2f MB\n", $totalMB);

// 2. uploads 目錄大小
echo "\nuploads 目錄:\n";
$uploadDir = __DIR__ . '/uploads';
if (is_dir($uploadDir)) {
    $totalFiles = 0;
    $totalSize = 0;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $totalFiles++;
            $totalSize += $file->getSize();
        }
    }
    echo "  檔案數: {$totalFiles}\n";
    echo sprintf("  合計: %.2f MB\n", $totalSize / 1024 / 1024);
} else {
    echo "  uploads 目錄不存在\n";
}

echo "\n完成\n";
