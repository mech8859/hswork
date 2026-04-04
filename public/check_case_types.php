<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h2>案件 case_type 分布</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;}</style>";

$stmt = $db->query("SELECT case_type, COUNT(*) as cnt FROM cases GROUP BY case_type ORDER BY cnt DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table><tr><th>case_type 值</th><th>筆數</th><th>備註</th></tr>";
$validTypes = array('new_install','addition','old_repair','new_repair','maintenance');
foreach ($rows as $row) {
    $ct = $row['case_type'] ?: '(空)';
    $note = in_array($row['case_type'], $validTypes) ? '正確' : '<span style="color:red">需修正</span>';
    echo "<tr><td>{$ct}</td><td>{$row['cnt']}</td><td>{$note}</td></tr>";
}
echo "</table>";

// 顯示需修正的案件樣本
echo "<h3>需修正的案件樣本（每類最多5筆）</h3>";
foreach ($rows as $row) {
    if (!in_array($row['case_type'], $validTypes)) {
        $ct = $row['case_type'] ?: '(空)';
        echo "<h4>case_type = '{$ct}' ({$row['cnt']}筆)</h4>";
        $stmt2 = $db->prepare("SELECT id, case_number, title, case_type FROM cases WHERE case_type = ? LIMIT 5");
        $stmt2->execute(array($row['case_type']));
        $samples = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        echo "<table><tr><th>ID</th><th>編號</th><th>標題</th><th>case_type</th></tr>";
        foreach ($samples as $s) {
            echo "<tr><td>{$s['id']}</td><td>{$s['case_number']}</td><td>{$s['title']}</td><td>{$s['case_type']}</td></tr>";
        }
        echo "</table>";
    }
}

echo "<p><a href='/cases.php'>返回案件管理</a></p>";
