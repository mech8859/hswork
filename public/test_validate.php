<?php
if (!isset($_GET['token']) || $_GET['token'] !== 'hswork2026fix') die('no');
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "validate_password exists: " . (function_exists('validate_password') ? 'YES' : 'NO') . "\n";
if (function_exists('validate_password')) {
    echo "Test 'abc': " . (validate_password('abc') ?: 'OK') . "\n";
    echo "Test 'Abc12345': " . (validate_password('Abc12345') ?: 'OK') . "\n";
}

// 也檢查 DB 欄位
try {
    $db = Database::getInstance();
    $cols = $db->query("SHOW COLUMNS FROM users LIKE 'password%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "Column: " . $c['Field'] . " " . $c['Type'] . "\n";
    $cols2 = $db->query("SHOW COLUMNS FROM users LIKE 'must_change%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols2 as $c) echo "Column: " . $c['Field'] . " " . $c['Type'] . "\n";
} catch (Exception $e) {
    echo "DB: " . $e->getMessage() . "\n";
}
