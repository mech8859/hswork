<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('settings.manage') && !in_array(Auth::user()['role'], array('boss','manager'))) {
    Session::flash('error', '無權限'); redirect('/');
}

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $items = $db->query("SELECT * FROM engineering_items ORDER BY category, sort_order, name")->fetchAll();
        $categories = $db->query("SELECT DISTINCT category FROM engineering_items ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
        
        $pageTitle = '工程項次管理';
        $currentPage = 'dropdown_options';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/engineering_items/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
    case 'edit':
        $item = null;
        if ($action === 'edit') {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM engineering_items WHERE id = ?");
            $stmt->execute(array($id));
            $item = $stmt->fetch();
            if (!$item) { Session::flash('error', '項目不存在'); redirect('/engineering_items.php'); }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/engineering_items.php'); }
            $data = array(
                trim($_POST['category'] ?? ''),
                trim($_POST['name'] ?? ''),
                trim($_POST['unit'] ?? '式'),
                (int)($_POST['default_price'] ?? 0),
                (int)($_POST['default_cost'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
                (int)($_POST['sort_order'] ?? 0),
            );

            if ($action === 'edit') {
                $db->prepare("UPDATE engineering_items SET category=?, name=?, unit=?, default_price=?, default_cost=?, is_active=?, sort_order=? WHERE id=?")->execute(array_merge($data, array($item['id'])));
                Session::flash('success', '已更新');
            } else {
                $db->prepare("INSERT INTO engineering_items (category, name, unit, default_price, default_cost, is_active, sort_order) VALUES (?,?,?,?,?,?,?)")->execute($data);
                Session::flash('success', '已新增');
            }
            redirect('/engineering_items.php');
        }

        $pageTitle = $action === 'edit' ? '編輯工程項次' : '新增工程項次';
        $currentPage = 'dropdown_options';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/engineering_items/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    // AJAX: 取得工程項次（報價單用）
    case 'ajax_list':
        header('Content-Type: application/json');
        $cat = $_GET['category'] ?? '';
        if ($cat) {
            $stmt = $db->prepare("SELECT * FROM engineering_items WHERE is_active = 1 AND category = ? ORDER BY sort_order, name");
            $stmt->execute(array($cat));
        } else {
            $stmt = $db->query("SELECT * FROM engineering_items WHERE is_active = 1 ORDER BY category, sort_order, name");
        }
        echo json_encode($stmt->fetchAll());
        exit;

    case 'ajax_categories':
        header('Content-Type: application/json');
        echo json_encode($db->query("SELECT DISTINCT category FROM engineering_items WHERE is_active = 1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN));
        exit;
}
