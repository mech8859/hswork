<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (Auth::user()['role'] !== 'boss') { die('Unauthorized'); }

echo '<h2>Debug Staff Save</h2>';

// 1. 檢查 users 表結構
try {
    $db = Database::getInstance();

    echo '<h3>Users table columns:</h3>';
    $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo '<table border="1" cellpadding="4"><tr><th>Field</th><th>Type</th><th>Null</th></tr>';
    foreach ($cols as $c) {
        echo '<tr><td>' . $c['Field'] . '</td><td>' . $c['Type'] . '</td><td>' . $c['Null'] . '</td></tr>';
    }
    echo '</table>';

    // 2. 檢查 id=7 的資料
    $stmt = $db->prepare('SELECT id, username, role, custom_permissions FROM users WHERE id = 7');
    $stmt->execute();
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    echo '<h3>User #7:</h3>';
    echo '<pre>' . htmlspecialchars(print_r($u, true)) . '</pre>';

    // 3. 測試 update
    echo '<h3>Test update simulation:</h3>';
    $testPerms = json_encode(array('cases' => 'cases.manage', 'schedule' => 'schedule.manage'));
    echo 'Test JSON: ' . htmlspecialchars($testPerms) . '<br>';

    // 測試 SQL
    $testSql = "UPDATE users SET custom_permissions = ? WHERE id = 7";
    echo 'SQL: ' . htmlspecialchars($testSql) . '<br>';
    echo '<p style="color:green">DB connection OK</p>';

    // 4. 檢查 PHP error log
    echo '<h3>Recent PHP errors:</h3>';
    $logFile = __DIR__ . '/../logs/error.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $last = array_slice($lines, -20);
        echo '<pre style="background:#111;color:#0f0;padding:10px;font-size:11px">';
        foreach ($last as $line) { echo htmlspecialchars($line); }
        echo '</pre>';
    } else {
        echo '<p>No error log at ' . htmlspecialchars($logFile) . '</p>';
        // 嘗試其他位置
        $altLog = ini_get('error_log');
        echo '<p>PHP error_log setting: ' . htmlspecialchars($altLog ?: '(empty)') . '</p>';
    }

} catch (Exception $e) {
    echo '<p style="color:red;font-weight:bold">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
