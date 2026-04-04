<?php
/**
 * 客戶資料全欄位驗證腳本
 * 掃描已匯入的 customers / customer_contacts 資料，列出異常
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '512M');

$db = Database::getInstance();

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>客戶資料驗證報告</title>';
echo '<style>
body { font-family: "Microsoft JhengHei", sans-serif; margin: 20px; background: #f5f5f5; }
.section { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
h1 { color: #333; }
h2 { color: #2c5282; border-bottom: 2px solid #2c5282; padding-bottom: 8px; }
h3 { color: #e53e3e; }
.ok { color: #38a169; font-weight: bold; }
.warn { color: #d69e2e; font-weight: bold; }
.err { color: #e53e3e; font-weight: bold; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 13px; }
th, td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; }
th { background: #edf2f7; }
tr:nth-child(even) { background: #f7fafc; }
.summary-table td:first-child { font-weight: bold; width: 200px; }
.sample { background: #fffbeb; }
.stat { font-size: 1.1em; margin: 5px 0; }
</style></head><body>';
echo '<h1>客戶資料驗證報告</h1>';
echo '<p>執行時間：' . date('Y-m-d H:i:s') . '</p>';

// ============================
// 基本統計
// ============================
echo '<div class="section"><h2>1. 基本統計</h2>';
$totalCustomers = (int)$db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalContacts = (int)$db->query("SELECT COUNT(*) FROM customer_contacts")->fetchColumn();
$totalGroups = (int)$db->query("SELECT COUNT(*) FROM customer_groups")->fetchColumn();
$totalCases = (int)$db->query("SELECT COUNT(*) FROM cases")->fetchColumn();
echo "<p class='stat'>客戶總數：<b>{$totalCustomers}</b></p>";
echo "<p class='stat'>聯絡人總數：<b>{$totalContacts}</b></p>";
echo "<p class='stat'>關聯群組：<b>{$totalGroups}</b></p>";
echo "<p class='stat'>案件總數：<b>{$totalCases}</b></p>";

// 各分公司分布
echo '<h3 style="color:#2c5282">各來源分公司分布</h3><table><tr><th>來源</th><th>筆數</th></tr>';
$stmt = $db->query("SELECT COALESCE(source_branch,'(空)') as sb, COUNT(*) as cnt FROM customers GROUP BY source_branch ORDER BY cnt DESC");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "<tr><td>{$r['sb']}</td><td>{$r['cnt']}</td></tr>";
}
echo '</table></div>';

// ============================
// 各欄位空值率
// ============================
echo '<div class="section"><h2>2. 各欄位空值率</h2>';
$fields = array(
    'customer_no' => '客戶編號',
    'case_number' => '進件編號',
    'name' => '客戶名稱',
    'category' => '分類',
    'contact_person' => '聯絡人',
    'phone' => '電話',
    'mobile' => '手機',
    'tax_id' => '統編',
    'site_address' => '地址',
    'completion_date' => '完工日期',
    'warranty_date' => '保固日期',
    'warranty_note' => '保固備註',
    'payment_info' => '付款方式',
    'payment_terms' => '付款條件',
    'salesperson_name' => '業務姓名',
    'sales_id' => '業務ID',
    'source_company' => '進件公司',
    'original_customer_no' => '原客戶編號',
    'source_branch' => '來源分公司',
);
echo '<table><tr><th>欄位</th><th>有值</th><th>空值</th><th>空值率</th><th>狀態</th></tr>';
foreach ($fields as $col => $label) {
    $filled = (int)$db->query("SELECT COUNT(*) FROM customers WHERE {$col} IS NOT NULL AND {$col} != ''")->fetchColumn();
    $empty = $totalCustomers - $filled;
    $rate = $totalCustomers > 0 ? round($empty / $totalCustomers * 100, 1) : 0;
    $status = '';
    // 關鍵欄位空值過高示警
    if (in_array($col, array('customer_no','name')) && $empty > 0) {
        $status = '<span class="err">必填欄位有空值！</span>';
    } elseif ($rate > 80) {
        $status = '<span class="warn">空值率偏高</span>';
    } else {
        $status = '<span class="ok">OK</span>';
    }
    echo "<tr><td>{$label} ({$col})</td><td>{$filled}</td><td>{$empty}</td><td>{$rate}%</td><td>{$status}</td></tr>";
}
echo '</table></div>';

// ============================
// 客戶編號驗證
// ============================
echo '<div class="section"><h2>3. 客戶編號 (customer_no) 驗證</h2>';

// 空值
$emptyNo = (int)$db->query("SELECT COUNT(*) FROM customers WHERE customer_no IS NULL OR customer_no = ''")->fetchColumn();
echo "<p>空值：" . ($emptyNo > 0 ? "<span class='err'>{$emptyNo} 筆</span>" : "<span class='ok'>0</span>") . "</p>";

// 重複
$dupes = $db->query("SELECT customer_no, COUNT(*) as cnt FROM customers WHERE customer_no IS NOT NULL AND customer_no != '' GROUP BY customer_no HAVING cnt > 1 ORDER BY cnt DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>重複編號：" . (count($dupes) > 0 ? "<span class='err'>" . count($dupes) . " 組</span>" : "<span class='ok'>無</span>") . "</p>";
if ($dupes) {
    echo '<table><tr><th>編號</th><th>重複次數</th></tr>';
    foreach ($dupes as $d) { echo "<tr><td>{$d['customer_no']}</td><td>{$d['cnt']}</td></tr>"; }
    echo '</table>';
}

// 格式分析
echo '<h3 style="color:#2c5282">編號格式分布</h3>';
$patterns = $db->query("SELECT LEFT(customer_no, 2) as prefix, COUNT(*) as cnt FROM customers WHERE customer_no IS NOT NULL GROUP BY prefix ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
echo '<table><tr><th>前綴</th><th>筆數</th></tr>';
foreach ($patterns as $p) { echo "<tr><td>{$p['prefix']}</td><td>{$p['cnt']}</td></tr>"; }
echo '</table></div>';

// ============================
// 電話驗證
// ============================
echo '<div class="section"><h2>4. 電話 (phone) 驗證</h2>';
// 找出有值但不像市話的
$badPhones = $db->query("
    SELECT id, customer_no, name, phone FROM customers
    WHERE phone IS NOT NULL AND phone != ''
    AND phone NOT REGEXP '^0[2-9][0-9()-]{5,12}$'
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
$phoneTotal = (int)$db->query("SELECT COUNT(*) FROM customers WHERE phone IS NOT NULL AND phone != ''")->fetchColumn();
echo "<p>有填電話：{$phoneTotal} 筆</p>";
echo "<p>格式異常：" . (count($badPhones) > 0 ? "<span class='warn'>" . count($badPhones) . "+ 筆（顯示前30）</span>" : "<span class='ok'>全部正常</span>") . "</p>";
if ($badPhones) {
    echo '<table class="sample"><tr><th>ID</th><th>編號</th><th>客戶名</th><th>電話值</th></tr>';
    foreach ($badPhones as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['phone']) . "</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// ============================
// 手機驗證
// ============================
echo '<div class="section"><h2>5. 手機 (mobile) 驗證</h2>';
$badMobiles = $db->query("
    SELECT id, customer_no, name, mobile FROM customers
    WHERE mobile IS NOT NULL AND mobile != ''
    AND REPLACE(REPLACE(REPLACE(mobile,'-',''),' ',''),'(','') NOT REGEXP '^09[0-9]{8}$'
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
$mobileTotal = (int)$db->query("SELECT COUNT(*) FROM customers WHERE mobile IS NOT NULL AND mobile != ''")->fetchColumn();
echo "<p>有填手機：{$mobileTotal} 筆</p>";
echo "<p>格式異常（非 09 開頭 10 碼）：" . (count($badMobiles) > 0 ? "<span class='warn'>" . count($badMobiles) . "+ 筆（顯示前30）</span>" : "<span class='ok'>全部正常</span>") . "</p>";
if ($badMobiles) {
    echo '<table class="sample"><tr><th>ID</th><th>編號</th><th>客戶名</th><th>手機值</th></tr>';
    foreach ($badMobiles as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['mobile']) . "</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// ============================
// 統編驗證
// ============================
echo '<div class="section"><h2>6. 統一編號 (tax_id) 驗證</h2>';
$taxTotal = (int)$db->query("SELECT COUNT(*) FROM customers WHERE tax_id IS NOT NULL AND tax_id != ''")->fetchColumn();
echo "<p>有填統編：{$taxTotal} 筆</p>";

// 非8碼數字
$badTax = $db->query("
    SELECT id, customer_no, name, tax_id FROM customers
    WHERE tax_id IS NOT NULL AND tax_id != ''
    AND tax_id NOT REGEXP '^[0-9]{8}$'
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>非8碼數字：" . (count($badTax) > 0 ? "<span class='err'>" . count($badTax) . "+ 筆</span>" : "<span class='ok'>全部正確</span>") . "</p>";
if ($badTax) {
    echo '<table class="sample"><tr><th>ID</th><th>編號</th><th>客戶名</th><th>統編值</th></tr>';
    foreach ($badTax as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['tax_id']) . "</td></tr>";
    }
    echo '</table>';
}

// 重複統編
$dupeTax = $db->query("SELECT tax_id, COUNT(*) as cnt FROM customers WHERE tax_id IS NOT NULL AND tax_id != '' AND tax_id REGEXP '^[0-9]{8}$' GROUP BY tax_id HAVING cnt > 1 ORDER BY cnt DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>重複統編：" . (count($dupeTax) > 0 ? "<span class='warn'>" . count($dupeTax) . " 組</span>" : "<span class='ok'>無</span>") . "</p>";
if ($dupeTax) {
    echo '<table><tr><th>統編</th><th>重複次數</th></tr>';
    foreach ($dupeTax as $d) { echo "<tr><td>{$d['tax_id']}</td><td>{$d['cnt']}</td></tr>"; }
    echo '</table>';
}
echo '</div>';

// ============================
// 地址驗證
// ============================
echo '<div class="section"><h2>7. 地址 (site_address) 驗證</h2>';
$addrTotal = (int)$db->query("SELECT COUNT(*) FROM customers WHERE site_address IS NOT NULL AND site_address != ''")->fetchColumn();
echo "<p>有填地址：{$addrTotal} 筆</p>";

// 太短（可能不完整）
$shortAddr = $db->query("
    SELECT id, customer_no, name, site_address FROM customers
    WHERE site_address IS NOT NULL AND site_address != ''
    AND CHAR_LENGTH(site_address) < 6
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>過短地址（<6字）：" . (count($shortAddr) > 0 ? "<span class='warn'>" . count($shortAddr) . "+ 筆</span>" : "<span class='ok'>無</span>") . "</p>";
if ($shortAddr) {
    echo '<table class="sample"><tr><th>ID</th><th>編號</th><th>客戶名</th><th>地址</th></tr>';
    foreach ($shortAddr as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['site_address']) . "</td></tr>";
    }
    echo '</table>';
}

// 沒有縣市
$noCityAddr = (int)$db->query("
    SELECT COUNT(*) FROM customers
    WHERE site_address IS NOT NULL AND site_address != ''
    AND site_address NOT REGEXP '(台北|新北|桃園|台中|臺中|台南|臺南|高雄|基隆|新竹|嘉義|苗栗|彰化|南投|雲林|屏東|宜蘭|花蓮|台東|臺東|澎湖|金門|連江)'
")->fetchColumn();
echo "<p>地址不含縣市名：" . ($noCityAddr > 0 ? "<span class='warn'>{$noCityAddr} 筆</span>" : "<span class='ok'>無</span>") . "</p>";
echo '</div>';

// ============================
// 分類驗證
// ============================
echo '<div class="section"><h2>8. 客戶分類 (category) 驗證</h2>';
$validCategories = array('residential','food','shop','hospital','school','religion','leisure','hotel','financial','industrial','builder','logistics','community','government','commercial','enterprise','association');
$catDist = $db->query("SELECT COALESCE(category,'(空)') as cat, COUNT(*) as cnt FROM customers GROUP BY category ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
echo '<table><tr><th>分類</th><th>筆數</th><th>狀態</th></tr>';
foreach ($catDist as $c) {
    $isValid = in_array($c['cat'], $validCategories);
    $status = ($c['cat'] === '(空)') ? '<span class="warn">未分類</span>' : ($isValid ? '<span class="ok">OK</span>' : '<span class="err">非法值！</span>');
    echo "<tr><td>{$c['cat']}</td><td>{$c['cnt']}</td><td>{$status}</td></tr>";
}
echo '</table></div>';

// ============================
// 完工日期驗證
// ============================
echo '<div class="section"><h2>9. 完工日期 (completion_date) 驗證</h2>';
$dateTotal = (int)$db->query("SELECT COUNT(*) FROM customers WHERE completion_date IS NOT NULL")->fetchColumn();
echo "<p>有填完工日期：{$dateTotal} 筆</p>";

// 未來日期
$futureDates = $db->query("
    SELECT id, customer_no, name, completion_date FROM customers
    WHERE completion_date > CURDATE()
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>未來日期：" . (count($futureDates) > 0 ? "<span class='err'>" . count($futureDates) . " 筆</span>" : "<span class='ok'>無</span>") . "</p>";
if ($futureDates) {
    echo '<table class="sample"><tr><th>ID</th><th>編號</th><th>客戶名</th><th>完工日期</th></tr>';
    foreach ($futureDates as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>{$r['completion_date']}</td></tr>";
    }
    echo '</table>';
}

// 太早的日期（2000年以前）
$oldDates = $db->query("
    SELECT id, customer_no, name, completion_date FROM customers
    WHERE completion_date IS NOT NULL AND completion_date < '2000-01-01'
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>2000年以前：" . (count($oldDates) > 0 ? "<span class='warn'>" . count($oldDates) . " 筆</span>" : "<span class='ok'>無</span>") . "</p>";
if ($oldDates) {
    echo '<table class="sample"><tr><th>ID</th><th>編號</th><th>客戶名</th><th>完工日期</th></tr>';
    foreach ($oldDates as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>{$r['completion_date']}</td></tr>";
    }
    echo '</table>';
}

// 年度分布
echo '<h3 style="color:#2c5282">完工年度分布</h3><table><tr><th>年度</th><th>筆數</th></tr>';
$yearDist = $db->query("SELECT YEAR(completion_date) as yr, COUNT(*) as cnt FROM customers WHERE completion_date IS NOT NULL GROUP BY yr ORDER BY yr")->fetchAll(PDO::FETCH_ASSOC);
foreach ($yearDist as $y) {
    echo "<tr><td>{$y['yr']}</td><td>{$y['cnt']}</td></tr>";
}
echo '</table></div>';

// ============================
// 保固日期驗證
// ============================
echo '<div class="section"><h2>10. 保固日期 (warranty_date) 驗證</h2>';
$wdTotal = (int)$db->query("SELECT COUNT(*) FROM customers WHERE warranty_date IS NOT NULL")->fetchColumn();
echo "<p>有填保固日期：{$wdTotal} 筆</p>";

// 保固 < 完工
$wdBeforeComp = $db->query("
    SELECT id, customer_no, name, completion_date, warranty_date FROM customers
    WHERE warranty_date IS NOT NULL AND completion_date IS NOT NULL
    AND warranty_date < completion_date
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>保固日 < 完工日：" . (count($wdBeforeComp) > 0 ? "<span class='err'>" . count($wdBeforeComp) . " 筆</span>" : "<span class='ok'>無</span>") . "</p>";
if ($wdBeforeComp) {
    echo '<table class="sample"><tr><th>ID</th><th>編號</th><th>客戶名</th><th>完工日</th><th>保固日</th></tr>';
    foreach ($wdBeforeComp as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>{$r['completion_date']}</td><td>{$r['warranty_date']}</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// ============================
// 客戶名重複分析
// ============================
echo '<div class="section"><h2>11. 客戶名稱重複分析</h2>';
$namedupes = $db->query("SELECT name, COUNT(*) as cnt FROM customers WHERE name IS NOT NULL AND name != '' GROUP BY name HAVING cnt > 1 ORDER BY cnt DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
$totalDupeGroups = (int)$db->query("SELECT COUNT(*) FROM (SELECT name FROM customers WHERE name IS NOT NULL AND name != '' GROUP BY name HAVING COUNT(*) > 1) t")->fetchColumn();
echo "<p>同名客戶群組：<b>{$totalDupeGroups}</b> 組（顯示前30）</p>";
if ($namedupes) {
    echo '<table><tr><th>客戶名</th><th>重複次數</th></tr>';
    foreach ($namedupes as $d) {
        echo "<tr><td>" . htmlspecialchars($d['name']) . "</td><td>{$d['cnt']}</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// ============================
// 業務比對預覽
// ============================
echo '<div class="section"><h2>12. 業務姓名 (salesperson_name) 比對預覽</h2>';
$salesNames = $db->query("SELECT COALESCE(salesperson_name,'(空)') as sn, COUNT(*) as cnt FROM customers GROUP BY salesperson_name ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
$activeUsers = $db->query("SELECT id, real_name FROM users WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
$userNames = array();
foreach ($activeUsers as $u) { $userNames[$u['real_name']] = $u['id']; }

echo '<table><tr><th>業務姓名</th><th>客戶數</th><th>對應 user</th></tr>';
foreach ($salesNames as $s) {
    $matched = isset($userNames[$s['sn']]) ? "<span class='ok'>ID={$userNames[$s['sn']]}</span>" : ($s['sn'] === '(空)' ? '-' : '<span class="warn">無對應</span>');
    echo "<tr><td>" . htmlspecialchars($s['sn']) . "</td><td>{$s['cnt']}</td><td>{$matched}</td></tr>";
}
echo '</table></div>';

// ============================
// 聯絡人驗證（如果已匯入的話）
// ============================
echo '<div class="section"><h2>13. 聯絡人 (customer_contacts) 驗證</h2>';
if ($totalContacts > 0) {
    echo "<p>聯絡人總數：<b>{$totalContacts}</b></p>";

    // orphan contacts
    $orphan = (int)$db->query("SELECT COUNT(*) FROM customer_contacts cc LEFT JOIN customers c ON cc.customer_id = c.id WHERE c.id IS NULL")->fetchColumn();
    echo "<p>孤兒聯絡人（customer_id 不存在）：" . ($orphan > 0 ? "<span class='err'>{$orphan} 筆</span>" : "<span class='ok'>0</span>") . "</p>";

    // 手機格式
    $badContactPhone = $db->query("
        SELECT cc.id, cc.customer_id, cc.contact_name, cc.phone FROM customer_contacts cc
        WHERE cc.phone IS NOT NULL AND cc.phone != ''
        AND REPLACE(REPLACE(REPLACE(cc.phone,'-',''),' ',''),'(','') NOT REGEXP '^0[0-9]{8,9}$'
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>聯絡人電話格式異常：" . (count($badContactPhone) > 0 ? "<span class='warn'>" . count($badContactPhone) . "+ 筆</span>" : "<span class='ok'>正常</span>") . "</p>";
    if ($badContactPhone) {
        echo '<table class="sample"><tr><th>ID</th><th>customer_id</th><th>聯絡人</th><th>電話</th></tr>';
        foreach ($badContactPhone as $r) {
            echo "<tr><td>{$r['id']}</td><td>{$r['customer_id']}</td><td>" . htmlspecialchars($r['contact_name']) . "</td><td>" . htmlspecialchars($r['phone']) . "</td></tr>";
        }
        echo '</table>';
    }
} else {
    echo '<p class="warn">尚未匯入聯絡人（Step 2 未執行）</p>';
}
echo '</div>';

// ============================
// 進件編號 case_number 驗證
// ============================
echo '<div class="section"><h2>14. 進件編號 (case_number) 驗證</h2>';
$cnTotal = (int)$db->query("SELECT COUNT(*) FROM customers WHERE case_number IS NOT NULL AND case_number != ''")->fetchColumn();
echo "<p>有填進件編號：{$cnTotal} 筆</p>";

// 格式分析 (預期: YYYY-NNNN)
$badCaseNum = $db->query("
    SELECT id, customer_no, name, case_number FROM customers
    WHERE case_number IS NOT NULL AND case_number != ''
    AND case_number NOT REGEXP '^[0-9]{4}-[0-9]{4}$'
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
echo "<p>非 YYYY-NNNN 格式：" . (count($badCaseNum) > 0 ? "<span class='warn'>" . count($badCaseNum) . "+ 筆</span>" : "<span class='ok'>全部符合</span>") . "</p>";
if ($badCaseNum) {
    echo '<table class="sample"><tr><th>ID</th><th>客戶編號</th><th>客戶名</th><th>進件編號</th></tr>';
    foreach ($badCaseNum as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['customer_no']) . "</td><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['case_number']) . "</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// ============================
// 總結
// ============================
echo '<div class="section"><h2>驗證總結</h2>';
echo '<table class="summary-table">';

$issues = array();
if ($emptyNo > 0) $issues[] = "客戶編號空值 {$emptyNo} 筆";
if (count($dupes) > 0) $issues[] = "客戶編號重複 " . count($dupes) . " 組";
if (count($badPhones) > 0) $issues[] = "電話格式異常 " . count($badPhones) . "+ 筆";
if (count($badMobiles) > 0) $issues[] = "手機格式異常 " . count($badMobiles) . "+ 筆";
if (count($badTax) > 0) $issues[] = "統編格式異常 " . count($badTax) . "+ 筆";
if (count($futureDates) > 0) $issues[] = "未來完工日期 " . count($futureDates) . " 筆";
if (count($oldDates) > 0) $issues[] = "過早完工日期 " . count($oldDates) . " 筆";
if (count($wdBeforeComp) > 0) $issues[] = "保固日<完工日 " . count($wdBeforeComp) . " 筆";
if ($noCityAddr > 0) $issues[] = "地址無縣市 {$noCityAddr} 筆";
if ($totalDupeGroups > 0) $issues[] = "同名客戶 {$totalDupeGroups} 組";

if (empty($issues)) {
    echo '<tr><td colspan="2"><span class="ok">全部通過！</span></td></tr>';
} else {
    foreach ($issues as $i => $issue) {
        echo '<tr><td>問題 ' . ($i+1) . '</td><td><span class="err">' . $issue . '</span></td></tr>';
    }
}
echo '</table>';
echo '<p style="margin-top:15px;color:#666">注意：部分「異常」可能是原始 Excel 資料本來就這樣，需人工判斷是否需要修正。</p>';
echo '</div>';

echo '</body></html>';
