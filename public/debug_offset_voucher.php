<?php
/**
 * 診斷沖帳問題
 * 用法：/debug_offset_voucher.php?voucher=JV-20260331-033
 *      /debug_offset_voucher.php?voucher=JV-20260327-006
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all') && !Auth::hasPermission('accounting.manage')) die('admin only');

$db = Database::getInstance();
$vno = isset($_GET['voucher']) ? trim($_GET['voucher']) : 'JV-20260331-033';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>沖帳診斷</title>";
echo "<style>
body{font-family:-apple-system,sans-serif;padding:20px;font-size:14px;line-height:1.5}
table{border-collapse:collapse;margin:8px 0}
th,td{border:1px solid #ccc;padding:6px 10px;text-align:left}
th{background:#f0f0f0}
.warn{color:#c5221f;font-weight:600}
.ok{color:#16a34a}
h2{color:#1f4e79;border-bottom:2px solid #1f4e79;padding-bottom:4px}
</style></head><body>";

echo "<h1>沖帳診斷：$vno</h1>";
echo "<form><input name='voucher' value='" . h($vno) . "' style='width:200px'> <button>查另一張</button></form>";

// (1) 取得傳票
$je = $db->prepare("SELECT * FROM journal_entries WHERE voucher_number = ?");
$je->execute(array($vno));
$entry = $je->fetch(PDO::FETCH_ASSOC);
if (!$entry) { echo "<p class='warn'>找不到傳票</p></body></html>"; exit; }

echo "<h2>傳票資訊</h2>";
echo "<table>";
foreach (array('id','voucher_number','voucher_date','status','total_debit','total_credit','description') as $k) {
    echo "<tr><th>$k</th><td>" . h($entry[$k]) . "</td></tr>";
}
echo "</table>";

// (2) 分錄行 + 立沖資訊 + 對應科目立沖屬性
$lines = $db->prepare("
    SELECT jl.*, c.account_code, c.account_name, c.offset_type
    FROM journal_entry_lines jl
    LEFT JOIN chart_of_accounts c ON c.id = jl.account_id
    WHERE jl.journal_entry_id = ?
    ORDER BY jl.sort_order, jl.id
");
$lines->execute(array($entry['id']));
$lineRows = $lines->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>分錄行</h2>";
echo "<table><thead><tr>
<th>#</th><th>科目編號</th><th>科目名稱</th><th>科目立沖屬性</th><th>借方</th><th>貸方</th>
<th>offset_flag</th><th>flag 解讀</th><th>offset_ledger_id</th><th>offset_amount</th><th>摘要</th>
</tr></thead><tbody>";
$flagLabel = array(0 => '一般', 1 => '立帳', 2 => '沖帳');
foreach ($lineRows as $i => $l) {
    $flag = (int)$l['offset_flag'];
    $needsLedger = ($flag === 2);
    $hasLedger = !empty($l['offset_ledger_id']);
    echo "<tr>";
    echo "<td>" . ($i+1) . "</td>";
    echo "<td>" . h($l['account_code']) . "</td>";
    echo "<td>" . h($l['account_name']) . "</td>";
    echo "<td>" . h($l['offset_type']) . "</td>";
    echo "<td>" . number_format((float)$l['debit_amount']) . "</td>";
    echo "<td>" . number_format((float)$l['credit_amount']) . "</td>";
    echo "<td>" . $flag . "</td>";
    echo "<td>" . (isset($flagLabel[$flag]) ? $flagLabel[$flag] : '?') . "</td>";
    echo "<td class='" . ($needsLedger && !$hasLedger ? 'warn' : '') . "'>" . h($l['offset_ledger_id'] ?: '—') . "</td>";
    echo "<td>" . ($l['offset_amount'] !== null ? number_format((float)$l['offset_amount']) : '—') . "</td>";
    echo "<td>" . h(mb_strimwidth((string)$l['description'], 0, 60, '…', 'UTF-8')) . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";

// (3) 對每個 立帳 line 看是否有 offset_ledger
echo "<h2>立帳 (offset_flag=1) 對應 offset_ledger 紀錄</h2>";
$hasIssue = false;
foreach ($lineRows as $l) {
    if ((int)$l['offset_flag'] !== 1) continue;
    $check = $db->prepare("SELECT id, voucher_number, original_amount, offset_total, remaining_amount, status FROM offset_ledger WHERE journal_line_id = ?");
    $check->execute(array($l['id']));
    $ol = $check->fetch(PDO::FETCH_ASSOC);
    if (!$ol) {
        echo "<p class='warn'>⚠ 行 ID " . (int)$l['id'] . " (" . h($l['account_code']) . " " . h($l['account_name']) . ") 標 立帳 但 offset_ledger 沒有對應紀錄</p>";
        $hasIssue = true;
    } else {
        echo "<p class='ok'>✓ 行 ID " . (int)$l['id'] . " → ledger ID " . (int)$ol['id'] . "（原始 " . number_format($ol['original_amount']) . "，已沖 " . number_format($ol['offset_total']) . "，餘 " . number_format($ol['remaining_amount']) . "，狀態 " . h($ol['status']) . "）</p>";
    }
}
if (!$hasIssue && empty($lineRows)) echo "<p>無立帳行</p>";

// (4) 對每個 沖帳 line 看 ledger 是否存在 + 是否還有餘額
echo "<h2>沖帳 (offset_flag=2) 對應 offset_ledger 狀況</h2>";
foreach ($lineRows as $l) {
    if ((int)$l['offset_flag'] !== 2) continue;
    $lid = (int)$l['offset_ledger_id'];
    $offsetAmt = (float)$l['offset_amount'];

    if ($lid <= 0) {
        echo "<p class='warn'>⚠ 沖帳行 (科目 " . h($l['account_code']) . ", 金額 " . number_format($offsetAmt) . ") <strong>沒有 offset_ledger_id</strong>，無法沖帳。需重新編輯選擇要沖的立帳。</p>";
        continue;
    }
    $check = $db->prepare("SELECT id, voucher_number, original_amount, offset_total, remaining_amount, status FROM offset_ledger WHERE id = ?");
    $check->execute(array($lid));
    $ol = $check->fetch(PDO::FETCH_ASSOC);
    if (!$ol) {
        echo "<p class='warn'>⚠ 沖帳行指向的 ledger ID $lid 不存在於 offset_ledger 表（立帳已被刪？）</p>";
    } else {
        $msg = "ledger ID $lid（{$ol['voucher_number']}，原始 " . number_format($ol['original_amount']) . "，已沖 " . number_format($ol['offset_total']) . "，餘 " . number_format($ol['remaining_amount']) . "）";
        if ((float)$ol['remaining_amount'] <= 0) {
            echo "<p class='warn'>⚠ $msg — <strong>已無餘額可沖！</strong></p>";
        } elseif ($offsetAmt > (float)$ol['remaining_amount']) {
            echo "<p class='warn'>⚠ $msg — <strong>本筆沖帳 " . number_format($offsetAmt) . " 超過餘額！</strong></p>";
        } else {
            echo "<p class='ok'>✓ $msg — 可正常沖帳本筆 " . number_format($offsetAmt) . "</p>";
        }
    }
}

echo "<hr><p><a href='/accounting.php?action=journal_view&id=" . (int)$entry['id'] . "'>看傳票詳情</a></p>";
echo "</body></html>";
