<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/inventory/StockModel.php';
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

$canManage = Auth::hasPermission('inventory.manage');
$canView = Auth::hasPermission('inventory.view');
if (!$canManage && !$canView) {
    Session::flash('error', '無權限');
    redirect('/index.php');
}

$model = new StockModel();
$invModel = new InventoryModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'status'       => !empty($_GET['status']) ? $_GET['status'] : '',
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
            'keyword'      => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'    => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'      => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
            'source_type'  => !empty($_GET['source_type']) ? $_GET['source_type'] : '',
        );
        $page = max(1, (int)(!empty($_GET['page']) ? $_GET['page'] : 1));
        $result = $model->getStockIns($filters, $page, 100);
        $records = $result['data'];
        $pagination = $result;
        $warehouses = $invModel->getWarehouses();

        $pageTitle = '入庫單';
        $currentPage = 'stock_ins';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/stock_ins/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getStockInById($id);
        if (!$record) {
            Session::flash('error', '入庫單不存在');
            redirect('/stock_ins.php');
        }
        $items = $model->getStockInItems($id);

        $pageTitle = '入庫單 - ' . $record['si_number'];
        $currentPage = 'stock_ins';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/stock_ins/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/stock_ins.php'); }
        $warehouses = $invModel->getWarehouses();
        $branches = $invModel->getBranches();
        $user = Session::getUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/stock_ins.php');
            }
            $data = array(
                'si_date'      => !empty($_POST['si_date']) ? $_POST['si_date'] : date('Y-m-d'),
                'source_type'  => !empty($_POST['source_type']) ? $_POST['source_type'] : 'manual',
                'source_number'=> !empty($_POST['source_number']) ? $_POST['source_number'] : null,
                'warehouse_id' => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'branch_id'    => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'branch_name'  => !empty($_POST['branch_name']) ? $_POST['branch_name'] : null,
                'note'         => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'   => $user['id'],
                'items'        => array(),
            );

            $totalQty = 0;
            if (!empty($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (empty($item['product_name']) && empty($item['model'])) continue;
                    $qty = !empty($item['quantity']) ? (float)$item['quantity'] : 0;
                    if ($qty <= 0) continue;
                    $data['items'][] = array(
                        'product_id'   => !empty($item['product_id']) ? $item['product_id'] : null,
                        'model'        => !empty($item['model']) ? $item['model'] : null,
                        'product_name' => !empty($item['product_name']) ? $item['product_name'] : null,
                        'spec'         => !empty($item['spec']) ? $item['spec'] : null,
                        'unit'         => !empty($item['unit']) ? $item['unit'] : null,
                        'quantity'     => $qty,
                        'unit_price'   => !empty($item['unit_price']) ? $item['unit_price'] : 0,
                    );
                    $totalQty += $qty;
                }
            }
            $data['total_qty'] = $totalQty;

            if (empty($data['items'])) {
                Session::flash('error', '請至少新增一筆入庫明細');
                $pageTitle = '新增入庫單';
                $currentPage = 'stock_ins';
                require __DIR__ . '/../templates/layouts/header.php';
                require __DIR__ . '/../templates/stock_ins/form.php';
                require __DIR__ . '/../templates/layouts/footer.php';
                break;
            }

            try {
                $siId = $model->createStockIn($data);
                Session::flash('success', '入庫單已建立');
                redirect('/stock_ins.php?action=view&id=' . $siId);
            } catch (Exception $e) {
                Session::flash('error', '建立失敗: ' . $e->getMessage());
                redirect('/stock_ins.php');
            }
        }

        $pageTitle = '新增入庫單';
        $currentPage = 'stock_ins';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/stock_ins/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create_from_return':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/stock_ins.php'); }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/stock_outs.php'); }

        $soId = (int)(!empty($_GET['stock_out_id']) ? $_GET['stock_out_id'] : 0);
        if ($soId <= 0) { Session::flash('error', '參數錯誤'); redirect('/stock_outs.php'); }

        $db = Database::getInstance();

        // 取出庫單資訊
        $soRecord = $db->prepare("SELECT * FROM stock_outs WHERE id = ?");
        $soRecord->execute(array($soId));
        $so = $soRecord->fetch(PDO::FETCH_ASSOC);
        if (!$so || $so['status'] !== '已確認') {
            Session::flash('error', '出庫單不存在或尚未確認');
            redirect('/stock_outs.php');
        }

        // 從施工回報取餘料（returned_qty > 0 的品項）
        $retStmt = $db->prepare("
            SELECT mu.product_id, mu.material_name, mu.unit, mu.returned_qty, mu.unit_cost
            FROM material_usage mu
            JOIN work_logs wl ON mu.work_log_id = wl.id
            JOIN schedules s ON wl.schedule_id = s.id
            JOIN quotations q ON q.case_id = s.case_id
            JOIN stock_outs so ON so.source_type = 'quotation' AND so.source_id = q.id
            WHERE so.id = ? AND mu.returned_qty > 0
            GROUP BY mu.product_id, mu.material_name, mu.unit, mu.returned_qty, mu.unit_cost
        ");
        $retStmt->execute(array($soId));
        $returnItems = $retStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($returnItems)) {
            Session::flash('error', '無餘料品項');
            redirect('/stock_outs.php?action=view&id=' . $soId);
        }

        // 建立入庫單
        $userId = Session::getUser()['id'];
        $items = array();
        $totalQty = 0;
        foreach ($returnItems as $ri) {
            $qty = (float)$ri['returned_qty'];
            $items[] = array(
                'product_id'   => !empty($ri['product_id']) ? $ri['product_id'] : null,
                'product_name' => $ri['material_name'],
                'model'        => '',
                'unit'         => $ri['unit'],
                'quantity'     => $qty,
                'unit_price'   => !empty($ri['unit_cost']) ? $ri['unit_cost'] : 0,
            );
            $totalQty += $qty;
        }

        try {
            $siId = $model->createStockIn(array(
                'si_date'       => date('Y-m-d'),
                'source_type'   => 'return_material',
                'source_id'     => $soId,
                'source_number' => $so['so_number'],
                'warehouse_id'  => $so['warehouse_id'],
                'note'          => '餘料入庫，來源出庫單 ' . $so['so_number'],
                'total_qty'     => $totalQty,
                'created_by'    => $userId,
                'items'         => $items,
            ));

            // 清除出庫單餘料標記
            $db->prepare("UPDATE stock_outs SET has_return_material = 0 WHERE id = ?")
               ->execute(array($soId));

            Session::flash('success', '餘料入庫單已建立');
            redirect('/stock_ins.php?action=view&id=' . $siId);
        } catch (Exception $e) {
            Session::flash('error', '建立失敗: ' . $e->getMessage());
            redirect('/stock_outs.php?action=view&id=' . $soId);
        }
        break;

    case 'confirm':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        if ($id > 0) {
            try {
                $userId = Session::getUser()['id'];
                $result = $model->confirmStockIn($id, $userId);
                if ($result) {
                    // Auto-journal on stock-in confirm
                    try {
                        require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
                        AutoJournalService::onStockInConfirmed($id);
                    } catch (Exception $autoJournalEx) {
                        error_log('AutoJournal stock_in error: ' . $autoJournalEx->getMessage());
                    }
                    Session::flash('success', '入庫單已確認，庫存已更新');
                } else {
                    Session::flash('error', '確認失敗：入庫單狀態不正確或無明細');
                }
            } catch (Exception $e) {
                Session::flash('error', '確認失敗: ' . $e->getMessage());
            }
        }
        redirect('/stock_ins.php?action=view&id=' . $id);
        break;

    default:
        redirect('/stock_ins.php');
        break;
}
