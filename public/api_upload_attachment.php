<?php
/**
 * 接收 Ragic 瀏覽器端 POST 過來的附件圖片 (base64)
 * POST: case_id, file_name, file_type, image_data (base64)
 */
header('Access-Control-Allow-Origin: https://ap15.ragic.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['file_name']) || empty($input['image_data'])) {
    echo json_encode(array('ok' => false, 'error' => 'missing params'));
    exit;
}

$db = Database::getInstance();

// 如果傳了 case_number 但沒 case_id，查 DB
$caseId = !empty($input['case_id']) ? (int)$input['case_id'] : 0;
if (!$caseId && !empty($input['case_number'])) {
    $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
    $stmt->execute(array($input['case_number']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $caseId = (int)$row['id'];
}
if (!$caseId) {
    echo json_encode(array('ok' => false, 'error' => 'case not found'));
    exit;
}
$fileName = $input['file_name'];
$fileType = isset($input['file_type']) ? $input['file_type'] : 'other';
$imageData = $input['image_data'];
$target = isset($input['target']) ? $input['target'] : 'attachment'; // attachment or payment

// 解碼 base64
if (strpos($imageData, 'base64,') !== false) {
    $imageData = substr($imageData, strpos($imageData, 'base64,') + 7);
}
$binary = base64_decode($imageData);
if (!$binary || strlen($binary) < 100) {
    echo json_encode(array('ok' => false, 'error' => 'invalid image data, size=' . strlen($binary)));
    exit;
}

if ($target === 'payment') {
    // 帳款交易圖片
    $paymentId = isset($input['payment_id']) ? (int)$input['payment_id'] : 0;
    $ragicSubId = isset($input['ragic_sub_id']) ? $input['ragic_sub_id'] : '';

    // 用 ragic_sub_id 找帳款紀錄
    if (!$paymentId && $ragicSubId) {
        $pStmt = $db->prepare("SELECT id FROM case_payments WHERE case_id = ? AND ragic_id = ?");
        $pStmt->execute(array($caseId, $ragicSubId));
        $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($pRow) $paymentId = (int)$pRow['id'];
    }
    $dir = __DIR__ . '/uploads/case_payments/' . $caseId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $safeName = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $fileName);
    file_put_contents($dir . '/' . $safeName, $binary);
    $filePath = 'uploads/case_payments/' . $caseId . '/' . $safeName;

    if ($paymentId) {
        $db->prepare("UPDATE case_payments SET image_path = ? WHERE id = ? AND case_id = ?")
           ->execute(array($filePath, $paymentId, $caseId));
    }
    echo json_encode(array('ok' => true, 'path' => $filePath, 'size' => strlen($binary)));
} else {
    // 案件附件
    $dir = __DIR__ . '/uploads/cases/' . $caseId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $safeName = date('Ymd_His') . '_' . mt_rand(1000,9999) . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $fileName);
    file_put_contents($dir . '/' . $safeName, $binary);
    $filePath = 'uploads/cases/' . $caseId . '/' . $safeName;

    // 更新 pending 記錄
    $stmt = $db->prepare("UPDATE case_attachments SET file_path = ?, file_size = ?, note = 'ragic_uploaded' WHERE case_id = ? AND file_name = ? AND note LIKE 'ragic_pending:%'");
    $stmt->execute(array($filePath, strlen($binary), $caseId, $fileName));

    echo json_encode(array('ok' => true, 'path' => $filePath, 'size' => strlen($binary), 'updated' => $stmt->rowCount()));
}
