<?php
/**
 * Migration 121: 銀行明細 - 舊欄位資料複製到新欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$updates = array(
    array('upload_no', 'upload_number'),
    array('remittance_code', 'bank_code'),
    array('counterparty_account', 'counter_account'),
    array('memo', 'remark'),
);

$total = 0;
foreach ($updates as $pair) {
    $newCol = $pair[0];
    $oldCol = $pair[1];
    $stmt = $db->prepare("
        UPDATE bank_transactions
        SET {$newCol} = {$oldCol}
        WHERE ({$newCol} IS NULL OR {$newCol} = '')
          AND {$oldCol} IS NOT NULL AND {$oldCol} != ''
    ");
    $stmt->execute();
    $cnt = $stmt->rowCount();
    echo "{$oldCol} -> {$newCol}: {$cnt} 筆更新\n";
    $total += $cnt;
}

echo "\n共更新 {$total} 筆\nMigration 121 done.\n";
