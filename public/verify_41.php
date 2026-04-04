<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(60);
$db = Database::getInstance();
$db->exec("SET NAMES utf8mb4");

// 只查這 41 筆
$ids = array('A-001832','A-001833','A-002197','A-002465','A-003571','A-004447','A-004448','A-004465','A-004927','A-004928','A-004974','A-005456','A-006645','A-006995','A-007086','A-007492','A-007529','A-007683','A-007985','A-008157','A-009044','A-009436','A-009874','A-010370','A-010512','A-010811','A-010887','A-011212','A-011219','A-011895','A-012137','A-012243','A-012553','A-013722','A-014632','A-015196','A-015198','A-015946','A-016110','A-016375','A-016569');

$ok = 0; $bad = 0;
foreach ($ids as $cno) {
    $stmt = $db->prepare("SELECT name FROM customers WHERE customer_no = ?");
    $stmt->execute(array($cno));
    $name = $stmt->fetchColumn();
    $has_q = (strpos($name, '?') !== false);
    if ($has_q) {
        $bad++;
        echo "BAD: {$cno} = {$name}\n";
    } else {
        $ok++;
    }
}
echo "\nOK: {$ok}, BAD(still has ?): {$bad}\n";
