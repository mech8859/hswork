<?php
/**
 * 修正案件 status：舊 ENUM 值 → 新進度值
 * 對應邏輯：優先看 sub_status，其次看舊 status
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

echo "<h2>修復案件進度 (status)</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .warn{color:orange;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;}</style>";

// Step 0: 修復前統計
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
$before = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>修復前 status 分布</h3><table><tr><th>status</th><th>筆數</th></tr>";
foreach ($before as $row) {
    echo "<tr><td>" . ($row['status'] ?: '(空)') . "</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// Step 1: 改 status 欄位為 VARCHAR（移除 ENUM 限制）
try {
    $db->exec("ALTER TABLE cases MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'tracking' COMMENT '進度狀態'");
    echo "<p class='ok'>Step 1: status 已改為 VARCHAR(30)</p>";
} catch (Exception $e) {
    echo "<p class='warn'>Step 1: " . $e->getMessage() . "</p>";
}

$totalFixed = 0;

// Step 2: 根據 sub_status 直接對應（最明確的）
$subStatusMap = array(
    // sub_status => 新 status
    '已完工結案'       => 'closed',
    '完工未收款'       => 'unpaid',
    '未完工'           => 'incomplete',
    '待安排派工查修'   => 'awaiting_dispatch',
    '客戶取消'         => 'customer_cancel',
    '客戶毀約'         => 'breach',
    '無效'             => 'lost',
    '已報價無意願'     => 'lost',
    '報價無下文'       => 'lost',
    '保養案件'         => 'maint_case',
    '待追蹤'           => 'tracking',
);

echo "<h3>Step 2: 根據 sub_status 修正</h3>";
foreach ($subStatusMap as $subStatus => $newStatus) {
    $stmt = $db->prepare("UPDATE cases SET status = ? WHERE sub_status = ? AND status IN ('completed','pending','in_progress','cancelled','ready')");
    $stmt->execute(array($newStatus, $subStatus));
    $affected = $stmt->rowCount();
    if ($affected > 0) {
        $totalFixed += $affected;
        echo "<p class='ok'>sub_status「{$subStatus}」→ status「{$newStatus}」: {$affected} 筆</p>";
    }
}

// Step 3: 根據 舊status + sub_status 組合對應
echo "<h3>Step 3: 根據舊 status + sub_status 修正剩餘</h3>";

// completed + 已成交/跨月成交/現簽/電話報價成交 → closed
$stmt = $db->prepare("UPDATE cases SET status = 'closed' WHERE status = 'completed' AND sub_status IN ('已成交','跨月成交','現簽','電話報價成交')");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>completed + 成交類 → closed: {$affected} 筆</p>";
}

// completed + 其他剩餘 → closed
$stmt = $db->prepare("UPDATE cases SET status = 'closed' WHERE status = 'completed'");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>completed 剩餘 → closed: {$affected} 筆</p>";
}

// in_progress + 已成交/跨月成交/現簽 → incomplete（進行中但已成交=施工中）
$stmt = $db->prepare("UPDATE cases SET status = 'incomplete' WHERE status = 'in_progress' AND sub_status IN ('已成交','跨月成交','現簽','電話報價成交')");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>in_progress + 成交類 → incomplete: {$affected} 筆</p>";
}

// in_progress + 其他剩餘 → incomplete
$stmt = $db->prepare("UPDATE cases SET status = 'incomplete' WHERE status = 'in_progress'");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>in_progress 剩餘 → incomplete: {$affected} 筆</p>";
}

// cancelled → lost
$stmt = $db->prepare("UPDATE cases SET status = 'lost' WHERE status = 'cancelled'");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>cancelled 剩餘 → lost: {$affected} 筆</p>";
}

// pending + 已成交類 → tracking（待處理但已成交=等安排）
$stmt = $db->prepare("UPDATE cases SET status = 'tracking' WHERE status = 'pending' AND sub_status IN ('已成交','跨月成交','現簽','電話報價成交')");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>pending + 成交類 → tracking: {$affected} 筆</p>";
}

// pending + 其他 → tracking
$stmt = $db->prepare("UPDATE cases SET status = 'tracking' WHERE status = 'pending'");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>pending 剩餘 → tracking: {$affected} 筆</p>";
}

// ready → tracking
$stmt = $db->prepare("UPDATE cases SET status = 'tracking' WHERE status = 'ready'");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>ready → tracking: {$affected} 筆</p>";
}

echo "<p><strong>總計修正 {$totalFixed} 筆</strong></p>";

// Step 4: 修復後統計
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
$after = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusLabels = array(
    'tracking'          => '待追蹤',
    'incomplete'        => '未完工',
    'unpaid'            => '完工未收款',
    'completed_pending' => '已完工待簽核',
    'closed'            => '已完工結案',
    'lost'              => '未成交',
    'maint_case'        => '保養案件',
    'breach'            => '毀約',
    'scheduled'         => '已排工/已排行事曆',
    'needs_reschedule'  => '已進場/需再安排',
    'awaiting_dispatch' => '待安排派工查修',
    'customer_cancel'   => '客戶取消',
);

echo "<h3>修復後 status 分布</h3><table><tr><th>status</th><th>中文</th><th>筆數</th></tr>";
foreach ($after as $row) {
    $s = $row['status'] ?: '(空)';
    $label = isset($statusLabels[$s]) ? $statusLabels[$s] : '<span style="color:red">未知</span>';
    echo "<tr><td>{$s}</td><td>{$label}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// 交叉驗證：status vs sub_status
echo "<h3>交叉驗證（status × sub_status 前20筆）</h3>";
$stmt = $db->query("SELECT status, sub_status, COUNT(*) as cnt FROM cases GROUP BY status, sub_status ORDER BY cnt DESC LIMIT 20");
$cross = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>status</th><th>sub_status</th><th>筆數</th></tr>";
foreach ($cross as $row) {
    $s = isset($statusLabels[$row['status']]) ? $statusLabels[$row['status']] : $row['status'];
    echo "<tr><td>{$s}</td><td>" . ($row['sub_status'] ?: '(空)') . "</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

echo "<p><a href='/cases.php'>返回案件管理</a></p>";
