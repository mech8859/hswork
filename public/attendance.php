<?php
/**
 * 工程人員出勤狀況表
 * 顯示整月行事曆，連動請假管理，顯示當日休假人員
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (!Auth::hasPermission('schedule.manage') && !Auth::hasPermission('schedule.view')) {
    Session::flash('error', '無權限');
    redirect('/index.php');
}

require_once __DIR__ . '/../modules/staff/StaffModel.php';

$staffModel = new StaffModel();
$branchIds = Auth::getAccessibleBranchIds();
$db = Database::getInstance();

$action = $_GET['action'] ?? 'calendar';

switch ($action) {
    case 'calendar':
    default:
        // 取得月份
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDayOfWeek = (int)date('w', mktime(0, 0, 0, $month, 1, $year)); // 0=Sun

        // 取得所有工程人員
        $engineers = $staffModel->getEngineers($branchIds);

        $ph = implode(',', array_fill(0, count($branchIds), '?'));
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // 取得該月請假資料（含假別）
        $stmtLeave = $db->prepare("
            SELECT l.user_id, l.start_date, l.end_date, l.leave_type, u.real_name
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            WHERE u.branch_id IN ($ph)
              AND l.status IN ('approved','pending')
              AND l.start_date <= ? AND l.end_date >= ?
              AND u.role IN ('engineer','eng_manager','eng_deputy')
        ");
        $leaveParams = $branchIds;
        $leaveParams[] = $endDate;
        $leaveParams[] = $startDate;
        $stmtLeave->execute($leaveParams);
        $leaveData = $stmtLeave->fetchAll();

        // 假別對照
        $leaveTypeLabels = array('annual' => '特休', 'day_off' => '排休', 'personal' => '事假', 'sick' => '病假', 'menstrual' => '生理假', 'bereavement' => '喪假', 'official' => '公假');

        // 組成 date => [user_id => ['name'=>..., 'type'=>...]]
        $dateLeaves = array();
        foreach ($leaveData as $lv) {
            $ls = max(strtotime($startDate), strtotime($lv['start_date']));
            $le = min(strtotime($endDate), strtotime($lv['end_date']));
            for ($t = $ls; $t <= $le; $t += 86400) {
                $d = date('Y-m-d', $t);
                if (!isset($dateLeaves[$d])) $dateLeaves[$d] = array();
                $dateLeaves[$d][$lv['user_id']] = array(
                    'name' => $lv['real_name'],
                    'type' => isset($leaveTypeLabels[$lv['leave_type']]) ? $leaveTypeLabels[$lv['leave_type']] : $lv['leave_type']
                );
            }
        }

        $totalEngineers = count($engineers);

        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

        $pageTitle = sprintf('工程人員出勤狀況表 - %d年%d月', $year, $month);
        $currentPage = 'attendance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/staff/attendance.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // AJAX: 取得某日請假人員
    case 'day_detail':
        $date = $_GET['date'] ?? '';
        if (!$date) { json_response(array('error' => '缺少日期')); }

        $ph = implode(',', array_fill(0, count($branchIds), '?'));

        // 請假人員（含假別）
        $stmt = $db->prepare("
            SELECT l.user_id, u.real_name, l.leave_type
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            WHERE u.branch_id IN ($ph)
              AND l.status IN ('approved','pending')
              AND l.start_date <= ? AND l.end_date >= ?
              AND u.role IN ('engineer','eng_manager','eng_deputy')
            ORDER BY u.real_name
        ");
        $p = $branchIds;
        $p[] = $date;
        $p[] = $date;
        $stmt->execute($p);
        $onLeave = $stmt->fetchAll();

        json_response(array(
            'date' => $date,
            'on_leave' => $onLeave
        ));
        break;
}
