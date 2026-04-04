<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$total = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
echo "DB customer_files: {$total}\n";
$dirs = glob(__DIR__ . '/uploads/customers/*', GLOB_ONLYDIR);
echo "uploads/customers 目錄數: " . count($dirs) . "\n";
