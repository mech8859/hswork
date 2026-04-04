<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h2>案件 status 分布</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;} .bad{color:red;}</style>";

// status 分布
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$validStatuses = array('tracking','incomplete','unpaid','completed_pending','closed','lost','maint_case','breach','scheduled','needs_reschedule','awaiting_dispatch','customer_cancel');

echo "<table><tr><th>status 值</th><th>筆數</th><th>備註</th></tr>";
foreach ($rows as $row) {
    $s = $row['status'] ?: '(空)';
    $note = in_array($row['status'], $validStatuses) ? '正確' : '<span class="bad">需修正</span>';
    echo "<tr><td>{$s}</td><td>{$row['cnt']}</td><td>{$note}</td></tr>";
}
echo "</table>";

// sub_status 分布
echo "<h2>案件 sub_status 分布</h2>";
$stmt = $db->query("SELECT sub_status, COUNT(*) as cnt FROM cases GROUP BY sub_status ORDER BY cnt DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>sub_status 值</th><th>筆數</th></tr>";
foreach ($rows as $row) {
    echo "<tr><td>" . ($row['sub_status'] ?: '(空)') . "</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// stage 分布
echo "<h2>案件 stage 分布</h2>";
$stmt = $db->query("SELECT stage, COUNT(*) as cnt FROM cases GROUP BY stage ORDER BY cnt DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>stage</th><th>筆數</th></tr>";
foreach ($rows as $row) {
    echo "<tr><td>" . ($row['stage'] ?: '(空)') . "</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// 需修正的 status 樣本
echo "<h3>需修正的 status 樣本</h3>";
foreach ($rows as $row) {
    // skip valid
}
$stmt = $db->query("SELECT status, case_number, title, sub_status, stage FROM cases WHERE status NOT IN ('tracking','incomplete','unpaid','completed_pending','closed','lost','maint_case','breach','scheduled','needs_reschedule','awaiting_dispatch','customer_cancel') LIMIT 20");
$samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($samples)) {
    echo "<p>無需修正的 status</p>";
} else {
    echo "<table><tr><th>編號</th><th>標題</th><th>status</th><th>sub_status</th><th>stage</th></tr>";
    foreach ($samples as $s) {
        echo "<tr><td>{$s['case_number']}</td><td>{$s['title']}</td><td>{$s['status']}</td><td>{$s['sub_status']}</td><td>{$s['stage']}</td></tr>";
    }
    echo "</table>";
}

echo "<p><a href='/cases.php'>返回案件管理</a></p>";
