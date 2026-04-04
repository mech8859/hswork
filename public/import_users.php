<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

$jsonFile = __DIR__ . '/users_import.json';
if (!file_exists($jsonFile)) {
    echo "ERROR: users_import.json not found.";
    exit;
}

// Branch map
$branchMap = array();
$brStmt = $db->query("SELECT id, code FROM branches");
while ($br = $brStmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$br['code']] = $br['id'];
}

// Delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete_imported') {
    echo "<h3>刪除匯入人員</h3>";
    $del = $db->exec("DELETE FROM users WHERE username != 'admin' AND username != 'boss'");
    echo "已刪除 {$del} 筆（保留 admin）<br><br>";
    echo "<a href='?' style='background:#2196F3;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>返回</a>";
    exit;
}

$all = json_decode(file_get_contents($jsonFile), true);
$total = count($all);

echo "<h3>人員資料匯入</h3>";
echo "總筆數: {$total}<br><br>";

// Default password
$defaultPass = password_hash('hs1357924680', PASSWORD_DEFAULT);

if (!isset($_GET['run'])) {
    // Preview
    echo "<b>預覽模式</b><br><br>";

    echo "<table border='1' cellpadding='4' cellspacing='0' style='font-size:13px;border-collapse:collapse'>";
    echo "<tr style='background:#eee'><th>#</th><th>姓名</th><th>角色</th><th>分公司</th><th>部門</th><th>狀態</th><th>電話</th></tr>";
    $roleLabels = array('boss'=>'管理者','manager'=>'管理者','sales_manager'=>'業務主管','eng_manager'=>'工程主管','eng_deputy'=>'工程副主管','engineer'=>'工程人員','sales'=>'業務','sales_assistant'=>'業務助理','admin_staff'=>'行政人員');
    $activeCnt = 0; $inactiveCnt = 0;
    foreach ($all as $i => $r) {
        $rl = isset($roleLabels[$r['role']]) ? $roleLabels[$r['role']] : $r['role'];
        $bc = $r['branch_code'];
        $isAct = isset($r['is_active']) ? (int)$r['is_active'] : 1;
        $statusLabel = $isAct ? '<span style="color:green">在職</span>' : '<span style="color:#999">離職</span>';
        if ($isAct) $activeCnt++; else $inactiveCnt++;
        $rowStyle = $isAct ? '' : ' style="color:#999"';
        echo "<tr{$rowStyle}><td>" . ($i+1) . "</td><td>" . htmlspecialchars($r['real_name']) . "</td><td>{$rl}</td><td>{$bc}</td><td>" . htmlspecialchars($r['dept'] ?: '') . "</td><td>{$statusLabel}</td><td>" . htmlspecialchars($r['phone'] ?: '') . "</td></tr>";
    }
    echo "</table><br>";
    echo "<b>在職: {$activeCnt} 人 | 離職/停薪/點工: {$inactiveCnt} 人</b><br>";

    $existing = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "目前系統人數: {$existing}<br><br>";

    echo "<a href='?run=1' style='background:#4CAF50;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:1.1em'>確認匯入</a><br><br>";
    echo "<a href='?action=delete_imported' style='background:#f44336;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>清除所有人員（保留admin）</a>";
    exit;
}

// Run import
$findByName = $db->prepare("SELECT id FROM users WHERE real_name = ? LIMIT 1");
$insertStmt = $db->prepare("INSERT INTO users (branch_id, username, password_hash, real_name, role, is_engineer, phone, email, is_active, is_mobile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$updateStmt = $db->prepare("UPDATE users SET branch_id=?, role=?, is_engineer=?, phone=?, email=?, is_active=?, is_mobile=? WHERE id=?");

$inserted = 0;
$updated = 0;
$skipped = 0;

foreach ($all as $r) {
    $branchId = isset($branchMap[$r['branch_code']]) ? $branchMap[$r['branch_code']] : 1;
    $isMobile = $r['is_engineer'] ? 1 : 0;

    // Check if user already exists
    $findByName->execute(array($r['real_name']));
    $exists = $findByName->fetch(PDO::FETCH_ASSOC);

    $isActive = isset($r['is_active']) ? (int)$r['is_active'] : 1;

    if ($exists) {
        $updateStmt->execute(array(
            $branchId, $r['role'], $r['is_engineer'],
            $r['phone'] ?: null, $r['email'] ?: null,
            $isActive, $isMobile, $exists['id']
        ));
        $updated++;
    } else {
        // Generate unique username
        $username = $r['username'];
        $checkUser = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->execute(array($username));
        if ($checkUser->fetch()) {
            $username = $username . '_' . mt_rand(100, 999);
        }

        try {
            $insertStmt->execute(array(
                $branchId, $username, $defaultPass,
                $r['real_name'], $r['role'], $r['is_engineer'],
                $r['phone'] ?: null, $r['email'] ?: null,
                $isActive, $isMobile
            ));
            $inserted++;
        } catch (Exception $e) {
            echo "<span style='color:red'>失敗 " . htmlspecialchars($r['real_name']) . ": " . htmlspecialchars($e->getMessage()) . "</span><br>";
            $skipped++;
        }
    }
}

echo "新增: {$inserted} | 更新: {$updated} | 跳過: {$skipped}<br><br>";
$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
echo "系統現有人數: {$totalUsers}<br><br>";
echo "<a href='/staff.php'>人員管理</a> | <a href='/import_cases.php'>匯入案件</a>";
