<?php
/**
 * Migration 139：2026-02-28 (含) 以前未設聯式的進項發票，全部設定為 '21'（進項三聯式、電子計算機統一發票）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$cutoffDate = '2026-02-28';

try {
    // 先統計目標筆數
    $countStmt = $db->prepare("SELECT COUNT(*) FROM purchase_invoices
        WHERE invoice_date <= ?
          AND (invoice_format IS NULL OR TRIM(invoice_format) = '')");
    $countStmt->execute(array($cutoffDate));
    $beforeCount = (int)$countStmt->fetchColumn();
    echo "Target rows: {$beforeCount} (invoice_date <= {$cutoffDate}, invoice_format empty)\n";

    if ($beforeCount === 0) {
        echo "No rows to update.\n";
        echo "Migration 139 done.\n";
        exit;
    }

    // 執行更新
    $updateStmt = $db->prepare("UPDATE purchase_invoices
        SET invoice_format = '21', updated_at = NOW()
        WHERE invoice_date <= ?
          AND (invoice_format IS NULL OR TRIM(invoice_format) = '')");
    $updateStmt->execute(array($cutoffDate));
    $affected = $updateStmt->rowCount();
    echo "OK: updated {$affected} purchase invoices to invoice_format='21'\n";

    // 後置統計：剩下未設聯式的進項（2026-03-01 之後不動）
    $remainStmt = $db->prepare("SELECT COUNT(*) FROM purchase_invoices
        WHERE invoice_format IS NULL OR TRIM(invoice_format) = ''");
    $remainStmt->execute();
    $remain = (int)$remainStmt->fetchColumn();
    echo "Remaining purchase invoices without invoice_format (all dates): {$remain}\n";

    AuditLog::log('purchase_invoices', 'migration', 0, "Migration 139: 把 {$cutoffDate} 之前 {$affected} 筆進項發票聯式設為 21");

    echo "Migration 139 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
