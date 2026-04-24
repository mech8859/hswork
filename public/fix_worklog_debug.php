<?php
/**
 * 診斷：為何排工詳情不顯示「同案件其他日期施工回報」
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}
$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$caseNumber = isset($_GET['case_number']) ? trim($_GET['case_number']) : '2026-0053';

echo "<h3>案件施工回報診斷：{$caseNumber}</h3>";

$cStmt = $db->prepare("SELECT id, case_number, title, total_visits FROM cases WHERE case_number = ?");
$cStmt->execute(array($caseNumber));
$c = $cStmt->fetch(PDO::FETCH_ASSOC);
if (!$c) die("<p style='color:#c62828'>找不到案件</p>");
echo "<p>case_id={$c['id']}, total_visits={$c['total_visits']}, title=" . htmlspecialchars($c['title']) . "</p>";

// 所有 schedules
$sStmt = $db->prepare("SELECT id, schedule_date, visit_number, status FROM schedules WHERE case_id = ? ORDER BY schedule_date, id");
$sStmt->execute(array($c['id']));
$schedules = $sStmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h4>排工 (" . count($schedules) . ")</h4>";
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'><thead><tr><th>schedule_id</th><th>日期</th><th>第幾次</th><th>狀態</th></tr></thead><tbody>";
foreach ($schedules as $s) {
    echo "<tr><td>{$s['id']}</td><td>{$s['schedule_date']}</td><td>{$s['visit_number']}</td><td>{$s['status']}</td></tr>";
}
echo "</tbody></table>";

// 所有 work_logs（含空的）
$wStmt = $db->prepare("
    SELECT wl.id, wl.schedule_id, s.schedule_date, wl.user_id, u.real_name,
           wl.work_description, wl.issues, wl.arrival_time, wl.is_completed
    FROM work_logs wl
    JOIN schedules s ON wl.schedule_id = s.id
    LEFT JOIN users u ON wl.user_id = u.id
    WHERE s.case_id = ?
    ORDER BY s.schedule_date, wl.id
");
$wStmt->execute(array($c['id']));
$logs = $wStmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h4>施工回報 (" . count($logs) . ")</h4>";
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'><thead><tr><th>work_log_id</th><th>schedule_id</th><th>日期</th><th>工程師</th><th>work_description</th><th>有內容?</th><th>完工?</th></tr></thead><tbody>";
foreach ($logs as $l) {
    $hasDesc = !empty(trim($l['work_description'] ?? ''));
    $color = $hasDesc ? '#2e7d32' : '#c62828';
    echo "<tr style='color:{$color}'>"
       . "<td>{$l['id']}</td>"
       . "<td>{$l['schedule_id']}</td>"
       . "<td>{$l['schedule_date']}</td>"
       . "<td>" . htmlspecialchars((string)$l['real_name']) . "</td>"
       . "<td style='max-width:400px'>" . htmlspecialchars(mb_substr((string)$l['work_description'], 0, 80)) . "</td>"
       . "<td>" . ($hasDesc ? '✓' : '✗ 空') . "</td>"
       . "<td>" . (!empty($l['is_completed']) ? '✓' : '-') . "</td>"
       . "</tr>";
}
echo "</tbody></table>";

echo "<hr><p>👉 如果「有內容」都是✗，代表該案件的施工回報從未被填寫（只建過空白記錄），所以排工詳情不會顯示歷史。</p>";
echo "<p>👉 getCaseTimeline 只會回傳 work_description 不為空的紀錄。</p>";
