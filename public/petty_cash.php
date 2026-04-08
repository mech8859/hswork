<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

// 權限：petty_cash.* 為新獨立權限，並向下相容 finance.*
$canViewPetty = Auth::hasPermission('petty_cash.view') || Auth::hasPermission('petty_cash.manage')
              || Auth::hasPermission('finance.view') || Auth::hasPermission('finance.manage')
              || Auth::hasPermission('all');
$canManagePetty = Auth::hasPermission('petty_cash.manage')
                || Auth::hasPermission('finance.manage')
                || Auth::hasPermission('all');

if (!$canViewPetty) {
    Session::flash('error', '無權限存取');
    redirect('/');
}

require_once __DIR__ . '/../modules/finance/FinanceModel.php';

$model = new FinanceModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$isBoss = Auth::hasPermission('all');
$canManageFinance = $canManagePetty;

// 取得使用者可存取的分公司（用於資料隔離守門）
$accessibleBranchIds = Auth::getAccessibleBranchIds();

/**
 * 守門：檢查 record 的 branch_id 是否屬於使用者可存取分公司
 */
function pettyCashAssertBranchAccess($record, $accessibleBranchIds) {
    if (!$record) return false;
    if (empty($accessibleBranchIds)) return false;
    $recBranchId = isset($record['branch_id']) ? (int)$record['branch_id'] : 0;
    if ($recBranchId === 0) return true; // 無分公司限制的紀錄保留可看
    return in_array($recBranchId, $accessibleBranchIds);
}

/**
 * 守門：檢查使用者送出的 branch_id 是否在可存取範圍
 */
function pettyCashAssertPostBranchAccess($postBranchId, $accessibleBranchIds) {
    $bid = (int)$postBranchId;
    if ($bid === 0) return true; // 允許不指定分公司
    return in_array($bid, $accessibleBranchIds);
}

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
    $rec = $model->getPettyCashById($delId);
    if (!pettyCashAssertBranchAccess($rec, $accessibleBranchIds)) {
        Session::flash('error', '無權限刪除此分公司的零用金記錄');
        redirect('/petty_cash.php');
    }
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
    $rec = $model->getPettyCashById($id);
    if (!pettyCashAssertBranchAccess($rec, $accessibleBranchIds)) {
        Session::flash('error', '無權限修改此分公司的零用金記錄');
        redirect('/petty_cash.php');
    }
    // 驗證新的 branch_id 也在範圍內
    if (!empty($_POST['branch_id']) && !pettyCashAssertPostBranchAccess($_POST['branch_id'], $accessibleBranchIds)) {
        Session::flash('error', '無權限將記錄改為此分公司');
        redirect('/petty_cash.php');
    }
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
    if (!pettyCashAssertBranchAccess($record, $accessibleBranchIds)) {
        Session::flash('error', '無權限查看此分公司的零用金記錄');
        redirect('/petty_cash.php');
    }
    $branches = $model->getBranches($accessibleBranchIds);
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
    // 驗證 branch_id 在可存取範圍
    if (!empty($_POST['branch_id']) && !pettyCashAssertPostBranchAccess($_POST['branch_id'], $accessibleBranchIds)) {
        Session::flash('error', '無權限新增此分公司的零用金記錄');
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
// 若使用者手動篩選的 branch_id 不在可存取範圍，強制清掉避免繞過
if (!empty($filters['branch_id']) && !in_array((int)$filters['branch_id'], $accessibleBranchIds)) {
    $filters['branch_id'] = '';
}

$branches = $model->getBranches($accessibleBranchIds);
$branchBalances = $model->getPettyCashBranchBalances($accessibleBranchIds);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
// 新版本：傳入 accessibleBranchIds 強制資料隔離
$result = $model->getPettyCashList($filters, $page, 100, $accessibleBranchIds);
$records = $result['data'];

// Calculate running balance for each record（同樣強制隔離）
$totalBalance = $model->getPettyCashBalanceUpTo($filters, 0, $accessibleBranchIds);

$runningBalance = $totalBalance;
if ($page > 1) {
    $perPage = $result['perPage'];
    $skipCount = ($page - 1) * $perPage;
    $stmtSkip = $model->getPettyCashPageSum($filters, $skipCount, $accessibleBranchIds);
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
