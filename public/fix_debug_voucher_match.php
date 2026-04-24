<?php
/**
 * 診斷：傳票綁定狀態 + 精準/模糊匹配邏輯驗證
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

echo "<h3>傳票綁定狀態 (4/1)</h3>";

// 列出 4/1 那天所有傳票
$sql = "SELECT je.id, je.voucher_number, je.voucher_date, je.status, je.description, je.total_debit,
               je.source_module, je.source_id
        FROM journal_entries je
        WHERE je.voucher_date = '2026-04-01'
        ORDER BY je.id DESC";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>id</th><th>voucher_number</th><th>status</th><th>description</th><th>total_debit</th><th>source_module</th><th>source_id</th><th>動作</th></tr></thead><tbody>";
foreach ($rows as $r) {
    $color = !empty($r['source_module']) && !empty($r['source_id']) ? '#2e7d32' : '#666';
    echo "<tr style='color:{$color}'>"
       . "<td>{$r['id']}</td>"
       . "<td><b>[" . htmlspecialchars($r['voucher_number']) . "]</b></td>"
       . "<td>" . htmlspecialchars($r['status']) . "</td>"
       . "<td>" . htmlspecialchars($r['description']) . "</td>"
       . "<td style='text-align:right'>" . number_format($r['total_debit']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['source_module']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['source_id']) . "</td>"
       . "<td>";
    if (!empty($r['source_module']) || !empty($r['source_id'])) {
        echo "<a href='?clear={$r['id']}' onclick='return confirm(\"清除 {$r['voucher_number']} 的綁定？\")' style='color:#c62828'>清除綁定</a>";
    }
    echo "</td></tr>";
}
echo "</tbody></table>";

// 清除綁定
if (isset($_GET['clear'])) {
    $clearId = (int)$_GET['clear'];
    $db->prepare("UPDATE journal_entries SET source_module = NULL, source_id = NULL WHERE id = ?")->execute(array($clearId));
    echo "<p style='color:#2e7d32'>✓ 已清除 id={$clearId} 的 source_module/source_id</p>";
    echo "<p><a href='?'>重新載入</a></p>";
}

// 列出 4/1 的零用金
echo "<h4>零用金 4/1 所有紀錄</h4>";
$pc = $db->query("SELECT id, entry_number, upload_number, expense_date, description, income_amount, expense_amount FROM petty_cash WHERE expense_date = '2026-04-01' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>id</th><th>entry_number</th><th>upload_number</th><th>description</th><th>收入</th><th>支出</th></tr></thead><tbody>";
foreach ($pc as $r) {
    echo "<tr>"
       . "<td>{$r['id']}</td>"
       . "<td><b>[" . htmlspecialchars((string)$r['entry_number']) . "]</b></td>"
       . "<td>" . htmlspecialchars((string)$r['upload_number']) . "</td>"
       . "<td>" . htmlspecialchars($r['description']) . "</td>"
       . "<td style='text-align:right;color:#2e7d32'>" . number_format($r['income_amount']) . "</td>"
       . "<td style='text-align:right;color:#c62828'>" . number_format($r['expense_amount']) . "</td>"
       . "</tr>";
}
echo "</tbody></table>";

// 列出 4/1 的備用金
echo "<h4>備用金 4/1 所有紀錄</h4>";
$rf = $db->query("SELECT id, entry_number, upload_number, expense_date, description, income_amount, expense_amount FROM reserve_fund WHERE expense_date = '2026-04-01' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>id</th><th>entry_number</th><th>upload_number</th><th>description</th><th>收入</th><th>支出</th></tr></thead><tbody>";
foreach ($rf as $r) {
    echo "<tr>"
       . "<td>{$r['id']}</td>"
       . "<td><b>[" . htmlspecialchars((string)$r['entry_number']) . "]</b></td>"
       . "<td>" . htmlspecialchars((string)$r['upload_number']) . "</td>"
       . "<td>" . htmlspecialchars($r['description']) . "</td>"
       . "<td style='text-align:right;color:#2e7d32'>" . number_format($r['income_amount']) . "</td>"
       . "<td style='text-align:right;color:#c62828'>" . number_format($r['expense_amount']) . "</td>"
       . "</tr>";
}
echo "</tbody></table>";

echo "<hr><p>📖 判讀：</p><ul>";
echo "<li>若某傳票的 <b>source_module</b> 已填（例如 petty_cash）、<b>source_id</b> 對應到某零用金 id → 那筆零用金會顯示 精準匹配</li>";
echo "<li>若 source_module 為空 → 所有同金額同日期的零用金都可能顯示 模糊匹配（指向同一張傳票）</li>";
echo "<li>若 source_module 填了但 source_id 指向錯的來源 → 那張傳票會被排除在模糊匹配外，但 _findPreciseMatch 用錯的 id 也找不到，所有來源都顯示 未建傳票</li>";
echo "<li>要手動清除綁定：點「清除綁定」</li>";
echo "</ul>";
