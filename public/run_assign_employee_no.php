<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';
$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 規則：
// 1. 只有有身分證字號的才編號
// 2. 王正宏 = 001, 陳宏璇 = 002
// 3. 其餘按到職日排序
// 4. 離職也算

// 先取所有有身分證的用戶（排除點工 dispatch_worker）
$stmt = $db->query("
    SELECT id, real_name, employee_id, id_number, hire_date, employment_status
    FROM users
    WHERE id_number IS NOT NULL AND id_number != ''
      AND (employment_status IS NULL OR employment_status != 'dispatch_worker')
    ORDER BY
        CASE
            WHEN real_name = '王正宏' THEN 0
            WHEN real_name = '陳宏璇' THEN 1
            ELSE 2
        END,
        COALESCE(hire_date, '9999-12-31') ASC,
        id ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "共 " . count($users) . " 位有身分證的人員\n\n";

$num = 1;
foreach ($users as $u) {
    $newNo = str_pad($num, 3, '0', STR_PAD_LEFT);
    $oldNo = $u['employee_id'] ?: '(無)';
    $statusLabel = $u['employment_status'] === 'resigned' ? '離職' : '在職';
    $hireDate = $u['hire_date'] ?: '(無到職日)';

    echo "[{$newNo}] {$u['real_name']} | 到職: {$hireDate} | {$statusLabel} | 舊編號: {$oldNo}";
    if ($oldNo !== $newNo) echo " → 改為 {$newNo}";
    echo "\n";

    if ($execute && $oldNo !== $newNo) {
        $db->prepare("UPDATE users SET employee_id = ? WHERE id = ?")->execute(array($newNo, $u['id']));
    }
    $num++;
}

echo "\n==============================\n";
echo "完成！共編號 " . ($num - 1) . " 位\n";
echo '</pre>';
