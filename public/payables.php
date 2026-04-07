<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限查看此頁面');
    redirect('/');
}
require_once __DIR__ . '/../modules/finance/FinanceModel.php';

$model = new FinanceModel();
$action = $_GET['action'] ?? 'list';
$branchIds = Auth::getAccessibleBranchIds();
$isBoss = Auth::hasPermission('all');
$canManageFinance = Auth::hasPermission('finance.manage');

switch ($action) {
    // ---- 應付帳款清單 ----
    case 'list':
        $filters = array(
            'keyword'   => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'   => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'sort'      => !empty($_GET['sort']) ? $_GET['sort'] : 'desc',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getPayables($filters, $page);
        $records = $result['data'];

        $pageTitle = '應付帳款單';
        $currentPage = 'payables';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/payables/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增應付帳款 ----
    case 'create':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/payables.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/payables.php');
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'create_date'    => !empty($_POST['create_date']) ? $_POST['create_date'] : date('Y-m-d'),
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'case_number'    => !empty($_POST['case_number']) ? $_POST['case_number'] : null,
                'customer_no'    => !empty($_POST['customer_no']) ? $_POST['customer_no'] : null,
                'payment_period' => !empty($_POST['payment_period']) ? $_POST['payment_period'] : null,
                'payment_terms'  => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'subtotal'       => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax'            => !empty($_POST['tax']) ? $_POST['tax'] : 0,
                'total_amount'   => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'prepaid'        => !empty($_POST['prepaid']) ? $_POST['prepaid'] : 0,
                'payable_amount' => !empty($_POST['payable_amount']) ? $_POST['payable_amount'] : 0,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
                'registrar'      => Session::getUser()['real_name'] ?? null,
                'created_by'     => $userId,
            );

            $payableId = $model->createPayable($data);

            // 儲存分公司拆帳
            if (!empty($_POST['branches'])) {
                $model->savePayableBranches($payableId, $_POST['branches']);
            }
            // 儲存進貨明細
            if (!empty($_POST['pd'])) {
                $model->savePayablePurchaseDetails($payableId, $_POST['pd']);
            }
            // 儲存進退明細
            if (!empty($_POST['rd'])) {
                $model->savePayableReturnDetails($payableId, $_POST['rd']);
            }
            // 儲存發票明細
            if (!empty($_POST['invoices'])) {
                $model->savePayableInvoices($payableId, $_POST['invoices']);
            }

            AuditLog::log('payables', 'create', $payableId, '新增應付帳款單');
            Session::flash('success', '應付帳款單已新增');
            redirect('/payables.php');
        }

        $record = null;
        $branches = $model->getBranches();
        $branchItems = array();
        $invoiceItems = array();

        $pageTitle = '新增應付帳款單';
        $currentPage = 'payables';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/payables/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯應付帳款 ----
    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getPayable($id);
        if (!$record) {
            Session::flash('error', '應付帳款單不存在');
            redirect('/payables.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManageFinance && !$isBoss) {
                Session::flash('error', '無權限執行此操作');
                redirect('/payables.php');
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/payables.php?action=edit&id=' . $id);
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'create_date'    => !empty($_POST['create_date']) ? $_POST['create_date'] : date('Y-m-d'),
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'case_number'    => !empty($_POST['case_number']) ? $_POST['case_number'] : null,
                'customer_no'    => !empty($_POST['customer_no']) ? $_POST['customer_no'] : null,
                'payment_period' => !empty($_POST['payment_period']) ? $_POST['payment_period'] : null,
                'payment_terms'  => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'subtotal'       => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax'            => !empty($_POST['tax']) ? $_POST['tax'] : 0,
                'total_amount'   => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'prepaid'        => !empty($_POST['prepaid']) ? $_POST['prepaid'] : 0,
                'payable_amount' => !empty($_POST['payable_amount']) ? $_POST['payable_amount'] : 0,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
                'updated_by'     => $userId,
            );

            $model->updatePayable($id, $data);

            // 儲存分公司拆帳
            if (isset($_POST['branches'])) {
                $model->savePayableBranches($id, $_POST['branches']);
            }
            // 儲存進貨明細（只有表單有渲染該區塊時才更新，避免誤刪）
            if (!empty($_POST['pd_rendered'])) {
                $model->savePayablePurchaseDetails($id, isset($_POST['pd']) ? $_POST['pd'] : array());
            }
            // 儲存進退明細
            if (!empty($_POST['rd_rendered'])) {
                $model->savePayableReturnDetails($id, isset($_POST['rd']) ? $_POST['rd'] : array());
            }
            // 儲存發票明細
            if (isset($_POST['invoices'])) {
                $model->savePayableInvoices($id, $_POST['invoices']);
            }

            AuditLog::log('payables', 'update', $id, '更新應付帳款單');
            Session::flash('success', '應付帳款單已更新');
            redirect('/payables.php');
        }

        $branches = $model->getBranches();
        $branchItems = $model->getPayableBranches($record['id']);
        $invoiceItems = $model->getPayableInvoices($record['id']);
        $record['purchase_details'] = $model->getPayablePurchaseDetails($record['id']);
        $record['return_details'] = $model->getPayableReturnDetails($record['id']);

        $pageTitle = '編輯應付帳款單';
        $currentPage = 'payables';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/payables/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除應付帳款 ----
    case 'delete':
        if (!Auth::hasPermission('finance.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/payables.php');
        }
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            AuditLog::log('payables', 'delete', $id, '刪除應付帳款單');
            $model->deletePayable($id);
            Session::flash('success', '應付帳款單已刪除');
        }
        redirect('/payables.php');
        break;

    // ---- AJAX: 廠商搜尋 ----
    case 'ajax_search_vendor':
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 1) { echo json_encode(array()); break; }
        $like = '%' . $q . '%';
        $stmt = Database::getInstance()->prepare("SELECT id, vendor_code, name, short_name, contact_person, phone FROM vendors WHERE is_active = 1 AND (name LIKE ? OR short_name LIKE ? OR vendor_code LIKE ?) ORDER BY name LIMIT 15");
        $stmt->execute(array($like, $like, $like));
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        redirect('/payables.php');
        break;
}
