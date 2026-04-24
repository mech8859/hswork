<?php
/**
 * 診斷：指定案件編號的附件（檢查檔案是否實際存在）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}
$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$caseNumber = isset($_GET['case_number']) ? trim($_GET['case_number']) : '2026-2408';

echo "<h3>案件附件檢查：{$caseNumber}</h3>";

$cStmt = $db->prepare("SELECT id, case_number, title FROM cases WHERE case_number = ?");
$cStmt->execute(array($caseNumber));
$c = $cStmt->fetch(PDO::FETCH_ASSOC);
if (!$c) {
    die("<p style='color:#c62828'>找不到案件 {$caseNumber}</p>");
}

echo "<p>case_id = <b>{$c['id']}</b>, title = " . htmlspecialchars($c['title']) . "</p>";

$aStmt = $db->prepare("SELECT id, file_type, file_name, file_path, file_size, uploaded_by, created_at FROM case_attachments WHERE case_id = ? ORDER BY id DESC");
$aStmt->execute(array($c['id']));
$atts = $aStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>共 <b>" . count($atts) . "</b> 個附件</p>";

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>id</th><th>type</th><th>file_name</th><th>file_path (DB)</th><th>實體檔案路徑</th><th>存在</th><th>大小</th><th>URL 試開</th></tr></thead><tbody>";

foreach ($atts as $a) {
    $rel = $a['file_path'];
    // 轉實體路徑
    $relNoSlash = ltrim($rel, '/');
    $full = __DIR__ . '/' . $relNoSlash;
    $exists = file_exists($full);
    $realSize = $exists ? filesize($full) : 0;
    $url = $rel;
    if ($url && $url[0] !== '/') $url = '/' . $url;

    $color = $exists ? '#2e7d32' : '#c62828';
    echo "<tr style='color:{$color}'>"
       . "<td>{$a['id']}</td>"
       . "<td>" . htmlspecialchars($a['file_type']) . "</td>"
       . "<td>" . htmlspecialchars($a['file_name']) . "</td>"
       . "<td style='font-size:.75rem'>" . htmlspecialchars($rel) . "</td>"
       . "<td style='font-size:.75rem;max-width:250px;word-break:break-all'>" . htmlspecialchars($full) . "</td>"
       . "<td>" . ($exists ? "✓ ({$realSize} bytes)" : '✗ 檔案不在') . "</td>"
       . "<td>" . htmlspecialchars((string)$a['file_size']) . "</td>"
       . "<td><a href='" . htmlspecialchars($url) . "' target='_blank'>開</a></td>"
       . "</tr>";
}
echo "</tbody></table>";

// 檢查目錄內實際檔案
$dir = __DIR__ . '/uploads/cases/' . (int)$c['id'];
echo "<h4>目錄內檔案列表：{$dir}</h4>";
if (is_dir($dir)) {
    $files = scandir($dir);
    echo "<ul>";
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $dir . '/' . $f;
        if (is_file($full)) {
            echo "<li>" . htmlspecialchars($f) . " (" . filesize($full) . " bytes)</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color:#c62828'>目錄不存在</p>";
}
