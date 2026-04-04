<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$RAGIC_BASE = 'https://ap15.ragic.com/hstcc/new-case-registration/16';

$targetCase = isset($_GET['case']) ? trim($_GET['case']) : '';
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

// 全量取得找目標案件
$ch = curl_init($RAGIC_BASE . '?api&limit=2000');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
curl_close($ch);
$data = json_decode($resp, true);

foreach ($data as $rid => $rdata) {
    $cn = isset($rdata['進件編號']) ? trim($rdata['進件編號']) : '';
    if ($cn === $targetCase) {
        echo "=== Ragic RID: $rid ===\n\n";
        foreach ($rdata as $key => $val) {
            if (strpos($key, '_subtable') === 0) continue;
            if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            echo htmlspecialchars($key) . ' => ' . htmlspecialchars($val) . "\n";
        }
        break;
    }
}
echo '</pre>';
