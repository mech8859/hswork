<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Auth::user()['role'], array('boss', 'manager'))) {
    Session::flash('error', '僅系統管理者可查看');
    redirect('/');
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $filters = array(
            'user_id'        => $_GET['user_id'] ?? '',
            'module'         => $_GET['module'] ?? '',
            'filter_action'  => $_GET['filter_action'] ?? '',
            'date_from'      => $_GET['date_from'] ?? '',
            'date_to'        => $_GET['date_to'] ?? '',
            'keyword'        => $_GET['keyword'] ?? '',
        );
        $page = max(1, (int)($_GET['page'] ?? 1));
        $result = AuditLog::getLogs($filters, $page);
        $onlineUsers = AuditLog::getOnlineUsers();

        // 取人員列表做篩選
        $db = Database::getInstance();
        $allUsers = $db->query("SELECT id, real_name FROM users ORDER BY real_name")->fetchAll();

        $pageTitle = '操作日誌';
        $currentPage = 'audit_logs';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/audit_logs/list.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;
}
