<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// 1. 新增 receipts 欄位
$cols = array(
    'voucher_number'  => "ADD COLUMN `voucher_number` VARCHAR(50) DEFAULT NULL COMMENT '傳票號碼' AFTER `receipt_number`",
    'billing_number'  => "ADD COLUMN `billing_number` VARCHAR(50) DEFAULT NULL COMMENT '請款單號' AFTER `voucher_number`",
    'ragic_id'        => "ADD COLUMN `ragic_id` VARCHAR(20) DEFAULT NULL COMMENT 'Ragic record ID' AFTER `note`",
    'registrar'       => "ADD COLUMN `registrar` VARCHAR(50) DEFAULT NULL COMMENT '登記人' AFTER `ragic_id`",
);

foreach ($cols as $name => $sql) {
    try {
        $db->exec("ALTER TABLE `receipts` {$sql}");
        $results[] = "欄位 {$name} 已新增";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $results[] = "欄位 {$name} 已存在，跳過";
        } else {
            $results[] = "錯誤 ({$name}): " . $e->getMessage();
        }
    }
}

// 2. 新增自動編號設定（傳票號碼 + 請款單號）
// 收款單編號改成 Ragic 格式: S2-YYYYMMDD-NNN
$seqUpdates = array(
    array('receipts', '收款單', 'S2', 'Ymd', '-', 3),
    array('vouchers_ar', '傳票號碼(收)', 'AR2', 'Ymd', '-', 3),
);

foreach ($seqUpdates as $s) {
    try {
        $chk = $db->prepare("SELECT COUNT(*) FROM number_sequences WHERE module = ?");
        $chk->execute(array($s[0]));
        if ($chk->fetchColumn() > 0) {
            // 更新前綴和格式
            $db->prepare("UPDATE number_sequences SET prefix=?, date_format=?, `separator`=?, seq_digits=? WHERE module=?")
               ->execute(array($s[2], $s[3], $s[4], $s[5], $s[0]));
            $results[] = "自動編號 {$s[0]} 已更新: {$s[2]}-{$s[3]}-{$s[5]}位";
        } else {
            $db->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits) VALUES (?,?,?,?,?,?)")
               ->execute($s);
            $results[] = "自動編號 {$s[0]} 已新增: {$s[2]}-{$s[3]}-{$s[5]}位";
        }
    } catch (PDOException $e) {
        $results[] = "編號設定錯誤 ({$s[0]}): " . $e->getMessage();
    }
}

echo "<h2>Migration 061 - 收款單欄位擴充+編號設定</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul><p><a href='/receipts.php'>收款單</a> | <a href='/menu_settings.php'>選單管理</a></p>";
