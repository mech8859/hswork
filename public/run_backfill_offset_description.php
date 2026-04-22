<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 撈出所有沖帳行：offset_flag=2 且 description 尚未包含 " | "（表示還沒補原立帳摘要）
// 透過 offset_ledger.journal_line_id 取原立帳行的 description
$sql = "
    SELECT jl.id, jl.description AS line_desc, jl.offset_ledger_id,
           orig.description AS ledger_desc, ol.voucher_number AS ledger_voucher
    FROM journal_entry_lines jl
    JOIN offset_ledger ol ON ol.id = jl.offset_ledger_id
    LEFT JOIN journal_entry_lines orig ON orig.id = ol.journal_line_id
    WHERE jl.offset_flag = 2
      AND jl.offset_ledger_id IS NOT NULL
      AND (jl.description IS NULL OR jl.description NOT LIKE '% | %')
";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
echo "符合條件的沖帳行：{$total} 筆\n\n";

$updated = 0;
$skipped = 0;
$skipReasons = array();

foreach ($rows as $r) {
    $lineId = (int)$r['id'];
    $lineDesc = trim((string)$r['line_desc']);
    $ledgerDesc = trim((string)$r['ledger_desc']);

    if ($ledgerDesc === '') {
        $skipped++;
        $skipReasons['立帳摘要為空'] = ($skipReasons['立帳摘要為空'] ?? 0) + 1;
        continue;
    }

    $newDesc = ($lineDesc !== '' ? $lineDesc : '沖 ' . $r['ledger_voucher']) . ' | ' . $ledgerDesc;

    if ($execute) {
        $stmt = $db->prepare("UPDATE journal_entry_lines SET description = ? WHERE id = ?");
        $stmt->execute(array($newDesc, $lineId));
    }

    $updated++;
    if ($updated <= 15) {
        echo "[#{$lineId}] {$lineDesc}\n    → {$newDesc}\n\n";
    } elseif ($updated === 16) {
        echo "... (以下省略列印，僅顯示前 15 筆)\n\n";
    }
}

echo "\n---\n";
echo "已" . ($execute ? "更新" : "預計更新") . "：{$updated} 筆\n";
echo "略過：{$skipped} 筆\n";
foreach ($skipReasons as $reason => $cnt) {
    echo "  - {$reason}：{$cnt}\n";
}
echo $execute ? "\n\n✓ 完成" : "\n\n(預覽模式，無變更。加 ?execute=1 實際執行)\n";
