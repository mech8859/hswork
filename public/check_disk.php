<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:14px">';
echo "=== 磁碟空間分析 ===\n\n";

function dirSize($dir) {
    $size = 0;
    $count = 0;
    if (!is_dir($dir)) return array(0, 0);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
            $count++;
        }
    }
    return array($size, $count);
}

function fmt($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

// uploads 子目錄
$uploadsDir = __DIR__ . '/uploads';
$totalSize = 0;
$totalFiles = 0;
$rows = array();

if (is_dir($uploadsDir)) {
    $dirs = glob($uploadsDir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $d) {
        list($s, $c) = dirSize($d);
        $name = basename($d);
        $rows[] = array($name, $s, $c);
        $totalSize += $s;
        $totalFiles += $c;
    }
    // 根目錄散落檔案
    $rootFiles = glob($uploadsDir . '/*.*');
    $rootSize = 0;
    foreach ($rootFiles as $f) { $rootSize += filesize($f); }
    if ($rootSize > 0) {
        $rows[] = array('(root files)', $rootSize, count($rootFiles));
        $totalSize += $rootSize;
        $totalFiles += count($rootFiles);
    }
}

// 排序
usort($rows, function($a, $b) { return $b[1] - $a[1]; });

echo str_pad('目錄', 25) . str_pad('大小', 15) . str_pad('檔案數', 10) . "\n";
echo str_repeat('-', 50) . "\n";
foreach ($rows as $r) {
    echo str_pad($r[0], 25) . str_pad(fmt($r[1]), 15) . str_pad(number_format($r[2]), 10) . "\n";
}
echo str_repeat('-', 50) . "\n";
echo str_pad('uploads 合計', 25) . str_pad(fmt($totalSize), 15) . str_pad(number_format($totalFiles), 10) . "\n\n";

// www 其他目錄
echo "=== www 根目錄其他項目 ===\n";
$wwwDirs = array('modules', 'templates', 'includes', 'database');
foreach ($wwwDirs as $wd) {
    $path = dirname(__DIR__) . '/' . $wd;
    if (!is_dir($path)) $path = __DIR__ . '/../' . $wd;
    if (is_dir($path)) {
        list($s, $c) = dirSize($path);
        echo str_pad($wd, 25) . str_pad(fmt($s), 15) . str_pad(number_format($c), 10) . "\n";
    }
}

// www 根目錄 PHP 檔
$phpFiles = glob(__DIR__ . '/*.php');
$phpSize = 0;
foreach ($phpFiles as $f) { $phpSize += filesize($f); }
echo str_pad('www/*.php', 25) . str_pad(fmt($phpSize), 15) . str_pad(number_format(count($phpFiles)), 10) . "\n";

// migration scripts
$migFiles = glob(__DIR__ . '/run_migration_*.php');
echo "\n=== Migration 腳本 (" . count($migFiles) . " 個) ===\n";
$migSize = 0;
foreach ($migFiles as $f) { $migSize += filesize($f); }
echo "佔用: " . fmt($migSize) . "\n";

// uploads 前10大檔案
echo "\n=== uploads 前 10 大檔案 ===\n";
$allFiles = array();
if (is_dir($uploadsDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $allFiles[] = array($file->getPathname(), $file->getSize());
        }
    }
}
usort($allFiles, function($a, $b) { return $b[1] - $a[1]; });
for ($i = 0; $i < min(10, count($allFiles)); $i++) {
    $rel = str_replace(__DIR__ . '/', '', $allFiles[$i][0]);
    echo str_pad(fmt($allFiles[$i][1]), 12) . $rel . "\n";
}

echo "\n=== 總結 ===\n";
echo "主機方案: 25 GB / 100,000 檔案\n";
echo "uploads 使用: " . fmt($totalSize) . " / " . number_format($totalFiles) . " 檔案\n";
$pctSpace = round($totalSize / (25 * 1073741824) * 100, 1);
$pctFiles = round($totalFiles / 100000 * 100, 1);
echo "空間佔比: {$pctSpace}%  檔案佔比: {$pctFiles}%\n";
echo '</pre>';
