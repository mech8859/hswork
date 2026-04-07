<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/business_calendar/BusinessCalendarModel.php';

$model = new BusinessCalendarModel();
$action = $_GET['action'] ?? 'calendar';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    // ---- 行事曆檢視 ----
    case 'calendar':
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        $filters = array(
            'staff_id'   => $_GET['staff_id'] ?? '',
            'region'     => $_GET['region'] ?? '',
            'branch_ids' => $branchIds,
        );

        $events = $model->getMonthEvents($year, $month, $filters);
        $salespeople = $model->getSalespeople();

        // 本月請假資料
        $db = Database::getInstance();
        $leaveStart = sprintf('%04d-%02d-01', $year, $month);
        $leaveEnd = date('Y-m-t', strtotime($leaveStart));
        $leaveStmt = $db->prepare("
            SELECT l.start_date, l.end_date, l.leave_type, u.real_name
            FROM leaves l JOIN users u ON l.user_id = u.id
            WHERE l.status = 'approved'
              AND l.start_date <= ? AND l.end_date >= ?
              AND u.role IN ('sales','sales_manager','sales_assistant')
            ORDER BY l.start_date
        ");
        $leaveStmt->execute(array($leaveEnd, $leaveStart));
        $leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

        // 按日期整理請假
        $leaveByDate = array();
        foreach ($leaves as $lv) {
            $s = max(strtotime($lv['start_date']), strtotime($leaveStart));
            $e = min(strtotime($lv['end_date']), strtotime($leaveEnd));
            for ($t = $s; $t <= $e; $t += 86400) {
                $d = (int)date('j', $t);
                if (!isset($leaveByDate[$d])) $leaveByDate[$d] = array();
                $leaveByDate[$d][] = $lv;
            }
        }

        $pageTitle = '業務行事曆';
        $currentPage = 'business_calendar';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_calendar/calendar.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 列表檢視 ----
    case 'list':
        $filters = array(
            'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
            'end_date'   => $_GET['end_date'] ?? date('Y-m-t'),
            'staff_id'   => $_GET['staff_id'] ?? '',
            'region'     => $_GET['region'] ?? '',
            'keyword'    => $_GET['keyword'] ?? '',
            'branch_ids' => $branchIds,
        );
        $items = $model->getList($filters);
        $salespeople = $model->getSalespeople();

        $pageTitle = '業務行事曆 - 列表';
        $currentPage = 'business_calendar';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_calendar/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增行程 ----
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/business_calendar.php'); }
            $id = $model->create($_POST);
            Session::flash('success', '行程已新增');
            redirect('/business_calendar.php');
        }
        $salespeople = $model->getSalespeople();
        $event = null;

        $pageTitle = '新增業務行程';
        $currentPage = 'business_calendar';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_calendar/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯行程 ----
    case 'edit':
        $id = (int)($_GET['id'] ?? 0);
        $event = $model->getById($id);
        if (!$event) { Session::flash('error', '行程不存在'); redirect('/business_calendar.php'); }

        // 業務（role=sales 或 is_sales=1）只能編輯自己承辦（staff_id）的行程
        // 非業務 + 有 business_calendar.manage 或 boss/manager → 可編輯所有
        $cu = Auth::user();
        $isSales = ($cu['role'] === 'sales') || !empty($cu['is_sales']);
        $isOwn = ((int)$event['staff_id'] === (int)Auth::id()) || ((int)$event['created_by'] === (int)Auth::id());
        if ($isSales) {
            $canEdit = $isOwn;
        } else {
            $canEdit = Auth::hasPermission('business_calendar.manage') || in_array($cu['role'], array('boss', 'manager', 'vice_president')) || $isOwn;
        }
        if (!$canEdit) {
            Session::flash('error', '權限不足，業務人員只能編輯自己承辦的行程');
            redirect('/business_calendar.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/business_calendar.php?action=edit&id='.$id); }
            $model->update($id, $_POST);
            Session::flash('success', '行程已更新');
            redirect('/business_calendar.php');
        }
        $salespeople = $model->getSalespeople();

        $pageTitle = '編輯業務行程';
        $currentPage = 'business_calendar';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_calendar/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除行程 ----
    case 'delete':
        if (verify_csrf()) {
            $id = (int)$_GET['id'];
            $event = $model->getById($id);
            if ($event) {
                $cu = Auth::user();
                $isSales = ($cu['role'] === 'sales') || !empty($cu['is_sales']);
                $isOwn = ((int)$event['staff_id'] === (int)Auth::id()) || ((int)$event['created_by'] === (int)Auth::id());
                $canDel = $isSales
                    ? $isOwn
                    : ($isOwn || Auth::hasPermission('business_calendar.manage') || in_array($cu['role'], array('boss','manager','vice_president')));
                if ($canDel) {
                    $model->delete($id);
                    Session::flash('success', '行程已刪除');
                } else {
                    Session::flash('error', '權限不足');
                }
            }
        }
        redirect('/business_calendar.php');
        break;

    // ---- 日檢視 ----
    case 'day':
        $date = $_GET['date'] ?? date('Y-m-d');
        $filters = array(
            'start_date' => $date,
            'end_date'   => $date,
            'staff_id'   => $_GET['staff_id'] ?? '',
            'region'     => $_GET['region'] ?? '',
            'branch_ids' => $branchIds,
        );
        $items = $model->getList($filters);
        $salespeople = $model->getSalespeople();

        $pageTitle = '業務行程 - ' . $date;
        $currentPage = 'business_calendar';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/business_calendar/day.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    default:
        redirect('/business_calendar.php');
}
