<?php
/**
 * 資料庫備份並上傳到 Google Drive
 * 分三步驟: step=export(匯出SQL) → step=compress(壓縮) → step=upload(上傳)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }

require_once __DIR__ . '/../includes/GoogleDrive.php';
@set_time_limit(120);
@ini_set('memory_limit', '256M');

$step = isset($_GET['step']) ? $_GET['step'] : 'export';
$tableIndex = isset($_GET['t']) ? intval($_GET['t']) : 0;

$foldersFile = __DIR__ . '/../data/google_drive_folders.json';
if (!file_exists($foldersFile)) {
    die('請先執行 google_drive_init.php 建立資料夾結構');
}
$folders = json_decode(file_get_contents($foldersFile), true);
$backupFolderId = $folders['backups'];

$date = date('Y-m-d');
$backupDir = __DIR__ . '/../data/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
$sqlFile = $backupDir . "/hswork_{$date}.sql";

$db = Database::getInstance();

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

if ($step === 'export') {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $totalTables = count($tables);

    if ($tableIndex === 0) {
        // 開始新備份，寫入檔頭
        $fp = fopen($sqlFile, 'w');
        fwrite($fp, "-- hswork database backup\n");
        fwrite($fp, "-- Date: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        fclose($fp);
        echo "=== 資料庫備份開始 ===\n";
        echo "時間: " . date('Y-m-d H:i:s') . "\n";
        echo "共 {$totalTables} 張表\n\n";
    }

    // 每次處理 5 張表
    $batchSize = 5;
    $endIndex = min($tableIndex + $batchSize, $totalTables);

    $fp = fopen($sqlFile, 'a');
    for ($i = $tableIndex; $i < $endIndex; $i++) {
        $table = $tables[$i];
        echo "匯出 {$table} (" . ($i + 1) . "/{$totalTables})...\n";
        ob_flush();
        flush();

        $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fp, $createStmt['Create Table'] . ";\n\n");

        $rowCount = $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if ($rowCount > 0) {
            $batch = 500;
            for ($offset = 0; $offset < $rowCount; $offset += $batch) {
                $rows = $db->query("SELECT * FROM `{$table}` LIMIT {$batch} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
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
        echo "  [OK] {$rowCount} 筆\n";
    }
    fclose($fp);

    if ($endIndex < $totalTables) {
        $pct = round($endIndex / $totalTables * 100);
        echo "\n進度: {$pct}% ({$endIndex}/{$totalTables}) — 2秒後繼續...\n";
        echo '</pre>';
        echo "<script>setTimeout(function(){ location.href='?step=export&t={$endIndex}'; }, 2000);</script>";
        echo "<a href='?step=export&t={$endIndex}'>手動繼續</a>";
    } else {
        // 寫入檔尾
        file_put_contents($sqlFile, "SET FOREIGN_KEY_CHECKS = 1;\n", FILE_APPEND);
        $size = round(filesize($sqlFile) / 1048576, 2);
        echo "\n匯出完成！SQL 檔案: {$size} MB\n";
        echo "2秒後壓縮...\n";
        echo '</pre>';
        echo "<script>setTimeout(function(){ location.href='?step=compress'; }, 2000);</script>";
    }

} elseif ($step === 'compress') {
    echo "壓縮備份檔...\n";
    ob_flush(); flush();

    $gzFile = $sqlFile . '.gz';
    $content = file_get_contents($sqlFile);
    $gzContent = gzencode($content, 6);
    file_put_contents($gzFile, $gzContent);
    unlink($sqlFile);

    $gzSize = round(filesize($gzFile) / 1048576, 2);
    echo "[OK] 壓縮後: {$gzSize} MB\n\n";
    echo "2秒後上傳到 Google Drive...\n";
    echo '</pre>';
    echo "<script>setTimeout(function(){ location.href='?step=upload'; }, 2000);</script>";

} elseif ($step === 'upload') {
    $gzFile = $sqlFile . '.gz';
    if (!file_exists($gzFile)) {
        echo "[ERROR] 找不到壓縮檔，請重新執行備份\n";
        echo '</pre>';
        exit;
    }

    echo "上傳到 Google Drive...\n";
    ob_flush(); flush();

    $drive = new GoogleDrive();
    try {
        $result = $drive->uploadFile($gzFile, basename($gzFile), $backupFolderId);
        echo "[OK] 上傳成功！Drive 檔案 ID: {$result['id']}\n\n";
    } catch (Exception $e) {
        echo "[ERROR] " . $e->getMessage() . "\n\n";
    }

    // 清理本機舊備份（保留 3 份）
    $backupFiles = glob($backupDir . '/hswork_*.sql.gz');
    if ($backupFiles) {
        rsort($backupFiles);
        for ($i = 3; $i < count($backupFiles); $i++) {
            unlink($backupFiles[$i]);
            echo "刪除本機舊備份: " . basename($backupFiles[$i]) . "\n";
        }
    }

    // 清理 Drive 舊備份（保留 10 份）
    try {
        $driveFiles = $drive->listFiles($backupFolderId);
        if (count($driveFiles) > 10) {
            usort($driveFiles, function($a, $b) { return strcmp($b['name'], $a['name']); });
            for ($i = 10; $i < count($driveFiles); $i++) {
                $drive->deleteFile($driveFiles[$i]['id']);
                echo "刪除 Drive 舊備份: {$driveFiles[$i]['name']}\n";
            }
        }
    } catch (Exception $e) {}

    echo "\n=== 備份完成！ ===\n";
    echo '</pre>';
}
