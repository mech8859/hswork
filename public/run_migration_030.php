<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>Migration 030: 點工人員</h2>';
try {
    $sql = file_get_contents(__DIR__ . '/../database/migration_030_dispatch_workers.sql');
    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) $db->exec($stmt);
    }
    echo '<p style="color:green">✓ dispatch_workers + schedule_dispatch_workers 表已建立</p>';
} catch (Exception $e) {
    echo '<p style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '<br><a href="/schedule.php">返回工程行事曆</a>';
