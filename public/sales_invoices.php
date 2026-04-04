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
            'invoice_type' => !empty($_GET['invoice_type']) ? $_GET['invoice_type'] : '',
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/sales_invoices.php');
            }
            $userId = Session::getUser()['id'];
            $data = array(
                'invoice_number'  => !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null,
                'invoice_date'    => !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'),
                'customer_name'   => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'customer_tax_id' => !empty($_POST['customer_tax_id']) ? $_POST['customer_tax_id'] : null,
                'invoice_type'    => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : '三聯式',
                'amount_untaxed'  => isset($_POST['amount_untaxed']) ? $_POST['amount_untaxed'] : 0,
                'tax_amount'      => isset($_POST['tax_amount']) ? $_POST['tax_amount'] : null,
                'total_amount'    => isset($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'tax_rate'        => isset($_POST['tax_rate']) ? $_POST['tax_rate'] : 5,
                'report_period'   => !empty($_POST['report_period']) ? $_POST['report_period'] : null,
                'invoice_format'  => !empty($_POST['invoice_format']) ? $_POST['invoice_format'] : null,
                'status'          => !empty($_POST['status']) ? $_POST['status'] : 'pending',
                'reference_type'  => !empty($_POST['reference_type']) ? $_POST['reference_type'] : null,
                'reference_id'    => !empty($_POST['reference_id']) ? $_POST['reference_id'] : null,
                'note'            => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'      => $userId,
            );
            $invoiceId = $model->createSalesInvoice($data);
            AuditLog::log('sales_invoices', 'create', $invoiceId, '新增銷項發票');

            // Auto-journal on sales invoice create
            try {
                require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
                AutoJournalService::onSalesInvoiceCreated($invoiceId);
            } catch (Exception $autoJournalEx) {
                error_log('AutoJournal sales_invoice error: ' . $autoJournalEx->getMessage());
            }

            Session::flash('success', '銷項發票已新增');
            redirect('/sales_invoices.php');
        }

        $record = null;
        $customers = $model->getCustomers();

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
            $model->updateSalesInvoice($id, $data);
            AuditLog::log('sales_invoices', 'update', $id, '更新銷項發票');
            Session::flash('success', '銷項發票已更新');
            redirect('/sales_invoices.php');
        }

        $customers = $model->getCustomers();

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

    default:
        redirect('/sales_invoices.php');
        break;
}
