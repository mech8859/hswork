<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// 需要加 registrar 的表
$tables = array(
    'cases'        => '案件管理',
    'receivables'  => '應收帳款',
    'payables'     => '應付帳款',
    'payments_out' => '付款單',
    'quotations'   => '報價單',
    'customers'    => '客戶管理',
    'repairs'      => '維修單',
);

foreach ($tables as $table => $label) {
    try {
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `registrar` VARCHAR(50) DEFAULT NULL COMMENT '登記人'");
        $results[] = "{$label} ({$table}): registrar 欄位已新增";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $results[] = "{$label} ({$table}): registrar 已存在，跳過";
        } else {
            $results[] = "{$label} ({$table}): 錯誤 - " . $e->getMessage();
        }
    }
}

// 也加 ragic_id 欄位（方便之後匯入比對）
$ragicTables = array('cases', 'receivables', 'payables', 'payments_out', 'quotations', 'bank_transactions', 'petty_cash', 'reserve_fund', 'cash_details');
foreach ($ragicTables as $table) {
    try {
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `ragic_id` VARCHAR(20) DEFAULT NULL COMMENT 'Ragic record ID'");
        $results[] = "{$table}: ragic_id 已新增";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            // skip
        } else {
            $results[] = "{$table} ragic_id 錯誤: " . $e->getMessage();
        }
    }
}

// 回填現有資料的 registrar（用 created_by 查 users.real_name）
$updated = 0;
foreach (array('cases', 'receipts', 'receivables', 'payables', 'payments_out', 'quotations', 'customers', 'repairs') as $table) {
    try {
        $affected = $db->exec("UPDATE `{$table}` t JOIN users u ON t.created_by = u.id SET t.registrar = u.real_name WHERE t.registrar IS NULL AND t.created_by IS NOT NULL");
        $updated += $affected;
    } catch (PDOException $e) {}
}
$results[] = "已回填 {$updated} 筆 registrar（從 created_by 對應）";

echo "<h2>Migration 063 - 財務表加登記人+ragic_id</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul>";
