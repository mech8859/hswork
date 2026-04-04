<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h2>清理孤立立帳記錄</h2>";

// 找出 journal_entry_id 不存在或傳票已刪除的 offset_ledger
$stmt = $db->query("
    SELECT ol.id, ol.voucher_number, ol.original_amount, ol.remaining_amount, ol.status,
           je.id as je_id, je.status as je_status
    FROM offset_ledger ol
    LEFT JOIN journal_entries je ON ol.journal_entry_id = je.id
    WHERE je.id IS NULL OR je.status = 'draft'
");
$orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>找到孤立記錄: " . count($orphans) . " 筆</p>";

if (!empty($orphans)) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>傳票號碼</th><th>原始金額</th><th>狀態</th><th>原因</th></tr>";
    foreach ($orphans as $o) {
        $reason = $o['je_id'] ? '傳票為草稿(已取消過帳)' : '傳票已刪除';
        echo "<tr><td>{$o['id']}</td><td>{$o['voucher_number']}</td><td>" . number_format($o['original_amount']) . "</td><td>{$o['status']}</td><td>{$reason}</td></tr>";
    }
    echo "</table>";

    // 刪除孤立記錄
    $ids = array_column($orphans, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("DELETE FROM offset_details WHERE ledger_id IN ({$placeholders})")->execute($ids);
    $db->prepare("DELETE FROM offset_ledger WHERE id IN ({$placeholders})")->execute($ids);
    echo "<p style='color:green'>✓ 已清理 " . count($orphans) . " 筆孤立記錄</p>";
}

// 驗證剩餘
$remaining = $db->query("SELECT COUNT(*) FROM offset_ledger")->fetchColumn();
echo "<p>清理後 offset_ledger 剩餘: {$remaining} 筆</p>";
echo "<a href='/accounting.php?action=journals'>返回傳票管理</a>";
