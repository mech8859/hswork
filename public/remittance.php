<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/remittance/RemittanceModel.php';

$model = new RemittanceModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$canManage = Auth::hasPermission('all') || in_array(Auth::user()['role'], array('boss', 'manager'));

switch ($action) {
    // ---- 各分公司彙總 ----
    case 'list':
        $summary = $model->getBranchSummary();

        $pageTitle = '未繳回帳務';
        $currentPage = 'remittance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/remittance/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 分公司明細 ----
    case 'branch':
        $branchId = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        if (!$branchId) { redirect('/remittance.php'); }

        // 非管理處只能看自己分公司
        if (!$canManage) {
            $myBranches = Auth::getAccessibleBranchIds();
            if (!in_array($branchId, $myBranches)) {
                Session::flash('error', '權限不足');
                redirect('/remittance.php');
            }
        }

        $branchName = $model->getBranchName($branchId);
        $unremitted = $model->getUnremittedByBranch($branchId);
        $remitted = $model->getRemittedByBranch($branchId);

        $pageTitle = $branchName . ' — 未繳回帳務';
        $currentPage = 'remittance';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/remittance/branch.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 批次繳回 ----
    case 'remit':
        if (!$canManage) {
            Session::flash('error', '只有管理處可以操作繳回');
            redirect('/remittance.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/remittance.php');
        }

        $paymentIds = isset($_POST['payment_ids']) ? $_POST['payment_ids'] : array();
        $remitDate = isset($_POST['remit_date']) ? $_POST['remit_date'] : date('Y-m-d');
        $remitNote = isset($_POST['remit_note']) ? trim($_POST['remit_note']) : '';
        $branchId = (int)(isset($_POST['branch_id']) ? $_POST['branch_id'] : 0);

        $count = $model->batchRemit($paymentIds, $remitDate, $remitNote);
        Session::flash('success', '已確認繳回 ' . $count . ' 筆');
        redirect('/remittance.php?action=branch&id=' . $branchId);
        break;

    // ---- 取消繳回 ----
    case 'cancel':
        if (!$canManage) {
            Session::flash('error', '權限不足');
            redirect('/remittance.php');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/remittance.php');
        }
        $paymentId = (int)(isset($_GET['payment_id']) ? $_GET['payment_id'] : 0);
        $branchId = (int)(isset($_GET['branch_id']) ? $_GET['branch_id'] : 0);
        $model->cancelRemit($paymentId);
        Session::flash('success', '已取消繳回');
        redirect('/remittance.php?action=branch&id=' . $branchId);
        break;

    default:
        redirect('/remittance.php');
}
