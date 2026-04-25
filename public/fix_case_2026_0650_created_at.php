<?php
/**
 * 一次性修正：案件 2026-0650 進件日期 → 2025-11-30
 * 跑完請刪除此檔
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$caseNumber = '2026-0650';
$newDate = '2025-11-30 08:00:00';

$cur = $db->prepare("SELECT id, case_number, created_at FROM cases WHERE case_number = ?");
$cur->execute(array($caseNumber));
$row = $cur->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "找不到案件 {$caseNumber}\n";
    exit;
}

echo "找到案件 ID={$row['id']}  原進件日期={$row['created_at']}\n";

$upd = $db->prepare("UPDATE cases SET created_at = ? WHERE id = ?");
$upd->execute(array($newDate, $row['id']));

$chk = $db->prepare("SELECT created_at FROM cases WHERE id = ?");
$chk->execute(array($row['id']));
echo "更新完成  新進件日期=" . $chk->fetchColumn() . "\n";

AuditLog::log('cases', 'manual_fix', $row['id'], $caseNumber, '進件日期修正：' . $row['created_at'] . ' → ' . $newDate);
echo "Done. 請刪除此檔（fix_case_2026_0650_created_at.php）\n";
