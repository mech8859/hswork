<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$total = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1 AND YEAR(completion_date) = 2026")->fetchColumn();
echo "2026完工: {$total}\n";

$excel = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1 AND YEAR(completion_date) = 2026 AND import_source = 'excel_import'")->fetchColumn();
echo "2026完工+excel_import: {$excel}\n";

$other = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1 AND YEAR(completion_date) = 2026 AND (import_source != 'excel_import' OR import_source IS NULL)")->fetchColumn();
echo "2026完工+非excel: {$other}\n";

$caseCreate = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1 AND YEAR(completion_date) = 2026 AND import_source = 'case_create'")->fetchColumn();
echo "2026完工+case_create: {$caseCreate}\n";

// 看 import_source 分布
$stmt = $db->query("SELECT COALESCE(import_source,'NULL') as src, COUNT(*) as cnt FROM customers WHERE is_active = 1 AND YEAR(completion_date) = 2026 GROUP BY import_source");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$r['src']}: {$r['cnt']}\n";
}
