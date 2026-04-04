<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>Migration 029: 案件帳款交易表</h2>';
try {
    $sql = file_get_contents(__DIR__ . '/../database/migration_029_case_payments.sql');
    $db->exec($sql);
    echo '<p style="color:green">✓ case_payments 表已建立</p>';
    
    $cols = $db->query("SHOW COLUMNS FROM case_payments")->fetchAll(PDO::FETCH_ASSOC);
    echo '<table border="1" cellpadding="4"><tr><th>欄位</th><th>類型</th><th>說明</th></tr>';
    foreach ($cols as $c) {
        echo '<tr><td>'.$c['Field'].'</td><td>'.$c['Type'].'</td><td>'.($c['Comment'] ?: '-').'</td></tr>';
    }
    echo '</table>';
} catch (Exception $e) {
    echo '<p style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '<br><a href="/cases.php">返回案件管理</a>';
