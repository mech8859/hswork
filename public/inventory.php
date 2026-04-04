<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('inventory.manage') && !Auth::hasPermission('inventory.view')) {
    Session::flash('error', '無權限查看此頁面');
    redirect('/');
}
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

$model = new InventoryModel();
$action = !empty($_GET['action']) ? $_GET['action'] : 'list';
$canManage = Auth::hasPermission('inventory.manage');

switch ($action) {

    // ============================================================
    // 庫存列表
    // ============================================================
    case 'list':
        $filters = array(
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
            'category_id'  => !empty($_GET['category_id']) ? $_GET['category_id'] : '',
            'keyword'      => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'has_stock'    => isset($_GET['has_stock']) ? $_GET['has_stock'] : '',
            'low_stock'    => isset($_GET['low_stock']) ? $_GET['low_stock'] : '',
            'page'         => !empty($_GET['page']) ? (int)$_GET['page'] : 1,
            'per_page'     => 100,
        );
        $result = $model->getInventoryList($filters);
        $records = $result['data'];
        $totalRecords = $result['total'];
        $currentPageNum = $result['page'];
        $totalPages = $result['total_pages'];
        $warehouses = $model->getWarehouses();
        $categories = $model->getCategories();
        $summary = $model->getWarehouseSummary();
        $lowStockCount = $model->getLowStockCount();
        // 全域合計（不受分頁限制）
        $grandTotal = $model->getGrandTotal($filters);

        $pageTitle = '庫存管理';
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 庫存明細
    // ============================================================
    case 'view':
        $productId = (int)(!empty($_GET['product_id']) ? $_GET['product_id'] : 0);
        if (!$productId) {
            Session::flash('error', '請指定商品');
            redirect('/inventory.php');
        }
        $inventoryRows = $model->getProductInventory($productId);
        if (empty($inventoryRows)) {
            $db = Database::getInstance();
            $pStmt = $db->prepare('SELECT p.id AS product_id, p.name AS product_name, p.model AS product_model, p.unit, p.cost, p.price AS sell_price, p.retail_price, p.category_id, pc.name AS category_name, pc.parent_id AS cat_parent_id, pc2.name AS cat_parent_name, pc2.parent_id AS cat_grandparent_id, pc3.name AS cat_grandparent_name FROM products p LEFT JOIN product_categories pc ON p.category_id = pc.id LEFT JOIN product_categories pc2 ON pc.parent_id = pc2.id LEFT JOIN product_categories pc3 ON pc2.parent_id = pc3.id WHERE p.id = ?');
            $pStmt->execute(array($productId));
            $product = $pStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                Session::flash('error', '產品不存在');
                redirect('/inventory.php');
            }
        } else {
            $product = $inventoryRows[0];
        }
        $transactions = $model->getTransactions(array('product_id' => $productId));
        $warehouses = $model->getWarehouses();

        $pageTitle = '庫存明細 - ' . $product['product_name'];
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 手動入庫/出庫
    // ============================================================
    case 'adjust':
        if (!$canManage) {
            Session::flash('error', '無權限執行此操作');
            redirect('/inventory.php');
        }
        $warehouses = $model->getWarehouses();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $productId = (int)$_POST['product_id'];
            $warehouseId = (int)$_POST['warehouse_id'];
            $adjustType = $_POST['adjust_type']; // manual_in or manual_out
            $quantity = abs((int)$_POST['quantity']);
            $note = isset($_POST['note']) ? trim($_POST['note']) : '';

            if (!$productId || !$warehouseId || !$quantity) {
                Session::flash('error', '請填寫完整資料');
                redirect('/inventory.php?action=adjust');
            }

            if ($adjustType === 'manual_out') {
                $quantity = -$quantity;
            }

            try {
                $model->adjustStock($productId, $warehouseId, $quantity, $adjustType, 'manual', null, $note, Auth::id());
                Session::flash('success', ($adjustType === 'manual_in' ? '入庫' : '出庫') . '成功');
                redirect('/inventory.php?action=view&product_id=' . $productId);
            } catch (Exception $e) {
                Session::flash('error', '操作失敗: ' . $e->getMessage());
                redirect('/inventory.php?action=adjust');
            }
        }

        $pageTitle = '入庫/出庫';
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/adjust.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 更新安全庫存
    // ============================================================
    case 'update_min_qty':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/inventory.php');
        }
        verify_csrf();
        $inventoryId = (int)$_POST['inventory_id'];
        $minQty = (int)$_POST['min_qty'];
        $model->updateMinQty($inventoryId, $minQty);
        Session::flash('success', '安全庫存已更新');
        $returnProduct = !empty($_POST['product_id']) ? '&product_id=' . (int)$_POST['product_id'] : '';
        redirect('/inventory.php?action=view' . $returnProduct);
        break;

    // ============================================================
    // 異動記錄
    // ============================================================
    case 'transactions':
        $filters = array(
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
            'type'         => !empty($_GET['type']) ? $_GET['type'] : '',
            'keyword'      => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'date_from'    => !empty($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to'      => !empty($_GET['date_to']) ? $_GET['date_to'] : '',
        );
        $transactions = $model->getTransactions($filters, 200);
        $warehouses = $model->getWarehouses();
        $typeOptions = InventoryModel::transactionTypeOptions();

        $pageTitle = '庫存異動記錄';
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/transactions.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 盤點列表
    // ============================================================
    case 'stocktake_list':
        $filters = array(
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
            'status'       => !empty($_GET['status']) ? $_GET['status'] : '',
        );
        $stocktakes = $model->getStocktakeList($filters);
        $warehouses = $model->getWarehouses();

        $pageTitle = '盤點管理';
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/stocktake_list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 建立盤點
    // ============================================================
    case 'stocktake_create':
        if (!$canManage) {
            Session::flash('error', '無權限');
            redirect('/inventory.php?action=stocktake_list');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $warehouseId = (int)$_POST['warehouse_id'];
            $note = isset($_POST['note']) ? trim($_POST['note']) : '';
            if (!$warehouseId) {
                Session::flash('error', '請選擇倉庫');
                redirect('/inventory.php?action=stocktake_list');
            }
            $stocktakerId = !empty($_POST['stocktaker_id']) ? (int)$_POST['stocktaker_id'] : null;
            $id = $model->createStocktake($warehouseId, $note, Auth::id());
            // 儲存盤點人
            if ($stocktakerId && $id) {
                $db = Database::getInstance();
                $stName = $db->prepare("SELECT name FROM users WHERE id = ?");
                $stName->execute(array($stocktakerId));
                $stNameVal = $stName->fetchColumn();
                $db->prepare("UPDATE stocktakes SET stocktaker_id = ?, stocktaker_name = ? WHERE id = ?")
                   ->execute(array($stocktakerId, $stNameVal ? $stNameVal : '', $id));
            }
            Session::flash('success', '盤點單已建立');
            redirect('/inventory.php?action=stocktake_edit&id=' . $id);
        }
        $warehouses = $model->getWarehouses();
        try {
            $stDb = Database::getInstance();
            $staffList = $stDb->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $stEx) {
            $staffList = array();
        }
        $pageTitle = '建立盤點';
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/stocktake_create.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 編輯盤點
    // ============================================================
    case 'stocktake_edit':
        $id = (int)(!empty($_GET['id']) ? $_GET['id'] : 0);
        $stocktake = $model->getStocktake($id);
        if (!$stocktake) {
            Session::flash('error', '找不到盤點單');
            redirect('/inventory.php?action=stocktake_list');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage && $stocktake['status'] === '盤點中') {
            verify_csrf();
            $postAction = isset($_POST['post_action']) ? $_POST['post_action'] : 'save';

            // 儲存盤點數量
            $items = array();
            if (!empty($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $itemId => $data) {
                    $items[] = array(
                        'id' => (int)$itemId,
                        'actual_qty' => isset($data['actual_qty']) ? $data['actual_qty'] : null,
                        'system_qty' => isset($data['system_qty']) ? (int)$data['system_qty'] : 0,
                        'note' => isset($data['note']) ? $data['note'] : '',
                    );
                }
            }
            $model->updateStocktakeItems($items);

            if ($postAction === 'complete') {
                $model->completeStocktake($id, Auth::id());
                Session::flash('success', '盤點已完成，差異已調整');
                redirect('/inventory.php?action=stocktake_list');
            } else {
                Session::flash('success', '盤點資料已儲存');
                redirect('/inventory.php?action=stocktake_edit&id=' . $id);
            }
        }

        $stocktakeItems = $model->getStocktakeItems($id);

        $pageTitle = '盤點 - ' . $stocktake['stocktake_number'];
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/stocktake_edit.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // ============================================================
    // 取消盤點
    // ============================================================
    case 'stocktake_cancel':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/inventory.php?action=stocktake_list');
        }
        verify_csrf();
        $id = (int)$_POST['id'];
        $model->cancelStocktake($id);
        Session::flash('success', '盤點已取消');
        redirect('/inventory.php?action=stocktake_list');
        break;

    // ============================================================
    // 倉庫管理
    // ============================================================
    case 'warehouses':
        if (!$canManage) {
            Session::flash('error', '無權限');
            redirect('/inventory.php');
        }
        $allWarehouses = $model->getAllWarehouses();
        $branches = $model->getBranches();

        $pageTitle = '倉庫管理';
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/warehouses.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'warehouse_save':
        if (!$canManage || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/inventory.php?action=warehouses');
        }
        verify_csrf();
        $whId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $data = array(
            'branch_id' => (int)$_POST['branch_id'],
            'code' => trim($_POST['code']),
            'name' => trim($_POST['name']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );
        if (!$data['branch_id'] || !$data['code'] || !$data['name']) {
            Session::flash('error', '請填寫完整資料');
            redirect('/inventory.php?action=warehouses');
        }
        if ($whId) {
            $model->updateWarehouse($whId, $data);
            Session::flash('success', '倉庫已更新');
        } else {
            $model->createWarehouse($data);
            Session::flash('success', '倉庫已建立');
        }
        redirect('/inventory.php?action=warehouses');
        break;

    // ============================================================
    // 匯出CSV
    // ============================================================
    case 'export':
        $filters = array(
            'warehouse_id' => !empty($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
            'category_id'  => !empty($_GET['category_id']) ? $_GET['category_id'] : '',
            'keyword'      => !empty($_GET['keyword']) ? $_GET['keyword'] : '',
            'has_stock'    => isset($_GET['has_stock']) ? $_GET['has_stock'] : '',
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory_' . date('Ymd') . '.csv"');
        $model->exportCsv($filters);
        exit;

    // ============================================================
    // AJAX: 搜尋產品
    // ============================================================
    case 'ajax_search_products':
        header('Content-Type: application/json');
        $keyword = !empty($_GET['q']) ? $_GET['q'] : '';
        if (mb_strlen($keyword) < 1) {
            echo json_encode(array());
            exit;
        }
        echo json_encode($model->searchProducts($keyword));
        exit;

    // ============================================================
    // 低庫存警示
    // ============================================================
    case 'low_stock':
        $lowStockItems = $model->getLowStockItems();
        $pageTitle = '低庫存警示';
        $currentPage = 'inventory';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/inventory/low_stock.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    default:
        redirect('/inventory.php');
        break;
}
