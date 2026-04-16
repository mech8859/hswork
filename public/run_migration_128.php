<?php
/**
 * Migration 128: leaves 表補欄位
 * - 加 start_time / end_time（半天/小時假需要）
 * - 擴充 leave_type enum：加 day_off（補休）、menstrual（生理假）、bereavement（喪假）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') {
    die('需要 boss 權限');
}
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

echo "=== Migration 128: leaves 補欄位 ===\n\n";

// 1. 加 start_time / end_time
$cols = $db->query("SHOW COLUMNS FROM leaves")->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'Field');

if (!in_array('start_time', $names)) {
    $db->exec("ALTER TABLE leaves ADD COLUMN start_time TIME NULL AFTER start_date");
    echo "ADDED: start_time\n";
} else echo "EXISTS: start_time\n";

if (!in_array('end_time', $names)) {
    $db->exec("ALTER TABLE leaves ADD COLUMN end_time TIME NULL AFTER end_date");
    echo "ADDED: end_time\n";
} else echo "EXISTS: end_time\n";

// 2. 擴充 leave_type enum
$cur = $db->query("SHOW COLUMNS FROM leaves LIKE 'leave_type'")->fetch(PDO::FETCH_ASSOC);
echo "\n目前 leave_type 定義: " . $cur['Type'] . "\n";

$newEnum = "ENUM('annual','personal','sick','official','day_off','menstrual','bereavement','marriage','maternity','paternity','funeral','other')";
$db->exec("ALTER TABLE leaves MODIFY COLUMN leave_type $newEnum NOT NULL");
echo "MODIFIED leave_type → $newEnum\n";

// 驗證
$check = $db->query("SHOW COLUMNS FROM leaves")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== leaves 最終欄位 ===\n";
foreach ($check as $c) echo "  " . $c['Field'] . " " . $c['Type'] . "\n";

echo "\n完成\n";
