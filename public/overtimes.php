<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/overtimes/OvertimeModel.php';

$model = new OvertimeModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();
$canManage = Auth::hasPermission('overtime.manage');
$canView   = Auth::hasPermission('overtime.view') || $canManage;
$canOwn    = Auth::hasPermission('overtime.own') || $canView;

// 僅允許指定人員手動覆寫加班時數（其他人一律以起迄時間重算）
$_ot_meName = isset(Auth::user()['real_name']) ? Auth::user()['real_name'] : '';
$canOverrideHours = ($_ot_meName === '張孟歆');

if (!$canOwn) {
    Session::flash('error', '無權限使用加班單管理');
    redirect('/index.php');
}

switch ($action) {
    // ---- 加班單清單 ----
    case 'list':
        $filters = array(
            'month'         => !empty($_GET['month']) ? $_GET['month'] : date('Y-m'),
            'user_id'       => !empty($_GET['user_id']) ? $_GET['user_id'] : '',
            'status'        => !empty($_GET['status']) ? $_GET['status'] : '',
            'overtime_type' => !empty($_GET['overtime_type']) ? $_GET['overtime_type'] : '',
            'branch_id'     => !empty($_GET['branch_id']) ? $_GET['branch_id'] : '',
        );
        // 只有 own 權限的人只看自己的
        $onlyUserId = null;
        if (!$canView) {
            $onlyUserId = Auth::id();
        }
        $records = $model->getList($branchIds, $filters, $onlyUserId);
        $users = $canView ? $model->getUsers($branchIds) : array();
        $branches = Database::getInstance()
            ->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = '加班單管理';
        $currentPage = 'overtimes';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/overtimes/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 申請加班 ----
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/overtimes.php');
            }
            try {
                // 一般員工只能幫自己申請；管理者可以幫別人
                $userId = $canView ? (int)$_POST['user_id'] : Auth::id();
                if (!$userId) {
                    throw new Exception('請選擇加班人員');
                }
                $data = array(
                    'user_id'       => $userId,
                    'overtime_date' => !empty($_POST['overtime_date']) ? $_POST['overtime_date'] : date('Y-m-d'),
                    'start_time'    => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
                    'end_time'      => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
                    'hours'         => ($canOverrideHours && isset($_POST['hours'])) ? $_POST['hours'] : 0,
                    'overtime_type' => !empty($_POST['overtime_type']) ? $_POST['overtime_type'] : 'weekday',
                    'reason'        => !empty($_POST['reason']) ? trim($_POST['reason']) : '',
                    'note'          => !empty($_POST['note']) ? trim($_POST['note']) : null,
                    'created_by'    => Auth::id(),
                );
                if (empty($data['start_time']) || empty($data['end_time'])) {
                    throw new Exception('請填寫開始與結束時間');
                }
                if (empty($data['reason'])) {
                    throw new Exception('請填寫加班事由');
                }
                $id = $model->create($data);
                AuditLog::log('overtimes', 'create', $id, '新增加班單');
                Session::flash('success', '加班單已送出，等待核准');
                redirect('/overtimes.php');
            } catch (Exception $e) {
                Session::flash('error', $e->getMessage());
                redirect('/overtimes.php?action=create');
            }
        }

        $record = null;
        $users = $canView ? $model->getUsers($branchIds) : array();
        $pageTitle = '申請加班';
        $currentPage = 'overtimes';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/overtimes/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯加班單（只能編輯自己 pending 狀態）----
    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '加班單不存在');
            redirect('/overtimes.php');
        }
        // 權限：本人 or manage
        if ($record['user_id'] != Auth::id() && !$canManage) {
            Session::flash('error', '權限不足');
            redirect('/overtimes.php');
        }
        if ($record['status'] !== 'pending') {
            Session::flash('error', '只能編輯待核准狀態');
            redirect('/overtimes.php?action=view&id=' . $id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/overtimes.php?action=edit&id=' . $id);
            }
            try {
                $userId = $canView ? (int)$_POST['user_id'] : Auth::id();
                $data = array(
                    'user_id'       => $userId,
                    'overtime_date' => !empty($_POST['overtime_date']) ? $_POST['overtime_date'] : date('Y-m-d'),
                    'start_time'    => $_POST['start_time'],
                    'end_time'      => $_POST['end_time'],
                    'hours'         => ($canOverrideHours && isset($_POST['hours'])) ? $_POST['hours'] : 0,
                    'overtime_type' => !empty($_POST['overtime_type']) ? $_POST['overtime_type'] : 'weekday',
                    'reason'        => trim($_POST['reason']),
                    'note'          => !empty($_POST['note']) ? trim($_POST['note']) : null,
                );
                $model->update($id, $data);
                AuditLog::log('overtimes', 'update', $id, '更新加班單');
                Session::flash('success', '加班單已更新');
                redirect('/overtimes.php?action=view&id=' . $id);
            } catch (Exception $e) {
                Session::flash('error', $e->getMessage());
                redirect('/overtimes.php?action=edit&id=' . $id);
            }
        }

        $users = $canView ? $model->getUsers($branchIds) : array();
        $pageTitle = '編輯加班單';
        $currentPage = 'overtimes';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/overtimes/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 檢視加班單 ----
    case 'view':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '加班單不存在');
            redirect('/overtimes.php');
        }
        // 權限：本人 or view+ or manage
        if ($record['user_id'] != Auth::id() && !$canView) {
            Session::flash('error', '權限不足');
            redirect('/overtimes.php');
        }

        $pageTitle = '加班單檢視';
        $currentPage = 'overtimes';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/overtimes/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 核准 ----
    case 'approve':
        if (!$canManage) {
            Session::flash('error', '權限不足');
            redirect('/overtimes.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/overtimes.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        try {
            $model->approve($id, Auth::id());
            AuditLog::log('overtimes', 'approve', $id, '核准加班單');
            Session::flash('success', '加班單已核准');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        redirect('/overtimes.php?action=view&id=' . $id);
        break;

    // ---- 駁回 ----
    case 'reject':
        if (!$canManage) {
            Session::flash('error', '權限不足');
            redirect('/overtimes.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/overtimes.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        $reason = !empty($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';
        try {
            $model->reject($id, Auth::id(), $reason);
            AuditLog::log('overtimes', 'reject', $id, '駁回加班單: ' . $reason);
            Session::flash('success', '加班單已駁回');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
        }
        redirect('/overtimes.php?action=view&id=' . $id);
        break;

    // ---- 撤回為待審核（管理者）----
    case 'reset_pending':
        if (!$canManage) {
            Session::flash('error', '權限不足');
            redirect('/overtimes.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/overtimes.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        $model->resetToPending($id);
        AuditLog::log('overtimes', 'reset_pending', $id, '撤回為待審核');
        Session::flash('success', '已撤回為待審核');
        redirect('/overtimes.php?action=view&id=' . $id);
        break;

    // ---- 刪除 ----
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/overtimes.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '加班單不存在');
            redirect('/overtimes.php');
        }
        // 本人 + pending/rejected 可刪 ; 或 manage 權限
        $canDelete = ($record['user_id'] == Auth::id() && in_array($record['status'], array('pending', 'rejected'))) || $canManage;
        if (!$canDelete) {
            Session::flash('error', '權限不足');
            redirect('/overtimes.php?action=view&id=' . $id);
        }
        try {
            $model->delete($id);
            AuditLog::log('overtimes', 'delete', $id, '刪除加班單');
            Session::flash('success', '加班單已刪除');
            redirect('/overtimes.php');
        } catch (Exception $e) {
            Session::flash('error', $e->getMessage());
            redirect('/overtimes.php?action=view&id=' . $id);
        }
        break;

    // ---- 月結報表 ----
    case 'monthly_report':
        if (!$canView) {
            Session::flash('error', '權限不足');
            redirect('/overtimes.php');
        }
        $yearMonth = !empty($_GET['month']) ? $_GET['month'] : date('Y-m');
        $statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'approved';
        $summary = $model->getMonthlySummary($yearMonth, $branchIds, $statusFilter ?: null);
        $branches = Database::getInstance()
            ->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = '加班月結報表 - ' . $yearMonth;
        $currentPage = 'overtimes';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/overtimes/monthly_report.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    default:
        redirect('/overtimes.php');
        break;
}
