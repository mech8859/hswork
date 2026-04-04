<?php
/**
 * Google Drive 初始化 — 建立正式資料夾結構
 * 只需執行一次
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }

require_once __DIR__ . '/../includes/GoogleDrive.php';
header('Content-Type: text/plain; charset=utf-8');

$drive = new GoogleDrive();

echo "=== 建立 Google Drive 資料夾結構 ===\n\n";

try {
    // 1. 建立根資料夾
    echo "1. 建立根資料夾 'hswork'...\n";
    $rootId = $drive->createFolder('hswork');
    echo "   [OK] ID: {$rootId}\n\n";

    // 2. 建立子資料夾
    $folders = array(
        'customers'     => '客戶掃描檔',
        'cases'         => '案件附件',
        'case_payments' => '請款單據',
        'staff'         => '人員文件',
        'backups'       => '資料庫備份',
    );

    $folderIds = array('root' => $rootId);

    foreach ($folders as $name => $desc) {
        echo "2. 建立 '{$name}' ({$desc})...\n";
        $folderId = $drive->createFolder($name, $rootId);
        $folderIds[$name] = $folderId;
        echo "   [OK] ID: {$folderId}\n\n";
    }

    // 3. 儲存資料夾 ID 對照表
    $configFile = __DIR__ . '/../data/google_drive_folders.json';
    file_put_contents($configFile, json_encode($folderIds, JSON_PRETTY_PRINT));
    echo "3. 資料夾 ID 已儲存到 data/google_drive_folders.json\n\n";

    echo "=== 完成！資料夾結構如下 ===\n";
    echo "hswork/ ({$rootId})\n";
    foreach ($folders as $name => $desc) {
        echo "  ├── {$name}/ ({$folderIds[$name]}) — {$desc}\n";
    }

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
