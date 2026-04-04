<?php
/**
 * 通知系統 API
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/notifications/NotificationModel.php';

$model = new NotificationModel();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$userId = Auth::id();

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    // 取得未讀通知
    case 'unread':
        $notifications = $model->getUnread($userId);
        $count = $model->getUnreadCount($userId);
        echo json_encode(array('success' => true, 'count' => $count, 'data' => $notifications));
        break;

    // 取得所有通知
    case 'all':
        $notifications = $model->getAll($userId);
        $count = $model->getUnreadCount($userId);
        echo json_encode(array('success' => true, 'count' => $count, 'data' => $notifications));
        break;

    // 標記已讀
    case 'read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            break;
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id) {
            $model->markRead($id, $userId);
        }
        echo json_encode(array('success' => true));
        break;

    // 全部已讀
    case 'read_all':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('error' => '方法不允許'));
            break;
        }
        $model->markAllRead($userId);
        echo json_encode(array('success' => true));
        break;

    default:
        echo json_encode(array('error' => '未知操作'));
        break;
}
