<?php
/**
 * 銷項稅額明細分類帳 vs 401 申報銷項稅額 差異診斷
 * /debug_sales_tax_diff.php?start=2026-03-01&end=2026-04-30&account=2281001
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all') && !Auth::hasPermission('accounting.manage')) die('admin only');

$db = Database::getInstance();
$start = isset($_GET['start']) ? $_GET['start'] : '2026-03-01';
$end = isset($_GET['end']) ? $_GET['end'] : '2026-04-30';
$accCode = isset($_GET['account']) ? $_GET['account'] : '2281001';
$companyTaxId = ($accCode === '2281001') ? '94081455' : (($accCode === '2281002') ? '97002927' : '');
$companyName = ($companyTaxId === '94081455') ? '禾順' : (($companyTaxId === '97002927') ? '政遠' : '');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>銷項稅額差異診斷</title>";
echo "<style>
body{font-family:-apple-system,sans-serif;padding:20px;font-size:14px;line-height:1.6;max-width:1400px}
h1{color:#1f4e79} h2{color:#2e75b6;border-bottom:1px solid #ccc}
table{border-collapse:collapse;margin:8px 0;width:100%}
th,td{border:1px solid #ddd;padding:6px 10px;text-align:left}
th{background:#f0f8ff}
tr:nth-child(even){background:#fafafa}
.warn{color:#c5221f;font-weight:600} .ok{color:#16a34a;font-weight:600}
.summary{background:#fff3cd;padding:10px 14px;border-left:4px solid #f9a825;margin:12px 0;border-radius:4px}
.bad{background:#ffebee;padding:10px 14px;border-left:4px solid #c62828;margin:12px 0;border-radius:4px;color:#c62828}
</style></head><body>";

echo "<h1>🩺 銷項稅額差異診斷（{$companyName}）</h1>";
echo "<form>期間 <input name='start' value='" . h($start) . "'> ~ <input name='end' value='" . h($end) . "'> 科目 <input name='account' value='" . h($accCode) . "' size='8'> <button>查</button></form>";

// 查科目 ID
$accStmt = $db->prepare("SELECT id, account_name FROM chart_of_accounts WHERE account_code = ?");
$accStmt->execute(array($accCode));
$acc = $accStmt->fetch(PDO::FETCH_ASSOC);
if (!$acc) { echo "<p class='warn'>科目 $accCode 不存在</p></body></html>"; exit; }
$accId = (int)$acc['id'];
echo "<p>科目：<b>" . h($acc['account_name']) . "</b> (ID $accId)</p>";

// === A. 401 角度：confirmed sales invoices 的稅額（33/34 為負）===
echo "<h2>A. 401 角度（confirmed sales invoices）</h2>";
$invStmt = $db->prepare("
    SELECT id, invoice_number, invoice_date, customer_name, invoice_format, tax_amount, status
    FROM sales_invoices
    WHERE seller_tax_id = ?
      AND status = 'confirmed'
      AND invoice_date BETWEEN ? AND ?
    ORDER BY invoice_date, invoice_number
");
$invStmt->execute(array($companyTaxId, $start, $end));
$invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);
$invSum = 0;
foreach ($invoices as $i) {
    $sign = in_array((string)$i['invoice_format'], array('33','34'), true) ? -1 : 1;
    $invSum += $sign * (int)$i['tax_amount'];
}
echo "<p>合計：<b>" . count($invoices) . " 張</b>，銷項稅額 <b>$" . number_format($invSum) . "</b>（33/34 為負）</p>";

// === B. 帳本角度：journal_entry_lines on 銷項稅額-{公司} ===
echo "<h2>B. 明細分類帳角度（journal_entry_lines on $accCode）</h2>";
$lineStmt = $db->prepare("
    SELECT jl.id AS line_id, jl.debit_amount, jl.credit_amount, jl.description AS line_desc,
           je.id AS je_id, je.voucher_number, je.voucher_date, je.status, je.description AS je_desc
    FROM journal_entry_lines jl
    JOIN journal_entries je ON je.id = jl.journal_entry_id
    WHERE jl.account_id = ?
      AND je.voucher_date BETWEEN ? AND ?
      AND je.status != 'voided'
    ORDER BY je.voucher_date, je.id
");
$lineStmt->execute(array($accId, $start, $end));
$lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

$postedSum = 0; $draftSum = 0;
$postedCnt = 0; $draftCnt = 0;
$draftLines = array();
foreach ($lines as $l) {
    $signed = (float)$l['credit_amount'] - (float)$l['debit_amount'];  // 銷項：貸方為正
    if ($l['status'] === 'posted') {
        $postedSum += $signed; $postedCnt++;
    } else {
        $draftSum += $signed; $draftCnt++;
        $draftLines[] = $l;
    }
}
echo "<table style='max-width:600px'>";
echo "<tr><th>狀態</th><th>筆數</th><th>淨額（貸-借）</th></tr>";
echo "<tr><td>已過帳 posted</td><td>$postedCnt</td><td><b>$" . number_format($postedSum) . "</b></td></tr>";
echo "<tr><td>草稿 draft</td><td class='" . ($draftCnt > 0 ? 'warn' : '') . "'>$draftCnt</td><td>$" . number_format($draftSum) . "</td></tr>";
echo "<tr style='font-weight:600;background:#f0f8ff'><td>合計（含 draft）</td><td>" . count($lines) . "</td><td>$" . number_format($postedSum + $draftSum) . "</td></tr>";
echo "</table>";

// === C. 比對 ===
echo "<h2>C. 差異比對</h2>";
$diff = $invSum - $postedSum;
$cls = $diff === 0 ? 'ok' : 'warn';
echo "<table style='max-width:700px'>";
echo "<tr><td>401 銷項稅額（A）</td><td>$" . number_format($invSum) . "</td></tr>";
echo "<tr><td>明細分類帳已過帳 (B-posted)</td><td>$" . number_format($postedSum) . "</td></tr>";
echo "<tr><td>差額</td><td class='$cls'><b>$" . number_format($diff) . "</b></td></tr>";
echo "</table>";

if ($draftCnt > 0) {
    echo "<div class='bad'>⚠ 有 <b>$draftCnt</b> 筆 draft 傳票分錄（合計 $" . number_format($draftSum) . "）尚未過帳，這些不會出現在明細分類帳。</div>";
    echo "<h3>Draft 傳票清單（請逐一檢查並過帳）</h3>";
    echo "<table><tr><th>傳票號碼</th><th>日期</th><th>借方</th><th>貸方</th><th>line 摘要</th><th>傳票備註</th></tr>";
    foreach ($draftLines as $l) {
        echo "<tr>";
        echo "<td><a href='/accounting.php?action=journal_view&id=" . (int)$l['je_id'] . "'>" . h($l['voucher_number']) . "</a></td>";
        echo "<td>" . h($l['voucher_date']) . "</td>";
        echo "<td>" . number_format((float)$l['debit_amount']) . "</td>";
        echo "<td>" . number_format((float)$l['credit_amount']) . "</td>";
        echo "<td style='font-size:.85rem'>" . h(mb_strimwidth((string)$l['line_desc'], 0, 60, '…', 'UTF-8')) . "</td>";
        echo "<td style='font-size:.85rem'>" . h(mb_strimwidth((string)$l['je_desc'], 0, 60, '…', 'UTF-8')) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// === D. 沒對應發票的 posted 銷項稅額分錄（手動調整？）===
echo "<h2>D. 不在發票之列的 posted 銷項稅額分錄（手動調整或關連到別處）</h2>";
$invNumbers = array();
foreach ($invoices as $i) $invNumbers[$i['invoice_number']] = $i;
$noInvLines = array();
$noInvSum = 0;
foreach ($lines as $l) {
    if ($l['status'] !== 'posted') continue;
    $found = false;
    foreach ($invNumbers as $invNum => $_) {
        if (mb_strpos((string)$l['line_desc'], $invNum) !== false || mb_strpos((string)$l['je_desc'], $invNum) !== false) {
            $found = true; break;
        }
    }
    if (!$found) {
        $noInvLines[] = $l;
        $noInvSum += (float)$l['credit_amount'] - (float)$l['debit_amount'];
    }
}
if (empty($noInvLines)) {
    echo "<p class='ok'>✓ 所有 posted 分錄都能對應到期間內的 confirmed 發票</p>";
} else {
    echo "<p>找到 <b>" . count($noInvLines) . "</b> 筆未對應發票的分錄，淨額 <b>$" . number_format($noInvSum) . "</b></p>";
    echo "<table><tr><th>傳票號碼</th><th>日期</th><th>借方</th><th>貸方</th><th>line 摘要</th></tr>";
    foreach ($noInvLines as $l) {
        echo "<tr>";
        echo "<td><a href='/accounting.php?action=journal_view&id=" . (int)$l['je_id'] . "'>" . h($l['voucher_number']) . "</a></td>";
        echo "<td>" . h($l['voucher_date']) . "</td>";
        echo "<td>" . number_format((float)$l['debit_amount']) . "</td>";
        echo "<td>" . number_format((float)$l['credit_amount']) . "</td>";
        echo "<td style='font-size:.85rem'>" . h(mb_strimwidth((string)$l['line_desc'], 0, 80, '…', 'UTF-8')) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// === E. 每張發票對應的 posted line 比對（找出沒對應 line 或金額不符的）===
echo "<h2>E. 發票 → posted 稅額分錄 逐筆比對</h2>";
echo "<p class='muted'>合併入帳：一筆 line 可能對應多張發票。下表把 line 的稅額平均分配給匹配的發票。</p>";

// 建立 line → 比對到的發票 map（正向 + 反向）
$linesByInv = array();        // invoice_number => array of matched lines
$invsByLine = array();        // line_id => array of matched invoice numbers
foreach ($lines as $l) {
    if ($l['status'] !== 'posted') continue;
    $sig = (float)$l['credit_amount'] - (float)$l['debit_amount'];
    foreach ($invoices as $inv) {
        $invNum = $inv['invoice_number'];
        $haystack = (string)$l['line_desc'] . ' ' . (string)$l['je_desc'];
        if (mb_strpos($haystack, $invNum) !== false) {
            $linesByInv[$invNum][] = array('line_id'=>$l['line_id'], 'voucher'=>$l['voucher_number'], 'signed'=>$sig);
            $invsByLine[$l['line_id']][] = $invNum;
        }
    }
}

$noMatchInvs = array();
$mismatchInvs = array();
$multiInvs = array();
foreach ($invoices as $inv) {
    $invNum = $inv['invoice_number'];
    $sign = in_array((string)$inv['invoice_format'], array('33','34'), true) ? -1 : 1;
    $invSigned = $sign * (int)$inv['tax_amount'];
    if (empty($linesByInv[$invNum])) {
        $noMatchInvs[] = $inv;
    } else {
        // 加總對應 lines（每筆 line 平均分給匹配的發票數量）
        $allocated = 0;
        foreach ($linesByInv[$invNum] as $li) {
            $shareCount = count($invsByLine[$li['line_id']]);
            $allocated += $li['signed'] / $shareCount;
        }
        $allocated = round($allocated);
        if ($allocated !== $invSigned) {
            $mismatchInvs[] = array('inv'=>$inv, 'invSigned'=>$invSigned, 'allocated'=>$allocated, 'lines'=>$linesByInv[$invNum]);
        }
        if (count($linesByInv[$invNum]) > 1 || count($invsByLine[$linesByInv[$invNum][0]['line_id']]) > 1) {
            $multiInvs[] = array('inv'=>$inv, 'lines'=>$linesByInv[$invNum]);
        }
    }
}

echo "<table style='max-width:800px'>";
echo "<tr><td>沒對應 posted line 的發票</td><td class='" . (count($noMatchInvs) ? 'warn' : 'ok') . "'>" . count($noMatchInvs) . " 張</td></tr>";
echo "<tr><td>金額不符（line 平均分配後 ≠ invoice tax）</td><td class='" . (count($mismatchInvs) ? 'warn' : 'ok') . "'>" . count($mismatchInvs) . " 張</td></tr>";
echo "<tr><td>合併入帳（一 line 多發票）</td><td>" . count($multiInvs) . " 組</td></tr>";
echo "</table>";

if (!empty($noMatchInvs)) {
    echo "<h3 class='warn'>沒對應 posted line 的發票（" . count($noMatchInvs) . " 張）</h3>";
    echo "<p class='muted'>這些 confirmed 發票在期間內沒有任何 posted 傳票分錄含其發票號碼。可能：1) 對應傳票仍是 draft 2) 傳票摘要漏寫發票號 3) 真的漏記。</p>";
    echo "<table><tr><th>發票號碼</th><th>日期</th><th>客戶</th><th>聯式</th><th>稅額</th></tr>";
    $noMatchSum = 0;
    foreach ($noMatchInvs as $i) {
        $sign = in_array((string)$i['invoice_format'], array('33','34'), true) ? -1 : 1;
        $signed = $sign * (int)$i['tax_amount'];
        $noMatchSum += $signed;
        echo "<tr>";
        echo "<td><a href='/sales_invoices.php?action=edit&id=" . (int)$i['id'] . "'>" . h($i['invoice_number']) . "</a></td>";
        echo "<td>" . h($i['invoice_date']) . "</td>";
        echo "<td>" . h($i['customer_name']) . "</td>";
        echo "<td>" . h($i['invoice_format']) . "</td>";
        echo "<td>$" . number_format($signed) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p>合計稅額 <b>$" . number_format($noMatchSum) . "</b>" . ($noMatchSum === $diff ? " <span class='warn'>← 剛好等於 C 區的差額！</span>" : "") . "</p>";
}

// === F. 沒對應發票的「最可能對應傳票」推薦（fuzzy match）===
if (!empty($noMatchInvs)) {
    echo "<h3>🔎 推薦：可能對應的傳票（依客戶名 + 含稅金額 + 日期 fuzzy 配對）</h3>";
    echo "<p class='muted'>對沒對應 line 的發票，掃描期間前後 30 天內的傳票，找客戶名/金額/日期接近的候選。</p>";

    $invDetailStmt = $db->prepare("SELECT amount_untaxed, tax_amount, total_amount, customer_name, invoice_date FROM sales_invoices WHERE id = ?");

    foreach ($noMatchInvs as $i) {
        // 取發票完整資訊
        $invDetailStmt->execute(array($i['id']));
        $invDetail = $invDetailStmt->fetch(PDO::FETCH_ASSOC);
        $invTotal = (int)$invDetail['total_amount'];
        $invDate = $invDetail['invoice_date'];
        $custName = (string)$invDetail['customer_name'];

        // 客戶名稱關鍵字（取前 4 字當匹配）
        $custKey = mb_substr($custName, 0, 4);

        // 找候選傳票：日期 ±30 天 且 客戶名出現在 任何 line 摘要 或 傳票 description 或 relation_name 或 金額接近
        $rangeStart = date('Y-m-d', strtotime($invDate . ' -30 days'));
        $rangeEnd   = date('Y-m-d', strtotime($invDate . ' +30 days'));

        $candStmt = $db->prepare("
            SELECT DISTINCT je.id AS je_id, je.voucher_number, je.voucher_date, je.status,
                   je.total_debit, je.description AS je_desc
            FROM journal_entries je
            LEFT JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
            WHERE je.status != 'voided'
              AND je.voucher_date BETWEEN ? AND ?
              AND (
                je.description LIKE ?
                OR jl.description LIKE ?
                OR jl.relation_name LIKE ?
                OR ABS(je.total_debit - ?) <= 1
              )
            ORDER BY ABS(DATEDIFF(je.voucher_date, ?)) ASC, ABS(je.total_debit - ?) ASC
            LIMIT 8
        ");
        $custLike = '%' . $custKey . '%';
        $candStmt->execute(array($rangeStart, $rangeEnd, $custLike, $custLike, $custLike, $invTotal, $invDate, $invTotal));
        $candidates = $candStmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div style='margin:12px 0;padding:10px;background:#f0f8ff;border-radius:6px'>";
        echo "<div><b>" . h($i['invoice_number']) . "</b> — " . h($invDate) . " — " . h($custName) . " — 含稅 <b>$" . number_format($invTotal) . "</b>";
        echo " <a href='/sales_invoices.php?action=edit&id=" . (int)$i['id'] . "' class='btn btn-outline btn-sm' style='margin-left:8px;font-size:.75rem;padding:2px 8px'>看發票</a></div>";

        if (empty($candidates)) {
            echo "<p class='muted'>找不到候選傳票（可能真的漏建）</p>";
        } else {
            echo "<table style='margin-top:6px'><tr><th>傳票號碼</th><th>日期</th><th>狀態</th><th>借方總額</th><th>備註</th><th>金額差</th></tr>";
            foreach ($candidates as $c) {
                $tDiff = (int)$c['total_debit'] - $invTotal;
                $isAmtMatch = abs($tDiff) <= 1;
                $statusBadge = $c['status'] === 'posted' ? '<span class="ok">已過帳</span>' : '<span class="warn">' . h($c['status']) . '</span>';
                echo "<tr" . ($isAmtMatch ? " style='background:#e8f5e9'" : "") . ">";
                echo "<td><a href='/accounting.php?action=journal_view&id=" . (int)$c['je_id'] . "' target='_blank'>" . h($c['voucher_number']) . "</a></td>";
                echo "<td>" . h($c['voucher_date']) . "</td>";
                echo "<td>" . $statusBadge . "</td>";
                echo "<td>$" . number_format((float)$c['total_debit']) . "</td>";
                echo "<td style='font-size:.85rem'>" . h(mb_strimwidth((string)$c['je_desc'], 0, 50, '…', 'UTF-8')) . "</td>";
                echo "<td class='" . ($isAmtMatch ? 'ok' : '') . "'>" . ($isAmtMatch ? '✓ 完全相符' : '$' . number_format($tDiff)) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p class='muted' style='margin-top:4px;font-size:.85rem'>找到金額完全符的（綠底）→ 開該傳票，在備註或銷項稅額分錄摘要補上發票號 <code>" . h($i['invoice_number']) . "</code> 即可被對帳工具找到。</p>";
        }
        echo "</div>";
    }
}

if (!empty($mismatchInvs)) {
    echo "<h3 class='warn'>金額不符（" . count($mismatchInvs) . " 張）</h3>";
    echo "<table><tr><th>發票號碼</th><th>日期</th><th>客戶</th><th>聯式</th><th>發票稅額</th><th>分配到的 line 金額</th><th>差額</th><th>對應傳票</th></tr>";
    foreach ($mismatchInvs as $m) {
        $i = $m['inv'];
        $vList = array();
        foreach ($m['lines'] as $li) $vList[] = $li['voucher'];
        echo "<tr>";
        echo "<td><a href='/sales_invoices.php?action=edit&id=" . (int)$i['id'] . "'>" . h($i['invoice_number']) . "</a></td>";
        echo "<td>" . h($i['invoice_date']) . "</td>";
        echo "<td>" . h($i['customer_name']) . "</td>";
        echo "<td>" . h($i['invoice_format']) . "</td>";
        echo "<td>$" . number_format($m['invSigned']) . "</td>";
        echo "<td>$" . number_format($m['allocated']) . "</td>";
        echo "<td class='warn'>$" . number_format($m['invSigned'] - $m['allocated']) . "</td>";
        echo "<td>" . h(implode(', ', array_unique($vList))) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</body></html>";
