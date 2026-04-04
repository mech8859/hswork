<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限存取');
    redirect('/');
}
require_once __DIR__ . '/../modules/finance/FinanceModel.php';

$model = new FinanceModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$isBoss = Auth::hasPermission('all');
$canManageFinance = Auth::hasPermission('finance.manage');

// ========== 刪除 ==========
if ($action === 'delete' && !empty($_GET['id'])) {
    if (!$isBoss) {
        Session::flash('error', '無權限執行此操作');
        redirect('/reserve_fund.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/reserve_fund.php');
    }
    $delId = (int)$_GET['id'];
    AuditLog::log('reserve_fund', 'delete', $delId, '刪除備用金記錄');
    $model->deleteReserveFund($delId);
    Session::flash('success', '備用金記錄已刪除');
    redirect('/reserve_fund.php');
}

// ========== 更新 POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && !empty($_GET['id'])) {
    if (!$canManageFinance && !$isBoss) {
        Session::flash('error', '無權限執行此操作');
        redirect('/reserve_fund.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/reserve_fund.php');
    }
    $id = (int)$_GET['id'];
    $model->updateReserveFund($id, array(
        'expense_date'    => !empty($_POST['expense_date']) ? $_POST['expense_date'] : null,
        'type'            => !empty($_POST['type']) ? $_POST['type'] : '支出',
        'amount'          => !empty($_POST['amount']) ? $_POST['amount'] : 0,
        'description'     => !empty($_POST['description']) ? $_POST['description'] : null,
        'invoice_info'    => !empty($_POST['invoice_info']) ? $_POST['invoice_info'] : null,
        'branch_id'       => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
        'registrar'       => !empty($_POST['registrar']) ? $_POST['registrar'] : null,
        'approval_status' => !empty($_POST['approval_status']) ? $_POST['approval_status'] : null,
    ));
    AuditLog::log('reserve_fund', 'update', $id, '更新備用金記錄');
    Session::flash('success', '備用金記錄已更新');
    redirect('/reserve_fund.php');
}

// ========== 編輯/檢視頁面 ==========
if ($action === 'edit' && !empty($_GET['id'])) {
    $record = $model->getReserveFundById((int)$_GET['id']);
    if (!$record) {
        Session::flash('error', '找不到此筆記錄');
        redirect('/reserve_fund.php');
    }
    $branchIds = Auth::getAccessibleBranchIds();
    $branches = $model->getBranches($branchIds);
    $pageTitle = '備用金 - ' . (!empty($record['entry_number']) ? $record['entry_number'] : '檢視');
    $currentPage = 'reserve_fund';
    require __DIR__ . '/../templates/layouts/header.php';
    require __DIR__ . '/../templates/reserve_fund/edit.php';
    require __DIR__ . '/../templates/layouts/footer.php';
    exit;
}

// ========== 新增 POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageFinance && !$isBoss) {
        Session::flash('error', '無權限');
        redirect('/reserve_fund.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/reserve_fund.php');
    }
    $user = Auth::user();
    try {
        $newId = $model->createReserveFund(array(
            'type'         => !empty($_POST['type']) ? $_POST['type'] : '支出',
            'expense_date' => !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d'),
            'amount'       => !empty($_POST['amount']) ? $_POST['amount'] : 0,
            'description'  => !empty($_POST['description']) ? $_POST['description'] : null,
            'branch_id'    => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
            'registrar'    => $user ? $user['real_name'] : null,
        ));
        AuditLog::log('reserve_fund', 'create', $newId ? $newId : 0, '新增備用金：' . (!empty($_POST['description']) ? $_POST['description'] : ''));
        Session::flash('success', '備用金記錄已新增');
    } catch (Exception $e) {
        error_log('reserve_fund create error: ' . $e->getMessage());
        Session::flash('error', '新增失敗：' . $e->getMessage());
    }
    redirect('/reserve_fund.php');
}

// ========== 列表 ==========
$filters = array(
    'branch_id' => !empty($_GET['branch_id']) ? $_GET['branch_id'] : '',
    'type'      => !empty($_GET['type']) ? $_GET['type'] : '',
    'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to'   => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
    'keyword'   => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
    'sort'      => !empty($_GET['sort']) ? $_GET['sort'] : 'desc',
);
$branchIds = Auth::getAccessibleBranchIds();
$branches = $model->getBranches($branchIds);
$defaultBranchId = 21; // 中區管理處
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$result = $model->getReserveFundList($filters, $page);
$records = $result['data'];

// Calculate running balance for each record
$totalBalance = $model->getReserveFundBalanceUpTo($filters);
$runningBalance = $totalBalance;
if ($page > 1) {
    $perPage = $result['perPage'];
    $skipCount = ($page - 1) * $perPage;
    $stmtSkip = $model->getReserveFundPageSum($filters, $skipCount);
    $runningBalance = $totalBalance - $stmtSkip;
}
foreach ($records as $idx => $r) {
    $records[$idx]['running_balance'] = $runningBalance;
    $income = !empty($r['income_amount']) ? (float)$r['income_amount'] : 0;
    $expense = !empty($r['expense_amount']) ? (float)$r['expense_amount'] : 0;
    $runningBalance -= ($income - $expense);
}

$pageTitle = '備用金管理';
$currentPage = 'reserve_fund';
require __DIR__ . '/../templates/layouts/header.php';
require __DIR__ . '/../templates/reserve_fund/list.php';
require __DIR__ . '/../templates/layouts/footer.php';
