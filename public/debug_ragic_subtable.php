<?php
/**
 * Debug: 查看 Ragic 子表格原始資料
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);
ini_set('memory_limit', '512M');

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';
$RAGIC_BASE = 'https://ap15.ragic.com/hstcc/new-case-registration/16';

$targetCase = isset($_GET['case']) ? trim($_GET['case']) : '2026-1763';

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Debug Ragic 子表格: ' . htmlspecialchars($targetCase) . '</h2>';
flush();

// 先找 Ragic record ID — 用搜尋而非全量
$listUrl = $RAGIC_BASE . '?api&limit=100&where=1000000,eq,' . urlencode($targetCase);
$ch = curl_init($listUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
curl_close($ch);

$list = json_decode($resp, true);
echo '<p>API 回傳: ' . ($list ? count($list) . ' 筆' : 'NULL / HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE)) . '</p>';

if (!$list || empty($list)) {
    // fallback: 全量搜尋但限制數量
    echo '<p>搜尋無結果，嘗試全量...</p>';
    flush();
    $listUrl2 = $RAGIC_BASE . '?api&limit=2000';
    $ch = curl_init($listUrl2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $resp = curl_exec($ch);
    curl_close($ch);
    $list = json_decode($resp, true);
    echo '<p>全量回傳: ' . ($list ? count($list) . ' 筆' : 'NULL') . '</p>';
}

$ragicRid = null;
if (is_array($list)) {
    foreach ($list as $rid => $rdata) {
        if (isset($rdata['進件編號']) && trim($rdata['進件編號']) === $targetCase) {
            $ragicRid = $rid;
            break;
        }
    }
}

if (!$ragicRid) {
    echo '<p style="color:red">找不到 Ragic 記錄</p>';
    exit;
}

echo '<p>Ragic Record ID: ' . $ragicRid . '</p>';

// 取完整資料含子表格
$detailUrl = $RAGIC_BASE . '/' . $ragicRid . '?api&subtables=true';
$ch = curl_init($detailUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$detailResp = curl_exec($ch);
curl_close($ch);

$detail = json_decode($detailResp, true);
if (!$detail || !isset($detail[$ragicRid])) {
    echo '<p style="color:red">無法取得詳細資料</p>';
    echo '<pre>' . htmlspecialchars(substr($detailResp, 0, 2000)) . '</pre>';
    exit;
}

$record = $detail[$ragicRid];

// 列出所有 key（找子表格）
echo '<h3>所有欄位 Key</h3>';
echo '<ul>';
foreach ($record as $key => $val) {
    if (strpos($key, '_subtable') === 0) {
        echo '<li style="color:blue;font-weight:bold">' . htmlspecialchars($key) . ' (子表格, ' . count($val) . ' 筆)</li>';
    }
}
echo '</ul>';

// 顯示每個子表格的內容
foreach ($record as $key => $val) {
    if (strpos($key, '_subtable') !== 0) continue;
    echo '<h3>' . htmlspecialchars($key) . ' (' . count($val) . ' 筆)</h3>';
    foreach ($val as $rid => $row) {
        echo '<details><summary>Row ' . htmlspecialchars($rid) . '</summary>';
        echo '<table border="1" cellpadding="4" style="font-size:.85rem;margin:8px 0">';
        foreach ($row as $rk => $rv) {
            $display = is_array($rv) ? json_encode($rv, JSON_UNESCAPED_UNICODE) : $rv;
            if (strlen($display) > 200) $display = substr($display, 0, 200) . '...';
            echo '<tr><td style="font-weight:600;white-space:nowrap">' . htmlspecialchars($rk) . '</td><td>' . htmlspecialchars($display) . '</td></tr>';
        }
        echo '</table></details>';
    }
}

echo '<hr><h3>完整 JSON（前 5000 字）</h3>';
echo '<pre style="max-height:400px;overflow:auto;font-size:.75rem">' . htmlspecialchars(substr(json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 5000)) . '</pre>';
