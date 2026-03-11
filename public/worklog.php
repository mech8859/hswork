<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/schedule/WorklogModel.php';

$model = new WorklogModel();
$action = $_GET['action'] ?? 'today';
$userId = Auth::id();

switch ($action) {
    // ---- 今日排工 (手機首頁) ----
    case 'today':
        $todaySchedules = $model->getTodaySchedules($userId);
        $pageTitle = '今日施工';
        $currentPage = 'worklog';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/worklog_today.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 打卡到場 ----
    case 'checkin':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $scheduleId = (int)$_POST['schedule_id'];
            $worklogId = $model->checkIn($scheduleId, $userId);
            Session::flash('success', '已打卡到場');
            redirect('/worklog.php?action=report&id=' . $worklogId);
        }
        redirect('/worklog.php');
        break;

    // ---- 打卡離場 ----
    case 'checkout':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $worklogId = (int)$_POST['worklog_id'];
            $model->checkOut($worklogId);
            Session::flash('success', '已打卡離場');
            redirect('/worklog.php?action=report&id=' . $worklogId);
        }
        redirect('/worklog.php');
        break;

    // ---- 施工回報 ----
    case 'report':
        $id = (int)($_GET['id'] ?? 0);
        $worklog = $model->getWorklog($id);
        if (!$worklog || $worklog['user_id'] != $userId) {
            // 管理員也可查看
            if (!Auth::hasPermission('schedule.manage')) {
                Session::flash('error', '無權限');
                redirect('/worklog.php');
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $model->saveReport($id, $_POST);
            if (!empty($_POST['materials'])) {
                $model->saveMaterials($id, $_POST['materials']);
            }
            Session::flash('success', '回報已儲存');
            redirect('/worklog.php?action=report&id=' . $id);
        }

        $pageTitle = '施工回報';
        $currentPage = 'worklog';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/worklog_report.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 歷史記錄 ----
    case 'history':
        $history = $model->getHistory($userId);
        $pageTitle = '施工記錄';
        $currentPage = 'worklog';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/schedule/worklog_history.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    default:
        redirect('/worklog.php');
}
