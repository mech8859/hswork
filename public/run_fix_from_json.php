<?php
/**
 * 從 cases_import.json 重新修正 case_type 和 status
 * 因為原本 ENUM 限制導致匯入值被丟棄
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

echo "<h2>從 JSON 重新修正案件 case_type 和 status</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .warn{color:orange;} .err{color:red;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;}</style>";

// 讀取 JSON
$json = file_get_contents(__DIR__ . '/cases_import.json');
$cases = json_decode($json, true);
echo "<p>JSON 筆數: " . count($cases) . "</p>";

// case_type 對應
$caseTypeMap = array(
    'new' => 'new_install',
    'new_install' => 'new_install',
    'addition' => 'addition',
    'old_repair' => 'old_repair',
    'new_repair' => 'new_repair',
    'maintenance' => 'maintenance',
    'other' => 'new_install',  // 預設，後面會用 title 覆蓋
);

// 從 title 解析案別
function parseCaseTypeFromTitle($title) {
    if (preg_match('/[-－](老客戶追加)$/', $title)) return 'addition';
    if (preg_match('/[-－](舊客戶維修案)$/', $title)) return 'old_repair';
    if (preg_match('/[-－](新客戶維修案)$/', $title)) return 'new_repair';
    if (preg_match('/[-－](維護保養)$/', $title)) return 'maintenance';
    if (preg_match('/[-－](新案)$/', $title)) return 'new_install';
    return null;
}

// status 對應（JSON 已經是正確的英文值）
$validStatuses = array('tracking','incomplete','unpaid','completed_pending','closed','lost','maint_case','breach','scheduled','needs_reschedule','awaiting_dispatch','customer_cancel');

$updateStmt = $db->prepare("UPDATE cases SET case_type = ?, status = ? WHERE case_number = ?");

$updated = 0;
$notFound = 0;
$errors = array();

foreach ($cases as $r) {
    $caseNumber = $r['case_number'];

    // 解析 case_type
    $titleType = parseCaseTypeFromTitle($r['title']);
    if ($titleType) {
        $caseType = $titleType;
    } else {
        $rawType = isset($r['case_type']) ? $r['case_type'] : '';
        $caseType = isset($caseTypeMap[$rawType]) ? $caseTypeMap[$rawType] : 'new_install';
    }

    // 解析 status
    $rawStatus = isset($r['status']) ? $r['status'] : '';
    if (in_array($rawStatus, $validStatuses)) {
        $status = $rawStatus;
    } else {
        $status = 'tracking';
    }

    try {
        $updateStmt->execute(array($caseType, $status, $caseNumber));
        if ($updateStmt->rowCount() > 0) {
            $updated++;
        } else {
            $notFound++;
        }
    } catch (Exception $e) {
        $errors[] = "{$caseNumber}: " . $e->getMessage();
    }
}

echo "<p class='ok'>更新: {$updated} 筆</p>";
if ($notFound > 0) {
    echo "<p class='warn'>未找到（case_number 不符）: {$notFound} 筆</p>";
}
if (!empty($errors)) {
    echo "<p class='err'>錯誤: " . count($errors) . " 筆</p>";
    foreach (array_slice($errors, 0, 5) as $e) {
        echo "<p class='err'>{$e}</p>";
    }
}

// 修復後統計
echo "<h3>修復後 case_type 分布</h3>";
$stmt = $db->query("SELECT case_type, COUNT(*) as cnt FROM cases GROUP BY case_type ORDER BY cnt DESC");
$typeLabels = array('new_install'=>'新案','addition'=>'老客戶追加','old_repair'=>'舊客戶維修案','new_repair'=>'新客戶維修案','maintenance'=>'維護保養');
echo "<table><tr><th>case_type</th><th>中文</th><th>筆數</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $label = isset($typeLabels[$row['case_type']]) ? $typeLabels[$row['case_type']] : '-';
    echo "<tr><td>{$row['case_type']}</td><td>{$label}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

echo "<h3>修復後 status 分布</h3>";
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
$statusLabels = array('tracking'=>'待追蹤','incomplete'=>'未完工','unpaid'=>'完工未收款','completed_pending'=>'已完工待簽核','closed'=>'已完工結案','lost'=>'未成交','maint_case'=>'保養案件','breach'=>'毀約','scheduled'=>'已排工','needs_reschedule'=>'已進場/需再安排','awaiting_dispatch'=>'待安排派工查修','customer_cancel'=>'客戶取消');
echo "<table><tr><th>status</th><th>中文</th><th>筆數</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $label = isset($statusLabels[$row['status']]) ? $statusLabels[$row['status']] : '-';
    echo "<tr><td>{$row['status']}</td><td>{$label}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

echo "<p><a href='/cases.php'>返回案件管理</a></p>";
