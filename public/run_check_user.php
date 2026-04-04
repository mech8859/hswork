<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$stmt = $db->query("SELECT id, username, real_name, role, is_engineer, is_active, branch_id FROM users WHERE real_name LIKE '%測試%' OR username LIKE '%test%' ORDER BY id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>測試相關帳號</h2><table border=1 cellpadding=5><tr><th>ID</th><th>帳號</th><th>姓名</th><th>角色</th><th>is_engineer</th><th>is_active</th><th>branch_id</th></tr>";
foreach ($rows as $r) {
    echo "<tr><td>{$r['id']}</td><td>{$r['username']}</td><td>{$r['real_name']}</td><td>{$r['role']}</td><td>" . ($r['is_engineer'] ? '✅ 是' : '❌ 否') . "</td><td>" . ($r['is_active'] ? '✅' : '❌') . "</td><td>{$r['branch_id']}</td></tr>";
}
echo "</table>";
