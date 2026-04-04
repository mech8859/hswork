<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (($_GET['token'] ?? '') !== 'hswork2026fix') { die('token required'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$stmt = $db->query("SELECT id, username, employee_no, real_name FROM users WHERE employee_no IS NOT NULL AND employee_no != '' ORDER BY employee_no");
$updated = 0;
$skipped = 0;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $newUsername = 'hs' . $r['employee_no'];
    if ($r['username'] === $newUsername) {
        $skipped++;
        continue;
    }
    $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $chk->execute(array($newUsername, $r['id']));
    if ($chk->fetch()) {
        echo "SKIP: {$r['real_name']} ({$r['employee_no']}) → {$newUsername} 已存在\n";
        $skipped++;
        continue;
    }
    $db->prepare("UPDATE users SET username = ? WHERE id = ?")->execute(array($newUsername, $r['id']));
    echo "OK: {$r['real_name']} | {$r['username']} → {$newUsername}\n";
    $updated++;
}
echo "\n修改: {$updated} 位, 跳過: {$skipped} 位\n";
