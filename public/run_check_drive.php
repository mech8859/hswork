<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
require_once __DIR__ . '/../includes/GoogleDrive.php';
header('Content-Type: text/plain; charset=utf-8');

$foldersFile = __DIR__ . '/../data/google_drive_folders.json';
if (!file_exists($foldersFile)) { die('Google Drive 尚未初始化'); }
$folders = json_decode(file_get_contents($foldersFile), true);

$drive = new GoogleDrive();

echo "=== Google Drive 備份資料夾 ===\n";
try {
    $files = $drive->listFiles($folders['backups']);
    usort($files, function($a, $b) { return strcmp($b['name'], $a['name']); });
    foreach ($files as $f) {
        echo "  " . $f['name'] . " (" . (isset($f['size']) ? round($f['size']/1048576, 2) . ' MB' : '-') . ")\n";
    }
    if (empty($files)) echo "  (空)\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== 各類檔案數量 ===\n";
$types = array('customers' => '客戶', 'cases' => '案件', 'case_payments' => '帳款', 'staff' => '人員', 'products' => '產品', 'worklogs' => '施工');
foreach ($types as $key => $label) {
    if (!isset($folders[$key])) continue;
    try {
        $list = $drive->listFiles($folders[$key]);
        echo "  {$label}: " . count($list) . " 個子資料夾/檔案\n";
    } catch (Exception $e) {
        echo "  {$label}: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "\n=== Token 狀態 ===\n";
try {
    $token = $drive->getAccessToken();
    echo "  Access Token: 有效\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}
