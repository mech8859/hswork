<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/VendorProductModel.php';

$model = new VendorProductModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        Auth::requirePermission('procurement.view');
        $filters = array(
            'vendor_id' => isset($_GET['vendor_id']) ? $_GET['vendor_id'] : '',
            'keyword'   => isset($_GET['keyword']) ? $_GET['keyword'] : '',
            'mapped'    => isset($_GET['mapped']) ? $_GET['mapped'] : '',
        );
        $items = $model->getList($filters);
        $vendors = $model->getVendorsWithMappings();
        $stats = $model->getStats();

        // 所有廠商（給新增用）
        $db = Database::getInstance();
        $allVendors = $db->query("SELECT id, name, short_name FROM vendors WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = '廠商產品對照';
        $currentPage = 'vendor_products';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/vendor_products/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        Auth::requirePermission('procurement.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/vendor_products.php');
        }
        $model->create($_POST);
        Session::flash('success', '已新增對照');
        redirect('/vendor_products.php?vendor_id=' . (isset($_POST['vendor_id']) ? $_POST['vendor_id'] : ''));
        break;

    case 'update':
        Auth::requirePermission('procurement.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            json_response(array('error' => '安全驗證失敗'), 403);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) { json_response(array('error' => '缺少 ID')); }
        $model->update($id, $_POST);
        json_response(array('success' => true));
        break;

    case 'delete':
        Auth::requirePermission('procurement.manage');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id) {
            $model->delete($id);
            json_response(array('success' => true));
        }
        json_response(array('error' => '缺少 ID'));
        break;

    // AJAX: 進貨單用 — 搜尋廠商產品對照
    case 'search_api':
        $vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;
        $keyword = isset($_GET['q']) ? $_GET['q'] : '';
        if (!$vendorId || strlen($keyword) < 1) {
            json_response(array());
        }
        $results = $model->searchForReceipt($vendorId, $keyword);
        json_response($results);
        break;

    // AJAX: 產品搜尋（給對照時選產品用）
    case 'search_products':
        $kw = isset($_GET['q']) ? $_GET['q'] : '';
        if (strlen($kw) < 1) { json_response(array()); }
        $db = Database::getInstance();
        $kwLike = '%' . $kw . '%';
        $stmt = $db->prepare("SELECT id, name, model, brand FROM products WHERE is_active = 1 AND (name LIKE ? OR model LIKE ? OR brand LIKE ?) ORDER BY name LIMIT 20");
        $stmt->execute(array($kwLike, $kwLike, $kwLike));
        json_response($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        redirect('/vendor_products.php');
}
