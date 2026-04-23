<?php
/**
 * 一次性：掃既有報價單，branch_id 為 0 或 NULL 的依承辦業務分公司回填。
 * 用法：/run_backfill_quote_branch.php           （預覽）
 *       /run_backfill_quote_branch.php?execute=1 （實際執行）
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin') && Auth::user()['role'] !== 'boss') {
    die('需要管理員權限');
}
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';

echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 1) 掃沒有分公司的報價單
$stmt = $db->query("
    SELECT q.id, q.quotation_number, q.sales_id, q.branch_id,
           u.real_name AS sales_name, u.branch_id AS sales_branch_id,
           b.name AS sales_branch_name
    FROM quotations q
    LEFT JOIN users u ON q.sales_id = u.id
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE q.branch_id IS NULL OR q.branch_id = 0
    ORDER BY q.id
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- 找到 " . count($rows) . " 筆 branch_id 為空的報價單 ---\n\n";

if (empty($rows)) {
    echo "所有報價單都有分公司，無需回填。\n";
    exit;
}

$canFix = 0;
$cannotFix = array();
foreach ($rows as $r) {
    if (!empty($r['sales_branch_id'])) {
        echo "  [可補] #{$r['quotation_number']} (id={$r['id']}) sales={$r['sales_name']} → branch_id={$r['sales_branch_id']} ({$r['sales_branch_name']})\n";
        $canFix++;
    } else {
        $cannotFix[] = $r;
        echo "  [無法補] #{$r['quotation_number']} (id={$r['id']}) sales=" . ($r['sales_name'] ?: '(無)') . "，該業務沒有分公司\n";
    }
}

echo "\n可補 {$canFix} 筆；無法補 " . count($cannotFix) . " 筆\n";

if ($execute && $canFix > 0) {
    echo "\n--- 執行回填 ---\n";
    $up = $db->prepare("UPDATE quotations SET branch_id = ? WHERE id = ?");
    $done = 0;
    foreach ($rows as $r) {
        if (!empty($r['sales_branch_id'])) {
            $up->execute(array((int)$r['sales_branch_id'], (int)$r['id']));
            $done++;
        }
    }
    echo "  ✓ 已更新 {$done} 筆\n";
}

if (!empty($cannotFix)) {
    echo "\n--- 無法自動補的清單（需人工處理）---\n";
    foreach ($cannotFix as $r) {
        echo "  #{$r['quotation_number']} (id={$r['id']})\n";
    }
    echo "建議：直接在 DB 手動指定 branch_id，或該業務補設定分公司後重跑此腳本\n";
}

echo "\n完成。";
echo $execute ? "\n" : "\n(預覽模式，無變更)\n";
