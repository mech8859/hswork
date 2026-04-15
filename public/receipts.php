<?php
/**
 * 收款單管理
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$receiptReadonly = false;

if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    // 無財務權限：僅允許檢視自己案件的收款單 或 收到通知的收款單（唯讀）
    if ($action === 'edit' && !empty($_GET['id'])) {
        $_db = Database::getInstance();
        $_rid = (int)$_GET['id'];
        $_rCheck = $_db->prepare("SELECT sales_id FROM receipts WHERE id = ?");
        $_rCheck->execute(array($_rid));
        $_rSalesId = $_rCheck->fetchColumn();
        // 自己的案件 或 有該收款單的通知 → 允許唯讀
        $_hasNotif = $_db->prepare("SELECT 1 FROM notifications WHERE user_id = ? AND related_type = 'receipts' AND related_id = ? LIMIT 1");
        $_hasNotif->execute(array(Auth::id(), $_rid));
        if (($_rSalesId && (int)$_rSalesId === (int)Auth::id()) || $_hasNotif->fetchColumn()) {
            $receiptReadonly = true;
        } else {
            Session::flash('error', '無權限存取');
            redirect('/');
        }
    } else {
        Session::flash('error', '無權限存取');
        redirect('/');
    }
}
require_once __DIR__ . '/../modules/finance/FinanceModel.php';
require_once __DIR__ . '/../modules/notifications/NotificationModel.php';

$model = new FinanceModel();
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
            'date_type' => !empty($_GET['date_type']) ? $_GET['date_type'] : 'register',
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
                'case_number'      => !empty($_POST['case_number']) ? $_POST['case_number'] : null,
                'customer_no'      => !empty($_POST['customer_no']) ? $_POST['customer_no'] : null,
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
                'registrar'        => Session::getUser()['real_name'] ?? null,
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
                'case_number'      => !empty($_POST['case_number']) ? $_POST['case_number'] : null,
                'customer_no'      => !empty($_POST['customer_no']) ? $_POST['customer_no'] : null,
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

            // 同步更新對應的案件帳款交易（用 receipt_number 反查）
            // 備註剝掉「案件帳款自動產生 - {類別} / 」系統前綴，只同步使用者部分
            $syncCaseNote = $data['note'] ?? '';
            if ($syncCaseNote !== null && $syncCaseNote !== '') {
                if (preg_match('/^案件帳款自動產生\s*-\s*[^\/]*?\s*\/\s*(.*)$/us', $syncCaseNote, $m)) {
                    $syncCaseNote = trim($m[1]);
                } elseif (preg_match('/^案件帳款自動產生\s*-\s*[^\/]*$/us', $syncCaseNote)) {
                    // 只有前綴沒有使用者備註
                    $syncCaseNote = '';
                }
            }
            try {
                $rnStmt = Database::getInstance()->prepare('SELECT receipt_number FROM receipts WHERE id = ?');
                $rnStmt->execute(array($id));
                $rNum = $rnStmt->fetchColumn();
                if ($rNum) {
                    Database::getInstance()->prepare("UPDATE case_payments SET payment_date=?, payment_type=?, transaction_type=?, amount=?, untaxed_amount=?, tax_amount=?, note=? WHERE receipt_number=?")
                       ->execute(array(
                           $data['register_date'],
                           $data['invoice_category'],
                           $data['receipt_method'],
                           (int)$data['total_amount'],
                           (int)$data['subtotal'],
                           (int)$data['tax'],
                           $syncCaseNote,
                           $rNum,
                       ));
                    // 同步後重算對應案件的總收款
                    $caseStmt = Database::getInstance()->prepare('SELECT DISTINCT case_id FROM case_payments WHERE receipt_number = ?');
                    $caseStmt->execute(array($rNum));
                    foreach ($caseStmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                        if ($cid) {
                            // 直接呼叫 case 模組的 updateTotalCollected
                            $totalStmt = Database::getInstance()->prepare("SELECT COALESCE(SUM(amount),0) FROM case_payments WHERE case_id = ?");
                            $totalStmt->execute(array($cid));
                            $cTotal = (int)$totalStmt->fetchColumn();
                            Database::getInstance()->prepare("UPDATE cases SET total_collected = ? WHERE id = ?")->execute(array($cTotal, $cid));
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('sync case_payment from receipt failed: ' . $e->getMessage());
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

        // 編輯鎖定（多人同時編輯提醒）
        require_once __DIR__ . '/../includes/EditingLock.php';
        $_curUser = Auth::user();
        if ($_curUser && $id > 0) EditingLock::set('receipts', $id, $_curUser['id'], $_curUser['real_name']);
        $otherEditors = ($id > 0) ? EditingLock::getOthers('receipts', $id, Auth::id()) : array();
        $editingLockModule = 'receipts';
        $editingLockRecordId = $id;

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

    case 'toggle_star':
        header('Content-Type: application/json; charset=utf-8');
        if (!$canManageFinance && !$isBoss) { echo json_encode(array('error' => '無權限')); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error' => '方法錯誤')); exit; }
        $sid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($sid <= 0) { echo json_encode(array('error' => '參數錯誤')); exit; }
        try {
            $db = Database::getInstance();
            $cur = $db->prepare("SELECT is_starred FROM receipts WHERE id = ?");
            $cur->execute(array($sid));
            $c = $cur->fetchColumn();
            if ($c === false) { echo json_encode(array('error' => '記錄不存在')); exit; }
            $new = ((int)$c === 1) ? 0 : 1;
            $db->prepare("UPDATE receipts SET is_starred = ? WHERE id = ?")->execute(array($new, $sid));
            echo json_encode(array('success' => true, 'starred' => $new));
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;
}
