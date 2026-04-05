<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('settings.manage');
require_once __DIR__ . '/../modules/notifications/NotificationSettingsModel.php';

$model = new NotificationSettingsModel();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $filterModule = isset($_GET['module']) ? $_GET['module'] : '';
        $rules = $model->getAll($filterModule ? $filterModule : null);
        $registry = $model->getModuleRegistry();
        $roles = $model->getRoles();
        $pageTitle = '通知設定';
        $currentPage = 'notification_settings';
        require __DIR__ . '/../templates/layouts/header.php';
        require __DIR__ . '/../templates/notifications/settings.php';
        require __DIR__ . '/../templates/layouts/footer.php';
        break;

    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/notification_settings.php');
        }
        $data = array(
            'module'           => isset($_POST['module']) ? $_POST['module'] : '',
            'event'            => isset($_POST['event']) ? $_POST['event'] : '',
            'condition_field'  => isset($_POST['condition_field']) ? $_POST['condition_field'] : '',
            'condition_value'  => isset($_POST['condition_value']) ? $_POST['condition_value'] : '',
            'notify_type'      => isset($_POST['notify_type']) ? $_POST['notify_type'] : 'role',
            'notify_target'    => isset($_POST['notify_target']) ? (is_array($_POST['notify_target']) ? implode(',', $_POST['notify_target']) : $_POST['notify_target']) : '',
            'branch_scope'     => isset($_POST['branch_scope']) ? $_POST['branch_scope'] : 'same',
            'title_template'   => isset($_POST['title_template']) ? $_POST['title_template'] : '',
            'message_template' => isset($_POST['message_template']) ? $_POST['message_template'] : '',
            'link_template'    => isset($_POST['link_template']) ? $_POST['link_template'] : '',
            'sort_order'       => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0,
            'created_by'       => Auth::id(),
        );
        if (empty($data['module']) || empty($data['event']) || empty($data['notify_target'])) {
            Session::flash('error', '模組、事件和通知對象為必填');
            redirect('/notification_settings.php');
        }
        $model->create($data);
        Session::flash('success', '通知規則已新增');
        redirect('/notification_settings.php' . ($data['module'] ? '?module=' . urlencode($data['module']) : ''));
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            json_response(array('error' => '缺少 ID'));
        }
        $data = array(
            'module'           => isset($_POST['module']) ? $_POST['module'] : '',
            'event'            => isset($_POST['event']) ? $_POST['event'] : '',
            'condition_field'  => isset($_POST['condition_field']) ? $_POST['condition_field'] : '',
            'condition_value'  => isset($_POST['condition_value']) ? $_POST['condition_value'] : '',
            'notify_type'      => isset($_POST['notify_type']) ? $_POST['notify_type'] : 'role',
            'notify_target'    => isset($_POST['notify_target']) ? (is_array($_POST['notify_target']) ? implode(',', $_POST['notify_target']) : $_POST['notify_target']) : '',
            'branch_scope'     => isset($_POST['branch_scope']) ? $_POST['branch_scope'] : 'same',
            'title_template'   => isset($_POST['title_template']) ? $_POST['title_template'] : '',
            'message_template' => isset($_POST['message_template']) ? $_POST['message_template'] : '',
            'link_template'    => isset($_POST['link_template']) ? $_POST['link_template'] : '',
            'sort_order'       => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0,
        );
        $model->update($id, $data);
        json_response(array('success' => true));
        break;

    case 'toggle':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('error' => '方法不允許'), 405);
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id) {
            $model->toggleActive($id);
            json_response(array('success' => true));
        }
        json_response(array('error' => '缺少 ID'));
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            Session::flash('error', '安全驗證失敗');
            redirect('/notification_settings.php');
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $module = isset($_POST['module']) ? $_POST['module'] : '';
        if ($id) {
            $model->delete($id);
            Session::flash('success', '通知規則已刪除');
        }
        redirect('/notification_settings.php' . ($module ? '?module=' . urlencode($module) : ''));
        break;

    default:
        redirect('/notification_settings.php');
}
