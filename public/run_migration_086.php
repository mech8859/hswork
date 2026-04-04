<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE stocktakes ADD COLUMN stocktaker_id INT UNSIGNED DEFAULT NULL AFTER warehouse_id",
    "ALTER TABLE stocktakes ADD COLUMN stocktaker_name VARCHAR(50) DEFAULT NULL AFTER stocktaker_id",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) {
        echo (strpos($e->getMessage(), 'Duplicate') !== false ? "SKIP" : "ERROR: " . $e->getMessage()) . "\n";
    }
}
echo "Done.\n";
