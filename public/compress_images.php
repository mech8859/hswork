<?php
/**
 * 批次壓縮 uploads 目錄下的圖片
 * 只處理 JPG/PNG，跳過已壓過的（< 500KB）
 * 用法：compress_images.php?dir=customers&batch=100&start=0
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }

set_time_limit(300);
ini_set('memory_limit', '128M');
header('Content-Type: text/html; charset=utf-8');
ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();

$targetDir = isset($_GET['dir']) ? $_GET['dir'] : 'customers';
$batchSize = isset($_GET['batch']) ? max((int)$_GET['batch'], 100) : 100;
$startFrom = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$maxWidth = isset($_GET['max']) ? (int)$_GET['max'] : 1920;
$quality = isset($_GET['q']) ? (int)$_GET['q'] : 75;
$minSize = 500 * 1024; // 500KB 以下不壓
$maxFileSize = 5 * 1048576; // 5MB 以上跳過（避免記憶體爆）
$dryRun = isset($_GET['dry']); // dry=1 只統計不壓縮

$baseDir = __DIR__ . '/uploads/' . $targetDir;
if (!is_dir($baseDir)) { die('目錄不存在: ' . htmlspecialchars($targetDir)); }

echo '<pre style="font-family:monospace;font-size:13px">';
echo "=== 圖片批次壓縮 ===\n";
echo "目錄: uploads/{$targetDir}\n";
echo "批次: {$batchSize} 張 (從第 {$startFrom} 張開始)\n";
echo "最大寬度: {$maxWidth}px | 品質: {$quality}%\n";
echo "最小處理門檻: " . round($minSize / 1024) . "KB\n";
if ($dryRun) echo "*** 預覽模式（不實際壓縮）***\n";
echo str_repeat('-', 60) . "\n\n";

// 收集所有圖片
$allImages = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, array('jpg', 'jpeg', 'png', 'bmp'))) continue;
    $allImages[] = $file->getPathname();
}

$totalImages = count($allImages);
echo "共找到 {$totalImages} 張圖片\n";

// 統計需壓縮的
$needCompress = 0;
$totalOrigSize = 0;
foreach ($allImages as $path) {
    $size = filesize($path);
    $totalOrigSize += $size;
    if ($size >= $minSize) $needCompress++;
}
echo "需壓縮（>= 500KB）: {$needCompress} 張\n";
echo "目前總大小: " . round($totalOrigSize / 1073741824, 2) . " GB\n\n";

if ($dryRun) {
    // 統計各區間
    $ranges = array('< 100KB' => 0, '100-500KB' => 0, '500KB-1MB' => 0, '1-3MB' => 0, '3-5MB' => 0, '5MB+' => 0);
    $rangeSizes = array('< 100KB' => 0, '100-500KB' => 0, '500KB-1MB' => 0, '1-3MB' => 0, '3-5MB' => 0, '5MB+' => 0);
    foreach ($allImages as $path) {
        $s = filesize($path);
        if ($s < 100*1024) { $ranges['< 100KB']++; $rangeSizes['< 100KB'] += $s; }
        elseif ($s < 500*1024) { $ranges['100-500KB']++; $rangeSizes['100-500KB'] += $s; }
        elseif ($s < 1048576) { $ranges['500KB-1MB']++; $rangeSizes['500KB-1MB'] += $s; }
        elseif ($s < 3*1048576) { $ranges['1-3MB']++; $rangeSizes['1-3MB'] += $s; }
        elseif ($s < 5*1048576) { $ranges['3-5MB']++; $rangeSizes['3-5MB'] += $s; }
        else { $ranges['5MB+']++; $rangeSizes['5MB+']+= $s; }
    }
    echo "=== 檔案大小分佈 ===\n";
    echo str_pad('區間', 15) . str_pad('數量', 10) . str_pad('總大小', 15) . "\n";
    foreach ($ranges as $k => $v) {
        echo str_pad($k, 15) . str_pad(number_format($v), 10) . str_pad(round($rangeSizes[$k]/1048576, 1) . ' MB', 15) . "\n";
    }
    echo '</pre>';
    echo '<p><a href="?dir=' . urlencode($targetDir) . '&batch=50&start=0">開始壓縮（每批50張）</a></p>';
    exit;
}

// 開始壓縮
$batch = array_slice($allImages, $startFrom, $batchSize);
$compressed = 0;
$skipped = 0;
$errors = 0;
$savedBytes = 0;

foreach ($batch as $idx => $path) {
    $num = $startFrom + $idx + 1;
    $relPath = str_replace(__DIR__ . '/', '', $path);
    $origSize = filesize($path);

    if ($origSize < $minSize) {
        $skipped++;
        continue;
    }

    if ($origSize > $maxFileSize) {
        $skipped++;
        echo "[SKIP] #{$num} 檔案過大(" . round($origSize/1048576,1) . "MB): {$relPath}\n";
        continue;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    // 預估記憶體需求（寬×高×4bytes×2張）
    $imgInfo = @getimagesize($path);
    if ($imgInfo) {
        $memNeeded = $imgInfo[0] * $imgInfo[1] * 4 * 2.5;
        if ($memNeeded > 80 * 1048576) {
            $skipped++;
            echo "[SKIP] #{$num} 解析度過高({$imgInfo[0]}x{$imgInfo[1]}): {$relPath}\n";
            continue;
        }
    }

    // 載入圖片
    $src = null;
    if ($ext === 'png') {
        $src = @imagecreatefrompng($path);
    } elseif ($ext === 'bmp') {
        if (function_exists('imagecreatefrombmp')) {
            $src = @imagecreatefrombmp($path);
        }
    } else {
        $src = @imagecreatefromjpeg($path);
    }

    if (!$src) {
        $errors++;
        echo "[ERR] #{$num} 無法讀取: {$relPath}\n";
        continue;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $newW = $w;
    $newH = $h;

    // 需要縮小
    if ($w > $maxWidth || $h > $maxWidth) {
        if ($w > $h) {
            $newH = (int)round($h * $maxWidth / $w);
            $newW = $maxWidth;
        } else {
            $newW = (int)round($w * $maxWidth / $h);
            $newH = $maxWidth;
        }
    }

    // 建立目標圖
    $dst = @imagecreatetruecolor($newW, $newH);
    if (!$dst) {
        imagedestroy($src);
        $errors++;
        echo "[ERR] #{$num} 記憶體不足: {$relPath}\n";
        continue;
    }

    // PNG 透明度保留（轉 JPG 所以填白底）
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($src);

    // 存為 JPG
    $newPath = preg_replace('/\.(png|bmp)$/i', '.jpg', $path);
    $ok = @imagejpeg($dst, $newPath, $quality);
    imagedestroy($dst);

    if (!$ok) {
        $errors++;
        echo "[ERR] #{$num} 寫入失敗: {$relPath}\n";
        continue;
    }

    $newSize = filesize($newPath);

    // 壓縮後更大就還原
    if ($newSize >= $origSize) {
        if ($newPath !== $path) unlink($newPath);
        $skipped++;
        continue;
    }

    // 如果副檔名改了（png→jpg），刪除原檔
    if ($newPath !== $path) {
        unlink($path);
    }

    $saved = $origSize - $newSize;
    $savedBytes += $saved;
    $compressed++;
    $pct = round((1 - $newSize / $origSize) * 100);
    echo "[OK]  #{$num} {$relPath}  {$w}x{$h} → {$newW}x{$newH}  " .
         round($origSize/1024) . "KB → " . round($newSize/1024) . "KB  (-{$pct}%)\n";

    // 如果副檔名改了，更新 DB 路徑
    if ($newPath !== $path) {
        $oldRel = 'uploads/' . $targetDir . '/' . str_replace($baseDir . '/', '', $path);
        $newRel = 'uploads/' . $targetDir . '/' . str_replace($baseDir . '/', '', $newPath);
        if ($targetDir === 'customers') {
            $db = Database::getInstance();
            $db->prepare("UPDATE customers SET scan_file = REPLACE(scan_file, ?, ?) WHERE scan_file LIKE ?")->execute(array($oldRel, $newRel, '%' . basename($path) . '%'));
        }
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "壓縮: {$compressed} 張 | 跳過: {$skipped} 張 | 錯誤: {$errors} 張\n";
echo "節省: " . round($savedBytes / 1048576, 1) . " MB\n";

$nextStart = $startFrom + $batchSize;
echo '</pre>';

if ($nextStart < $totalImages) {
    $url = '?dir=' . urlencode($targetDir) . '&batch=' . $batchSize . '&start=' . $nextStart . '&max=' . $maxWidth . '&q=' . $quality;
    echo '<p style="font-size:1.2rem">';
    echo '進度: ' . min($nextStart, $totalImages) . ' / ' . $totalImages . ' (' . round(min($nextStart, $totalImages) / $totalImages * 100) . '%)<br>';
    echo '<a href="' . $url . '" style="background:#2196F3;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">繼續下一批 →</a>';
    echo ' <a href="?dir=' . urlencode($targetDir) . '&dry=1">停止</a>';
    echo '</p>';
    // 自動繼續（2秒後）
    echo '<script>setTimeout(function(){ window.location.href="' . $url . '"; }, 2000);</script>';
    echo '<p class="text-muted">2秒後自動繼續...</p>';
} else {
    echo '<p style="color:green;font-size:1.2rem;font-weight:bold">全部完成！</p>';
    echo '<p><a href="/check_disk.php">查看磁碟空間</a></p>';
}
