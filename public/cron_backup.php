<?php
/**
 * Cron 自動備份入口
 * 用 secret key 認證，不需登入
 * URL: https://hswork.com.tw/cron_backup.php?key=YOUR_KEY
 */
$cronKey = 'hswork_backup_2026_secret';

if (($_GET['key'] ?? '') !== $cronKey) {
    http_response_code(403);
    die('Forbidden');
}

// 模擬已登入（cron 不經過 session）
define('CRON_MODE', true);

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/GoogleDrive.php';
@set_time_limit(300);
@ini_set('memory_limit', '256M');
header('Content-Type: text/plain; charset=utf-8');

$foldersFile = __DIR__ . '/../data/google_drive_folders.json';
if (!file_exists($foldersFile)) {
    die('Google Drive folders not initialized');
}
$folders = json_decode(file_get_contents($foldersFile), true);
$backupFolderId = $folders['backups'];

$drive = new GoogleDrive();
$db = Database::getInstance();

$date = date('Y-m-d');
$backupDir = __DIR__ . '/../data/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// 檢查今天是否已備份
$todayFile = glob($backupDir . "/hswork_{$date}*.sql.gz");
if (!empty($todayFile)) {
    echo "Today's backup already exists: " . basename($todayFile[0]) . "\n";
    exit;
}

$sqlFile = $backupDir . "/hswork_{$date}.sql";
$gzFile = $sqlFile . '.gz';

echo "=== Auto Backup " . date('Y-m-d H:i:s') . " ===\n";

// 匯出所有表
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$fp = fopen($sqlFile, 'w');
fwrite($fp, "-- hswork auto backup\n-- Date: " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

foreach ($tables as $table) {
    $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($fp, $createStmt['Create Table'] . ";\n\n");

    $rowCount = $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    if ($rowCount > 0) {
        for ($offset = 0; $offset < $rowCount; $offset += 500) {
            $rows = $db->query("SELECT * FROM `{$table}` LIMIT 500 OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) break;
            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`, `', $columns) . '`';
            foreach ($rows as $row) {
                $values = array();
                foreach ($row as $val) {
                    $values[] = ($val === null) ? 'NULL' : $db->quote($val);
                }
                fwrite($fp, "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");\n");
            }
        }
    }
    fwrite($fp, "\n");
}
fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($fp);

echo "SQL exported: " . round(filesize($sqlFile) / 1048576, 2) . " MB\n";

// 壓縮
$gzContent = gzencode(file_get_contents($sqlFile), 6);
file_put_contents($gzFile, $gzContent);
unlink($sqlFile);
echo "Compressed: " . round(filesize($gzFile) / 1048576, 2) . " MB\n";

// 上傳到 Google Drive
try {
    $result = $drive->uploadFile($gzFile, basename($gzFile), $backupFolderId);
    echo "Uploaded to Drive: {$result['id']}\n";
} catch (Exception $e) {
    echo "Drive upload failed: " . $e->getMessage() . "\n";
}

// 清理本機（保留 3 份）
$backupFiles = glob($backupDir . '/hswork_*.sql.gz');
rsort($backupFiles);
for ($i = 3; $i < count($backupFiles); $i++) {
    unlink($backupFiles[$i]);
}

// 清理 Drive（保留 10 份）
try {
    $driveFiles = $drive->listFiles($backupFolderId);
    if (count($driveFiles) > 10) {
        usort($driveFiles, function($a, $b) { return strcmp($b['name'], $a['name']); });
        for ($i = 10; $i < count($driveFiles); $i++) {
            $drive->deleteFile($driveFiles[$i]['id']);
        }
    }
} catch (Exception $e) {}

echo "=== Done ===\n";
