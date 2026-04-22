<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

echo "=== offset_ledger 欄位 ===\n";
foreach ($db->query("SHOW COLUMNS FROM offset_ledger")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo sprintf("%-30s %s\n", $c['Field'], $c['Type']);
}

echo "\n=== journal_entry_lines 欄位（過濾 offset/desc） ===\n";
foreach ($db->query("SHOW COLUMNS FROM journal_entry_lines")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if (stripos($c['Field'], 'offset') !== false || stripos($c['Field'], 'desc') !== false) {
        echo sprintf("%-30s %s\n", $c['Field'], $c['Type']);
    }
}

echo "\n=== 沖帳行統計（offset_flag=2） ===\n";
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN offset_ledger_id IS NOT NULL THEN 1 ELSE 0 END) AS has_ledger_id,
        SUM(CASE WHEN offset_ref_id IS NOT NULL THEN 1 ELSE 0 END) AS has_ref_id,
        SUM(CASE WHEN description IS NULL OR description = '' THEN 1 ELSE 0 END) AS empty_desc,
        SUM(CASE WHEN description LIKE '% | %' THEN 1 ELSE 0 END) AS has_pipe
    FROM journal_entry_lines WHERE offset_flag = 2
")->fetch(PDO::FETCH_ASSOC);
foreach ($stats as $k => $v) echo sprintf("%-20s %s\n", $k, $v);

echo "\n=== 樣本 5 筆沖帳行 ===\n";
foreach ($db->query("SELECT id, offset_ledger_id, offset_ref_id, description FROM journal_entry_lines WHERE offset_flag = 2 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
