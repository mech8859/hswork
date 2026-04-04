<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// 檢查當前 charset
$result = $db->query("SHOW CREATE TABLE customers")->fetch(PDO::FETCH_ASSOC);
echo "=== 當前表結構 ===\n";
// 只顯示 charset 相關
$create = $result['Create Table'];
if (preg_match('/CHARSET=(\w+)/', $create, $m)) {
    echo "Table charset: " . $m[1] . "\n";
}
if (preg_match('/COLLATE=(\w+)/', $create, $m)) {
    echo "Table collate: " . $m[1] . "\n";
}

// 檢查 name 欄位
$cols = $db->query("SHOW FULL COLUMNS FROM customers WHERE Field = 'name'")->fetch(PDO::FETCH_ASSOC);
echo "name column charset: " . ($cols['Collation'] ?? 'N/A') . "\n";
echo "name column type: " . ($cols['Type'] ?? 'N/A') . "\n\n";

// 改表和欄位為 utf8mb4
echo "=== 修改 charset ===\n";
try {
    $db->exec("ALTER TABLE customers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "OK: customers 改為 utf8mb4\n";
} catch (PDOException $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE customer_contacts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "OK: customer_contacts 改為 utf8mb4\n";
} catch (PDOException $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}

// 驗證
$cols2 = $db->query("SHOW FULL COLUMNS FROM customers WHERE Field = 'name'")->fetch(PDO::FETCH_ASSOC);
echo "\n修改後 name column charset: " . ($cols2['Collation'] ?? 'N/A') . "\n";
echo "完成！\n";
