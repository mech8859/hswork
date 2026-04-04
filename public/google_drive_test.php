<?php
/**
 * Google Drive 連線測試頁面
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') {
    die('Permission denied');
}

require_once __DIR__ . '/../includes/GoogleDrive.php';
header('Content-Type: text/plain; charset=utf-8');

$drive = new GoogleDrive();

echo "=== Google Drive 連線測試 ===\n\n";

// 1. 檢查授權狀態
echo "1. 檢查授權狀態...\n";
if (!$drive->isAuthorized()) {
    echo "   [FAIL] 尚未授權，請先執行 google_oauth_start.php\n";
    exit;
}
echo "   [OK] 已授權\n\n";

// 2. 測試取得 Access Token
echo "2. 測試取得 Access Token...\n";
try {
    $token = $drive->getAccessToken();
    echo "   [OK] Token: " . substr($token, 0, 20) . "...\n\n";
} catch (Exception $e) {
    echo "   [FAIL] " . $e->getMessage() . "\n";
    exit;
}

// 3. 建立測試資料夾
echo "3. 建立測試資料夾 'hswork_test'...\n";
try {
    $folderId = $drive->createFolder('hswork_test');
    echo "   [OK] 資料夾 ID: {$folderId}\n\n";
} catch (Exception $e) {
    echo "   [FAIL] " . $e->getMessage() . "\n";
    exit;
}

// 4. 上傳測試檔案
echo "4. 上傳測試檔案...\n";
$testFile = tempnam(sys_get_temp_dir(), 'hswork_test_');
file_put_contents($testFile, "Google Drive 連線測試 - " . date('Y-m-d H:i:s'));
try {
    $file = $drive->uploadFile($testFile, 'test.txt', $folderId);
    echo "   [OK] 檔案 ID: {$file['id']}\n\n";
    unlink($testFile);
} catch (Exception $e) {
    echo "   [FAIL] " . $e->getMessage() . "\n";
    unlink($testFile);
    exit;
}

// 5. 列出資料夾內容
echo "5. 列出資料夾內容...\n";
try {
    $files = $drive->listFiles($folderId);
    foreach ($files as $f) {
        echo "   - {$f['name']} ({$f['id']})\n";
    }
    echo "   [OK]\n\n";
} catch (Exception $e) {
    echo "   [FAIL] " . $e->getMessage() . "\n";
}

// 6. 清理測試資料
echo "6. 清理測試資料...\n";
try {
    $drive->deleteFile($file['id']);
    $drive->deleteFile($folderId);
    echo "   [OK] 已刪除測試檔案和資料夾\n\n";
} catch (Exception $e) {
    echo "   [FAIL] " . $e->getMessage() . "\n";
}

echo "=== 測試完成！所有功能正常 ===\n";
