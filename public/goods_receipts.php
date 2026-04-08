<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/GoodsReceiptModel.php';
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

$model = new GoodsReceiptModel();
$invModel = new InventoryModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'status'      => !empty($_GET['status']) ? $_GET['status'] : '',
            'vendor_name' => !empty($_GET['vendor_name']) ? $_GET['vendor_name'] : '',
            'keyword'     => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'   => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'     => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
        );
        $page = max(1, (int)(!empty($_GET['page']) ? $_GET['page'] : 1));
        $result = $model->getList($filters, $page, 100);
        $records = $result['data'];
        $pagination = $result;
        $warehouses = $invModel->getWarehouses();

        $pageTitle = '進貨單';
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        $fromPoId = !empty($_GET['from_po']) ? (int)$_GET['from_po'] : 0;
        $prefill = null;
        if ($fromPoId > 0) {
            $prefill = $model->createFromPO($fromPoId);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/goods_receipts.php');
            }

            // 強制廠商必選 (vendor_id 必填)
            if (empty($_POST['vendor_id']) || empty($_POST['vendor_name'])) {
                Session::flash('error', '廠商必須從廠商管理選擇，不可手動輸入或留空');
                redirect('/goods_receipts.php?action=create' . ($fromPoId > 0 ? '&from_po=' . $fromPoId : ''));
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'gr_date'       => !empty($_POST['gr_date']) ? $_POST['gr_date'] : date('Y-m-d'),
                'status'        => !empty($_POST['status']) ? $_POST['status'] : '草稿',
                'po_id'         => !empty($_POST['po_id']) ? $_POST['po_id'] : null,
                'po_number'     => !empty($_POST['po_number']) ? $_POST['po_number'] : null,
                'vendor_id'     => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_name'   => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'warehouse_id'  => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'receiver_name' => !empty($_POST['receiver_name']) ? $_POST['receiver_name'] : null,
                'note'          => !empty($_POST['note']) ? $_POST['note'] : null,
                'total_qty'     => !empty($_POST['total_qty']) ? $_POST['total_qty'] : 0,
                'total_amount'  => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'created_by'    => $userId,
                'items'         => !empty($_POST['items']) ? $_POST['items'] : array(),
            );

            $grId = $model->create($data);

            // 從採購單轉入時，更新採購單狀態為「已轉進貨單」
            if (!empty($data['po_id'])) {
                $db = Database::getInstance();
                $db->prepare("UPDATE purchase_orders SET status = '已轉進貨單' WHERE id = ?")->execute(array($data['po_id']));
            }

            Session::flash('success', '進貨單已新增');
            redirect('/goods_receipts.php?action=edit&id=' . $grId);
        }

        $record = $prefill ? $prefill['header'] : null;
        $items = $prefill ? $prefill['items'] : array();
        $warehouses = $invModel->getWarehouses();
        $branches = Database::getInstance()->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $pendingPOs = $model->getPendingPOs();

        $pageTitle = '新增進貨單';
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '進貨單不存在');
            redirect('/goods_receipts.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/goods_receipts.php?action=edit&id=' . $id);
            }

            // 強制廠商必選（編輯時）
            // 註：歷史單舊資料 vendor_id 為空也允許保留，只擋「使用者改了廠商欄位卻沒帶 ID」的情況
            //     判斷邏輯：如果 vendor_name 改變了 → 必須有 vendor_id
            $oldVendorName = isset($record['vendor_name']) ? trim($record['vendor_name']) : '';
            $newVendorName = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : '';
            if ($newVendorName !== '' && $newVendorName !== $oldVendorName && empty($_POST['vendor_id'])) {
                Session::flash('error', '廠商必須從廠商管理選擇，不可手動輸入');
                redirect('/goods_receipts.php?action=edit&id=' . $id);
            }
            if ($newVendorName === '') {
                Session::flash('error', '廠商不可留空');
                redirect('/goods_receipts.php?action=edit&id=' . $id);
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'gr_date'       => !empty($_POST['gr_date']) ? $_POST['gr_date'] : date('Y-m-d'),
                'status'        => !empty($_POST['status']) ? $_POST['status'] : $record['status'],
                'po_id'         => !empty($_POST['po_id']) ? $_POST['po_id'] : null,
                'po_number'     => !empty($_POST['po_number']) ? $_POST['po_number'] : null,
                'vendor_id'     => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'vendor_name'   => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'warehouse_id'  => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'receiver_name' => !empty($_POST['receiver_name']) ? $_POST['receiver_name'] : null,
                'note'          => !empty($_POST['note']) ? $_POST['note'] : null,
                'total_qty'     => !empty($_POST['total_qty']) ? $_POST['total_qty'] : 0,
                'total_amount'  => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'updated_by'    => $userId,
                'items'         => isset($_POST['items']) ? $_POST['items'] : array(),
            );

            $model->update($id, $data);
            Session::flash('success', '進貨單已更新');
            redirect('/goods_receipts.php?action=edit&id=' . $id);
        }

        $items = $model->getItems($id);
        $warehouses = $invModel->getWarehouses();
        $branches = Database::getInstance()->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $pendingPOs = $model->getPendingPOs();

        $pageTitle = '編輯進貨單 - ' . $record['gr_number'];
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '進貨單不存在');
            redirect('/goods_receipts.php');
        }
        $items = $model->getItems($id);

        $pageTitle = '進貨單 - ' . $record['gr_number'];
        $currentPage = 'goods_receipts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/goods_receipts/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'confirm':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            try {
                $userId = Session::getUser()['id'];
                $stockInId = $model->confirm($id, $userId);
                if ($stockInId) {
                    Session::flash('success', '進貨單已確認，入庫單已自動建立');
                } else {
                    Session::flash('error', '確認失敗：進貨單狀態不正確或無明細');
                }
            } catch (Exception $e) {
                Session::flash('error', '確認失敗: ' . $e->getMessage());
            }
        }
        redirect('/goods_receipts.php?action=view&id=' . $id);
        break;

    case 'delete':
        if (!Auth::hasPermission('inventory.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/goods_receipts.php');
        }
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            if ($model->delete($id)) {
                Session::flash('success', '進貨單已刪除');
            } else {
                Session::flash('error', '僅草稿狀態的進貨單可刪除');
            }
        }
        redirect('/goods_receipts.php');
        break;

    case 'create_from_po':
        $poId = (int)(!empty($_GET['po_id']) ? $_GET['po_id'] : 0);
        if ($poId > 0) {
            redirect('/goods_receipts.php?action=create&from_po=' . $poId);
        }
        redirect('/goods_receipts.php');
        break;

    // ============================================================
    // ADMIN_TOOL_BLOCK_START - 測試期專用，完成後可整段移除
    // ============================================================
    case 'admin_delete':
        $u = Auth::user();
        if (!$u || $u['role'] !== 'admin') {
            Session::flash('error', '無權限執行此操作（僅系統管理者）');
            redirect('/goods_receipts.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/goods_receipts.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) { Session::flash('error', '參數錯誤'); redirect('/goods_receipts.php'); }
        $reasons = $model->checkDeletable($id);
        if (!empty($reasons)) {
            Session::flash('error', '無法刪除：' . implode('；', $reasons));
            redirect('/goods_receipts.php?action=view&id=' . $id);
        }
        try {
            $model->deleteHard($id);
            AuditLog::log('goods_receipts', 'admin_delete', $id, '管理者刪除整張進貨單');
            Session::flash('success', '進貨單已刪除');
        } catch (Exception $e) {
            Session::flash('error', '刪除失敗：' . $e->getMessage());
        }
        redirect('/goods_receipts.php');
        break;

    case 'admin_edit_basic':
        $u = Auth::user();
        if (!$u || $u['role'] !== 'admin') {
            Session::flash('error', '無權限執行此操作（僅系統管理者）');
            redirect('/goods_receipts.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/goods_receipts.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) { Session::flash('error', '參數錯誤'); redirect('/goods_receipts.php'); }
        try {
            $model->updateBasic($id, array(
                'vendor_name' => trim(!empty($_POST['vendor_name']) ? $_POST['vendor_name'] : ''),
                'vendor_id'   => (int)(!empty($_POST['vendor_id']) ? $_POST['vendor_id'] : 0),
            ));
            AuditLog::log('goods_receipts', 'admin_edit_basic', $id, '管理者修改廠商: ' . $_POST['vendor_name']);
            Session::flash('success', '廠商已更新');
        } catch (Exception $e) {
            Session::flash('error', '更新失敗：' . $e->getMessage());
        }
        redirect('/goods_receipts.php?action=view&id=' . $id);
        break;
    // ADMIN_TOOL_BLOCK_END

    default:
        redirect('/goods_receipts.php');
        break;
}
