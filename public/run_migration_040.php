<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
echo "=== Migration 040: dispatch_attendance ===\n\n";
$sql = file_get_contents(__DIR__ . '/../database/migration_040_dispatch_attendance.sql');
$db->exec($sql);
echo "[OK] dispatch_attendance 表建立完成\n\n完成\n";
