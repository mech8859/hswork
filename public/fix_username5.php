<?php
if (!isset($_GET['token']) || $_GET['token'] !== 'hswork2026fix') { die('token required'); }
header('Content-Type: text/plain; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->query("SELECT id, username, employee_no, real_name FROM users WHERE employee_no IS NOT NULL AND employee_no != '' ORDER BY employee_no");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $skipped = 0;
    foreach ($rows as $r) {
        $newUser = 'hs' . $r['employee_no'];
        if ($r['username'] === $newUser) { $skipped++; continue; }
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk->execute(array($newUser, $r['id']));
        if ($chk->fetch()) { echo "SKIP: " . $r['real_name'] . " -> " . $newUser . " exists\n"; $skipped++; continue; }
        $db->prepare("UPDATE users SET username = ? WHERE id = ?")->execute(array($newUser, $r['id']));
        echo "OK: " . $r['real_name'] . " | " . $r['username'] . " -> " . $newUser . "\n";
        $updated++;
    }
    echo "\nUpdated: " . $updated . ", Skipped: " . $skipped . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
