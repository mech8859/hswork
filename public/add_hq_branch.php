<?php
/**
 * 新增「總公司」據點
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

echo "<h2>新增總公司據點</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;}</style>";

// 檢查是否已存在
$stmt = $db->prepare("SELECT id FROM branches WHERE name = '總公司'");
$stmt->execute();
$exists = $stmt->fetch();

if ($exists) {
    echo "<p>總公司已存在 (ID: {$exists['id']})</p>";
} else {
    $stmt = $db->prepare("INSERT INTO branches (name, code, is_active, created_at) VALUES ('總公司', 'HQ', 1, NOW())");
    $stmt->execute();
    $newId = $db->lastInsertId();
    echo "<p class='ok'>已新增「總公司」據點 (ID: {$newId})</p>";
}

// 顯示所有據點
$stmt = $db->query("SELECT id, name, code, is_active FROM branches ORDER BY id");
echo "<h3>目前所有據點</h3><table border='1' cellpadding='6'><tr><th>ID</th><th>名稱</th><th>代碼</th><th>啟用</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['code']}</td><td>" . ($row['is_active'] ? '是' : '否') . "</td></tr>";
}
echo "</table>";

echo "<p><a href='/staff.php'>返回人員管理</a></p>";
