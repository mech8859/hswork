<?php
/**
 * 修正 case_type：
 * 1. ENUM → VARCHAR
 * 2. repair → old_repair
 * 3. 從 title 後綴解析正確案別
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

echo "<h2>修復案件案別 (case_type)</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .warn{color:orange;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;}</style>";

// Step 0: 修復前統計
$stmt = $db->query("SELECT case_type, COUNT(*) as cnt FROM cases GROUP BY case_type ORDER BY cnt DESC");
$before = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>修復前分布</h3><table><tr><th>case_type</th><th>筆數</th></tr>";
foreach ($before as $row) {
    echo "<tr><td>" . ($row['case_type'] ?: '(空)') . "</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

// Step 1: ENUM → VARCHAR（如果還沒改的話）
try {
    $db->exec("ALTER TABLE cases MODIFY COLUMN case_type VARCHAR(30) NOT NULL DEFAULT 'new_install' COMMENT '案件類型'");
    echo "<p class='ok'>Step 1: case_type 已改為 VARCHAR(30)</p>";
} catch (Exception $e) {
    echo "<p class='warn'>Step 1: " . $e->getMessage() . "</p>";
}

$totalFixed = 0;

// Step 2: 舊 ENUM 值對應
$oldMappings = array(
    'repair'     => 'old_repair',
    'inspection' => 'maintenance',
    'other'      => 'new_install',
);
foreach ($oldMappings as $oldVal => $newVal) {
    $stmt = $db->prepare("UPDATE cases SET case_type = ? WHERE case_type = ?");
    $stmt->execute(array($newVal, $oldVal));
    $affected = $stmt->rowCount();
    if ($affected > 0) {
        $totalFixed += $affected;
        echo "<p class='ok'>「{$oldVal}」→「{$newVal}」: 修正 {$affected} 筆</p>";
    }
}

// Step 3: 從 title 後綴解析案別（覆蓋 new_install）
$titleMappings = array(
    'addition'    => array('老客戶追加'),
    'old_repair'  => array('舊客戶維修案'),
    'new_repair'  => array('新客戶維修案'),
    'maintenance' => array('維護保養'),
);
foreach ($titleMappings as $typeKey => $keywords) {
    foreach ($keywords as $kw) {
        $stmt = $db->prepare("UPDATE cases SET case_type = ? WHERE (title LIKE ? OR title LIKE ?) AND case_type = 'new_install'");
        $stmt->execute(array($typeKey, '%-' . $kw, '%－' . $kw));
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $totalFixed += $affected;
            echo "<p class='ok'>title 含「{$kw}」→「{$typeKey}」: 修正 {$affected} 筆</p>";
        }
    }
}

// Step 4: 空值歸為 new_install
$stmt = $db->prepare("UPDATE cases SET case_type = 'new_install' WHERE case_type = '' OR case_type IS NULL");
$stmt->execute();
$affected = $stmt->rowCount();
if ($affected > 0) {
    $totalFixed += $affected;
    echo "<p class='ok'>空值 → new_install: 修正 {$affected} 筆</p>";
}

echo "<p><strong>總計修正 {$totalFixed} 筆</strong></p>";

// Step 5: 修復後統計
$stmt = $db->query("SELECT case_type, COUNT(*) as cnt FROM cases GROUP BY case_type ORDER BY cnt DESC");
$after = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeLabels = array(
    'new_install' => '新案',
    'addition' => '老客戶追加',
    'old_repair' => '舊客戶維修案',
    'new_repair' => '新客戶維修案',
    'maintenance' => '維護保養',
);

echo "<h3>修復後分布</h3><table><tr><th>case_type</th><th>中文</th><th>筆數</th></tr>";
foreach ($after as $row) {
    $ct = $row['case_type'] ?: '(空)';
    $label = isset($typeLabels[$ct]) ? $typeLabels[$ct] : '-';
    echo "<tr><td>{$ct}</td><td>{$label}</td><td>{$row['cnt']}</td></tr>";
}
echo "</table>";

echo "<p><a href='/cases.php'>返回案件管理</a></p>";
