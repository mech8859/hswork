<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/returns/ReturnModel.php';

$model = new ReturnModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'return_type'  => !empty($_GET['return_type']) ? $_GET['return_type'] : '',
            'status'       => !empty($_GET['status']) ? $_GET['status'] : '',
            'keyword'      => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'    => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'      => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
        );
        $records = $model->getList($filters);
        $warehouses = $model->getWarehouses();

        $pageTitle = '退貨單';
        $currentPage = 'returns';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/returns/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/returns.php');
            }

            // 強制廠商必選 (vendor_id 必填)
            if (empty($_POST['vendor_id']) || empty($_POST['vendor_name'])) {
                Session::flash('error', '廠商必須從廠商管理選擇，不可手動輸入或留空');
                redirect('/returns.php?action=create');
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'return_date'    => !empty($_POST['return_date']) ? $_POST['return_date'] : date('Y-m-d'),
                'return_type'    => !empty($_POST['return_type']) ? $_POST['return_type'] : 'customer_return',
                'branch_id'      => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'warehouse_id'   => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'reference_type' => !empty($_POST['reference_type']) ? $_POST['reference_type'] : null,
                'reference_id'   => !empty($_POST['reference_id']) ? $_POST['reference_id'] : null,
                'customer_name'  => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'total_amount'   => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'reason'         => !empty($_POST['reason']) ? $_POST['reason'] : null,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'     => $userId,
                'items'          => !empty($_POST['items']) ? $_POST['items'] : array(),
            );

            try {
                $id = $model->create($data);
                Session::flash('success', '退貨單已新增');
                redirect('/returns.php?action=view&id=' . $id);
            } catch (Exception $ex) {
                Session::flash('error', '新增失敗: ' . $ex->getMessage());
                redirect('/returns.php?action=create');
            }
        }

        $record = null;
        $items = array();
        $warehouses = $model->getWarehouses();
        $db = Database::getInstance();
        $branches = $db->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = '新增退貨單';
        $currentPage = 'returns';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/returns/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '退貨單不存在');
            redirect('/returns.php');
        }
        if ($record['status'] !== 'draft') {
            Session::flash('error', '只能編輯草稿狀態的退貨單');
            redirect('/returns.php?action=view&id=' . $id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/returns.php?action=edit&id=' . $id);
            }

            // 強制廠商必選（編輯時）
            // 歷史單舊資料 vendor_id 為空也允許保留，只擋「使用者改了廠商欄位卻沒帶 ID」
            $oldVendorName = isset($record['vendor_name']) ? trim($record['vendor_name']) : '';
            $newVendorName = isset($_POST['vendor_name']) ? trim($_POST['vendor_name']) : '';
            if ($newVendorName === '') {
                Session::flash('error', '廠商不可留空');
                redirect('/returns.php?action=edit&id=' . $id);
            }
            if ($newVendorName !== $oldVendorName && empty($_POST['vendor_id'])) {
                Session::flash('error', '廠商必須從廠商管理選擇，不可手動輸入');
                redirect('/returns.php?action=edit&id=' . $id);
            }

            $data = array(
                'return_date'    => !empty($_POST['return_date']) ? $_POST['return_date'] : date('Y-m-d'),
                'return_type'    => !empty($_POST['return_type']) ? $_POST['return_type'] : 'customer_return',
                'branch_id'      => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'warehouse_id'   => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'reference_type' => !empty($_POST['reference_type']) ? $_POST['reference_type'] : null,
                'reference_id'   => !empty($_POST['reference_id']) ? $_POST['reference_id'] : null,
                'customer_name'  => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'vendor_name'    => !empty($_POST['vendor_name']) ? $_POST['vendor_name'] : null,
                'total_amount'   => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'reason'         => !empty($_POST['reason']) ? $_POST['reason'] : null,
                'note'           => !empty($_POST['note']) ? $_POST['note'] : null,
                'items'          => !empty($_POST['items']) ? $_POST['items'] : array(),
            );

            try {
                $model->update($id, $data);
                Session::flash('success', '退貨單已更新');
                redirect('/returns.php?action=view&id=' . $id);
            } catch (Exception $ex) {
                Session::flash('error', '更新失敗: ' . $ex->getMessage());
                redirect('/returns.php?action=edit&id=' . $id);
            }
        }

        $items = $record['items'];
        $warehouses = $model->getWarehouses();
        $db = Database::getInstance();
        $branches = $db->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = '編輯退貨單 - ' . $record['return_number'];
        $currentPage = 'returns';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/returns/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) {
            Session::flash('error', '退貨單不存在');
            redirect('/returns.php');
        }

        $pageTitle = '退貨單 - ' . $record['return_number'];
        $currentPage = 'returns';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/returns/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'confirm':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            try {
                $userId = Session::getUser()['id'];
                $model->confirm($id, $userId);
                Session::flash('success', '退貨單已確認，庫存已更新');
            } catch (Exception $ex) {
                Session::flash('error', '確認失敗: ' . $ex->getMessage());
            }
        }
        redirect('/returns.php?action=view&id=' . $id);
        break;

    case 'delete':
        if (!Auth::hasPermission('inventory.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/returns.php');
        }
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            try {
                $model->delete($id);
                Session::flash('success', '退貨單已刪除');
            } catch (Exception $ex) {
                Session::flash('error', '刪除失敗: ' . $ex->getMessage());
            }
        }
        redirect('/returns.php');
        break;

    // ============================================================
    // ADMIN_TOOL_BLOCK_START - 測試期專用，完成後可整段移除
    // ============================================================
    case 'admin_delete':
        $u = Auth::user();
        if (!$u || $u['role'] !== 'boss') {
            Session::flash('error', '無權限執行此操作（僅系統管理者）');
            redirect('/returns.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/returns.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) { Session::flash('error', '參數錯誤'); redirect('/returns.php'); }
        $reasons = $model->checkDeletable($id);
        if (!empty($reasons)) {
            Session::flash('error', '無法刪除：' . implode('；', $reasons));
            redirect('/returns.php?action=view&id=' . $id);
        }
        try {
            $model->deleteHard($id);
            AuditLog::log('returns', 'admin_delete', $id, '管理者刪除整張退貨單');
            Session::flash('success', '退貨單已刪除');
        } catch (Exception $e) {
            Session::flash('error', '刪除失敗：' . $e->getMessage());
        }
        redirect('/returns.php');
        break;

    case 'admin_edit_basic':
        $u = Auth::user();
        if (!$u || $u['role'] !== 'boss') {
            Session::flash('error', '無權限執行此操作（僅系統管理者）');
            redirect('/returns.php');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '請從畫面操作');
            redirect('/returns.php');
        }
        $id = (int)(!empty($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) { Session::flash('error', '參數錯誤'); redirect('/returns.php'); }
        try {
            $model->updateBasic($id, array(
                'vendor_name' => trim(!empty($_POST['vendor_name']) ? $_POST['vendor_name'] : ''),
                'vendor_id'   => (int)(!empty($_POST['vendor_id']) ? $_POST['vendor_id'] : 0),
            ));
            AuditLog::log('returns', 'admin_edit_basic', $id, '管理者修改廠商: ' . $_POST['vendor_name']);
            Session::flash('success', '廠商已更新');
        } catch (Exception $e) {
            Session::flash('error', '更新失敗：' . $e->getMessage());
        }
        redirect('/returns.php?action=view&id=' . $id);
        break;
    // ADMIN_TOOL_BLOCK_END

    default:
        redirect('/returns.php');
        break;
}
