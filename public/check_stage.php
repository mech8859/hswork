<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
echo "<h2>案件 stage 分布</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;}</style>";
$stmt = $db->query("SELECT stage, COUNT(*) as cnt FROM cases GROUP BY stage ORDER BY stage");
echo "<table><tr><th>stage</th><th>筆數</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>" . ($row['stage'] ?: '(空)') . "</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// 看 status vs stage 交叉
echo "<h3>status × stage 交叉（前20）</h3>";
$stmt = $db->query("SELECT status, stage, COUNT(*) as cnt FROM cases GROUP BY status, stage ORDER BY cnt DESC LIMIT 20");
echo "<table><tr><th>status</th><th>stage</th><th>筆數</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['status']}</td><td>{$row['stage']}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";
echo "<p><a href='/engineering_tracking.php'>返回工程追蹤</a></p>";
