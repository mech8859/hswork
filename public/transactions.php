<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/transactions/TransactionModel.php';

$model = new TransactionModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    // ---- 清單（依人彙總）----
    case 'list':
        $filters = array(
            'target_type'  => isset($_GET['target_type']) ? $_GET['target_type'] : '',
            'contact_name' => isset($_GET['contact_name']) ? $_GET['contact_name'] : '',
            'settled'      => isset($_GET['settled']) ? $_GET['settled'] : '',
        );
        $records = $model->getGroupedList($filters);

        $pageTitle = '非廠商交易管理';
        $currentPage = 'transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/transactions/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 某人的交易列表 ----
    case 'contact':
        $contactName = isset($_GET['name']) ? $_GET['name'] : '';
        if (!$contactName) { redirect('/transactions.php'); }
        $contactRecords = $model->getByContact($contactName);

        $pageTitle = $contactName . ' - 交易紀錄';
        $currentPage = 'transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/transactions/contact.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 新增 ----
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/transactions.php'); }

            $items = array();
            if (!empty($_POST['item_trade_date'])) {
                foreach ($_POST['item_trade_date'] as $i => $v) {
                    $items[] = array(
                        'trade_date'   => $v,
                        'description'  => isset($_POST['item_description'][$i]) ? $_POST['item_description'][$i] : '',
                        'product'      => isset($_POST['item_product'][$i]) ? $_POST['item_product'][$i] : '',
                        'amount'       => isset($_POST['item_amount'][$i]) ? $_POST['item_amount'][$i] : 0,
                        'due_date'     => isset($_POST['item_due_date'][$i]) ? $_POST['item_due_date'][$i] : '',
                        'is_settled'   => isset($_POST['item_is_settled'][$i]) ? $_POST['item_is_settled'][$i] : 0,
                        'note'         => isset($_POST['item_note'][$i]) ? $_POST['item_note'][$i] : '',
                    );
                }
            }

            $data = array(
                'register_date' => $_POST['register_date'],
                'target_type'   => $_POST['target_type'],
                'category'      => $_POST['category'],
                'contact_name'  => $_POST['contact_name'],
                'created_by'    => Auth::user()['id'],
                'items'         => $items,
            );

            $model->create($data);
            Session::flash('success', '交易記錄已新增');
            $cn = trim($_POST['contact_name']);
            if ($cn) {
                redirect('/transactions.php?action=contact&name=' . urlencode($cn));
            } else {
                redirect('/transactions.php');
            }
        }

        $record = null;
        $pageTitle = '新增交易';
        $currentPage = 'transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/transactions/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 編輯 ----
    case 'edit':
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) { Session::flash('error', '記錄不存在'); redirect('/transactions.php'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/transactions.php?action=edit&id=' . $id); }

            $items = array();
            if (!empty($_POST['item_trade_date'])) {
                foreach ($_POST['item_trade_date'] as $i => $v) {
                    $items[] = array(
                        'trade_date'   => $v,
                        'description'  => isset($_POST['item_description'][$i]) ? $_POST['item_description'][$i] : '',
                        'product'      => isset($_POST['item_product'][$i]) ? $_POST['item_product'][$i] : '',
                        'amount'       => isset($_POST['item_amount'][$i]) ? $_POST['item_amount'][$i] : 0,
                        'due_date'     => isset($_POST['item_due_date'][$i]) ? $_POST['item_due_date'][$i] : '',
                        'is_settled'   => isset($_POST['item_is_settled'][$i]) ? $_POST['item_is_settled'][$i] : 0,
                        'note'         => isset($_POST['item_note'][$i]) ? $_POST['item_note'][$i] : '',
                    );
                }
            }

            $data = array(
                'register_date' => $_POST['register_date'],
                'target_type'   => $_POST['target_type'],
                'category'      => $_POST['category'],
                'contact_name'  => $_POST['contact_name'],
                'items'         => $items,
            );

            $model->update($id, $data);
            Session::flash('success', '交易記錄已更新');
            $cn = trim($_POST['contact_name']);
            if ($cn) {
                redirect('/transactions.php?action=contact&name=' . urlencode($cn));
            } else {
                redirect('/transactions.php');
            }
        }

        $pageTitle = '編輯交易';
        $currentPage = 'transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/transactions/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 檢視 ----
    case 'view':
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) { Session::flash('error', '記錄不存在'); redirect('/transactions.php'); }

        $pageTitle = '交易詳情';
        $currentPage = 'transactions';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/transactions/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ---- 刪除 ----
    case 'delete':
        if (!Auth::hasPermission('finance.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/transactions.php');
        }
        if (verify_csrf()) {
            $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
            $model->delete($id);
            Session::flash('success', '交易記錄已刪除');
        }
        redirect('/transactions.php');
        break;

    // ---- 結清明細 ----
    case 'settle_item':
        if (verify_csrf()) {
            $itemId = (int)(isset($_GET['item_id']) ? $_GET['item_id'] : 0);
            $model->settleItem($itemId);
            Session::flash('success', '已結清');
        }
        $back = isset($_GET['back']) ? $_GET['back'] : '';
        if ($back) {
            redirect('/transactions.php?action=contact&name=' . urlencode($back));
        } else {
            $txId = isset($_GET['tx_id']) ? (int)$_GET['tx_id'] : 0;
            redirect('/transactions.php?action=view&id=' . $txId);
        }
        break;

    // ---- 取消結清 ----
    case 'unsettle_item':
        if (verify_csrf()) {
            $itemId = (int)(isset($_GET['item_id']) ? $_GET['item_id'] : 0);
            $model->unsettleItem($itemId);
            Session::flash('success', '已取消結清');
        }
        $back = isset($_GET['back']) ? $_GET['back'] : '';
        if ($back) {
            redirect('/transactions.php?action=contact&name=' . urlencode($back));
        } else {
            $txId = isset($_GET['tx_id']) ? (int)$_GET['tx_id'] : 0;
            redirect('/transactions.php?action=view&id=' . $txId);
        }
        break;

    default:
        redirect('/transactions.php');
}
