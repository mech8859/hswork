<?php
/**
 * 一次性 migration：cases 新增 is_pure_sale 欄位（單純買賣）
 *
 * 用法：/run_add_is_pure_sale.php           （預覽）
 *       /run_add_is_pure_sale.php?execute=1 （實際執行）
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

$col = $db->query("SHOW COLUMNS FROM cases LIKE 'is_pure_sale'")->fetch();
if ($col) {
    echo "[已存在] cases.is_pure_sale\n";
} else {
    echo "[新增] cases.is_pure_sale TINYINT(1) NOT NULL DEFAULT 0 COMMENT '單純買賣（隱藏內部成本/預計線材）'\n";
    if ($execute) {
        $db->exec("ALTER TABLE cases ADD COLUMN is_pure_sale TINYINT(1) NOT NULL DEFAULT 0 COMMENT '單純買賣（隱藏內部成本/預計線材）' AFTER no_equipment");
        echo "  → 完成\n";
    } else {
        echo "  (預覽，未實際執行)\n";
    }
}

echo "\n--- 驗證 ---\n";
$cols = $db->query("SHOW COLUMNS FROM cases LIKE '%pure_sale%'")->fetchAll(PDO::FETCH_ASSOC);
if ($cols) {
    foreach ($cols as $c) {
        echo "✓ {$c['Field']}  {$c['Type']}  default={$c['Default']}\n";
    }
} else {
    echo "(欄位尚未建立)\n";
}
