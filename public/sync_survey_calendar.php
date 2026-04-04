<?php
/**
 * 同步場勘案件到業務行事曆
 * 找出有 survey_date 但尚未在 business_calendar 建立事件的案件
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage')) { die('No permission'); }

$db = Database::getInstance();

// 找出有場勘日期、但 business_calendar 沒有對應 survey 事件的案件
$stmt = $db->query("
    SELECT c.id, c.case_number, c.title, c.customer_name, c.survey_date, c.visit_method,
           c.sales_id, c.address, c.customer_phone, c.customer_mobile,
           b.name as branch_name
    FROM cases c
    LEFT JOIN branches b ON c.branch_id = b.id
    WHERE c.survey_date IS NOT NULL
      AND c.survey_date != ''
      AND c.survey_date != '0000-00-00'
      AND NOT EXISTS (
          SELECT 1 FROM business_calendar bc
          WHERE bc.case_id = c.id AND bc.activity_type = 'survey'
      )
    ORDER BY c.survey_date DESC
");
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($cases);
$inserted = 0;
$errors = array();

$insertStmt = $db->prepare("
    INSERT INTO business_calendar (event_date, staff_id, case_id, customer_id, customer_name,
        activity_type, phone, region, address, note, status, created_by, created_at)
    VALUES (?, ?, ?, NULL, ?, 'survey', ?, ?, ?, ?, 'planned', 1, NOW())
");

foreach ($cases as $c) {
    try {
        $phone = $c['customer_phone'] ?: $c['customer_mobile'];
        $note = $c['case_number'] . ' ' . $c['title'];
        if ($c['visit_method']) {
            $note .= ' (拜訪方式: ' . $c['visit_method'] . ')';
        }
        $insertStmt->execute(array(
            $c['survey_date'],
            $c['sales_id'] ?: 1,
            $c['id'],
            $c['customer_name'],
            $phone,
            $c['branch_name'],
            $c['address'],
            $note
        ));
        $inserted++;
    } catch (Exception $e) {
        $errors[] = $c['case_number'] . ': ' . $e->getMessage();
    }
}

echo "<h3>場勘同步到業務行事曆</h3>";
echo "<p>找到 {$total} 筆未同步的場勘案件，成功同步 {$inserted} 筆。</p>";
if ($errors) {
    echo "<p style='color:red'>錯誤：<br>" . implode("<br>", $errors) . "</p>";
}
if ($inserted > 0) {
    echo "<p><a href='/business_calendar.php'>前往業務行事曆查看</a></p>";
}
