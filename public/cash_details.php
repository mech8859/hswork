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

// ========== AJAX: 切換星號記號 ==========
if ($action === 'toggle_star') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$canManageFinance && !$isBoss) {
        echo json_encode(array('error' => '無權限'));
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('error' => '方法錯誤'));
        exit;
    }
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(array('error' => '參數錯誤'));
        exit;
    }
    try {
        $db = Database::getInstance();
        $curStmt = $db->prepare("SELECT is_starred FROM cash_details WHERE id = ?");
        $curStmt->execute(array($id));
        $cur = $curStmt->fetchColumn();
        if ($cur === false) {
            echo json_encode(array('error' => '記錄不存在'));
            exit;
        }
        $new = ((int)$cur === 1) ? 0 : 1;
        $db->prepare("UPDATE cash_details SET is_starred = ? WHERE id = ?")
           ->execute(array($new, $id));
        echo json_encode(array('success' => true, 'starred' => $new));
    } catch (Exception $e) {
        echo json_encode(array('error' => $e->getMessage()));
    }
    exit;
}

// ========== 刪除 ==========
if ($action === 'delete' && !empty($_GET['id'])) {
    if (!$isBoss) {
        Session::flash('error', '無權限執行此操作');
        redirect('/cash_details.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/cash_details.php');
    }
    $delId = (int)$_GET['id'];
    AuditLog::log('cash_details', 'delete', $delId, '刪除現金明細記錄');
    $model->deleteCashDetail($delId);
    Session::flash('success', '現金明細記錄已刪除');
    redirect('/cash_details.php');
}

// ========== 更新 POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && !empty($_GET['id'])) {
    if (!$canManageFinance && !$isBoss) {
        Session::flash('error', '無權限執行此操作');
        redirect('/cash_details.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/cash_details.php');
    }
    $id = (int)$_GET['id'];
    $model->updateCashDetail($id, array(
        'transaction_date' => !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : null,
        'type'             => !empty($_POST['type']) ? $_POST['type'] : '支出',
        'amount'           => !empty($_POST['amount']) ? $_POST['amount'] : 0,
        'description'      => !empty($_POST['description']) ? $_POST['description'] : null,
        'sales_name'       => !empty($_POST['sales_name']) ? $_POST['sales_name'] : null,
        'branch_id'        => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
    ));
    AuditLog::log('cash_details', 'update', $id, '更新現金明細記錄');
    Session::flash('success', '現金明細記錄已更新');
    redirect('/cash_details.php');
}

// ========== 編輯/檢視頁面 ==========
if ($action === 'edit' && !empty($_GET['id'])) {
    $_cdId = (int)$_GET['id'];
    $record = $model->getCashDetailById($_cdId);
    if (!$record) {
        Session::flash('error', '找不到此筆記錄');
        redirect('/cash_details.php');
    }
    $branchIds = Auth::getAccessibleBranchIds();
    $branches = $model->getBranches($branchIds);

    // 上一筆/下一筆
    $_cdDb = Database::getInstance();
    $_cdBr = !empty($branchIds) ? implode(',', array_map('intval', $branchIds)) : '0';
    $prevId = $_cdDb->query("SELECT id FROM cash_details WHERE id < $_cdId AND branch_id IN ($_cdBr) ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextId = $_cdDb->query("SELECT id FROM cash_details WHERE id > $_cdId AND branch_id IN ($_cdBr) ORDER BY id ASC LIMIT 1")->fetchColumn();

    $pageTitle = '現金明細 - ' . (!empty($record['entry_number']) ? $record['entry_number'] : '檢視');
    $currentPage = 'cash_details';
    require __DIR__ . '/../templates/layouts/header.php';
    require __DIR__ . '/../templates/cash_details/edit.php';
    require __DIR__ . '/../templates/layouts/footer.php';
    exit;
}

// ========== 新增 POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageFinance && !$isBoss) {
        Session::flash('error', '無權限');
        redirect('/cash_details.php');
    }
    if (!verify_csrf()) {
        Session::flash('error', '安全驗證失敗');
        redirect('/cash_details.php');
    }
    $model->createCashDetail(array(
        'type'             => !empty($_POST['type']) ? $_POST['type'] : '支出',
        'transaction_date' => !empty($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d'),
        'amount'           => !empty($_POST['amount']) ? $_POST['amount'] : 0,
        'description'      => !empty($_POST['description']) ? $_POST['description'] : null,
        'sales_name'       => !empty($_POST['sales_name']) ? $_POST['sales_name'] : null,
        'branch_id'        => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
    ));
    AuditLog::log('cash_details', 'create', 0, '新增現金明細：' . ($_POST['description'] ?? ''));
    Session::flash('success', '現金明細記錄已新增');
    redirect('/cash_details.php');
}

// ========== 列表 ==========
$filters = array(
    'branch_id' => !empty($_GET['branch_id']) ? $_GET['branch_id'] : '',
    'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to'   => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
    'keyword'   => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
    'sort'      => !empty($_GET['sort']) ? $_GET['sort'] : 'desc',
);
$branchIds = Auth::getAccessibleBranchIds();
$branches = $model->getBranches($branchIds);
$branchBalances = $model->getCashDetailsBranchBalances($branchIds);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$result = $model->getCashDetails($filters, $page);
$records = $result['data'];

// Calculate running balance for each record
$totalBalance = $model->getCashDetailsBalanceUpTo($filters);
$sortAsc = (!empty($filters['sort']) && $filters['sort'] === 'asc');

if ($sortAsc) {
    // 舊→新：從 0 累加
    $perPage = $result['perPage'];
    $skipCount = ($page - 1) * $perPage;
    if ($skipCount > 0) {
        $newerCount = $result['total'] - $skipCount;
        $newerSum = $model->getCashDetailsPageSum($filters, $newerCount);
        $runningBalance = $totalBalance - $newerSum;
    } else {
        $runningBalance = 0;
    }
    foreach ($records as $idx => $r) {
        $income = !empty($r['income_amount']) ? (float)$r['income_amount'] : 0;
        $expense = !empty($r['expense_amount']) ? (float)$r['expense_amount'] : 0;
        $runningBalance += ($income - $expense);
        $records[$idx]['running_balance'] = $runningBalance;
    }
} else {
    // 新→舊：從總餘額開始，每筆減掉自己
    $runningBalance = $totalBalance;
    if ($page > 1) {
        $perPage = $result['perPage'];
        $skipCount = ($page - 1) * $perPage;
        $stmtSkip = $model->getCashDetailsPageSum($filters, $skipCount);
        $runningBalance = $totalBalance - $stmtSkip;
    }
    foreach ($records as $idx => $r) {
        $records[$idx]['running_balance'] = $runningBalance;
        $income = !empty($r['income_amount']) ? (float)$r['income_amount'] : 0;
        $expense = !empty($r['expense_amount']) ? (float)$r['expense_amount'] : 0;
        $runningBalance -= ($income - $expense);
    }
}

// 取得業務人員列表（所有啟用的人員）
$db = Database::getInstance();
$staffList = $db->query("SELECT id, real_name FROM users WHERE is_active = 1 AND is_sales = 1 ORDER BY branch_id, real_name")->fetchAll();

$pageTitle = '現金明細';
$currentPage = 'cash_details';
require __DIR__ . '/../templates/layouts/header.php';
require __DIR__ . '/../templates/cash_details/list.php';
require __DIR__ . '/../templates/layouts/footer.php';
