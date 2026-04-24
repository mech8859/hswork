<?php
/**
 * 一次性修復：業務行事曆「同案件同日重複」
 *
 * 成因：
 *   舊 bug 造成業務行事曆編輯後 case_id 被清空，
 *   之後案件再更新場勘日 → syncSurveyToCalendar() 找不到原事件 → 再建一筆。
 *   導致同 case_id + 同 event_date + activity_type='survey' 出現 2 筆。
 *
 * 策略：
 *   分組（case_id + event_date + activity_type='survey'），保留 1 筆，刪其他。
 *   保留優先順序（由高到低）：
 *     1. 有填 result（執行結果）
 *     2. status 非 planned（已完成/已取消）
 *     3. id 最大（最新建立，資料最即時）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$dryRun = !isset($_GET['go']) || $_GET['go'] !== '1';

echo "<h3>業務行事曆重複行程去重</h3>";
echo "<p>模式：" . ($dryRun ? '<b style="color:#c62828">Dry-run (預覽)</b>' : '<b style="color:#2e7d32">實際執行</b>') . "</p>";

// 找出同 case_id + event_date + activity_type='survey' 有重複的組
$stmt = $db->query("
    SELECT case_id, event_date, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids
    FROM business_calendar
    WHERE case_id IS NOT NULL
      AND activity_type = 'survey'
    GROUP BY case_id, event_date
    HAVING cnt > 1
    ORDER BY case_id DESC
");
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalGroups = count($groups);

if ($totalGroups === 0) {
    echo "<p style='color:#888'>沒有需要去重的紀錄。</p>";
    exit;
}

echo "<p>找到 <b>{$totalGroups}</b> 組重複（case_id + event_date）</p>";

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>案件 ID</th><th>案件編號</th><th>日期</th><th>筆數</th><th>明細 (id / status / result / note)</th><th>保留</th><th>刪除</th></tr></thead><tbody>";

$detailStmt = $db->prepare("SELECT id, status, result, note, customer_name, start_time, staff_id, created_at FROM business_calendar WHERE id IN (_IDS_)");
$caseInfoStmt = $db->prepare("SELECT case_number FROM cases WHERE id = ?");
$delStmt = $db->prepare("DELETE FROM business_calendar WHERE id = ?");

$totalDeleted = 0;
$totalKept = 0;

foreach ($groups as $g) {
    $ids = explode(',', $g['ids']);
    $ids = array_map('intval', $ids);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rowStmt = $db->prepare("SELECT id, status, result, note, customer_name, start_time, staff_id, created_at
                             FROM business_calendar WHERE id IN ($ph) ORDER BY id");
    $rowStmt->execute($ids);
    $rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);

    // 決定保留哪一筆（優先：有 result > status!=planned > id 最大）
    usort($rows, function($a, $b) {
        $aHasResult = !empty(trim((string)$a['result']));
        $bHasResult = !empty(trim((string)$b['result']));
        if ($aHasResult !== $bHasResult) return $aHasResult ? -1 : 1;
        $aStatus = ($a['status'] !== 'planned' && $a['status'] !== '') ? 1 : 0;
        $bStatus = ($b['status'] !== 'planned' && $b['status'] !== '') ? 1 : 0;
        if ($aStatus !== $bStatus) return $bStatus - $aStatus;
        return (int)$b['id'] - (int)$a['id']; // 最大 id 優先
    });

    $keepRow = $rows[0];
    $delRows = array_slice($rows, 1);

    $caseInfoStmt->execute(array($g['case_id']));
    $ci = $caseInfoStmt->fetch(PDO::FETCH_ASSOC);
    $caseNumber = $ci ? $ci['case_number'] : '(查無)';

    $detailHtml = '';
    foreach ($rows as $r) {
        $isKeep = ($r['id'] == $keepRow['id']);
        $color = $isKeep ? '#2e7d32' : '#c62828';
        $label = $isKeep ? '✓ 保留' : '✗ 刪除';
        $resultText = !empty(trim($r['result'] ?? '')) ? '有填' : '-';
        $detailHtml .= "<div style='color:{$color};margin-bottom:3px'>"
                    . "<b>{$label}</b> id={$r['id']} | status={$r['status']} | result={$resultText} | "
                    . htmlspecialchars(mb_substr((string)$r['note'], 0, 50))
                    . "</div>";
    }

    echo "<tr>"
       . "<td>{$g['case_id']}</td>"
       . "<td>" . htmlspecialchars($caseNumber) . "</td>"
       . "<td>{$g['event_date']}</td>"
       . "<td>{$g['cnt']}</td>"
       . "<td style='max-width:500px'>{$detailHtml}</td>"
       . "<td style='color:#2e7d32'>id={$keepRow['id']}</td>"
       . "<td style='color:#c62828'>";

    foreach ($delRows as $dr) {
        echo "id={$dr['id']}<br>";
        if (!$dryRun) {
            $delStmt->execute(array($dr['id']));
        }
        $totalDeleted++;
    }
    echo "</td></tr>";
    $totalKept++;
}

echo "</tbody></table>";

echo "<hr>";
echo "<h4>結果</h4>";
echo "<ul>";
echo "<li>重複組數：{$totalGroups}</li>";
echo "<li>保留筆數：<b style='color:#2e7d32'>{$totalKept}</b></li>";
echo "<li>" . ($dryRun ? '將刪除' : '已刪除') . "筆數：<b style='color:#c62828'>{$totalDeleted}</b></li>";
echo "</ul>";

if ($dryRun) {
    echo "<p><a href='?go=1' onclick='return confirm(\"確定刪除 {$totalDeleted} 筆重複行程？\")' "
       . "style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>"
       . "執行刪除 {$totalDeleted} 筆</a></p>";
} else {
    echo "<p><a href='/business_calendar.php'>回業務行事曆</a></p>";
}
