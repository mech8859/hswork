<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限查看此頁面');
    redirect('/');
}
require_once __DIR__ . '/../modules/finance/FinanceModel.php';

$model = new FinanceModel();
$db = Database::getInstance();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$isBoss = Auth::hasPermission('all');
$canManageFinance = Auth::hasPermission('finance.manage');

switch ($action) {
    // ---- 清單 ----
    case 'list':
        $filters = array(
            'status'        => !empty($_GET['status']) ? $_GET['status'] : '',
            'main_category' => !empty($_GET['main_category']) ? $_GET['main_category'] : '',
            'keyword'       => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'     => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'       => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'sort'          => !empty($_GET['sort']) ? $_GET['sort'] : 'desc',
        );
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $result = $model->getPaymentsOut($filters, $page);
        $records = $result['data'];

        $pageTitle = '付款單';
        $currentPage = 'payments_out';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/payments_out/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增 ----
    case 'create':
        if (!$canManageFinance && !$isBoss) {
            Session::flash('error', '無權限執行此操作');
            redirect('/payments_out.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/payments_out.php');
            }
            $user = Session::getUser();
            $data = array(
                'create_date'    => !empty($_POST['create_date']) ? $_POST['create_date'] : null,
                'payment_date'   => !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'payment_method' => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_type'   => !empty($_POST['payment_type']) ? $_POST['payment_type'] : null,
                'payment_terms'  => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'status'         => !empty($_POST['status']) ? $_POST['status'] : '待付款',
                'main_category'  => !empty($_POST['main_category']) ? $_POST['main_category'] : null,
                'sub_category'   => !empty($_POST['sub_category']) ? $_POST['sub_category'] : null,
                'subtotal'       => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax'            => !empty($_POST['tax']) ? $_POST['tax'] : 0,
                'remittance_fee' => !empty($_POST['remittance_fee']) ? $_POST['remittance_fee'] : 0,
                'total_amount'   => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'     => $user['id'],
                'updated_by'     => $user['id'],
            );
            $id = $model->createPaymentOut($data);

            // 儲存分公司拆帳
            if (!empty($_POST['branches'])) {
                $model->savePaymentOutBranches($id, $_POST['branches']);
            }
            // 儲存憑證明細
            if (!empty($_POST['vouchers'])) {
                $model->savePaymentOutVouchers($id, $_POST['vouchers']);
            }

            AuditLog::log('payments_out', 'create', $id, '新增付款單');
            Session::flash('success', '付款單已建立');
            redirect('/payments_out.php?action=edit&id=' . $id);
        }

        $record = null;
        $branchIds = Auth::getAccessibleBranchIds();
        $branches = $model->getBranches($branchIds);
        $branchItems = array();
        $voucherItems = array();

        $pageTitle = '新增付款單';
        $currentPage = 'payments_out';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/payments_out/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯 ----
    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getPaymentOut($id);
        if (!$record) {
            Session::flash('error', '付款單不存在');
            redirect('/payments_out.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$canManageFinance && !$isBoss) {
                Session::flash('error', '無權限執行此操作');
                redirect('/payments_out.php');
            }
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/payments_out.php?action=edit&id=' . $id);
            }
            $user = Session::getUser();
            $data = array(
                'create_date'    => !empty($_POST['create_date']) ? $_POST['create_date'] : null,
                'payment_date'   => !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'payment_method' => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
                'payment_type'   => !empty($_POST['payment_type']) ? $_POST['payment_type'] : null,
                'payment_terms'  => !empty($_POST['payment_terms']) ? $_POST['payment_terms'] : null,
                'status'         => !empty($_POST['status']) ? $_POST['status'] : '待付款',
                'main_category'  => !empty($_POST['main_category']) ? $_POST['main_category'] : null,
                'sub_category'   => !empty($_POST['sub_category']) ? $_POST['sub_category'] : null,
                'subtotal'       => !empty($_POST['subtotal']) ? $_POST['subtotal'] : 0,
                'tax'            => !empty($_POST['tax']) ? $_POST['tax'] : 0,
                'remittance_fee' => !empty($_POST['remittance_fee']) ? $_POST['remittance_fee'] : 0,
                'total_amount'   => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
                'updated_by'     => $user['id'],
            );
            $model->updatePaymentOut($id, $data);

            // 儲存分公司拆帳
            $branchData = !empty($_POST['branches']) ? $_POST['branches'] : array();
            $model->savePaymentOutBranches($id, $branchData);

            // 儲存憑證明細
            $voucherData = !empty($_POST['vouchers']) ? $_POST['vouchers'] : array();
            $model->savePaymentOutVouchers($id, $voucherData);

            AuditLog::log('payments_out', 'update', $id, '更新付款單');

            // Auto-journal: when status changes to 已付款
            if ($data['status'] === '已付款' && $record['status'] !== '已付款') {
                try {
                    require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
                    AutoJournalService::onPaymentConfirmed($id);
                } catch (Exception $autoJournalEx) {
                    error_log('AutoJournal payment error: ' . $autoJournalEx->getMessage());
                }
            }

            Session::flash('success', '付款單已更新');
            redirect('/payments_out.php?action=edit&id=' . $id);
        }

        $branchIds = Auth::getAccessibleBranchIds();
        $branches = $model->getBranches($branchIds);
        $branchItems = $model->getPaymentOutBranches($record['id']);
        $voucherItems = $model->getPaymentOutVouchers($record['id']);

        $pageTitle = '編輯付款單 - ' . (!empty($record['payment_number']) ? $record['payment_number'] : $record['id']);
        $currentPage = 'payments_out';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/payments_out/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 廠商搜尋 AJAX ----
    case 'ajax_vendor_search':
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) { echo '[]'; exit; }
        $kw = '%' . $q . '%';
        $stmt = $db->prepare("SELECT id, vendor_code, name, contact_person, phone, tax_id, fax, email, address FROM vendors WHERE (name LIKE ? OR vendor_code LIKE ? OR contact_person LIKE ?) AND is_active = 1 ORDER BY name LIMIT 10");
        $stmt->execute(array($kw, $kw, $kw));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // fallback: 也搜 outsource_vendors
        if (empty($results)) {
            $stmt2 = $db->prepare("SELECT id, '' as vendor_code, name, contact_person, phone FROM outsource_vendors WHERE name LIKE ? AND is_active = 1 ORDER BY name LIMIT 10");
            $stmt2->execute(array($kw));
            $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($results);
        exit;

    // ---- 應付帳款搜尋 AJAX ----
    case 'ajax_payable_search':
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) { echo '[]'; exit; }
        $kw = '%' . $q . '%';
        $stmt = $db->prepare("SELECT id, payable_number, vendor_name, total_amount, status FROM payables WHERE (payable_number LIKE ? OR vendor_name LIKE ?) ORDER BY id DESC LIMIT 10");
        $stmt->execute(array($kw, $kw));
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    // ---- 刪除 ----
    case 'delete':
        if (!$isBoss && !Auth::hasPermission('finance.delete')) {
            Session::flash('error', '無權限執行此操作');
            redirect('/payments_out.php');
        }
        if (verify_csrf()) {
            $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
            AuditLog::log('payments_out', 'delete', $id, '刪除付款單');
            $model->deletePaymentOut($id);
            Session::flash('success', '付款單已刪除');
        }
        redirect('/payments_out.php');
        break;

    default:
        redirect('/payments_out.php');
}
