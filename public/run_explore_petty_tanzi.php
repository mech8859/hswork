<?php
/**
 * 探索 Ragic 潭子零用金欄位結構
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') die('需要管理員權限');

header('Content-Type: text/plain; charset=utf-8');

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$url = 'https://ap15.ragic.com/hstcc/taichung-case-tracking-table/44?api';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n";
echo "Response length: " . strlen($resp) . " bytes\n\n";

if ($httpCode != 200) {
    echo substr($resp, 0, 1000);
    exit;
}

$data = json_decode($resp, true);
if (!$data) {
    echo "JSON decode failed\n";
    echo substr($resp, 0, 500);
    exit;
}

echo "=== 總筆數: " . count($data) . " ===\n\n";

// 顯示前 3 筆
$count = 0;
foreach ($data as $rid => $row) {
    if ($count >= 3) break;
    echo "=== Record ID: $rid ===\n";
    foreach ($row as $k => $v) {
        if (substr($k, 0, 1) === '_') continue;
        $vStr = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
        if (strlen($vStr) > 100) $vStr = substr($vStr, 0, 100) . '...';
        echo "  [$k] => $vStr\n";
    }
    echo "\n";
    $count++;
}
