<?php
// 接收從 Ragic 前端 POST 過來的照片 base64
require __DIR__ . '/../includes/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('error' => 'POST only'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['base64']) || empty($input['filename'])) {
    echo json_encode(array('error' => 'missing base64 or filename'));
    exit;
}

$base64 = $input['base64'];
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $input['filename']);
$docType = isset($input['doc_type']) ? $input['doc_type'] : 'other';
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

// 解碼 base64
$data = base64_decode($base64);
if (!$data) {
    echo json_encode(array('error' => 'invalid base64'));
    exit;
}

// 存到 uploads/staff/
$dir = __DIR__ . '/uploads/staff';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$ext = pathinfo($filename, PATHINFO_EXTENSION);
if (!$ext) $ext = 'jpg';
$saveName = 'staff_' . $userId . '_' . $docType . '_' . time() . '.' . $ext;
$savePath = $dir . '/' . $saveName;
file_put_contents($savePath, $data);

$webPath = '/uploads/staff/' . $saveName;

// 如果有 user_id，寫入 staff_documents
if ($userId > 0) {
    $db = Database::getInstance();
    // 刪除舊的同類型文件
    $db->prepare("DELETE FROM staff_documents WHERE user_id = ? AND doc_type = ?")->execute(array($userId, $docType));
    // 新增
    $db->prepare("INSERT INTO staff_documents (user_id, doc_type, doc_label, file_path, file_name) VALUES (?, ?, ?, ?, ?)")
       ->execute(array($userId, $docType, $docType, $webPath, $filename));
}

echo json_encode(array('ok' => true, 'path' => $webPath, 'filename' => $saveName));
