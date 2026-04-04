<?php
/**
 * 接收 Ragic 子表格資料並寫入 DB
 * 從瀏覽器端 JS POST 過來
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['case_number'])) {
    echo json_encode(array('ok' => false, 'error' => 'missing case_number'));
    exit;
}

$db = Database::getInstance();

// 找 case_id
$stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
$stmt->execute(array($input['case_number']));
$case = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$case) { echo json_encode(array('ok' => false, 'error' => 'case not found')); exit; }
$caseId = (int)$case['id'];
$userId = 1; // system

$payNew = 0; $attNew = 0; $wlNew = 0;

// 帳款交易
$payments = isset($input['payments']) ? $input['payments'] : array();
foreach ($payments as $p) {
    if (empty($p['date'])) continue;
    $ragicId = $p['ragic_id'];
    $chk = $db->prepare("SELECT id FROM case_payments WHERE case_id = ? AND ragic_id = ?");
    $chk->execute(array($caseId, $ragicId));
    if ($chk->fetch()) continue;

    $db->prepare("INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, note, ragic_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
       ->execute(array($caseId, str_replace('/', '-', $p['date']), isset($p['type']) ? $p['type'] : null, isset($p['content']) ? $p['content'] : null, (int)(isset($p['amount']) ? $p['amount'] : 0), isset($p['note']) ? $p['note'] : null, $ragicId, $userId));
    $payNew++;
}

// 附件
$attachments = isset($input['attachments']) ? $input['attachments'] : array();
foreach ($attachments as $a) {
    if (empty($a['file_name'])) continue;
    $chk = $db->prepare("SELECT id FROM case_attachments WHERE case_id = ? AND file_name = ?");
    $chk->execute(array($caseId, $a['file_name']));
    if ($chk->fetch()) continue;

    $db->prepare("INSERT INTO case_attachments (case_id, file_type, file_name, file_path, file_size, uploaded_by, note) VALUES (?,?,?,?,?,?,?)")
       ->execute(array($caseId, $a['file_type'], $a['file_name'], '', 0, $userId, 'ragic_pending:' . $a['file_key']));
    $attNew++;
}

// 施工紀錄
$worklogs = isset($input['worklogs']) ? $input['worklogs'] : array();
foreach ($worklogs as $w) {
    if (empty($w['content'])) continue;
    $ragicId = $w['ragic_id'];
    $chk = $db->prepare("SELECT id FROM case_work_logs WHERE case_id = ? AND ragic_id = ?");
    $chk->execute(array($caseId, $ragicId));
    if ($chk->fetch()) continue;

    $db->prepare("INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, cable_used, ragic_id, created_by) VALUES (?,?,?,?,?,?,?)")
       ->execute(array($caseId, !empty($w['date']) ? str_replace('/', '-', $w['date']) : null, $w['content'], isset($w['equipment']) ? $w['equipment'] : null, isset($w['cable']) ? $w['cable'] : null, $ragicId, $userId));
    $wlNew++;
}

echo json_encode(array('ok' => true, 'payments' => $payNew, 'attachments' => $attNew, 'worklogs' => $wlNew));
