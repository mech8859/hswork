<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';
$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 從 Ragic 標記為點工人員的 10 位
$dispatchList = array(
    array('00624', '鳴勝工程行-水電 廖述忠', 'backup'),  // 預備
    array('00625', '劉建鑫',                   'primary'), // 優先
    array('00626', '劉享和',                   'primary'),
    array('00627', '張富全',                   'primary'),
    array('00628', '張智凱(黑誌)',             'backup'),  // 預備
    array('00629', '張俊翔(苗栗)',             'primary'),
    array('00630', '黃繹軒(苗栗)',             'backup'),  // 預備
    array('00632', '陳聰明',                   'backup'),  // 預備
    array('00633', '葉木松',                   'primary'),
    array('00634', '賴誌德',                   'primary'),
);

$moved = 0;
$skipped = 0;

foreach ($dispatchList as $item) {
    $empId = $item[0];
    $expectedName = $item[1];
    $status = $item[2];

    // 從 users 表找到這個人
    $stmt = $db->prepare("SELECT * FROM users WHERE employee_id = ?");
    $stmt->execute(array($empId));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "[{$empId}] {$expectedName} — users 表中找不到，跳過\n\n";
        $skipped++;
        continue;
    }

    // 檢查 dispatch_workers 是否已存在
    $chk = $db->prepare("SELECT id FROM dispatch_workers WHERE name = ? OR (id_number = ? AND id_number != '')");
    $chk->execute(array($user['real_name'], $user['id_number'] ?: '__none__'));
    $existId = $chk->fetchColumn();

    $statusLabel = $status === 'primary' ? '優先' : '預備';
    echo "[{$empId}] {$user['real_name']} (users ID:{$user['id']})\n";
    echo "  部門: {$user['department']} | 手機: {$user['phone']} | 狀態: {$statusLabel}\n";
    echo "  身分證: {$user['id_number']} | 地址: {$user['address']}\n";

    if ($existId) {
        echo "  → dispatch_workers 已存在 (ID:{$existId})，跳過\n\n";
        $skipped++;
        continue;
    }

    echo "  → 移至 dispatch_workers\n";

    if ($execute) {
        // 新增到 dispatch_workers
        $db->prepare("
            INSERT INTO dispatch_workers (worker_type, name, id_number, phone, address, birth_date, specialty, status, daily_rate, emergency_contact, emergency_phone, note, is_active)
            VALUES ('dispatch', ?, ?, ?, ?, ?, ?, ?, 0, '', '', ?, 1)
        ")->execute(array(
            $user['real_name'],
            $user['id_number'] ?: '',
            $user['phone'] ?: '',
            $user['address'] ?: '',
            $user['birth_date'] ?: null,
            $user['job_title'] ?: '工程師',
            $status,
            '從 Ragic 匯入，原員工編號: ' . $empId
        ));
        $newId = $db->lastInsertId();
        echo "  → 已新增 dispatch_workers ID:{$newId}\n";

        // 停用 users 帳號
        $db->prepare("UPDATE users SET is_active = 0, employment_status = 'dispatch_worker' WHERE id = ?")->execute(array($user['id']));
        echo "  → users 帳號已停用\n";

        $moved++;
    } else {
        $moved++;
    }
    echo "\n";
}

echo "==============================\n";
echo "完成！移動: {$moved} 筆，跳過: {$skipped} 筆\n";
echo "\n<a href='/dispatch_workers.php?type=dispatch'>→ 查看點工人員</a>\n";
echo '</pre>';
