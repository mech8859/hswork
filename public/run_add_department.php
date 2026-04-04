<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 1. 加 department 欄位到 users 表
$cols = $db->query("SHOW COLUMNS FROM users LIKE 'department'")->fetch();
if ($cols) {
    echo "[已存在] users.department 欄位\n";
} else {
    echo "[新增] users.department 欄位\n";
    if ($execute) {
        $db->exec("ALTER TABLE users ADD COLUMN department VARCHAR(50) DEFAULT NULL COMMENT '部門' AFTER employee_id");
        echo "  → 完成\n";
    }
}

// 2. 加部門選項到 dropdown_options
$departments = array(
    '潭子分公司工程部',
    '潭子分公司業務部',
    '潭子分公司行政部',
    '潭子分公司管理部',
    '員林分公司工程部',
    '員林分公司行政部',
    '員林分公司業務部',
    '員林分公司管理部',
    '清水分公司工程部',
    '清水分公司行政部',
    '清水分公司管理部',
    '東區電子鎖門市部',
    '東區電子鎖技師',
    '清水電子鎖門市部',
    '中區專案部工程部',
    '中區專案部行政部',
    '中區專案部業務部',
    '中區專案部管理部',
    '中區管理處行政部',
    '中區管理處技術部',
    '中區管理處管理部',
);

echo "\n部門選項 (" . count($departments) . " 個):\n";
foreach ($departments as $i => $dept) {
    $chk = $db->prepare("SELECT id FROM dropdown_options WHERE category = 'department' AND label = ?");
    $chk->execute(array($dept));
    if ($chk->fetch()) {
        echo "  [已存在] {$dept}\n";
    } else {
        echo "  [新增] {$dept}\n";
        if ($execute) {
            $db->prepare("INSERT INTO dropdown_options (category, label, sort_order, is_active) VALUES ('department', ?, ?, 1)")
               ->execute(array($dept, $i + 1));
        }
    }
}

echo "\n完成\n";
