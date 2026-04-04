<?php
/**
 * 批次上傳附件：接收檔名，在伺服器上建立記錄
 * POST: files = [{case_id, file_name, file_data(base64)}]
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['case_id']) || empty($input['file_name']) || empty($input['image_data'])) {
    echo json_encode(array('ok' => false, 'error' => 'missing params'));
    exit;
}

$caseId = (int)$input['case_id'];
$fileName = $input['file_name'];
$imageData = $input['image_data'];

// 解碼 base64
if (strpos($imageData, 'base64,') !== false) {
    $imageData = substr($imageData, strpos($imageData, 'base64,') + 7);
}
$binary = base64_decode($imageData);
if (!$binary || strlen($binary) < 100) {
    echo json_encode(array('ok' => false, 'error' => 'invalid data'));
    exit;
}

$db = Database::getInstance();

// 存檔
$dir = __DIR__ . '/uploads/cases/' . $caseId;
if (!is_dir($dir)) mkdir($dir, 0755, true);
$safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $fileName);
file_put_contents($dir . '/' . $safeName, $binary);
$filePath = 'uploads/cases/' . $caseId . '/' . $safeName;

// 更新 DB
$stmt = $db->prepare("UPDATE case_attachments SET file_path = ?, file_size = ?, note = 'ragic_uploaded' WHERE case_id = ? AND file_name = ? AND note LIKE 'ragic_pending:%'");
$stmt->execute(array($filePath, strlen($binary), $caseId, $fileName));

echo json_encode(array('ok' => true, 'updated' => $stmt->rowCount(), 'size' => strlen($binary), 'path' => $filePath));
