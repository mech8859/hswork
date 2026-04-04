<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
echo "=== 匯入掃描檔 DB 記錄 ===\n";

$db = Database::getInstance();
$sqlFile = __DIR__ . '/../database/customer_scan_import.sql';
if (!file_exists($sqlFile)) { die("SQL 檔不存在\n"); }

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(";\n", $sql)));
$success = 0; $errors = 0; $skip = 0;
foreach ($statements as $stmt) {
    $stmt = rtrim(trim($stmt), ';');
    if (empty($stmt) || strpos($stmt, '--') === 0 || strpos($stmt, 'SET ') === 0) continue;
    try {
        $affected = $db->exec($stmt);
        if ($affected > 0) $success++;
        else $skip++;
    } catch (PDOException $e) {
        $errors++;
        if ($errors <= 5) echo "[ERR] " . mb_substr($stmt, 0, 100) . "\n  " . $e->getMessage() . "\n";
    }
}
echo "完成: 新增 {$success}, 跳過 {$skip}, 錯誤 {$errors}\n";
$total = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
echo "customer_files 總筆數: {$total}\n";
