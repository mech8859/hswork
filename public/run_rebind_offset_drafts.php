<?php
/**
 * 批次掃描所有 draft 沖帳傳票，自動重綁到當前 offset_ledger
 * 預覽：/run_rebind_offset_drafts.php
 * 執行：/run_rebind_offset_drafts.php?confirm=1
 *
 * 額外：偵測「立帳行 但 offset_ledger 沒對應」的問題（極少見）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all') && !Auth::hasPermission('accounting.manage')) die('admin only');

$db = Database::getInstance();
$confirm = !empty($_GET['confirm']);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>沖帳自動重綁</title>";
echo "<style>
body{font-family:-apple-system,sans-serif;padding:20px;font-size:14px;line-height:1.5;max-width:1400px}
h1{color:#1f4e79}
table{border-collapse:collapse;margin:8px 0;width:100%}
th,td{border:1px solid #ddd;padding:6px 10px;text-align:left}
th{background:#f0f8ff}
tr:nth-child(even){background:#fafafa}
.warn{color:#c5221f;font-weight:600}
.ok{color:#16a34a;font-weight:600}
.muted{color:#999}
.summary{background:#fff3cd;padding:10px 14px;border-left:4px solid #f9a825;margin:12px 0;border-radius:4px}
.btn{display:inline-block;padding:10px 20px;background:#dc3545;color:#fff;border-radius:4px;text-decoration:none;font-weight:600}
.btn-cancel{background:#6c757d;margin-left:8px}
code{background:#f5f5f5;padding:2px 6px;border-radius:3px}
</style></head><body>";

echo "<h1>🔧 沖帳自動重綁工具</h1>";
echo "<p class='muted'>掃描所有 <strong>draft 狀態</strong>且含「沖帳」的傳票，重新綁定到當前可用的 offset_ledger。</p>";

// === 找所有 draft 中含沖帳行 ===
$stmt = $db->query("
    SELECT je.id AS je_id, je.voucher_number, je.voucher_date, je.description AS je_desc,
           jl.id AS line_id, jl.account_id, jl.relation_type, jl.relation_id, jl.relation_name,
           jl.offset_ledger_id, jl.offset_amount, jl.offset_ref_id,
           jl.debit_amount, jl.credit_amount, jl.description AS line_desc,
           c.account_code, c.account_name
    FROM journal_entries je
    JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id
    LEFT JOIN chart_of_accounts c ON c.id = jl.account_id
    WHERE je.status = 'draft' AND jl.offset_flag = 2
    ORDER BY je.voucher_date DESC, je.id, jl.sort_order
");
$draftOffsetLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($draftOffsetLines)) {
    echo "<p class='ok'>✓ 沒有任何 draft 沖帳行需要處理</p></body></html>";
    exit;
}

// === 對每行檢查是否需重綁 ===
$rebindCandidates = array();   // 需要且能重綁
$invalidLines = array();        // 找不到對應立帳的（無法重綁）
$okLines = 0;

$ledgerCheck = $db->prepare("SELECT id, remaining_amount, voucher_number FROM offset_ledger WHERE id = ?");
$rebindByLineRef = $db->prepare("SELECT id FROM offset_ledger WHERE journal_line_id = ?");
$rebindByVoucher = $db->prepare("
    SELECT id, voucher_number, original_amount, remaining_amount
    FROM offset_ledger
    WHERE voucher_number = ? AND account_id = ?
      AND (relation_id <=> ?) AND (relation_type <=> ?)
    ORDER BY ABS(original_amount - ?) ASC, id DESC
    LIMIT 1
");
$rebindByRel = $db->prepare("
    SELECT id, voucher_number, original_amount, remaining_amount
    FROM offset_ledger
    WHERE account_id = ?
      AND (relation_id <=> ?) AND (relation_type <=> ?)
      AND status = 'open'
    ORDER BY ABS(original_amount - ?) ASC, id DESC
    LIMIT 1
");

foreach ($draftOffsetLines as $l) {
    $lid = (int)$l['offset_ledger_id'];
    $offsetAmt = (float)$l['offset_amount'];

    // 確認當前綁的 ledger 是否還有效
    $isCurrentValid = false;
    if ($lid > 0) {
        $ledgerCheck->execute(array($lid));
        $cur = $ledgerCheck->fetch(PDO::FETCH_ASSOC);
        if ($cur && (float)$cur['remaining_amount'] >= $offsetAmt) {
            $isCurrentValid = true;
            $okLines++;
            continue;
        }
    }

    // 嘗試重綁
    $newId = 0;
    $strategy = '';

    // 策略 1：原 line_id
    if (!empty($l['offset_ref_id'])) {
        $rebindByLineRef->execute(array((int)$l['offset_ref_id']));
        $f = $rebindByLineRef->fetchColumn();
        if ($f) { $newId = (int)$f; $strategy = '原 line ref'; }
    }

    // 策略 2：摘要抓 JV-...
    if (!$newId && preg_match('/(JV-\d{8}-\d{3})/', (string)$l['line_desc'], $m)) {
        $rebindByVoucher->execute(array($m[1], (int)$l['account_id'], $l['relation_id'], $l['relation_type'], $offsetAmt));
        $f = $rebindByVoucher->fetch(PDO::FETCH_ASSOC);
        if ($f) { $newId = (int)$f['id']; $strategy = '摘要 ' . $m[1]; }
    }

    // 策略 3：同科目 + 往來對象 + 開放中（金額最近）
    if (!$newId) {
        $rebindByRel->execute(array((int)$l['account_id'], $l['relation_id'], $l['relation_type'], $offsetAmt));
        $f = $rebindByRel->fetch(PDO::FETCH_ASSOC);
        if ($f && (float)$f['remaining_amount'] >= $offsetAmt) {
            $newId = (int)$f['id'];
            $strategy = '同科目+往來';
        }
    }

    if ($newId) {
        $rebindCandidates[] = array(
            'line_id' => (int)$l['line_id'],
            'old_ledger' => $lid,
            'new_ledger' => $newId,
            'strategy' => $strategy,
            'voucher' => $l['voucher_number'],
            'account' => $l['account_code'] . ' ' . $l['account_name'],
            'relation' => $l['relation_name'],
            'amount' => $offsetAmt,
            'line_desc' => $l['line_desc'],
        );
    } else {
        $invalidLines[] = array_merge($l, array('amt' => $offsetAmt));
    }
}

// === 統計 ===
echo "<div class='summary'>";
echo "<strong>掃描結果</strong>：共 " . count($draftOffsetLines) . " 行沖帳<br>";
echo "✓ 已正常綁定：{$okLines} 行<br>";
echo "🔧 可自動重綁：" . count($rebindCandidates) . " 行<br>";
echo "❌ 無法自動重綁（需手動處理）：" . count($invalidLines) . " 行";
echo "</div>";

// === 執行區 ===
if ($confirm && !empty($rebindCandidates)) {
    $upd = $db->prepare("UPDATE journal_entry_lines SET offset_ledger_id = ? WHERE id = ?");
    $changed = 0;
    foreach ($rebindCandidates as $c) {
        $upd->execute(array($c['new_ledger'], $c['line_id']));
        if ($upd->rowCount() > 0) $changed++;
    }
    echo "<p class='ok'>✓ 已執行重綁：{$changed} 行更新成功</p>";
    if (class_exists('AuditLog')) {
        try { AuditLog::log('journal_entry_lines', 'rebind', 0, "批次重綁沖帳 line {$changed} 行"); } catch (Exception $e) {}
    }
}

// === 可重綁清單 ===
if (!empty($rebindCandidates)) {
    echo "<h2>🔧 可自動重綁清單（" . count($rebindCandidates) . " 行）</h2>";
    if (!$confirm) {
        echo "<a href='?confirm=1' class='btn' onclick=\"return confirm('確定執行重綁 " . count($rebindCandidates) . " 行嗎？')\">執行重綁</a>";
        echo "<a href='/accounting.php' class='btn btn-cancel'>取消</a>";
    }
    echo "<table><thead><tr>
        <th>傳票</th><th>科目</th><th>往來對象</th><th>金額</th>
        <th>舊 Ledger</th><th>→</th><th>新 Ledger</th><th>策略</th>
    </tr></thead><tbody>";
    foreach ($rebindCandidates as $c) {
        echo "<tr>";
        echo "<td>" . h($c['voucher']) . "</td>";
        echo "<td>" . h($c['account']) . "</td>";
        echo "<td>" . h($c['relation']) . "</td>";
        echo "<td>" . number_format($c['amount']) . "</td>";
        echo "<td class='warn'>" . (int)$c['old_ledger'] . "</td>";
        echo "<td>→</td>";
        echo "<td class='ok'>" . (int)$c['new_ledger'] . "</td>";
        echo "<td>" . h($c['strategy']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// === 無法重綁清單 ===
if (!empty($invalidLines)) {
    echo "<h2>❌ 無法自動重綁（需手動處理 " . count($invalidLines) . " 行）</h2>";
    echo "<p class='muted'>找不到對應的開放中立帳。可能：1) 立帳已全沖完 2) 摘要沒寫 JV 編號 3) 往來對象不一致 4) 立帳真的不存在</p>";
    echo "<table><thead><tr>
        <th>傳票</th><th>科目</th><th>往來對象</th><th>金額</th>
        <th>舊 Ledger ID</th><th>line 摘要</th>
    </tr></thead><tbody>";
    foreach ($invalidLines as $l) {
        echo "<tr>";
        echo "<td>" . h($l['voucher_number']) . "</td>";
        echo "<td>" . h($l['account_code']) . " " . h($l['account_name']) . "</td>";
        echo "<td>" . h($l['relation_name']) . "</td>";
        echo "<td>" . number_format($l['amt']) . "</td>";
        echo "<td class='warn'>" . (int)$l['offset_ledger_id'] . "</td>";
        echo "<td>" . h(mb_strimwidth((string)$l['line_desc'], 0, 80, '…', 'UTF-8')) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "<p class='muted'>處理方式：開該傳票編輯頁，沖帳行重新挑選要沖的立帳。</p>";
}

echo "</body></html>";
