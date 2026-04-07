<?php
/**
 * 加入「拋轉待確認」收款單狀態到 dropdown_options
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

// 確認是否已存在
$stmt = $db->prepare("SELECT id FROM dropdown_options WHERE category = 'receipt_status' AND option_key = '拋轉待確認'");
$stmt->execute();
if ($stmt->fetch()) {
    echo "已存在，跳過\n";
} else {
    // 取得目前最大 sort_order
    $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM dropdown_options WHERE category = 'receipt_status'")->fetchColumn();

    $stmt = $db->prepare("INSERT INTO dropdown_options (category, option_key, label, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute(array('receipt_status', '拋轉待確認', '拋轉待確認', $maxSort + 1));
    echo "已新增「拋轉待確認」(sort_order: " . ($maxSort + 1) . ")\n";
}

echo "\n=== 目前所有收款單狀態 ===\n";
$stmt = $db->query("SELECT option_key, label, sort_order FROM dropdown_options WHERE category = 'receipt_status' AND is_active = 1 ORDER BY sort_order, label");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  [{$row['sort_order']}] {$row['option_key']} => {$row['label']}\n";
}
echo "\nDone.\n";
