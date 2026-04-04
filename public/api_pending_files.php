<?php
header('Access-Control-Allow-Origin: https://ap15.ragic.com');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

$includePdf = isset($_GET['pdf']) ? true : false;

$where = "ca.note LIKE 'ragic_pending:%'";
if (!$includePdf) {
    $where .= " AND ca.file_name NOT LIKE '%.pdf'";
}

$stmt = $db->query("
    SELECT ca.id, ca.case_id, c.case_number, ca.file_type, ca.file_name,
           REPLACE(ca.note, 'ragic_pending:', '') AS file_key
    FROM case_attachments ca
    JOIN cases c ON c.id = ca.case_id
    WHERE $where
    ORDER BY c.case_number
");
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byCaseNumber = array();
foreach ($attachments as $a) {
    $cn = $a['case_number'];
    if (!isset($byCaseNumber[$cn])) {
        $byCaseNumber[$cn] = array('case_id' => $a['case_id'], 'case_number' => $cn, 'files' => array());
    }
    $byCaseNumber[$cn]['files'][] = array(
        'id' => $a['id'],
        'file_type' => $a['file_type'],
        'file_name' => $a['file_name'],
        'file_key' => $a['file_key'],
    );
}

echo json_encode(array(
    'total_files' => count($attachments),
    'total_cases' => count($byCaseNumber),
    'cases' => array_values($byCaseNumber),
), JSON_UNESCAPED_UNICODE);
