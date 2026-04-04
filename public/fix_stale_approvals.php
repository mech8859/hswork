<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 清除 purchases #19 和 #20 的 stale pending flows
$stmt = $db->query("
    SELECT af.id, af.module, af.target_id, af.status
    FROM approval_flows af
    WHERE af.module = 'purchases' AND af.status = 'pending'
      AND af.target_id IN (19, 20)
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "flow_id=" . $r['id'] . " target_id=" . $r['target_id'] . "\n";
}

// 刪除這些 stale flows
$db->query("DELETE FROM approval_flows WHERE module = 'purchases' AND status = 'pending' AND target_id IN (19, 20)");
echo "Deleted.\n";

// 也把對應的請購單狀態修正（如果還是簽核中）
$db->query("UPDATE requisitions SET status = '已核准' WHERE id IN (19, 20) AND status = '簽核中'");
echo "Requisition status fixed.\n";
echo "Done.\n";
