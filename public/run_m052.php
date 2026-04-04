<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
try {
    $db->exec("ALTER TABLE cases ADD COLUMN IF NOT EXISTS contact_line_id VARCHAR(100) DEFAULT NULL COMMENT 'LINE ID' AFTER contact_person");
    echo "OK: contact_line_id added\n";
} catch (PDOException $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
