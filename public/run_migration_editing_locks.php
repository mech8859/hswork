<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS editing_locks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        module VARCHAR(50) NOT NULL,
        record_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        locked_at DATETIME NOT NULL,
        heartbeat_at DATETIME NOT NULL,
        UNIQUE KEY uk_module_record_user (module, record_id, user_id),
        INDEX idx_heartbeat (heartbeat_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "editing_locks table created.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "Done.\n";
