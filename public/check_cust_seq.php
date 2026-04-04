<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$stmt = $db->query("SELECT * FROM number_sequences WHERE module = 'customers'");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if ($r) {
    print_r($r);
} else {
    echo "customers 模組沒有 number_sequences 設定\n";
    // 建立
    $db->exec("INSERT INTO number_sequences (module, module_label, prefix, date_format, separator, seq_digits, last_reset_key, last_sequence) VALUES ('customers', '客戶編號', 'C', 'Ymd', '-', 3, '', 0)");
    echo "已建立 customers 編號規則: C-Ymd-001\n";
}
