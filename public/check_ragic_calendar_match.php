<?php
/**
 * 比對 Ragic 東區工程行事曆 vs hswork 案件/客戶
 * 只讀取，不寫入任何資料
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$API_KEY = 'dGhmNTRyZk9uMlRUS2c3MjhhQytMZjlZdCtQc1lUMVJHYzVCNlA0dFFVZm1tREk0MFVxU0JibnRmNGV3TElEMA==';

$branchConfigs = array(
    'east' => array('name' => '東區電子鎖', 'url' => 'https://ap15.ragic.com/hstcc/east-district-electronic-lock/22', 'name_fields' => array('客戶名稱(新建)', '客戶名稱(現有客戶)', '客戶名稱(上傳)'), 'num_field' => '排工編號', 'date_field' => '開始時間'),
    'tanzi' => array('name' => '潭子分公司', 'url' => 'https://ap15.ragic.com/hstcc/taichung-case-tracking-table/37', 'name_fields' => array('客戶名稱'), 'num_field' => '', 'date_field' => '施工日期'),
    'yuanlin' => array('name' => '員林分公司', 'url' => 'https://ap15.ragic.com/hstcc/yuanlin-case-tracking-sheet/28', 'name_fields' => array('客戶名稱'), 'num_field' => '', 'date_field' => '施工日期'),
    'shimizu' => array('name' => '清水分公司', 'url' => 'https://ap15.ragic.com/hstcc/shimizu-case-tracking-table/18', 'name_fields' => array('客戶名稱'), 'num_field' => '', 'date_field' => '施工日期'),
);

$branch = isset($_GET['branch']) ? $_GET['branch'] : 'east';
if (!isset($branchConfigs[$branch])) { echo "未知分公司，可用: " . implode(', ', array_keys($branchConfigs)); exit; }
$cfg = $branchConfigs[$branch];
$url = $cfg['url'] . '?api&limit=1000';

echo "=== Ragic {$cfg['name']}工程行事曆 vs hswork 比對 ===\n";
echo "切換: ";
foreach ($branchConfigs as $k => $v) {
    $active = ($k === $branch) ? '[' . $v['name'] . ']' : $v['name'];
    echo "<a href='?branch={$k}'>{$active}</a>  ";
}
echo "\n\n";

// 抓 Ragic 資料
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $API_KEY));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
curl_close($ch);

$ragicData = json_decode($response, true);
if (!$ragicData) { echo "API 錯誤\n"; exit; }

$db = Database::getInstance();
$found = 0;
$notFound = 0;
$custFound = 0;
$custNotFound = 0;
$missingList = array();

echo str_pad('排工編號', 16) . str_pad('客戶名稱', 14) . str_pad('案件比對', 28) . str_pad('客戶比對', 24) . "\n";
echo str_repeat('-', 82) . "\n";

foreach ($ragicData as $rid => $rec) {
    $name = '';
    foreach ($cfg['name_fields'] as $f) {
        if (!empty($rec[$f])) { $name = trim($rec[$f]); break; }
    }
    $num = $cfg['num_field'] ? (isset($rec[$cfg['num_field']]) ? trim($rec[$cfg['num_field']]) : '') : 'R-' . $rid;
    $addr = isset($rec['地址']) ? trim($rec['地址']) : (isset($rec['施工地址']) ? trim($rec['施工地址']) : '');

    // 1. 案件比對（全分公司找）
    $caseStmt = $db->prepare("SELECT id, case_number, branch_id FROM cases WHERE customer_name = ? LIMIT 1");
    $caseStmt->execute(array($name));
    $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);

    // 模糊比對（去掉先生/小姐/設計師）
    $fuzzyCase = null;
    if (!$caseRow) {
        $baseName = preg_replace('/(先生|小姐|設計師|老師)$/u', '', $name);
        if ($baseName !== $name && mb_strlen($baseName) > 0) {
            $f = $db->prepare("SELECT id, case_number, branch_id FROM cases WHERE customer_name LIKE ? LIMIT 1");
            $f->execute(array($baseName . '%'));
            $fuzzyCase = $f->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 2. 客戶比對
    $custStmt = $db->prepare("SELECT id, customer_no FROM customers WHERE name = ? LIMIT 1");
    $custStmt->execute(array($name));
    $custRow = $custStmt->fetch(PDO::FETCH_ASSOC);

    $fuzzyCust = null;
    if (!$custRow) {
        $baseName = preg_replace('/(先生|小姐|設計師|老師)$/u', '', $name);
        if ($baseName !== $name && mb_strlen($baseName) > 0) {
            $f = $db->prepare("SELECT id, customer_no FROM customers WHERE name LIKE ? LIMIT 1");
            $f->execute(array($baseName . '%'));
            $fuzzyCust = $f->fetch(PDO::FETCH_ASSOC);
        }
    }

    $caseResult = $caseRow
        ? '✓ #' . $caseRow['id'] . ' ' . $caseRow['case_number']
        : ($fuzzyCase ? '~ #' . $fuzzyCase['id'] . ' ' . $fuzzyCase['case_number'] . ' (模糊)' : '✗ 未找到');
    $custResult = $custRow
        ? '✓ ' . $custRow['customer_no']
        : ($fuzzyCust ? '~ ' . $fuzzyCust['customer_no'] . ' (模糊)' : '✗ 未找到');

    if ($caseRow || $fuzzyCase) $found++; else { $notFound++; $missingList[] = $name; }
    if ($custRow || $fuzzyCust) $custFound++; else $custNotFound++;

    $mark = (!$caseRow && !$fuzzyCase) ? ' ⚠' : '';
    echo str_pad($num, 16) . str_pad($name, 14) . str_pad($caseResult, 28) . str_pad($custResult, 24) . $mark . "\n";
}

echo "\n=== 統計 ===\n";
echo "總筆數: " . count($ragicData) . "\n";
echo "案件找到: {$found} | 未找到: {$notFound}\n";
echo "客戶找到: {$custFound} | 未找到: {$custNotFound}\n";

if ($missingList) {
    echo "\n⚠ 案件未找到的客戶:\n";
    foreach ($missingList as $m) {
        echo "  - {$m}\n";
    }
    echo "\n這些會在同步時自動建立新案件（標記 ragic_calendar 來源）\n";
}

echo '</pre>';
