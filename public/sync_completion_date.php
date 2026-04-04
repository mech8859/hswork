<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);

$jsonFile = __DIR__ . '/../database/ragic_cases_20260405.json';
if (!file_exists($jsonFile)) die('JSON not found');
$ragicData = json_decode(file_get_contents($jsonFile), true);

echo "=== 完工日期同步（" . ($dryRun ? '預覽模式' : '執行模式') . "）===\n\n";

$updated = 0;
$skipped = 0;
$notFound = 0;

foreach ($ragicData as $ragicId => $r) {
    $caseNumber = trim(isset($r['進件編號']) ? $r['進件編號'] : '');
    if (!$caseNumber) continue;

    $rawDate = isset($r['完工日期']) ? trim($r['完工日期']) : '';
    if (!$rawDate) { $skipped++; continue; }

    // 日期格式轉換
    $completionDate = str_replace('/', '-', $rawDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $completionDate)) { $skipped++; continue; }
    $completionDate = substr($completionDate, 0, 10);

    // 找案件
    $stmt = $db->prepare("SELECT id, completion_date FROM cases WHERE case_number = ?");
    $stmt->execute(array($caseNumber));
    $case = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$case) { $notFound++; continue; }

    // 已一樣就跳過
    if ($case['completion_date'] === $completionDate) { $skipped++; continue; }

    if (!$dryRun) {
        $db->prepare("UPDATE cases SET completion_date = ? WHERE id = ?")->execute(array($completionDate, $case['id']));
    }

    $old = $case['completion_date'] ?: '(空)';
    echo "{$caseNumber}: {$old} => {$completionDate}\n";
    $updated++;
}

echo "\n--- 結果 ---\n";
echo "更新: {$updated} 筆\n";
echo "跳過(無日期/已相同): {$skipped} 筆\n";
echo "找不到案件: {$notFound} 筆\n";

if ($dryRun && $updated > 0) {
    echo "\n確認無誤後，加 ?execute=1 執行\n";
}
