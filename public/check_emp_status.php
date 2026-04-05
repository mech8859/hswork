<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$rows = $db->query("SELECT employment_status, COUNT(*) as cnt FROM users WHERE is_active = 1 GROUP BY employment_status ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "=== employment_status 統計 ===\n\n";
foreach ($rows as $r) {
    echo "  [{$r['employment_status']}] => {$r['cnt']} 筆\n";
}

echo "\n=== 全部（含停用）===\n";
$all = $db->query("SELECT employment_status, is_active, COUNT(*) as cnt FROM users GROUP BY employment_status, is_active ORDER BY is_active DESC, cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $r) {
    $active = $r['is_active'] ? '啟用' : '停用';
    echo "  [{$r['employment_status']}] {$active} => {$r['cnt']} 筆\n";
}
