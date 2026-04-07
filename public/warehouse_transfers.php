<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('inventory.manage') && !Auth::hasPermission('inventory.view')) {
    Session::flash('error', '無權限查看此頁面');
    redirect('/');
}
require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

$model = new ProcurementModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$branchIds = Auth::getAccessibleBranchIds();

switch ($action) {
    case 'list':
        $filters = array(
            'from_warehouse_id' => !empty($_GET['from_warehouse_id']) ? $_GET['from_warehouse_id'] : '',
            'to_warehouse_id'   => !empty($_GET['to_warehouse_id']) ? $_GET['to_warehouse_id'] : '',
            'status'            => !empty($_GET['status']) ? $_GET['status'] : '',
            'keyword'           => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'         => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'           => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
        );
        $records = $model->getTransfers($filters);
        $warehouses = $model->getWarehouses();

        $pageTitle = '倉庫調撥';
        $currentPage = 'warehouse_transfers';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/warehouse_transfers/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/warehouse_transfers.php');
            }

            $userId = Session::getUser()['id'];
            $data = array(
                'transfer_date'       => !empty($_POST['transfer_date']) ? $_POST['transfer_date'] : date('Y-m-d'),
                'from_branch_id'      => !empty($_POST['from_branch_id']) ? $_POST['from_branch_id'] : null,
                'to_branch_id'        => !empty($_POST['to_branch_id']) ? $_POST['to_branch_id'] : null,
                'from_warehouse_id'   => !empty($_POST['from_warehouse_id']) ? $_POST['from_warehouse_id'] : null,
                'to_warehouse_id'     => !empty($_POST['to_warehouse_id']) ? $_POST['to_warehouse_id'] : null,
                'from_warehouse_name' => !empty($_POST['from_warehouse_name']) ? $_POST['from_warehouse_name'] : null,
                'to_warehouse_name'   => !empty($_POST['to_warehouse_name']) ? $_POST['to_warehouse_name'] : null,
                'status'              => !empty($_POST['status']) ? $_POST['status'] : '待出貨',
                'shipper_name'        => !empty($_POST['shipper_name']) ? $_POST['shipper_name'] : null,
                'receiver_name'       => !empty($_POST['receiver_name']) ? $_POST['receiver_name'] : null,
                'total_amount'        => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'note'                => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'          => $userId,
            );

            $transferId = $model->createTransfer($data);

            if (!empty($_POST['items'])) {
                $model->saveTransferItems($transferId, $_POST['items']);
            }

            Session::flash('success', '調撥單已新增');
            redirect('/warehouse_transfers.php?action=edit&id=' . $transferId);
        }

        $record = null;
        $items = array();
        $warehouses = $model->getWarehouses();
        $branches = $model->getBranches();

        $pageTitle = '新增調撥單';
        $currentPage = 'warehouse_transfers';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/warehouse_transfers/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getTransfer($id);
        if (!$record) {
            Session::flash('error', '調撥單不存在');
            redirect('/warehouse_transfers.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/warehouse_transfers.php?action=edit&id=' . $id);
            }

            $userId = Session::getUser()['id'];
            $oldStatus = $record['status'];
            $newStatus = !empty($_POST['status']) ? $_POST['status'] : $oldStatus;

            $data = array(
                'transfer_date'       => !empty($_POST['transfer_date']) ? $_POST['transfer_date'] : date('Y-m-d'),
                'from_branch_id'      => !empty($_POST['from_branch_id']) ? $_POST['from_branch_id'] : null,
                'to_branch_id'        => !empty($_POST['to_branch_id']) ? $_POST['to_branch_id'] : null,
                'from_warehouse_id'   => !empty($_POST['from_warehouse_id']) ? $_POST['from_warehouse_id'] : null,
                'to_warehouse_id'     => !empty($_POST['to_warehouse_id']) ? $_POST['to_warehouse_id'] : null,
                'from_warehouse_name' => !empty($_POST['from_warehouse_name']) ? $_POST['from_warehouse_name'] : null,
                'to_warehouse_name'   => !empty($_POST['to_warehouse_name']) ? $_POST['to_warehouse_name'] : null,
                'status'              => $newStatus,
                'update_inventory'    => !empty($_POST['update_inventory']) ? 1 : 0,
                'shipper_name'        => !empty($_POST['shipper_name']) ? $_POST['shipper_name'] : null,
                'receiver_name'       => !empty($_POST['receiver_name']) ? $_POST['receiver_name'] : null,
                'total_amount'        => !empty($_POST['total_amount']) ? $_POST['total_amount'] : 0,
                'note'                => !empty($_POST['note']) ? $_POST['note'] : null,
                'updated_by'          => $userId,
            );

            $model->updateTransfer($id, $data);

            if (isset($_POST['items'])) {
                $model->saveTransferItems($id, $_POST['items']);
            }

            // 完成調撥 → 更新庫存
            if ($oldStatus !== '完成' && $newStatus === '完成') {
                $invModel = new InventoryModel();
                $transferItems = $model->getTransferItems($id);
                $fromWh = !empty($data['from_warehouse_id']) ? $data['from_warehouse_id'] : null;
                $toWh = !empty($data['to_warehouse_id']) ? $data['to_warehouse_id'] : null;

                foreach ($transferItems as $item) {
                    if (!empty($item['product_id']) && $item['quantity'] > 0) {
                        if ($fromWh) {
                            $invModel->adjustStock(
                                $item['product_id'], $fromWh, -$item['quantity'],
                                'transfer_out', 'warehouse_transfer', $id,
                                '調撥出貨: ' . $record['transfer_number'], $userId
                            );
                        }
                        if ($toWh) {
                            $invModel->adjustStock(
                                $item['product_id'], $toWh, $item['quantity'],
                                'transfer_in', 'warehouse_transfer', $id,
                                '調撥入庫: ' . $record['transfer_number'], $userId
                            );
                        }
                    }
                }
            }

            Session::flash('success', '調撥單已更新');
            redirect('/warehouse_transfers.php?action=edit&id=' . $id);
        }

        $items = $model->getTransferItems($record['id']);
        $warehouses = $model->getWarehouses();
        $branches = $model->getBranches();

        $pageTitle = '編輯調撥單 - ' . $record['transfer_number'];
        $currentPage = 'warehouse_transfers';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/warehouse_transfers/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'delete':
        if (!Auth::hasPermission('inventory.delete')) {
            Session::flash('error', '無刪除權限');
            redirect('/warehouse_transfers.php');
        }
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            $model->deleteTransfer($id);
            Session::flash('success', '調撥單已刪除');
        }
        redirect('/warehouse_transfers.php');
        break;

    default:
        redirect('/warehouse_transfers.php');
        break;
}
