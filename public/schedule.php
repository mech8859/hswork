<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/schedule/ScheduleModel.php';

$model = new ScheduleModel();
$action = $_GET['action'] ?? 'calendar';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 行事曆 ----
    case 'calendar':
        $year  = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $schedules = $model->getByDateRange($branchIds, $startDate, $endDate);
        $visitWarnings = $model->getVisitWarnings($branchIds);

        // 按日期分組
        $schedulesByDate = [];
        foreach ($schedules as $s) {
            $schedulesByDate[$s['schedule_date']][] = $s;
        }

        $pageTitle = '排工行事曆';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/calendar.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增排工 ----
    case 'create':
        Auth::requirePermission('schedule.manage');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/schedule.php'); }
            $scheduleId = $model->create($_POST);
            Session::flash('success', '排工已建立');
            redirect('/schedule.php?action=view&id=' . $scheduleId);
        }

        $date = $_GET['date'] ?? date('Y-m-d');
        $cases = $model->getSchedulableCases($branchIds);
        $vehicles = $model->getAvailableVehicles($date, $branchIds);

        // 如果有指定案件，取得推薦工程師
        $caseId = (int)($_GET['case_id'] ?? 0);
        $engineers = [];
        if ($caseId) {
            $engineers = $model->getAvailableEngineers($date, $caseId, $branchIds);
        }

        $pageTitle = '新增排工';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 排工詳情 ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $schedule = $model->getById($id);
        if (!$schedule) { Session::flash('error', '排工不存在'); redirect('/schedule.php'); }

        $pageTitle = '排工詳情';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯排工 ----
    case 'edit':
        Auth::requirePermission('schedule.manage');
        $id = (int)($_GET['id'] ?? 0);
        $schedule = $model->getById($id);
        if (!$schedule) { Session::flash('error', '排工不存在'); redirect('/schedule.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/schedule.php?action=edit&id='.$id); }
            $model->update($id, $_POST);
            Session::flash('success', '排工已更新');
            redirect('/schedule.php?action=view&id=' . $id);
        }

        $date = $schedule['schedule_date'];
        $cases = $model->getSchedulableCases($branchIds);
        $vehicles = $model->getAvailableVehicles($date, $branchIds);
        $engineers = $model->getAvailableEngineers($date, (int)$schedule['case_id'], $branchIds);

        $pageTitle = '編輯排工';
        $currentPage = 'schedule';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- AJAX: 取得可用工程師 ----
    case 'ajax_engineers':
        $date = $_GET['date'] ?? date('Y-m-d');
        $caseId = (int)($_GET['case_id'] ?? 0);
        if (!$caseId) json_response(['error' => 'Missing case_id'], 400);
        $engineers = $model->getAvailableEngineers($date, $caseId, $branchIds);
        json_response(['data' => $engineers]);
        break;

    // ---- AJAX: 取得可用車輛 ----
    case 'ajax_vehicles':
        $date = $_GET['date'] ?? date('Y-m-d');
        $vehicles = $model->getAvailableVehicles($date, $branchIds);
        json_response(['data' => $vehicles]);
        break;

    // ---- 刪除排工 ----
    case 'delete':
        Auth::requirePermission('schedule.manage');
        if (verify_csrf()) {
            $model->delete((int)$_GET['id']);
            Session::flash('success', '排工已刪除');
        }
        redirect('/schedule.php');
        break;

    default:
        redirect('/schedule.php');
}
