<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

// Count cases with survey_date
$total = $db->query("SELECT COUNT(*) FROM cases WHERE survey_date IS NOT NULL AND survey_date != '' AND survey_date != '0000-00-00'")->fetchColumn();
$stage2 = $db->query("SELECT COUNT(*) FROM cases WHERE stage = 2")->fetchColumn();
$synced = $db->query("SELECT COUNT(*) FROM business_calendar WHERE activity_type = 'survey'")->fetchColumn();

echo "場勘階段案件(stage=2): {$stage2}<br>";
echo "有場勘日期的案件: {$total}<br>";
echo "已同步到行事曆: {$synced}<br>";

// Show sample of stage 2 cases
$rows = $db->query("SELECT id, case_number, survey_date, sub_status FROM cases WHERE stage = 2 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<br>場勘階段案件範例:<br>";
foreach ($rows as $r) {
    echo "#{$r['id']} {$r['case_number']} survey_date={$r['survey_date']} status={$r['sub_status']}<br>";
}
