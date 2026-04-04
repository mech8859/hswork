<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$db->exec("SET NAMES utf8mb4");

$jsonPath = __DIR__ . '/../database/customer_import_v2.json';
$records = json_decode(file_get_contents($jsonPath), true);

$stmt = $db->query("SELECT customer_no, name FROM customers ORDER BY id");
$dbByNo = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbByNo[$row['customer_no']] = $row['name'];
}

$diff = 0;
foreach ($records as $r) {
    $cno = $r['customer_no'];
    $jName = $r['customer_name'];
    $dName = isset($dbByNo[$cno]) ? $dbByNo[$cno] : '(NOT FOUND)';
    if ($jName !== $dName) {
        $diff++;
        if ($diff <= 5) {
            echo "DIFF: {$cno}\n";
            echo "  JSON: " . bin2hex(mb_substr($jName,0,10,'UTF-8')) . " = " . mb_substr($jName,0,30,'UTF-8') . "\n";
            echo "  DB:   " . bin2hex(mb_substr($dName,0,10,'UTF-8')) . " = " . mb_substr($dName,0,30,'UTF-8') . "\n\n";
        }
    }
}
echo "Total diff: {$diff} / " . count($records) . "\n";
