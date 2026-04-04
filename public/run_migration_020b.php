<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h3>Fix branches</h3>";

$nb = array(
    array('中區技術組', 'department', '禾順監視數位', 'TECH'),
    array('中區管理處', 'department', '禾順監視數位', 'MGT'),
    array('清水門市', 'store', '理創(政遠企業)', 'QS'),
    array('東區門市', 'store', '理創(政遠企業)', 'ED'),
);

foreach ($nb as $b) {
    try {
        $chk = $db->prepare("SELECT id FROM branches WHERE name = ?");
        $chk->execute(array($b[0]));
        if ($chk->fetch()) {
            echo "SKIP: {$b[0]} exists<br>";
        } else {
            $ins = $db->prepare("INSERT INTO branches (name, code, branch_type, company, is_active) VALUES (?, ?, ?, ?, 1)");
            $ins->execute(array($b[0], $b[3], $b[1], $b[2]));
            echo "OK: {$b[0]}<br>";
        }
    } catch (Exception $e) {
        echo "ERR: {$b[0]} - " . $e->getMessage() . "<br>";
    }
}

echo "<br>DONE<br>";
echo "<a href='/staff.php'>人員管理</a>";
