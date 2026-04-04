<?php
/**
 * 出貨單管理
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/delivery/DeliveryModel.php';

$canManage = Auth::hasPermission('inventory.manage');
$canView = Auth::hasPermission('inventory.view');
if (!$canManage && !$canView) {
    Session::flash('error', '無權限');
    redirect('/index.php');
}

$model = new DeliveryModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'month'        => isset($_GET['month']) ? $_GET['month'] : date('Y-m'),
            'status'       => isset($_GET['status']) ? $_GET['status'] : '',
            'keyword'      => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'warehouse_id' => isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : '',
        );
        $orders = $model->getList($filters);
        $warehouses = $model->getWarehouses();
        $pageTitle = '出貨單管理';
        $currentPage = 'delivery_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/delivery_orders/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/delivery_orders.php'); }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/delivery_orders.php'); }
            $orderId = $model->create($_POST);
            AuditLog::log('delivery_orders', 'create', $orderId, isset($_POST['case_name']) ? $_POST['case_name'] : '');
            Session::flash('success', '出貨單已建立');
            redirect('/delivery_orders.php?action=view&id=' . $orderId);
        }
        $record = null;
        $warehouses = $model->getWarehouses();
        $branches = $model->getBranches();
        $cases = $model->getCaseOptions();
        $quotations = $model->getQuotationOptions();
        $pageTitle = '新增出貨單';
        $currentPage = 'delivery_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/delivery_orders/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create_from_quotation':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/delivery_orders.php'); }
        $quotationId = (int)(isset($_GET['quotation_id']) ? $_GET['quotation_id'] : 0);
        if (!$quotationId) { Session::flash('error', '請指定報價單'); redirect('/delivery_orders.php'); }

        $prefill = $model->createFromQuotation($quotationId);
        if (!$prefill) { Session::flash('error', '報價單不存在'); redirect('/delivery_orders.php'); }

        $record = $prefill;
        $warehouses = $model->getWarehouses();
        $branches = $model->getBranches();
        $cases = $model->getCaseOptions();
        $quotations = $model->getQuotationOptions();
        $pageTitle = '從報價單建立出貨單';
        $currentPage = 'delivery_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/delivery_orders/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'edit':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/delivery_orders.php'); }
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) { Session::flash('error', '出貨單不存在'); redirect('/delivery_orders.php'); }
        if ($record['status'] !== '草稿') {
            Session::flash('error', '僅草稿狀態可編輯');
            redirect('/delivery_orders.php?action=view&id=' . $id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/delivery_orders.php?action=edit&id=' . $id); }
            $model->update($id, $_POST);
            AuditLog::log('delivery_orders', 'update', $id, isset($_POST['case_name']) ? $_POST['case_name'] : '');
            Session::flash('success', '出貨單已更新');
            redirect('/delivery_orders.php?action=view&id=' . $id);
        }
        $warehouses = $model->getWarehouses();
        $branches = $model->getBranches();
        $cases = $model->getCaseOptions();
        $quotations = $model->getQuotationOptions();
        $pageTitle = '編輯出貨單';
        $currentPage = 'delivery_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/delivery_orders/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'view':
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $record = $model->getById($id);
        if (!$record) { Session::flash('error', '出貨單不存在'); redirect('/delivery_orders.php'); }
        $pageTitle = '出貨單 ' . $record['do_number'];
        $currentPage = 'delivery_orders';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/delivery_orders/view.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'confirm':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/delivery_orders.php'); }
        if (verify_csrf()) {
            $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
            try {
                $stockOutId = $model->confirm($id);
                if ($stockOutId) {
                    AuditLog::log('delivery_orders', 'confirm', $id, '已確認，出庫單ID: ' . $stockOutId);
                    Session::flash('success', '出貨單已確認，出庫單已自動產生');
                } else {
                    Session::flash('error', '確認失敗，請確認狀態是否正確');
                }
            } catch (Exception $e) {
                Session::flash('error', '確認失敗：' . $e->getMessage());
            }
        }
        redirect('/delivery_orders.php?action=view&id=' . (int)(isset($_GET['id']) ? $_GET['id'] : 0));
        break;

    case 'ship':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/delivery_orders.php'); }
        if (verify_csrf()) {
            $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
            if ($model->ship($id)) {
                AuditLog::log('delivery_orders', 'ship', $id, '已出貨');
                Session::flash('success', '已標記為出貨');
            } else {
                Session::flash('error', '操作失敗');
            }
        }
        redirect('/delivery_orders.php?action=view&id=' . (int)(isset($_GET['id']) ? $_GET['id'] : 0));
        break;

    case 'complete':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/delivery_orders.php'); }
        if (verify_csrf()) {
            $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
            if ($model->complete($id)) {
                AuditLog::log('delivery_orders', 'complete', $id, '已完成');
                Session::flash('success', '出貨單已完成');
            } else {
                Session::flash('error', '操作失敗');
            }
        }
        redirect('/delivery_orders.php?action=view&id=' . (int)(isset($_GET['id']) ? $_GET['id'] : 0));
        break;

    case 'delete':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/delivery_orders.php'); }
        if (verify_csrf()) {
            $model->delete((int)(isset($_GET['id']) ? $_GET['id'] : 0));
            AuditLog::log('delivery_orders', 'delete', (int)(isset($_GET['id']) ? $_GET['id'] : 0), '');
            Session::flash('success', '出貨單已刪除');
        }
        redirect('/delivery_orders.php');
        break;

    case 'ajax_products':
        header('Content-Type: application/json');
        $keyword = trim(isset($_GET['keyword']) ? $_GET['keyword'] : '');
        if (strlen($keyword) < 1) {
            echo json_encode(array());
            exit;
        }
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.model, p.unit, p.price, p.cost
            FROM products p
            WHERE p.is_active = 1 AND (p.name LIKE ? OR p.model LIKE ?)
            ORDER BY p.name
            LIMIT 30
        ");
        $kw = '%' . $keyword . '%';
        $stmt->execute(array($kw, $kw));
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    default:
        redirect('/delivery_orders.php');
        break;
}
