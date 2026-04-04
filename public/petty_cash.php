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
        redirect('/petty_cash.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/petty_cash.php');
    }
    $delId = (int)$_GET['id'];
    AuditLog::log('petty_cash', 'delete', $delId, '刪除零用金記錄');
    $model->deletePettyCash($delId);
    Session::flash('success', '零用金記錄已刪除');
    redirect('/petty_cash.php');
}

// ========== 更新 POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && !empty($_GET['id'])) {
    if (!$canManageFinance && !$isBoss) {
        Session::flash('error', '無權限執行此操作');
        redirect('/petty_cash.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/petty_cash.php');
    }
    $id = (int)$_GET['id'];
    $model->updatePettyCash($id, array(
        'entry_date'      => !empty($_POST['entry_date']) ? $_POST['entry_date'] : null,
        'type'            => !empty($_POST['type']) ? $_POST['type'] : '支出',
        'amount'          => !empty($_POST['amount']) ? $_POST['amount'] : 0,
        'has_invoice'     => !empty($_POST['has_invoice']) ? $_POST['has_invoice'] : null,
        'invoice_info'    => !empty($_POST['invoice_info']) ? $_POST['invoice_info'] : null,
        'description'     => !empty($_POST['description']) ? $_POST['description'] : null,
        'branch_id'       => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
        'registrar'       => !empty($_POST['registrar']) ? $_POST['registrar'] : null,
        'approval_status' => !empty($_POST['approval_status']) ? $_POST['approval_status'] : null,
    ));
    AuditLog::log('petty_cash', 'update', $id, '更新零用金記錄');
    Session::flash('success', '零用金記錄已更新');
    redirect('/petty_cash.php');
}

// ========== 編輯/檢視頁面 ==========
if ($action === 'edit' && !empty($_GET['id'])) {
    $record = $model->getPettyCashById((int)$_GET['id']);
    if (!$record) {
        Session::flash('error', '找不到此筆記錄');
        redirect('/petty_cash.php');
    }
    $branchIds = Auth::getAccessibleBranchIds();
    $branches = $model->getBranches($branchIds);
    $pageTitle = '零用金 - ' . (!empty($record['entry_number']) ? $record['entry_number'] : '檢視');
    $currentPage = 'petty_cash';
    require __DIR__ . '/../templates/layouts/header.php';
    require __DIR__ . '/../templates/petty_cash/edit.php';
    require __DIR__ . '/../templates/layouts/footer.php';
    exit;
}

// ========== 新增 POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageFinance && !$isBoss) {
        Session::flash('error', '無權限');
        redirect('/petty_cash.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/petty_cash.php');
    }
    $user = Auth::user();
    $pcId = $model->createPettyCash(array(
        'type'        => !empty($_POST['type']) ? $_POST['type'] : '支出',
        'entry_date'  => !empty($_POST['entry_date']) ? $_POST['entry_date'] : date('Y-m-d'),
        'amount'      => !empty($_POST['amount']) ? $_POST['amount'] : 0,
        'has_invoice'  => !empty($_POST['has_invoice']) ? $_POST['has_invoice'] : null,
        'description'  => !empty($_POST['description']) ? $_POST['description'] : null,
        'branch_id'    => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
        'registrar'    => $user ? $user['real_name'] : null,
    ));
    AuditLog::log('petty_cash', 'create', $pcId ? $pcId : 0, '新增零用金：' . (isset($_POST['description']) ? $_POST['description'] : ''));

    // Auto-journal on petty cash create
    if ($pcId) {
        try {
            require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
            AutoJournalService::onPettyCashExpense($pcId);
        } catch (Exception $autoJournalEx) {
            error_log('AutoJournal petty_cash error: ' . $autoJournalEx->getMessage());
        }
    }

    Session::flash('success', '零用金記錄已新增');
    redirect('/petty_cash.php');
}

// ========== 列表 ==========
$filters = array(
    'branch_id' => !empty($_GET['branch_id']) ? $_GET['branch_id'] : '',
    'type'      => !empty($_GET['type']) ? $_GET['type'] : '',
    'keyword'   => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
    'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to'   => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
    'sort'      => !empty($_GET['sort']) ? $_GET['sort'] : 'desc',
);
$branchIds = Auth::getAccessibleBranchIds();
$branches = $model->getBranches($branchIds);
$branchBalances = $model->getPettyCashBranchBalances($branchIds);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$result = $model->getPettyCashList($filters, $page);
$records = $result['data'];

// Calculate running balance for each record
$totalBalance = $model->getPettyCashBalanceUpTo($filters, 0);

$runningBalance = $totalBalance;
if ($page > 1) {
    $perPage = $result['perPage'];
    $skipCount = ($page - 1) * $perPage;
    $stmtSkip = $model->getPettyCashPageSum($filters, $skipCount);
    $runningBalance = $totalBalance - $stmtSkip;
}

foreach ($records as $idx => $r) {
    $records[$idx]['running_balance'] = $runningBalance;
    $income = !empty($r['income_amount']) ? (float)$r['income_amount'] : 0;
    $expense = !empty($r['expense_amount']) ? (float)$r['expense_amount'] : 0;
    $runningBalance -= ($income - $expense);
}

$pageTitle = '零用金管理';
$currentPage = 'petty_cash';
require __DIR__ . '/../templates/layouts/header.php';
require __DIR__ . '/../templates/petty_cash/list.php';
require __DIR__ . '/../templates/layouts/footer.php';
