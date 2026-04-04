<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/inter_branch/InterBranchModel.php';

$model = new InterBranchModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 清單 ----
    case 'list':
        Auth::requirePermission('inter_branch.view');
        $filters = array(
            'month'     => isset($_GET['month']) ? $_GET['month'] : date('Y-m'),
            'branch_id' => isset($_GET['branch_id']) ? $_GET['branch_id'] : '',
            'settled'   => isset($_GET['settled']) ? $_GET['settled'] : '',
        );
        $records = $model->getList($branchIds, $filters);
        $branches = $model->getAllBranches();

        $pageTitle = '點工費管理';
        $currentPage = 'inter_branch';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inter_branch/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增 ----
    case 'create':
        Auth::requirePermission('inter_branch.manage');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/inter_branch.php'); }
            $model->create($_POST);
            Session::flash('success', '點工記錄已新增');
            redirect('/inter_branch.php');
        }
        $branches = $model->getAllBranches();
        $engineers = $model->getEngineers();
        $record = null;

        $pageTitle = '新增點工記錄';
        $currentPage = 'inter_branch';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inter_branch/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯 ----
    case 'edit':
        Auth::requirePermission('inter_branch.manage');
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) { Session::flash('error', '記錄不存在'); redirect('/inter_branch.php'); }
        if ($record['settled']) { Session::flash('error', '已結算記錄不可編輯'); redirect('/inter_branch.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/inter_branch.php?action=edit&id='.$id); }
            $model->update($id, $_POST);
            Session::flash('success', '點工記錄已更新');
            redirect('/inter_branch.php');
        }
        $branches = $model->getAllBranches();
        $engineers = $model->getEngineers();

        $pageTitle = '編輯點工記錄';
        $currentPage = 'inter_branch';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inter_branch/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除 ----
    case 'delete':
        if (!Auth::hasPermission('inter_branch.delete') && !Auth::hasPermission('all')) {
            Session::flash('error', '權限不足，無法刪除點工記錄');
            redirect('/inter_branch.php');
        }
        if (verify_csrf()) {
            $id = (int)$_GET['id'];
            if ($model->delete($id)) {
                Session::flash('success', '點工記錄已刪除');
            } else {
                Session::flash('error', '無法刪除（已結算）');
            }
        }
        redirect('/inter_branch.php');
        break;

    // ---- 月結 ----
    case 'settle':
        Auth::requirePermission('inter_branch.manage');
        $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/inter_branch.php?action=settle&month='.$month); }
            $count = $model->settleMonth($month, $branchIds);
            Session::flash('success', "已結算 {$count} 筆記錄");
            redirect('/inter_branch.php?action=settle&month=' . $month);
        }

        $summary = $model->getSettleSummary($month, $branchIds);
        $branches = $model->getAllBranches();

        $pageTitle = '月結確認';
        $currentPage = 'inter_branch';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inter_branch/settle.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'attendance':
        // 出勤登錄頁面
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $attendanceList = $model->getAttendanceByDate($date);
        $scheduledWorkers = $model->getScheduledDispatchWorkers($date);
        $allWorkers = $model->getActiveDispatchWorkers();
        $branches = $model->getBranches();

        $pageTitle = '點工出勤登錄';
        $currentPage = 'inter_branch';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inter_branch/attendance.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'attendance_save':
        // AJAX 儲存出勤
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error' => 'POST only')); exit; }

        $result = $model->saveAttendance(array(
            'dispatch_worker_id' => (int)$_POST['dispatch_worker_id'],
            'schedule_id' => !empty($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : null,
            'attendance_date' => $_POST['attendance_date'],
            'branch_id' => (int)$_POST['branch_id'],
            'charge_type' => $_POST['charge_type'],
            'daily_rate' => (int)$_POST['daily_rate'],
            'status' => $_POST['status'],
            'note' => isset($_POST['note']) ? trim($_POST['note']) : '',
            'recorded_by' => Auth::user()['id'],
        ));
        echo json_encode(array('ok' => true, 'id' => $result));
        exit;

    case 'attendance_delete':
        header('Content-Type: application/json');
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $cnt = $model->deleteAttendance($id);
        echo json_encode(array('ok' => $cnt > 0));
        exit;

    case 'attendance_settle_page':
        // 結算查詢頁面
        $filters = array(
            'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'),
            'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'),
            'worker_id' => isset($_GET['worker_id']) ? $_GET['worker_id'] : '',
            'branch_id' => isset($_GET['branch_id']) ? $_GET['branch_id'] : '',
            'settled' => isset($_GET['settled']) ? $_GET['settled'] : '',
        );
        $settleData = $model->getAttendanceSettle($filters);
        $allWorkers = $model->getActiveDispatchWorkers();
        $branches = $model->getBranches();

        $pageTitle = '點工出勤結算';
        $currentPage = 'inter_branch';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inter_branch/attendance_settle.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'attendance_do_settle':
        // 執行月結
        $month = isset($_POST['settle_month']) ? $_POST['settle_month'] : '';
        $branchId = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
        if (!$month) { redirect('/inter_branch.php?action=attendance_settle_page&error=no_month'); }
        $cnt = $model->settleAttendance($month, array('branch_id' => $branchId));
        redirect('/inter_branch.php?action=attendance_settle_page&settled_count=' . $cnt . '&date_from=' . $month . '-01&date_to=' . date('Y-m-t', strtotime($month . '-01')));
        break;

    default:
        redirect('/inter_branch.php');
}
