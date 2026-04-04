<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// 要更新/新增的編號設定
$seqList = array(
    // [module, label, prefix, date_format, separator, digits]
    array('receipts',        '收款單編號',      'S2',  'Ymd', '-', 3),
    array('receivables',     '請款單號',        'S1',  'Ymd', '-', 3),
    array('payables',        '付款單號',        'P1',  'Ymd', '-', 3),
    array('payments',        '付款單編號',      'P2',  'Ymd', '-', 3),
    array('vouchers_ar',     '傳票號碼(收)',    'AR2', 'Ymd', '-', 3),
    array('vouchers_billing','傳票號碼(請款)',  'AR1', 'Ymd', '-', 3),
    array('bank_statements', '銀行明細上傳編號','CC',  'Y',   '-', 6),
);

foreach ($seqList as $s) {
    $chk = $db->prepare("SELECT COUNT(*) FROM number_sequences WHERE module = ?");
    $chk->execute(array($s[0]));
    if ($chk->fetchColumn() > 0) {
        $db->prepare("UPDATE number_sequences SET module_label=?, prefix=?, date_format=?, `separator`=?, seq_digits=? WHERE module=?")
           ->execute(array($s[1], $s[2], $s[3], $s[4], $s[5], $s[0]));
        $results[] = "[更新] {$s[1]}: {$s[2]}-{$s[3]}-{$s[5]}位";
    } else {
        $db->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits) VALUES (?,?,?,?,?,?)")
           ->execute($s);
        $results[] = "[新增] {$s[1]}: {$s[2]}-{$s[3]}-{$s[5]}位";
    }
}

echo "<h2>編號格式更新（對應 Ragic）</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul><p><a href='/dropdown_options.php?tab=numbering'>← 查看自動編號設定</a></p>";
