<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限存取');
    redirect('/');
}
require_once __DIR__ . '/../modules/finance/FinanceModel.php';

$model = new FinanceModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();
$isBoss = Auth::hasPermission('all');
$canManageFinance = Auth::hasPermission('finance.manage');

switch ($action) {
    // ---- 應收帳款清單 ----
    case 'list':
        $filters = array(
            'status'    => !empty($_GET['status']) ? $_GET['status'] : '',
            'keyword'   => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'   => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'sort'      => !empty($_GET['sort']) ? $_GET['sort'] : 'desc',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getReceivables($filters, $branchIds, $page);
        $data = $result['data'];
        $branches = $model->getBranches($branchIds);

        $pageTitle = '應收帳款';
        $currentPage = 'receivables';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/receivables/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增請款單 ----
    case 'create':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/receivables.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/receivables.php');
            }
            $userId = Session::getUser()['id'];
            $postData = array(
                'invoice_date'        => !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'),
                'voucher_number'      => !empty($_POST['voucher_number']) ? $_POST['voucher_number'] : null,
                'case_id'             => !empty($_POST['case_id']) ? $_POST['case_id'] : null,
                'case_number'         => !empty($_POST['case_number']) ? $_POST['case_number'] : null,
                'customer_no'         => !empty($_POST['customer_no']) ? $_POST['customer_no'] : null,
                'customer_name'       => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'branch_id'           => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'sales_id'            => !empty($_POST['sales_id']) ? $_POST['sales_id'] : null,
                'invoice_category'    => !empty($_POST['invoice_category']) ? $_POST['invoice_category'] : null,
                'status'              => !empty($_POST['status']) ? $_POST['status'] : '待請款',
                'invoice_title'       => !empty($_POST['invoice_title']) ? $_POST['invoice_title'] : null,
                'tax_id'              => !empty($_POST['tax_id']) ? $_POST['tax_id'] : null,
                'phone'               => !empty($_POST['phone']) ? $_POST['phone'] : null,
                'mobile'              => !empty($_POST['mobile']) ? $_POST['mobile'] : null,
                'invoice_email'       => !empty($_POST['invoice_email']) ? $_POST['invoice_email'] : null,
                'invoice_address'     => !empty($_POST['invoice_address']) ? $_POST['invoice_address'] : null,
                'payment_method'      => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_terms'       => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'deposit'             => isset($_POST['deposit']) ? (int)$_POST['deposit'] : 0,
                'discount'            => isset($_POST['discount']) ? (int)$_POST['discount'] : 0,
                'subtotal'            => isset($_POST['subtotal']) ? (int)$_POST['subtotal'] : 0,
                'tax'                 => isset($_POST['tax']) ? (int)$_POST['tax'] : 0,
                'shipping'            => isset($_POST['shipping']) ? (int)$_POST['shipping'] : 0,
                'total_amount'        => isset($_POST['total_amount']) ? (int)$_POST['total_amount'] : 0,
                'real_invoice_number' => !empty($_POST['real_invoice_number']) ? $_POST['real_invoice_number'] : null,
                'voucher_type'        => !empty($_POST['voucher_type']) ? $_POST['voucher_type'] : null,
                'tax_rate'            => !empty($_POST['tax_rate']) ? $_POST['tax_rate'] : null,
                'registrar'           => Session::getUser()['real_name'] ?? null,
                'note'                => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'          => $userId,
            );
            $receivableId = $model->createReceivable($postData);
            if (!empty($_POST['items'])) {
                $model->saveReceivableItems($receivableId, $_POST['items']);
            }
            AuditLog::log('receivables', 'create', $receivableId, '新增請款單');
            Session::flash('success', '請款單已新增');
            redirect('/receivables.php?action=edit&id=' . $receivableId);
        }

        $record = null;
        $items = array();
        $branches = $model->getBranches($branchIds);
        $salesUsers = $model->getSalesUsers($branchIds);

        $pageTitle = '新增請款單';
        $currentPage = 'receivables';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/receivables/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯請款單 ----
    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getReceivable($id);
        if (!$record) {
            Session::flash('error', '請款單不存在');
            redirect('/receivables.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManageFinance && !$isBoss) {
                Session::flash('error', '無權限執行此操作');
                redirect('/receivables.php');
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/receivables.php?action=edit&id=' . $id);
            }
            $userId = Session::getUser()['id'];
            $postData = array(
                'invoice_date'        => !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'),
                'voucher_number'      => !empty($_POST['voucher_number']) ? $_POST['voucher_number'] : null,
                'case_id'             => !empty($_POST['case_id']) ? $_POST['case_id'] : null,
                'case_number'         => !empty($_POST['case_number']) ? $_POST['case_number'] : null,
                'customer_no'         => !empty($_POST['customer_no']) ? $_POST['customer_no'] : null,
                'customer_name'       => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'branch_id'           => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'sales_id'            => !empty($_POST['sales_id']) ? $_POST['sales_id'] : null,
                'invoice_category'    => !empty($_POST['invoice_category']) ? $_POST['invoice_category'] : null,
                'status'              => !empty($_POST['status']) ? $_POST['status'] : '待請款',
                'invoice_title'       => !empty($_POST['invoice_title']) ? $_POST['invoice_title'] : null,
                'tax_id'              => !empty($_POST['tax_id']) ? $_POST['tax_id'] : null,
                'phone'               => !empty($_POST['phone']) ? $_POST['phone'] : null,
                'mobile'              => !empty($_POST['mobile']) ? $_POST['mobile'] : null,
                'invoice_email'       => !empty($_POST['invoice_email']) ? $_POST['invoice_email'] : null,
                'invoice_address'     => !empty($_POST['invoice_address']) ? $_POST['invoice_address'] : null,
                'payment_method'      => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_terms'       => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'deposit'             => isset($_POST['deposit']) ? (int)$_POST['deposit'] : 0,
                'discount'            => isset($_POST['discount']) ? (int)$_POST['discount'] : 0,
                'subtotal'            => isset($_POST['subtotal']) ? (int)$_POST['subtotal'] : 0,
                'tax'                 => isset($_POST['tax']) ? (int)$_POST['tax'] : 0,
                'shipping'            => isset($_POST['shipping']) ? (int)$_POST['shipping'] : 0,
                'total_amount'        => isset($_POST['total_amount']) ? (int)$_POST['total_amount'] : 0,
                'real_invoice_number' => !empty($_POST['real_invoice_number']) ? $_POST['real_invoice_number'] : null,
                'voucher_type'        => !empty($_POST['voucher_type']) ? $_POST['voucher_type'] : null,
                'tax_rate'            => !empty($_POST['tax_rate']) ? $_POST['tax_rate'] : null,
                'note'                => !empty($_POST['note']) ? $_POST['note'] : null,
                'updated_by'          => $userId,
            );
            try {
                $model->updateReceivable($id, $postData);
                if (isset($_POST['items'])) {
                    $model->saveReceivableItems($id, $_POST['items']);
                }
                AuditLog::log('receivables', 'update', $id, '更新請款單');
                Session::flash('success', '請款單已更新');
                redirect('/receivables.php?action=edit&id=' . $id);
            } catch (Exception $ex) {
                Session::flash('error', '更新失敗: ' . $ex->getMessage());
                redirect('/receivables.php?action=edit&id=' . $id);
            }
        }

        $items = $model->getReceivableItems($record['id']);
        $branches = $model->getBranches($branchIds);
        $salesUsers = $model->getSalesUsers($branchIds);

        // 編輯鎖定（多人同時編輯提醒）
        require_once __DIR__ . '/../includes/EditingLock.php';
        $_curUser = Auth::user();
        if ($_curUser && $id > 0) EditingLock::set('receivables', $id, $_curUser['id'], $_curUser['real_name']);
        $otherEditors = ($id > 0) ? EditingLock::getOthers('receivables', $id, Auth::id()) : array();
        $editingLockModule = 'receivables';
        $editingLockRecordId = $id;

        $pageTitle = '編輯請款單 - ' . $record['invoice_number'];
        $currentPage = 'receivables';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/receivables/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除請款單 ----
    case 'delete':
        if (!Auth::hasPermission('finance.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/receivables.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/receivables.php');
            }
            $id = (int)(!empty($_POST['id']) ? $_POST['id'] : (!empty($_GET['id']) ? $_GET['id'] : 0));
            if ($id) {
                AuditLog::log('receivables', 'delete', $id, '刪除請款單');
                $model->deleteReceivable($id);
                Session::flash('success', '請款單已刪除');
            }
        }
        redirect('/receivables.php');
        break;

    default:
        redirect('/receivables.php');
}
