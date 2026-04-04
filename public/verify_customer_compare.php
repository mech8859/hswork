<?php
/**
 * 客戶資料源頭比對：JSON vs DB 逐筆逐欄比較
 * 找出匯入過程中產生的差異
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '1G');

$db = Database::getInstance();

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>JSON vs DB 源頭比對</title>';
echo '<style>
body { font-family: "Microsoft JhengHei", sans-serif; margin: 20px; background: #f5f5f5; }
.section { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
h1 { color: #333; }
h2 { color: #2c5282; border-bottom: 2px solid #2c5282; padding-bottom: 8px; }
.ok { color: #38a169; font-weight: bold; }
.err { color: #e53e3e; font-weight: bold; }
.warn { color: #d69e2e; font-weight: bold; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 13px; }
th, td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; }
th { background: #edf2f7; }
.diff { background: #fff5f5; }
.stat { font-size: 1.1em; margin: 5px 0; }
.field-diff { background: #fed7d7; padding: 2px 6px; border-radius: 3px; }
</style></head><body>';
echo '<h1>JSON vs DB 源頭比對報告</h1>';
echo '<p>執行時間：' . date('Y-m-d H:i:s') . '</p>';

// 讀取 JSON
$jsonPath = __DIR__ . '/../database/customer_import_v2.json';
if (!file_exists($jsonPath)) { die('customer_import.json 不存在'); }
$records = json_decode(file_get_contents($jsonPath), true);
if (!$records) { die('JSON 解析失敗'); }

$jsonCount = count($records);
$dbCount = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();

echo '<div class="section"><h2>1. 筆數比對</h2>';
echo "<p class='stat'>JSON 筆數：<b>{$jsonCount}</b></p>";
echo "<p class='stat'>DB 筆數：<b>{$dbCount}</b></p>";
if ($jsonCount === $dbCount) {
    echo '<p class="ok">筆數一致</p>';
} else {
    echo '<p class="err">筆數不一致！差 ' . abs($jsonCount - $dbCount) . ' 筆</p>';
}
echo '</div>';

// 比對欄位對照表：JSON key → DB column
$fieldMap = array(
    'customer_no' => 'customer_no',
    'case_number' => 'case_number',
    'customer_name' => 'name',
    'category' => 'category',
    'source_company' => 'source_company',
    'original_customer_no' => 'original_customer_no',
    'contact_person' => 'contact_person',
    'phone' => 'phone',
    'mobile' => 'mobile',
    'tax_id' => 'tax_id',
    'site_address' => 'site_address',
    'completion_date' => 'completion_date',
    'warranty_date' => 'warranty_date',
    'warranty_note' => 'warranty_note',
    'payment_info' => 'payment_info',
    'salesperson_name' => 'salesperson_name',
    'line_official' => 'line_official',
    'source_branch' => 'source_branch',
);

$fieldLabels = array(
    'customer_no' => '客戶編號',
    'case_number' => '進件編號',
    'customer_name' => '客戶名稱',
    'category' => '分類',
    'source_company' => '進件公司',
    'original_customer_no' => '原客戶編號',
    'contact_person' => '聯絡人',
    'phone' => '電話',
    'mobile' => '手機',
    'tax_id' => '統編',
    'site_address' => '地址',
    'completion_date' => '完工日期',
    'warranty_date' => '保固日期',
    'warranty_note' => '保固備註',
    'payment_info' => '付款方式',
    'salesperson_name' => '業務姓名',
    'line_official' => '官方LINE',
    'source_branch' => '來源分公司',
);

// 預載所有 DB 客戶，用 customer_no 索引
echo '<div class="section"><h2>2. 逐欄比對結果</h2>';
echo '<p>載入 DB 資料中...</p>';

$stmt = $db->query("SELECT * FROM customers ORDER BY id");
$dbByNo = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbByNo[$row['customer_no']] = $row;
}

// 逐筆比對
$fieldErrors = array(); // field => count
$diffDetails = array(); // [{customer_no, field, json_val, db_val}, ...]
$missingInDb = array();
$totalChecked = 0;
$totalDiffs = 0;
$recordsWithDiff = 0;

foreach ($fieldMap as $jk => $dk) {
    $fieldErrors[$jk] = 0;
}

foreach ($records as $idx => $jr) {
    $cno = $jr['customer_no'];
    if (!isset($dbByNo[$cno])) {
        $missingInDb[] = $cno;
        continue;
    }
    $dr = $dbByNo[$cno];
    $totalChecked++;
    $hasDiff = false;

    foreach ($fieldMap as $jk => $dk) {
        $jVal = isset($jr[$jk]) ? $jr[$jk] : null;
        $dVal = isset($dr[$dk]) ? $dr[$dk] : null;

        // 正規化比較
        // 空字串 vs NULL 視為相同
        if (($jVal === '' || $jVal === null) && ($dVal === '' || $dVal === null)) {
            continue;
        }

        // 日期特殊處理：0000-00-00 視為 NULL
        if (in_array($jk, array('completion_date', 'warranty_date'))) {
            if ($dVal === '0000-00-00') $dVal = null;
            if ($jVal === '0000-00-00') $jVal = null;
            if (($jVal === '' || $jVal === null) && ($dVal === '' || $dVal === null)) {
                continue;
            }
        }

        // warranty_note 特殊處理：Step 4 會把離職業務名寫入 warranty_note
        // 所以 DB 的 warranty_note 可能比 JSON 多一段「原承辦業務：XXX」
        // 這裡先做精確比對，但標記為 known difference

        // 字串比較（trim）
        $jStr = trim((string)$jVal);
        $dStr = trim((string)$dVal);

        if ($jStr !== $dStr) {
            $fieldErrors[$jk]++;
            $totalDiffs++;
            $hasDiff = true;

            // 只記錄前 10 筆明細
            if ($fieldErrors[$jk] <= 10) {
                $diffDetails[] = array(
                    'customer_no' => $cno,
                    'field' => $jk,
                    'label' => $fieldLabels[$jk],
                    'json_val' => mb_substr($jStr, 0, 100),
                    'db_val' => mb_substr($dStr, 0, 100),
                );
            }
        }
    }
    if ($hasDiff) $recordsWithDiff++;
}

// 彙總表
echo '<table>';
echo '<tr><th>欄位</th><th>JSON 欄位名</th><th>DB 欄位名</th><th>差異筆數</th><th>狀態</th></tr>';
foreach ($fieldMap as $jk => $dk) {
    $cnt = $fieldErrors[$jk];
    $status = ($cnt === 0) ? '<span class="ok">完全一致</span>' : '<span class="err">' . $cnt . ' 筆不同</span>';
    echo "<tr><td>{$fieldLabels[$jk]}</td><td>{$jk}</td><td>{$dk}</td><td>{$cnt}</td><td>{$status}</td></tr>";
}
echo '</table>';

echo "<p class='stat'>比對筆數：{$totalChecked} / {$jsonCount}</p>";
echo "<p class='stat'>有差異的客戶：<b>{$recordsWithDiff}</b> 筆</p>";
echo "<p class='stat'>總差異欄位數：<b>{$totalDiffs}</b></p>";
if (count($missingInDb) > 0) {
    echo "<p class='err'>JSON 有但 DB 找不到：" . count($missingInDb) . " 筆</p>";
    echo '<p>' . implode(', ', array_slice($missingInDb, 0, 20)) . '</p>';
}
echo '</div>';

// 差異明細
if ($diffDetails) {
    echo '<div class="section"><h2>3. 差異明細（每欄位前 10 筆）</h2>';
    echo '<table>';
    echo '<tr><th>客戶編號</th><th>欄位</th><th>JSON 原始值</th><th>DB 值</th></tr>';
    foreach ($diffDetails as $d) {
        echo '<tr class="diff">';
        echo '<td>' . htmlspecialchars($d['customer_no']) . '</td>';
        echo '<td>' . htmlspecialchars($d['label']) . '</td>';
        echo '<td>' . htmlspecialchars($d['json_val']) . '</td>';
        echo '<td><span class="field-diff">' . htmlspecialchars($d['db_val']) . '</span></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
}

// 抽樣完整比對（前5筆、中間5筆、最後5筆）
echo '<div class="section"><h2>4. 抽樣完整比對（前/中/後各5筆）</h2>';
$sampleIndexes = array_merge(
    range(0, min(4, $jsonCount-1)),
    range(max(0, intval($jsonCount/2) - 2), min($jsonCount-1, intval($jsonCount/2) + 2)),
    range(max(0, $jsonCount - 5), $jsonCount - 1)
);
$sampleIndexes = array_unique($sampleIndexes);

foreach ($sampleIndexes as $si) {
    $jr = $records[$si];
    $cno = $jr['customer_no'];
    if (!isset($dbByNo[$cno])) {
        echo "<p class='err'>#{$si} {$cno} 在 DB 中不存在</p>";
        continue;
    }
    $dr = $dbByNo[$cno];

    echo "<h3 style='color:#2c5282;margin-top:15px'>#{$si} {$cno} — " . htmlspecialchars($jr['customer_name']) . "</h3>";
    echo '<table><tr><th>欄位</th><th>JSON</th><th>DB</th><th>結果</th></tr>';
    foreach ($fieldMap as $jk => $dk) {
        $jVal = isset($jr[$jk]) ? trim((string)$jr[$jk]) : '';
        $dVal = isset($dr[$dk]) ? trim((string)$dr[$dk]) : '';
        // 正規化
        if ($dVal === '0000-00-00') $dVal = '';
        $match = ($jVal === $dVal) || ($jVal === '' && $dVal === '') || ($jVal === '' && $dVal === null);
        $cls = $match ? '' : ' class="diff"';
        $icon = $match ? '<span class="ok">OK</span>' : '<span class="err">不同</span>';
        echo "<tr{$cls}><td>{$fieldLabels[$jk]}</td><td>" . htmlspecialchars(mb_substr($jVal,0,80)) . "</td><td>" . htmlspecialchars(mb_substr($dVal,0,80)) . "</td><td>{$icon}</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

echo '</body></html>';
