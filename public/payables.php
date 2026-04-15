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

/**
 * 檢查應付帳款發票明細重複
 * @return string 空字串=OK；非空=錯誤訊息
 */
function checkPayableInvoiceDuplicates($invoices, $excludePayableId = 0)
{
    if (empty($invoices) || !is_array($invoices)) return '';
    // 1. 表單內重複
    $seen = array();
    $formDup = array();
    foreach ($invoices as $inv) {
        $num = isset($inv['invoice_number']) ? trim(strtoupper($inv['invoice_number'])) : '';
        if ($num === '') continue;
        if (isset($seen[$num])) $formDup[$num] = true;
        $seen[$num] = true;
    }
    if (!empty($formDup)) {
        return '發票明細內有重複的發票號碼：' . implode('、', array_keys($formDup));
    }
    // 2. 全系統（排除自己這張）
    $nums = array_keys($seen);
    if (empty($nums)) return '';
    $db = Database::getInstance();
    $ph = implode(',', array_fill(0, count($nums), '?'));
    $sql = "SELECT pi.invoice_number, p.payable_number
            FROM payable_invoices pi
            JOIN payables p ON pi.payable_id = p.id
            WHERE pi.invoice_number IN ($ph)";
    $params = $nums;
    if ($excludePayableId > 0) {
        $sql .= " AND pi.payable_id <> ?";
        $params[] = $excludePayableId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $dups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($dups)) {
        $msg = array();
        foreach ($dups as $d) {
            $msg[] = $d['invoice_number'] . '（已於 ' . $d['payable_number'] . ' 使用）';
        }
        return '以下發票號碼已存在於其他應付帳款單：' . implode('、', $msg);
    }
    return '';
}

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

            // 發票號碼重複檢查（form 內 + 全系統）
            $invDupError = checkPayableInvoiceDuplicates($_POST['invoices'] ?? array(), 0);
            if ($invDupError) {
                Session::flash('error', $invDupError);
                redirect('/payables.php?action=create');
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'create_date'    => !empty($_POST['create_date']) ? $_POST['create_date'] : date('Y-m-d'),
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'vendor_code'    => !empty($_POST['vendor_code']) ? $_POST['vendor_code'] : null,
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
            // 儲存發票明細（全刪光時仍要呼叫才能清掉 DB 舊資料）
            if (!empty($_POST['invoices_rendered'])) {
                $model->savePayableInvoices($payableId, isset($_POST['invoices']) ? $_POST['invoices'] : array());
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
            // 鎖定保護：已生成付款單 → 不可修改
            if (!empty($record['payment_out_id'])) {
                $poStmt = Database::getInstance()->prepare("SELECT payment_number FROM payments_out WHERE id = ?");
                $poStmt->execute(array($record['payment_out_id']));
                $poNum = $poStmt->fetchColumn() ?: '';
                Session::flash('error', '此應付帳款已生成付款單 ' . $poNum . '，請先刪除該付款單才能修改');
                redirect('/payables.php?action=edit&id=' . $id);
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/payables.php?action=edit&id=' . $id);
            }

            // 發票號碼重複檢查（form 內 + 全系統，排除自己）
            $invDupError = checkPayableInvoiceDuplicates($_POST['invoices'] ?? array(), $id);
            if ($invDupError) {
                Session::flash('error', $invDupError);
                redirect('/payables.php?action=edit&id=' . $id);
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'create_date'    => !empty($_POST['create_date']) ? $_POST['create_date'] : date('Y-m-d'),
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'vendor_code'    => !empty($_POST['vendor_code']) ? $_POST['vendor_code'] : null,
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
            // 儲存發票明細（全刪光時仍要呼叫才能清掉 DB 舊資料）
            if (!empty($_POST['invoices_rendered'])) {
                $model->savePayableInvoices($id, isset($_POST['invoices']) ? $_POST['invoices'] : array());
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

        // 編輯鎖定（多人同時編輯提醒）
        require_once __DIR__ . '/../includes/EditingLock.php';
        $_curUser = Auth::user();
        if ($_curUser && $id > 0) EditingLock::set('payables', $id, $_curUser['id'], $_curUser['real_name']);
        $otherEditors = ($id > 0) ? EditingLock::getOthers('payables', $id, Auth::id()) : array();
        $editingLockModule = 'payables';
        $editingLockRecordId = $id;

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
            // 鎖定保護
            $delRecord = $model->getPayable($id);
            if ($delRecord && !empty($delRecord['payment_out_id'])) {
                $poStmt = Database::getInstance()->prepare("SELECT payment_number FROM payments_out WHERE id = ?");
                $poStmt->execute(array($delRecord['payment_out_id']));
                $poNum = $poStmt->fetchColumn() ?: '';
                Session::flash('error', '此應付帳款已生成付款單 ' . $poNum . '，請先刪除該付款單才能刪除應付帳款');
                redirect('/payables.php');
            }
            AuditLog::log('payables', 'delete', $id, '刪除應付帳款單');
            $model->deletePayable($id);
            Session::flash('success', '應付帳款單已刪除');
        }
        redirect('/payables.php');
        break;

    // ---- 生成付款單（從應付帳款）----
    case 'generate_payment':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/payables.php');
        }
        if (!verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/payables.php');
        }
        $payableId = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getPayable($payableId);
        if (!$record) {
            Session::flash('error', '應付帳款單不存在');
            redirect('/payables.php');
        }
        // 1:1 保護
        if (!empty($record['payment_out_id'])) {
            $poStmt = Database::getInstance()->prepare("SELECT payment_number FROM payments_out WHERE id = ?");
            $poStmt->execute(array($record['payment_out_id']));
            $poNum = $poStmt->fetchColumn() ?: '';
            Session::flash('error', '此應付帳款已生成付款單 ' . $poNum . '，如需重新生成請先刪除該付款單');
            redirect('/payables.php?action=edit&id=' . $payableId);
        }
        try {
            $db = Database::getInstance();
            $userId = Session::getUser()['id'];
            $userName = Session::getUser()['real_name'] ?? null;

            // 建立付款單（草稿/待付款狀態）
            $poData = array(
                'create_date'    => date('Y-m-d'),
                'payment_date'   => null, // 空白，讓會計填
                'payable_id'     => $payableId,
                'vendor_name'    => $record['vendor_name'],
                'vendor_code'    => $record['vendor_code'],
                'payment_method' => null,
                'payment_type'   => null,
                'payment_terms'  => $record['payment_terms'],
                'status'         => '待付款',
                'subtotal'       => $record['subtotal'],
                'tax'            => $record['tax'],
                'remittance_fee' => 0,
                'total_amount'   => $record['payable_amount'] ?: $record['total_amount'],
                'main_category'  => null,
                'sub_category'   => null,
                'note'           => '由應付帳款 ' . $record['payable_number'] . ' 生成',
                'registrar'      => $userName,
                'created_by'     => $userId,
            );
            $paymentOutId = $model->createPaymentOut($poData);

            // 複製分公司拆帳到付款單
            $branchItems = $model->getPayableBranches($payableId);
            if (!empty($branchItems)) {
                $branchData = array();
                foreach ($branchItems as $idx => $b) {
                    $branchData[$idx] = array(
                        'branch_id' => $b['branch_id'],
                        'amount'    => $b['amount'],
                        'note'      => $b['note'] ?? '',
                    );
                }
                $model->savePaymentOutBranches($paymentOutId, $branchData);
            }

            // 鎖定應付帳款
            $db->prepare("UPDATE payables SET payment_out_id = ? WHERE id = ?")
               ->execute(array($paymentOutId, $payableId));

            AuditLog::log('payables', 'generate_payment', $payableId, '從應付帳款 ' . $record['payable_number'] . ' 生成付款單');
            Session::flash('success', '付款單已生成，請檢查並填入付款日期/付款方式');
            redirect('/payments_out.php?action=edit&id=' . $paymentOutId);
        } catch (Exception $e) {
            Session::flash('error', '生成失敗：' . $e->getMessage());
            redirect('/payables.php?action=edit&id=' . $payableId);
        }
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

    // ---- AJAX: 依廠商與日期區間搜尋退貨單 ----
    case 'ajax_search_returns':
        header('Content-Type: application/json');
        $vendorName = trim($_GET['vendor_name'] ?? '');
        $dateFrom   = trim($_GET['date_from'] ?? '');
        $dateTo     = trim($_GET['date_to'] ?? '');
        if ($vendorName === '') { echo json_encode(array()); break; }
        $where = 'r.vendor_name = ?';
        $params = array($vendorName);
        if ($dateFrom !== '') { $where .= ' AND r.return_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo !== '')   { $where .= ' AND r.return_date <= ?'; $params[] = $dateTo; }
        $sql = "SELECT r.id, r.return_number, r.return_date, r.vendor_name, r.total_amount, r.total_qty, r.status,
                       b.name AS branch_name, gr.gr_number AS source_gr_number
                FROM returns r
                LEFT JOIN branches b ON r.branch_id = b.id
                LEFT JOIN goods_receipts gr ON r.gr_id = gr.id
                WHERE $where
                ORDER BY r.return_date DESC, r.id DESC
                LIMIT 200";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ---- AJAX: 依廠商與日期區間搜尋進貨單 ----
    case 'ajax_search_goods_receipts':
        header('Content-Type: application/json');
        $vendorName = trim($_GET['vendor_name'] ?? '');
        $dateFrom   = trim($_GET['date_from'] ?? '');
        $dateTo     = trim($_GET['date_to'] ?? '');
        if ($vendorName === '') { echo json_encode(array()); break; }
        $where = 'gr.vendor_name = ?';
        $params = array($vendorName);
        if ($dateFrom !== '') { $where .= ' AND gr.gr_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo !== '')   { $where .= ' AND gr.gr_date <= ?'; $params[] = $dateTo; }
        // total_amount 以 items 即時計算為準（含稅 = 未稅 * 1.05）
        // 若 items 全無金額（舊 Ragic 入庫資料），回退使用原儲存值
        $sql = "SELECT gr.id, gr.gr_number, gr.gr_date, gr.vendor_name, gr.branch_name,
                       CASE WHEN COALESCE(ic.calc_subtotal, 0) > 0 THEN ROUND(ic.calc_subtotal * 1.05, 0) ELSE gr.total_amount END AS total_amount,
                       CASE WHEN COALESCE(ic.calc_qty, 0) > 0 THEN ic.calc_qty ELSE gr.total_qty END AS total_qty,
                       gr.status, gr.paid_amount
                FROM goods_receipts gr
                LEFT JOIN (
                    SELECT goods_receipt_id, SUM(received_qty) AS calc_qty, SUM(amount) AS calc_subtotal
                    FROM goods_receipt_items GROUP BY goods_receipt_id
                ) ic ON ic.goods_receipt_id = gr.id
                WHERE $where
                ORDER BY gr.gr_date DESC, gr.id DESC
                LIMIT 200";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'toggle_star':
        header('Content-Type: application/json; charset=utf-8');
        if (!$canManageFinance && !$isBoss) { echo json_encode(array('error' => '無權限')); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error' => '方法錯誤')); exit; }
        $sid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($sid <= 0) { echo json_encode(array('error' => '參數錯誤')); exit; }
        try {
            $db = Database::getInstance();
            $cur = $db->prepare("SELECT is_starred FROM payables WHERE id = ?");
            $cur->execute(array($sid));
            $c = $cur->fetchColumn();
            if ($c === false) { echo json_encode(array('error' => '記錄不存在')); exit; }
            $new = ((int)$c === 1) ? 0 : 1;
            $db->prepare("UPDATE payables SET is_starred = ? WHERE id = ?")->execute(array($new, $sid));
            echo json_encode(array('success' => true, 'starred' => $new));
        } catch (Exception $e) {
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;

    default:
        redirect('/payables.php');
        break;
}
