<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
// 財務權限
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view') && !in_array(Auth::user()['role'], array('boss','manager'))) {
    Session::flash('error', '無權限'); redirect('/');
}

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$canManage = Auth::hasPermission('finance.manage') || in_array(Auth::user()['role'], array('boss','manager'));

switch ($action) {
    case 'list':
        $level1Filter = $_GET['level1'] ?? '';
        $keyword = trim($_GET['keyword'] ?? '');
        $showInactive = !empty($_GET['show_inactive']);

        $where = '1=1';
        $params = array();
        
        if ($level1Filter) {
            $where .= ' AND level1 = ?';
            $params[] = $level1Filter;
        }
        if ($keyword) {
            $where .= ' AND (code LIKE ? OR name LIKE ? OR level3 LIKE ?)';
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        if (!$showInactive) {
            $where .= ' AND is_active = 1';
        }

        $stmt = $db->prepare("SELECT * FROM chart_of_accounts WHERE $where ORDER BY code");
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();

        // 取所有一階科目做篩選
        $level1Options = $db->query("SELECT DISTINCT level1 FROM chart_of_accounts ORDER BY level1")->fetchAll(PDO::FETCH_COLUMN);

        $pageTitle = '會計科目管理';
        $currentPage = 'chart_of_accounts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/chart_of_accounts/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
    case 'edit':
        if (!$canManage) { Session::flash('error', '無權限'); redirect('/chart_of_accounts.php'); }
        
        $account = null;
        if ($action === 'edit') {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM chart_of_accounts WHERE id = ?");
            $stmt->execute(array($id));
            $account = $stmt->fetch();
            if (!$account) { Session::flash('error', '科目不存在'); redirect('/chart_of_accounts.php'); }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) { Session::flash('error', '安全驗證失敗'); redirect('/chart_of_accounts.php'); }
            
            $data = array(
                trim($_POST['code'] ?? ''),
                trim($_POST['name'] ?? ''),
                trim($_POST['level1'] ?? ''),
                trim($_POST['level2'] ?? ''),
                trim($_POST['level3'] ?? ''),
                trim($_POST['level3_code'] ?? ''),
                isset($_POST['is_active']) ? 1 : 0,
            );

            if ($action === 'edit') {
                $db->prepare("UPDATE chart_of_accounts SET code=?, name=?, level1=?, level2=?, level3=?, level3_code=?, is_active=? WHERE id=?")->execute(array_merge($data, array($account['id'])));
                Session::flash('success', '科目已更新');
            } else {
                $db->prepare("INSERT INTO chart_of_accounts (code, name, level1, level2, level3, level3_code, is_active) VALUES (?,?,?,?,?,?,?)")->execute($data);
                Session::flash('success', '科目已新增');
            }
            redirect('/chart_of_accounts.php');
        }

        $pageTitle = $action === 'edit' ? '編輯科目' : '新增科目';
        $currentPage = 'chart_of_accounts';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/chart_of_accounts/form.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;
}
