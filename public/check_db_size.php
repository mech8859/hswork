<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!Session::isLoggedIn() || Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}
$db = Database::getInstance();

echo '<h2>資料庫空間使用狀況</h2>';

// 各表大小
$stmt = $db->query("
    SELECT table_name,
           table_rows,
           ROUND(data_length/1024, 1) AS data_kb,
           ROUND(index_length/1024, 1) AS index_kb,
           ROUND((data_length + index_length)/1024, 1) AS total_kb
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
    ORDER BY (data_length + index_length) DESC
");
$tables = $stmt->fetchAll();

$totalKB = 0;
echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">';
echo '<tr style="background:#eee"><th>資料表</th><th>筆數</th><th>資料(KB)</th><th>索引(KB)</th><th>合計(KB)</th></tr>';
foreach ($tables as $t) {
    $totalKB += $t['total_kb'];
    echo '<tr>';
    echo '<td>' . htmlspecialchars($t['table_name']) . '</td>';
    echo '<td align="right">' . number_format($t['table_rows']) . '</td>';
    echo '<td align="right">' . number_format($t['data_kb'], 1) . '</td>';
    echo '<td align="right">' . number_format($t['index_kb'], 1) . '</td>';
    echo '<td align="right">' . number_format($t['total_kb'], 1) . '</td>';
    echo '</tr>';
}
echo '<tr style="background:#ffd;font-weight:bold">';
echo '<td>合計</td><td></td><td></td><td></td>';
echo '<td align="right">' . number_format($totalKB, 1) . ' KB (' . number_format($totalKB/1024, 2) . ' MB)</td>';
echo '</tr>';
echo '</table>';

// 智邦方案空間
echo '<h3>空間摘要</h3>';
echo '<p>FTP 磁碟使用：約 38 MB</p>';
echo '<p>資料庫使用：' . number_format($totalKB/1024, 2) . ' MB</p>';
echo '<p style="color:blue">智邦虛擬主機基本方案：磁碟 5GB + MySQL 500MB</p>';
