<?php
/**
 * 收款單管理
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限存取');
    redirect('/');
}
require_once __DIR__ . '/../modules/finance/FinanceModel.php';
require_once __DIR__ . '/../modules/notifications/NotificationModel.php';

$model = new FinanceModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();
$userId = Session::getUser()['id'];
$isBoss = Auth::hasPermission('all');
$canManageFinance = Auth::hasPermission('finance.manage');

switch ($action) {
    case 'list':
    default:
        $filters = array(
            'status'    => !empty($_GET['status']) ? $_GET['status'] : '',
            'keyword'   => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from' => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'   => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'sort'      => !empty($_GET['sort']) ? $_GET['sort'] : 'desc',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getReceipts($filters, $branchIds, $page);
        $receipts = $result['data'];
        $pageTitle = '收款單';
        $currentPage = 'receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/receipts/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/receipts.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/receipts.php');
            }
            $data = array(
                'register_date'    => !empty($_POST['register_date']) ? $_POST['register_date'] : date('Y-m-d'),
                'deposit_date'     => !empty($_POST['deposit_date']) ? $_POST['deposit_date'] : null,
                'customer_name'    => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'case_id'          => !empty($_POST['case_id']) ? $_POST['case_id'] : null,
                'sales_id'         => !empty($_POST['sales_id']) ? $_POST['sales_id'] : null,
                'branch_id'        => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'receipt_method'   => !empty($_POST['receipt_method']) ? $_POST['receipt_method'] : null,
                'invoice_category' => !empty($_POST['invoice_category']) ? $_POST['invoice_category'] : null,
                'status'           => !empty($_POST['status']) ? $_POST['status'] : '待收款',
                'bank_ref'         => !empty($_POST['bank_ref']) ? $_POST['bank_ref'] : null,
                'subtotal'         => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax'              => !empty($_POST['tax']) ? $_POST['tax'] : 0,
                'discount'         => !empty($_POST['discount']) ? $_POST['discount'] : 0,
                'total_amount'     => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'note'             => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'       => $userId,
            );
            $receiptId = $model->createReceipt($data);
            if (!empty($_POST['items'])) {
                $model->saveReceiptItems($receiptId, $_POST['items']);
            }
            // 通知分派（依 notification_settings 規則）
            require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
            $data['id'] = $receiptId;
            NotificationDispatcher::dispatch('receipts', 'created', $data, $userId);

            AuditLog::log('receipts', 'create', $receiptId, '新增收款單');
            Session::flash('success', '收款單已建立');
            redirect('/receipts.php');
        }
        $record = null;
        $items = array();
        $branches = $model->getBranches($branchIds);
        $salesUsers = $model->getSalesUsers($branchIds);
        $pageTitle = '新增收款單';
        $currentPage = 'receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/receipts/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getReceipt($id);
        if (!$record) {
            Session::flash('error', '收款單不存在');
            redirect('/receipts.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManageFinance && !$isBoss) {
                Session::flash('error', '無權限執行此操作');
                redirect('/receipts.php');
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/receipts.php?action=edit&id=' . $id);
            }
            $data = array(
                'register_date'    => !empty($_POST['register_date']) ? $_POST['register_date'] : date('Y-m-d'),
                'deposit_date'     => !empty($_POST['deposit_date']) ? $_POST['deposit_date'] : null,
                'customer_name'    => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'case_id'          => !empty($_POST['case_id']) ? $_POST['case_id'] : null,
                'sales_id'         => !empty($_POST['sales_id']) ? $_POST['sales_id'] : null,
                'branch_id'        => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'receipt_method'   => !empty($_POST['receipt_method']) ? $_POST['receipt_method'] : null,
                'invoice_category' => !empty($_POST['invoice_category']) ? $_POST['invoice_category'] : null,
                'status'           => !empty($_POST['status']) ? $_POST['status'] : '待收款',
                'bank_ref'         => !empty($_POST['bank_ref']) ? $_POST['bank_ref'] : null,
                'subtotal'         => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax'              => !empty($_POST['tax']) ? $_POST['tax'] : 0,
                'discount'         => !empty($_POST['discount']) ? $_POST['discount'] : 0,
                'total_amount'     => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'note'             => !empty($_POST['note']) ? $_POST['note'] : null,
                'updated_by'       => $userId,
            );
            $model->updateReceipt($id, $data);
            if (isset($_POST['items'])) {
                $model->saveReceiptItems($id, $_POST['items']);
            }
            AuditLog::log('receipts', 'update', $id, '更新收款單');

            // 通知分派：狀態變更時依規則通知
            if ($data['status'] !== $record['status']) {
                require_once __DIR__ . '/../modules/notifications/NotificationDispatcher.php';
                $data['id'] = $id;
                NotificationDispatcher::dispatch('receipts', 'status_changed', $data, $userId, $record);
            }

            // Auto-journal: when status changes to 已入帳
            if ($data['status'] === '已入帳' && $record['status'] !== '已入帳') {
                try {
                    require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
                    AutoJournalService::onReceiptConfirmed($id);
                } catch (Exception $autoJournalEx) {
                    // Don't break main flow
                    error_log('AutoJournal receipt error: ' . $autoJournalEx->getMessage());
                }
            }

            Session::flash('success', '收款單已更新');
            redirect('/receipts.php');
        }
        $items = $model->getReceiptItems($record['id']);
        $branches = $model->getBranches($branchIds);
        $salesUsers = $model->getSalesUsers($branchIds);
        $pageTitle = '編輯收款單';
        $currentPage = 'receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/receipts/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'delete':
        if (!$isBoss && !Auth::hasPermission('finance.delete')) {
            Session::flash('error', '無權限執行此操作');
            redirect('/receipts.php');
        }
        if (verify_csrf()) {
            $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
            AuditLog::log('receipts', 'delete', $id, '刪除收款單');
            $model->deleteReceipt($id);
            Session::flash('success', '收款單已刪除');
        }
        redirect('/receipts.php');
        break;
}
