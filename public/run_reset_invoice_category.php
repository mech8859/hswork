<?php
/**
 * 重設收款單帳款類別選項（dropdown_options）
 * 統一為：訂金/第一期款/第二期款/第三期款/尾款/保留款/全款/退款
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

// 清空既有選項
$db->prepare("DELETE FROM dropdown_options WHERE category = 'invoice_category'")->execute();
echo "已清空舊選項\n\n";

// 寫入新選項
$options = array(
    '訂金' => 1,
    '第一期款' => 2,
    '第二期款' => 3,
    '第三期款' => 4,
    '尾款' => 5,
    '保留款' => 6,
    '全款' => 7,
    '退款' => 8,
);

$stmt = $db->prepare("INSERT INTO dropdown_options (category, option_key, label, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
foreach ($options as $key => $sort) {
    $stmt->execute(array('invoice_category', $key, $key, $sort));
    echo "  + $key (sort: $sort)\n";
}

echo "\n=== 確認 ===\n";
$rows = $db->query("SELECT option_key, label, sort_order FROM dropdown_options WHERE category = 'invoice_category' AND is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  [{$r['sort_order']}] {$r['option_key']}\n";
echo "\nDone.\n";
