<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$sql = file_get_contents(__DIR__ . '/../database/customer_scan_import.sql');
$statements = array_filter(array_map('trim', explode(";\n", $sql)));
$ok = 0; $err = 0;
foreach ($statements as $stmt) {
    $stmt = rtrim(trim($stmt), ';');
    if (empty($stmt) || strpos($stmt, '--') === 0 || strpos($stmt, 'SET') === 0) continue;
    try {
        $db->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        echo "ERR: " . $e->getMessage() . "\n";
        $err++;
    }
}
$count = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
echo "INSERT 成功: {$ok}, 錯誤: {$err}\n";
echo "customer_files 總數: {$count}\n";

// 抽查
echo "\n=== 抽查 ===\n";
$stmt = $db->query("SELECT cf.id, cf.customer_id, cf.file_name, cf.file_path, c.customer_no, c.name, c.legacy_customer_no FROM customer_files cf JOIN customers c ON cf.customer_id = c.id LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['file_name']} → {$r['customer_no']} | {$r['legacy_customer_no']} | {$r['name']}\n";
}
