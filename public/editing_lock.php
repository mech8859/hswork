<?php
/**
 * 通用編輯鎖定 AJAX：心跳 + 釋放
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../includes/EditingLock.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$module = $_POST['module'] ?? $_GET['module'] ?? '';
$recordId = (int)($_POST['record_id'] ?? $_GET['record_id'] ?? 0);
$user = Auth::user();

if (!$user || !$module || $recordId <= 0) {
    echo json_encode(array('success' => false, 'error' => 'invalid'));
    exit;
}

// 白名單模組（避免任意寫入）
$allowed = array('cases','customers','quotations','receivables','receipts','payables','payments_out','purchase_invoices','sales_invoices');
if (!in_array($module, $allowed, true)) {
    echo json_encode(array('success' => false, 'error' => 'module not allowed'));
    exit;
}

switch ($action) {
    case 'heartbeat':
        // 同時更新本人 + 回傳其他編輯者
        EditingLock::set($module, $recordId, $user['id'], $user['real_name']);
        $others = EditingLock::getOthers($module, $recordId, $user['id'], 2);
        echo json_encode(array('success' => true, 'others' => $others));
        break;

    case 'release':
        EditingLock::release($module, $recordId, $user['id']);
        echo json_encode(array('success' => true));
        break;

    default:
        echo json_encode(array('success' => false, 'error' => 'unknown action'));
}
