<?php
/**
 * 修復「立帳行沒有對應 offset_ledger 紀錄」的孤兒立帳
 * 自動補建 ledger，並重綁所有指向該舊 ID 的沖帳行
 *
 * 預覽：/run_fix_orphan_ledger.php
 * 執行：/run_fix_orphan_ledger.php?confirm=1
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all') && !Auth::hasPermission('accounting.manage')) die('admin only');

$db = Database::getInstance();
$confirm = !empty($_GET['confirm']);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>修復孤兒立帳</title>";
echo "<style>
body{font-family:-apple-system,sans-serif;padding:20px;font-size:14px;line-height:1.6;max-width:1400px}
h1{color:#1f4e79}
table{border-collapse:collapse;margin:8px 0;width:100%}
th,td{border:1px solid #ddd;padding:6px 10px}
th{background:#f0f8ff;text-align:left}
tr:nth-child(even){background:#fafafa}
.warn{color:#c5221f}
.ok{color:#16a34a;font-weight:600}
.summary{background:#fff3cd;padding:12px 16px;border-left:4px solid #f9a825;margin:12px 0;border-radius:4px}
.btn{display:inline-block;padding:10px 20px;background:#dc3545;color:#fff;border-radius:4px;text-decoration:none;font-weight:600}
.btn-cancel{background:#6c757d;margin-left:8px}
code{background:#f5f5f5;padding:2px 6px;border-radius:3px}
</style></head><body>";

echo "<h1>🩺 修復孤兒立帳（補建 offset_ledger）</h1>";
echo "<p>掃描所有「offset_flag=1（立帳）但在 offset_ledger 表中沒紀錄」的分錄行，自動補建 ledger。</p>";
echo "<p class='warn'>⚠ 安全機制：只處理 <strong>已過帳</strong>傳票的立帳行（draft 不動）。</p>";

// === 找所有立帳但沒對應 ledger 的行 ===
$orphans = $db->query("
    SELECT jl.id AS line_id, jl.journal_entry_id, jl.account_id, jl.cost_center_id,
           jl.relation_type, jl.relation_id, jl.relation_name,
           jl.debit_amount, jl.credit_amount, jl.description AS line_desc,
           je.voucher_number, je.voucher_date, je.status,
           c.account_code, c.account_name
    FROM journal_entry_lines jl
    JOIN journal_entries je ON je.id = jl.journal_entry_id
    LEFT JOIN chart_of_accounts c ON c.id = jl.account_id
    LEFT JOIN offset_ledger ol ON ol.journal_line_id = jl.id
    WHERE jl.offset_flag = 1
      AND je.status = 'posted'
      AND ol.id IS NULL
    ORDER BY je.voucher_date, je.id
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($orphans)) {
    echo "<p class='ok'>✓ 沒有孤兒立帳需要修復</p></body></html>";
    exit;
}

echo "<div class='summary'>找到 <strong>" . count($orphans) . "</strong> 筆孤兒立帳行（已過帳但缺 ledger）</div>";

// === 顯示清單 ===
echo "<h2>清單</h2>";
echo "<table><thead><tr>
<th>傳票</th><th>line ID</th><th>科目</th><th>往來</th><th>金額</th><th>方向</th><th>line 摘要</th>
</tr></thead><tbody>";
foreach ($orphans as $o) {
    $amt = max((float)$o['debit_amount'], (float)$o['credit_amount']);
    $direction = (float)$o['debit_amount'] > 0 ? 'debit (借)' : 'credit (貸)';
    echo "<tr>";
    echo "<td>" . h($o['voucher_number']) . "</td>";
    echo "<td>" . (int)$o['line_id'] . "</td>";
    echo "<td>" . h($o['account_code']) . " " . h($o['account_name']) . "</td>";
    echo "<td>" . h($o['relation_name']) . "</td>";
    echo "<td>" . number_format($amt) . "</td>";
    echo "<td>" . h($direction) . "</td>";
    echo "<td>" . h(mb_strimwidth((string)$o['line_desc'], 0, 60, '…', 'UTF-8')) . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";

if (!$confirm) {
    echo "<div class='summary'>⚠ 目前是<strong>預覽模式</strong>。確認以上清單無誤再執行：</div>";
    echo "<a href='?confirm=1' class='btn' onclick=\"return confirm('確定補建 " . count($orphans) . " 筆 ledger？此動作會 INSERT offset_ledger 並嘗試重綁可能受影響的沖帳行。')\">執行補建 + 重綁</a>";
    echo "<a href='/accounting.php' class='btn btn-cancel'>取消</a>";
    echo "</body></html>";
    exit;
}

// === 執行補建 ===
$db->beginTransaction();
try {
    $insStmt = $db->prepare("
        INSERT INTO offset_ledger
        (journal_entry_id, journal_line_id, account_id, cost_center_id,
         relation_type, relation_id, relation_name,
         voucher_date, voucher_number, direction,
         original_amount, offset_total, remaining_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'open')
    ");

    $created = 0;
    $rebound = 0;
    $createdLogs = array();
    foreach ($orphans as $o) {
        $amt = max((float)$o['debit_amount'], (float)$o['credit_amount']);
        $direction = (float)$o['debit_amount'] > 0 ? 'debit' : 'credit';
        $insStmt->execute(array(
            $o['journal_entry_id'], $o['line_id'], $o['account_id'], $o['cost_center_id'],
            $o['relation_type'], $o['relation_id'], $o['relation_name'],
            $o['voucher_date'], $o['voucher_number'], $direction,
            $amt, $amt,
        ));
        $newLedgerId = (int)$db->lastInsertId();
        $created++;

        // 嘗試找指向「不存在 ledger」且其他條件相符的沖帳行重綁
        // 條件：相同 account_id + relation_id + 摘要含本傳票號
        $rebindStmt = $db->prepare("
            SELECT jl.id, jl.offset_ledger_id, jl.offset_amount
            FROM journal_entry_lines jl
            JOIN journal_entries je ON je.id = jl.journal_entry_id
            LEFT JOIN offset_ledger ol ON ol.id = jl.offset_ledger_id
            WHERE jl.offset_flag = 2
              AND jl.account_id = ?
              AND (jl.relation_id <=> ?) AND (jl.relation_type <=> ?)
              AND jl.description LIKE ?
              AND ol.id IS NULL
              AND je.status IN ('draft','posted')
        ");
        $rebindStmt->execute(array(
            $o['account_id'], $o['relation_id'], $o['relation_type'],
            '%' . $o['voucher_number'] . '%',
        ));
        $candidates = $rebindStmt->fetchAll(PDO::FETCH_ASSOC);

        $remainingForLedger = $amt;
        $upd = $db->prepare("UPDATE journal_entry_lines SET offset_ledger_id = ? WHERE id = ?");
        $offUpd = $db->prepare("UPDATE offset_ledger SET offset_total = offset_total + ?, remaining_amount = remaining_amount - ?, status = IF(remaining_amount - ? <= 0, 'closed', 'open') WHERE id = ?");
        $detIns = $db->prepare("INSERT INTO offset_details (ledger_id, journal_entry_id, journal_line_id, offset_amount, voucher_date, voucher_number) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($candidates as $c) {
            if ($remainingForLedger <= 0) break;
            $offsetAmt = (float)$c['offset_amount'];
            if ($offsetAmt > $remainingForLedger) continue; // 沖帳金額超過原始，先略過

            // 找該沖帳行所屬傳票
            $entryStmt = $db->prepare("SELECT je.id, je.voucher_date, je.voucher_number, je.status FROM journal_entries je JOIN journal_entry_lines jl ON jl.journal_entry_id = je.id WHERE jl.id = ?");
            $entryStmt->execute(array($c['id']));
            $entry = $entryStmt->fetch(PDO::FETCH_ASSOC);
            if (!$entry) continue;

            // 重綁 line
            $upd->execute(array($newLedgerId, $c['id']));
            $rebound++;

            // 若該沖帳傳票已過帳，要補寫 offset_details + 更新 ledger 餘額（讓帳目一致）
            if ($entry['status'] === 'posted') {
                $detIns->execute(array($newLedgerId, $entry['id'], $c['id'], $offsetAmt, $entry['voucher_date'], $entry['voucher_number']));
                $offUpd->execute(array($offsetAmt, $offsetAmt, $offsetAmt, $newLedgerId));
                $remainingForLedger -= $offsetAmt;
            }
            // draft 不寫沖帳明細，等使用者手動過帳時系統會自動寫
        }

        $createdLogs[] = "Voucher {$o['voucher_number']} line {$o['line_id']} → 新 ledger ID {$newLedgerId}";
    }

    $db->commit();

    if (class_exists('AuditLog')) {
        try { AuditLog::log('offset_ledger', 'fix_orphan', 0, "補建 {$created} 筆孤兒立帳 + 重綁 {$rebound} 筆沖帳行"); } catch (Exception $e) {}
    }

    echo "<p class='ok'>✓ 補建完成：建立 {$created} 筆 ledger，重綁 {$rebound} 筆沖帳行</p>";
    echo "<h3>明細</h3><ul>";
    foreach ($createdLogs as $log) echo "<li>" . h($log) . "</li>";
    echo "</ul>";
    echo "<p><a href='/accounting.php'>返回會計</a></p>";

} catch (Exception $e) {
    $db->rollBack();
    echo "<p class='warn'>❌ 失敗：" . h($e->getMessage()) . "</p>";
}

echo "</body></html>";
