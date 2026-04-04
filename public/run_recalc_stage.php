<?php
/**
 * 批次重算所有案件的 stage（自動判斷）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

$model = new CaseModel();
$db = Database::getInstance();

echo "<h2>批次重算案件 Stage</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;}</style>";

// 修復前
$stmt = $db->query("SELECT stage, COUNT(*) as cnt FROM cases GROUP BY stage ORDER BY stage");
echo "<h3>修復前 stage 分布</h3><table><tr><th>stage</th><th>筆數</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['stage']}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// 取所有案件 ID
$stmt = $db->query("SELECT id FROM cases ORDER BY id");
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$changes = array();
$total = count($ids);
$changed = 0;

foreach ($ids as $id) {
    $oldStmt = $db->prepare("SELECT stage FROM cases WHERE id = ?");
    $oldStmt->execute(array($id));
    $oldStage = (int)$oldStmt->fetchColumn();

    $newStage = $model->syncStage($id);

    if ($newStage != $oldStage) {
        $changed++;
        $key = "{$oldStage} → {$newStage}";
        if (!isset($changes[$key])) $changes[$key] = 0;
        $changes[$key]++;
    }
}

echo "<p class='ok'>總計 {$total} 筆案件，<strong>{$changed}</strong> 筆 stage 有變動</p>";

if (!empty($changes)) {
    echo "<h3>變動明細</h3><table><tr><th>變動</th><th>筆數</th></tr>";
    arsort($changes);
    $stageLabels = CaseModel::stageLabels();
    foreach ($changes as $k => $v) {
        echo "<tr><td>{$k}</td><td>{$v}</td></tr>";
    }
    echo "</table>";
}

// 修復後
$stmt = $db->query("SELECT stage, COUNT(*) as cnt FROM cases GROUP BY stage ORDER BY stage");
$labels = CaseModel::stageLabels();
echo "<h3>修復後 stage 分布</h3><table><tr><th>stage</th><th>中文</th><th>筆數</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $s = (int)$row['stage'];
    $label = isset($labels[$s]) ? $labels[$s] : '-';
    echo "<tr><td>{$s}</td><td>{$label}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

echo "<p><a href='/cases.php'>案件管理</a> | <a href='/engineering_tracking.php'>工程追蹤</a></p>";
