<?php
/**
 * 進階修復：沒有 offset_ref_id 時，用描述中的「沖 JV-XXXX」+ 科目/往來/金額 比對找回對應的 offset_ledger.id
 * 跑完請刪除此檔
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    // 掃描所有「沖帳行 + offset_ledger_id 失效」
    $allStmt = $db->query("
        SELECT jl.id, jl.offset_ledger_id, jl.offset_ref_id, jl.account_id, jl.cost_center_id,
               jl.relation_type, jl.relation_id, jl.offset_amount, jl.description,
               jl.journal_entry_id, je.voucher_number AS my_voucher
        FROM journal_entry_lines jl
        JOIN journal_entries je ON jl.journal_entry_id = je.id
        LEFT JOIN offset_ledger ol ON ol.id = jl.offset_ledger_id
        WHERE jl.offset_flag = 2 AND jl.offset_ledger_id IS NOT NULL
          AND ol.id IS NULL
    ");
    $rows = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "找到 " . count($rows) . " 筆失效沖帳行\n\n";

    $fixed = 0;
    $stillBroken = array();
    $update = $db->prepare("UPDATE journal_entry_lines SET offset_ledger_id = ? WHERE id = ?");

    foreach ($rows as $r) {
        // 1. 從描述抓 JV-YYYYMMDD-NNN
        $matched = null;
        if (preg_match('/(JV-\d{8}-\d{3})/', (string)$r['description'], $m)) {
            $vn = $m[1];
            // 2. 找 offset_ledger：voucher_number + account_id + relation_id + original_amount = offset_amount
            $stmt = $db->prepare("
                SELECT id, original_amount, remaining_amount FROM offset_ledger
                WHERE voucher_number = ? AND account_id = ?
                  AND (relation_id <=> ?) AND (relation_type <=> ?)
                ORDER BY ABS(original_amount - ?) ASC, id DESC
                LIMIT 1
            ");
            $stmt->execute(array(
                $vn, $r['account_id'], $r['relation_id'], $r['relation_type'], $r['offset_amount']
            ));
            $cand = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cand) {
                $matched = (int)$cand['id'];
                echo "  ✓ {$r['my_voucher']} 行 {$r['id']} → 找到 ledger {$matched}（原 731 已失效，新綁到此）\n";
                $update->execute(array($matched, $r['id']));
                $fixed++;
                continue;
            }
        }
        $stillBroken[] = $r;
    }

    echo "\n=== 結果 ===\n";
    echo "已修復：{$fixed} 筆\n";
    echo "仍無法修復：" . count($stillBroken) . " 筆\n";
    foreach ($stillBroken as $r) {
        echo "  {$r['my_voucher']} 行 {$r['id']}：摘要『" . mb_substr($r['description'], 0, 40) . "』\n";
    }

    AuditLog::log('offset_ledger', 'bulk_rebind_v2', 0, "進階重綁 {$fixed} 筆沖帳行");
    echo "\nDone. 請刪除此檔（fix_offset_ledger_rebind2.php）\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
