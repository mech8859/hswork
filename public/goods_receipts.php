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

    default:
        redirect('/goods_receipts.php');
        break;
}
