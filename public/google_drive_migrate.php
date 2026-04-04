<?php
/**
 * 批次搬移檔案到 Google Drive
 * 用 auto-redirect 方式分批處理，避免 timeout
 * 用法: google_drive_migrate.php?type=customers&start=0
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }

require_once __DIR__ . '/../includes/GoogleDrive.php';
@set_time_limit(120);
header('Content-Type: text/html; charset=utf-8');

$type = isset($_GET['type']) ? $_GET['type'] : '';
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$batchSize = 5; // 每批處理 5 個檔案（避免 timeout）

// 讀取資料夾 ID
$foldersFile = __DIR__ . '/../data/google_drive_folders.json';
if (!file_exists($foldersFile)) {
    die('請先執行 google_drive_init.php 建立資料夾結構');
}
$folders = json_decode(file_get_contents($foldersFile), true);

// 進度檔案
$progressFile = __DIR__ . '/../data/migrate_progress_' . $type . '.json';
$progress = array();
if (file_exists($progressFile)) {
    $progress = json_decode(file_get_contents($progressFile), true);
    if (!is_array($progress)) {
        $progress = array();
    }
}

// Google Drive 子資料夾快取
$subFoldersFile = __DIR__ . '/../data/migrate_subfolders_' . $type . '.json';
$subFolders = array();
if (file_exists($subFoldersFile)) {
    $subFolders = json_decode(file_get_contents($subFoldersFile), true);
    if (!is_array($subFolders)) {
        $subFolders = array();
    }
}

$drive = new GoogleDrive();
$db = Database::getInstance();

$validTypes = array('customers', 'cases', 'case_payments', 'staff');
if (!in_array($type, $validTypes)) {
    echo '<h2>Google Drive 批次搬移</h2>';
    echo '<p>選擇要搬移的類型：</p>';
    echo '<ul>';
    foreach ($validTypes as $t) {
        $count = 0;
        if ($t === 'customers') {
            $count = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
        } elseif ($t === 'cases') {
            $count = $db->query("SELECT COUNT(*) FROM case_attachments")->fetchColumn();
        } elseif ($t === 'case_payments') {
            $count = $db->query("SELECT COUNT(*) FROM case_payments WHERE image_path IS NOT NULL AND image_path != ''")->fetchColumn();
        } elseif ($t === 'staff') {
            $count = $db->query("SELECT COUNT(*) FROM staff_documents")->fetchColumn();
        }
        $done = 0;
        $pf = __DIR__ . '/../data/migrate_progress_' . $t . '.json';
        if (file_exists($pf)) {
            $pd = json_decode(file_get_contents($pf), true);
            $done = is_array($pd) ? count($pd) : 0;
        }
        echo "<li><a href='?type={$t}'>{$t}</a> — {$count} 筆";
        if ($done > 0) {
            echo " (已完成 {$done} 筆)";
        }
        echo "</li>\n";
    }
    echo '</ul>';
    exit;
}

if (!isset($folders[$type])) {
    die("Google Drive 資料夾 '{$type}' 不存在，請先執行 google_drive_init.php");
}
$parentFolderId = $folders[$type];

// 取得檔案清單
$files = array();
if ($type === 'customers') {
    $stmt = $db->query("SELECT id, customer_id, file_path, file_name FROM customer_files ORDER BY id LIMIT {$start}, {$batchSize}");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalStmt = $db->query("SELECT COUNT(*) FROM customer_files");
} elseif ($type === 'cases') {
    $stmt = $db->query("SELECT id, case_id, file_path, file_name FROM case_attachments ORDER BY id LIMIT {$start}, {$batchSize}");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalStmt = $db->query("SELECT COUNT(*) FROM case_attachments");
} elseif ($type === 'case_payments') {
    $stmt = $db->query("SELECT id, case_id, image_path FROM case_payments WHERE image_path IS NOT NULL AND image_path != '' ORDER BY id LIMIT {$start}, {$batchSize}");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalStmt = $db->query("SELECT COUNT(*) FROM case_payments WHERE image_path IS NOT NULL AND image_path != ''");
} elseif ($type === 'staff') {
    $stmt = $db->query("SELECT id, user_id, file_path, file_name FROM staff_documents ORDER BY id LIMIT {$start}, {$batchSize}");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalStmt = $db->query("SELECT COUNT(*) FROM staff_documents");
}
$total = $totalStmt->fetchColumn();

echo "<h2>Google Drive 搬移: {$type}</h2>\n";
echo "<p>總計: {$total} 筆 | 目前位置: {$start} | 每批: {$batchSize}</p>\n";
echo "<pre>\n";

if (empty($files)) {
    echo "所有檔案已處理完成！\n";
    $doneCount = count($progress);
    echo "成功上傳: {$doneCount} 筆\n";
    echo "</pre>\n";
    exit;
}

$uploaded = 0;
$skipped = 0;
$errors = 0;

foreach ($files as $file) {
    $fileId = $file['id'];

    // 已處理過就跳過
    if (isset($progress[$fileId])) {
        echo "[SKIP] #{$fileId} 已處理過\n";
        $skipped++;
        continue;
    }

    // 組合檔案路徑
    if ($type === 'case_payments') {
        $relPath = $file['image_path'];
        $subId = $file['case_id'];
        $fileName = basename($relPath);
    } else {
        $relPath = $file['file_path'];
        $subId = isset($file['customer_id']) ? $file['customer_id'] : (isset($file['case_id']) ? $file['case_id'] : $file['user_id']);
        $fileName = !empty($file['file_name']) ? $file['file_name'] : basename($relPath);
    }

    // 本機路徑（伺服器上）
    $localPath = __DIR__ . '/' . ltrim($relPath, '/');
    if (!file_exists($localPath)) {
        $localPath = __DIR__ . '/../' . ltrim($relPath, '/');
    }

    if (!file_exists($localPath)) {
        echo "[MISS] #{$fileId} 檔案不存在: {$relPath}\n";
        $progress[$fileId] = array('status' => 'missing', 'path' => $relPath);
        $skipped++;
        continue;
    }

    // 確認子資料夾存在（例如 customers/12345）
    $subKey = strval($subId);
    if (!isset($subFolders[$subKey])) {
        try {
            $subFolderId = $drive->createFolder($subKey, $parentFolderId);
            $subFolders[$subKey] = $subFolderId;
            file_put_contents($subFoldersFile, json_encode($subFolders));
        } catch (Exception $e) {
            echo "[ERROR] #{$fileId} 建立子資料夾失敗: " . $e->getMessage() . "\n";
            $errors++;
            continue;
        }
    }

    // 上傳
    try {
        $result = $drive->uploadFile($localPath, $fileName, $subFolders[$subKey]);
        $progress[$fileId] = array(
            'status'   => 'uploaded',
            'drive_id' => $result['id'],
            'path'     => $relPath,
        );
        echo "[OK] #{$fileId} {$fileName} -> {$result['id']}\n";
        $uploaded++;
    } catch (Exception $e) {
        echo "[ERROR] #{$fileId} 上傳失敗: " . $e->getMessage() . "\n";
        $progress[$fileId] = array('status' => 'error', 'error' => $e->getMessage(), 'path' => $relPath);
        $errors++;
    }

    // 每筆都儲存進度
    file_put_contents($progressFile, json_encode($progress));
}

echo "\n本批結果: 上傳 {$uploaded}, 跳過 {$skipped}, 失敗 {$errors}\n";
echo "累計已處理: " . count($progress) . " / {$total}\n";
echo "</pre>\n";

$nextStart = $start + $batchSize;
if ($nextStart < $total) {
    $pct = round($nextStart / $total * 100, 1);
    echo "<p>進度: {$pct}% — 3 秒後自動繼續...</p>\n";
    echo "<script>setTimeout(function(){ location.href='?type={$type}&start={$nextStart}'; }, 3000);</script>\n";
    echo "<p><a href='?type={$type}&start={$nextStart}'>手動繼續</a> | <a href='?'>停止</a></p>\n";
} else {
    echo "<p><strong>搬移完成！</strong></p>\n";
    echo "<p><a href='?'>返回</a></p>\n";
}
