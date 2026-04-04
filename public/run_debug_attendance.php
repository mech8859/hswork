<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

echo "Step 1: Bootstrap OK\n";

require_once __DIR__ . '/../modules/inter_branch/InterBranchModel.php';
echo "Step 2: Model loaded\n";

$model = new InterBranchModel();
echo "Step 3: Model instantiated\n";

$date = date('Y-m-d');
$attendanceList = $model->getAttendanceByDate($date);
echo "Step 4: getAttendanceByDate OK - " . count($attendanceList) . " records\n";

$scheduledWorkers = $model->getScheduledDispatchWorkers($date);
echo "Step 5: getScheduledDispatchWorkers OK - " . count($scheduledWorkers) . " records\n";

$allWorkers = $model->getActiveDispatchWorkers();
echo "Step 6: getActiveDispatchWorkers OK - " . count($allWorkers) . " records\n";

$branches = $model->getBranches();
echo "Step 7: getBranches OK - " . count($branches) . " records\n";

echo "\nAll OK!\n";
