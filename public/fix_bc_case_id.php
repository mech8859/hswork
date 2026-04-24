<?php
/**
 * 一次性修復：業務行事曆 case_id 被清空問題
 *
 * 根因：
 *   編輯表單 form.php 過去沒有 case_id / customer_id hidden field，
 *   每次儲存 UPDATE 會把 case_id 覆寫成 null。
 *
 * 修復策略：
 *   業務行事曆 note 欄位格式：「{case_number} {title} (拜訪方式: xxx)」
 *   由 CaseModel::syncSurveyToCalendar() 寫入。
 *   以 regex 從 note 抓出案件編號，反查 cases.id 回填 case_id。
 *   同時回填 customer_id（從 cases.customer_id）。
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$dryRun = !isset($_GET['go']) || $_GET['go'] !== '1';

echo "<h3>修復業務行事曆 case_id 被清空</h3>";
echo "<p>模式：" . ($dryRun ? '<b style="color:#c62828">Dry-run (預覽)</b> — <a href="?go=1" onclick="return confirm(\'確定執行回填？\')">點此執行實際回填</a>' : '<b style="color:#2e7d32">實際執行</b>') . "</p>";

// 找出 case_id 為空、但 note 開頭有案件編號格式 YYYY-NNNN 的記錄
$stmt = $db->query("
    SELECT bc.id, bc.note, bc.customer_name, bc.event_date, bc.activity_type
    FROM business_calendar bc
    WHERE bc.case_id IS NULL
      AND bc.note REGEXP '^[0-9]{4}-[0-9]+'
    ORDER BY bc.event_date DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
$matched = 0;
$noMatch = 0;
$examples = array();

echo "<p>候選紀錄（note 開頭為案件編號）：<b>{$total}</b> 筆</p>";

if ($total === 0) {
    echo "<p style='color:#888'>沒有需要回填的紀錄。</p>";
    exit;
}

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.88rem'>";
echo "<thead><tr><th>BC ID</th><th>日期</th><th>note</th><th>抓到案件編號</th><th>反查 case_id</th><th>結果</th></tr></thead><tbody>";

$updStmt = $db->prepare("UPDATE business_calendar SET case_id = ?, customer_id = ? WHERE id = ?");
$caseLookup = $db->prepare("SELECT id, customer_id FROM cases WHERE case_number = ? LIMIT 1");

foreach ($rows as $r) {
    // 抓 note 最前面的案件編號（YYYY-NNNN 或 YYYY-NNNNN...）
    if (preg_match('/^(\d{4}-\d+)/', $r['note'], $m)) {
        $caseNumber = $m[1];
        $caseLookup->execute(array($caseNumber));
        $c = $caseLookup->fetch(PDO::FETCH_ASSOC);

        if ($c) {
            $caseId = (int)$c['id'];
            $custId = !empty($c['customer_id']) ? (int)$c['customer_id'] : null;
            if (!$dryRun) {
                $updStmt->execute(array($caseId, $custId, $r['id']));
            }
            $matched++;
            echo "<tr>"
               . "<td>{$r['id']}</td>"
               . "<td>" . htmlspecialchars($r['event_date']) . "</td>"
               . "<td style='max-width:400px;overflow:hidden;text-overflow:ellipsis'>" . htmlspecialchars(mb_substr($r['note'], 0, 80)) . "</td>"
               . "<td>" . htmlspecialchars($caseNumber) . "</td>"
               . "<td>{$caseId}</td>"
               . "<td style='color:#2e7d32'>✓ " . ($dryRun ? '將回填' : '已回填') . " (cust={$custId})</td>"
               . "</tr>";
        } else {
            $noMatch++;
            echo "<tr style='background:#fff3e0'>"
               . "<td>{$r['id']}</td>"
               . "<td>" . htmlspecialchars($r['event_date']) . "</td>"
               . "<td style='max-width:400px;overflow:hidden;text-overflow:ellipsis'>" . htmlspecialchars(mb_substr($r['note'], 0, 80)) . "</td>"
               . "<td>" . htmlspecialchars($caseNumber) . "</td>"
               . "<td>-</td>"
               . "<td style='color:#e65100'>✗ 案件不存在</td>"
               . "</tr>";
        }
    } else {
        $noMatch++;
    }
}

echo "</tbody></table>";

echo "<hr>";
echo "<h4>結果</h4>";
echo "<ul>";
echo "<li>總候選：{$total}</li>";
echo "<li>成功回填：<b style='color:#2e7d32'>{$matched}</b></li>";
echo "<li>無法對應（案件不存在）：<b style='color:#e65100'>{$noMatch}</b></li>";
echo "</ul>";

if ($dryRun) {
    echo "<p><a href='?go=1' onclick='return confirm(\"確定執行回填？\")' style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>執行回填 {$matched} 筆</a></p>";
} else {
    echo "<p><a href='/business_calendar.php'>回業務行事曆</a></p>";
}
