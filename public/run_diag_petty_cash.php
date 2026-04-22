<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

echo "=== petty_cash 資料診斷 ===\n\n";

$total = (int)$db->query("SELECT COUNT(*) FROM petty_cash")->fetchColumn();
echo "資料表總筆數：{$total}\n\n";

if ($total > 0) {
    echo "各分公司筆數：\n";
    $byBranch = $db->query("SELECT branch_id, COUNT(*) c FROM petty_cash GROUP BY branch_id ORDER BY branch_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($byBranch as $b) {
        echo "  branch_id={$b['branch_id']}: {$b['c']} 筆\n";
    }
    echo "\n最新 5 筆：\n";
    foreach ($db->query("SELECT id, entry_date, branch_id, type, amount, description FROM petty_cash ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== 當前登入者 ===\n";
$u = Auth::user();
echo "id={$u['id']}, role={$u['role']}, branch_id={$u['branch_id']}, can_view_all={$u['can_view_all_branches']}\n";
echo "accessibleBranchIds: " . json_encode(Auth::getAccessibleBranchIds()) . "\n";
