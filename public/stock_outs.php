<?php
/**
 * 出庫單管理
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/inventory/StockModel.php';

$canManage = Auth::hasPermission('inventory.manage');
$canView = Auth::hasPermission('inventory.view');
if (!$canManage && !$canView) {
    Session::flash('error', '無權限');
    redirect('/index.php');
}

$model = new StockModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'month'        => isset($_GET['month']) ? $_GET['month'] : (isset($_GET['keyword']) || isset($_GET['status']) ? '' : date('Y-m')),
            'status'       => isset($_GET['status']) ? $_GET['status'] : '',
            'keyword'      => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'warehouse_id' => isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
        );
        $page = max(1, (int)(!empty($_GET['page']) ? $_GET['page'] : 1));
        $result = $model->getStockOuts($filters, $page, 100);
        $records = $result['data'];
        $pagination = $result;
        $warehouses = $model->getWarehouses();
        $pageTitle = '出庫單管理';
        $currentPage = 'stock_outs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/stock_outs/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getStockOutById($id);
        if (!$record) { Session::flash('error', '出庫單不存在'); redirect('/stock_outs.php'); }
        $soNumber = isset($record['stockout_number']) ? $record['stockout_number'] : (isset($record['so_number']) ? $record['so_number'] : '');
        $pageTitle = '出庫單 ' . $soNumber;
        $currentPage = 'stock_outs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/stock_outs/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/stock_outs.php'); }
        require_once __DIR__ . '/../modules/inventory/InventoryModel.php';
        $invModel = new InventoryModel();
        $warehouses = $model->getWarehouses();
        $branches = $invModel->getBranches();
        $user = Session::getUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                Session::flash('error', '安全驗證失敗');
                redirect('/stock_outs.php');
            }
            $data = array(
                'so_date'       => !empty($_POST['so_date']) ? $_POST['so_date'] : date('Y-m-d'),
                'source_type'   => !empty($_POST['source_type']) ? $_POST['source_type'] : 'manual',
                'source_number' => !empty($_POST['source_number']) ? $_POST['source_number'] : null,
                'warehouse_id'  => !empty($_POST['warehouse_id']) ? $_POST['warehouse_id'] : null,
                'customer_id'   => !empty($_POST['customer_id']) ? $_POST['customer_id'] : null,
                'customer_name' => !empty($_POST['customer_name']) ? $_POST['customer_name'] : null,
                'branch_id'     => !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                'branch_name'   => !empty($_POST['branch_name']) ? $_POST['branch_name'] : null,
                'note'          => !empty($_POST['note']) ? $_POST['note'] : null,
                'created_by'    => $user['id'],
                'items'         => array(),
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
                Session::flash('error', '請至少新增一筆出庫明細');
                $pageTitle = '新增出庫單';
                $currentPage = 'stock_outs';
                require __DIR__ . '/../templates/layouts/header.php';
                require __DIR__ . '/../templates/stock_outs/form.php';
                require __DIR__ . '/../templates/layouts/footer.php';
                break;
            }

            try {
                $soId = $model->createStockOut($data);
                Session::flash('success', '出庫單已建立');
                redirect('/stock_outs.php?action=view&id=' . $soId);
            } catch (Exception $e) {
                Session::flash('error', '建立失敗: ' . $e->getMessage());
                redirect('/stock_outs.php');
            }
        }

        $pageTitle = '新增出庫單';
        $currentPage = 'stock_outs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/stock_outs/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'confirm':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/stock_outs.php'); }
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        // 批次確認勾選品項（POST）
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['item_ids'])) {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/stock_outs.php?action=view&id=' . $id); }
            $itemQtys = isset($_POST['item_qtys']) ? $_POST['item_qtys'] : array();
            $confirmed = 0;
            $failed = 0;
            foreach ($_POST['item_ids'] as $itemId) {
                $confirmQty = isset($itemQtys[$itemId]) ? (int)$itemQtys[$itemId] : 0;
                try {
                    if ($model->confirmStockOutItem($id, (int)$itemId, Auth::id(), $confirmQty > 0 ? $confirmQty : null)) {
                        $confirmed++;
                    } else {
                        $failed++;
                    }
                } catch (Exception $e) {
                    $failed++;
                }
            }
            if ($confirmed > 0) {
                try {
                    require_once __DIR__ . '/../modules/accounting/AutoJournalService.php';
                    AutoJournalService::onStockOutConfirmed($id);
                } catch (Exception $autoJournalEx) {
                    error_log('AutoJournal stock_out error: ' . $autoJournalEx->getMessage());
                }
                AuditLog::log('stock_outs', 'confirm', $id, $confirmed . ' 品項確認出庫');
            }
            $msg = $confirmed . ' 個品項已確認出庫';
            if ($failed > 0) $msg .= '，' . $failed . ' 個失敗';
            Session::flash($confirmed > 0 ? 'success' : 'error', $msg);
        }
        redirect('/stock_outs.php?action=view&id=' . $id);
        break;

    case 'cancel':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/stock_outs.php'); }
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        if (verify_csrf()) {
            try {
                if ($model->cancelStockOut($id, Auth::id())) {
                    AuditLog::log('stock_outs', 'cancel', $id, '出庫單已取消');
                    Session::flash('success', '出庫單已取消（已出庫品項紀錄保留）');
                } else {
                    Session::flash('error', '取消失敗');
                }
            } catch (Exception $e) {
                Session::flash('error', '取消失敗：' . $e->getMessage());
            }
        }
        redirect('/stock_outs.php?action=view&id=' . $id);
        break;

    // ---- 手動餘料入庫 ----
    case 'manual_return':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/stock_outs.php'); }
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['item_ids'])) {
            Session::flash('error', '無效請求'); redirect('/stock_outs.php?action=view&id=' . $id);
        }
        if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/stock_outs.php?action=view&id=' . $id); }

        $so = $model->getStockOutById($id);
        if (!$so) { Session::flash('error', '出庫單不存在'); redirect('/stock_outs.php'); }

        $returnQtys = isset($_POST['return_qtys']) ? $_POST['return_qtys'] : array();
        $db = Database::getInstance();

        // 取出庫單明細
        $soItems = $model->getStockOutItems($id);
        $itemMap = array();
        foreach ($soItems as $si) { $itemMap[(int)$si['id']] = $si; }

        // 組入庫單明細
        $siItems = array();
        foreach ($_POST['item_ids'] as $itemId) {
            $itemId = (int)$itemId;
            if (!isset($itemMap[$itemId])) continue;
            $item = $itemMap[$itemId];
            $qty = isset($returnQtys[$itemId]) ? (int)$returnQtys[$itemId] : 0;
            $maxQty = (int)(isset($item['quantity']) ? $item['quantity'] : 0);
            if ($qty <= 0 || $qty > $maxQty) continue;

            $siItems[] = array(
                'product_id' => !empty($item['product_id']) ? $item['product_id'] : null,
                'model'      => isset($item['model']) ? $item['model'] : (isset($item['product_model']) ? $item['product_model'] : ''),
                'product_name' => isset($item['product_name']) ? $item['product_name'] : (isset($item['db_product_name']) ? $item['db_product_name'] : ''),
                'spec'       => isset($item['spec']) ? $item['spec'] : '',
                'unit'       => isset($item['unit']) ? $item['unit'] : '',
                'quantity'   => $qty,
                'unit_price' => isset($item['unit_cost']) ? $item['unit_cost'] : (isset($item['unit_price']) ? $item['unit_price'] : 0),
                'note'       => '',
            );
        }

        if (empty($siItems)) {
            Session::flash('error', '沒有有效的退料品項');
            redirect('/stock_outs.php?action=view&id=' . $id);
        }

        // 建立入庫單
        $branchName = '';
        if (!empty($so['branch_id'])) {
            $bn = $db->prepare("SELECT name FROM branches WHERE id = ?");
            $bn->execute(array($so['branch_id']));
            $branchName = $bn->fetchColumn() ?: '';
        }

        $siData = array(
            'si_date'       => date('Y-m-d'),
            'warehouse_id'  => $so['warehouse_id'],
            'branch_id'     => !empty($so['branch_id']) ? $so['branch_id'] : null,
            'branch_name'   => $branchName,
            'source_type'   => 'manual_return',
            'source_id'     => $id,
            'source_number' => $so['so_number'],
            'note'          => '手動餘料入庫，來源出庫單 ' . $so['so_number'],
            'items'         => $siItems,
            'created_by'    => Auth::id(),
        );

        try {
            $siId = $model->createStockIn($siData);
            AuditLog::log('stock_ins', 'create', $siId, '手動餘料入庫，來源出庫單 ' . $so['so_number']);
            Session::flash('success', '入庫單已建立，請確認入庫');
            redirect('/stock_ins.php?action=view&id=' . $siId);
        } catch (Exception $e) {
            Session::flash('error', '建立入庫單失敗：' . $e->getMessage());
            redirect('/stock_outs.php?action=view&id=' . $id);
        }
        break;

    // ---- 新增備品 AJAX ----
    case 'ajax_add_spare':
        if (!$canManage) { header('Content-Type: application/json'); echo json_encode(array('error' => '無權限')); break; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Content-Type: application/json'); echo json_encode(array('error' => '無效請求')); break; }
        $soId = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        try {
            $itemId = $model->addSpareItem($soId, $_POST);
            header('Content-Type: application/json');
            echo json_encode(array('success' => true, 'item_id' => $itemId));
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(array('error' => $e->getMessage()));
        }
        break;

    // ---- 移除備品 AJAX ----
    case 'ajax_remove_spare':
        if (!$canManage) { header('Content-Type: application/json'); echo json_encode(array('error' => '無權限')); break; }
        $soId = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $itemId = (int)(isset($_GET['item_id']) ? $_GET['item_id'] : 0);
        try {
            $ok = $model->removeSpareItem($soId, $itemId);
            header('Content-Type: application/json');
            echo json_encode(array('success' => $ok));
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(array('error' => $e->getMessage()));
        }
        break;

    // ---- 產品分類 AJAX ----
    case 'ajax_categories':
        header('Content-Type: application/json');
        $db = Database::getInstance();
        $parentId = (int)(isset($_GET['parent_id']) ? $_GET['parent_id'] : 0);
        if ($parentId > 0) {
            $cats = $db->prepare("SELECT id, name, parent_id FROM product_categories WHERE parent_id = ? ORDER BY name");
            $cats->execute(array($parentId));
            echo json_encode($cats->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $cats = $db->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($cats);
        }
        break;

    // ---- 搜尋產品 AJAX（含庫存）----
    case 'ajax_products':
        header('Content-Type: application/json');
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $categoryId = (int)(isset($_GET['category_id']) ? $_GET['category_id'] : 0);
        $warehouseId = (int)(isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : 0);

        $db = Database::getInstance();
        $where = 'p.is_active = 1';
        $params = array();

        if ($categoryId > 0) {
            $catIds = array($categoryId);
            $queue = array($categoryId);
            while (!empty($queue)) {
                $pid = array_shift($queue);
                $subStmt = $db->prepare('SELECT id FROM product_categories WHERE parent_id = ?');
                $subStmt->execute(array($pid));
                foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
                    $catIds[] = (int)$sub['id'];
                    $queue[] = (int)$sub['id'];
                }
            }
            $ph = implode(',', array_fill(0, count($catIds), '?'));
            $where .= " AND p.category_id IN ($ph)";
            $params = array_merge($params, $catIds);
        }
        if (strlen($keyword) >= 1) {
            $where .= ' AND (p.name LIKE ? OR p.model LIKE ?)';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }
        if (!$categoryId && strlen($keyword) < 1) {
            echo json_encode(array());
            break;
        }

        $stockJoin = '';
        $stockCol = '0 AS stock_qty';
        if ($warehouseId > 0) {
            $stockJoin = "LEFT JOIN inventory inv ON inv.product_id = p.id AND inv.warehouse_id = $warehouseId";
            $stockCol = 'COALESCE(inv.stock_qty, 0) AS stock_qty';
        } else {
            $stockJoin = "LEFT JOIN (SELECT product_id, SUM(stock_qty) AS stock_qty FROM inventory GROUP BY product_id) inv ON inv.product_id = p.id";
            $stockCol = 'COALESCE(inv.stock_qty, 0) AS stock_qty';
        }

        $stmt = $db->prepare("
            SELECT p.id, p.name, p.model, p.unit, p.price, p.cost, p.brand,
                   pc.name AS category_name, $stockCol
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            $stockJoin
            WHERE $where
            ORDER BY p.name
            LIMIT 50
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ---- 客戶搜尋 AJAX ----
    case 'ajax_search_customer':
        header('Content-Type: application/json');
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        if (strlen($keyword) < 1) { echo json_encode(array()); break; }
        $db = Database::getInstance();
        $kw = '%' . $keyword . '%';
        $stmt = $db->prepare("SELECT id, customer_no, name, site_address FROM customers WHERE is_active = 1 AND (name LIKE ? OR customer_no LIKE ?) ORDER BY name LIMIT 15");
        $stmt->execute(array($kw, $kw));
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        redirect('/stock_outs.php');
        break;
}
