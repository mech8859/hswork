<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sql = file_get_contents(__DIR__ . '/../database/migration_051_enlarge_fields.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt) || strpos($stmt, '--') === 0) continue;
    try {
        $db->exec($stmt);
        echo "OK: " . substr($stmt, 0, 80) . "\n";
    } catch (PDOException $e) {
        echo "ERR: " . $e->getMessage() . "\n  " . substr($stmt, 0, 80) . "\n";
    }
}
echo "\n完成！";
