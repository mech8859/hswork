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
    // ---- 進項發票清單 ----
    case 'list':
        $filters = array(
            'period'         => !empty($_GET['period']) ? $_GET['period'] : '',
            'vendor'         => !empty($_GET['vendor']) ? $_GET['vendor'] : '',
            'status'         => !empty($_GET['status']) ? $_GET['status'] : '',
            'keyword'        => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'invoice_type'   => !empty($_GET['invoice_type']) ? $_GET['invoice_type'] : '',
            'deduction_type' => !empty($_GET['deduction_type']) ? $_GET['deduction_type'] : '',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getPurchaseInvoices($filters, $page);
        $records = $result['data'];
        $periodOptions = $model->getPeriodOptions();
        $vendors = $model->getVendors();

        $pageTitle = '進項發票管理';
        $currentPage = 'purchase_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/purchase_invoices_list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增進項發票 ----
    case 'create':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/purchase_invoices.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/purchase_invoices.php');
            }
            $userId = Session::getUser()['id'];
            $data = array(
                'invoice_number'     => !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null,
                'invoice_date'       => !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'),
                'vendor_id'          => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_name'        => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'vendor_tax_id'      => !empty($_POST['vendor_tax_id']) ? $_POST['vendor_tax_id'] : null,
                'invoice_type'       => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : '應稅',
                'amount_untaxed'     => isset($_POST['amount_untaxed']) ? $_POST['amount_untaxed'] : 0,
                'tax_amount'         => isset($_POST['tax_amount']) ? $_POST['tax_amount'] : null,
                'total_amount'       => isset($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'tax_rate'           => isset($_POST['tax_rate']) ? $_POST['tax_rate'] : 5,
                'deduction_type'     => !empty($_POST['deduction_type']) ? $_POST['deduction_type'] : 'deductible',
                'report_period'      => !empty($_POST['report_period']) ? $_POST['report_period'] : null,
                'invoice_format'     => !empty($_POST['invoice_format']) ? $_POST['invoice_format'] : null,
                'deduction_category' => !empty($_POST['deduction_category']) ? $_POST['deduction_category'] : null,
                'status'             => !empty($_POST['status']) ? $_POST['status'] : 'pending',
                'reference_type'     => !empty($_POST['reference_type']) ? $_POST['reference_type'] : null,
                'reference_id'       => !empty($_POST['reference_id']) ? $_POST['reference_id'] : null,
                'note'               => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'         => $userId,
            );
            $invoiceId = $model->createPurchaseInvoice($data);
            AuditLog::log('purchase_invoices', 'create', $invoiceId, '新增進項發票');

            // Auto-journal on purchase invoice create
            try {
                require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
                AutoJournalService::onPurchaseInvoiceCreated($invoiceId);
            } catch (Exception $autoJournalEx) {
                error_log('AutoJournal purchase_invoice error: ' . $autoJournalEx->getMessage());
            }

            Session::flash('success', '進項發票已新增');
            redirect('/purchase_invoices.php');
        }

        $record = null;
        $vendors = $model->getVendors();

        $pageTitle = '新增進項發票';
        $currentPage = 'purchase_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/purchase_invoice_form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯進項發票 ----
    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getPurchaseInvoiceById($id);
        if (!$record) {
            Session::flash('error', '進項發票不存在');
            redirect('/purchase_invoices.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManage) {
                Session::flash('error', '無權限執行此操作');
                redirect('/purchase_invoices.php');
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/purchase_invoices.php?action=edit&id=' . $id);
            }
            $data = array(
                'invoice_number'     => !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : null,
                'invoice_date'       => !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'),
                'vendor_id'          => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_name'        => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'vendor_tax_id'      => !empty($_POST['vendor_tax_id']) ? $_POST['vendor_tax_id'] : null,
                'invoice_type'       => !empty($_POST['invoice_type']) ? $_POST['invoice_type'] : '應稅',
                'amount_untaxed'     => isset($_POST['amount_untaxed']) ? $_POST['amount_untaxed'] : 0,
                'tax_amount'         => isset($_POST['tax_amount']) ? $_POST['tax_amount'] : null,
                'total_amount'       => isset($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'tax_rate'           => isset($_POST['tax_rate']) ? $_POST['tax_rate'] : 5,
                'deduction_type'     => !empty($_POST['deduction_type']) ? $_POST['deduction_type'] : 'deductible',
                'report_period'      => !empty($_POST['report_period']) ? $_POST['report_period'] : null,
                'invoice_format'     => !empty($_POST['invoice_format']) ? $_POST['invoice_format'] : null,
                'deduction_category' => !empty($_POST['deduction_category']) ? $_POST['deduction_category'] : null,
                'reference_type'     => !empty($_POST['reference_type']) ? $_POST['reference_type'] : null,
                'reference_id'       => !empty($_POST['reference_id']) ? $_POST['reference_id'] : null,
                'status'             => !empty($_POST['status']) ? $_POST['status'] : 'pending',
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
            );
            $model->updatePurchaseInvoice($id, $data);
            AuditLog::log('purchase_invoices', 'update', $id, '更新進項發票');
            Session::flash('success', '進項發票已更新');
            redirect('/purchase_invoices.php');
        }

        $vendors = $model->getVendors();

        // 編輯鎖定
        require_once __DIR__ . '/../includes/EditingLock.php';
        $curUser = Auth::user();
        if ($curUser) EditingLock::set('purchase_invoices', $id, $curUser['id'], $curUser['real_name']);
        $otherEditors = EditingLock::getOthers('purchase_invoices', $id, Auth::id());

        $pageTitle = '編輯進項發票 - ' . $record['invoice_number'];
        $currentPage = 'purchase_invoices';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/accounting/purchase_invoice_form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除進項發票 ----
    case 'delete':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/purchase_invoices.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/purchase_invoices.php');
            }
            $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
            if ($id) {
                try {
                    $model->deletePurchaseInvoice($id);
                    AuditLog::log('purchase_invoices', 'delete', $id, '刪除進項發票');
                    Session::flash('success', '進項發票已刪除');
                } catch (Exception $ex) {
                    Session::flash('error', $ex->getMessage());
                }
            }
        }
        redirect('/purchase_invoices.php');
        break;

    // ---- 作廢進項發票 ----
    case 'void':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/purchase_invoices.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/purchase_invoices.php');
            }
            $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
            if ($id) {
                $model->voidPurchaseInvoice($id);
                AuditLog::log('purchase_invoices', 'void', $id, '作廢進項發票');
                Session::flash('success', '進項發票已作廢');
            }
        }
        redirect('/purchase_invoices.php');
        break;

    case 'unconfirm':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/purchase_invoices.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/purchase_invoices.php');
            }
            $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
            if ($id) {
                $db = Database::getInstance();
                $db->prepare("UPDATE purchase_invoices SET status = 'pending' WHERE id = ? AND status = 'confirmed'")->execute(array($id));
                AuditLog::log('purchase_invoices', 'unconfirm', $id, '取消確認進項發票');
                Session::flash('success', '已取消確認');
                redirect('/purchase_invoices.php?action=edit&id=' . $id);
            }
        }
        redirect('/purchase_invoices.php');
        break;

    default:
        redirect('/purchase_invoices.php');
        break;
}
