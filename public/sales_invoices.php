<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限存取');
    redirect('/');
}
require_once __DIR__ . '/../modules/accounting/InvoiceModel.php';

$model = new InvoiceModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$isBoss = Auth::hasPermission('all');
$canManage = Auth::hasPermission('finance.manage') || $isBoss;

switch ($action) {
    // ---- 銷項發票清單 ----
    case 'list':
        $filters = array(
            'period'       => !empty($_GET['period']) ? $_GET['period'] : '',
            'customer'     => !empty($_GET['customer']) ? $_GET['customer'] : '',
            'status'       => !empty($_GET['status']) ? $_GET['status'] : '',
            'keyword'      => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'invoice_format' => !empty($_GET['invoice_format']) ? $_GET['invoice_format'] : '',
            'invoice_no_from' => !empty($_GET['invoice_no_from']) ? $_GET['invoice_no_from'] : '',
            'invoice_no_to'   => !empty($_GET['invoice_no_to']) ? $_GET['invoice_no_to'] : '',
            // seller_tax_id 預設禾順 94081455；使用者若主動切到「全部賣方」會送空字串
            'seller_tax_id'   => isset($_GET['seller_tax_id']) ? $_GET['seller_tax_id'] : '94081455',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getSalesInvoices($filters, $page);
        $records = $result['data'];
        $periodOptions = $model->getPeriodOptions();
        $customers = $model->getCustomers();

        $pageTitle = '銷項發票管理';
        $currentPage = 'sales_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/sales_invoices_list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增銷項發票 ----
    case 'create':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/sales_invoices.php');
        }
        // 從案件管理過來時 case_id + return=case
        $fromCaseId = isset($_REQUEST['case_id']) ? (int)$_REQUEST['case_id'] : 0;
        $returnTo = isset($_REQUEST['return']) ? $_REQUEST['return'] : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/sales_invoices.php');
            }
            $userId = Session::getUser()['id'];
            // 若是從案件來，強制設定 reference_type/id
            $refType = !empty($_POST['reference_type']) ? $_POST['reference_type'] : null;
            $refId = !empty($_POST['reference_id']) ? $_POST['reference_id'] : null;
            if ($fromCaseId > 0) {
                $refType = 'case';
                $refId = $fromCaseId;
            }
            $data = array(
                'invoice_number'  => !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null,
                'invoice_date'    => !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'),
                'customer_name'   => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'customer_tax_id' => !empty($_POST['customer_tax_id']) ? $_POST['customer_tax_id'] : null,
                'seller_tax_id'   => !empty($_POST['seller_tax_id']) ? $_POST['seller_tax_id'] : null,
                'seller_name'     => !empty($_POST['seller_name']) ? $_POST['seller_name'] : null,
                'invoice_type'    => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : '三聯式',
                'amount_untaxed'  => isset($_POST['amount_untaxed']) ? $_POST['amount_untaxed'] : 0,
                'tax_amount'      => isset($_POST['tax_amount']) ? $_POST['tax_amount'] : null,
                'total_amount'    => isset($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'tax_rate'        => isset($_POST['tax_rate']) ? $_POST['tax_rate'] : 5,
                'report_period'   => !empty($_POST['report_period']) ? $_POST['report_period'] : null,
                'invoice_format'  => !empty($_POST['invoice_format']) ? $_POST['invoice_format'] : null,
                'status'          => !empty($_POST['status']) ? $_POST['status'] : 'pending',
                'reference_type'  => $refType,
                'reference_id'    => $refId,
                'note'            => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'      => $userId,
            );
            try {
                $invoiceId = $model->createSalesInvoice($data);
            } catch (Exception $e) {
                Session::flash('error', $e->getMessage());
                // 保留欄位資料供回填
                Session::set('sales_invoice_form_data', $_POST);
                $postReturn = !empty($_POST['return']) ? $_POST['return'] : '';
                $postCaseId = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
                $back = '/sales_invoices.php?action=create';
                if ($postReturn === 'case' && $postCaseId > 0) {
                    $back .= '&case_id=' . $postCaseId . '&return=case';
                }
                redirect($back);
            }
            AuditLog::log('sales_invoices', 'create', $invoiceId, '新增銷項發票');

            // Auto-journal on sales invoice create
            try {
                require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
                AutoJournalService::onSalesInvoiceCreated($invoiceId);
            } catch (Exception $autoJournalEx) {
                error_log('AutoJournal sales_invoice error: ' . $autoJournalEx->getMessage());
            }

            Session::flash('success', '銷項發票已新增');
            // 從案件來的，跳回案件編輯頁
            $postReturn = !empty($_POST['return']) ? $_POST['return'] : '';
            $postCaseId = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
            if ($postReturn === 'case' && $postCaseId > 0) {
                redirect('/cases.php?action=edit&id=' . $postCaseId);
            }
            redirect('/sales_invoices.php');
        }

        $record = null;
        $customers = $model->getCustomers();

        // 從案件來時，預先帶入客戶資料
        $prefillCustomerName = '';
        $prefillCustomerTaxId = '';
        if ($fromCaseId > 0) {
            $caseStmt = Database::getInstance()->prepare("SELECT billing_title, billing_tax_id FROM cases WHERE id = ?");
            $caseStmt->execute(array($fromCaseId));
            $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);
            if ($caseRow) {
                $prefillCustomerName = $caseRow['billing_title'];
                $prefillCustomerTaxId = $caseRow['billing_tax_id'];
            }
        }

        $pageTitle = '新增銷項發票';
        $currentPage = 'sales_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/sales_invoice_form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯銷項發票 ----
    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getSalesInvoiceById($id);
        if (!$record) {
            Session::flash('error', '銷項發票不存在');
            redirect('/sales_invoices.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManage) {
                Session::flash('error', '無權限執行此操作');
                redirect('/sales_invoices.php');
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/sales_invoices.php?action=edit&id=' . $id);
            }
            $data = array(
                'invoice_number'  => !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null,
                'invoice_date'    => !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'),
                'customer_name'   => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'customer_tax_id' => !empty($_POST['customer_tax_id']) ? $_POST['customer_tax_id'] : null,
                'seller_tax_id'   => !empty($_POST['seller_tax_id']) ? $_POST['seller_tax_id'] : null,
                'seller_name'     => !empty($_POST['seller_name']) ? $_POST['seller_name'] : null,
                'invoice_type'    => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : '三聯式',
                'amount_untaxed'  => isset($_POST['amount_untaxed']) ? $_POST['amount_untaxed'] : 0,
                'tax_amount'      => isset($_POST['tax_amount']) ? $_POST['tax_amount'] : null,
                'total_amount'    => isset($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'tax_rate'        => isset($_POST['tax_rate']) ? $_POST['tax_rate'] : 5,
                'report_period'   => !empty($_POST['report_period']) ? $_POST['report_period'] : null,
                'invoice_format'  => !empty($_POST['invoice_format']) ? $_POST['invoice_format'] : null,
                'reference_type'  => !empty($_POST['reference_type']) ? $_POST['reference_type'] : null,
                'reference_id'    => !empty($_POST['reference_id']) ? $_POST['reference_id'] : null,
                'status'          => !empty($_POST['status']) ? $_POST['status'] : 'pending',
                'note'            => !empty($_POST['note']) ? $_POST['note'] : null,
            );
            try {
                $model->updateSalesInvoice($id, $data);
            } catch (Exception $e) {
                Session::flash('error', $e->getMessage());
                Session::set('sales_invoice_form_data', $_POST);
                $postReturn = !empty($_POST['return']) ? $_POST['return'] : '';
                $postCaseId = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
                $back = '/sales_invoices.php?action=edit&id=' . $id;
                if ($postReturn === 'case' && $postCaseId > 0) {
                    $back .= '&case_id=' . $postCaseId . '&return=case';
                }
                redirect($back);
            }
            AuditLog::log('sales_invoices', 'update', $id, '更新銷項發票');
            Session::flash('success', '銷項發票已更新');
            // 若是從案件管理過來的，跳回案件編輯頁
            $postReturn = !empty($_POST['return']) ? $_POST['return'] : '';
            $postCaseId = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
            if ($postReturn === 'case' && $postCaseId > 0) {
                redirect('/cases.php?action=edit&id=' . $postCaseId);
            }
            redirect('/sales_invoices.php');
        }

        $customers = $model->getCustomers();
        // GET 模式時也要支援 case_id + return 參數（編輯頁從案件來時用）
        $fromCaseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
        $returnTo = isset($_GET['return']) ? $_GET['return'] : '';
        $prefillCustomerName = '';
        $prefillCustomerTaxId = '';

        // 編輯鎖定
        require_once __DIR__ . '/../includes/EditingLock.php';
        $curUser = Auth::user();
        if ($curUser) EditingLock::set('sales_invoices', $id, $curUser['id'], $curUser['real_name']);
        $otherEditors = EditingLock::getOthers('sales_invoices', $id, Auth::id());

        $pageTitle = '編輯銷項發票 - ' . $record['invoice_number'];
        $currentPage = 'sales_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/sales_invoice_form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除銷項發票 ----
    case 'delete':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/sales_invoices.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/sales_invoices.php');
            }
            $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
            if ($id) {
                try {
                    $model->deleteSalesInvoice($id);
                    AuditLog::log('sales_invoices', 'delete', $id, '刪除銷項發票');
                    Session::flash('success', '銷項發票已刪除');
                } catch (Exception $ex) {
                    Session::flash('error', $ex->getMessage());
                }
            }
        }
        redirect('/sales_invoices.php');
        break;

    // ---- 作廢銷項發票 ----
    case 'void':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/sales_invoices.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/sales_invoices.php');
            }
            $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
            if ($id) {
                $model->voidSalesInvoice($id);
                AuditLog::log('sales_invoices', 'void', $id, '作廢銷項發票');
                Session::flash('success', '銷項發票已作廢');
            }
        }
        redirect('/sales_invoices.php');
        break;

    case 'unconfirm':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/sales_invoices.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/sales_invoices.php');
            }
            $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
            if ($id) {
                $db = Database::getInstance();
                $db->prepare("UPDATE sales_invoices SET status = 'pending' WHERE id = ? AND status = 'confirmed'")->execute(array($id));
                AuditLog::log('sales_invoices', 'unconfirm', $id, '取消確認銷項發票');
                Session::flash('success', '已取消確認');
                redirect('/sales_invoices.php?action=edit&id=' . $id);
            }
        }
        redirect('/sales_invoices.php');
        break;

    case 'toggle_star':
        header('Content-Type: application/json; charset=utf-8');
        if (!$canManage) { echo json_encode(array('error' => '無權限')); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error' => '方法錯誤')); exit; }
        $sid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($sid <= 0) { echo json_encode(array('error' => '參數錯誤')); exit; }
        try {
            $db = Database::getInstance();
            $cur = $db->prepare("SELECT is_starred FROM sales_invoices WHERE id = ?");
            $cur->execute(array($sid));
            $c = $cur->fetchColumn();
            if ($c === false) { echo json_encode(array('error' => '記錄不存在')); exit; }
            $new = ((int)$c === 1) ? 0 : 1;
            $db->prepare("UPDATE sales_invoices SET is_starred = ? WHERE id = ?")->execute(array($new, $sid));
            echo json_encode(array('success' => true, 'starred' => $new));
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;

    default:
        redirect('/sales_invoices.php');
        break;
}
