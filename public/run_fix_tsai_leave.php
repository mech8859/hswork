<?php
/**
 * 一次性：修正蔡文雄 2026-04-11 請假紀錄，特休 → 排休
 * 用法：/run_fix_tsai_leave.php           （預覽）
 *       /run_fix_tsai_leave.php?execute=1 （實際執行）
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

$stmt = $db->prepare("
    SELECT l.id, l.user_id, l.leave_type, l.start_date, l.end_date, l.status, u.real_name
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    WHERE u.real_name = ? AND l.start_date = ? AND l.leave_type = 'annual'
");
$stmt->execute(array('蔡文雄', '2026-04-11'));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "找不到符合條件的紀錄（蔡文雄 2026-04-11 特休）。\n";
    exit;
}

foreach ($rows as $r) {
    echo "找到紀錄：id={$r['id']}  {$r['real_name']}  {$r['start_date']}~{$r['end_date']}  leave_type={$r['leave_type']}  status={$r['status']}\n";
    echo "  → 要改為 leave_type='day_off'\n";
    if ($execute) {
        $up = $db->prepare("UPDATE leaves SET leave_type = 'day_off' WHERE id = ?");
        $up->execute(array((int)$r['id']));
        echo "  ✓ 已更新\n";
    }
}

echo "\n完成。";
echo $execute ? "\n" : "\n(預覽模式，無變更)\n";
