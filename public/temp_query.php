<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../modules/schedule/ScheduleModel.php';

$db = Database::getInstance();
$model = new ScheduleModel();

// 用實際的 getByDateRange
$branchIds = array(1,2,3,4,5);
$schedules = $model->getByDateRange($branchIds, '2026-04-11', '2026-04-11');

echo "4/11 排工: " . count($schedules) . " 筆\n";
echo "now: " . date('H:i') . " | today: " . date('Y-m-d') . "\n\n";

foreach ($schedules as $s) {
    $engNames = implode(',', array_column($s['engineers'], 'real_name'));
    echo "#{$s['id']} {$s['case_number']} {$s['case_title']} | db:{$s['status']} → display:{$s['display_status']} | eng:{$engNames}\n";
}
