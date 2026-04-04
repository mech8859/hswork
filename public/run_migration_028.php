<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
echo "<h2>Migration 028: 自動編號序列表</h2>";

// Step 1: 建表
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS number_sequences (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          module VARCHAR(30) NOT NULL UNIQUE,
          module_label VARCHAR(50) NOT NULL,
          prefix VARCHAR(20) NOT NULL DEFAULT '',
          date_format VARCHAR(20) NOT NULL DEFAULT 'Ym',
          `separator` VARCHAR(5) NOT NULL DEFAULT '-',
          seq_digits INT NOT NULL DEFAULT 3,
          last_reset_key VARCHAR(20) DEFAULT NULL,
          last_sequence INT NOT NULL DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ 資料表建立成功</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ 建表失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Step 2: 檢查是否已有資料
$count = (int)$db->query("SELECT COUNT(*) FROM number_sequences")->fetchColumn();
if ($count > 0) {
    echo "<p style='color:gray'>已有 {$count} 筆資料，跳過新增</p>";
} else {
    // 逐筆新增
    $rows = array(
        array('cases', '案件', '', 'Ym', '-', 3),
        array('quotations', '報價單', 'Q', 'Ymd', '-', 3),
        array('receivables', '應收帳款', 'AR', 'Y', '-', 4),
        array('receipts', '收款單', 'RC', 'Y', '-', 4),
        array('payables', '應付帳款', 'AP', 'Y', '-', 4),
        array('payments', '付款單', 'PM', 'Y', '-', 4),
        array('purchase_orders', '採購單', 'PUR', 'Ymd', '-', 3),
        array('requisitions', '請購單', 'PR', 'Ymd', '-', 3),
        array('warehouse_transfers', '倉庫調撥', 'ST', 'Ymd', '-', 3),
    );
    $stmt = $db->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($rows as $r) {
        try {
            $stmt->execute($r);
            echo "<p style='color:green'>✓ {$r[1]} ({$r[0]})</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ {$r[1]}: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// Step 3: 顯示結果
echo "<h3>目前設定</h3>";
$rows = $db->query("SELECT * FROM number_sequences ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'><tr><th>模組</th><th>名稱</th><th>前綴</th><th>日期格式</th><th>分隔符</th><th>序號位數</th><th>預覽</th></tr>";
foreach ($rows as $r) {
    $parts = array();
    if ($r['prefix'] !== '') $parts[] = $r['prefix'];
    if (!empty($r['date_format'])) $parts[] = date($r['date_format']);
    $parts[] = str_pad(1, (int)$r['seq_digits'], '0', STR_PAD_LEFT);
    $preview = implode($r['separator'], $parts);
    echo "<tr><td>{$r['module']}</td><td>{$r['module_label']}</td><td>{$r['prefix']}</td><td>{$r['date_format']}</td><td>{$r['separator']}</td><td>{$r['seq_digits']}</td><td><code>{$preview}</code></td></tr>";
}
echo "</table>";
echo "<hr><p><a href='/dropdown_options.php?tab=numbering'>自動編號設定</a> | <a href='/cases.php'>案件管理</a></p>";
