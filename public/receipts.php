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
            // 嚴格匹配已知帳款類別，避免誤吃使用者直接寫在類別後的內容
            $syncCaseNote = $data['note'] ?? '';
            if ($syncCaseNote !== null && $syncCaseNote !== '') {
                $knownTypes = '訂金|第一期款|第二期款|第三期款|尾款|保留款|全款|退款';
                if (preg_match('/^案件帳款自動產生\s*-\s*(?:' . $knownTypes . ')\s*\/\s*(.*)$/us', $syncCaseNote, $m)) {
                    $syncCaseNote = trim($m[1]);
                } elseif (preg_match('/^案件帳款自動產生\s*-\s*(?:' . $knownTypes . ')\s*$/us', $syncCaseNote)) {
                    // 純系統前綴沒有使用者備註
                    $syncCaseNote = '';
                }
                // 其他情況保留完整備註（使用者可能直接寫在類別後沒用 / 分隔）
            }
            $affectedCaseIds = array();
            try {
                $rnStmt = Database::getInstance()->prepare('SELECT receipt_number FROM receipts WHERE id = ?');
                $rnStmt->execute(array($id));
                $rNum = $rnStmt->fetchColumn();
                if ($rNum) {
                    // 同步含 discount → wire_fee（折讓/匯費）
                    Database::getInstance()->prepare("UPDATE case_payments SET payment_date=?, payment_type=?, transaction_type=?, amount=?, untaxed_amount=?, tax_amount=?, wire_fee=?, note=? WHERE receipt_number=?")
                       ->execute(array(
                           $data['register_date'],
                           $data['invoice_category'],
                           $data['receipt_method'],
                           (int)$data['total_amount'],
                           (int)$data['subtotal'],
                           (int)$data['tax'],
                           (int)($data['discount'] ?? 0),
                           $syncCaseNote,
                           $rNum,
                       ));
                    // 同步後重算對應案件的總收款 + 尾款（要扣折讓/匯費）
                    $caseStmt = Database::getInstance()->prepare('SELECT DISTINCT case_id FROM case_payments WHERE receipt_number = ?');
                    $caseStmt->execute(array($rNum));
                    $affectedCaseIds = array_filter($caseStmt->fetchAll(PDO::FETCH_COLUMN));
                    foreach ($affectedCaseIds as $cid) {
                        $totalStmt = Database::getInstance()->prepare("SELECT COALESCE(SUM(amount),0), COALESCE(SUM(wire_fee),0) FROM case_payments WHERE case_id = ?");
                        $totalStmt->execute(array($cid));
                        $sumRow = $totalStmt->fetch(PDO::FETCH_NUM);
                        $cTotal = (int)$sumRow[0];
                        $cWire  = (int)$sumRow[1];
                        // 訂金合計
                        $depStmt = Database::getInstance()->prepare("SELECT COALESCE(SUM(amount),0) FROM case_payments WHERE case_id = ? AND payment_type = '訂金'");
                        $depStmt->execute(array($cid));
                        $cDeposit = (int)$depStmt->fetchColumn();
                        // 重算尾款
                        $cInfoStmt = Database::getInstance()->prepare("SELECT deal_amount, total_amount FROM cases WHERE id = ?");
                        $cInfoStmt->execute(array($cid));
                        $cInfo = $cInfoStmt->fetch(PDO::FETCH_ASSOC);
                        $base = $cInfo ? ((int)$cInfo['total_amount'] > 0 ? (int)$cInfo['total_amount'] : (int)$cInfo['deal_amount']) : 0;
                        $cBalance = $base > 0 ? max(0, $base - $cTotal - $cWire) : null;
                        Database::getInstance()->prepare("UPDATE cases SET total_collected = ?, deposit_amount = ?, balance_amount = ? WHERE id = ?")
                            ->execute(array($cTotal, $cDeposit ?: null, $cBalance, $cid));
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

            // 自動結清：完工金額 > 0 且 總收款 > 0 且 balance = 0 且尚未結清 → 標已結清
            // 結清日 = 該案件最後一筆 case_payment 日期
            if (!empty($affectedCaseIds)) {
                try {
                    foreach ($affectedCaseIds as $cid) {
                        $stmt = Database::getInstance()->prepare("SELECT total_amount, balance_amount, total_collected, settlement_confirmed FROM cases WHERE id = ?");
                        $stmt->execute(array($cid));
                        $c = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$c) continue;
                        // 自動結清條件：含稅金額 > 0 且 總收款 > 0 且 balance = 0 且尚未結清
                        if ((int)$c['total_amount'] > 0
                            && (int)$c['balance_amount'] === 0
                            && (int)$c['total_collected'] > 0
                            && (int)$c['settlement_confirmed'] !== 1) {
                            // 結清日 = 該案件 case_payments 最大日期
                            $latestStmt = Database::getInstance()->prepare("SELECT MAX(payment_date) FROM case_payments WHERE case_id = ?");
                            $latestStmt->execute(array($cid));
                            $latestPayDate = $latestStmt->fetchColumn();
                            $settleDate = $latestPayDate ?: ($data['register_date'] ?: date('Y-m-d'));
                            Database::getInstance()->prepare("UPDATE cases SET settlement_confirmed = 1, settlement_date = ? WHERE id = ?")
                                ->execute(array($settleDate, $cid));
                        }
                        // 嘗試自動結案（只有狀態改為已收款才觸發，避免每次更新都重複跑）
                        if ($data['status'] === '已收款' && $record['status'] !== '已收款') {
                            tryAutoCloseCase($cid);
                        }
                    }
                } catch (Exception $autoSettleEx) {
                    error_log('Auto-settle on receipt update failed: ' . $autoSettleEx->getMessage());
                }
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
        $_delRole = Auth::user()['role'] ?? '';
        if (!$isBoss && !Auth::hasPermission('finance.delete') && $_delRole !== 'accounting_supervisor') {
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
