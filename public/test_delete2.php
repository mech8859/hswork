<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';
header('Content-Type: text/plain; charset=utf-8');

$model = new CaseModel();
$db = Database::getInstance();

// 找最新的測試案件
$case = $db->query("SELECT id, case_number, title FROM cases ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "最新案件: ID={$case['id']} {$case['case_number']} {$case['title']}\n\n";

// 測試每個關聯表
$tables = array('case_attachments','case_contacts','case_readiness','case_site_conditions','case_required_skills','schedule_records');
foreach ($tables as $t) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$t} WHERE case_id = ?");
        $stmt->execute(array($case['id']));
        echo "OK: {$t} = " . $stmt->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo "ERR: {$t} = " . $e->getMessage() . "\n";
    }
}

// 也測 case_payments, case_work_logs
foreach (array('case_payments','case_work_logs') as $t) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$t} WHERE case_id = ?");
        $stmt->execute(array($case['id']));
        echo "OK: {$t} = " . $stmt->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo "ERR: {$t} = " . $e->getMessage() . "\n";
    }
}
