<?php
/**
 * 一次性：把所有「核算專案」的會計科目改為「不核算專案」
 * 跑完請刪除此檔
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $cnt = (int)$db->query("SELECT COUNT(*) FROM chart_of_accounts WHERE project_calc = '核算專案'")->fetchColumn();
    echo "目前「核算專案」科目數：{$cnt}\n";

    $upd = $db->prepare("UPDATE chart_of_accounts SET project_calc = '不核算專案' WHERE project_calc = '核算專案'");
    $upd->execute();
    $affected = $upd->rowCount();
    echo "已更新 {$affected} 筆 → 全部改為「不核算專案」\n";

    AuditLog::log('chart_of_accounts', 'bulk_update', 0, '專案核算批次設為不核算專案', array('affected' => $affected));
    echo "Done. 請刪除此檔（fix_chart_disable_project_calc.php）\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
