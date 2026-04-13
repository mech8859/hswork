<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

echo "=== users 相關欄位 ===\n";
$cols = $db->query("SHOW COLUMNS FROM users WHERE Field IN ('employee_id','employee_no','staff_no','is_active','status','is_sales','role')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo "{$c['Field']}: {$c['Type']}\n";

echo "\n=== 測試帳號（名稱含測試）===\n";
$tests = $db->query("SELECT id, real_name, role, is_sales, is_active, employee_id FROM users WHERE real_name LIKE '%測試%' OR real_name LIKE '%系統管理%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tests as $t) echo "id:{$t['id']} {$t['real_name']} role:{$t['role']} is_sales:{$t['is_sales']} active:{$t['is_active']} emp_id:" . ($t['employee_id'] ?: 'NULL') . "\n";

echo "\n=== 目前清單中不該出現的 ===\n";
$bad = $db->query("SELECT id, real_name, role, is_sales, is_active, employee_id FROM users WHERE (role IN ('sales','sales_manager','sales_assistant','boss') OR is_sales = 1) AND is_active = 1 AND (employee_id IS NULL OR employee_id = '') ORDER BY real_name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($bad as $b) echo "id:{$b['id']} {$b['real_name']} role:{$b['role']} is_sales:{$b['is_sales']} emp_id:" . ($b['employee_id'] ?: 'NULL') . "\n";
