<?php
/**
 * 一次性修復：沖帳行的 offset_ledger_id 指向已不存在的立帳記錄時，
 * 用 offset_ref_id（沖帳對應立帳行 ID）重新綁定到目前有效的 offset_ledger.id
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
    // 找出所有「offset_flag=2（沖帳）+ 有 offset_ledger_id」的行
    $allStmt = $db->query("
        SELECT jl.id, jl.offset_ledger_id, jl.offset_ref_id, jl.description, jl.journal_entry_id,
               je.voucher_number AS my_voucher
        FROM journal_entry_lines jl
        JOIN journal_entries je ON jl.journal_entry_id = je.id
        WHERE jl.offset_flag = 2 AND jl.offset_ledger_id IS NOT NULL
    ");
    $rows = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($rows);
    $fixed = 0;
    $stillBroken = array();

    echo "掃描 {$total} 筆沖帳行...\n\n";

    $check = $db->prepare("SELECT id FROM offset_ledger WHERE id = ?");
    $findByLine = $db->prepare("SELECT id FROM offset_ledger WHERE journal_line_id = ?");
    $update = $db->prepare("UPDATE journal_entry_lines SET offset_ledger_id = ? WHERE id = ?");

    foreach ($rows as $r) {
        $lid = (int)$r['offset_ledger_id'];
        $check->execute(array($lid));
        if ($check->fetchColumn()) continue; // 還有效，跳過

        // 失效 → 試著用 offset_ref_id 重綁
        $refId = (int)$r['offset_ref_id'];
        if ($refId > 0) {
            $findByLine->execute(array($refId));
            $newLid = $findByLine->fetchColumn();
            if ($newLid) {
                $update->execute(array((int)$newLid, $r['id']));
                $fixed++;
                echo "  ✓ {$r['my_voucher']} 行 {$r['id']}：{$lid} → {$newLid}\n";
                continue;
            }
        }
        $stillBroken[] = $r;
    }

    echo "\n=== 結果 ===\n";
    echo "已修復：{$fixed} 筆\n";
    echo "仍無法修復：" . count($stillBroken) . " 筆\n";
    if (!empty($stillBroken)) {
        echo "\n以下沖帳行需要人工處理（offset_ref_id 也找不到對應立帳，可能立帳已被刪）：\n";
        foreach ($stillBroken as $r) {
            echo "  傳票 {$r['my_voucher']} 行 ID {$r['id']}：原 ledger_id={$r['offset_ledger_id']}, ref_id={$r['offset_ref_id']}, 摘要：{$r['description']}\n";
        }
    }

    AuditLog::log('offset_ledger', 'bulk_rebind', 0, "重綁 {$fixed} 筆沖帳行");
    echo "\nDone. 請刪除此檔（fix_offset_ledger_rebind.php）\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
