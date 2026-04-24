<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/leaves/LeaveModel.php';

$model = new LeaveModel();
$action = $_GET['action'] ?? 'calendar';
$branchIds = Auth::getAccessibleBranchIds();
$canManage = Auth::hasPermission('leaves.manage');

switch ($action) {
    // ---- 請假清單 ----
    case 'list':
        $filters = array(
            'month'   => $_GET['month'] ?? date('Y-m'),
            'user_id' => $_GET['user_id'] ?? '',
            'status'  => $_GET['status'] ?? '',
        );
        // 只有 leaves.own 的人只看自己的
        $onlyUserId = null;
        if (!$canManage && !Auth::hasPermission('leaves.view')) {
            $onlyUserId = Auth::id();
        }
        $leaves = $model->getList($branchIds, $filters, $onlyUserId);
        $users = $canManage ? $model->getUsers($branchIds) : array();

        $pageTitle = '請假管理';
        $currentPage = 'leaves';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/leaves/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 檢視單筆 ----
    case 'view':
        $id = (int)($_GET['id'] ?? 0);
        $leave = $model->getById($id);
        if (!$leave) {
            Session::flash('error', '請假單不存在');
            redirect('/leaves.php');
        }
        // 權限：本人 or view+ or manage
        if ($leave['user_id'] != Auth::id() && !$canManage && !Auth::hasPermission('leaves.view')) {
            Session::flash('error', '權限不足');
            redirect('/leaves.php');
        }

        $pageTitle = '請假單檢視';
        $currentPage = 'leaves';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/leaves/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 申請請假 ----
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/leaves.php'); }

            $data = array(
                'user_id'    => $canManage ? (int)$_POST['user_id'] : Auth::id(),
                'leave_type' => $_POST['leave_type'],
                'start_date' => $_POST['start_date'],
                'end_date'   => $_POST['end_date'],
                'start_time' => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
                'end_time'   => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
                'reason'     => $_POST['reason'] ?? '',
            );

            if ($data['start_date'] > $data['end_date']) {
                Session::flash('error', '結束日期不能早於開始日期');
                redirect('/leaves.php?action=create');
            }

            $leaveId = $model->create($data);

            // 觸發簽核流程（依請假人的 branch_id + role 比對規則）
            try {
                require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';
                $approvalModel = new ApprovalModel();
                $approvalModel->submitForApproval('leaves', $leaveId, 0, null, (int)$data['user_id']);
            } catch (Exception $e) {
                error_log('Leave approval dispatch failed: ' . $e->getMessage());
            }

            Session::flash('success', '請假申請已送出');
            redirect('/leaves.php');
        }

        $users = $canManage ? $model->getUsers($branchIds) : array();
        $pageTitle = '申請請假';
        $currentPage = 'leaves';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/leaves/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 核准 ----
    case 'approve':
        if (!$canManage) { Session::flash('error', '權限不足'); redirect('/leaves.php'); }
        if (verify_csrf()) {
            $model->approve((int)$_GET['id'], Auth::id());
            Session::flash('success', '已核准');
        }
        redirect('/leaves.php');
        break;

    // ---- 駁回 ----
    case 'reject':
        if (!$canManage) { Session::flash('error', '權限不足'); redirect('/leaves.php'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            $model->reject((int)$_POST['id'], Auth::id(), $_POST['reject_reason'] ?? '');
            Session::flash('success', '已駁回');
        }
        redirect('/leaves.php');
        break;

    // ---- 刪除 ----
    case 'delete':
        if (verify_csrf()) {
            $leave = $model->getById((int)$_GET['id']);
            // 自己的待審核假單可以刪除，或有 leaves.delete 權限
            $canDeleteLeave = ($leave && $leave['user_id'] == Auth::id() && $leave['status'] === 'pending');
            $hasDeletePerm = Auth::hasPermission('leaves.delete') || Auth::hasPermission('all');
            if ($canDeleteLeave || $hasDeletePerm) {
                $model->delete((int)$_GET['id']);
                Session::flash('success', '已刪除');
            } else {
                Session::flash('error', '權限不足，無法刪除此請假申請');
            }
        }
        redirect('/leaves.php');
        break;

    // ---- 請假行事曆 ----
    case 'calendar':
        $yearMonth = $_GET['month'] ?? date('Y-m');
        $calendarData = $model->getCalendarData($yearMonth, $branchIds);
        $users = $model->getUsers($branchIds);

        $pageTitle = '請假行事曆';
        $currentPage = 'leaves';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/leaves/calendar.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 批次新增請假 (AJAX) ----
    case 'batch_create':
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        if (!$canManage) {
            echo json_encode(array('error' => '權限不足'));
            exit;
        }
        $date = $_POST['date'] ?? '';
        $userIds = isset($_POST['user_ids']) ? (array)$_POST['user_ids'] : array();
        $leaveType = $_POST['leave_type'] ?? 'personal';
        if (!$date || empty($userIds)) {
            echo json_encode(array('error' => '缺少參數'));
            exit;
        }
        $created = $model->createBatch($date, $userIds, $leaveType);
        echo json_encode(array('success' => true, 'created' => $created));
        exit;

    // ---- 取消某日請假 (AJAX) ----
    case 'cancel_leave':
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            exit;
        }
        if (!$canManage) {
            echo json_encode(array('error' => '權限不足'));
            exit;
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        if (!$userId || !$date) {
            echo json_encode(array('error' => '缺少參數'));
            exit;
        }
        $result = $model->cancelLeaveOnDate($userId, $date);
        echo json_encode(array('success' => $result));
        exit;

    default:
        redirect('/leaves.php');
}
